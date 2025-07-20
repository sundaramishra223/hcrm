-- Create Medicine Categories Table for Better Management
-- Run this to add proper category management

CREATE TABLE IF NOT EXISTS medicine_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    UNIQUE KEY unique_category_per_hospital (hospital_id, name)
);

-- Insert some default categories
INSERT IGNORE INTO medicine_categories (hospital_id, name, description) VALUES
(1, 'Antibiotics', 'Medications that fight bacterial infections'),
(1, 'Pain Relief', 'Analgesics and pain management medications'),
(1, 'Vitamins', 'Vitamin supplements and nutritional aids'),
(1, 'Heart Medications', 'Cardiovascular and cardiac medications'),
(1, 'Diabetes', 'Medications for diabetes management'),
(1, 'Blood Pressure', 'Hypertension and blood pressure medications'),
(1, 'Respiratory', 'Medications for breathing and lung conditions'),
(1, 'Digestive', 'Medications for stomach and digestive issues'),
(1, 'Skin Care', 'Topical medications and skin treatments'),
(1, 'Mental Health', 'Psychiatric and mental health medications'),
(1, 'Emergency', 'Emergency and critical care medications'),
(1, 'Pediatric', 'Medications specifically for children'),
(1, 'Surgical', 'Pre and post-operative medications'),
(1, 'Injectable', 'Injectable medications and vaccines');

-- Show created categories
SELECT 
    id,
    name,
    description,
    is_active,
    created_at
FROM medicine_categories 
WHERE hospital_id = 1 
ORDER BY name;