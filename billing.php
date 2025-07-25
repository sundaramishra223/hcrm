<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'generate_bill':
                    $patient_id = $_POST['patient_id'];
                    $bill_date = $_POST['bill_date'] ?? date('Y-m-d');
                    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
                    $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
                    $notes = $_POST['notes'] ?? '';
                    
                    // Generate unique bill ID
                    $bill_id = 'BILL' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                    
                    // Get all unbilled services for the patient
                    $services = [];
                    $total_amount = 0;
                    
                    // 1. Get unpaid appointments (consultation fees)
                    $appointments = $db->query("
                        SELECT a.id, a.appointment_date, a.appointment_time, 
                               d.doctor_name, d.consultation_fee, a.appointment_type
                        FROM appointments a
                        LEFT JOIN doctors d ON a.doctor_id = d.id
                        WHERE a.patient_id = ? AND a.status = 'completed' 
                        AND a.id NOT IN (SELECT appointment_id FROM billing WHERE appointment_id IS NOT NULL)
                        ORDER BY a.appointment_date DESC
                    ", [$patient_id])->fetchAll();
                    
                    foreach ($appointments as $appointment) {
                        $services[] = [
                            'type' => 'consultation',
                            'description' => 'Consultation - Dr. ' . $appointment['doctor_name'] . ' (' . ucfirst($appointment['appointment_type']) . ')',
                            'date' => $appointment['appointment_date'],
                            'amount' => $appointment['consultation_fee'],
                            'reference_id' => $appointment['id']
                        ];
                        $total_amount += $appointment['consultation_fee'];
                    }
                    
                    // 2. Get unpaid pharmacy sales
                    $pharmacy_sales = $db->query("
                        SELECT ps.id, ps.sale_date, ps.total_amount, ps.payment_status,
                               GROUP_CONCAT(CONCAT(p.medicine_name, ' (', psi.quantity, ')') SEPARATOR ', ') as medicines
                        FROM pharmacy_sales ps
                        LEFT JOIN pharmacy_sale_items psi ON ps.id = psi.sale_id
                        LEFT JOIN pharmacy p ON psi.medicine_id = p.id
                        WHERE ps.patient_id = ? AND ps.payment_status != 'paid'
                        AND ps.id NOT IN (SELECT pharmacy_sale_id FROM billing WHERE pharmacy_sale_id IS NOT NULL)
                        GROUP BY ps.id
                        ORDER BY ps.sale_date DESC
                    ", [$patient_id])->fetchAll();
                    
                    foreach ($pharmacy_sales as $sale) {
                        $services[] = [
                            'type' => 'pharmacy',
                            'description' => 'Medicines: ' . $sale['medicines'],
                            'date' => $sale['sale_date'],
                            'amount' => $sale['total_amount'],
                            'reference_id' => $sale['id']
                        ];
                        $total_amount += $sale['total_amount'];
                    }
                    
                    // 3. Get unpaid lab tests
                    $lab_tests = $db->query("
                        SELECT lt.id, lt.test_date, l.test_name, l.price, lt.status
                        FROM lab_tests lt
                        LEFT JOIN laboratory l ON lt.test_id = l.id
                        WHERE lt.patient_id = ? AND lt.status IN ('completed', 'in_progress')
                        AND lt.id NOT IN (SELECT lab_test_id FROM billing WHERE lab_test_id IS NOT NULL)
                        ORDER BY lt.test_date DESC
                    ", [$patient_id])->fetchAll();
                    
                    foreach ($lab_tests as $test) {
                        $services[] = [
                            'type' => 'lab_test',
                            'description' => 'Lab Test: ' . $test['test_name'],
                            'date' => $test['test_date'],
                            'amount' => $test['price'],
                            'reference_id' => $test['id']
                        ];
                        $total_amount += $test['price'];
                    }
                    
                    // Calculate discount and final amount
                    $discount_amount = ($total_amount * $discount_percentage) / 100;
                    $final_amount = $total_amount - $discount_amount;
                    
                    if (empty($services)) {
                        $error_message = "No unbilled services found for this patient.";
                    } else {
                        // Create the main bill record
                        $db->query("
                            INSERT INTO billing (
                                bill_id, patient_id, bill_date, due_date, 
                                subtotal_amount, discount_percentage, discount_amount, 
                                total_amount, paid_amount, balance_amount, 
                                payment_status, notes, created_by, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending', ?, ?, NOW())
                        ", [
                            $bill_id, $patient_id, $bill_date, $due_date,
                            $total_amount, $discount_percentage, $discount_amount,
                            $final_amount, $final_amount, $notes, $_SESSION['user_id']
                        ]);
                        
                        $billing_id = $db->lastInsertId();
                        
                        // Create bill items for each service
                        foreach ($services as $service) {
                            $db->query("
                                INSERT INTO bill_items (
                                    bill_id, service_type, description, service_date, 
                                    amount, reference_id, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ", [
                                $billing_id, $service['type'], $service['description'],
                                $service['date'], $service['amount'], $service['reference_id']
                            ]);
                        }
                        
                        // Update the main billing table with reference IDs
                        foreach ($appointments as $appointment) {
                            $db->query("UPDATE billing SET appointment_id = ? WHERE id = ?", [$appointment['id'], $billing_id]);
                        }
                        
                        $success_message = "Bill generated successfully! Bill ID: $bill_id (Total: " . formatCurrency($final_amount) . ")";
                    }
                    break;
                    
                case 'record_payment':
                    $bill_id = $_POST['bill_id'];
                    $payment_amount = floatval($_POST['payment_amount']);
                    $payment_method = $_POST['payment_method'];
                    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
                    $payment_notes = $_POST['payment_notes'] ?? '';
                    
                    // Get current bill details
                    $bill = $db->query("SELECT * FROM billing WHERE id = ?", [$bill_id])->fetch();
                    
                    if ($bill) {
                        $new_paid_amount = $bill['paid_amount'] + $payment_amount;
                        $new_balance = $bill['total_amount'] - $new_paid_amount;
                        
                        // Determine payment status
                        if ($new_balance <= 0) {
                            $payment_status = 'paid';
                            $new_balance = 0;
                        } elseif ($new_paid_amount > 0) {
                            $payment_status = 'partial';
                        } else {
                            $payment_status = 'pending';
                        }
                        
                        // Update billing record
                        $db->query("
                            UPDATE billing 
                            SET paid_amount = ?, balance_amount = ?, payment_status = ?, 
                                payment_method = ?, payment_date = ?, updated_at = NOW()
                            WHERE id = ?
                        ", [$new_paid_amount, $new_balance, $payment_status, $payment_method, $payment_date, $bill_id]);
                        
                        // Record payment transaction
                        $db->query("
                            INSERT INTO payment_transactions (
                                bill_id, payment_amount, payment_method, payment_date, 
                                notes, recorded_by, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ", [$bill_id, $payment_amount, $payment_method, $payment_date, $payment_notes, $_SESSION['user_id']]);
                        
                        $success_message = "Payment recorded successfully! Amount: " . formatCurrency($payment_amount);
                    } else {
                        $error_message = "Bill not found.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get patients for dropdown
try {
    $patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY first_name, last_name")->fetchAll();
} catch (Exception $e) {
    $patients = [];
}

// Get recent bills
try {
    $bills = $db->query("
        SELECT b.*, 
               p.patient_id, p.first_name, p.last_name 
        FROM billing b 
        LEFT JOIN patients p ON b.patient_id = p.id 
        ORDER BY b.created_at DESC 
        LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {
    $bills = [];
}

// Get billing statistics
try {
    $stats = [];
    
    // Total bills this month
    $result = $db->query("SELECT COUNT(*) as count FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['total_bills'] = $result['count'];
    
    // Total revenue this month
    $result = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['total_revenue'] = $result['revenue'];
    
    // Paid amount this month
    $result = $db->query("SELECT COALESCE(SUM(paid_amount), 0) as paid FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['paid_amount'] = $result['paid'];
    
    // Pending amount
    $result = $db->query("SELECT COALESCE(SUM(balance_amount), 0) as pending FROM billing WHERE payment_status IN ('pending', 'partial')")->fetch();
    $stats['pending_amount'] = $result['pending'];
    
    // Overdue bills
    $result = $db->query("SELECT COUNT(*) as count FROM billing WHERE payment_status IN ('pending', 'partial') AND due_date < CURDATE()")->fetch();
    $stats['overdue_bills'] = $result['count'];
    
} catch (Exception $e) {
    $stats = [
        'total_bills' => 0,
        'total_revenue' => 0,
        'paid_amount' => 0,
        'pending_amount' => 0,
        'overdue_bills' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                    <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'lab_technician', 'intern_lab', 'intern_doctor'])): ?>
                    <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                    <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <?php endif; ?>
                
                <li><a href="billing.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="insurance.php"><i class="fas fa-shield-alt"></i> Insurance</a></li>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                    <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
                
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-file-invoice-dollar"></i> Billing Management</h1>
                    <p>Automatic billing system with service aggregation</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="showGenerateBillModal()">
                        <i class="fas fa-plus"></i> Generate Bill
                    </button>
                    <button class="btn btn-success" onclick="showPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i> Record Payment
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Billing Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_bills']); ?></h3>
                    <p><i class="fas fa-file-invoice"></i> Total Bills This Month</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                    <p><i class="fas fa-chart-line"></i> Total Revenue</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['paid_amount']); ?></h3>
                    <p><i class="fas fa-check-circle"></i> Paid Amount</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['pending_amount']); ?></h3>
                    <p><i class="fas fa-clock"></i> Pending Amount</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['overdue_bills']); ?></h3>
                    <p><i class="fas fa-exclamation-triangle"></i> Overdue Bills</p>
                </div>
            </div>

            <!-- Recent Bills -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Recent Bills</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($bills)): ?>
                        <p class="text-muted text-center">No bills found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Bill ID</th>
                                        <th>Patient</th>
                                        <th>Bill Date</th>
                                        <th>Subtotal</th>
                                        <th>Discount</th>
                                        <th>Total Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Payment Status</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills as $bill): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($bill['patient_id'] . ' - ' . $bill['first_name'] . ' ' . $bill['last_name']); ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></td>
                                            <td><?php echo formatCurrency($bill['subtotal_amount'] ?? $bill['total_amount']); ?></td>
                                            <td>
                                                <?php if ($bill['discount_percentage'] > 0): ?>
                                                    <?php echo $bill['discount_percentage']; ?>% (<?php echo formatCurrency($bill['discount_amount'] ?? 0); ?>)
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                            <td><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                            <td><?php echo formatCurrency($bill['balance_amount']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $bill['payment_status'] == 'paid' ? 'success' : 
                                                        ($bill['payment_status'] == 'pending' ? 'warning' : 
                                                        ($bill['payment_status'] == 'partial' ? 'info' : 'danger')); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($bill['payment_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($bill['due_date']): ?>
                                                    <?php 
                                                    $due_date = new DateTime($bill['due_date']);
                                                    $today = new DateTime();
                                                    if ($due_date < $today && $bill['payment_status'] != 'paid'): ?>
                                                        <span class="badge badge-danger"><?php echo date('d M Y', strtotime($bill['due_date'])); ?></span>
                                                    <?php else: ?>
                                                        <?php echo date('d M Y', strtotime($bill['due_date'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewBillDetails(<?php echo $bill['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($bill['balance_amount'] > 0): ?>
                                                    <button class="btn btn-sm btn-success" onclick="recordPayment(<?php echo $bill['id']; ?>, '<?php echo $bill['bill_id']; ?>', <?php echo $bill['balance_amount']; ?>)">
                                                        <i class="fas fa-money-bill-wave"></i> Pay
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Generate Bill Modal -->
    <div id="generateBillModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Generate Automatic Bill</h3>
                <span class="close" onclick="hideGenerateBillModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="generate_bill">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="patient_id">Select Patient:</label>
                        <select name="patient_id" id="patient_id" class="form-control" required onchange="previewServices()">
                            <option value="">-- Select Patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="servicesPreview" style="display: none;">
                        <div class="form-group">
                            <label>Unbilled Services:</label>
                            <div id="servicesList" class="services-preview">
                                <!-- Services will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="bill_date">Bill Date:</label>
                            <input type="date" name="bill_date" id="bill_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="due_date">Due Date:</label>
                            <input type="date" name="due_date" id="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="discount_percentage">Discount (%):</label>
                        <input type="number" name="discount_percentage" id="discount_percentage" class="form-control" min="0" max="100" step="0.01" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <div class="billing-summary" id="billingSummary" style="display: none;">
                        <h5>Billing Summary:</h5>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotalAmount">₹0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Discount:</span>
                            <span id="discountAmount">₹0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span><strong>Total Amount:</strong></span>
                            <span id="totalAmount"><strong>₹0.00</strong></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideGenerateBillModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Bill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-money-bill-wave"></i> Record Payment</h3>
                <span class="close" onclick="hidePaymentModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="bill_id" id="payment_bill_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="payment_bill_select">Select Bill:</label>
                        <select name="bill_id" id="payment_bill_select" class="form-control" required onchange="updatePaymentDetails()">
                            <option value="">-- Select Bill --</option>
                            <?php foreach ($bills as $bill): ?>
                                <?php if ($bill['balance_amount'] > 0): ?>
                                    <option value="<?php echo $bill['id']; ?>" data-balance="<?php echo $bill['balance_amount']; ?>" data-bill-id="<?php echo $bill['bill_id']; ?>">
                                        <?php echo htmlspecialchars($bill['bill_id'] . ' - ' . $bill['first_name'] . ' ' . $bill['last_name'] . ' (Balance: ' . formatCurrency($bill['balance_amount']) . ')'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="payment_amount">Payment Amount:</label>
                            <input type="number" name="payment_amount" id="payment_amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="payment_method">Payment Method:</label>
                            <select name="payment_method" id="payment_method" class="form-control" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="upi">UPI</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Payment Date:</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_notes">Notes:</label>
                        <textarea name="payment_notes" id="payment_notes" class="form-control" rows="3" placeholder="Payment reference, transaction ID, etc..."></textarea>
                    </div>
                    
                    <div id="paymentSummary" class="payment-summary" style="display: none;">
                        <div class="summary-row">
                            <span>Outstanding Balance:</span>
                            <span id="outstandingBalance">₹0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Payment Amount:</span>
                            <span id="paymentAmountDisplay">₹0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span><strong>Remaining Balance:</strong></span>
                            <span id="remainingBalance"><strong>₹0.00</strong></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hidePaymentModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showGenerateBillModal() {
            document.getElementById('generateBillModal').style.display = 'block';
        }

        function hideGenerateBillModal() {
            document.getElementById('generateBillModal').style.display = 'none';
            document.getElementById('servicesPreview').style.display = 'none';
            document.getElementById('billingSummary').style.display = 'none';
        }

        function showPaymentModal() {
            document.getElementById('paymentModal').style.display = 'block';
        }

        function hidePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        function recordPayment(billId, billIdText, balance) {
            document.getElementById('payment_bill_id').value = billId;
            document.getElementById('payment_bill_select').value = billId;
            document.getElementById('payment_amount').value = balance.toFixed(2);
            updatePaymentDetails();
            showPaymentModal();
        }

        // Preview unbilled services for selected patient
        async function previewServices() {
            const patientId = document.getElementById('patient_id').value;
            if (!patientId) {
                document.getElementById('servicesPreview').style.display = 'none';
                document.getElementById('billingSummary').style.display = 'none';
                return;
            }

            try {
                const response = await fetch('get_patient_services.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'patient_id=' + patientId
                });
                
                const data = await response.json();
                
                if (data.services && data.services.length > 0) {
                    let servicesHtml = '<div class="services-list">';
                    let subtotal = 0;
                    
                    data.services.forEach(service => {
                        subtotal += parseFloat(service.amount);
                        servicesHtml += `
                            <div class="service-item">
                                <div class="service-details">
                                    <strong>${service.description}</strong>
                                    <small>Date: ${service.date}</small>
                                </div>
                                <div class="service-amount">₹${parseFloat(service.amount).toFixed(2)}</div>
                            </div>
                        `;
                    });
                    
                    servicesHtml += '</div>';
                    document.getElementById('servicesList').innerHTML = servicesHtml;
                    document.getElementById('servicesPreview').style.display = 'block';
                    
                    // Update billing summary
                    updateBillingSummary(subtotal);
                } else {
                    document.getElementById('servicesList').innerHTML = '<p class="text-muted">No unbilled services found for this patient.</p>';
                    document.getElementById('servicesPreview').style.display = 'block';
                    document.getElementById('billingSummary').style.display = 'none';
                }
            } catch (error) {
                console.error('Error fetching services:', error);
                document.getElementById('servicesList').innerHTML = '<p class="text-danger">Error loading services.</p>';
                document.getElementById('servicesPreview').style.display = 'block';
            }
        }

        // Update billing summary
        function updateBillingSummary(subtotal) {
            const discountPercentage = parseFloat(document.getElementById('discount_percentage').value) || 0;
            const discountAmount = (subtotal * discountPercentage) / 100;
            const totalAmount = subtotal - discountAmount;

            document.getElementById('subtotalAmount').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('discountAmount').textContent = '₹' + discountAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = '₹' + totalAmount.toFixed(2);
            document.getElementById('billingSummary').style.display = 'block';
        }

        // Update payment details
        function updatePaymentDetails() {
            const select = document.getElementById('payment_bill_select');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const balance = parseFloat(selectedOption.dataset.balance);
                const paymentAmount = parseFloat(document.getElementById('payment_amount').value) || 0;
                const remainingBalance = balance - paymentAmount;

                document.getElementById('outstandingBalance').textContent = '₹' + balance.toFixed(2);
                document.getElementById('paymentAmountDisplay').textContent = '₹' + paymentAmount.toFixed(2);
                document.getElementById('remainingBalance').textContent = '₹' + Math.max(0, remainingBalance).toFixed(2);
                document.getElementById('paymentSummary').style.display = 'block';
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Update billing summary when discount changes
            document.getElementById('discount_percentage').addEventListener('input', function() {
                const subtotalText = document.getElementById('subtotalAmount').textContent;
                const subtotal = parseFloat(subtotalText.replace('₹', ''));
                if (!isNaN(subtotal)) {
                    updateBillingSummary(subtotal);
                }
            });

            // Update payment summary when amount changes
            document.getElementById('payment_amount').addEventListener('input', updatePaymentDetails);
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const generateModal = document.getElementById('generateBillModal');
            const paymentModal = document.getElementById('paymentModal');
            
            if (event.target === generateModal) {
                hideGenerateBillModal();
            }
            if (event.target === paymentModal) {
                hidePaymentModal();
            }
        }

        // View bill details
        function viewBillDetails(billId) {
            // This would open a detailed view of the bill
            window.open('bill_details.php?id=' + billId, '_blank');
        }
    </script>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
            text-align: right;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .col-md-6 {
            flex: 0 0 48%;
        }

        .services-preview {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }

        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        .service-details strong {
            display: block;
            color: #333;
        }

        .service-details small {
            color: #666;
        }

        .service-amount {
            font-weight: bold;
            color: #28a745;
        }

        .billing-summary, .payment-summary {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .summary-row.total {
            border-top: 1px solid #dee2e6;
            padding-top: 8px;
            margin-top: 8px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</body>
</html>