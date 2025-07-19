# Hospital CRM System

A comprehensive Hospital & Clinic Management System built with PHP, MySQL, and modern web technologies. This system provides a complete solution for managing hospital operations with enhanced security, role-based access control, and modern UI design.

## 🏥 Features Overview

### Core Features
- **Multi-Role Login System** - Admin, Doctor, Nurse, Patient, Staff, Pharmacy, Lab Tech, Receptionist
- **Patient Management** - Complete patient lifecycle management
- **Doctor Management** - Doctor profiles, schedules, and assignments
- **Appointment System** - Advanced appointment booking with conflict detection
- **Billing System** - Comprehensive billing with auto-billing and insurance integration
- **Pharmacy Management** - Medicine inventory and prescription management
- **Laboratory Management** - Test orders and result management
- **Equipment Management** - Medical equipment tracking
- **Staff Management** - Employee management with attendance tracking
- **Reports & Analytics** - Comprehensive reporting system

### Advanced Features
- **Cliniva Angular Theme** - Modern, responsive design with dark mode
- **Customizable Branding** - Logo, colors, and theme customization
- **Inpatient/Outpatient Management** - Patient type conversion system
- **Bed Management** - Hospital bed assignment and tracking
- **Patient Vitals** - Comprehensive vital signs tracking
- **Attendance System** - Staff attendance with salary calculation
- **Intern System** - Intern management for various roles
- **Security Features** - Enhanced security with encryption and audit logging

## 🛠 Technical Stack

- **Backend:** PHP 8.0+
- **Database:** MySQL 8.0+
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **UI Framework:** Custom Cliniva-inspired design
- **Security:** AES-256-GCM encryption, Argon2id password hashing
- **Charts:** Chart.js for data visualization

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- SSL certificate (recommended for production)
- Modern web browser with JavaScript enabled

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/your-username/hospital-crm.git
cd hospital-crm
```

### 2. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE hospital_crm;
USE hospital_crm;

# Import database schema
mysql -u root -p hospital_crm < database_schema.sql
```

### 3. Configuration
```bash
# Copy configuration file
cp config/database.example.php config/database.php

# Edit database configuration
nano config/database.php
```

Update the database configuration:
```php
<?php
return [
    'host' => 'localhost',
    'dbname' => 'hospital_crm',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4'
];
?>
```

### 4. File Permissions
```bash
# Set proper permissions
chmod 755 -R /path/to/hospital-crm
chmod 777 -R uploads/
chmod 777 -R logs/
```

### 5. Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/hospital-crm;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Initial Setup
1. Access the system: `http://your-domain.com`
2. Default admin credentials:
   - Username: `admin`
   - Password: `admin123`
3. Change default password immediately
4. Configure system settings in Admin Panel

## 👥 User Roles & Permissions

### Admin
- Full system access
- User management
- System configuration
- Reports and analytics
- Security management

### Doctor
- Patient management (assigned patients only)
- Appointment management
- Prescription management
- Patient vitals recording
- Medical history access

### Nurse
- Patient vitals recording
- Bed management
- Patient care coordination
- Limited patient access

### Lab Technician
- Lab test management
- Result upload
- Equipment management
- No patient contact information access

### Receptionist
- Patient registration
- Appointment booking
- Billing management
- Patient information management

### Pharmacy Staff
- Medicine inventory
- Prescription dispensing
- Stock management
- Billing integration

### Intern Roles
- Limited access based on role
- Supervised by senior staff
- Training mode features

## 🔐 Security Features

### Authentication & Authorization
- Multi-factor authentication support
- Role-based access control (RBAC)
- Session management with secure tokens
- Password policies and complexity requirements

### Data Protection
- AES-256-GCM encryption for sensitive data
- Argon2id password hashing
- Input sanitization and validation
- SQL injection prevention
- XSS protection

### Audit & Monitoring
- Comprehensive audit logging
- Security event monitoring
- Suspicious activity detection
- Login attempt tracking
- User activity history

### Network Security
- CSRF protection
- Rate limiting
- IP-based access control
- Secure headers implementation

## 📊 Key Modules

