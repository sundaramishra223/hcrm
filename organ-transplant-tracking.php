<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in with proper authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$message = '';

// Get user role and permissions - Only authorized medical staff
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Verify user has organ transplant access rights
$authorized_roles = ['admin', 'doctor', 'transplant_coordinator', 'surgeon'];
if (!in_array($user_role, $authorized_roles)) {
    header('Location: unauthorized.php');
    exit;
}

// Handle form submissions with legal compliance
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'register_donor':
                // Verify donor consent and legal documentation
                $consent_sql = "INSERT INTO organ_donor_consent (donor_id, consent_type, consent_date, witness_1, witness_2, legal_guardian, consent_document_path, notarized, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $consent_id = $db->query($consent_sql, [
                    $_POST['donor_id'], $_POST['consent_type'], $_POST['consent_date'],
                    $_POST['witness_1'], $_POST['witness_2'], $_POST['legal_guardian'],
                    $_POST['consent_document_path'], $_POST['notarized'], $_SESSION['user_id']
                ])->lastInsertId();
                
                // Register organ donation with medical evaluation
                $donation_sql = "INSERT INTO organ_donations (donor_id, consent_id, donation_type, organ_type, medical_evaluation, brain_death_confirmation, declaration_time, declaring_physician, harvest_team_lead, preservation_method, ischemia_time, organ_condition, legal_clearance, ethics_committee_approval, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_harvest')";
                $db->query($donation_sql, [
                    $_POST['donor_id'], $consent_id, $_POST['donation_type'], $_POST['organ_type'],
                    $_POST['medical_evaluation'], $_POST['brain_death_confirmation'], $_POST['declaration_time'],
                    $_POST['declaring_physician'], $_POST['harvest_team_lead'], $_POST['preservation_method'],
                    $_POST['ischemia_time'], $_POST['organ_condition'], $_POST['legal_clearance'],
                    $_POST['ethics_committee_approval'], $_SESSION['user_id']
                ]);
                
                $message = "Organ donation registered with full legal compliance!";
                break;
                
            case 'register_recipient':
                // Register recipient with priority scoring and legal verification
                $recipient_sql = "INSERT INTO organ_recipients (patient_id, organ_needed, urgency_level, waiting_list_date, priority_score, medical_compatibility, insurance_verification, legal_consent, guardian_consent, ethics_approval, psychosocial_evaluation, financial_clearance, registered_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active_waiting')";
                $db->query($recipient_sql, [
                    $_POST['patient_id'], $_POST['organ_needed'], $_POST['urgency_level'],
                    $_POST['waiting_list_date'], $_POST['priority_score'], $_POST['medical_compatibility'],
                    $_POST['insurance_verification'], $_POST['legal_consent'], $_POST['guardian_consent'],
                    $_POST['ethics_approval'], $_POST['psychosocial_evaluation'], $_POST['financial_clearance'],
                    $_SESSION['user_id']
                ]);
                
                $message = "Recipient registered on waiting list with legal verification!";
                break;
                
            case 'record_transplant':
                // Record transplant with complete legal and medical documentation
                $transplant_sql = "INSERT INTO organ_transplants (donation_id, recipient_id, surgery_date, lead_surgeon, surgical_team, operation_duration, cross_match_result, immunosuppression_protocol, immediate_complications, legal_documentation_complete, informed_consent_signed, ethics_clearance, insurance_approval, post_op_monitoring_plan, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
                $transplant_id = $db->query($transplant_sql, [
                    $_POST['donation_id'], $_POST['recipient_id'], $_POST['surgery_date'],
                    $_POST['lead_surgeon'], $_POST['surgical_team'], $_POST['operation_duration'],
                    $_POST['cross_match_result'], $_POST['immunosuppression_protocol'], $_POST['immediate_complications'],
                    $_POST['legal_documentation_complete'], $_POST['informed_consent_signed'], $_POST['ethics_clearance'],
                    $_POST['insurance_approval'], $_POST['post_op_monitoring_plan'], $_SESSION['user_id']
                ])->lastInsertId();
                
                // Update organ donation and recipient status
                $db->query("UPDATE organ_donations SET status = 'transplanted', transplant_id = ? WHERE id = ?", 
                          [$transplant_id, $_POST['donation_id']]);
                $db->query("UPDATE organ_recipients SET status = 'transplanted', transplant_date = ? WHERE id = ?", 
                          [$_POST['surgery_date'], $_POST['recipient_id']]);
                
                // Legal audit trail
                $audit_sql = "INSERT INTO organ_audit_trail (transplant_id, action_type, action_details, performed_by, legal_significance, timestamp) VALUES (?, 'transplant_completed', ?, ?, 'high', NOW())";
                $db->query($audit_sql, [$transplant_id, "Organ transplant completed with full legal compliance", $_SESSION['user_id']]);
                
                $message = "Organ transplant recorded with complete legal documentation!";
                break;
                
            case 'legal_rejection':
                // Handle legal rejection or withdrawal of consent
                $rejection_sql = "INSERT INTO organ_legal_rejections (donation_id, recipient_id, rejection_type, rejection_reason, legal_basis, rejecting_authority, rejection_date, documentation_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($rejection_sql, [
                    $_POST['donation_id'], $_POST['recipient_id'], $_POST['rejection_type'],
                    $_POST['rejection_reason'], $_POST['legal_basis'], $_POST['rejecting_authority'],
                    $_POST['rejection_date'], $_POST['documentation_path'], $_SESSION['user_id']
                ]);
                
                $message = "Legal rejection/withdrawal recorded with proper documentation!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        // Log security incident for organ transplant system
        error_log("ORGAN_TRANSPLANT_ERROR: User {$_SESSION['user_id']} - " . $e->getMessage());
    }
}

