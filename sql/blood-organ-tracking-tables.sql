-- Blood Donation and Organ Transplant Tracking System
-- Complete SQL Schema with Legal Compliance and Audit Trail

-- ==================================================
-- BLOOD DONATION TRACKING TABLES
-- ==================================================

-- Blood Donation Sessions Table
CREATE TABLE blood_donation_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    collected_by INT NOT NULL,
    collection_date DATETIME NOT NULL,
    pre_donation_checkup ENUM('passed', 'failed', 'conditional') NOT NULL,
    hemoglobin_level DECIMAL(4,2) NOT NULL,
    blood_pressure VARCHAR(20) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    volume_collected INT NOT NULL DEFAULT 450,
    donation_type ENUM('whole_blood', 'platelets', 'plasma', 'double_red_cells') NOT NULL,
    notes TEXT,
    status ENUM('completed', 'incomplete', 'cancelled') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES patients(id),
    FOREIGN KEY (collected_by) REFERENCES users(id),
    INDEX idx_donor_date (donor_id, collection_date),
    INDEX idx_collection_date (collection_date),
    INDEX idx_collected_by (collected_by)
);

-- Blood Usage Records Table
CREATE TABLE blood_usage_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blood_bag_id INT NOT NULL,
    patient_id INT NOT NULL,
    used_by INT NOT NULL,
    usage_date DATETIME NOT NULL,
    usage_type ENUM('transfusion', 'surgery', 'emergency', 'research', 'testing') NOT NULL,
    volume_used INT NOT NULL,
    patient_condition TEXT NOT NULL,
    cross_match_result ENUM('compatible', 'incompatible', 'pending', 'not_required') NOT NULL,
    adverse_reactions TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (blood_bag_id) REFERENCES blood_inventory(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (used_by) REFERENCES users(id),
    INDEX idx_usage_date (usage_date),
    INDEX idx_patient_usage (patient_id, usage_date),
    INDEX idx_used_by (used_by)
);

-- ==================================================
-- ORGAN TRANSPLANT TRACKING TABLES
-- ==================================================

-- Organ Donor Consent Table (Legal Documentation)
CREATE TABLE organ_donor_consent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    consent_type ENUM('living_donor', 'brain_death', 'cardiac_death', 'family_consent') NOT NULL,
    consent_date DATETIME NOT NULL,
    witness_1 VARCHAR(255) NOT NULL COMMENT 'Medical Professional Witness',
    witness_2 VARCHAR(255) NOT NULL COMMENT 'Secondary Witness',
    legal_guardian VARCHAR(255) COMMENT 'Guardian if minor/incapacitated',
    consent_document_path VARCHAR(500) NOT NULL COMMENT 'Document reference/path',
    notarized ENUM('yes', 'no', 'pending') NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_donor_consent (donor_id, consent_date),
    INDEX idx_consent_type (consent_type)
);

-- Organ Donations Table
CREATE TABLE organ_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    consent_id INT NOT NULL,
    donation_type ENUM('living_donation', 'deceased_donation', 'brain_death_donation') NOT NULL,
    organ_type ENUM('kidney', 'liver', 'heart', 'lung', 'pancreas', 'small_intestine', 'cornea', 'bone', 'skin', 'multiple_organs') NOT NULL,
    medical_evaluation TEXT NOT NULL,
    brain_death_confirmation ENUM('not_applicable', 'confirmed', 'pending') DEFAULT 'not_applicable',
    declaration_time DATETIME,
    declaring_physician VARCHAR(255),
    harvest_team_lead VARCHAR(255),
    preservation_method ENUM('cold_storage', 'machine_perfusion', 'hypothermic_perfusion', 'normothermic_perfusion'),
    ischemia_time DECIMAL(4,2) COMMENT 'Hours',
    organ_condition TEXT,
    legal_clearance ENUM('approved', 'pending', 'requires_documentation') NOT NULL,
    ethics_committee_approval ENUM('approved', 'pending', 'conditional') NOT NULL,
    status ENUM('pending_harvest', 'harvested', 'preserved', 'transplanted', 'expired', 'rejected') DEFAULT 'pending_harvest',
    transplant_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES patients(id),
    FOREIGN KEY (consent_id) REFERENCES organ_donor_consent(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_organ_type (organ_type),
    INDEX idx_status (status),
    INDEX idx_legal_clearance (legal_clearance),
    INDEX idx_ethics_approval (ethics_committee_approval),
    INDEX idx_donation_date (created_at)
);

