<?php
session_start();
require_once 'config/database.php';

// Check if patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: patient-login.php');
    exit;
}

$db = new Database();
$message = '';
$patient_id = $_SESSION['patient_id'];

// Get patient information
$patient = $db->query("SELECT * FROM patients WHERE id = ?", [$patient_id])->fetch();

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'request_blood':
                $request_sql = "INSERT INTO blood_requests (patient_id, blood_group, component_type, units_needed, urgency_level, medical_reason, doctor_prescription, insurance_pre_auth, estimated_cost, requested_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')";
                $db->query($request_sql, [
                    $patient_id, $_POST['blood_group'], $_POST['component_type'], $_POST['units_needed'],
                    $_POST['urgency_level'], $_POST['medical_reason'], $_POST['doctor_prescription'],
                    $_POST['insurance_pre_auth'], $_POST['estimated_cost']
                ]);
                $message = "Blood request submitted successfully!";
                break;
                
            case 'register_as_donor':
                $donor_sql = "INSERT INTO blood_donors (patient_id, donor_id, blood_group, last_donation_date, total_donations, is_eligible, eligibility_notes, contact_preference, emergency_contact, medical_history, is_active) VALUES (?, ?, ?, NULL, 0, 1, ?, ?, ?, ?, 1)";
                $donor_id = 'DON' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
                $db->query($donor_sql, [
                    $patient_id, $donor_id, $patient['blood_group'], $_POST['eligibility_notes'],
                    $_POST['contact_preference'], $_POST['emergency_contact'], $_POST['medical_history']
                ]);
                $message = "Registered as blood donor successfully!";
                break;
                
            case 'pay_bill':
                $payment_sql = "INSERT INTO patient_payments (patient_id, bill_id, amount_paid, payment_method, payment_reference, payment_date, payment_status, processed_by) VALUES (?, ?, ?, ?, ?, NOW(), 'completed', 1)";
                $db->query($payment_sql, [
                    $patient_id, $_POST['bill_id'], $_POST['amount_paid'], $_POST['payment_method'], $_POST['payment_reference']
                ]);
                
                // Update bill status
                $db->query("UPDATE patient_bills SET payment_status = 'paid', paid_amount = paid_amount + ? WHERE id = ?", 
                          [$_POST['amount_paid'], $_POST['bill_id']]);
                
                $message = "Payment processed successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get patient's blood donation history
