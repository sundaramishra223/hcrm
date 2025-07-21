#!/bin/bash

echo "🚀 Committing HCRM Admin Menu & Log Cleanup Updates..."

# Add all changes
git add .

# Commit with detailed message
git commit -m "✨ Add admin menu organization and comprehensive log cleanup system

🔧 Admin Menu Updates:
- Added missing admin menu items for blood bank, organ management, and insurance
- Created new admin-dashboard.php with organized module categories  
- Updated includes/sidebar.php and dashboard.php with all management modules
- Added Blood Donation Tracking access for medical staff

🧹 Log Cleanup System:
- Added comprehensive log cleanup system with LogCleanupHelper class
- Created security_logs_setup.sql for missing database tables
- Added cleanup-logs.php standalone script for manual/cron execution
- Enhanced existing security-helper.php with cleanup integration

📁 Files Created/Updated:
- admin-dashboard.php (NEW) - Comprehensive admin control panel
- includes/log-cleanup-helper.php (NEW) - Log cleanup functionality
- cleanup-logs.php (NEW) - Standalone cleanup script
- sql/security_logs_setup.sql (NEW) - Database setup for security tables
- includes/sidebar.php (UPDATED) - All modules now visible
- dashboard.php (UPDATED) - Admin menu organized
- includes/security-helper.php (UPDATED) - Cleanup integration

🎯 Result: Admin can now easily access all features (blood bank, organ management, insurance) 
through organized menu structure and comprehensive control panel."

# Check status
git status

echo "✅ Changes committed! Now push to GitHub manually:"
echo "git push origin cursor/clean-up-old-logs-8770"