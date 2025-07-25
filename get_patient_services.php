<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['patient_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$db = new Database();
$patient_id = $_POST['patient_id'];

try {
    $services = [];
    
    // 1. Get unpaid appointments (consultation fees)
    $appointments = $db->query("
        SELECT a.id, a.appointment_date, a.appointment_time, 
               d.doctor_name, d.consultation_fee, a.appointment_type
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ? AND a.status = 'completed' 
        AND a.id NOT IN (SELECT appointment_id FROM billing WHERE appointment_id IS NOT NULL)
        ORDER BY a.appointment_date DESC
    ", [$patient_id])->fetchAll();
    
    foreach ($appointments as $appointment) {
        $services[] = [
            'type' => 'consultation',
            'description' => 'Consultation - Dr. ' . $appointment['doctor_name'] . ' (' . ucfirst($appointment['appointment_type']) . ')',
            'date' => date('d M Y', strtotime($appointment['appointment_date'])),
            'amount' => $appointment['consultation_fee'],
            'reference_id' => $appointment['id']
        ];
    }
    
    // 2. Get unpaid pharmacy sales
    $pharmacy_sales = $db->query("
        SELECT ps.id, ps.sale_date, ps.total_amount, ps.payment_status,
               GROUP_CONCAT(CONCAT(p.medicine_name, ' (', psi.quantity, ')') SEPARATOR ', ') as medicines
        FROM pharmacy_sales ps
        LEFT JOIN pharmacy_sale_items psi ON ps.id = psi.sale_id
        LEFT JOIN pharmacy p ON psi.medicine_id = p.id
        WHERE ps.patient_id = ? AND ps.payment_status != 'paid'
        AND ps.id NOT IN (SELECT pharmacy_sale_id FROM billing WHERE pharmacy_sale_id IS NOT NULL)
        GROUP BY ps.id
        ORDER BY ps.sale_date DESC
    ", [$patient_id])->fetchAll();
    
    foreach ($pharmacy_sales as $sale) {
        $services[] = [
            'type' => 'pharmacy',
            'description' => 'Medicines: ' . $sale['medicines'],
            'date' => date('d M Y', strtotime($sale['sale_date'])),
            'amount' => $sale['total_amount'],
            'reference_id' => $sale['id']
        ];
    }
    
    // 3. Get unpaid lab tests
    $lab_tests = $db->query("
        SELECT lt.id, lt.test_date, l.test_name, l.price, lt.status
        FROM lab_tests lt
        LEFT JOIN laboratory l ON lt.test_id = l.id
        WHERE lt.patient_id = ? AND lt.status IN ('completed', 'in_progress')
        AND lt.id NOT IN (SELECT lab_test_id FROM billing WHERE lab_test_id IS NOT NULL)
        ORDER BY lt.test_date DESC
    ", [$patient_id])->fetchAll();
    
    foreach ($lab_tests as $test) {
        $services[] = [
            'type' => 'lab_test',
            'description' => 'Lab Test: ' . $test['test_name'],
            'date' => date('d M Y', strtotime($test['test_date'])),
            'amount' => $test['price'],
            'reference_id' => $test['id']
        ];
    }
    
    // Sort services by date (newest first)
    usort($services, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'services' => $services,
        'total_services' => count($services),
        'total_amount' => array_sum(array_column($services, 'amount'))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>