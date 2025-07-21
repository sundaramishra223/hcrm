# 🏥 HCRM Complete Database Setup Guide

## 📋 Overview
This guide provides complete instructions for setting up the Hospital CRM (HCRM) database system with all modules included.

## 🎯 What's Included

### ✅ **Core Hospital Management**
- **User Management**: Multi-role authentication system
- **Patient Management**: Complete patient records and tracking
- **Doctor Management**: Doctor profiles, schedules, and assignments
- **Staff Management**: Hospital staff with roles and departments
- **Appointment System**: Booking, scheduling, and management

### ✅ **Medical Services** 
- **Laboratory System**: Tests, orders, results management
- **Pharmacy Management**: Medicine inventory, prescriptions, dispensing
- **Patient Vitals**: Vital signs tracking and monitoring
- **Patient Visits**: Visit records and medical history

### ✅ **Specialized Modules**
- **Blood Bank Management**: Donor management, inventory, requests
- **Organ Transplant System**: Legal compliant organ donation/transplant tracking
- **Insurance Management**: Claims, policies, company management
- **Equipment Management**: Hospital equipment tracking and maintenance

### ✅ **Operations Management**
- **Billing System**: Comprehensive billing with insurance integration
- **Bed Management**: Bed assignments, availability tracking
- **Ambulance Management**: Vehicle and booking management
- **Shift Management**: Staff scheduling and attendance

### ✅ **Security & Audit**
- **Security Logs**: Complete security event tracking
- **Audit Trails**: Database change tracking
- **Login Monitoring**: Failed login attempts and security monitoring
- **Log Cleanup System**: Automated old log cleanup

## 🚀 Quick Installation

### Method 1: Single File Installation (Recommended)
```bash
# 1. Create database and import
mysql -u root -p -e "CREATE DATABASE hospital_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 2. Import the complete schema
mysql -u root -p hospital_crm < INSTALL_COMPLETE_HCRM.sql
```

### Method 2: Step-by-Step Installation
```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE hospital_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 2. Import parts in order
mysql -u root -p hospital_crm < COMPLETE_HCRM_DATABASE.sql
mysql -u root -p hospital_crm < COMPLETE_HCRM_DATABASE_PART2.sql  
mysql -u root -p hospital_crm < COMPLETE_HCRM_DATABASE_PART3.sql
mysql -u root -p hospital_crm < COMPLETE_HCRM_DATABASE_FINAL.sql
```

## 📊 Database Structure

### **Core Tables (50+ tables)**

#### **User Management**
- `hospitals` - Multi-hospital support
- `users` - Authentication and user roles
- `departments` - Hospital departments

#### **Patient Care**
- `patients` - Patient records
- `doctors` - Doctor profiles  
- `staff` - Hospital staff
- `appointments` - Appointment scheduling
- `patient_vitals` - Vital signs tracking
- `patient_visits` - Visit records
- `patient_status_logs` - Status change tracking

#### **Medical Services**
- `lab_tests` - Available lab tests
- `lab_orders` - Test orders and results
- `medicines` - Medicine inventory
- `prescriptions` - Prescription management
- `medicine_categories` - Medicine categorization

#### **Blood Bank System**
- `blood_donors` - Donor management
- `blood_donation_sessions` - Donation tracking
- `blood_inventory` - Blood inventory management
- `blood_requests` - Blood requests
- `blood_usage_records` - Usage tracking

#### **Organ Transplant System** 
- `organ_donor_consent` - Legal consent tracking
- `organ_donations` - Organ donation records
- `organ_recipients` - Recipient waiting list
- `organ_transplants` - Transplant procedures
- `organ_legal_rejections` - Legal rejection tracking

#### **Insurance System**
- `insurance_companies` - Insurance providers
- `patient_insurance` - Patient policies
- `insurance_claims` - Claims management

#### **Billing System**
- `bills` - Patient bills
- `bill_items` - Bill line items
- `bill_payments` - Payment tracking

#### **Equipment & Facilities**
- `equipment` - Equipment inventory
- `equipment_maintenance` - Maintenance tracking
- `beds` - Bed management
- `bed_assignments` - Patient bed assignments

#### **Ambulance System**
- `ambulances` - Vehicle management
- `ambulance_bookings` - Booking system

#### **Security & Audit**
- `security_logs` - Security events
- `login_attempts` - Login monitoring
- `audit_logs` - Database change tracking
- `email_logs` - Email communication logs