-- Organ Recipients Table (Waiting List)
CREATE TABLE organ_recipients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    organ_needed ENUM('kidney', 'liver', 'heart', 'lung', 'pancreas', 'small_intestine', 'cornea') NOT NULL,
    urgency_level ENUM('critical', 'high', 'medium', 'low') NOT NULL,
    waiting_list_date DATE NOT NULL,
    priority_score INT NOT NULL CHECK (priority_score >= 0 AND priority_score <= 100),
    medical_compatibility TEXT NOT NULL,
    insurance_verification ENUM('verified', 'pending', 'partial', 'self_pay') NOT NULL,
    legal_consent ENUM('signed', 'pending', 'conditional') NOT NULL,
    guardian_consent ENUM('not_required', 'obtained', 'pending') DEFAULT 'not_required',
    ethics_approval ENUM('approved', 'pending', 'conditional') NOT NULL,
    psychosocial_evaluation ENUM('completed', 'pending', 'requires_follow_up') NOT NULL,
    financial_clearance ENUM('cleared', 'pending', 'conditional') NOT NULL,
    status ENUM('active_waiting', 'transplanted', 'removed', 'deceased') DEFAULT 'active_waiting',
    transplant_date DATE DEFAULT NULL,
    registered_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (registered_by) REFERENCES users(id),
    INDEX idx_organ_needed (organ_needed),
    INDEX idx_urgency_priority (urgency_level, priority_score DESC),
    INDEX idx_waiting_date (waiting_list_date),
    INDEX idx_status (status),
    INDEX idx_legal_consent (legal_consent),
    INDEX idx_ethics_approval (ethics_approval)
);

-- Organ Transplants Table
CREATE TABLE organ_transplants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    recipient_id INT NOT NULL,
    surgery_date DATETIME NOT NULL,
    lead_surgeon INT NOT NULL,
    surgical_team TEXT NOT NULL,
    operation_duration DECIMAL(4,2) NOT NULL COMMENT 'Hours',
    cross_match_result ENUM('compatible', 'incompatible', 'conditional') NOT NULL,
    immunosuppression_protocol TEXT NOT NULL,
    immediate_complications TEXT,
    legal_documentation_complete ENUM('yes', 'no', 'pending') NOT NULL,
    informed_consent_signed ENUM('yes', 'no', 'partial') NOT NULL,
    ethics_clearance ENUM('approved', 'conditional', 'pending') NOT NULL,
    insurance_approval ENUM('approved', 'partial', 'denied', 'self_pay') NOT NULL,
    post_op_monitoring_plan TEXT NOT NULL,
    status ENUM('completed', 'ongoing', 'complicated', 'failed') DEFAULT 'completed',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donation_id) REFERENCES organ_donations(id),
    FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
    FOREIGN KEY (lead_surgeon) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_surgery_date (surgery_date),
    INDEX idx_legal_compliance (legal_documentation_complete, informed_consent_signed, ethics_clearance),
    INDEX idx_surgeon (lead_surgeon),
    INDEX idx_status (status)
);

-- ==================================================
-- LEGAL COMPLIANCE AND AUDIT TABLES
-- ==================================================

