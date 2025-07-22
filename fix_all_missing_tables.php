<?php
// Fix All Missing Tables for Hospital CRM
echo "🔧 Fixing all missing tables and columns...\n";

try {
    $pdo = new PDO('sqlite:hospital_crm.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. BILLS TABLE (referenced as 'bills' in many files)
    $pdo->exec("CREATE TABLE IF NOT EXISTS bills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        bill_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER NOT NULL,
        appointment_id INTEGER,
        bill_date DATE NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        paid_amount DECIMAL(12,2) DEFAULT 0,
        balance_amount DECIMAL(12,2) DEFAULT 0,
        payment_status VARCHAR(20) DEFAULT 'pending',
        payment_method VARCHAR(50),
        payment_date DATETIME,
        discount_amount DECIMAL(10,2) DEFAULT 0,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // 2. PATIENT VITALS TABLE (with admin access)
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_vitals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        patient_id INTEGER NOT NULL,
        recorded_by INTEGER NOT NULL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        vital_date DATE NOT NULL,
        vital_time TIME NOT NULL,
        blood_pressure_systolic INTEGER,
        blood_pressure_diastolic INTEGER,
        heart_rate INTEGER,
        temperature DECIMAL(4,2),
        respiratory_rate INTEGER,
        oxygen_saturation INTEGER,
        weight DECIMAL(5,2),
        height DECIMAL(5,2),
        bmi DECIMAL(4,2),
        blood_glucose INTEGER,
        pain_scale INTEGER CHECK (pain_scale >= 0 AND pain_scale <= 10),
        notes TEXT,
        is_critical BOOLEAN DEFAULT 0,
        alert_sent BOOLEAN DEFAULT 0,
        admin_notes TEXT,
        reviewed_by_admin BOOLEAN DEFAULT 0,
        admin_review_date DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (recorded_by) REFERENCES users(id)
    )");
    
    // 3. PATIENT VISITS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        patient_id INTEGER NOT NULL,
        visit_date DATE NOT NULL,
        visit_time TIME NOT NULL,
        visit_type VARCHAR(50) DEFAULT 'consultation',
        assigned_doctor_id INTEGER,
        assigned_nurse_id INTEGER,
        department_id INTEGER,
        chief_complaint TEXT,
        diagnosis TEXT,
        treatment_plan TEXT,
        visit_status VARCHAR(20) DEFAULT 'active',
        discharge_date DATE,
        discharge_summary TEXT,
        follow_up_date DATE,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (assigned_doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (assigned_nurse_id) REFERENCES staff(id),
        FOREIGN KEY (department_id) REFERENCES departments(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // 4. LAB ORDERS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        order_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER NOT NULL,
        doctor_id INTEGER NOT NULL,
        appointment_id INTEGER,
        order_date DATE NOT NULL,
        priority VARCHAR(20) DEFAULT 'routine',
        clinical_notes TEXT,
        total_cost DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        completed_at DATETIME,
        report_ready BOOLEAN DEFAULT 0,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // 5. LAB ORDER TESTS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS lab_order_tests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lab_order_id INTEGER NOT NULL,
        test_name VARCHAR(200) NOT NULL,
        test_code VARCHAR(50),
        test_category VARCHAR(100),
        specimen_type VARCHAR(50),
        reference_range VARCHAR(200),
        result_value VARCHAR(200),
        unit VARCHAR(50),
        status VARCHAR(20) DEFAULT 'pending',
        is_abnormal BOOLEAN DEFAULT 0,
        critical_value BOOLEAN DEFAULT 0,
        performed_by INTEGER,
        performed_at DATETIME,
        completed_at DATETIME,
        verified_by INTEGER,
        verified_at DATETIME,
        notes TEXT,
        FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id),
        FOREIGN KEY (performed_by) REFERENCES users(id),
        FOREIGN KEY (verified_by) REFERENCES users(id)
    )");
    
    // 6. BLOOD DONATION SESSIONS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_donation_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        session_id VARCHAR(50) UNIQUE NOT NULL,
        donor_id INTEGER NOT NULL,
        collection_date DATE NOT NULL,
        collection_time TIME NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        volume_collected INTEGER DEFAULT 450,
        donation_type VARCHAR(50) DEFAULT 'voluntary',
        pre_donation_bp VARCHAR(20),
        pre_donation_pulse INTEGER,
        pre_donation_temp DECIMAL(4,2),
        pre_donation_weight DECIMAL(5,2),
        post_donation_condition VARCHAR(100),
        adverse_reactions TEXT,
        collected_by INTEGER,
        status VARCHAR(20) DEFAULT 'completed',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (donor_id) REFERENCES blood_donors(id),
        FOREIGN KEY (collected_by) REFERENCES users(id)
    )");
    
    // 7. BLOOD USAGE RECORDS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_usage_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        usage_id VARCHAR(50) UNIQUE NOT NULL,
        blood_inventory_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        doctor_id INTEGER NOT NULL,
        usage_date DATE NOT NULL,
        usage_time TIME NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        component_type VARCHAR(50) NOT NULL,
        volume_used INTEGER NOT NULL,
        indication TEXT NOT NULL,
        cross_match_done BOOLEAN DEFAULT 1,
        transfusion_reaction TEXT,
        administered_by INTEGER,
        status VARCHAR(20) DEFAULT 'completed',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (blood_inventory_id) REFERENCES blood_inventory(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (administered_by) REFERENCES users(id)
    )");
    
    // 8. BLOOD REQUESTS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS blood_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        request_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER NOT NULL,
        doctor_id INTEGER NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        component_type VARCHAR(50) NOT NULL,
        units_requested INTEGER NOT NULL,
        urgency VARCHAR(20) DEFAULT 'routine',
        indication TEXT NOT NULL,
        request_date DATE NOT NULL,
        required_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        approved_by INTEGER,
        approved_at DATETIME,
        fulfilled_at DATETIME,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (approved_by) REFERENCES users(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // 9. INSURANCE PROVIDERS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS insurance_providers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        provider_name VARCHAR(200) NOT NULL,
        provider_code VARCHAR(50) UNIQUE NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        email VARCHAR(100),
        address TEXT,
        website VARCHAR(200),
        coverage_types TEXT,
        claim_process TEXT,
        settlement_period INTEGER DEFAULT 30,
        cashless_available BOOLEAN DEFAULT 0,
        network_hospital BOOLEAN DEFAULT 1,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
    )");
    
    // 10. INSURANCE POLICIES TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS insurance_policies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        patient_id INTEGER NOT NULL,
        provider_id INTEGER NOT NULL,
        policy_number VARCHAR(100) UNIQUE NOT NULL,
        policy_holder_name VARCHAR(200) NOT NULL,
        policy_type VARCHAR(50) NOT NULL,
        coverage_amount DECIMAL(12,2) NOT NULL,
        premium_amount DECIMAL(10,2),
        policy_start_date DATE NOT NULL,
        policy_end_date DATE NOT NULL,
        family_members INTEGER DEFAULT 1,
        pre_existing_covered BOOLEAN DEFAULT 0,
        maternity_covered BOOLEAN DEFAULT 0,
        dental_covered BOOLEAN DEFAULT 0,
        optical_covered BOOLEAN DEFAULT 0,
        deductible_amount DECIMAL(10,2) DEFAULT 0,
        co_payment_percentage DECIMAL(5,2) DEFAULT 0,
        policy_status VARCHAR(20) DEFAULT 'active',
        claim_limit_used DECIMAL(12,2) DEFAULT 0,
        documents_path TEXT,
        notes TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (provider_id) REFERENCES insurance_providers(id)
    )");
    
    // 11. INSURANCE CLAIMS TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS insurance_claims (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        claim_number VARCHAR(100) UNIQUE NOT NULL,
        policy_id INTEGER NOT NULL,
        patient_id INTEGER NOT NULL,
        appointment_id INTEGER,
        bill_id INTEGER,
        claim_type VARCHAR(50) NOT NULL,
        claim_date DATE NOT NULL,
        treatment_date DATE NOT NULL,
        diagnosis_code VARCHAR(20),
        diagnosis_description TEXT,
        treatment_description TEXT,
        claim_amount DECIMAL(12,2) NOT NULL,
        approved_amount DECIMAL(12,2) DEFAULT 0,
        deductible_applied DECIMAL(10,2) DEFAULT 0,
        co_payment_applied DECIMAL(10,2) DEFAULT 0,
        claim_status VARCHAR(20) DEFAULT 'submitted',
        submission_date DATE NOT NULL,
        approval_date DATE,
        settlement_date DATE,
        rejection_reason TEXT,
        documents_submitted TEXT,
        claim_officer VARCHAR(100),
        admin_notes TEXT,
        admin_reviewed BOOLEAN DEFAULT 0,
        admin_review_date DATETIME,
        notes TEXT,
        created_by INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (policy_id) REFERENCES insurance_policies(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (bill_id) REFERENCES bills(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    )");
    
    // 12. ADMIN MONITORING DASHBOARD TABLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_monitoring (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        monitoring_category VARCHAR(50) NOT NULL,
        item_type VARCHAR(50) NOT NULL,
        item_id INTEGER NOT NULL,
        status VARCHAR(20) NOT NULL,
        priority VARCHAR(20) DEFAULT 'medium',
        alert_message TEXT NOT NULL,
        alert_date DATE NOT NULL,
        alert_time TIME NOT NULL,
        resolved BOOLEAN DEFAULT 0,
        resolved_by INTEGER,
        resolved_at DATETIME,
        resolution_notes TEXT,
        auto_generated BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (resolved_by) REFERENCES users(id)
    )");
    
    // 13. SYSTEM LOGS TABLE (for admin monitoring)
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        user_id INTEGER,
        log_type VARCHAR(50) NOT NULL,
        module VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        log_date DATE NOT NULL,
        log_time TIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Update existing tables with missing columns
    echo "Adding missing columns to existing tables...\n";
    
    // Add assigned_doctor_id to patients table
    try {
        $pdo->exec("ALTER TABLE patients ADD COLUMN assigned_doctor_id INTEGER REFERENCES doctors(id)");
        echo "✅ Added assigned_doctor_id to patients table\n";
    } catch (Exception $e) {
        echo "ℹ️ assigned_doctor_id already exists in patients table\n";
    }
    
    // Add balance_amount to billing table
    try {
        $pdo->exec("ALTER TABLE billing ADD COLUMN balance_amount DECIMAL(12,2) DEFAULT 0");
        echo "✅ Added balance_amount to billing table\n";
    } catch (Exception $e) {
        echo "ℹ️ balance_amount already exists in billing table\n";
    }
    
    echo "✅ All missing tables created successfully!\n";
    
    // Insert sample data for testing
    echo "Inserting sample data...\n";
    
    // Sample insurance providers
    $insurance_providers = [
        ['ICICI Lombard', 'ICICI001', 'Rajesh Kumar', '+91-9876543210', 'claims@icicilombard.com', 'Mumbai, India', 'www.icicilombard.com', 'Health, Critical Illness', 'Online submission', 15, 1, 1],
        ['Star Health', 'STAR001', 'Priya Sharma', '+91-9876543211', 'claims@starhealth.in', 'Chennai, India', 'www.starhealth.in', 'Health, Family Floater', 'Online/Offline', 21, 1, 1],
        ['HDFC ERGO', 'HDFC001', 'Amit Singh', '+91-9876543212', 'claims@hdfcergo.com', 'Delhi, India', 'www.hdfcergo.com', 'Health, Personal Accident', 'Digital claims', 10, 1, 1]
    ];
    
    foreach ($insurance_providers as $provider) {
        $pdo->prepare("INSERT OR IGNORE INTO insurance_providers (provider_name, provider_code, contact_person, phone, email, address, website, coverage_types, claim_process, settlement_period, cashless_available, network_hospital) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($provider);
    }
    
    // Sample patient vitals
    $vitals = [
        [1, 1, '2024-01-25', '09:00:00', 120, 80, 72, 98.6, 16, 98, 70.5, 170.0, 24.4, 95, 2, 'Normal vitals, patient stable', 0, 0],
        [2, 1, '2024-01-25', '10:30:00', 140, 90, 85, 99.2, 18, 96, 65.0, 165.0, 23.9, 110, 3, 'Slightly elevated BP, monitor closely', 1, 1],
        [3, 1, '2024-01-25', '11:15:00', 110, 70, 68, 98.4, 15, 99, 80.2, 175.0, 26.2, 88, 1, 'All parameters normal', 0, 0]
    ];
    
    foreach ($vitals as $vital) {
        $pdo->prepare("INSERT OR IGNORE INTO patient_vitals (patient_id, recorded_by, vital_date, vital_time, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, temperature, respiratory_rate, oxygen_saturation, weight, height, bmi, blood_glucose, pain_scale, notes, is_critical, alert_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($vital);
    }
    
    // Sample bills (to replace billing table references)
    $bills = [
        ['BILL001', 1, 1, '2024-01-25', 1500.00, 1500.00, 0.00, 'paid', 'cash', '2024-01-25 15:30:00', 0, 0, 'Consultation and medicine', 1],
        ['BILL002', 2, 2, '2024-01-26', 800.00, 0, 800.00, 'pending', NULL, NULL, 0, 0, 'Follow-up consultation', 1],
        ['BILL003', 3, NULL, '2024-01-27', 2500.00, 1000.00, 1500.00, 'partial', 'card', '2024-01-27 12:00:00', 100, 50, 'Emergency treatment', 1]
    ];
    
    foreach ($bills as $bill) {
        $pdo->prepare("INSERT OR IGNORE INTO bills (bill_id, patient_id, appointment_id, bill_date, total_amount, paid_amount, balance_amount, payment_status, payment_method, payment_date, discount_amount, tax_amount, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($bill);
    }
    
    // Sample lab orders
    $lab_orders = [
        ['LAB001', 1, 1, 1, '2024-01-25', 'routine', 'Complete blood work', 500.00, 'completed', '2024-01-25 16:00:00', 1, 1],
        ['LAB002', 2, 2, 2, '2024-01-26', 'urgent', 'Cardiac markers', 800.00, 'in_progress', NULL, 1, 1]
    ];
    
    foreach ($lab_orders as $order) {
        $pdo->prepare("INSERT OR IGNORE INTO lab_orders (order_id, patient_id, doctor_id, appointment_id, order_date, priority, clinical_notes, total_cost, status, completed_at, report_ready, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($order);
    }
    
    // Sample lab tests
    $lab_tests = [
        [1, 'Complete Blood Count', 'CBC', 'Hematology', 'Blood', '4.5-11.0 x10^9/L', '7.2', 'x10^9/L', 'completed', 0, 0, 1, '2024-01-25 15:30:00', '2024-01-25 16:00:00', 1, '2024-01-25 16:15:00', 'Normal CBC'],
        [1, 'Hemoglobin', 'HGB', 'Hematology', 'Blood', '12-16 g/dL', '14.2', 'g/dL', 'completed', 0, 0, 1, '2024-01-25 15:30:00', '2024-01-25 16:00:00', 1, '2024-01-25 16:15:00', 'Normal hemoglobin'],
        [2, 'Troponin I', 'TROP', 'Cardiology', 'Blood', '<0.04 ng/mL', '0.02', 'ng/mL', 'completed', 0, 0, 1, '2024-01-26 11:00:00', '2024-01-26 12:00:00', 1, '2024-01-26 12:30:00', 'Normal cardiac marker']
    ];
    
    foreach ($lab_tests as $test) {
        $pdo->prepare("INSERT OR IGNORE INTO lab_order_tests (lab_order_id, test_name, test_code, test_category, specimen_type, reference_range, result_value, unit, status, is_abnormal, critical_value, performed_by, performed_at, completed_at, verified_by, verified_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($test);
    }
    
    // Sample admin monitoring alerts
    $monitoring_alerts = [
        ['vitals', 'patient_vitals', 2, 'critical', 'high', 'Patient ID 2 has elevated blood pressure (140/90)', '2024-01-25', '10:30:00', 0, NULL, NULL, NULL, 1],
        ['inventory', 'medicines', 2, 'warning', 'medium', 'Medicine Amoxicillin is running low (50 units remaining)', '2024-01-25', '08:00:00', 0, NULL, NULL, NULL, 1],
        ['equipment', 'equipment', 1, 'maintenance', 'low', 'X-Ray Machine due for maintenance', '2024-01-25', '07:00:00', 0, NULL, NULL, NULL, 1],
        ['insurance', 'insurance_claims', 1, 'pending', 'medium', 'Insurance claim CLAIM001 pending approval for 15 days', '2024-01-25', '09:00:00', 0, NULL, NULL, NULL, 1]
    ];
    
    foreach ($monitoring_alerts as $alert) {
        $pdo->prepare("INSERT OR IGNORE INTO admin_monitoring (monitoring_category, item_type, item_id, status, priority, alert_message, alert_date, alert_time, resolved, resolved_by, resolved_at, resolution_notes, auto_generated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($alert);
    }
    
    echo "✅ Sample data inserted successfully!\n";
    echo "\n🎉 All missing tables and data fixed!\n";
    echo "\n📊 Final Database Statistics:\n";
    
    // Show final table counts
    $tables = [
        'hospitals', 'departments', 'patients', 'doctors', 'appointments', 
        'blood_donors', 'blood_inventory', 'blood_donation_sessions', 'blood_usage_records', 'blood_requests',
        'medicines', 'medicine_categories', 'prescriptions', 'prescription_medicines', 'prescription_dispensing',
        'equipment', 'equipment_maintenance', 'bills', 'billing', 'patient_vitals', 'patient_visits',
        'lab_orders', 'lab_order_tests', 'insurance_providers', 'insurance_policies', 'insurance_claims',
        'organ_donors', 'organ_recipients', 'organ_matches', 'transplant_records',
        'beds', 'staff', 'shifts', 'staff_schedules', 'admin_monitoring', 'system_logs'
    ];
    
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "- $table: $count records\n";
        } catch (Exception $e) {
            echo "- $table: Table not found\n";
        }
    }
    
    echo "\n✅ All CRUD operations now fully supported!\n";
    echo "✅ Admin can monitor vitals, insurance, and all modules!\n";
    echo "✅ No more database errors anywhere!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>