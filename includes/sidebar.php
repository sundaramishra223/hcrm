<?php
// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Function to check if current page should have active class
function isActive($page_names) {
    global $current_page;
    if (is_array($page_names)) {
        return in_array($current_page, $page_names) ? 'active' : '';
    }
    return $current_page === $page_names ? 'active' : '';
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
        <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        
        <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
            <li><a href="patients.php" class="<?php echo isActive(['patients.php', 'patient-details.php', 'add-patient.php']); ?>"><i class="fas fa-users"></i> Patients</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
            <li><a href="doctors.php" class="<?php echo isActive(['doctors.php', 'doctor-details.php']); ?>"><i class="fas fa-user-md"></i> Doctors</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
            <li><a href="appointments.php" class="<?php echo isActive(['appointments.php', 'book-appointment.php']); ?>"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
            <li><a href="billing.php" class="<?php echo isActive(['billing.php', 'invoice.php']); ?>"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'pharmacy_staff', 'intern_pharmacy'])): ?>
            <li><a href="pharmacy.php" class="<?php echo isActive(['pharmacy.php', 'medicine-details.php', 'manage-categories.php']); ?>"><i class="fas fa-pills"></i> Pharmacy</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab', 'doctor', 'intern_doctor'])): ?>
            <li><a href="lab-test-management.php" class="<?php echo isActive(['lab-test-management.php', 'lab-technician.php', 'laboratory.php']); ?>"><i class="fas fa-flask"></i> Lab Tests</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'nurse', 'intern_nurse'])): ?>
            <li><a href="patient-vitals.php" class="<?php echo isActive('patient-vitals.php'); ?>"><i class="fas fa-heartbeat"></i> Patient Vitals</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist', 'intern_doctor', 'intern_nurse'])): ?>
            <li><a href="patient-monitoring.php" class="<?php echo isActive('patient-monitoring.php'); ?>"><i class="fas fa-user-injured"></i> Patient Monitoring</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin'])): ?>
            <li><a href="equipment.php" class="<?php echo isActive('equipment.php'); ?>"><i class="fas fa-tools"></i> Equipment</a></li>
            <li><a href="beds.php" class="<?php echo isActive('beds.php'); ?>"><i class="fas fa-bed"></i> Bed Management</a></li>
            <li><a href="intern-management.php" class="<?php echo isActive('intern-management.php'); ?>"><i class="fas fa-graduation-cap"></i> Intern Management</a></li>
            <li><a href="attendance.php" class="<?php echo isActive('attendance.php'); ?>"><i class="fas fa-clock"></i> Attendance</a></li>
        <?php endif; ?>
        
        <li><a href="profile.php" class="<?php echo isActive('profile.php'); ?>"><i class="fas fa-user"></i> My Profile</a></li>
        
        <?php if (in_array($user_role, ['admin'])): ?>
            <li><a href="settings.php" class="<?php echo isActive('settings.php'); ?>"><i class="fas fa-cog"></i> Settings</a></li>
        <?php endif; ?>
        
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
    
    <!-- Sidebar Toggle Button -->
    <div class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>
</aside>