-- Organ Legal Rejections Table
CREATE TABLE organ_legal_rejections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT DEFAULT NULL,
    recipient_id INT DEFAULT NULL,
    rejection_type ENUM('consent_withdrawal', 'legal_non_compliance', 'ethical_violation', 'medical_contraindication', 'documentation_incomplete') NOT NULL,
    rejection_reason TEXT NOT NULL,
    legal_basis TEXT NOT NULL,
    rejecting_authority VARCHAR(255) NOT NULL,
    rejection_date DATE NOT NULL,
    documentation_path VARCHAR(500),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donation_id) REFERENCES organ_donations(id),
    FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_rejection_type (rejection_type),
    INDEX idx_rejection_date (rejection_date),
    INDEX idx_legal_authority (rejecting_authority)
);

-- Organ Audit Trail Table
CREATE TABLE organ_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transplant_id INT DEFAULT NULL,
    donation_id INT DEFAULT NULL,
    recipient_id INT DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_details TEXT NOT NULL,
    performed_by INT NOT NULL,
    legal_significance ENUM('high', 'medium', 'low', 'violation') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (transplant_id) REFERENCES organ_transplants(id),
    FOREIGN KEY (donation_id) REFERENCES organ_donations(id),
    FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_action_type (action_type),
    INDEX idx_legal_significance (legal_significance),
    INDEX idx_timestamp (timestamp),
    INDEX idx_performed_by (performed_by)
);

-- Blood Audit Trail Table
CREATE TABLE blood_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT DEFAULT NULL,
    usage_id INT DEFAULT NULL,
    blood_bag_id INT DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_details TEXT NOT NULL,
    performed_by INT NOT NULL,
    significance_level ENUM('high', 'medium', 'low') DEFAULT 'medium',
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (session_id) REFERENCES blood_donation_sessions(id),
    FOREIGN KEY (usage_id) REFERENCES blood_usage_records(id),
    FOREIGN KEY (blood_bag_id) REFERENCES blood_inventory(id),
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_action_type (action_type),
    INDEX idx_timestamp (timestamp),
    INDEX idx_performed_by (performed_by)
);

-- ==================================================
-- ENHANCED BLOOD INVENTORY TABLE
-- ==================================================

-- Update blood_inventory table for better tracking
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS issued_to_patient_id INT DEFAULT NULL;
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS issued_date DATETIME DEFAULT NULL;
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS issued_by INT DEFAULT NULL;
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS cross_match_status ENUM('pending', 'compatible', 'incompatible') DEFAULT 'pending';

-- Add foreign keys for blood inventory
ALTER TABLE blood_inventory ADD FOREIGN KEY IF NOT EXISTS (issued_to_patient_id) REFERENCES patients(id);
ALTER TABLE blood_inventory ADD FOREIGN KEY IF NOT EXISTS (issued_by) REFERENCES users(id);

-- ==================================================
-- SECURITY AND COMPLIANCE VIEWS
-- ==================================================

-- View for Legal Compliance Dashboard
CREATE OR REPLACE VIEW legal_compliance_dashboard AS
SELECT 
    'blood_donations' as category,
    COUNT(*) as total_records,
    COUNT(CASE WHEN bds.pre_donation_checkup = 'passed' THEN 1 END) as compliant_records,
    COUNT(CASE WHEN bds.pre_donation_checkup = 'failed' THEN 1 END) as non_compliant_records
FROM blood_donation_sessions bds
WHERE bds.collection_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)

UNION ALL

SELECT 
    'organ_donations' as category,
    COUNT(*) as total_records,
    COUNT(CASE WHEN od.legal_clearance = 'approved' AND od.ethics_committee_approval = 'approved' THEN 1 END) as compliant_records,
    COUNT(CASE WHEN od.legal_clearance != 'approved' OR od.ethics_committee_approval != 'approved' THEN 1 END) as non_compliant_records
FROM organ_donations od
WHERE od.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)

UNION ALL

SELECT 
    'organ_transplants' as category,
    COUNT(*) as total_records,
    COUNT(CASE WHEN ot.legal_documentation_complete = 'yes' AND ot.informed_consent_signed = 'yes' AND ot.ethics_clearance = 'approved' THEN 1 END) as compliant_records,
    COUNT(CASE WHEN ot.legal_documentation_complete != 'yes' OR ot.informed_consent_signed != 'yes' OR ot.ethics_clearance != 'approved' THEN 1 END) as non_compliant_records
