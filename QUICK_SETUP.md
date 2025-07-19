# 🚀 Quick Setup Guide - Hospital CRM

## Immediate Testing Steps

### 1. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE hospital_crm;
USE hospital_crm;

# Import schema
mysql -u root -p hospital_crm < database_schema.sql
```

### 2. Configuration
Update `config/database.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'hospital_crm');
```

### 3. Test System
1. Run test script: `http://your-domain/test-setup.php`
2. Check all ✅ marks
3. Access login: `http://your-domain/index.php`

### 4. Demo Login Credentials
- **Admin:** admin@hospital.com / password
- **Doctor:** dr.sharma@hospital.com / password  
- **Patient:** john.doe@email.com / password
- **Nurse:** priya.nurse@hospital.com / password
- **Reception:** reception@hospital.com / password

## 🧪 Testing Checklist

### ✅ System Components
- [ ] Database connection working
- [ ] All 32 tables created
- [ ] Sample data loaded
- [ ] File permissions set
- [ ] CSS and assets loading

### ✅ Core Features
- [ ] Multi-role login system
- [ ] Dashboard with statistics
- [ ] Patient management
- [ ] Doctor management
- [ ] Appointment booking
- [ ] Billing system
- [ ] Pharmacy management
- [ ] Laboratory management

### ✅ Advanced Features
- [ ] Patient vitals recording
- [ ] Inpatient/outpatient conversion
- [ ] Bed management
- [ ] Lab technician interface
- [ ] Attendance system
- [ ] Settings configuration
- [ ] Security features

## 🔧 Troubleshooting

### Database Connection Issues
- Check database credentials in `config/database.php`
- Ensure MySQL service is running
- Verify database `hospital_crm` exists

### File Permission Issues
```bash
chmod 755 -R /path/to/hospital-crm
chmod 777 uploads/ logs/
```

### Missing Tables
- Re-import `database_schema.sql`
- Check MySQL user permissions

### Login Issues
- Verify demo credentials
- Check session configuration
- Clear browser cache

## 📞 Support
If you encounter issues:
1. Run `test-setup.php` to identify problems
2. Check error logs in `logs/` directory
3. Verify all requirements are met

## 🎉 Success!
Once all tests pass, your Hospital CRM system is ready for use!