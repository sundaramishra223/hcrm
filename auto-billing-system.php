<?php
// Automatic Billing System
// This file contains functions to automatically add services to patient bills

class AutoBillingSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Create or get existing bill for patient
    public function getOrCreateBill($patient_id, $bill_type = 'comprehensive') {
        // Check if patient has an active bill
        $existing_bill = $this->db->query(
            "SELECT * FROM bills WHERE patient_id = ? AND payment_status IN ('pending', 'partial') ORDER BY created_at DESC LIMIT 1",
            [$patient_id]
        )->fetch();
        
        if ($existing_bill) {
            return $existing_bill['id'];
        }
        
        // Create new bill
        $bill_number = 'BILL' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $bill_sql = "INSERT INTO bills (hospital_id, patient_id, bill_number, bill_date, bill_type, subtotal, total_amount, balance_amount, created_by) VALUES (1, ?, ?, CURDATE(), ?, 0.00, 0.00, 0.00, ?)";
        
        $this->db->query($bill_sql, [
            $patient_id,
            $bill_number,
            $bill_type,
            $_SESSION['user_id'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Add ambulance service to bill
    public function addAmbulanceService($patient_id, $ambulance_booking_id) {
        try {
            // Get ambulance booking details
            $booking = $this->db->query(
                "SELECT ab.*, a.vehicle_type, a.vehicle_number 
                 FROM ambulance_bookings ab 
                 JOIN ambulances a ON ab.ambulance_id = a.id 
                 WHERE ab.id = ?",
                [$ambulance_booking_id]
            )->fetch();
            
            if (!$booking) return false;
            
            $bill_id = $this->getOrCreateBill($patient_id, 'ambulance');
            
            // Calculate charges based on vehicle type and distance
            $base_charges = [
                'basic' => 500,
                'advanced' => 800,
                'icu' => 1200
            ];
            
            $charges = $booking['charges'] ?? $base_charges[$booking['vehicle_type']] ?? 500;
            
            // Add to bill items
            $this->addBillItem(
                $bill_id,
                'other',
                "Ambulance Service - {$booking['vehicle_type']} ({$booking['vehicle_number']})",
                "AMB-{$ambulance_booking_id}",
                1,
                $charges,
                $charges,
                "Pickup: {$booking['pickup_address']}, Drop: {$booking['destination_address']}"
            );
            
            $this->updateBillTotal($bill_id);
            
            return true;
        } catch (Exception $e) {
            error_log("Ambulance billing error: " . $e->getMessage());
            return false;
        }
    }
    
    // Add bed service to bill
    public function addBedService($patient_id, $bed_assignment_id) {
        try {
            // Get bed assignment details
            $assignment = $this->db->query(
                "SELECT ba.*, b.bed_number, b.bed_type, b.daily_rate, b.room_number 
                 FROM bed_assignments ba 
                 JOIN beds b ON ba.bed_id = b.id 
                 WHERE ba.id = ?",
                [$bed_assignment_id]
            )->fetch();
            
            if (!$assignment) return false;
            
            $bill_id = $this->getOrCreateBill($patient_id, 'bed');
            
            // Calculate days
            $start_date = new DateTime($assignment['assigned_date']);
            $end_date = $assignment['discharge_date'] ? new DateTime($assignment['discharge_date']) : new DateTime();
            $days = $start_date->diff($end_date)->days + 1; // Include admission day
            
            $daily_rate = $assignment['daily_rate'] ?? 500;
            $total_cost = $daily_rate * $days;
            
            // Add to bill items
            $this->addBillItem(
                $bill_id,
                'bed',
                "Bed Charges - {$assignment['bed_type']} (Room {$assignment['room_number']}, Bed {$assignment['bed_number']})",
                "BED-{$bed_assignment_id}",
                $days,
                $daily_rate,
                $total_cost,
                "From {$assignment['assigned_date']} for {$days} days"
            );
            
            $this->updateBillTotal($bill_id);
            
            return true;
        } catch (Exception $e) {
            error_log("Bed billing error: " . $e->getMessage());
            return false;
        }
    }
    
    // Add consultation to bill
    public function addConsultation($patient_id, $appointment_id) {
        try {
            // Get appointment details
            $appointment = $this->db->query(
                "SELECT a.*, d.first_name, d.last_name, d.consultation_fee, d.specialization 
                 FROM appointments a 
                 JOIN doctors d ON a.doctor_id = d.id 
                 WHERE a.id = ?",
                [$appointment_id]
            )->fetch();
            
            if (!$appointment) return false;
            
            $bill_id = $this->getOrCreateBill($patient_id, 'consultation');
            
            $consultation_fee = $appointment['consultation_fee'] ?? 500;
            
            // Add to bill items
            $this->addBillItem(
                $bill_id,
                'consultation',
                "Consultation - Dr. {$appointment['first_name']} {$appointment['last_name']} ({$appointment['specialization']})",
                "CONSULT-{$appointment_id}",
                1,
                $consultation_fee,
                $consultation_fee,
                "Appointment on {$appointment['appointment_date']} at {$appointment['appointment_time']}"
            );
            
            $this->updateBillTotal($bill_id);
            
            return true;
        } catch (Exception $e) {
            error_log("Consultation billing error: " . $e->getMessage());
            return false;
        }
    }
    
    // Add lab tests to bill
    public function addLabTests($patient_id, $lab_order_id) {
        try {
            // Get lab order tests
            $tests = $this->db->query(
                "SELECT lot.*, lt.name as test_name 
                 FROM lab_order_tests lot 
                 JOIN lab_tests lt ON lot.lab_test_id = lt.id 
                 WHERE lot.lab_order_id = ?",
                [$lab_order_id]
            )->fetchAll();
            
            if (empty($tests)) return false;
            
            $bill_id = $this->getOrCreateBill($patient_id, 'lab');
            
            foreach ($tests as $test) {
                $this->addBillItem(
                    $bill_id,
                    'lab_test',
                    "Lab Test - {$test['test_name']}",
                    "LAB-{$test['id']}",
                    1,
                    $test['cost'],
                    $test['cost'],
                    "Lab Order #{$lab_order_id}"
                );
            }
            
            $this->updateBillTotal($bill_id);
            
            return true;
        } catch (Exception $e) {
            error_log("Lab test billing error: " . $e->getMessage());
            return false;
        }
    }
    
    // Add medicines to bill
    public function addMedicines($patient_id, $prescription_id) {
        try {
            // Get prescription medicines
            $medicines = $this->db->query(
                "SELECT pm.*, m.name as medicine_name, m.unit_price 
                 FROM prescription_medicines pm 
                 JOIN medicines m ON pm.medicine_id = m.id 
                 WHERE pm.prescription_id = ?",
                [$prescription_id]
            )->fetchAll();
            
            if (empty($medicines)) return false;
            
            $bill_id = $this->getOrCreateBill($patient_id, 'pharmacy');
            
            foreach ($medicines as $medicine) {
                $total_cost = $medicine['quantity'] * $medicine['unit_price'];
                
                $this->addBillItem(
                    $bill_id,
                    'medicine',
                    "Medicine - {$medicine['medicine_name']}",
                    "MED-{$medicine['id']}",
                    $medicine['quantity'],
                    $medicine['unit_price'],
                    $total_cost,
                    "Dosage: {$medicine['dosage']}, Frequency: {$medicine['frequency']}"
                );
            }
            
            $this->updateBillTotal($bill_id);
            
            return true;
        } catch (Exception $e) {
            error_log("Medicine billing error: " . $e->getMessage());
            return false;
        }
    }
    
    // Add equipment usage to bill
    public function addEquipmentUsage($patient_id, $equipment_id, $usage_hours, $hourly_rate = null) {
        try {
            // Get equipment details
            $equipment = $this->db->query(
                "SELECT * FROM equipment WHERE id = ?",
                [$equipment_id]
            )->fetch();
            
            if (!$equipment) return false;
            
            $bill_id = $this->getOrCreateBill($patient_id, 'equipment');
            
            $rate = $hourly_rate ?? 100; // Default ₹100 per hour
            $total_cost = $usage_hours * $rate;
            
            $this->addBillItem(
                $bill_id,
                'equipment',
                "Equipment Usage - {$equipment['name']}",
                "EQP-{$equipment_id}",
                $usage_hours,
                $rate,
                $total_cost,
                "Model: {$equipment['model']}, Location: {$equipment['location']}"
            );
            
            $this->updateBillTotal($bill_id);
            
            return true;
        } catch (Exception $e) {
            error_log("Equipment billing error: " . $e->getMessage());
            return false;
        }
    }
    
    // Helper function to add bill item
    private function addBillItem($bill_id, $item_type, $item_name, $item_code, $quantity, $unit_price, $total_price, $notes = '') {
        // Check if item already exists
        $existing = $this->db->query(
            "SELECT id FROM bill_items WHERE bill_id = ? AND item_code = ?",
            [$bill_id, $item_code]
        )->fetch();
        
        if ($existing) {
            // Update existing item
            $this->db->query(
                "UPDATE bill_items SET quantity = quantity + ?, total_price = total_price + ?, final_price = final_price + ? WHERE id = ?",
                [$quantity, $total_price, $total_price, $existing['id']]
            );
        } else {
            // Insert new item
            $this->db->query(
                "INSERT INTO bill_items (bill_id, item_type, item_name, item_code, quantity, unit_price, total_price, final_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$bill_id, $item_type, $item_name, $item_code, $quantity, $unit_price, $total_price, $total_price]
            );
        }
    }
    
    // Helper function to update bill total
    private function updateBillTotal($bill_id) {
        // Calculate totals
        $totals = $this->db->query(
            "SELECT SUM(final_price) as subtotal FROM bill_items WHERE bill_id = ?",
            [$bill_id]
        )->fetch();
        
        $subtotal = $totals['subtotal'] ?? 0;
        $tax_amount = $subtotal * 0.18; // 18% GST
        $total_amount = $subtotal + $tax_amount;
        
        // Get paid amount
        $payments = $this->db->query(
            "SELECT SUM(payment_amount) as paid FROM bill_payments WHERE bill_id = ?",
            [$bill_id]
        )->fetch();
        
        $paid_amount = $payments['paid'] ?? 0;
        $balance_amount = $total_amount - $paid_amount;
        
        // Update payment status
        $payment_status = 'pending';
        if ($paid_amount >= $total_amount) {
            $payment_status = 'paid';
        } elseif ($paid_amount > 0) {
            $payment_status = 'partial';
        }
        
        // Update bill
        $this->db->query(
            "UPDATE bills SET subtotal = ?, tax_amount = ?, total_amount = ?, paid_amount = ?, balance_amount = ?, payment_status = ?, updated_at = NOW() WHERE id = ?",
            [$subtotal, $tax_amount, $total_amount, $paid_amount, $balance_amount, $payment_status, $bill_id]
        );
    }
    
    // Get patient's current bill summary
    public function getPatientBillSummary($patient_id) {
        return $this->db->query(
            "SELECT b.*, 
                    (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id) as item_count,
                    p.first_name, p.last_name, p.patient_id as patient_number
             FROM bills b 
             JOIN patients p ON b.patient_id = p.id 
             WHERE b.patient_id = ? AND b.payment_status != 'paid' 
             ORDER BY b.created_at DESC",
            [$patient_id]
        )->fetchAll();
    }
}

// Usage hooks - Call these functions when services are used

// When ambulance booking is completed
function onAmbulanceBookingCompleted($ambulance_booking_id) {
    global $db;
    
    // Get patient from booking
    $booking = $db->query("SELECT patient_id FROM ambulance_bookings WHERE id = ?", [$ambulance_booking_id])->fetch();
    
    if ($booking && $booking['patient_id']) {
        $billing = new AutoBillingSystem($db);
        $billing->addAmbulanceService($booking['patient_id'], $ambulance_booking_id);
    }
}

// When bed is assigned
function onBedAssigned($bed_assignment_id) {
    global $db;
    
    $assignment = $db->query("SELECT patient_id FROM bed_assignments WHERE id = ?", [$bed_assignment_id])->fetch();
    
    if ($assignment) {
        $billing = new AutoBillingSystem($db);
        $billing->addBedService($assignment['patient_id'], $bed_assignment_id);
    }
}

// When appointment is completed
function onAppointmentCompleted($appointment_id) {
    global $db;
    
    $appointment = $db->query("SELECT patient_id FROM appointments WHERE id = ?", [$appointment_id])->fetch();
    
    if ($appointment) {
        $billing = new AutoBillingSystem($db);
        $billing->addConsultation($appointment['patient_id'], $appointment_id);
    }
}

// When lab tests are completed
function onLabTestsCompleted($lab_order_id) {
    global $db;
    
    $order = $db->query("SELECT patient_id FROM lab_orders WHERE id = ?", [$lab_order_id])->fetch();
    
    if ($order) {
        $billing = new AutoBillingSystem($db);
        $billing->addLabTests($order['patient_id'], $lab_order_id);
    }
}

// When medicines are dispensed
function onMedicinesDispensed($prescription_id) {
    global $db;
    
    $prescription = $db->query("SELECT patient_id FROM prescriptions WHERE id = ?", [$prescription_id])->fetch();
    
    if ($prescription) {
        $billing = new AutoBillingSystem($db);
        $billing->addMedicines($prescription['patient_id'], $prescription_id);
    }
}
?>