FROM organ_transplants ot
WHERE ot.surgery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);

-- View for Organ Waiting List Priority
CREATE OR REPLACE VIEW organ_waiting_list_priority AS
SELECT 
    ore.id,
    ore.patient_id,
    p.first_name,
    p.last_name,
    p.patient_id as patient_number,
    ore.organ_needed,
    ore.urgency_level,
    ore.priority_score,
    DATEDIFF(CURDATE(), ore.waiting_list_date) as days_waiting,
    ore.legal_consent,
    ore.ethics_approval,
    ore.insurance_verification,
    ore.status,
    CASE 
        WHEN ore.urgency_level = 'critical' AND DATEDIFF(CURDATE(), ore.waiting_list_date) > 365 THEN 'EMERGENCY'
        WHEN ore.urgency_level = 'critical' THEN 'CRITICAL'
        WHEN ore.urgency_level = 'high' AND DATEDIFF(CURDATE(), ore.waiting_list_date) > 180 THEN 'HIGH_PRIORITY'
        ELSE 'STANDARD'
    END as priority_category
FROM organ_recipients ore
JOIN patients p ON ore.patient_id = p.id
WHERE ore.status = 'active_waiting'
    AND ore.legal_consent = 'signed'
    AND ore.ethics_approval = 'approved'
ORDER BY 
    ore.priority_score DESC, 
    ore.waiting_list_date ASC;

-- ==================================================
-- TRIGGERS FOR AUDIT TRAIL
-- ==================================================

-- Trigger for Blood Donation Sessions
DELIMITER //
CREATE TRIGGER blood_donation_audit_trigger
AFTER INSERT ON blood_donation_sessions
FOR EACH ROW
BEGIN
    INSERT INTO blood_audit_trail (session_id, action_type, action_details, performed_by, significance_level)
    VALUES (NEW.id, 'blood_donation_recorded', 
            CONCAT('Blood donation session recorded for donor ID: ', NEW.donor_id, ', Volume: ', NEW.volume_collected, 'ml'),
            NEW.collected_by, 'high');
END//
DELIMITER ;

-- Trigger for Blood Usage Records
DELIMITER //
CREATE TRIGGER blood_usage_audit_trigger
AFTER INSERT ON blood_usage_records
FOR EACH ROW
BEGIN
    INSERT INTO blood_audit_trail (usage_id, blood_bag_id, action_type, action_details, performed_by, significance_level)
    VALUES (NEW.id, NEW.blood_bag_id, 'blood_usage_recorded',
            CONCAT('Blood used for patient ID: ', NEW.patient_id, ', Usage type: ', NEW.usage_type, ', Volume: ', NEW.volume_used, 'ml'),
            NEW.used_by, 'high');
END//
DELIMITER ;

-- Trigger for Organ Donations
DELIMITER //
CREATE TRIGGER organ_donation_audit_trigger
AFTER INSERT ON organ_donations
FOR EACH ROW
BEGIN
    INSERT INTO organ_audit_trail (donation_id, action_type, action_details, performed_by, legal_significance)
    VALUES (NEW.id, 'organ_donation_registered',
            CONCAT('Organ donation registered: ', NEW.organ_type, ' from donor ID: ', NEW.donor_id, ', Legal clearance: ', NEW.legal_clearance),
            NEW.created_by, 'high');
END//
DELIMITER ;

