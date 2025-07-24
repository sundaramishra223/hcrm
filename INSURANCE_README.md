# 🏥 Hospital CRM - Insurance Management System

## 📋 Overview

The **Insurance Management System** is a comprehensive module integrated into the Hospital CRM that allows hospitals to manage insurance companies, patient policies, claims processing, and pre-authorizations efficiently.

## 🚀 Features

### 🏢 Insurance Companies Management
- **Company Registration**: Add and manage insurance companies
- **Company Details**: Store license numbers, contact information, coverage types
- **Network Management**: Track network hospitals and partnerships
- **Cashless Limits**: Set and monitor cashless treatment limits
- **Reimbursement Settings**: Configure reimbursement percentages and settlement timelines

### 📄 Patient Insurance Policies
- **Policy Registration**: Link patients with their insurance policies
- **Policy Types**: Support for Individual, Family, Group, and Corporate policies
- **Coverage Tracking**: Monitor coverage amounts, deductibles, and co-payments
- **Expiry Management**: Track policy validity and expiration dates
- **Document Storage**: Store policy documents and related files
- **Multi-Policy Support**: Patients can have multiple active policies

### 🏥 Insurance Claims Processing
- **Claim Types**: 
  - **Cashless**: Direct settlement with insurance companies
  - **Reimbursement**: Patient pays first, gets reimbursed later
  - **Emergency**: Immediate treatment coverage
- **Treatment Categories**: Outpatient, Inpatient, Emergency, Surgery, Diagnostic
- **Claim Tracking**: Complete lifecycle from submission to settlement
- **Status Management**: Pending → Under Review → Approved/Rejected → Paid
- **Document Management**: Track submitted documents and requirements
- **Settlement Tracking**: Monitor approved amounts and final settlements

### 🔐 Pre-Authorization System
- **Treatment Pre-approval**: Get advance approval for expensive treatments
- **Estimated Cost Management**: Submit estimated treatment costs
- **Approval Workflow**: Track authorization requests and approvals
- **Validity Management**: Monitor authorization validity periods
- **Usage Tracking**: Track utilization of pre-authorized amounts

## 📊 Dashboard & Analytics

### 📈 Key Metrics
- **Insurance Companies**: Total registered companies
- **Active Policies**: Currently valid patient policies
- **Pending Claims**: Claims awaiting processing
- **Approved Claims**: Successfully processed claims

### 📋 Management Tabs
1. **Insurance Companies**: Manage insurance providers
2. **Patient Policies**: Track patient insurance coverage
3. **Insurance Claims**: Process and monitor claims

## 🗄️ Database Schema

### Tables Created:

#### 1. `insurance_companies`
- Company information and settings
- Contact details and licensing
- Coverage types and limits
- Network and settlement configurations

#### 2. `patient_insurance_policies`
- Patient-insurance company relationships
- Policy details and coverage information
- Premium and deductible tracking
- Validity and document management

#### 3. `insurance_claims`
- Claim submission and processing
- Treatment and diagnosis information
- Financial tracking (claimed vs approved amounts)
- Status workflow and settlement tracking

#### 4. `insurance_pre_authorizations`
- Pre-treatment approval requests
- Estimated vs authorized amounts
- Validity period management
- Approval workflow tracking

## 🔧 Installation & Setup

### 1. Database Setup
```sql
-- Run the insurance_tables.sql file to create required tables
SOURCE insurance_tables.sql;
```

### 2. Sample Data
The system includes sample data for:
- 5 Major Indian insurance companies (Star Health, HDFC ERGO, ICICI Lombard, New India, United India)
- 5 Sample patient policies
- 5 Sample insurance claims
- 3 Sample pre-authorizations

### 3. File Integration
- Add `insurance.php` to your hospital CRM root directory
- Update navigation menus in existing files to include insurance link
- Ensure proper user role permissions are configured

## 👥 User Roles & Permissions