### 1. Patient Management
- Patient registration and profiles
- Medical history tracking
- Vital signs monitoring
- Appointment history
- Billing history

### 2. Appointment System
- Advanced booking with conflict detection
- Doctor schedule management
- Appointment reminders
- Status tracking
- Calendar integration

### 3. Billing & Insurance
- Comprehensive billing system
- Insurance claim management
- Payment processing
- Receipt generation
- Financial reporting

### 4. Pharmacy Management
- Medicine inventory
- Prescription management
- Stock tracking
- Expiry date monitoring
- Supplier management

### 5. Laboratory Management
- Test order management
- Result upload and tracking
- Equipment management
- Quality control
- Report generation

### 6. Staff Management
- Employee profiles
- Attendance tracking
- Salary calculation
- Performance monitoring
- Training records

## 🎨 UI/UX Features

### Design System
- Cliniva Angular-inspired design
- Responsive layout for all devices
- Dark/Light mode toggle
- Customizable color schemes
- Modern typography

### User Experience
- Intuitive navigation
- Quick action buttons
- Search and filter functionality
- Data visualization with charts
- Mobile-friendly interface

### Accessibility
- WCAG 2.1 compliance
- Keyboard navigation support
- Screen reader compatibility
- High contrast mode
- Font size adjustment

## 📈 Reporting & Analytics

### Dashboard Analytics
- Real-time statistics
- Revenue tracking
- Patient demographics
- Staff performance metrics
- Equipment utilization

### Custom Reports
- Patient reports
- Financial reports
- Inventory reports
- Attendance reports
- Audit reports

### Data Export
- PDF report generation
- Excel export functionality
- CSV data export
- Email report delivery

## 🔧 Configuration & Customization

### System Settings
- Hospital information
- Contact details
- Logo and branding
- Email configuration
- SMS integration

### Module Management
- Enable/disable modules
- Feature toggles
- Custom fields
- Workflow configuration
- Notification settings

### Security Configuration
- Password policies
- Session timeout
- IP restrictions
- Audit log retention
- Backup settings

## 📱 Mobile Support

- Responsive design for all screen sizes
- Touch-friendly interface
- Mobile-optimized forms
- Offline capability for critical functions
- Progressive Web App (PWA) features

## 🔄 Backup & Maintenance

### Database Backup
```bash
# Automated backup script
mysqldump -u username -p hospital_crm > backup_$(date +%Y%m%d_%H%M%S).sql
```

### File Backup
```bash
# Backup uploads and configuration
tar -czf hospital_crm_backup_$(date +%Y%m%d).tar.gz uploads/ config/
```

### Maintenance Tasks
- Regular database optimization
- Log file rotation
- Cache clearing
- Security updates
- Performance monitoring

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Error
```php
// Check database configuration
// Verify MySQL service is running
// Test connection credentials
```

#### Permission Errors
```bash
# Fix file permissions
chmod 755 -R /path/to/hospital-crm
chmod 777 -R uploads/ logs/
```

#### Session Issues
```php
// Check session configuration
// Verify session storage permissions
// Clear browser cache
```

### Error Logs
- Check PHP error logs: `/var/log/php/error.log`
- Check Apache/Nginx logs: `/var/log/apache2/` or `/var/log/nginx/`
- Check application logs: `logs/` directory

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation:** [Wiki](https://github.com/your-username/hospital-crm/wiki)
- **Issues:** [GitHub Issues](https://github.com/your-username/hospital-crm/issues)
- **Email:** support@hospital-crm.com
- **Community:** [Discord Server](https://discord.gg/hospital-crm)

## 🙏 Acknowledgments

- Cliniva Angular theme inspiration
- Medical professionals for domain expertise
- Open source community for libraries and tools
- Contributors and beta testers

## 📝 Changelog

### Version 2.0.0 (Current)
- Complete system overhaul
- Enhanced security features
- Modern UI/UX design
- Multi-role support
- Advanced reporting

### Version 1.0.0
- Initial release
- Basic hospital management features
- Core modules implementation

---

**Note:** This is a comprehensive hospital management system designed for production use. Please ensure proper security measures and compliance with healthcare regulations in your deployment.