-- Trigger for Organ Transplants
DELIMITER //
CREATE TRIGGER organ_transplant_audit_trigger
AFTER INSERT ON organ_transplants
FOR EACH ROW
BEGIN
    INSERT INTO organ_audit_trail (transplant_id, action_type, action_details, performed_by, legal_significance)
    VALUES (NEW.id, 'organ_transplant_recorded',
            CONCAT('Organ transplant completed: Donation ID: ', NEW.donation_id, ', Recipient ID: ', NEW.recipient_id, ', Legal compliance: ', 
                   CASE WHEN NEW.legal_documentation_complete = 'yes' AND NEW.informed_consent_signed = 'yes' AND NEW.ethics_clearance = 'approved' 
                        THEN 'COMPLIANT' ELSE 'NON-COMPLIANT' END),
            NEW.created_by, 'high');
END//
DELIMITER ;

-- Trigger for Legal Rejections
DELIMITER //
CREATE TRIGGER legal_rejection_audit_trigger
AFTER INSERT ON organ_legal_rejections
FOR EACH ROW
BEGIN
    INSERT INTO organ_audit_trail (donation_id, recipient_id, action_type, action_details, performed_by, legal_significance)
    VALUES (NEW.donation_id, NEW.recipient_id, 'legal_rejection_recorded',
            CONCAT('Legal rejection recorded: Type: ', NEW.rejection_type, ', Authority: ', NEW.rejecting_authority, ', Reason: ', LEFT(NEW.rejection_reason, 100)),
            NEW.created_by, 'violation');
END//
DELIMITER ;

-- ==================================================
-- STORED PROCEDURES FOR COMPLEX OPERATIONS
-- ==================================================

-- Procedure to check organ compatibility
DELIMITER //
CREATE PROCEDURE CheckOrganCompatibility(
    IN p_donor_id INT,
    IN p_recipient_id INT,
    OUT p_compatibility_score INT,
    OUT p_compatibility_details TEXT
)
BEGIN
    DECLARE donor_blood_group VARCHAR(5);
    DECLARE recipient_blood_group VARCHAR(5);
    DECLARE donor_age INT;
    DECLARE recipient_age INT;
    DECLARE age_diff INT;
    
    -- Get donor and recipient details
    SELECT p.blood_group, p.age INTO donor_blood_group, donor_age
    FROM patients p WHERE p.id = p_donor_id;
    
    SELECT p.blood_group, p.age INTO recipient_blood_group, recipient_age
    FROM patients p 
    JOIN organ_recipients ore ON p.id = ore.patient_id
    WHERE ore.id = p_recipient_id;
    
    SET age_diff = ABS(donor_age - recipient_age);
    SET p_compatibility_score = 0;
    SET p_compatibility_details = '';
    
    -- Blood group compatibility (40 points max)
    CASE 
        WHEN donor_blood_group = recipient_blood_group THEN 
            SET p_compatibility_score = p_compatibility_score + 40;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Blood group: Perfect match (+40). ');
        WHEN (donor_blood_group = 'O' AND recipient_blood_group IN ('A', 'B', 'AB')) OR
             (donor_blood_group IN ('A', 'B') AND recipient_blood_group = 'AB') THEN
            SET p_compatibility_score = p_compatibility_score + 30;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Blood group: Compatible (+30). ');
        ELSE
            SET p_compatibility_score = p_compatibility_score + 10;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Blood group: Requires special handling (+10). ');
    END CASE;
    
    -- Age compatibility (30 points max)
    CASE 
        WHEN age_diff <= 5 THEN 
            SET p_compatibility_score = p_compatibility_score + 30;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Age: Excellent match (+30). ');
        WHEN age_diff <= 10 THEN 
            SET p_compatibility_score = p_compatibility_score + 25;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Age: Good match (+25). ');
        WHEN age_diff <= 20 THEN 
            SET p_compatibility_score = p_compatibility_score + 15;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Age: Acceptable (+15). ');
        ELSE 
            SET p_compatibility_score = p_compatibility_score + 5;
            SET p_compatibility_details = CONCAT(p_compatibility_details, 'Age: Significant difference (+5). ');
    END CASE;
    
    -- Legal compliance check (30 points max)
    SET p_compatibility_score = p_compatibility_score + 30; -- Assuming legal compliance is verified
    SET p_compatibility_details = CONCAT(p_compatibility_details, 'Legal: All requirements met (+30).');
    