// Get tracking data with legal compliance filters
$date_filter = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Organ availability and matching
$organ_inventory = $db->query("
    SELECT 
        organ_type,
        COUNT(*) as total_available,
        AVG(ischemia_time) as avg_ischemia_time,
        COUNT(CASE WHEN legal_clearance = 'approved' THEN 1 END) as legally_cleared,
        COUNT(CASE WHEN ethics_committee_approval = 'approved' THEN 1 END) as ethics_approved
    FROM organ_donations 
    WHERE status IN ('pending_harvest', 'harvested', 'preserved')
    AND legal_clearance = 'approved'
    GROUP BY organ_type
    ORDER BY organ_type
")->fetchAll();

// Waiting list with priority
$waiting_list = $db->query("
    SELECT or.*, p.patient_id, p.first_name, p.last_name, p.blood_group, p.age,
           DATEDIFF(CURDATE(), or.waiting_list_date) as days_waiting,
           s.first_name as coordinator_first_name, s.last_name as coordinator_last_name
    FROM organ_recipients or
    JOIN patients p ON or.patient_id = p.id
    LEFT JOIN staff s ON or.registered_by = s.user_id
    WHERE or.status = 'active_waiting'
    AND or.legal_consent = 'signed'
    AND or.ethics_approval = 'approved'
    ORDER BY or.priority_score DESC, or.waiting_list_date ASC
    LIMIT 20
")->fetchAll();

// Recent transplants with legal compliance
$recent_transplants = $db->query("
    SELECT ot.*, 
           od.organ_type, od.donation_type,
           p_donor.first_name as donor_first_name, p_donor.last_name as donor_last_name,
           p_recipient.first_name as recipient_first_name, p_recipient.last_name as recipient_last_name,
           s_surgeon.first_name as surgeon_first_name, s_surgeon.last_name as surgeon_last_name,
           (ot.legal_documentation_complete = 'yes' AND ot.informed_consent_signed = 'yes' AND ot.ethics_clearance = 'approved') as fully_compliant
    FROM organ_transplants ot
    JOIN organ_donations od ON ot.donation_id = od.id
    JOIN patients p_donor ON od.donor_id = p_donor.id
    JOIN organ_recipients ore ON ot.recipient_id = ore.id
    JOIN patients p_recipient ON ore.patient_id = p_recipient.id
    LEFT JOIN staff s_surgeon ON ot.lead_surgeon = s_surgeon.user_id
    WHERE ot.surgery_date BETWEEN ? AND ?
    ORDER BY ot.surgery_date DESC, ot.created_at DESC
    LIMIT 15
", [$date_filter, $date_to])->fetchAll();

// Legal compliance dashboard
$compliance_stats = [
    'pending_legal_clearance' => $db->query("SELECT COUNT(*) as count FROM organ_donations WHERE legal_clearance = 'pending'")->fetch()['count'],
    'pending_ethics_approval' => $db->query("SELECT COUNT(*) as count FROM organ_donations WHERE ethics_committee_approval = 'pending'")->fetch()['count'],
    'consent_withdrawals' => $db->query("SELECT COUNT(*) as count FROM organ_legal_rejections WHERE rejection_type = 'consent_withdrawal' AND rejection_date >= CURDATE() - INTERVAL 30 DAY")->fetch()['count'],
    'legal_violations' => $db->query("SELECT COUNT(*) as count FROM organ_audit_trail WHERE legal_significance = 'violation' AND timestamp >= CURDATE() - INTERVAL 30 DAY")->fetch()['count']
];

// Get data for forms
$eligible_donors = $db->query("SELECT p.*, bd.blood_group FROM patients p LEFT JOIN blood_donors bd ON p.id = bd.patient_id WHERE p.is_active = 1 ORDER BY p.first_name")->fetchAll();
$eligible_recipients = $db->query("SELECT * FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();
$available_organs = $db->query("SELECT od.*, p.first_name, p.last_name FROM organ_donations od JOIN patients p ON od.donor_id = p.id WHERE od.status IN ('harvested', 'preserved') AND od.legal_clearance = 'approved' ORDER BY od.organ_type")->fetchAll();
$surgeons = $db->query("SELECT * FROM staff WHERE staff_type IN ('surgeon', 'transplant_surgeon') AND is_active = 1 ORDER BY first_name")->fetchAll();

// My activity (for specific user with role-based access)
$my_activities = [];
if (in_array($user_role, ['doctor', 'surgeon', 'transplant_coordinator'])) {
    $my_activities = $db->query("
        SELECT 'donation' as activity_type, od.created_at as activity_date, od.organ_type as details, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name, od.status
        FROM organ_donations od
        JOIN patients p ON od.donor_id = p.id
        WHERE od.created_by = ? AND od.created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 'recipient' as activity_type, ore.created_at as activity_date, ore.organ_needed as details,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name, ore.status
        FROM organ_recipients ore
        JOIN patients p ON ore.patient_id = p.id
        WHERE ore.registered_by = ? AND ore.created_at BETWEEN ? AND ?
        UNION ALL
        SELECT 'transplant' as activity_type, ot.created_at as activity_date, 
               CONCAT('Transplant: ', od.organ_type) as details,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name, ot.status
        FROM organ_transplants ot
        JOIN organ_donations od ON ot.donation_id = od.id
        JOIN organ_recipients ore ON ot.recipient_id = ore.id
        JOIN patients p ON ore.patient_id = p.id
        WHERE ot.created_by = ? AND ot.created_at BETWEEN ? AND ?
        ORDER BY activity_date DESC
        LIMIT 20
    ", [$user_id, $date_filter, $date_to, $user_id, $date_filter, $date_to, $user_id, $date_filter, $date_to])->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Organ Transplant Tracking - Legal Compliance System');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .organ-tracking {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .legal-warning {
            background: linear-gradient(135deg, #fef3c7, #fbbf24);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #f59e0b;
            box-shadow: var(--shadow-md);
        }
        
        .legal-warning h3 {
            color: #92400e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legal-warning p {
            color: #78350f;
            font-weight: 500;
            margin: 0;
        }
        
        .page-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            border-left: 5px solid #dc2626;
        }
        
        .compliance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .compliance-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            text-align: center;
            position: relative;
        }
        
        .compliance-card.critical {
            border-left: 5px solid #dc2626;
            background: linear-gradient(135deg, var(--bg-card), #fef2f2);
        }
        
        .compliance-card.warning {
            border-left: 5px solid #f59e0b;
            background: linear-gradient(135deg, var(--bg-card), #fffbeb);
        }
        
        .compliance-card.success {
            border-left: 5px solid #10b981;
            background: linear-gradient(135deg, var(--bg-card), #f0fdf4);
        }
        
        .organ-inventory {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .organ-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .organ-card {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #dc2626;
            position: relative;
        }
        
        .organ-type {
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .legal-status {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            margin: 5px 0;
        }
        
        .legal-status.cleared {
            color: #10b981;
        }
        
        .legal-status.pending {
            color: #f59e0b;
        }
        
        .legal-status.rejected {
            color: #dc2626;
        }
        
        .sensitive-data {
            background: #fef2f2;
            border: 2px dashed #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .sensitive-data::before {
            content: "🔒 SENSITIVE MEDICAL DATA - RESTRICTED ACCESS";
            display: block;
            color: #dc2626;
            font-weight: bold;
            font-size: 0.8rem;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .legal-required {
            border: 2px solid #f59e0b !important;
            background: #fffbeb !important;
        }
        
        .legal-required::after {
            content: "⚖️ LEGAL DOCUMENTATION REQUIRED";
            position: absolute;
            top: -10px;
            right: 10px;
            background: #f59e0b;
            color: white;
            padding: 2px 8px;
            font-size: 0.7rem;
            border-radius: 10px;
        }
        
        .ethics-approval {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .ethics-approval::before {
            content: "🏛️ ETHICS COMMITTEE REVIEW";
            color: #0ea5e9;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        .priority-urgent {
            background: #fecaca !important;
            border-left-color: #dc2626 !important;
        }
        
        .priority-high {
            background: #fef3c7 !important;
            border-left-color: #f59e0b !important;
        }
        
        .priority-medium {
            background: #e0f2fe !important;
            border-left-color: #0ea5e9 !important;
        }
        
        .waiting-time-critical {
            color: #dc2626;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .waiting-time-long {
            color: #f59e0b;
            font-weight: bold;
        }
        
        .legal-compliance-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        
        .legal-compliance-indicator.compliant {
            background: #10b981;
        }
        
        .legal-compliance-indicator.non-compliant {
            background: #dc2626;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        
        .audit-trail {
            background: var(--bg-card);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #6366f1;
        }
        
        .audit-trail::before {
            content: "📋 AUDIT TRAIL RECORDED";
            color: #6366f1;
            font-weight: bold;
            font-size: 0.8rem;
            display: block;
            margin-bottom: 5px;
        }
        
        .form-section.high-security {
            border: 3px solid #dc2626;
            background: linear-gradient(135deg, var(--bg-secondary), #fef2f2);
            position: relative;
        }
        
        .form-section.high-security::before {
            content: "🔐 HIGH SECURITY - LEGAL COMPLIANCE REQUIRED";
            position: absolute;
            top: -15px;
            left: 20px;
            background: #dc2626;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .consent-verification {
            background: #f0fdf4;
            border: 2px solid #10b981;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .consent-verification h4 {
            color: #065f46;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .digital-signature {
            background: #ede9fe;
            border: 2px dashed #8b5cf6;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 10px 0;
        }
        
        .digital-signature::before {
            content: "✍️ DIGITAL SIGNATURE REQUIRED";
            color: #7c3aed;
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
        }
        
        /* Enhanced security for transplant forms */
        .transplant-form {
            background: linear-gradient(135deg, #fef2f2, #fef3c7);
            border: 3px solid #dc2626;
            padding: 25px;
            border-radius: 15px;
            position: relative;
            margin: 20px 0;
        }
        
        .transplant-form::before {
            content: "⚠️ CRITICAL MEDICAL PROCEDURE - MAXIMUM LEGAL COMPLIANCE REQUIRED";
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="organ-tracking">
                <!-- Legal Warning Banner -->
                <div class="legal-warning">
                    <h3><i class="fas fa-gavel"></i> LEGAL COMPLIANCE NOTICE</h3>
                    <p>This system handles sensitive organ transplant data. All actions are legally monitored and audited. Only authorized medical personnel with proper credentials can access this system. Unauthorized access or data manipulation is a criminal offense.</p>
                </div>
                
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-heart"></i> Organ Transplant Tracking System</h1>
                        <p>Complete organ donation and transplant management with legal compliance</p>
                        <small><strong>User:</strong> <?php echo htmlspecialchars($_SESSION['username'] . ' (' . ucfirst($user_role) . ')'); ?> | <strong>Access Level:</strong> Authorized Medical Personnel</small>
                    </div>
                    <div class="filter-section">
                        <form method="GET">
                            <label>From:</label>
                            <input type="date" name="date_from" value="<?php echo $date_filter; ?>">
                            <label>To:</label>
                            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                            <button type="submit" class="btn">Filter</button>
                        </form>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Legal Compliance Dashboard -->
                <div class="compliance-grid">
                    <div class="compliance-card <?php echo $compliance_stats['pending_legal_clearance'] > 0 ? 'warning' : 'success'; ?>">
                        <div class="stat-number"><?php echo $compliance_stats['pending_legal_clearance']; ?></div>
                        <div class="stat-label">Pending Legal Clearance</div>
                        <div class="legal-compliance-indicator <?php echo $compliance_stats['pending_legal_clearance'] > 0 ? 'non-compliant' : 'compliant'; ?>"></div>
                    </div>
                    <div class="compliance-card <?php echo $compliance_stats['pending_ethics_approval'] > 0 ? 'warning' : 'success'; ?>">
                        <div class="stat-number"><?php echo $compliance_stats['pending_ethics_approval']; ?></div>
                        <div class="stat-label">Pending Ethics Approval</div>
                        <div class="legal-compliance-indicator <?php echo $compliance_stats['pending_ethics_approval'] > 0 ? 'non-compliant' : 'compliant'; ?>"></div>
                    </div>
                    <div class="compliance-card <?php echo $compliance_stats['consent_withdrawals'] > 0 ? 'critical' : 'success'; ?>">
                        <div class="stat-number"><?php echo $compliance_stats['consent_withdrawals']; ?></div>
                        <div class="stat-label">Consent Withdrawals (30d)</div>
                        <div class="legal-compliance-indicator <?php echo $compliance_stats['consent_withdrawals'] > 0 ? 'non-compliant' : 'compliant'; ?>"></div>
                    </div>
                    <div class="compliance-card <?php echo $compliance_stats['legal_violations'] > 0 ? 'critical' : 'success'; ?>">
                        <div class="stat-number"><?php echo $compliance_stats['legal_violations']; ?></div>
                        <div class="stat-label">Legal Violations (30d)</div>
                        <div class="legal-compliance-indicator <?php echo $compliance_stats['legal_violations'] > 0 ? 'non-compliant' : 'compliant'; ?>"></div>
                    </div>
                </div>
                
                <!-- Organ Inventory -->
                <div class="organ-inventory">
                    <h3><i class="fas fa-procedures"></i> Available Organs - Legal Compliance Status</h3>
                    <div class="organ-grid">
                        <?php foreach ($organ_inventory as $organ): ?>
                            <div class="organ-card">
                                <div class="organ-type"><?php echo htmlspecialchars($organ['organ_type']); ?></div>
                                <div class="legal-status <?php echo $organ['legally_cleared'] == $organ['total_available'] ? 'cleared' : 'pending'; ?>">
                                    <span>Legal Clearance:</span>
                                    <span><?php echo $organ['legally_cleared']; ?>/<?php echo $organ['total_available']; ?></span>
                                </div>
                                <div class="legal-status <?php echo $organ['ethics_approved'] == $organ['total_available'] ? 'cleared' : 'pending'; ?>">
                                    <span>Ethics Approved:</span>
                                    <span><?php echo $organ['ethics_approved']; ?>/<?php echo $organ['total_available']; ?></span>
                                </div>
                                <div style="font-size: 0.8rem; margin-top: 10px; color: var(--text-secondary);">
                                    <strong>Avg Ischemia:</strong> <?php echo number_format($organ['avg_ischemia_time'], 1); ?>h
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Management Tabs -->
                <div class="management-tabs">
                    <div class="tab-btn active" onclick="showTab('waiting-list')">
                        <i class="fas fa-list-ol"></i> Waiting List
                    </div>
                    <div class="tab-btn" onclick="showTab('recent-transplants')">
                        <i class="fas fa-heartbeat"></i> Recent Transplants
                    </div>
                    <?php if (in_array($user_role, ['admin', 'doctor', 'surgeon', 'transplant_coordinator'])): ?>
                        <div class="tab-btn" onclick="showTab('register-donor')">
                            <i class="fas fa-hand-holding-heart"></i> Register Donor
                        </div>
                        <div class="tab-btn" onclick="showTab('register-recipient')">
                            <i class="fas fa-user-plus"></i> Register Recipient
                        </div>
                        <div class="tab-btn" onclick="showTab('record-transplant')">
                            <i class="fas fa-procedures"></i> Record Transplant
                        </div>
                    <?php endif; ?>
                    <div class="tab-btn" onclick="showTab('my-activity')">
                        <i class="fas fa-user-md"></i> My Activity
                    </div>
                    <div class="tab-btn" onclick="showTab('legal-compliance')">
                        <i class="fas fa-gavel"></i> Legal Compliance
                    </div>
                </div>
                
                <!-- Waiting List Tab -->
                <div id="waiting-list" class="tab-content active">
                    <h2>Organ Transplant Waiting List - Priority Order</h2>
                    <div class="tracking-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Patient</th>
                                    <th>Organ Needed</th>
                                    <th>Urgency</th>
                                    <th>Days Waiting</th>
                                    <th>Legal Status</th>
                                    <th>Medical Compatibility</th>
                                    <th>Coordinator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waiting_list as $recipient): ?>
                                    <tr class="priority-<?php echo $recipient['urgency_level']; ?>">
                                        <td>
                                            <strong><?php echo $recipient['priority_score']; ?></strong>
                                            <div class="legal-compliance-indicator <?php 
                                                echo ($recipient['legal_consent'] === 'signed' && $recipient['ethics_approval'] === 'approved') ? 'compliant' : 'non-compliant'; 
                                            ?>"></div>
                                        </td>
                                        <td class="sensitive-data">
                                            <strong><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></strong>
                                            <br><small><?php echo $recipient['patient_id']; ?> | Age: <?php echo $recipient['age']; ?> | <?php echo $recipient['blood_group']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $recipient['organ_needed'])); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $recipient['urgency_level'] === 'critical' ? 'danger' : 
                                                    ($recipient['urgency_level'] === 'high' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($recipient['urgency_level']); ?>
                                            </span>
                                        </td>
                                        <td class="<?php 
                                            echo $recipient['days_waiting'] > 365 ? 'waiting-time-critical' : 
                                                ($recipient['days_waiting'] > 180 ? 'waiting-time-long' : ''); 
                                        ?>">
                                            <?php echo $recipient['days_waiting']; ?> days
                                            <br><small>Since: <?php echo date('d-M-Y', strtotime($recipient['waiting_list_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="legal-status <?php echo $recipient['legal_consent'] === 'signed' ? 'cleared' : 'pending'; ?>">
                                                Consent: <?php echo ucfirst($recipient['legal_consent']); ?>
                                            </div>
                                            <div class="legal-status <?php echo $recipient['ethics_approval'] === 'approved' ? 'cleared' : 'pending'; ?>">
                                                Ethics: <?php echo ucfirst($recipient['ethics_approval']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($recipient['medical_compatibility']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($recipient['coordinator_first_name'] . ' ' . $recipient['coordinator_last_name']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Transplants Tab -->
                <div id="recent-transplants" class="tab-content">
                    <h2>Recent Organ Transplants - Legal Compliance Verified</h2>
                    <div class="tracking-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Organ</th>
                                    <th>Donor</th>
                                    <th>Recipient</th>
                                    <th>Surgeon</th>
                                    <th>Duration</th>
                                    <th>Legal Compliance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transplants as $transplant): ?>
                                    <tr>
                                        <td><?php echo date('d-M-Y H:i', strtotime($transplant['surgery_date'])); ?></td>
                                        <td>
                                            <strong><?php echo ucfirst(str_replace('_', ' ', $transplant['organ_type'])); ?></strong>
                                            <br><small><?php echo ucfirst($transplant['donation_type']); ?></small>
                                        </td>
                                        <td class="sensitive-data">
                                            <?php echo htmlspecialchars($transplant['donor_first_name'] . ' ' . $transplant['donor_last_name']); ?>
                                        </td>
                                        <td class="sensitive-data">
                                            <?php echo htmlspecialchars($transplant['recipient_first_name'] . ' ' . $transplant['recipient_last_name']); ?>
                                        </td>
                                        <td>
                                            Dr. <?php echo htmlspecialchars($transplant['surgeon_first_name'] . ' ' . $transplant['surgeon_last_name']); ?>
                                        </td>
                                        <td><?php echo $transplant['operation_duration']; ?> hours</td>
                                        <td>
                                            <div class="legal-compliance-indicator <?php echo $transplant['fully_compliant'] ? 'compliant' : 'non-compliant'; ?>"></div>
                                            <small>
                                                Legal Docs: <?php echo $transplant['legal_documentation_complete']; ?><br>
                                                Consent: <?php echo $transplant['informed_consent_signed']; ?><br>
                                                Ethics: <?php echo $transplant['ethics_clearance']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $transplant['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($transplant['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Register Donor Tab -->
                <?php if (in_array($user_role, ['admin', 'doctor', 'surgeon', 'transplant_coordinator'])): ?>
                <div id="register-donor" class="tab-content">
                    <h2>Register Organ Donor - Legal Compliance Required</h2>
                    <div class="form-grid">
                        <div class="form-section high-security">
                            <h3><i class="fas fa-hand-holding-heart"></i> Donor Registration & Legal Documentation</h3>
                            <form method="POST" class="transplant-form">
                                <input type="hidden" name="action" value="register_donor">
                                
                                <div class="consent-verification">
                                    <h4><i class="fas fa-file-signature"></i> Legal Consent Verification</h4>
                                    <div class="form-group legal-required">
                                        <label>Donor</label>
                                        <select name="donor_id" required>
                                            <option value="">Select Donor</option>
                                            <?php foreach ($eligible_donors as $donor): ?>
                                                <option value="<?php echo $donor['id']; ?>">
                                                    <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name'] . ' (Age: ' . $donor['age'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Consent Type</label>
                                        <select name="consent_type" required>
                                            <option value="living_donor">Living Donor Consent</option>
                                            <option value="brain_death">Brain Death Declaration</option>
                                            <option value="cardiac_death">Cardiac Death Declaration</option>
                                            <option value="family_consent">Family/Next of Kin Consent</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Consent Date & Time</label>
                                        <input type="datetime-local" name="consent_date" required>
                                    </div>
                                </div>
                                
                                <div class="ethics-approval">
                                    <h4><i class="fas fa-gavel"></i> Legal Witnesses & Documentation</h4>
                                    <div class="form-group legal-required">
                                        <label>Primary Witness (Medical Professional)</label>
                                        <input type="text" name="witness_1" required placeholder="Dr. Name, Qualification, License No.">
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Secondary Witness</label>
                                        <input type="text" name="witness_2" required placeholder="Name, Relationship, ID Proof">
                                    </div>
                                    <div class="form-group">
                                        <label>Legal Guardian (if minor or incapacitated)</label>
                                        <input type="text" name="legal_guardian" placeholder="Guardian Name, Relationship, Legal Authority">
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Consent Document Path/Reference</label>
                                        <input type="text" name="consent_document_path" required placeholder="Document ID, File Path, or Reference Number">
                                    </div>
                                    <div class="form-group">
                                        <label>Notarized</label>
                                        <select name="notarized" required>
                                            <option value="yes">Yes - Notarized</option>
                                            <option value="no">No - Hospital Witness Only</option>
                                            <option value="pending">Pending Notarization</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="sensitive-data">
                                    <h4><i class="fas fa-procedures"></i> Medical & Organ Details</h4>
                                    <div class="form-group">
                                        <label>Donation Type</label>
                                        <select name="donation_type" required>
                                            <option value="living_donation">Living Donation</option>
                                            <option value="deceased_donation">Deceased Donation</option>
                                            <option value="brain_death_donation">Brain Death Donation</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Organ Type</label>
                                        <select name="organ_type" required>
                                            <option value="kidney">Kidney</option>
                                            <option value="liver">Liver</option>
                                            <option value="heart">Heart</option>
                                            <option value="lung">Lung</option>
                                            <option value="pancreas">Pancreas</option>
                                            <option value="small_intestine">Small Intestine</option>
                                            <option value="cornea">Cornea</option>
                                            <option value="bone">Bone</option>
                                            <option value="skin">Skin</option>
                                            <option value="multiple_organs">Multiple Organs</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Medical Evaluation Summary</label>
                                        <textarea name="medical_evaluation" rows="3" required placeholder="Complete medical evaluation, compatibility, contraindications"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Brain Death Confirmation (if applicable)</label>
                                        <select name="brain_death_confirmation">
                                            <option value="not_applicable">Not Applicable</option>
                                            <option value="confirmed">Confirmed by Medical Board</option>
                                            <option value="pending">Pending Confirmation</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Declaration Time (if deceased)</label>
                                        <input type="datetime-local" name="declaration_time">
                                    </div>
                                    <div class="form-group">
                                        <label>Declaring Physician</label>
                                        <input type="text" name="declaring_physician" placeholder="Dr. Name, License No. (if deceased donation)">
                                    </div>
                                    <div class="form-group">
                                        <label>Harvest Team Lead</label>
                                        <input type="text" name="harvest_team_lead" placeholder="Lead Surgeon Name">
                                    </div>
                                    <div class="form-group">
                                        <label>Preservation Method</label>
                                        <select name="preservation_method">
                                            <option value="cold_storage">Cold Storage</option>
                                            <option value="machine_perfusion">Machine Perfusion</option>
                                            <option value="hypothermic_perfusion">Hypothermic Perfusion</option>
                                            <option value="normothermic_perfusion">Normothermic Perfusion</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Expected Ischemia Time (hours)</label>
                                        <input type="number" name="ischemia_time" step="0.5" min="0" max="24">
                                    </div>
                                    <div class="form-group">
                                        <label>Organ Condition Assessment</label>
                                        <textarea name="organ_condition" rows="2" placeholder="Organ viability, quality assessment"></textarea>
                                    </div>
                                </div>
                                
                                <div class="ethics-approval">
                                    <h4><i class="fas fa-balance-scale"></i> Legal & Ethics Clearance</h4>
                                    <div class="form-group legal-required">
                                        <label>Legal Clearance Status</label>
                                        <select name="legal_clearance" required>
                                            <option value="approved">Approved</option>
                                            <option value="pending">Pending Review</option>
                                            <option value="requires_documentation">Requires Additional Documentation</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Ethics Committee Approval</label>
                                        <select name="ethics_committee_approval" required>
                                            <option value="approved">Approved</option>
                                            <option value="pending">Pending Review</option>
                                            <option value="conditional">Conditional Approval</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="digital-signature">
                                    <p>By submitting this form, I certify that all legal documentation is complete and compliant with medical and legal standards.</p>
                                    <button type="submit" class="btn btn-success">Register Donor with Legal Compliance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Register Recipient Tab -->
                <div id="register-recipient" class="tab-content">
                    <h2>Register Organ Recipient - Waiting List Entry</h2>
                    <div class="form-grid">
                        <div class="form-section high-security">
                            <h3><i class="fas fa-user-plus"></i> Recipient Registration & Priority Assessment</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="register_recipient">
                                
                                <div class="sensitive-data">
                                    <div class="form-group legal-required">
                                        <label>Patient</label>
                                        <select name="patient_id" required>
                                            <option value="">Select Patient</option>
                                            <?php foreach ($eligible_recipients as $recipient): ?>
                                                <option value="<?php echo $recipient['id']; ?>">
                                                    <?php echo htmlspecialchars($recipient['patient_id'] . ' - ' . $recipient['first_name'] . ' ' . $recipient['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Organ Needed</label>
                                        <select name="organ_needed" required>
                                            <option value="kidney">Kidney</option>
                                            <option value="liver">Liver</option>
                                            <option value="heart">Heart</option>
                                            <option value="lung">Lung</option>
                                            <option value="pancreas">Pancreas</option>
                                            <option value="small_intestine">Small Intestine</option>
                                            <option value="cornea">Cornea</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Medical Urgency Level</label>
                                        <select name="urgency_level" required>
                                            <option value="critical">Critical (Status 1)</option>
                                            <option value="high">High Priority (Status 2)</option>
                                            <option value="medium">Medium Priority (Status 3)</option>
                                            <option value="low">Low Priority (Status 4)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Waiting List Entry Date</label>
                                        <input type="date" name="waiting_list_date" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Priority Score (0-100)</label>
                                        <input type="number" name="priority_score" min="0" max="100" required placeholder="Based on medical criteria">
                                    </div>
                                    <div class="form-group">
                                        <label>Medical Compatibility Assessment</label>
                                        <textarea name="medical_compatibility" rows="3" required placeholder="Blood type, tissue match, contraindications"></textarea>
                                    </div>
                                </div>
                                
                                <div class="consent-verification">
                                    <h4><i class="fas fa-file-contract"></i> Legal & Financial Verification</h4>
                                    <div class="form-group legal-required">
                                        <label>Insurance Verification</label>
                                        <select name="insurance_verification" required>
                                            <option value="verified">Verified & Approved</option>
                                            <option value="pending">Pending Verification</option>
                                            <option value="partial">Partial Coverage</option>
                                            <option value="self_pay">Self Pay</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Legal Consent Status</label>
                                        <select name="legal_consent" required>
                                            <option value="signed">Fully Signed</option>
                                            <option value="pending">Pending Signature</option>
                                            <option value="conditional">Conditional Consent</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Guardian Consent (if minor)</label>
                                        <select name="guardian_consent">
                                            <option value="not_required">Not Required</option>
                                            <option value="obtained">Obtained</option>
                                            <option value="pending">Pending</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Ethics Committee Approval</label>
                                        <select name="ethics_approval" required>
                                            <option value="approved">Approved</option>
                                            <option value="pending">Pending Review</option>
                                            <option value="conditional">Conditional</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Psychosocial Evaluation</label>
                                        <select name="psychosocial_evaluation" required>
                                            <option value="completed">Completed & Approved</option>
                                            <option value="pending">Pending</option>
                                            <option value="requires_follow_up">Requires Follow-up</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Financial Clearance</label>
                                        <select name="financial_clearance" required>
                                            <option value="cleared">Cleared</option>
                                            <option value="pending">Pending</option>
                                            <option value="conditional">Conditional</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">Add to Waiting List</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Record Transplant Tab -->
                <div id="record-transplant" class="tab-content">
                    <h2>Record Organ Transplant Surgery</h2>
                    <div class="form-grid">
                        <div class="form-section high-security">
                            <h3><i class="fas fa-procedures"></i> Transplant Surgery Documentation</h3>
                            <form method="POST" class="transplant-form">
                                <input type="hidden" name="action" value="record_transplant">
                                
                                <div class="sensitive-data">
                                    <div class="form-group legal-required">
                                        <label>Donor Organ</label>
                                        <select name="donation_id" required>
                                            <option value="">Select Available Organ</option>
                                            <?php foreach ($available_organs as $organ): ?>
                                                <option value="<?php echo $organ['id']; ?>">
                                                    <?php echo $organ['organ_type'] . ' - ' . $organ['first_name'] . ' ' . $organ['last_name'] . ' (Harvested: ' . date('d-M-Y', strtotime($organ['created_at'])) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Recipient</label>
                                        <select name="recipient_id" required>
                                            <option value="">Select Recipient from Waiting List</option>
                                            <?php foreach ($waiting_list as $recipient): ?>
                                                <option value="<?php echo $recipient['id']; ?>">
                                                    <?php echo $recipient['first_name'] . ' ' . $recipient['last_name'] . ' (Priority: ' . $recipient['priority_score'] . ', Waiting: ' . $recipient['days_waiting'] . ' days)'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Surgery Date & Time</label>
                                        <input type="datetime-local" name="surgery_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Lead Surgeon</label>
                                        <select name="lead_surgeon" required>
                                            <option value="">Select Surgeon</option>
                                            <?php foreach ($surgeons as $surgeon): ?>
                                                <option value="<?php echo $surgeon['user_id']; ?>">
                                                    Dr. <?php echo $surgeon['first_name'] . ' ' . $surgeon['last_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Surgical Team</label>
                                        <textarea name="surgical_team" rows="2" required placeholder="List all team members, roles, qualifications"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Operation Duration (hours)</label>
                                        <input type="number" name="operation_duration" step="0.5" min="1" max="24" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Cross Match Result</label>
                                        <select name="cross_match_result" required>
                                            <option value="compatible">Compatible</option>
                                            <option value="incompatible">Incompatible</option>
                                            <option value="conditional">Conditional Compatibility</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Immunosuppression Protocol</label>
                                        <textarea name="immunosuppression_protocol" rows="3" required placeholder="Detailed immunosuppression plan"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Immediate Complications</label>
                                        <textarea name="immediate_complications" rows="2" placeholder="Any immediate post-surgical complications (leave blank if none)"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Post-Op Monitoring Plan</label>
                                        <textarea name="post_op_monitoring_plan" rows="3" required placeholder="Detailed post-operative monitoring and follow-up plan"></textarea>
                                    </div>
                                </div>
                                
                                <div class="consent-verification">
                                    <h4><i class="fas fa-clipboard-check"></i> Legal Compliance Verification</h4>
                                    <div class="form-group legal-required">
                                        <label>Legal Documentation Complete</label>
                                        <select name="legal_documentation_complete" required>
                                            <option value="yes">Yes - All Legal Docs Complete</option>
                                            <option value="no">No - Missing Documentation</option>
                                            <option value="pending">Pending Final Review</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Informed Consent Signed</label>
                                        <select name="informed_consent_signed" required>
                                            <option value="yes">Yes - Fully Signed</option>
                                            <option value="no">No - Not Signed</option>
                                            <option value="partial">Partial Consent</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Ethics Committee Clearance</label>
                                        <select name="ethics_clearance" required>
                                            <option value="approved">Approved</option>
                                            <option value="conditional">Conditional Approval</option>
                                            <option value="pending">Pending</option>
                                        </select>
                                    </div>
                                    <div class="form-group legal-required">
                                        <label>Insurance Approval</label>
                                        <select name="insurance_approval" required>
                                            <option value="approved">Approved</option>
                                            <option value="partial">Partial Coverage</option>
                                            <option value="denied">Denied</option>
                                            <option value="self_pay">Self Pay</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="digital-signature">
                                    <p><strong>CRITICAL MEDICAL PROCEDURE CERTIFICATION:</strong> I hereby certify that this organ transplant has been performed with full legal compliance, proper consent, and ethical approval.</p>
                                    <button type="submit" class="btn btn-danger">Record Transplant with Legal Compliance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- My Activity Tab -->
                <div id="my-activity" class="tab-content">
                    <h2>My Organ Transplant Activity</h2>
                    <div class="form-section">
                        <h3><i class="fas fa-user-md"></i> My Recent Activities</h3>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php foreach ($my_activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-header">
                                        <div class="timeline-title">
                                            <?php echo ucfirst($activity['activity_type']); ?> - <?php echo htmlspecialchars($activity['patient_name']); ?>
                                        </div>
                                        <div class="timeline-time"><?php echo date('d-M-Y H:i', strtotime($activity['activity_date'])); ?></div>
                                    </div>
                                    <div class="timeline-details">
                                        <strong>Details:</strong> <?php echo htmlspecialchars($activity['details']); ?><br>
                                        <strong>Status:</strong> <?php echo ucfirst($activity['status']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Legal Compliance Tab -->
                <div id="legal-compliance" class="tab-content">
                    <h2>Legal Compliance & Audit Trail</h2>
                    <div class="form-grid">
                        <div class="form-section high-security">
                            <h3><i class="fas fa-gavel"></i> Legal Requirements Checklist</h3>
                            <div class="audit-trail">
                                <h4>Critical Legal Requirements:</h4>
                                <ul>
                                    <li>✅ Donor consent verification with legal witnesses</li>
                                    <li>✅ Recipient informed consent with guardian approval (if applicable)</li>
                                    <li>✅ Ethics committee review and approval</li>
                                    <li>✅ Medical board certification for brain death (if applicable)</li>
                                    <li>✅ Insurance verification and financial clearance</li>
                                    <li>✅ Cross-match compatibility verification</li>
                                    <li>✅ Complete surgical team credentials verification</li>
                                    <li>✅ Post-operative monitoring plan documentation</li>
                                </ul>
                            </div>
                            
                            <div class="consent-verification">
                                <h4><i class="fas fa-exclamation-triangle"></i> Legal Violation Reporting</h4>
                                <form method="POST">
                                    <input type="hidden" name="action" value="legal_rejection">
                                    <div class="form-group">
                                        <label>Violation Type</label>
                                        <select name="rejection_type" required>
                                            <option value="consent_withdrawal">Consent Withdrawal</option>
                                            <option value="legal_non_compliance">Legal Non-Compliance</option>
                                            <option value="ethical_violation">Ethical Violation</option>
                                            <option value="medical_contraindication">Medical Contraindication</option>
                                            <option value="documentation_incomplete">Incomplete Documentation</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Reason</label>
                                        <textarea name="rejection_reason" rows="3" required placeholder="Detailed reason for violation/rejection"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Legal Basis</label>
                                        <textarea name="legal_basis" rows="2" required placeholder="Legal statute or regulation basis"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Rejecting Authority</label>
                                        <input type="text" name="rejecting_authority" required placeholder="Authority/Institution Name">
                                    </div>
                                    <div class="form-group">
                                        <label>Rejection Date</label>
                                        <input type="date" name="rejection_date" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Supporting Documentation</label>
                                        <input type="text" name="documentation_path" placeholder="Document reference or file path">
                                    </div>
                                    <button type="submit" class="btn btn-danger">Report Legal Violation</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Legal compliance warning for sensitive actions
        document.addEventListener('DOMContentLoaded', function() {
            const sensitiveButtons = document.querySelectorAll('button[type="submit"]');
            sensitiveButtons.forEach(button => {
                if (button.textContent.includes('Record Transplant') || button.textContent.includes('Register Donor')) {
                    button.addEventListener('click', function(e) {
                        if (!confirm('This action involves sensitive medical data and legal compliance. Are you authorized to perform this action?')) {
                            e.preventDefault();
                        }
                    });
                }
            });
        });
        
        // Auto-save for legal documentation
        setInterval(function() {
            const forms = document.querySelectorAll('.transplant-form');
            forms.forEach(form => {
                const formData = new FormData(form);
                // Auto-save logic can be implemented here
                console.log('Auto-saving legal documentation...');
            });
        }, 30000); // Auto-save every 30 seconds
    </script>
</body>
</html>