$my_donations = $db->query("
    SELECT bds.*, bd.donor_id, s.first_name as collector_name, s.last_name as collector_lastname
    FROM blood_donation_sessions bds
    JOIN blood_donors bd ON bds.donor_id = bd.id
    LEFT JOIN staff s ON bds.collected_by = s.user_id
    WHERE bd.patient_id = ?
    ORDER BY bds.collection_date DESC
", [$patient_id])->fetchAll();

// Get patient's blood usage history
$my_blood_usage = $db->query("
    SELECT bur.*, bi.bag_number, bi.blood_group, bi.component_type, bi.volume_ml,
           s.first_name as staff_name, s.last_name as staff_lastname,
           pb.total_amount, pb.payment_status
    FROM blood_usage_records bur
    JOIN blood_inventory bi ON bur.blood_bag_id = bi.id
    LEFT JOIN staff s ON bur.used_by = s.user_id
    LEFT JOIN patient_bills pb ON pb.patient_id = ? AND pb.service_type = 'blood_transfusion' AND DATE(pb.service_date) = DATE(bur.usage_date)
    WHERE bur.patient_id = ?
    ORDER BY bur.usage_date DESC
", [$patient_id, $patient_id])->fetchAll();

// Get patient's bills and charges
$my_bills = $db->query("
    SELECT pb.*, pi.policy_number, pi.coverage_percentage
    FROM patient_bills pb
    LEFT JOIN patient_insurance pi ON pb.patient_id = pi.patient_id AND pi.is_active = 1
    WHERE pb.patient_id = ?
    ORDER BY pb.service_date DESC
", [$patient_id])->fetchAll();

// Get blood requests
$my_requests = $db->query("
    SELECT br.*, pb.total_amount, pb.payment_status
    FROM blood_requests br
    LEFT JOIN patient_bills pb ON pb.patient_id = br.patient_id AND pb.service_type = 'blood_request' AND DATE(pb.service_date) = DATE(br.requested_date)
    WHERE br.patient_id = ?
    ORDER BY br.requested_date DESC
", [$patient_id])->fetchAll();

// Get insurance information
$insurance_info = $db->query("
    SELECT pi.*, ic.company_name, ic.contact_number as company_contact
    FROM patient_insurance pi
    JOIN insurance_companies ic ON pi.insurance_company_id = ic.id
    WHERE pi.patient_id = ? AND pi.is_active = 1
", [$patient_id])->fetchAll();

// Calculate statistics
$stats = [
    'total_donations' => count($my_donations),
    'total_volume_donated' => array_sum(array_column($my_donations, 'volume_collected')),
    'total_blood_received' => count($my_blood_usage),
    'total_volume_received' => array_sum(array_column($my_blood_usage, 'volume_used')),
    'outstanding_bills' => $db->query("SELECT SUM(total_amount - paid_amount) as total FROM patient_bills WHERE patient_id = ? AND payment_status != 'paid'", [$patient_id])->fetch()['total'] ?? 0,
    'last_donation_date' => !empty($my_donations) ? $my_donations[0]['collection_date'] : null
];

// Check donor eligibility
$donor_status = $db->query("SELECT * FROM blood_donors WHERE patient_id = ?", [$patient_id])->fetch();
$can_donate = false;
$eligibility_message = '';

if ($donor_status) {
    if ($donor_status['last_donation_date']) {
        $days_since_last = (new DateTime())->diff(new DateTime($donor_status['last_donation_date']))->days;
        if ($days_since_last >= 56) {
            $can_donate = true;
            $eligibility_message = "You are eligible to donate blood!";
        } else {
            $remaining_days = 56 - $days_since_last;
            $eligibility_message = "You can donate again in {$remaining_days} days.";
        }
    } else {
        $can_donate = true;
        $eligibility_message = "You are eligible to donate blood!";
    }
} else {
    $eligibility_message = "Please register as a blood donor first.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('My Blood Bank Portal');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .patient-portal {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .portal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }
        
        .patient-welcome {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .patient-id {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stats-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .stat-unit {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .eligibility-status {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid var(--secondary-color);
        }
        
        .eligible {
            border-left-color: var(--secondary-color);
            background: linear-gradient(135deg, var(--bg-card), #f0fdf4);
        }
        
        .not-eligible {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, var(--bg-card), #fffbeb);
        }
        
        .portal-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .portal-tab {
            padding: 12px 20px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            position: relative;
        }
        
        .portal-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .portal-tab-content {
            display: none;
            background: var(--bg-card);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-md);
        }
        
        .portal-tab-content.active {
            display: block;
        }
        
        .blood-history-item {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
            position: relative;
        }
        
        .donation-item {
            border-left-color: var(--secondary-color);
        }
        
        .usage-item {
            border-left-color: #f59e0b;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .history-title {
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .history-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .history-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            text-align: center;
            padding: 10px;
            background: var(--bg-card);
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .bill-item {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent-color);
        }
        
        .bill-unpaid {
            border-left-color: #dc2626;
            background: linear-gradient(135deg, var(--bg-card), #fef2f2);
        }
        
        .bill-paid {
            border-left-color: var(--secondary-color);
            background: linear-gradient(135deg, var(--bg-card), #f0fdf4);
        }
        
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .bill-amount {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .amount-unpaid {
            color: #dc2626;
        }
        
        .amount-paid {
            color: var(--secondary-color);
        }
        
        .payment-form {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-success { background: var(--secondary-color); }
        .btn-warning { background: var(--accent-color); }
        .btn-danger { background: #dc2626; }
        
        .insurance-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .insurance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }
        
        .insurance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .insurance-company {
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        .policy-number {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .coverage-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .coverage-item {
            text-align: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
        
        .request-form {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/patient-sidebar.php'; ?>
        
        <main class="main-content">
            <div class="patient-portal">
                <!-- Portal Header -->
                <div class="portal-header">
                    <div class="patient-welcome">
                        Welcome, <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>! 🩸
                    </div>
                    <div class="patient-id">
                        Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?> | 
                        Blood Group: <strong><?php echo htmlspecialchars($patient['blood_group']); ?></strong>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Dashboard -->
                <div class="stats-dashboard">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_donations']; ?></div>
                        <div class="stat-label">Total Donations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_volume_donated']; ?><span class="stat-unit">ml</span></div>
                        <div class="stat-label">Blood Donated</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_blood_received']; ?></div>
                        <div class="stat-label">Blood Received</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_volume_received']; ?><span class="stat-unit">ml</span></div>
                        <div class="stat-label">Volume Received</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">₹<?php echo number_format($stats['outstanding_bills']); ?></div>
                        <div class="stat-label">Outstanding Bills</div>
                    </div>
                </div>
                
                <!-- Donation Eligibility Status -->
                <div class="eligibility-status <?php echo $can_donate ? 'eligible' : 'not-eligible'; ?>">
                    <h3><i class="fas fa-heart"></i> Blood Donation Status</h3>
                    <p><?php echo $eligibility_message; ?></p>
                    <?php if ($stats['last_donation_date']): ?>
                        <small>Last Donation: <?php echo date('d-M-Y', strtotime($stats['last_donation_date'])); ?></small>
                    <?php endif; ?>
                </div>
                
                <!-- Portal Tabs -->
                <div class="portal-tabs">
                    <div class="portal-tab active" onclick="showPortalTab('my-donations')">
                        <i class="fas fa-hand-holding-heart"></i> My Donations
                    </div>
                    <div class="portal-tab" onclick="showPortalTab('my-usage')">
                        <i class="fas fa-syringe"></i> Blood Received
                    </div>
                    <div class="portal-tab" onclick="showPortalTab('my-bills')">
                        <i class="fas fa-file-invoice"></i> Bills & Payments
                    </div>
                    <div class="portal-tab" onclick="showPortalTab('blood-request')">
                        <i class="fas fa-plus-circle"></i> Request Blood
                    </div>
                    <div class="portal-tab" onclick="showPortalTab('insurance')">
                        <i class="fas fa-shield-alt"></i> Insurance
                    </div>
                    <div class="portal-tab" onclick="showPortalTab('register-donor')">
                        <i class="fas fa-user-plus"></i> Become Donor
                    </div>
                </div>
                
                <!-- My Donations Tab -->
                <div id="my-donations" class="portal-tab-content active">
                    <h2>My Blood Donation History</h2>
                    <?php if (empty($my_donations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-hand-holding-heart"></i>
                            <h3>No Donations Yet</h3>
                            <p>You haven't donated blood yet. Consider becoming a blood donor to save lives!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_donations as $donation): ?>
                            <div class="blood-history-item donation-item">
                                <div class="history-header">
                                    <div class="history-title">Blood Donation Session</div>
                                    <div class="history-date"><?php echo date('d-M-Y H:i', strtotime($donation['collection_date'])); ?></div>
                                </div>
                                <div class="history-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Volume Donated</div>
                                        <div class="detail-value"><?php echo $donation['volume_collected']; ?>ml</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Donation Type</div>
                                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $donation['donation_type'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Hemoglobin</div>
                                        <div class="detail-value"><?php echo $donation['hemoglobin_level']; ?> g/dL</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Blood Pressure</div>
                                        <div class="detail-value"><?php echo $donation['blood_pressure']; ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Collected By</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($donation['collector_name'] . ' ' . $donation['collector_lastname']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value"><?php echo ucfirst($donation['status']); ?></div>
                                    </div>
                                </div>
                                <?php if ($donation['notes']): ?>
                                    <div style="margin-top: 15px; padding: 10px; background: var(--bg-card); border-radius: 5px;">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($donation['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- My Blood Usage Tab -->
                <div id="my-usage" class="portal-tab-content">
                    <h2>Blood Received History</h2>
                    <?php if (empty($my_blood_usage)): ?>
                        <div class="empty-state">
                            <i class="fas fa-syringe"></i>
                            <h3>No Blood Transfusions</h3>
                            <p>You haven't received any blood transfusions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_blood_usage as $usage): ?>
                            <div class="blood-history-item usage-item">
                                <div class="history-header">
                                    <div class="history-title">Blood Transfusion</div>
                                    <div class="history-date"><?php echo date('d-M-Y H:i', strtotime($usage['usage_date'])); ?></div>
                                </div>
                                <div class="history-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Blood Bag</div>
                                        <div class="detail-value"><?php echo $usage['bag_number']; ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Blood Group</div>
                                        <div class="detail-value"><?php echo $usage['blood_group']; ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Component</div>
                                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $usage['component_type'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Volume Used</div>
                                        <div class="detail-value"><?php echo $usage['volume_used']; ?>ml</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Usage Type</div>
                                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $usage['usage_type'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Cross Match</div>
                                        <div class="detail-value"><?php echo ucfirst($usage['cross_match_result']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Administered By</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($usage['staff_name'] . ' ' . $usage['staff_lastname']); ?></div>
                                    </div>
                                    <?php if ($usage['total_amount']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Bill Amount</div>
                                            <div class="detail-value">₹<?php echo number_format($usage['total_amount']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 15px; padding: 10px; background: var(--bg-card); border-radius: 5px;">
                                    <strong>Patient Condition:</strong> <?php echo htmlspecialchars($usage['patient_condition']); ?>
                                    <?php if ($usage['adverse_reactions']): ?>
                                        <br><strong>Adverse Reactions:</strong> <?php echo htmlspecialchars($usage['adverse_reactions']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Bills & Payments Tab -->
                <div id="my-bills" class="portal-tab-content">
                    <h2>Bills & Payments</h2>
                    <?php if (empty($my_bills)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <h3>No Bills</h3>
                            <p>You don't have any bills currently.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_bills as $bill): ?>
                            <div class="bill-item <?php echo $bill['payment_status'] === 'paid' ? 'bill-paid' : 'bill-unpaid'; ?>">
                                <div class="bill-header">
                                    <div>
                                        <h3><?php echo ucfirst(str_replace('_', ' ', $bill['service_type'])); ?></h3>
                                        <p>Date: <?php echo date('d-M-Y', strtotime($bill['service_date'])); ?></p>
                                        <p>Bill #: <?php echo $bill['bill_number']; ?></p>
                                    </div>
                                    <div class="bill-amount <?php echo $bill['payment_status'] === 'paid' ? 'amount-paid' : 'amount-unpaid'; ?>">
                                        ₹<?php echo number_format($bill['total_amount']); ?>
                                        <div style="font-size: 0.8rem; font-weight: normal;">
                                            <?php echo ucfirst($bill['payment_status']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-grid">
                                    <div>
                                        <strong>Service Details:</strong> <?php echo htmlspecialchars($bill['service_details']); ?>
                                    </div>
                                    <div>
                                        <strong>Base Amount:</strong> ₹<?php echo number_format($bill['base_amount']); ?>
                                    </div>
                                    <?php if ($bill['insurance_coverage'] > 0): ?>
                                        <div>
                                            <strong>Insurance Coverage:</strong> ₹<?php echo number_format($bill['insurance_coverage']); ?>
                                            (<?php echo $bill['coverage_percentage']; ?>%)
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong>Tax:</strong> ₹<?php echo number_format($bill['tax_amount']); ?>
                                    </div>
                                    <div>
                                        <strong>Paid Amount:</strong> ₹<?php echo number_format($bill['paid_amount']); ?>
                                    </div>
                                    <div>
                                        <strong>Outstanding:</strong> ₹<?php echo number_format($bill['total_amount'] - $bill['paid_amount']); ?>
                                    </div>
                                </div>
                                
                                <?php if ($bill['payment_status'] !== 'paid' && ($bill['total_amount'] - $bill['paid_amount']) > 0): ?>
                                    <div class="payment-form">
                                        <h4>Make Payment</h4>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="pay_bill">
                                            <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                            <div class="form-grid">
                                                <div class="form-group">
                                                    <label>Amount to Pay</label>
                                                    <input type="number" name="amount_paid" step="0.01" max="<?php echo $bill['total_amount'] - $bill['paid_amount']; ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Payment Method</label>
                                                    <select name="payment_method" required>
                                                        <option value="card">Credit/Debit Card</option>
                                                        <option value="upi">UPI</option>
                                                        <option value="bank_transfer">Bank Transfer</option>
                                                        <option value="cash">Cash</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Payment Reference</label>
                                                    <input type="text" name="payment_reference" placeholder="Transaction ID/Reference">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-success">Pay Now</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Blood Request Tab -->
                <div id="blood-request" class="portal-tab-content">
                    <h2>Request Blood</h2>
                    <div class="request-form">
                        <form method="POST">
                            <input type="hidden" name="action" value="request_blood">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Blood Group Needed</label>
                                    <select name="blood_group" required>
                                        <option value="<?php echo $patient['blood_group']; ?>" selected><?php echo $patient['blood_group']; ?> (Your Group)</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Component Type</label>
                                    <select name="component_type" required>
                                        <option value="whole_blood">Whole Blood</option>
                                        <option value="red_blood_cells">Red Blood Cells</option>
                                        <option value="platelets">Platelets</option>
                                        <option value="plasma">Plasma</option>
                                        <option value="cryoprecipitate">Cryoprecipitate</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Units Needed</label>
                                    <input type="number" name="units_needed" min="1" max="10" required>
                                </div>
                                <div class="form-group">
                                    <label>Urgency Level</label>
                                    <select name="urgency_level" required>
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent (within 24 hours)</option>
                                        <option value="emergency">Emergency (immediate)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Medical Reason</label>
                                    <textarea name="medical_reason" rows="3" required placeholder="Reason for blood requirement"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Doctor's Prescription</label>
                                    <input type="text" name="doctor_prescription" required placeholder="Doctor's name and prescription details">
                                </div>
                                <div class="form-group">
                                    <label>Insurance Pre-Authorization</label>
                                    <input type="text" name="insurance_pre_auth" placeholder="Pre-auth number (if applicable)">
                                </div>
                                <div class="form-group">
                                    <label>Estimated Cost</label>
                                    <input type="number" name="estimated_cost" step="0.01" placeholder="Estimated cost in ₹">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning">Submit Blood Request</button>
                        </form>
                    </div>
                    
                    <!-- Previous Requests -->
                    <h3>My Blood Requests</h3>
                    <?php if (empty($my_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No Requests</h3>
                            <p>You haven't made any blood requests yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_requests as $request): ?>
                            <div class="blood-history-item">
                                <div class="history-header">
                                    <div class="history-title">Blood Request - <?php echo $request['blood_group']; ?></div>
                                    <div class="history-date"><?php echo date('d-M-Y H:i', strtotime($request['requested_date'])); ?></div>
                                </div>
                                <div class="history-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Component</div>
                                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $request['component_type'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Units</div>
                                        <div class="detail-value"><?php echo $request['units_needed']; ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Urgency</div>
                                        <div class="detail-value"><?php echo ucfirst($request['urgency_level']); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value"><?php echo ucfirst($request['status']); ?></div>
                                    </div>
                                    <?php if ($request['total_amount']): ?>
                                        <div class="detail-item">
                                            <div class="detail-label">Amount</div>
                                            <div class="detail-value">₹<?php echo number_format($request['total_amount']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-top: 15px; padding: 10px; background: var(--bg-card); border-radius: 5px;">
                                    <strong>Medical Reason:</strong> <?php echo htmlspecialchars($request['medical_reason']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Insurance Tab -->
                <div id="insurance" class="portal-tab-content">
                    <h2>Insurance Information</h2>
                    <?php if (empty($insurance_info)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <h3>No Insurance</h3>
                            <p>You don't have any active insurance policies. Consider adding insurance for better coverage.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($insurance_info as $insurance): ?>
                            <div class="insurance-card">
                                <div class="insurance-header">
                                    <div class="insurance-company"><?php echo htmlspecialchars($insurance['company_name']); ?></div>
                                    <div class="policy-number">Policy: <?php echo htmlspecialchars($insurance['policy_number']); ?></div>
                                </div>
                                <div class="coverage-info">
                                    <div class="coverage-item">
                                        <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $insurance['coverage_percentage']; ?>%</div>
                                        <div style="font-size: 0.9rem;">Coverage</div>
                                    </div>
                                    <div class="coverage-item">
                                        <div style="font-size: 1.2rem; font-weight: bold;">₹<?php echo number_format($insurance['coverage_limit']); ?></div>
                                        <div style="font-size: 0.9rem;">Max Limit</div>
                                    </div>
                                    <div class="coverage-item">
                                        <div style="font-size: 1.2rem; font-weight: bold;">₹<?php echo number_format($insurance['deductible']); ?></div>
                                        <div style="font-size: 0.9rem;">Deductible</div>
                                    </div>
                                    <div class="coverage-item">
                                        <div style="font-size: 1.2rem; font-weight: bold;"><?php echo date('d-M-Y', strtotime($insurance['expiry_date'])); ?></div>
                                        <div style="font-size: 0.9rem;">Expires</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Register as Donor Tab -->
                <div id="register-donor" class="portal-tab-content">
                    <h2>Become a Blood Donor</h2>
                    <?php if ($donor_status): ?>
                        <div class="alert alert-success">
                            <strong>You are already registered as a blood donor!</strong><br>
                            Donor ID: <?php echo $donor_status['donor_id']; ?><br>
                            Total Donations: <?php echo $donor_status['total_donations']; ?><br>
                            Status: <?php echo $donor_status['is_eligible'] ? 'Eligible' : 'Not Eligible'; ?>
                        </div>
                    <?php else: ?>
                        <div class="request-form">
                            <div class="alert alert-warning">
                                <strong>Help Save Lives!</strong> By becoming a blood donor, you can help save up to 3 lives with each donation.
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="register_as_donor">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Contact Preference</label>
                                        <select name="contact_preference" required>
                                            <option value="phone">Phone</option>
                                            <option value="email">Email</option>
                                            <option value="sms">SMS</option>
                                            <option value="none">No Contact</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Emergency Contact</label>
                                        <input type="text" name="emergency_contact" required placeholder="Emergency contact name and number">
                                    </div>
                                    <div class="form-group">
                                        <label>Medical History</label>
                                        <textarea name="medical_history" rows="3" placeholder="Any medical conditions, medications, or allergies"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Additional Notes</label>
                                        <textarea name="eligibility_notes" rows="2" placeholder="Any additional information"></textarea>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <strong>Donor Requirements:</strong>
                                    <ul>
                                        <li>Age: 18-65 years</li>
                                        <li>Weight: At least 50 kg</li>
                                        <li>Hemoglobin: At least 12.5 g/dL</li>
                                        <li>No recent illness or medication</li>
                                        <li>Wait 56 days between donations</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="btn btn-success">Register as Blood Donor</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function showPortalTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.portal-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.portal-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Auto-refresh eligibility status
        setInterval(function() {
            // Check if user can donate based on last donation date
            const lastDonationElement = document.querySelector('.eligibility-status small');
            if (lastDonationElement) {
                const lastDonationText = lastDonationElement.textContent;
                if (lastDonationText.includes('Last Donation:')) {
                    // Calculate days since last donation
                    const dateMatch = lastDonationText.match(/(\d{2}-\w{3}-\d{4})/);
                    if (dateMatch) {
                        const lastDate = new Date(dateMatch[1]);
                        const today = new Date();
                        const daysDiff = Math.floor((today - lastDate) / (1000 * 60 * 60 * 24));
                        
                        const eligibilityStatus = document.querySelector('.eligibility-status');
                        const eligibilityMessage = eligibilityStatus.querySelector('p');
                        
                        if (daysDiff >= 56) {
                            eligibilityStatus.className = 'eligibility-status eligible';
                            eligibilityMessage.textContent = 'You are eligible to donate blood!';
                        } else {
                            const remainingDays = 56 - daysDiff;
                            eligibilityStatus.className = 'eligibility-status not-eligible';
                            eligibilityMessage.textContent = `You can donate again in ${remainingDays} days.`;
                        }
                    }
                }
            }
        }, 60000); // Check every minute
    </script>
</body>
</html>