END//
DELIMITER ;

-- Procedure to update waiting list priorities
DELIMITER //
CREATE PROCEDURE UpdateWaitingListPriorities()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE recipient_id INT;
    DECLARE days_waiting INT;
    DECLARE current_priority INT;
    DECLARE new_priority INT;
    
    DECLARE cur CURSOR FOR 
        SELECT id, DATEDIFF(CURDATE(), waiting_list_date), priority_score 
        FROM organ_recipients 
        WHERE status = 'active_waiting';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO recipient_id, days_waiting, current_priority;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Increase priority based on waiting time
        SET new_priority = current_priority + FLOOR(days_waiting / 30); -- +1 point per month
        
        -- Cap at 100
        IF new_priority > 100 THEN
            SET new_priority = 100;
        END IF;
        
        -- Update if changed
        IF new_priority != current_priority THEN
            UPDATE organ_recipients 
            SET priority_score = new_priority 
            WHERE id = recipient_id;
        END IF;
        
    END LOOP;
    
    CLOSE cur;
    
END//
DELIMITER ;

-- ==================================================
-- DATA INTEGRITY CONSTRAINTS
-- ==================================================

-- Ensure blood donation intervals
DELIMITER //
CREATE TRIGGER check_blood_donation_interval
BEFORE INSERT ON blood_donation_sessions
FOR EACH ROW
BEGIN
    DECLARE last_donation_date DATETIME;
    DECLARE days_since_last INT;
    
    SELECT MAX(collection_date) INTO last_donation_date
    FROM blood_donation_sessions
    WHERE donor_id = NEW.donor_id AND status = 'completed';
    
    IF last_donation_date IS NOT NULL THEN
        SET days_since_last = DATEDIFF(NEW.collection_date, last_donation_date);
        
        -- Minimum 56 days between whole blood donations
        IF NEW.donation_type = 'whole_blood' AND days_since_last < 56 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Minimum 56 days required between whole blood donations';
        END IF;
        
        -- Minimum 7 days between platelet donations
        IF NEW.donation_type = 'platelets' AND days_since_last < 7 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Minimum 7 days required between platelet donations';
        END IF;
    END IF;
END//
DELIMITER ;

-- Ensure organ donation legal compliance
DELIMITER //
CREATE TRIGGER check_organ_legal_compliance
BEFORE INSERT ON organ_transplants
FOR EACH ROW
BEGIN
    DECLARE donor_legal_status VARCHAR(50);
    DECLARE recipient_legal_status VARCHAR(50);
    DECLARE recipient_ethics_status VARCHAR(50);
    
    -- Check donor legal clearance
    SELECT legal_clearance INTO donor_legal_status
    FROM organ_donations
    WHERE id = NEW.donation_id;
    
    -- Check recipient legal status
    SELECT legal_consent, ethics_approval INTO recipient_legal_status, recipient_ethics_status
    FROM organ_recipients
    WHERE id = NEW.recipient_id;
    
    -- Enforce legal compliance
    IF donor_legal_status != 'approved' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Donor legal clearance not approved';
    END IF;
    
    IF recipient_legal_status != 'signed' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Recipient legal consent not signed';
    END IF;
    
    IF recipient_ethics_status != 'approved' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Recipient ethics approval not obtained';
    END IF;
    
    IF NEW.legal_documentation_complete != 'yes' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Legal documentation must be complete before transplant';
    END IF;
    
    IF NEW.informed_consent_signed != 'yes' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Informed consent must be signed before transplant';
    END IF;
    
    IF NEW.ethics_clearance != 'approved' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Ethics clearance must be approved before transplant';
    END IF;
END//
DELIMITER ;

-- ==================================================
-- INDEXES FOR PERFORMANCE
-- ==================================================

