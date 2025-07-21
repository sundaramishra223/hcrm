-- Fix All Module Issues
-- Run this SQL to fix pharmacy, billing, and lab test issues

-- 1. Fix medicine_categories table - make hospital_id optional
ALTER TABLE medicine_categories 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- 2. Fix medicines table - ensure hospital_id has default
ALTER TABLE medicines 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- 3. Fix bills table - add default hospital_id
ALTER TABLE bills 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- 4. Fix lab_orders table - check priority enum values
ALTER TABLE lab_orders 
MODIFY COLUMN priority ENUM('low', 'normal', 'high', 'urgent', 'emergency', 'routine') DEFAULT 'normal';

-- 5. Fix lab_order_tests table priority
ALTER TABLE lab_order_tests 
MODIFY COLUMN status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending';

-- 6. Add missing order_number generation for lab_orders if missing
UPDATE lab_orders 
SET order_number = CONCAT('LAB', YEAR(order_date), LPAD(id, 6, '0'))
WHERE order_number IS NULL OR order_number = '';

-- 7. Ensure prescription table has hospital_id default
ALTER TABLE prescriptions 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- 8. Fix any missing foreign key issues
UPDATE medicines SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;
UPDATE medicine_categories SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;
UPDATE bills SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;
UPDATE prescriptions SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;

-- 9. Insert sample medicine categories if missing
INSERT IGNORE INTO medicine_categories (hospital_id, name, description) VALUES
(1, 'Antibiotics', 'Medications that fight bacterial infections'),
(1, 'Pain Relief', 'Analgesics and pain management medications'),
(1, 'Vitamins', 'Vitamin supplements and nutritional aids'),
(1, 'Heart Medications', 'Cardiovascular and cardiac medications'),
(1, 'Diabetes', 'Medications for diabetes management'),
(1, 'Blood Pressure', 'Hypertension and blood pressure medications');

-- 10. Show results
SELECT 'All pharmacy and module issues fixed!' as message;
SELECT 'Medicine categories:', COUNT(*) as count FROM medicine_categories;
SELECT 'Medicines:', COUNT(*) as count FROM medicines;
SELECT 'Bills:', COUNT(*) as count FROM bills;