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
if (!in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get insurance companies
try {
    $insurance_companies = $db->query("SELECT * FROM insurance_companies ORDER BY company_name")->fetchAll();
} catch (Exception $e) {
    $insurance_companies = [];
}

// Get patient insurance policies
try {
    $patient_policies = $db->query("
        SELECT pip.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.patient_id,
               ic.company_name,
               ic.contact_email,
               ic.contact_phone
        FROM patient_insurance_policies pip
        LEFT JOIN patients p ON pip.patient_id = p.id
        LEFT JOIN insurance_companies ic ON pip.insurance_company_id = ic.id
        WHERE pip.is_active = 1
        ORDER BY pip.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $patient_policies = [];
}

// Get insurance claims
try {
    $insurance_claims = $db->query("
        SELECT ic.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.patient_id,
               comp.company_name,
               pip.policy_number,
               b.bill_id,
               b.total_amount
        FROM insurance_claims ic
        LEFT JOIN patients p ON ic.patient_id = p.id
        LEFT JOIN insurance_companies comp ON ic.insurance_company_id = comp.id
        LEFT JOIN patient_insurance_policies pip ON ic.policy_id = pip.id
        LEFT JOIN billing b ON ic.bill_id = b.id
        ORDER BY ic.created_at DESC
        LIMIT 50
    ")->fetchAll();
} catch (Exception $e) {
    $insurance_claims = [];
}

// Calculate statistics
$total_companies = count($insurance_companies);
$active_policies = count($patient_policies);
$pending_claims = count(array_filter($insurance_claims, function($claim) { 
    return $claim['status'] === 'pending'; 
}));
$approved_claims = count(array_filter($insurance_claims, function($claim) { 
    return $claim['status'] === 'approved'; 
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurance Management - Hospital CRM</title>
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="insurance.php" class="active"><i class="fas fa-shield-alt"></i> Insurance</a></li>
                <?php if ($user_role === 'admin'): ?>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <?php endif; ?>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-shield-alt"></i> Insurance Management</h1>
                    <p>Manage insurance companies, policies, and claims</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Insurance Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_companies; ?></h3>
                        <p>Insurance Companies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_policies; ?></h3>
                        <p>Active Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_claims; ?></h3>
                        <p>Pending Claims</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $approved_claims; ?></h3>
                        <p>Approved Claims</p>
                    </div>
                </div>
            </div>

            <!-- Insurance Navigation Tabs -->
            <div class="insurance-tabs">
                <button class="tab-btn active" onclick="showTab('companies')">
                    <i class="fas fa-building"></i> Insurance Companies
                </button>
                <button class="tab-btn" onclick="showTab('policies')">
                    <i class="fas fa-file-contract"></i> Patient Policies
                </button>
                <button class="tab-btn" onclick="showTab('claims')">
                    <i class="fas fa-file-medical"></i> Insurance Claims
                </button>
            </div>

            <!-- Insurance Companies Tab -->
            <div id="companies-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Insurance Companies</h3>
                        <button class="btn btn-primary" onclick="showAddCompanyModal()">
                            <i class="fas fa-plus"></i> Add Company
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($insurance_companies)): ?>
                            <p class="text-muted text-center">No insurance companies found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Company Name</th>
                                            <th>License Number</th>
                                            <th>Contact Email</th>
                                            <th>Contact Phone</th>
                                            <th>Coverage Types</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($insurance_companies as $company): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($company['company_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($company['license_number']); ?></td>
                                                <td><?php echo htmlspecialchars($company['contact_email']); ?></td>
                                                <td><?php echo htmlspecialchars($company['contact_phone']); ?></td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo htmlspecialchars($company['coverage_types']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $company['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $company['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="editCompany(<?php echo $company['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Patient Policies Tab -->
            <div id="policies-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-contract"></i> Patient Insurance Policies</h3>
                        <button class="btn btn-primary" onclick="showAddPolicyModal()">
                            <i class="fas fa-plus"></i> Add Policy
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patient_policies)): ?>
                            <p class="text-muted text-center">No patient policies found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Insurance Company</th>
                                            <th>Policy Number</th>
                                            <th>Coverage Amount</th>
                                            <th>Deductible</th>
                                            <th>Valid Until</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patient_policies as $policy): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($policy['patient_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($policy['patient_id']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($policy['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($policy['policy_number']); ?></td>
                                                <td><?php echo formatCurrency($policy['coverage_amount']); ?></td>
                                                <td><?php echo formatCurrency($policy['deductible_amount']); ?></td>
                                                <td><?php echo $policy['expiry_date'] ? date('d M Y', strtotime($policy['expiry_date'])) : 'N/A'; ?></td>
                                                <td>
                                                    <?php
                                                    $isExpired = $policy['expiry_date'] && strtotime($policy['expiry_date']) < time();
                                                    $statusClass = $isExpired ? 'danger' : ($policy['is_active'] ? 'success' : 'warning');
                                                    $statusText = $isExpired ? 'Expired' : ($policy['is_active'] ? 'Active' : 'Inactive');
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Insurance Claims Tab -->
            <div id="claims-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-medical"></i> Insurance Claims</h3>
                        <button class="btn btn-primary" onclick="showAddClaimModal()">
                            <i class="fas fa-plus"></i> New Claim
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($insurance_claims)): ?>
                            <p class="text-muted text-center">No insurance claims found.</p>
                        <?php else: ?>
                            <div class="claims-list">
                                <?php foreach ($insurance_claims as $claim): ?>
                                    <div class="claim-card">
                                        <div class="claim-header">
                                            <div class="claim-info">
                                                <h4>Claim #<?php echo htmlspecialchars($claim['claim_number']); ?></h4>
                                                <p><strong>Patient:</strong> <?php echo htmlspecialchars($claim['patient_name']); ?> (<?php echo htmlspecialchars($claim['patient_id']); ?>)</p>
                                                <p><strong>Insurance:</strong> <?php echo htmlspecialchars($claim['company_name']); ?></p>
                                                <p><strong>Policy:</strong> <?php echo htmlspecialchars($claim['policy_number']); ?></p>
                                                <p><strong>Bill:</strong> <?php echo htmlspecialchars($claim['bill_id']); ?> - <?php echo formatCurrency($claim['total_amount']); ?></p>
                                            </div>
                                            <div class="claim-status">
                                                <?php
                                                $statusColors = [
                                                    'pending' => 'warning',
                                                    'under_review' => 'info',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'paid' => 'success'
                                                ];
                                                $statusColor = $statusColors[$claim['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge badge-<?php echo $statusColor; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $claim['status'])); ?>
                                                </span>
                                                <div class="claim-amounts">
                                                    <small>Claimed: <?php echo formatCurrency($claim['claimed_amount']); ?></small>
                                                    <?php if ($claim['approved_amount']): ?>
                                                        <br><small>Approved: <?php echo formatCurrency($claim['approved_amount']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="claim-dates">
                                            <small class="text-muted">
                                                Submitted: <?php echo date('d M Y', strtotime($claim['created_at'])); ?>
                                                <?php if ($claim['processed_date']): ?>
                                                    | Processed: <?php echo date('d M Y', strtotime($claim['processed_date'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <?php if ($claim['notes']): ?>
                                            <div class="claim-notes">
                                                <strong>Notes:</strong> <?php echo htmlspecialchars($claim['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function showTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected tab content
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }

    function showAddCompanyModal() {
        alert('Add Insurance Company modal would open here');
    }

    function showAddPolicyModal() {
        alert('Add Patient Policy modal would open here');
    }

    function showAddClaimModal() {
        alert('Add Insurance Claim modal would open here');
    }

    function editCompany(id) {
        alert('Edit company with ID: ' + id);
    }
    </script>

    <style>
    .insurance-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
    }

    .tab-btn {
        background: none;
        border: none;
        padding: 12px 20px;
        cursor: pointer;
        font-size: 14px;
        color: #666;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
    }

    .tab-btn:hover {
        color: #2c3e50;
        background-color: #f8f9fa;
    }

    .tab-btn.active {
        color: #2c3e50;
        border-bottom-color: #3498db;
        font-weight: 600;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .claims-list {
        max-height: 600px;
        overflow-y: auto;
    }

    .claim-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        background: #fff;
    }

    .claim-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .claim-info h4 {
        color: #2c3e50;
        margin-bottom: 10px;
    }

    .claim-info p {
        margin: 5px 0;
        color: #666;
    }

    .claim-status {
        text-align: right;
    }

    .claim-amounts {
        margin-top: 10px;
    }

    .claim-dates {
        padding-top: 10px;
        border-top: 1px solid #f0f0f0;
    }

    .claim-notes {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        margin-top: 10px;
        color: #495057;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .badge {
        font-size: 0.8em;
        padding: 5px 10px;
    }
    </style>
</body>
</html>