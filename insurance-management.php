<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$message = '';

// Get user role and permissions
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'create_claim':
                // Create insurance claim
                $claim_sql = "INSERT INTO insurance_claims (patient_id, policy_id, claim_number, claim_type, service_type, service_date, claim_amount, submitted_documents, doctor_reference, diagnosis_code, treatment_details, estimated_coverage, claim_status, submitted_by, submitted_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())";
                $claim_number = 'CLM' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $claim_id = $db->query($claim_sql, [
                    $_POST['patient_id'], $_POST['policy_id'], $claim_number, $_POST['claim_type'],
                    $_POST['service_type'], $_POST['service_date'], $_POST['claim_amount'],
                    $_POST['submitted_documents'], $_POST['doctor_reference'], $_POST['diagnosis_code'],
                    $_POST['treatment_details'], $_POST['estimated_coverage'], $_SESSION['user_id']
                ])->lastInsertId();
                
                // Create claim timeline entry
                $timeline_sql = "INSERT INTO claim_timeline (claim_id, status, status_date, updated_by, notes) VALUES (?, 'submitted', NOW(), ?, 'Claim submitted for review')";
                $db->query($timeline_sql, [$claim_id, $_SESSION['user_id']]);
                
                $message = "Insurance claim submitted successfully! Claim Number: {$claim_number}";
                break;
                
            case 'update_claim_status':
                $status_sql = "UPDATE insurance_claims SET claim_status = ?, processed_amount = ?, rejection_reason = ?, processed_date = NOW(), processed_by = ? WHERE id = ?";
                $db->query($status_sql, [
                    $_POST['claim_status'], $_POST['processed_amount'], $_POST['rejection_reason'], 
                    $_SESSION['user_id'], $_POST['claim_id']
                ]);
                
                // Add timeline entry
                $timeline_sql = "INSERT INTO claim_timeline (claim_id, status, status_date, updated_by, notes) VALUES (?, ?, NOW(), ?, ?)";
                $db->query($timeline_sql, [
                    $_POST['claim_id'], $_POST['claim_status'], $_SESSION['user_id'], $_POST['status_notes']
                ]);
                
                // If approved, update patient bill
                if ($_POST['claim_status'] === 'approved' && !empty($_POST['processed_amount'])) {
                    $db->query("UPDATE patient_bills SET insurance_coverage = insurance_coverage + ? WHERE id = ?", 
                              [$_POST['processed_amount'], $_POST['bill_id']]);
                }
                
                $message = "Claim status updated successfully!";
                break;
                
            case 'add_insurance_policy':
                $policy_sql = "INSERT INTO patient_insurance (patient_id, insurance_company_id, policy_number, policy_type, coverage_percentage, coverage_limit, deductible, premium_amount, start_date, expiry_date, is_active, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
                $db->query($policy_sql, [
                    $_POST['patient_id'], $_POST['insurance_company_id'], $_POST['policy_number'],
                    $_POST['policy_type'], $_POST['coverage_percentage'], $_POST['coverage_limit'],
                    $_POST['deductible'], $_POST['premium_amount'], $_POST['start_date'],
                    $_POST['expiry_date'], $_SESSION['user_id']
                ]);
                $message = "Insurance policy added successfully!";
                break;
                
            case 'verify_eligibility':
                // Insurance eligibility verification
                $verification_sql = "INSERT INTO insurance_verifications (patient_id, policy_id, verification_date, eligibility_status, coverage_details, verification_reference, verified_by, notes) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";
                $verification_ref = 'VER' . date('Ymd') . rand(1000, 9999);
                $db->query($verification_sql, [
                    $_POST['patient_id'], $_POST['policy_id'], $_POST['eligibility_status'],
                    $_POST['coverage_details'], $verification_ref, $_SESSION['user_id'], $_POST['verification_notes']
                ]);
                $message = "Insurance eligibility verified! Reference: {$verification_ref}";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$date_filter = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get insurance claims
