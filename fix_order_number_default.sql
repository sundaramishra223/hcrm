-- Fix order_number field to handle missing values
-- This ensures order_number always has a value

-- Option 1: Add default value to existing order_number field (if no unique constraint issues)
-- ALTER TABLE lab_orders MODIFY COLUMN order_number VARCHAR(20) DEFAULT CONCAT('LAB', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(FLOOR(RAND() * 9999), 4, '0'));

-- Option 2: Update any existing NULL order_numbers
UPDATE lab_orders 
SET order_number = CONCAT('LAB', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(id, 4, '0'))
WHERE order_number IS NULL OR order_number = '';

-- Check if there are any NULL order_numbers
SELECT 
    COUNT(*) as total_orders,
    COUNT(order_number) as orders_with_number,
    COUNT(*) - COUNT(order_number) as orders_without_number
FROM lab_orders;

-- Show sample orders
SELECT id, order_number, order_date, status 
FROM lab_orders 
ORDER BY id DESC 
LIMIT 10;