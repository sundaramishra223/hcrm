PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_name VARCHAR(50) UNIQUE NOT NULL,
        role_display_name VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
        permissions TEXT DEFAULT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
INSERT INTO roles VALUES(1,'admin','Administrator','Full system access',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(2,'doctor','Doctor','Medical practitioner',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(3,'nurse','Nurse','Nursing staff',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(4,'patient','Patient','Hospital patient',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(5,'receptionist','Receptionist','Front desk staff',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(6,'pharmacy_staff','Pharmacy Staff','Pharmacy personnel',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(7,'lab_technician','Lab Technician','Laboratory personnel',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO roles VALUES(8,'staff','General Staff','General hospital staff',NULL,1,'2025-07-22 13:36:40','2025-07-22 13:36:40');
CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'patient',
        role_id INTEGER,
        first_name VARCHAR(100) DEFAULT NULL,
        last_name VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        date_of_birth DATE DEFAULT NULL,
        gender VARCHAR(10) DEFAULT NULL,
        is_active BOOLEAN DEFAULT 1,
        last_login DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    );
INSERT INTO users VALUES(1,'admin','admin@hospital.com','password','$2y$12$CDID5pSLnxUCK5P7NQh56ecVkUDxJEBOo1.a4WYS76CGfd0ZqTDoO','admin',1,'Admin','User',NULL,NULL,NULL,NULL,1,NULL,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO users VALUES(2,'dr.sharma','dr.sharma@hospital.com','password','$2y$12$CDID5pSLnxUCK5P7NQh56ecVkUDxJEBOo1.a4WYS76CGfd0ZqTDoO','doctor',2,'Dr. Sharma','Kumar',NULL,NULL,NULL,NULL,1,NULL,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO users VALUES(3,'john.doe','john.doe@email.com','password','$2y$12$CDID5pSLnxUCK5P7NQh56ecVkUDxJEBOo1.a4WYS76CGfd0ZqTDoO','doctor',2,'John','Doe',NULL,NULL,NULL,NULL,1,NULL,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO users VALUES(4,'priya.nurse','priya.nurse@hospital.com','password','$2y$12$CDID5pSLnxUCK5P7NQh56ecVkUDxJEBOo1.a4WYS76CGfd0ZqTDoO','nurse',3,'Priya','Sharma',NULL,NULL,NULL,NULL,1,NULL,'2025-07-22 13:36:40','2025-07-22 13:36:40');
INSERT INTO users VALUES(5,'reception','reception@hospital.com','password','$2y$12$CDID5pSLnxUCK5P7NQh56ecVkUDxJEBOo1.a4WYS76CGfd0ZqTDoO','receptionist',5,'Reception','Desk',NULL,NULL,NULL,NULL,1,NULL,'2025-07-22 13:36:40','2025-07-22 13:36:40');
CREATE TABLE hospitals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        zip_code VARCHAR(20),
        phone VARCHAR(20),
        email VARCHAR(100),
        license_number VARCHAR(100) UNIQUE,
        established_date DATE,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
INSERT INTO hospitals VALUES(1,'General Hospital','123 Hospital Street','Healthcare City',NULL,NULL,'+91-9876543210','info@hospital.com',NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
CREATE TABLE departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        head_doctor_id INTEGER,
        phone VARCHAR(20),
        email VARCHAR(100),
        location VARCHAR(100),
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
    );
INSERT INTO departments VALUES(1,1,'General Medicine','General medical consultations and treatments',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
INSERT INTO departments VALUES(2,1,'Cardiology','Heart and cardiovascular treatments',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
INSERT INTO departments VALUES(3,1,'Orthopedics','Bone and joint treatments',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
INSERT INTO departments VALUES(4,1,'Pediatrics','Child healthcare',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
INSERT INTO departments VALUES(5,1,'Emergency','Emergency medical services',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
INSERT INTO departments VALUES(6,1,'Pharmacy','Medicine dispensing and management',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
INSERT INTO departments VALUES(7,1,'Laboratory','Medical testing and diagnostics',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41');
CREATE TABLE patients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        patient_id VARCHAR(50) UNIQUE NOT NULL,
        user_id INTEGER,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        date_of_birth DATE,
        gender VARCHAR(10),
        blood_group VARCHAR(10),
        address TEXT,
        emergency_contact VARCHAR(100),
        emergency_phone VARCHAR(20),
        medical_history TEXT,
        allergies TEXT,
        insurance_details TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, assigned_doctor_id INTEGER REFERENCES doctors(id),
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
INSERT INTO patients VALUES(1,1,'PAT001',NULL,'Rahul','Singh','rahul@email.com','+91-9876543214','1990-05-15','male','B+',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41',NULL);
INSERT INTO patients VALUES(2,1,'PAT002',NULL,'Sunita','Devi','sunita@email.com','+91-9876543215','1985-08-22','female','A+',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41',NULL);
INSERT INTO patients VALUES(3,1,'PAT003',NULL,'Arjun','Gupta','arjun@email.com','+91-9876543216','1992-12-10','male','O+',NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41',NULL);
CREATE TABLE doctors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        user_id INTEGER,
        doctor_id VARCHAR(50) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        specialization VARCHAR(100),
        department_id INTEGER,
        license_number VARCHAR(100),
        qualification TEXT,
        experience_years INTEGER,
        consultation_fee DECIMAL(10,2),
        schedule TEXT,
        is_available BOOLEAN DEFAULT 1,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (department_id) REFERENCES departments(id)
    );
INSERT INTO doctors VALUES(1,1,NULL,'DOC001','Dr. Rajesh','Sharma','dr.sharma@hospital.com','+91-9876543211','Cardiology',1,'MD-001','MD Cardiology',15,500,NULL,1,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO doctors VALUES(2,1,NULL,'DOC002','Dr. Priya','Patel','dr.priya@hospital.com','+91-9876543212','Pediatrics',4,'MD-002','MD Pediatrics',12,400,NULL,1,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO doctors VALUES(3,1,NULL,'DOC003','Dr. Amit','Kumar','dr.amit@hospital.com','+91-9876543213','Orthopedics',3,'MD-003','MS Orthopedics',18,600,NULL,1,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
CREATE TABLE appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        patient_id INTEGER NOT NULL,
        doctor_id INTEGER NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        appointment_type VARCHAR(50) DEFAULT 'consultation',
        status VARCHAR(20) DEFAULT 'scheduled',
        reason TEXT,
        notes TEXT,
        consultation_fee DECIMAL(10,2),
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    );
INSERT INTO appointments VALUES(1,1,1,1,'2024-01-25','10:00:00','consultation','scheduled','Regular checkup','Patient feeling well',500,1,'2025-07-22 13:45:06','2025-07-22 13:45:06');
INSERT INTO appointments VALUES(2,1,2,2,'2024-01-26','11:30:00','consultation','completed','Follow-up visit','Treatment going well',400,1,'2025-07-22 13:45:06','2025-07-22 13:45:06');
INSERT INTO appointments VALUES(3,1,3,3,'2024-01-27','14:00:00','emergency','scheduled','Chest pain','Urgent consultation needed',600,1,'2025-07-22 13:45:06','2025-07-22 13:45:06');
CREATE TABLE blood_donors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        donor_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20) NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        date_of_birth DATE NOT NULL,
        gender VARCHAR(10) NOT NULL,
        address TEXT,
        emergency_contact VARCHAR(100),
        emergency_phone VARCHAR(20),
        last_donation_date DATE,
        registered_date DATE DEFAULT (DATE('now')),
        medical_history TEXT,
        eligibility_status VARCHAR(20) DEFAULT 'eligible',
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    );
INSERT INTO blood_donors VALUES(1,1,'BD001',1,'Rahul','Singh','rahul@email.com','+91-9876543214','B+','1990-05-15','male','Delhi, India','Sunita Singh','+91-9876543215','2024-01-15','2025-07-22',NULL,'eligible',1,'2025-07-22 13:45:07','2025-07-22 13:45:07');
INSERT INTO blood_donors VALUES(2,1,'BD002',2,'Sunita','Devi','sunita@email.com','+91-9876543215','A+','1985-08-22','female','Mumbai, India','Rahul Singh','+91-9876543214','2024-01-10','2025-07-22',NULL,'eligible',1,'2025-07-22 13:45:07','2025-07-22 13:45:07');
CREATE TABLE blood_inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        blood_group VARCHAR(10) NOT NULL,
        component_type VARCHAR(50) DEFAULT 'whole_blood',
        donor_id INTEGER,
        donation_session_id VARCHAR(50),
        collection_date DATE NOT NULL,
        expiry_date DATE NOT NULL,
        volume_ml INTEGER NOT NULL,
        status VARCHAR(20) DEFAULT 'available',
        storage_location VARCHAR(100),
        tested_for TEXT,
        test_results TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, bag_number VARCHAR(50),
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (donor_id) REFERENCES blood_donors(id)
    );
INSERT INTO blood_inventory VALUES(1,1,'B+','whole_blood',1,'BS001','2024-01-15','2024-04-15',450,'available','Fridge-A1','HIV, HBV, HCV','All negative',NULL,'2025-07-22 13:45:07','2025-07-22 13:45:07',NULL);
INSERT INTO blood_inventory VALUES(2,1,'A+','whole_blood',2,'BS002','2024-01-10','2024-04-10',450,'available','Fridge-A2','HIV, HBV, HCV','All negative',NULL,'2025-07-22 13:45:07','2025-07-22 13:45:07',NULL);
INSERT INTO blood_inventory VALUES(3,1,'A+','whole_blood',NULL,NULL,'2024-01-15','2024-02-15',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG001');
INSERT INTO blood_inventory VALUES(4,1,'A+','whole_blood',NULL,NULL,'2024-01-20','2024-02-20',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG002');
INSERT INTO blood_inventory VALUES(5,1,'A+','whole_blood',NULL,NULL,'2024-01-10','2024-02-10',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG003');
INSERT INTO blood_inventory VALUES(6,1,'A+','platelets',NULL,NULL,'2024-01-25','2024-01-30',250,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG004');
INSERT INTO blood_inventory VALUES(7,1,'A+','plasma',NULL,NULL,'2024-01-25','2024-02-25',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG005');
INSERT INTO blood_inventory VALUES(8,1,'A+','whole_blood',NULL,NULL,'2024-01-01','2024-02-01',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG006');
INSERT INTO blood_inventory VALUES(9,1,'A+','whole_blood',NULL,NULL,'2023-12-20','2024-01-20',450,'expired',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG007');
INSERT INTO blood_inventory VALUES(10,1,'A-','whole_blood',NULL,NULL,'2024-01-18','2024-02-18',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG008');
INSERT INTO blood_inventory VALUES(11,1,'A-','whole_blood',NULL,NULL,'2024-01-22','2024-02-22',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG009');
INSERT INTO blood_inventory VALUES(12,1,'A-','plasma',NULL,NULL,'2024-01-12','2024-02-12',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG010');
INSERT INTO blood_inventory VALUES(13,1,'A-','whole_blood',NULL,NULL,'2023-12-25','2024-01-25',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG011');
INSERT INTO blood_inventory VALUES(14,1,'B+','whole_blood',NULL,NULL,'2024-01-16','2024-02-16',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG012');
INSERT INTO blood_inventory VALUES(15,1,'B+','whole_blood',NULL,NULL,'2024-01-21','2024-02-21',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG013');
INSERT INTO blood_inventory VALUES(16,1,'B+','whole_blood',NULL,NULL,'2024-01-14','2024-02-14',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG014');
INSERT INTO blood_inventory VALUES(17,1,'B+','platelets',NULL,NULL,'2024-01-23','2024-01-28',250,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG015');
INSERT INTO blood_inventory VALUES(18,1,'B+','plasma',NULL,NULL,'2024-01-28','2024-02-28',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG016');
INSERT INTO blood_inventory VALUES(19,1,'B+','whole_blood',NULL,NULL,'2023-12-30','2024-01-30',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG017');
INSERT INTO blood_inventory VALUES(20,1,'B-','whole_blood',NULL,NULL,'2024-01-17','2024-02-17',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG018');
INSERT INTO blood_inventory VALUES(21,1,'B-','whole_blood',NULL,NULL,'2023-12-29','2024-01-29',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG019');
INSERT INTO blood_inventory VALUES(22,1,'B-','plasma',NULL,NULL,'2024-01-19','2024-02-19',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG020');
INSERT INTO blood_inventory VALUES(23,1,'AB+','whole_blood',NULL,NULL,'2024-01-13','2024-02-13',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG021');
INSERT INTO blood_inventory VALUES(24,1,'AB+','whole_blood',NULL,NULL,'2024-01-24','2024-02-24',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG022');
INSERT INTO blood_inventory VALUES(25,1,'AB+','platelets',NULL,NULL,'2024-01-22','2024-01-27',250,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG023');
INSERT INTO blood_inventory VALUES(26,1,'AB+','plasma',NULL,NULL,'2024-01-26','2024-02-26',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG024');
INSERT INTO blood_inventory VALUES(27,1,'AB+','whole_blood',NULL,NULL,'2023-12-28','2024-01-28',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG025');
INSERT INTO blood_inventory VALUES(28,1,'AB-','whole_blood',NULL,NULL,'2024-01-11','2024-02-11',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG026');
INSERT INTO blood_inventory VALUES(29,1,'AB-','plasma',NULL,NULL,'2024-01-23','2024-02-23',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG027');
INSERT INTO blood_inventory VALUES(30,1,'AB-','whole_blood',NULL,NULL,'2023-12-15','2024-01-15',450,'expired',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG028');
INSERT INTO blood_inventory VALUES(31,1,'O+','whole_blood',NULL,NULL,'2024-01-27','2024-02-27',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG029');
INSERT INTO blood_inventory VALUES(32,1,'O+','whole_blood',NULL,NULL,'2024-01-16','2024-02-16',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG030');
INSERT INTO blood_inventory VALUES(33,1,'O+','whole_blood',NULL,NULL,'2024-01-18','2024-02-18',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG031');
INSERT INTO blood_inventory VALUES(34,1,'O+','whole_blood',NULL,NULL,'2024-01-20','2024-02-20',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG032');
INSERT INTO blood_inventory VALUES(35,1,'O+','platelets',NULL,NULL,'2024-01-21','2024-01-26',250,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG033');
INSERT INTO blood_inventory VALUES(36,1,'O+','plasma',NULL,NULL,'2024-01-29','2024-02-29',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG034');
INSERT INTO blood_inventory VALUES(37,1,'O+','whole_blood',NULL,NULL,'2023-12-31','2024-01-31',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG035');
INSERT INTO blood_inventory VALUES(38,1,'O+','whole_blood',NULL,NULL,'2023-12-29','2024-01-29',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG036');
INSERT INTO blood_inventory VALUES(39,1,'O-','whole_blood',NULL,NULL,'2024-01-12','2024-02-12',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG037');
INSERT INTO blood_inventory VALUES(40,1,'O-','whole_blood',NULL,NULL,'2023-12-28','2024-01-28',450,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG038');
INSERT INTO blood_inventory VALUES(41,1,'O-','plasma',NULL,NULL,'2024-01-14','2024-02-14',300,'available',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG039');
INSERT INTO blood_inventory VALUES(42,1,'O-','whole_blood',NULL,NULL,'2023-12-26','2024-01-26',450,'used',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG040');
INSERT INTO blood_inventory VALUES(43,1,'O-','whole_blood',NULL,NULL,'2023-12-18','2024-01-18',450,'expired',NULL,NULL,NULL,NULL,'2025-07-22 13:56:17','2025-07-22 13:56:17','BAG041');
CREATE TABLE medicine_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
    );
INSERT INTO medicine_categories VALUES(1,1,'Antibiotics','Antimicrobial medications',1,'2025-07-22 13:44:41');
INSERT INTO medicine_categories VALUES(2,1,'Painkillers','Pain relief medications',1,'2025-07-22 13:44:41');
INSERT INTO medicine_categories VALUES(3,1,'Vitamins','Vitamin supplements',1,'2025-07-22 13:44:41');
INSERT INTO medicine_categories VALUES(4,1,'Cardiac','Heart medications',1,'2025-07-22 13:44:41');
INSERT INTO medicine_categories VALUES(5,1,'Diabetes','Diabetes management',1,'2025-07-22 13:44:41');
INSERT INTO medicine_categories VALUES(6,1,'Emergency','Emergency medications',1,'2025-07-22 13:44:41');
CREATE TABLE medicines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        name VARCHAR(200) NOT NULL,
        generic_name VARCHAR(200),
        manufacturer VARCHAR(100),
        category_id INTEGER,
        category VARCHAR(100),
        dosage_form VARCHAR(50),
        strength VARCHAR(50),
        unit_price DECIMAL(10,2) NOT NULL,
        pack_size INTEGER DEFAULT 1,
        batch_number VARCHAR(100),
        expiry_date DATE,
        stock_quantity INTEGER DEFAULT 0,
        min_stock_level INTEGER DEFAULT 10,
        reorder_level INTEGER DEFAULT 20,
        prescription_required BOOLEAN DEFAULT 1,
        storage_condition VARCHAR(100),
        side_effects TEXT,
        contraindications TEXT,
        storage_conditions VARCHAR(255),
        notes TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (category_id) REFERENCES medicine_categories(id)
    );
INSERT INTO medicines VALUES(1,1,'Paracetamol','Acetaminophen','PharmaCorp',1,'Painkillers','Tablet','500mg',5,10,'BATCH001','2025-12-31',100,10,20,1,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO medicines VALUES(2,1,'Amoxicillin','Amoxicillin','MediLab',1,'Antibiotics','Capsule','250mg',12.5,10,'BATCH002','2025-11-30',50,10,20,1,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO medicines VALUES(3,1,'Vitamin C','Ascorbic Acid','HealthPlus',3,'Vitamins','Tablet','1000mg',8,30,'BATCH003','2026-06-30',200,10,20,1,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO medicines VALUES(4,1,'Aspirin','Acetylsalicylic Acid','CardioMed',2,'Painkillers','Tablet','75mg',3.5,10,'BATCH004','2025-10-31',150,10,20,1,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO medicines VALUES(5,1,'Metformin','Metformin HCl','DiabetesRx',5,'Diabetes','Tablet','500mg',15,10,'BATCH005','2025-09-30',75,10,20,1,NULL,NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
CREATE TABLE medicine_stock_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        medicine_id INTEGER NOT NULL,
        movement_type VARCHAR(20) NOT NULL,
        quantity INTEGER NOT NULL,
        previous_stock INTEGER NOT NULL,
        new_stock INTEGER NOT NULL,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (medicine_id) REFERENCES medicines(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    );
CREATE TABLE prescriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        prescription_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER NOT NULL,
        doctor_id INTEGER NOT NULL,
        appointment_id INTEGER,
        prescription_date DATE NOT NULL,
        diagnosis TEXT,
        instructions TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        dispensed_at DATETIME,
        dispensed_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (dispensed_by) REFERENCES users(id)
    );
CREATE TABLE prescription_medicines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prescription_id INTEGER NOT NULL,
        medicine_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        dosage VARCHAR(100),
        frequency VARCHAR(100),
        duration VARCHAR(100),
        instructions TEXT,
        dispensed_quantity INTEGER DEFAULT 0,
        dispensed_at DATETIME,
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
        FOREIGN KEY (medicine_id) REFERENCES medicines(id)
    );
CREATE TABLE prescription_dispensing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prescription_id INTEGER NOT NULL,
        medicine_id INTEGER NOT NULL,
        dispensed_quantity INTEGER NOT NULL,
        dispensed_by INTEGER NOT NULL,
        dispensed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
        FOREIGN KEY (medicine_id) REFERENCES medicines(id),
        FOREIGN KEY (dispensed_by) REFERENCES users(id)
    );
CREATE TABLE equipment (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(100),
        model VARCHAR(100),
        serial_number VARCHAR(100) UNIQUE,
        manufacturer VARCHAR(100),
        purchase_date DATE,
        warranty_expiry DATE,
        cost DECIMAL(12,2),
        location VARCHAR(100),
        status VARCHAR(20) DEFAULT 'operational',
        maintenance_schedule TEXT,
        last_maintenance DATE,
        specifications TEXT,
        notes TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
    );
INSERT INTO equipment VALUES(1,1,'X-Ray Machine','Diagnostic','XR-2000','XR001','MedTech Inc','2023-01-15','2026-01-15',250000,'Radiology Ward','operational',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO equipment VALUES(2,1,'ECG Machine','Cardiac','ECG-500','ECG001','CardioTech','2023-03-20','2026-03-20',75000,'Cardiology','operational',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO equipment VALUES(3,1,'Ultrasound Scanner','Diagnostic','US-Pro','US001','UltraSound Corp','2023-02-10','2026-02-10',180000,'General Ward','operational',NULL,NULL,NULL,NULL,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
CREATE TABLE equipment_maintenance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        equipment_id INTEGER NOT NULL,
        maintenance_type VARCHAR(50) NOT NULL,
        maintenance_date DATE NOT NULL,
        performed_by VARCHAR(100),
        cost DECIMAL(10,2),
        notes TEXT,
        next_maintenance_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (equipment_id) REFERENCES equipment(id)
    );
CREATE TABLE billing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        bill_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER NOT NULL,
        appointment_id INTEGER,
        bill_date DATE NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        paid_amount DECIMAL(12,2) DEFAULT 0,
        payment_status VARCHAR(20) DEFAULT 'pending',
        payment_method VARCHAR(50),
        payment_date DATETIME,
        discount_amount DECIMAL(10,2) DEFAULT 0,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, balance_amount DECIMAL(12,2) DEFAULT 0,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    );
INSERT INTO billing VALUES(1,1,'BILL001',1,1,'2024-01-25',1500,1500,'paid','cash','2024-01-25 15:30:00',0,0,'Consultation and medicine',1,'2025-07-22 13:45:07','2025-07-22 13:45:07',0);
INSERT INTO billing VALUES(2,1,'BILL002',2,2,'2024-01-26',800,0,'pending',NULL,NULL,0,0,'Follow-up consultation',1,'2025-07-22 13:45:07','2025-07-22 13:45:07',0);
CREATE TABLE bill_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bill_id INTEGER NOT NULL,
        item_type VARCHAR(50) NOT NULL,
        item_id INTEGER,
        item_name VARCHAR(200) NOT NULL,
        quantity INTEGER DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bill_id) REFERENCES billing(id)
    );
CREATE TABLE organ_donors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        donor_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        blood_group VARCHAR(10) NOT NULL,
        date_of_birth DATE NOT NULL,
        gender VARCHAR(10) NOT NULL,
        address TEXT,
        emergency_contact VARCHAR(100),
        organs_to_donate TEXT,
        consent_type VARCHAR(50),
        consent_date DATE,
        consent_witness VARCHAR(100),
        medical_history TEXT,
        is_eligible BOOLEAN DEFAULT 1,
        is_active BOOLEAN DEFAULT 1,
        registered_date DATE DEFAULT (DATE('now')),
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    );
CREATE TABLE organ_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        recipient_id VARCHAR(50) UNIQUE NOT NULL,
        patient_id INTEGER NOT NULL,
        organ_needed VARCHAR(50) NOT NULL,
        blood_group VARCHAR(10) NOT NULL,
        urgency_level VARCHAR(20) DEFAULT 'medium',
        medical_condition TEXT,
        date_added_to_list DATE NOT NULL,
        priority_score INTEGER DEFAULT 0,
        status VARCHAR(20) DEFAULT 'waiting',
        transplant_date DATE,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    );
CREATE TABLE organ_matches (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        donor_id INTEGER NOT NULL,
        recipient_id INTEGER NOT NULL,
        organ_type VARCHAR(50) NOT NULL,
        compatibility_score DECIMAL(5,2),
        status VARCHAR(20) DEFAULT 'potential',
        coordinator_id INTEGER,
        match_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (donor_id) REFERENCES organ_donors(id),
        FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
        FOREIGN KEY (coordinator_id) REFERENCES users(id)
    );
CREATE TABLE transplant_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        transplant_id VARCHAR(50) UNIQUE NOT NULL,
        donor_id INTEGER NOT NULL,
        recipient_id INTEGER NOT NULL,
        organ_type VARCHAR(50) NOT NULL,
        surgery_date DATE NOT NULL,
        surgeon_id INTEGER,
        coordinator_id INTEGER,
        status VARCHAR(20) DEFAULT 'scheduled',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (donor_id) REFERENCES organ_donors(id),
        FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
        FOREIGN KEY (surgeon_id) REFERENCES doctors(id),
        FOREIGN KEY (coordinator_id) REFERENCES users(id)
    );
CREATE TABLE beds (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        bed_number VARCHAR(20) NOT NULL,
        room_number VARCHAR(20),
        ward VARCHAR(50),
        bed_type VARCHAR(50) DEFAULT 'general',
        status VARCHAR(20) DEFAULT 'available',
        patient_id INTEGER,
        admission_date DATE,
        discharge_date DATE,
        daily_rate DECIMAL(10,2) DEFAULT 0,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (patient_id) REFERENCES patients(id)
    );
INSERT INTO beds VALUES(1,1,'B001','R101','General Ward','general','available',NULL,NULL,NULL,1000,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO beds VALUES(2,1,'B002','R102','General Ward','general','available',NULL,NULL,NULL,1000,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO beds VALUES(3,1,'B003','R201','ICU','icu','available',NULL,NULL,NULL,2500,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO beds VALUES(4,1,'B004','R202','ICU','icu','available',NULL,NULL,NULL,2500,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
INSERT INTO beds VALUES(5,1,'B005','R301','Private Ward','private','available',NULL,NULL,NULL,3000,1,'2025-07-22 13:44:41','2025-07-22 13:44:41');
CREATE TABLE staff (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        user_id INTEGER,
        staff_id VARCHAR(50) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        department_id INTEGER,
        position VARCHAR(100),
        salary DECIMAL(12,2),
        hire_date DATE,
        shift_id INTEGER,
        shift_timing VARCHAR(100),
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (department_id) REFERENCES departments(id)
    );
CREATE TABLE shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hospital_id INTEGER DEFAULT 1,
        shift_name VARCHAR(50) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
    );
INSERT INTO shifts VALUES(1,1,'Morning Shift','06:00:00','14:00:00',1,'2025-07-22 13:44:41');
INSERT INTO shifts VALUES(2,1,'Evening Shift','14:00:00','22:00:00',1,'2025-07-22 13:44:41');
INSERT INTO shifts VALUES(3,1,'Night Shift','22:00:00','06:00:00',1,'2025-07-22 13:44:41');
CREATE TABLE staff_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        staff_id INTEGER NOT NULL,
        week_start_date DATE NOT NULL,
        monday_shift_id INTEGER,
        tuesday_shift_id INTEGER,
        wednesday_shift_id INTEGER,
        thursday_shift_id INTEGER,
        friday_shift_id INTEGER,
        saturday_shift_id INTEGER,
        sunday_shift_id INTEGER,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES staff(id),
        FOREIGN KEY (created_by) REFERENCES users(id)
    );
CREATE TABLE patient_vitals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hospital_id INTEGER DEFAULT 1,
    patient_id INTEGER NOT NULL,
    recorded_by INTEGER NOT NULL,
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
    pain_scale INTEGER,
    notes TEXT,
    is_critical BOOLEAN DEFAULT 0,
    alert_sent BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);
INSERT INTO patient_vitals VALUES(1,1,1,1,'2024-01-25','09:00:00',120,80,72,98.5999999999999943,16,98,70.5,170,24.3999999999999985,95,2,'Normal vitals, patient stable',0,0,'2025-07-22 13:49:27');
INSERT INTO patient_vitals VALUES(2,1,2,1,'2024-01-25','10:30:00',140,90,85,99.2000000000000028,18,96,65,165,23.8999999999999985,110,3,'Slightly elevated BP, monitor closely',1,1,'2025-07-22 13:49:27');
INSERT INTO patient_vitals VALUES(3,1,3,1,'2024-01-25','11:15:00',110,70,68,98.4000000000000056,15,99,80.2000000000000028,175,26.1999999999999992,88,1,'All parameters normal',0,0,'2025-07-22 13:49:27');
CREATE TABLE insurance_providers (
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
);
INSERT INTO insurance_providers VALUES(1,1,'ICICI Lombard','ICICI001','Rajesh Kumar','+91-9876543210','claims@icicilombard.com','Mumbai, India','www.icicilombard.com','Health, Critical Illness','Online submission',15,1,1,1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
INSERT INTO insurance_providers VALUES(2,1,'Star Health','STAR001','Priya Sharma','+91-9876543211','claims@starhealth.in','Chennai, India','www.starhealth.in','Health, Family Floater','Online/Offline',21,1,1,1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
INSERT INTO insurance_providers VALUES(3,1,'HDFC ERGO','HDFC001','Amit Singh','+91-9876543212','claims@hdfcergo.com','Delhi, India','www.hdfcergo.com','Health, Personal Accident','Digital claims',10,1,1,1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
CREATE TABLE insurance_policies (
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
);
CREATE TABLE insurance_claims (
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
    notes TEXT,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (policy_id) REFERENCES insurance_policies(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (bill_id) REFERENCES billing(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
CREATE TABLE insurance_pre_auth (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    hospital_id INTEGER DEFAULT 1,
    auth_number VARCHAR(100) UNIQUE NOT NULL,
    policy_id INTEGER NOT NULL,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    treatment_type VARCHAR(100) NOT NULL,
    estimated_cost DECIMAL(12,2) NOT NULL,
    requested_date DATE NOT NULL,
    treatment_date DATE NOT NULL,
    diagnosis TEXT NOT NULL,
    treatment_plan TEXT NOT NULL,
    auth_status VARCHAR(20) DEFAULT 'pending',
    approved_amount DECIMAL(12,2) DEFAULT 0,
    validity_date DATE,
    auth_code VARCHAR(50),
    rejection_reason TEXT,
    documents_path TEXT,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (policy_id) REFERENCES insurance_policies(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
CREATE TABLE bills (
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
    );
INSERT INTO bills VALUES(1,1,'BILL001',1,1,'2024-01-25',1500,1500,0,'paid','cash','2024-01-25 15:30:00',0,0,'Consultation and medicine',1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
INSERT INTO bills VALUES(2,1,'BILL002',2,2,'2024-01-26',800,0,800,'pending',NULL,NULL,0,0,'Follow-up consultation',1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
INSERT INTO bills VALUES(3,1,'BILL003',3,NULL,'2024-01-27',2500,1000,1500,'partial','card','2024-01-27 12:00:00',100,50,'Emergency treatment',1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
CREATE TABLE patient_visits (
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
    );
CREATE TABLE lab_orders (
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
    );
INSERT INTO lab_orders VALUES(1,1,'LAB001',1,1,1,'2024-01-25','routine','Complete blood work',500,'completed','2024-01-25 16:00:00',1,1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
INSERT INTO lab_orders VALUES(2,1,'LAB002',2,2,2,'2024-01-26','urgent','Cardiac markers',800,'in_progress',NULL,1,1,'2025-07-22 13:49:27','2025-07-22 13:49:27');
CREATE TABLE lab_order_tests (
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
    );
INSERT INTO lab_order_tests VALUES(1,1,'Complete Blood Count','CBC','Hematology','Blood','4.5-11.0 x10^9/L','7.2','x10^9/L','completed',0,0,1,'2024-01-25 15:30:00','2024-01-25 16:00:00',1,'2024-01-25 16:15:00','Normal CBC');
INSERT INTO lab_order_tests VALUES(2,1,'Hemoglobin','HGB','Hematology','Blood','12-16 g/dL','14.2','g/dL','completed',0,0,1,'2024-01-25 15:30:00','2024-01-25 16:00:00',1,'2024-01-25 16:15:00','Normal hemoglobin');
INSERT INTO lab_order_tests VALUES(3,2,'Troponin I','TROP','Cardiology','Blood','<0.04 ng/mL','0.02','ng/mL','completed',0,0,1,'2024-01-26 11:00:00','2024-01-26 12:00:00',1,'2024-01-26 12:30:00','Normal cardiac marker');
CREATE TABLE blood_donation_sessions (
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
    );
INSERT INTO blood_donation_sessions VALUES(1,1,'SESSION001',1,'2024-01-15','09:30:00','A+',450,'voluntary','120/80',72,98.5999999999999943,70.5,'Good condition','',1,'completed','Regular donor, no issues','2025-07-22 13:56:17');
INSERT INTO blood_donation_sessions VALUES(2,1,'SESSION002',2,'2024-01-20','10:15:00','O+',450,'voluntary','115/75',68,98.4000000000000056,65,'Excellent condition','',1,'completed','First time donor, very cooperative','2025-07-22 13:56:17');
INSERT INTO blood_donation_sessions VALUES(3,1,'SESSION003',1,'2024-01-25','11:00:00','B+',450,'voluntary','125/82',75,99,72,'Good condition','Slight dizziness post-donation',1,'completed','Experienced donor','2025-07-22 13:56:17');
INSERT INTO blood_donation_sessions VALUES(4,1,'SESSION004',2,'2024-01-27','14:30:00','AB+',450,'voluntary','118/78',70,98.5,68.5,'Very good condition','',1,'completed','Regular donor','2025-07-22 13:56:17');
INSERT INTO blood_donation_sessions VALUES(5,1,'SESSION005',1,'2024-01-29','16:00:00','O-',450,'emergency','130/85',80,98.7999999999999971,75,'Good condition','',1,'completed','Emergency donation for surgery','2025-07-22 13:56:17');
CREATE TABLE blood_usage_records (
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
    );
INSERT INTO blood_usage_records VALUES(1,1,'USAGE001',1,1,1,'2024-01-24','15:30:00','O+','whole_blood',450,'Emergency surgery - appendectomy',1,'',1,'completed','Successful transfusion, no reactions','2025-07-22 13:56:17');
INSERT INTO blood_usage_records VALUES(2,1,'USAGE002',2,2,2,'2024-01-26','11:00:00','A+','platelets',250,'Chemotherapy support',1,'',1,'completed','Regular transfusion completed','2025-07-22 13:56:17');
INSERT INTO blood_usage_records VALUES(3,1,'USAGE003',3,3,1,'2024-01-27','09:45:00','B+','whole_blood',450,'Surgery - cardiac bypass',1,'',1,'completed','Pre-operative transfusion successful','2025-07-22 13:56:17');
CREATE TABLE blood_requests (
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
    );
INSERT INTO blood_requests VALUES(1,1,'REQ001',1,1,'O+','whole_blood',2,'urgent','Surgery - appendectomy','2024-01-25','2024-01-26','pending',NULL,NULL,NULL,'Patient needs blood for emergency surgery',1,'2025-07-22 13:56:17','2025-07-22 13:56:17');
INSERT INTO blood_requests VALUES(2,1,'REQ002',2,2,'A+','platelets',1,'routine','Chemotherapy support','2024-01-26','2024-01-28','approved',1,'2024-01-26 10:30:00',NULL,'Regular transfusion for cancer patient',1,'2025-07-22 13:56:17','2025-07-22 13:56:17');
INSERT INTO blood_requests VALUES(3,1,'REQ003',3,1,'O-','whole_blood',3,'emergency','Trauma - road accident','2024-01-27','2024-01-27','pending',NULL,NULL,NULL,'Critical patient, immediate need',1,'2025-07-22 13:56:17','2025-07-22 13:56:17');
CREATE TABLE admin_monitoring (
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
    );
INSERT INTO admin_monitoring VALUES(1,1,'vitals','patient_vitals',2,'critical','high','Patient ID 2 has elevated blood pressure (140/90)','2024-01-25','10:30:00',0,NULL,NULL,NULL,1,'2025-07-22 13:49:27');
INSERT INTO admin_monitoring VALUES(2,1,'inventory','medicines',2,'warning','medium','Medicine Amoxicillin is running low (50 units remaining)','2024-01-25','08:00:00',0,NULL,NULL,NULL,1,'2025-07-22 13:49:27');
INSERT INTO admin_monitoring VALUES(3,1,'equipment','equipment',1,'maintenance','low','X-Ray Machine due for maintenance','2024-01-25','07:00:00',0,NULL,NULL,NULL,1,'2025-07-22 13:49:27');
INSERT INTO admin_monitoring VALUES(4,1,'insurance','insurance_claims',1,'pending','medium','Insurance claim CLAIM001 pending approval for 15 days','2024-01-25','09:00:00',0,NULL,NULL,NULL,1,'2025-07-22 13:49:27');
INSERT INTO admin_monitoring VALUES(5,1,'blood','blood_inventory',38,'critical','high','O- blood group critically low - only 1 unit available','2024-01-25','08:00:00',0,NULL,NULL,NULL,1,'2025-07-22 13:56:17');
INSERT INTO admin_monitoring VALUES(6,1,'blood','blood_inventory',23,'warning','medium','AB+ platelets expiring within 3 days','2024-01-25','09:15:00',0,NULL,NULL,NULL,1,'2025-07-22 13:56:17');
INSERT INTO admin_monitoring VALUES(7,1,'blood','blood_inventory',33,'warning','medium','O+ platelets expiring within 3 days','2024-01-25','09:30:00',0,NULL,NULL,NULL,1,'2025-07-22 13:56:17');
INSERT INTO admin_monitoring VALUES(8,1,'blood','blood_requests',1,'urgent','high','Urgent blood request REQ001 pending for 2 hours','2024-01-25','12:00:00',0,NULL,NULL,NULL,1,'2025-07-22 13:56:17');
INSERT INTO admin_monitoring VALUES(9,1,'blood','blood_requests',3,'critical','high','Emergency blood request REQ003 - trauma patient needs immediate attention','2024-01-27','10:00:00',0,NULL,NULL,NULL,1,'2025-07-22 13:56:17');
CREATE TABLE system_logs (
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
    );
DELETE FROM sqlite_sequence;
INSERT INTO sqlite_sequence VALUES('roles',8);
INSERT INTO sqlite_sequence VALUES('users',5);
INSERT INTO sqlite_sequence VALUES('hospitals',1);
INSERT INTO sqlite_sequence VALUES('departments',7);
INSERT INTO sqlite_sequence VALUES('medicine_categories',6);
INSERT INTO sqlite_sequence VALUES('medicines',5);
INSERT INTO sqlite_sequence VALUES('doctors',3);
INSERT INTO sqlite_sequence VALUES('patients',3);
INSERT INTO sqlite_sequence VALUES('equipment',3);
INSERT INTO sqlite_sequence VALUES('shifts',3);
INSERT INTO sqlite_sequence VALUES('beds',5);
INSERT INTO sqlite_sequence VALUES('appointments',3);
INSERT INTO sqlite_sequence VALUES('blood_donors',2);
INSERT INTO sqlite_sequence VALUES('blood_inventory',43);
INSERT INTO sqlite_sequence VALUES('billing',2);
INSERT INTO sqlite_sequence VALUES('insurance_providers',3);
INSERT INTO sqlite_sequence VALUES('patient_vitals',3);
INSERT INTO sqlite_sequence VALUES('bills',3);
INSERT INTO sqlite_sequence VALUES('lab_orders',2);
INSERT INTO sqlite_sequence VALUES('lab_order_tests',3);
INSERT INTO sqlite_sequence VALUES('admin_monitoring',9);
INSERT INTO sqlite_sequence VALUES('blood_donation_sessions',5);
INSERT INTO sqlite_sequence VALUES('blood_requests',3);
INSERT INTO sqlite_sequence VALUES('blood_usage_records',3);
COMMIT;