### Access Levels:
- **Admin**: Full access to all insurance features
- **Doctor**: View policies and claims, create pre-authorizations
- **Nurse**: View patient policies and claim status
- **Receptionist**: Manage policies, submit claims, process payments

## 🎯 Key Workflows

### 1. New Patient Policy Registration
1. Patient visits hospital
2. Receptionist/Admin adds patient's insurance policy details
3. System validates policy information
4. Policy becomes active and available for claims

### 2. Cashless Treatment Process
1. Patient arrives for treatment
2. Staff checks active insurance policies
3. Pre-authorization requested if required
4. Treatment provided upon approval
5. Direct claim submitted to insurance company
6. Settlement processed directly with insurer

### 3. Reimbursement Claim Process
1. Patient pays hospital bill
2. Staff creates reimbursement claim
3. Required documents collected and attached
4. Claim submitted to insurance company
5. Patient receives reimbursement from insurer

### 4. Emergency Treatment
1. Patient brought in emergency
2. Treatment provided immediately
3. Insurance verification done post-treatment
4. Emergency claim submitted with treatment details
5. Expedited processing for emergency cases

## 📱 User Interface Features

### 🎨 Modern Design
- **Responsive Layout**: Works on desktop, tablet, and mobile
- **Interactive Tabs**: Easy navigation between different sections
- **Status Badges**: Color-coded status indicators
- **Card-based Layout**: Clean and organized information display

### 🔍 Search & Filter
- Filter claims by status, date, patient, or insurance company
- Search policies by policy number or patient name
- Quick access to recent and pending items

### 📊 Visual Indicators
- **Color-coded Status**: Green (Approved), Yellow (Pending), Red (Rejected)
- **Progress Tracking**: Visual workflow indicators
- **Expiry Alerts**: Highlight policies nearing expiration

## 🔒 Security Features

- **Role-based Access Control**: Different access levels for different user types
- **Data Validation**: Server-side validation for all inputs
- **Audit Trail**: Track all changes and updates
- **Secure File Storage**: Protected document storage

## 🚀 Future Enhancements

### Planned Features:
- **API Integration**: Direct integration with insurance company APIs
- **Automated Claims**: Auto-submission of eligible claims
- **Mobile App**: Dedicated mobile application for staff
- **Patient Portal**: Allow patients to view their policy status
- **Advanced Analytics**: Detailed reporting and analytics
- **Notification System**: Automated alerts for policy expiry, claim updates
- **Bulk Processing**: Handle multiple claims simultaneously

## 📞 Support & Integration

### Integration Points:
- **Patient Management**: Linked with patient records
- **Billing System**: Integrated with hospital billing
- **Appointment System**: Connected to appointment scheduling
- **Doctor Module**: Accessible to doctors for treatment planning

### Data Flow:
```
Patient Registration → Policy Addition → Treatment → Billing → Claim Submission → Processing → Settlement
```

## 🏆 Benefits

### For Hospital:
- **Reduced Payment Delays**: Faster claim settlements
- **Improved Cash Flow**: Direct insurance payments
- **Better Patient Care**: Focus on treatment, not payment concerns
- **Compliance**: Proper documentation and audit trails

### For Patients:
- **Cashless Treatment**: No upfront payment required
- **Transparent Process**: Clear visibility of claim status
- **Multiple Policy Support**: Use best available coverage
- **Quick Processing**: Efficient claim handling

### For Staff:
- **Streamlined Workflow**: Automated processes reduce manual work
- **Better Organization**: All insurance data in one place
- **Quick Access**: Fast retrieval of policy and claim information
- **Reduced Errors**: Automated validations and checks

---

## 📝 Notes

- Ensure all insurance company details are verified before adding
- Regular backup of insurance data is recommended
- Keep policy documents updated and accessible
- Monitor claim settlement timelines for better cash flow management
- Train staff on proper claim submission procedures

**The Insurance Management System transforms how hospitals handle insurance-related processes, making them more efficient, transparent, and patient-friendly!** 🎉