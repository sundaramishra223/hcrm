-- =============================================
-- CREATE MISSING BILL_PAYMENTS TABLE
-- =============================================

USE hospital_crm;

-- Create bill_payments table if it doesn't exist
CREATE TABLE IF NOT EXISTS bill_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    payment_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'online', 'cheque', 'insurance') NOT NULL,
    payment_reference VARCHAR(100),
    payment_date DATETIME NOT NULL,
    notes TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Add some sample payment data if bills exist
INSERT IGNORE INTO bill_payments (bill_id, payment_amount, payment_method, payment_reference, payment_date, notes, recorded_by)
SELECT 
    b.id as bill_id,
    b.paid_amount as payment_amount,
    'cash' as payment_method,
    CONCAT('REF', LPAD(b.id, 6, '0')) as payment_reference,
    b.created_at as payment_date,
    'Initial payment record' as notes,
    1 as recorded_by
FROM bills b
WHERE b.paid_amount > 0
AND NOT EXISTS (SELECT 1 FROM bill_payments WHERE bill_id = b.id);

-- Verification
SELECT 'bill_payments table created successfully!' as message;

-- Show table structure
DESCRIBE bill_payments;

-- Show sample data
SELECT COUNT(*) as total_payments FROM bill_payments;