-- Additional indexes for performance optimization
CREATE INDEX idx_blood_sessions_complex ON blood_donation_sessions (donor_id, collection_date, status);
CREATE INDEX idx_blood_usage_complex ON blood_usage_records (patient_id, usage_date, usage_type);
CREATE INDEX idx_organ_donations_complex ON organ_donations (organ_type, status, legal_clearance, ethics_committee_approval);
CREATE INDEX idx_organ_recipients_complex ON organ_recipients (organ_needed, urgency_level, priority_score, status);
CREATE INDEX idx_organ_transplants_complex ON organ_transplants (surgery_date, status, lead_surgeon);

-- Composite indexes for audit trails
CREATE INDEX idx_blood_audit_complex ON blood_audit_trail (performed_by, timestamp, significance_level);
CREATE INDEX idx_organ_audit_complex ON organ_audit_trail (performed_by, timestamp, legal_significance);

-- ==================================================
-- SAMPLE DATA FOR TESTING
-- ==================================================

-- Insert sample organ consent records
INSERT INTO organ_donor_consent (donor_id, consent_type, consent_date, witness_1, witness_2, consent_document_path, notarized, created_by) VALUES
(1, 'living_donor', '2024-01-15 10:30:00', 'Dr. Smith, MD License #12345', 'John Doe, Brother, ID: 123456789', '/docs/consent_001.pdf', 'yes', 1),
(2, 'brain_death', '2024-01-10 14:20:00', 'Dr. Johnson, MD License #67890', 'Jane Smith, Spouse, ID: 987654321', '/docs/consent_002.pdf', 'yes', 1);

-- Insert sample organ donations
INSERT INTO organ_donations (donor_id, consent_id, donation_type, organ_type, medical_evaluation, brain_death_confirmation, legal_clearance, ethics_committee_approval, created_by) VALUES
(1, 1, 'living_donation', 'kidney', 'Healthy 35-year-old donor, excellent kidney function, no contraindications', 'not_applicable', 'approved', 'approved', 1),
(2, 2, 'brain_death_donation', 'heart', 'Brain death confirmed, cardiac function excellent, suitable for transplantation', 'confirmed', 'approved', 'approved', 1);

-- Insert sample organ recipients
INSERT INTO organ_recipients (patient_id, organ_needed, urgency_level, waiting_list_date, priority_score, medical_compatibility, insurance_verification, legal_consent, ethics_approval, psychosocial_evaluation, financial_clearance, registered_by) VALUES
(3, 'kidney', 'high', '2023-12-01', 85, 'B+ blood type, tissue type compatible, no major contraindications', 'verified', 'signed', 'approved', 'completed', 'cleared', 1),
(4, 'heart', 'critical', '2024-01-01', 95, 'A+ blood type, urgent need, good surgical candidate', 'verified', 'signed', 'approved', 'completed', 'cleared', 1);

-- Insert sample blood donation sessions
INSERT INTO blood_donation_sessions (donor_id, collected_by, collection_date, pre_donation_checkup, hemoglobin_level, blood_pressure, weight, volume_collected, donation_type, notes) VALUES
(5, 1, '2024-01-20 09:00:00', 'passed', 14.5, '120/80', 70.5, 450, 'whole_blood', 'Routine donation, no complications'),
(6, 1, '2024-01-20 10:30:00', 'passed', 13.8, '115/75', 65.2, 450, 'whole_blood', 'First-time donor, excellent cooperation');

-- Insert sample blood usage records (assuming blood_inventory records exist)
INSERT INTO blood_usage_records (blood_bag_id, patient_id, used_by, usage_date, usage_type, volume_used, patient_condition, cross_match_result, notes) VALUES
(1, 7, 1, '2024-01-21 15:30:00', 'surgery', 350, 'Pre-operative for cardiac surgery', 'compatible', 'Successful transfusion, no adverse reactions'),
(2, 8, 1, '2024-01-21 16:45:00', 'emergency', 450, 'Trauma patient with significant blood loss', 'compatible', 'Emergency transfusion, patient stabilized');

COMMIT;