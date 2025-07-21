<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    redirect('login.php');
}

$current_user = getCurrentUser();
$user_role = $current_user['role'];

// Check permissions
$authorized_roles = ['admin', 'doctor', 'surgeon', 'transplant_coordinator', 'nurse'];
if (!in_array($user_role, $authorized_roles)) {
    redirect('dashboard.php?error=access_denied');
}

// Get organ monitoring statistics
try {
    $stats = [
        'total_donations' => $db->query("SELECT COUNT(*) as count FROM organ_donations WHERE hospital_id = 1")->fetch()['count'],
        'active_recipients' => $db->query("SELECT COUNT(*) as count FROM organ_recipients WHERE status = 'waiting' AND hospital_id = 1")->fetch()['count'],
        'completed_transplants' => $db->query("SELECT COUNT(*) as count FROM organ_transplants WHERE status = 'completed' AND hospital_id = 1")->fetch()['count'],
        'pending_legal_clearance' => $db->query("SELECT COUNT(*) as count FROM organ_donations WHERE legal_clearance = 'pending' AND hospital_id = 1")->fetch()['count'],
        'pending_ethics_approval' => $db->query("SELECT COUNT(*) as count FROM organ_donations WHERE ethics_committee_approval = 'pending' AND hospital_id = 1")->fetch()['count'],
        'today_activities' => $db->query("SELECT COUNT(*) as count FROM organ_audit_trail WHERE DATE(timestamp) = CURDATE() AND hospital_id = 1")->fetch()['count']
    ];

    // Recent activities
    $recent_activities = $db->query("
        SELECT oat.*, u.first_name, u.last_name 
        FROM organ_audit_trail oat 
        LEFT JOIN users u ON oat.performed_by = u.id 
        WHERE oat.hospital_id = 1 
        ORDER BY oat.timestamp DESC 
        LIMIT 10
    ")->fetchAll();

    // Pending legal/ethics approvals
    $pending_approvals = $db->query("
        SELECT od.*, p.first_name, p.last_name, p.patient_id 
        FROM organ_donations od 
        JOIN patients p ON od.donor_id = p.id 
        WHERE od.hospital_id = 1 
        AND (od.legal_clearance = 'pending' OR od.ethics_committee_approval = 'pending')
        ORDER BY od.created_at DESC
    ")->fetchAll();

    // Active organ recipients waiting
    $waiting_recipients = $db->query("
        SELECT orc.*, p.first_name, p.last_name, p.patient_id, p.blood_group
        FROM organ_recipients orc 
        JOIN patients p ON orc.patient_id = p.id 
        WHERE orc.hospital_id = 1 
        AND orc.status = 'waiting' 
        ORDER BY orc.urgency_level DESC, orc.waiting_list_date ASC
    ")->fetchAll();

    // Available organs
    $available_organs = $db->query("
        SELECT od.*, p.first_name, p.last_name 
        FROM organ_donations od 
        JOIN patients p ON od.donor_id = p.id 
        WHERE od.hospital_id = 1 
        AND od.status IN ('harvested', 'preserved') 
        AND od.legal_clearance = 'approved' 
        AND od.ethics_committee_approval = 'approved'
        ORDER BY od.preservation_time ASC
    ")->fetchAll();

    // Recent transplants
    $recent_transplants = $db->query("
        SELECT ot.*, 
               dp.first_name as donor_first_name, dp.last_name as donor_last_name,
               rp.first_name as recipient_first_name, rp.last_name as recipient_last_name,
               s.first_name as surgeon_first_name, s.last_name as surgeon_last_name
        FROM organ_transplants ot
        JOIN organ_donations od ON ot.donation_id = od.id
        JOIN patients dp ON od.donor_id = dp.id
        JOIN organ_recipients orc ON ot.recipient_id = orc.id
        JOIN patients rp ON orc.patient_id = rp.id
        LEFT JOIN users s ON ot.lead_surgeon = s.id
        WHERE ot.hospital_id = 1
        ORDER BY ot.surgery_date DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    error_log("Organ monitoring error: " . $e->getMessage());
    $stats = ['total_donations' => 0, 'active_recipients' => 0, 'completed_transplants' => 0, 'pending_legal_clearance' => 0, 'pending_ethics_approval' => 0, 'today_activities' => 0];
    $recent_activities = [];
    $pending_approvals = [];
    $waiting_recipients = [];
    $available_organs = [];
    $recent_transplants = [];
}

$page_title = "Organ Transplant Monitoring";
include 'includes/header.php';
?>

<style>
.monitoring-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.monitoring-header {
    text-align: center;
    margin-bottom: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 20px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.monitoring-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: 300;
}

.monitoring-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-left: 5px solid;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-card.total { border-left-color: #3498db; }
.stat-card.waiting { border-left-color: #f39c12; }
.stat-card.completed { border-left-color: #27ae60; }
.stat-card.pending { border-left-color: #e74c3c; }
.stat-card.ethics { border-left-color: #9b59b6; }
.stat-card.activities { border-left-color: #34495e; }

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    margin-bottom: 5px;
    color: #2c3e50;
}

.stat-label {
    color: #7f8c8d;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.monitoring-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    margin: 0;
    font-size: 1.3em;
    font-weight: 500;
}

.section-content {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #ecf0f1;
    transition: background-color 0.3s ease;
}

.activity-item:hover {
    background-color: #f8f9fa;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: white;
    font-size: 1.2em;
}

.activity-icon.legal { background: #e74c3c; }
.activity-icon.ethics { background: #9b59b6; }
.activity-icon.transplant { background: #27ae60; }
.activity-icon.donor { background: #3498db; }
.activity-icon.recipient { background: #f39c12; }

.activity-details h4 {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-size: 1em;
}

.activity-details p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9em;
}

.recipient-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid;
}

.recipient-item.critical { border-left-color: #e74c3c; }
.recipient-item.high { border-left-color: #f39c12; }
.recipient-item.medium { border-left-color: #f1c40f; }
.recipient-item.low { border-left-color: #27ae60; }

.recipient-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.recipient-name {
    font-weight: bold;
    color: #2c3e50;
}

.urgency-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    color: white;
    font-weight: bold;
}

.urgency-badge.critical { background: #e74c3c; }
.urgency-badge.high { background: #f39c12; }
.urgency-badge.medium { background: #f1c40f; }
.urgency-badge.low { background: #27ae60; }

.recipient-details {
    font-size: 0.9em;
    color: #7f8c8d;
}

.organ-item {
    background: #e8f5e8;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid #27ae60;
}

.organ-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.organ-type {
    font-weight: bold;
    color: #27ae60;
    text-transform: capitalize;
}

.preservation-time {
    font-size: 0.8em;
    color: #e74c3c;
    font-weight: bold;
}

.transplant-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid #3498db;
}

.transplant-details {
    font-size: 0.9em;
    color: #7f8c8d;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    color: white;
    font-weight: bold;
}

.status-badge.completed { background: #27ae60; }
.status-badge.in_progress { background: #f39c12; }
.status-badge.scheduled { background: #3498db; }
.status-badge.failed { background: #e74c3c; }

.alert-section {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.alert-header {
    color: #856404;
    font-weight: bold;
    margin-bottom: 10px;
}

.no-data {
    text-align: center;
    color: #7f8c8d;
    font-style: italic;
    padding: 40px 20px;
}

.refresh-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9em;
    transition: opacity 0.3s ease;
}

.refresh-btn:hover {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .stat-number {
        font-size: 2em;
    }
}
</style>

<div class="monitoring-container">
    <div class="monitoring-header">
        <h1><i class="fas fa-heart"></i> Organ Transplant Monitoring</h1>
        <p>Real-time monitoring of organ donations, recipients, and transplant activities</p>
    </div>

    <!-- Statistics Overview -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-number"><?php echo $stats['total_donations']; ?></div>
            <div class="stat-label">Total Donations</div>
        </div>
        <div class="stat-card waiting">
            <div class="stat-number"><?php echo $stats['active_recipients']; ?></div>
            <div class="stat-label">Waiting Recipients</div>
        </div>
        <div class="stat-card completed">
            <div class="stat-number"><?php echo $stats['completed_transplants']; ?></div>
            <div class="stat-label">Completed Transplants</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-number"><?php echo $stats['pending_legal_clearance']; ?></div>
            <div class="stat-label">Pending Legal</div>
        </div>
        <div class="stat-card ethics">
            <div class="stat-number"><?php echo $stats['pending_ethics_approval']; ?></div>
            <div class="stat-label">Pending Ethics</div>
        </div>
        <div class="stat-card activities">
            <div class="stat-number"><?php echo $stats['today_activities']; ?></div>
            <div class="stat-label">Today's Activities</div>
        </div>
    </div>

    <!-- Alerts Section -->
    <?php if ($stats['pending_legal_clearance'] > 0 || $stats['pending_ethics_approval'] > 0): ?>
    <div class="alert-section">
        <div class="alert-header">
            <i class="fas fa-exclamation-triangle"></i> Urgent Attention Required
        </div>
        <p>
            <?php if ($stats['pending_legal_clearance'] > 0): ?>
                <strong><?php echo $stats['pending_legal_clearance']; ?></strong> organ donations pending legal clearance. 
            <?php endif; ?>
            <?php if ($stats['pending_ethics_approval'] > 0): ?>
                <strong><?php echo $stats['pending_ethics_approval']; ?></strong> organ donations pending ethics committee approval.
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Recent Activities -->
        <div class="monitoring-section">
            <h3 class="section-header">
                <i class="fas fa-clock"></i> Recent Activities
                <button class="refresh-btn" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </h3>
            <div class="section-content">
                <?php if (empty($recent_activities)): ?>
                    <div class="no-data">No recent activities found</div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo strtolower($activity['action']); ?>">
                                <i class="fas fa-<?php 
                                    switch(strtolower($activity['action'])) {
                                        case 'legal': echo 'gavel'; break;
                                        case 'ethics': echo 'balance-scale'; break;
                                        case 'transplant': echo 'procedures'; break;
                                        case 'donor': echo 'hand-holding-heart'; break;
                                        case 'recipient': echo 'user-plus'; break;
                                        default: echo 'clipboard-list';
                                    }
                                ?>"></i>
                            </div>
                            <div class="activity-details">
                                <h4><?php echo htmlspecialchars($activity['action']); ?></h4>
                                <p>
                                    <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?> - 
                                    <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Waiting Recipients -->
        <div class="monitoring-section">
            <h3 class="section-header">
                <i class="fas fa-users"></i> Waiting Recipients
            </h3>
            <div class="section-content">
                <?php if (empty($waiting_recipients)): ?>
                    <div class="no-data">No recipients currently waiting</div>
                <?php else: ?>
                    <?php foreach ($waiting_recipients as $recipient): ?>
                        <div class="recipient-item <?php echo strtolower($recipient['urgency_level']); ?>">
                            <div class="recipient-header">
                                <div class="recipient-name">
                                    <?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?>
                                </div>
                                <div class="urgency-badge <?php echo strtolower($recipient['urgency_level']); ?>">
                                    <?php echo ucfirst($recipient['urgency_level']); ?>
                                </div>
                            </div>
                            <div class="recipient-details">
                                Patient ID: <?php echo htmlspecialchars($recipient['patient_id']); ?> | 
                                Blood Group: <?php echo htmlspecialchars($recipient['blood_group']); ?> | 
                                Organ Needed: <?php echo ucfirst($recipient['organ_needed']); ?> | 
                                Waiting Since: <?php echo date('M j, Y', strtotime($recipient['waiting_list_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Organs -->
        <div class="monitoring-section">
            <h3 class="section-header">
                <i class="fas fa-heart"></i> Available Organs
            </h3>
            <div class="section-content">
                <?php if (empty($available_organs)): ?>
                    <div class="no-data">No organs currently available</div>
                <?php else: ?>
                    <?php foreach ($available_organs as $organ): ?>
                        <div class="organ-item">
                            <div class="organ-header">
                                <div class="organ-type">
                                    <?php echo ucfirst($organ['organ_type']); ?>
                                </div>
                                <div class="preservation-time">
                                    Preserved: <?php echo date('M j, g:i A', strtotime($organ['preservation_time'])); ?>
                                </div>
                            </div>
                            <div class="recipient-details">
                                Donor: <?php echo htmlspecialchars($organ['first_name'] . ' ' . $organ['last_name']); ?> | 
                                Status: <?php echo ucfirst($organ['status']); ?> | 
                                Legal: <?php echo ucfirst($organ['legal_clearance']); ?> | 
                                Ethics: <?php echo ucfirst($organ['ethics_committee_approval']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Transplants -->
        <div class="monitoring-section">
            <h3 class="section-header">
                <i class="fas fa-procedures"></i> Recent Transplants
            </h3>
            <div class="section-content">
                <?php if (empty($recent_transplants)): ?>
                    <div class="no-data">No recent transplants found</div>
                <?php else: ?>
                    <?php foreach ($recent_transplants as $transplant): ?>
                        <div class="transplant-item">
                            <div class="recipient-header">
                                <div class="recipient-name">
                                    Transplant #<?php echo $transplant['id']; ?>
                                </div>
                                <div class="status-badge <?php echo strtolower($transplant['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $transplant['status'])); ?>
                                </div>
                            </div>
                            <div class="transplant-details">
                                Donor: <?php echo htmlspecialchars($transplant['donor_first_name'] . ' ' . $transplant['donor_last_name']); ?> | 
                                Recipient: <?php echo htmlspecialchars($transplant['recipient_first_name'] . ' ' . $transplant['recipient_last_name']); ?> | 
                                Surgeon: <?php echo htmlspecialchars($transplant['surgeon_first_name'] . ' ' . $transplant['surgeon_last_name']); ?> | 
                                Date: <?php echo date('M j, Y', strtotime($transplant['surgery_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto refresh every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);

// Add real-time clock
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    document.title = 'Organ Monitoring - ' + timeString;
}

setInterval(updateClock, 1000);
updateClock();
</script>

<?php include 'includes/footer.php'; ?>