#### **System Management**
- `system_settings` - Configuration
- `email_templates` - Email templates
- `staff_shifts` - Shift scheduling
- `staff_attendance` - Attendance tracking

## 🔧 Configuration

### Database Connection (config/database.php)
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hospital_crm');
define('DB_CHARSET', 'utf8mb4');
```

### Default Login Credentials
- **Username**: `admin`
- **Email**: `admin@hospital.com` 
- **Password**: `password` (Change immediately!)

## 🔍 Key Features

### **1. Multi-Hospital Support**
- Single database supports multiple hospitals
- Hospital-specific data isolation
- Centralized user management

### **2. Role-Based Access Control**
- **Admin**: Full system access
- **Doctor**: Patient care, prescriptions, appointments
- **Nurse**: Patient vitals, monitoring, care
- **Receptionist**: Appointments, patient registration
- **Pharmacy Staff**: Medicine management, dispensing
- **Lab Technician**: Lab tests, results
- **Patient**: Personal records, appointments, bills
- **Staff**: Department-specific access
- **Driver**: Ambulance management

### **3. Comprehensive Audit Trail**
- All database changes logged
- User action tracking
- Security event monitoring
- Automatic log cleanup

### **4. Legal Compliance**
- Organ transplant legal tracking
- Patient consent management
- Insurance claim compliance
- Medical record audit trails

### **5. Performance Optimized**
- Proper indexing for fast queries
- Generated columns for calculations
- Optimized foreign key relationships
- Efficient query patterns

## 📈 Advanced Features

### **Blood Bank Dashboard View**
```sql
SELECT * FROM blood_inventory_dashboard;
-- Shows real-time blood inventory status
```

### **Automatic Log Cleanup**
```php
// Manual cleanup
$cleanup = new LogCleanupHelper($db);
$result = $cleanup->cleanAllLogs();

// Scheduled cleanup (add to cron)
php cleanup-logs.php --type=all
```

### **Security Monitoring**
- Failed login attempt tracking
- Suspicious activity detection
- Session management
- IP-based access monitoring

## 🛠️ Maintenance

### **Regular Tasks**
1. **Log Cleanup**: Run monthly
   ```bash
   php cleanup-logs.php
   ```

2. **Database Backup**: Daily recommended
   ```bash
   mysqldump -u username -p hospital_crm > backup_$(date +%Y%m%d).sql
   ```

3. **Security Review**: Weekly audit log review

### **Performance Monitoring**
- Monitor table sizes
- Check slow query log
- Review index usage
- Monitor disk space

## 🚨 Security Considerations

### **Database Security**
- Use strong passwords
- Enable SSL connections
- Regular security updates
- Backup encryption

### **Application Security**
- Input validation
- SQL injection prevention
- XSS protection
- CSRF tokens

### **Access Control**
- Role-based permissions
- Session management
- Password policies
- Two-factor authentication (configurable)

## 🐛 Troubleshooting

### **Common Issues**

1. **Foreign Key Errors**
   ```sql
   SET FOREIGN_KEY_CHECKS = 0;
   -- Run your queries
   SET FOREIGN_KEY_CHECKS = 1;
   ```

2. **Character Set Issues**
   ```sql
   ALTER DATABASE hospital_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```

3. **Permission Errors**
   ```sql
   GRANT ALL PRIVILEGES ON hospital_crm.* TO 'username'@'localhost';
   FLUSH PRIVILEGES;
   ```

## 📞 Support

### **Error Reporting**
- Enable error logging in PHP
- Check database error logs
- Monitor application logs

### **Performance Issues**
- Use EXPLAIN for slow queries
- Check index usage
- Monitor table sizes
- Consider partitioning for large tables

## 🎉 Completion Checklist

- [ ] Database created successfully
- [ ] All tables imported without errors
- [ ] Default data populated
- [ ] Admin user created
- [ ] Database connection configured
- [ ] PHP application can connect
- [ ] All modules accessible in admin panel
- [ ] Log cleanup system tested
- [ ] Security settings configured
- [ ] Backup system in place

## 📝 Notes

- **Total Tables**: 50+ comprehensive tables
- **Database Size**: ~10-20MB (empty)
- **Estimated Production Size**: 100MB-1GB+ (depending on usage)
- **Supported MySQL/MariaDB**: 5.7+ / 10.2+
- **PHP Requirements**: 7.4+ with PDO MySQL extension

---

**🎯 Your HCRM system is now ready for production use!**

**Important**: Change default passwords and configure security settings before going live.