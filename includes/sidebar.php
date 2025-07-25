<?php
// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
        <p><?php echo htmlspecialchars($_SESSION['role_display'] ?? ucfirst($_SESSION['role'])); ?></p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        
        <?php if ($user_role === 'patient'): ?>
            <li><a href="patient-portal.php" class="<?php echo $current_page === 'patient-portal.php' ? 'active' : ''; ?>"><i class="fas fa-user-circle"></i> My Portal</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
            <li><a href="patients.php" class="<?php echo $current_page === 'patients.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Patients</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
            <li><a href="doctors.php" class="<?php echo $current_page === 'doctors.php' ? 'active' : ''; ?>"><i class="fas fa-user-md"></i> Doctors</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
            <li><a href="appointments.php" class="<?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
            <li><a href="pharmacy.php" class="<?php echo $current_page === 'pharmacy.php' ? 'active' : ''; ?>"><i class="fas fa-pills"></i> Pharmacy</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor', 'lab_technician', 'intern_lab'])): ?>
            <li><a href="laboratory.php" class="<?php echo $current_page === 'laboratory.php' ? 'active' : ''; ?>"><i class="fas fa-flask"></i> Laboratory</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
            <li><a href="prescriptions.php" class="<?php echo $current_page === 'prescriptions.php' ? 'active' : ''; ?>"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
            <li><a href="billing.php" class="<?php echo $current_page === 'billing.php' ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
            <li><a href="insurance.php" class="<?php echo $current_page === 'insurance.php' ? 'active' : ''; ?>"><i class="fas fa-shield-alt"></i> Insurance</a></li>
        <?php endif; ?>
        
        <li><a href="blood-bank.php" class="<?php echo $current_page === 'blood-bank.php' ? 'active' : ''; ?>"><i class="fas fa-tint"></i> Blood Bank</a></li>
        <li><a href="organ-donation.php" class="<?php echo $current_page === 'organ-donation.php' ? 'active' : ''; ?>"><i class="fas fa-heart"></i> Organ Donation</a></li>
        
        <?php if (in_array($user_role, ['admin'])): ?>
            <li><a href="staff.php" class="<?php echo $current_page === 'staff.php' ? 'active' : ''; ?>"><i class="fas fa-user-tie"></i> Staff</a></li>
            <li><a href="equipment.php" class="<?php echo $current_page === 'equipment.php' ? 'active' : ''; ?>"><i class="fas fa-tools"></i> Equipment</a></li>
            <li><a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
        <?php endif; ?>
        
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>