$claims = $db->query("
    SELECT ic.*, 
           p.first_name, p.last_name, p.patient_id as patient_number,
           pi.policy_number, comp.company_name,
           s1.first_name as submitted_by_name, s1.last_name as submitted_by_lastname,
           s2.first_name as processed_by_name, s2.last_name as processed_by_lastname
    FROM insurance_claims ic
    JOIN patients p ON ic.patient_id = p.id
    JOIN patient_insurance pi ON ic.policy_id = pi.id
    JOIN insurance_companies comp ON pi.insurance_company_id = comp.id
    LEFT JOIN staff s1 ON ic.submitted_by = s1.user_id
    LEFT JOIN staff s2 ON ic.processed_by = s2.user_id
    WHERE ic.submitted_date BETWEEN ? AND ?
    ORDER BY ic.submitted_date DESC
    LIMIT 50
", [$date_filter, $date_to])->fetchAll();

// Get insurance statistics
$stats = [
    'total_claims' => $db->query("SELECT COUNT(*) as count FROM insurance_claims WHERE submitted_date >= CURDATE() - INTERVAL 30 DAY")->fetch()['count'],
    'pending_claims' => $db->query("SELECT COUNT(*) as count FROM insurance_claims WHERE claim_status IN ('submitted', 'under_review')")->fetch()['count'],
    'approved_claims' => $db->query("SELECT COUNT(*) as count FROM insurance_claims WHERE claim_status = 'approved' AND submitted_date >= CURDATE() - INTERVAL 30 DAY")->fetch()['count'],
    'total_coverage' => $db->query("SELECT SUM(processed_amount) as total FROM insurance_claims WHERE claim_status = 'approved' AND submitted_date >= CURDATE() - INTERVAL 30 DAY")->fetch()['total'] ?? 0,
    'rejection_rate' => $db->query("SELECT (COUNT(CASE WHEN claim_status = 'rejected' THEN 1 END) * 100.0 / COUNT(*)) as rate FROM insurance_claims WHERE submitted_date >= CURDATE() - INTERVAL 30 DAY")->fetch()['rate'] ?? 0
];

// Get data for forms
$patients = $db->query("SELECT * FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();
$insurance_companies = $db->query("SELECT * FROM insurance_companies WHERE is_active = 1 ORDER BY company_name")->fetchAll();
$active_policies = $db->query("
    SELECT pi.*, p.first_name, p.last_name, p.patient_id as patient_number, ic.company_name
    FROM patient_insurance pi
    JOIN patients p ON pi.patient_id = p.id
    JOIN insurance_companies ic ON pi.insurance_company_id = ic.id
    WHERE pi.is_active = 1 AND pi.expiry_date > CURDATE()
    ORDER BY p.first_name
")->fetchAll();

// Get recent verifications
$recent_verifications = $db->query("
    SELECT iv.*, p.first_name, p.last_name, pi.policy_number, ic.company_name,
           s.first_name as verified_by_name, s.last_name as verified_by_lastname
    FROM insurance_verifications iv
    JOIN patients p ON iv.patient_id = p.id
    JOIN patient_insurance pi ON iv.policy_id = pi.id
    JOIN insurance_companies ic ON pi.insurance_company_id = ic.id
    LEFT JOIN staff s ON iv.verified_by = s.user_id
    WHERE iv.verification_date BETWEEN ? AND ?
    ORDER BY iv.verification_date DESC
    LIMIT 20
", [$date_filter, $date_to])->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Insurance Management System');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .insurance-management {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }
        
        .insurance-stats {
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
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .insurance-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .insurance-tab {
            padding: 12px 20px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .insurance-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .tab-content {
            display: none;
            background: var(--bg-card);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-md);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .claim-item {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            position: relative;
            border-left: 4px solid #4f46e5;
        }
        
        .claim-submitted {
            border-left-color: #f59e0b;
        }
        
        .claim-approved {
            border-left-color: var(--secondary-color);
        }
        
        .claim-rejected {
            border-left-color: #dc2626;
        }
        
        .claim-under-review {
            border-left-color: #0ea5e9;
        }
        
        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .claim-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .claim-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-submitted {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fecaca;
            color: #991b1b;
        }
        
        .status-under-review {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .claim-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .claim-detail-item {
            background: var(--bg-card);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
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
        
        .amount-claimed {
            color: #f59e0b;
            font-size: 1.1rem;
        }
        
        .amount-approved {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
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
        .btn-info { background: #0ea5e9; }
        
        .claim-timeline {
            background: var(--bg-card);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .timeline-item {
            padding: 10px;
            border-left: 3px solid var(--primary-color);
            margin-left: 15px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 15px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .timeline-content {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .verification-item {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #0ea5e9;
        }
        
        .verification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .verification-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .not-eligible {
            background: #fecaca;
            color: #991b1b;
        }
        
        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-section input, .filter-section select {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
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
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .insurance-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .policy-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            text-align: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="insurance-management">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-shield-alt"></i> Insurance Management System</h1>
                    <p>Complete insurance claims, policies, and eligibility management</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Insurance Statistics -->
                <div class="insurance-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_claims']; ?></div>
                        <div class="stat-label">Total Claims (30d)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_claims']; ?></div>
                        <div class="stat-label">Pending Claims</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['approved_claims']; ?></div>
                        <div class="stat-label">Approved (30d)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">₹<?php echo number_format($stats['total_coverage']); ?></div>
                        <div class="stat-label">Total Coverage (30d)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['rejection_rate'], 1); ?>%</div>
                        <div class="stat-label">Rejection Rate</div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET">
                        <label>From:</label>
                        <input type="date" name="date_from" value="<?php echo $date_filter; ?>">
                        <label>To:</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                        <button type="submit" class="btn">Filter</button>
                    </form>
                </div>
                
                <!-- Insurance Management Tabs -->
                <div class="insurance-tabs">
                    <div class="insurance-tab active" onclick="showInsuranceTab('claims')">
                        <i class="fas fa-file-medical"></i> Insurance Claims
                    </div>
                    <div class="insurance-tab" onclick="showInsuranceTab('policies')">
                        <i class="fas fa-clipboard-list"></i> Policies
                    </div>
                    <div class="insurance-tab" onclick="showInsuranceTab('verifications')">
                        <i class="fas fa-check-circle"></i> Verifications
                    </div>
                    <?php if (in_array($user_role, ['admin', 'insurance_staff', 'billing_staff'])): ?>
                        <div class="insurance-tab" onclick="showInsuranceTab('create-claim')">
                            <i class="fas fa-plus-circle"></i> Create Claim
                        </div>
                        <div class="insurance-tab" onclick="showInsuranceTab('add-policy')">
                            <i class="fas fa-user-plus"></i> Add Policy
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Insurance Claims Tab -->
                <div id="claims" class="tab-content active">
                    <h2>Insurance Claims Management</h2>
                    <?php foreach ($claims as $claim): ?>
                        <div class="claim-item claim-<?php echo str_replace('_', '-', $claim['claim_status']); ?>">
                            <div class="claim-header">
                                <div class="claim-number"><?php echo $claim['claim_number']; ?></div>
                                <div class="claim-status status-<?php echo str_replace('_', '-', $claim['claim_status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $claim['claim_status'])); ?>
                                </div>
                            </div>
                            
                            <div class="claim-details">
                                <div class="claim-detail-item">
                                    <div class="detail-label">Patient</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($claim['first_name'] . ' ' . $claim['last_name']); ?>
                                        <br><small><?php echo $claim['patient_number']; ?></small>
                                    </div>
                                </div>
                                <div class="claim-detail-item">
                                    <div class="detail-label">Policy</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($claim['company_name']); ?>
                                        <br><small><?php echo $claim['policy_number']; ?></small>
                                    </div>
                                </div>
                                <div class="claim-detail-item">
                                    <div class="detail-label">Claim Type</div>
                                    <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $claim['claim_type'])); ?></div>
                                </div>
                                <div class="claim-detail-item">
                                    <div class="detail-label">Service Type</div>
                                    <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $claim['service_type'])); ?></div>
                                </div>
                                <div class="claim-detail-item">
                                    <div class="detail-label">Service Date</div>
                                    <div class="detail-value"><?php echo date('d-M-Y', strtotime($claim['service_date'])); ?></div>
                                </div>
                                <div class="claim-detail-item">
                                    <div class="detail-label">Claimed Amount</div>
                                    <div class="detail-value amount-claimed">₹<?php echo number_format($claim['claim_amount']); ?></div>
                                </div>
                                <?php if ($claim['processed_amount']): ?>
                                    <div class="claim-detail-item">
                                        <div class="detail-label">Approved Amount</div>
                                        <div class="detail-value amount-approved">₹<?php echo number_format($claim['processed_amount']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="claim-detail-item">
                                    <div class="detail-label">Submitted By</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($claim['submitted_by_name'] . ' ' . $claim['submitted_by_lastname']); ?>
                                        <br><small><?php echo date('d-M-Y', strtotime($claim['submitted_date'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px; padding: 15px; background: var(--bg-card); border-radius: 8px;">
                                <strong>Treatment Details:</strong> <?php echo htmlspecialchars($claim['treatment_details']); ?>
                                <?php if ($claim['diagnosis_code']): ?>
                                    <br><strong>Diagnosis Code:</strong> <?php echo htmlspecialchars($claim['diagnosis_code']); ?>
                                <?php endif; ?>
                                <?php if ($claim['rejection_reason']): ?>
                                    <br><strong>Rejection Reason:</strong> <span style="color: #dc2626;"><?php echo htmlspecialchars($claim['rejection_reason']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (in_array($user_role, ['admin', 'insurance_staff']) && $claim['claim_status'] !== 'approved' && $claim['claim_status'] !== 'rejected'): ?>
                                <div style="margin-top: 15px; padding: 15px; background: var(--bg-secondary); border-radius: 8px;">
                                    <h4>Update Claim Status</h4>
                                    <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                                        <input type="hidden" name="action" value="update_claim_status">
                                        <input type="hidden" name="claim_id" value="<?php echo $claim['id']; ?>">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select name="claim_status" required>
                                                <option value="under_review">Under Review</option>
                                                <option value="approved">Approved</option>
                                                <option value="rejected">Rejected</option>
                                                <option value="pending_documents">Pending Documents</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Processed Amount</label>
                                            <input type="number" name="processed_amount" step="0.01" placeholder="Approved amount">
                                        </div>
                                        <div class="form-group">
                                            <label>Rejection Reason</label>
                                            <input type="text" name="rejection_reason" placeholder="Reason if rejected">
                                        </div>
                                        <div class="form-group">
                                            <label>Notes</label>
                                            <input type="text" name="status_notes" placeholder="Status update notes">
                                        </div>
                                        <button type="submit" class="btn btn-warning">Update Status</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Policies Tab -->
                <div id="policies" class="tab-content">
                    <h2>Active Insurance Policies</h2>
                    <?php foreach ($active_policies as $policy): ?>
                        <div class="insurance-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <div>
                                    <h3><?php echo htmlspecialchars($policy['company_name']); ?></h3>
                                    <p>Policy: <?php echo htmlspecialchars($policy['policy_number']); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.1rem; font-weight: bold;">
                                        <?php echo htmlspecialchars($policy['first_name'] . ' ' . $policy['last_name']); ?>
                                    </div>
                                    <div style="font-size: 0.9rem; opacity: 0.9;">
                                        <?php echo $policy['patient_number']; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="policy-info">
                                <div class="info-item">
                                    <div style="font-size: 1.2rem; font-weight: bold;"><?php echo $policy['coverage_percentage']; ?>%</div>
                                    <div style="font-size: 0.9rem;">Coverage</div>
                                </div>
                                <div class="info-item">
                                    <div style="font-size: 1.2rem; font-weight: bold;">₹<?php echo number_format($policy['coverage_limit']); ?></div>
                                    <div style="font-size: 0.9rem;">Max Limit</div>
                                </div>
                                <div class="info-item">
                                    <div style="font-size: 1.2rem; font-weight: bold;">₹<?php echo number_format($policy['deductible']); ?></div>
                                    <div style="font-size: 0.9rem;">Deductible</div>
                                </div>
                                <div class="info-item">
                                    <div style="font-size: 1.2rem; font-weight: bold;"><?php echo date('d-M-Y', strtotime($policy['expiry_date'])); ?></div>
                                    <div style="font-size: 0.9rem;">Expires</div>
                                </div>
                                <div class="info-item">
                                    <div style="font-size: 1.2rem; font-weight: bold;">₹<?php echo number_format($policy['premium_amount']); ?></div>
                                    <div style="font-size: 0.9rem;">Premium</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Verifications Tab -->
                <div id="verifications" class="tab-content">
                    <h2>Insurance Eligibility Verifications</h2>
                    <?php foreach ($recent_verifications as $verification): ?>
                        <div class="verification-item">
                            <div class="verification-header">
                                <div>
                                    <strong><?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?></strong>
                                    <br><small><?php echo $verification['company_name'] . ' - ' . $verification['policy_number']; ?></small>
                                </div>
                                <div class="verification-status <?php echo $verification['eligibility_status'] === 'eligible' ? 'verified' : 'not-eligible'; ?>">
                                    <?php echo ucfirst($verification['eligibility_status']); ?>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                                <div>
                                    <strong>Verification Date:</strong> <?php echo date('d-M-Y H:i', strtotime($verification['verification_date'])); ?>
                                </div>
                                <div>
                                    <strong>Verified By:</strong> <?php echo htmlspecialchars($verification['verified_by_name'] . ' ' . $verification['verified_by_lastname']); ?>
                                </div>
                                <div>
                                    <strong>Reference:</strong> <?php echo $verification['verification_reference']; ?>
                                </div>
                            </div>
                            <div style="margin-top: 10px; padding: 10px; background: var(--bg-card); border-radius: 5px;">
                                <strong>Coverage Details:</strong> <?php echo htmlspecialchars($verification['coverage_details']); ?>
                                <?php if ($verification['notes']): ?>
                                    <br><strong>Notes:</strong> <?php echo htmlspecialchars($verification['notes']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Create Claim Tab -->
                <?php if (in_array($user_role, ['admin', 'insurance_staff', 'billing_staff'])): ?>
                <div id="create-claim" class="tab-content">
                    <h2>Create Insurance Claim</h2>
                    <div class="form-grid">
                        <div class="form-section">
                            <h3><i class="fas fa-file-medical"></i> New Insurance Claim</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_claim">
                                <div class="form-group">
                                    <label>Patient</label>
                                    <select name="patient_id" required onchange="loadPatientPolicies(this.value)">
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>">
                                                <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Insurance Policy</label>
                                    <select name="policy_id" id="policy_id" required>
                                        <option value="">Select Policy</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Claim Type</label>
                                    <select name="claim_type" required>
                                        <option value="medical">Medical Treatment</option>
                                        <option value="surgical">Surgical Procedure</option>
                                        <option value="emergency">Emergency Care</option>
                                        <option value="diagnostic">Diagnostic Tests</option>
                                        <option value="pharmacy">Pharmacy</option>
                                        <option value="dental">Dental</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Service Type</label>
                                    <select name="service_type" required>
                                        <option value="inpatient">Inpatient</option>
                                        <option value="outpatient">Outpatient</option>
                                        <option value="emergency">Emergency</option>
                                        <option value="consultation">Consultation</option>
                                        <option value="diagnostic">Diagnostic</option>
                                        <option value="blood_transfusion">Blood Transfusion</option>
                                        <option value="organ_transplant">Organ Transplant</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Service Date</label>
                                    <input type="date" name="service_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Claim Amount (₹)</label>
                                    <input type="number" name="claim_amount" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>Estimated Coverage (₹)</label>
                                    <input type="number" name="estimated_coverage" step="0.01">
                                </div>
                                <div class="form-group">
                                    <label>Doctor Reference</label>
                                    <input type="text" name="doctor_reference" required placeholder="Dr. Name, License No.">
                                </div>
                                <div class="form-group">
                                    <label>Diagnosis Code</label>
                                    <input type="text" name="diagnosis_code" placeholder="ICD-10 or relevant diagnosis code">
                                </div>
                                <div class="form-group">
                                    <label>Treatment Details</label>
                                    <textarea name="treatment_details" rows="3" required placeholder="Detailed description of treatment/service"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Submitted Documents</label>
                                    <textarea name="submitted_documents" rows="2" placeholder="List of documents submitted with claim"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">Submit Claim</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Add Policy Tab -->
                <div id="add-policy" class="tab-content">
                    <h2>Add Insurance Policy</h2>
                    <div class="form-grid">
                        <div class="form-section">
                            <h3><i class="fas fa-user-plus"></i> New Insurance Policy</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_insurance_policy">
                                <div class="form-group">
                                    <label>Patient</label>
                                    <select name="patient_id" required>
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>">
                                                <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Insurance Company</label>
                                    <select name="insurance_company_id" required>
                                        <option value="">Select Company</option>
                                        <?php foreach ($insurance_companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>">
                                                <?php echo htmlspecialchars($company['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Policy Number</label>
                                    <input type="text" name="policy_number" required>
                                </div>
                                <div class="form-group">
                                    <label>Policy Type</label>
                                    <select name="policy_type" required>
                                        <option value="individual">Individual</option>
                                        <option value="family">Family</option>
                                        <option value="group">Group</option>
                                        <option value="corporate">Corporate</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Coverage Percentage (%)</label>
                                    <input type="number" name="coverage_percentage" min="0" max="100" required>
                                </div>
                                <div class="form-group">
                                    <label>Coverage Limit (₹)</label>
                                    <input type="number" name="coverage_limit" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>Deductible (₹)</label>
                                    <input type="number" name="deductible" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>Premium Amount (₹)</label>
                                    <input type="number" name="premium_amount" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label>Policy Start Date</label>
                                    <input type="date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Policy Expiry Date</label>
                                    <input type="date" name="expiry_date" required>
                                </div>
                                <button type="submit" class="btn btn-success">Add Policy</button>
                            </form>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-check-circle"></i> Verify Insurance Eligibility</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="verify_eligibility">
                                <div class="form-group">
                                    <label>Patient</label>
                                    <select name="patient_id" required onchange="loadPatientPoliciesForVerification(this.value)">
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>">
                                                <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Policy to Verify</label>
                                    <select name="policy_id" id="verification_policy_id" required>
                                        <option value="">Select Policy</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Eligibility Status</label>
                                    <select name="eligibility_status" required>
                                        <option value="eligible">Eligible</option>
                                        <option value="not_eligible">Not Eligible</option>
                                        <option value="pending">Pending Verification</option>
                                        <option value="expired">Policy Expired</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Coverage Details</label>
                                    <textarea name="coverage_details" rows="3" required placeholder="Coverage details, limits, exclusions"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Verification Notes</label>
                                    <textarea name="verification_notes" rows="2" placeholder="Additional verification notes"></textarea>
                                </div>
                                <button type="submit" class="btn btn-info">Verify Eligibility</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function showInsuranceTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.insurance-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function loadPatientPolicies(patientId) {
            if (!patientId) {
                document.getElementById('policy_id').innerHTML = '<option value="">Select Policy</option>';
                return;
            }
            
            // Make AJAX call to get patient policies
            fetch(`get-patient-policies.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Select Policy</option>';
                    data.forEach(policy => {
                        options += `<option value="${policy.id}">${policy.company_name} - ${policy.policy_number}</option>`;
                    });
                    document.getElementById('policy_id').innerHTML = options;
                })
                .catch(error => console.error('Error loading policies:', error));
        }
        
        function loadPatientPoliciesForVerification(patientId) {
            if (!patientId) {
                document.getElementById('verification_policy_id').innerHTML = '<option value="">Select Policy</option>';
                return;
            }
            
            // Make AJAX call to get patient policies
            fetch(`get-patient-policies.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Select Policy</option>';
                    data.forEach(policy => {
                        options += `<option value="${policy.id}">${policy.company_name} - ${policy.policy_number}</option>`;
                    });
                    document.getElementById('verification_policy_id').innerHTML = options;
                })
                .catch(error => console.error('Error loading policies:', error));
        }
    </script>
</body>
</html>