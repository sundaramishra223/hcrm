<?php
/**
 * Billing Helper Functions
 * Enhanced with auto-billing, insurance integration, and comprehensive billing features
 */

class BillingHelper {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate bill number
     */
    public function generateBillNumber($hospital_id) {
        try {
            $date = date('Ymd');
            $count = $this->db->query(
                "SELECT COUNT(*) as count FROM bills 
                 WHERE hospital_id = ? AND DATE(created_at) = CURDATE()",
                [$hospital_id]
            )->fetch()['count'];
            
            return 'BILL' . $date . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            error_log("Generate bill number error: " . $e->getMessage());
            return 'BILL' . date('YmdHis');
        }
    }
    
    /**
     * Create comprehensive bill with auto-billing
     */
    public function createComprehensiveBill($patient_id, $visit_id, $bill_type = 'comprehensive', $items = []) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Get patient and visit details
            $patient = $this->db->query("SELECT * FROM patients WHERE id = ?", [$patient_id])->fetch();
            $visit = $this->db->query("SELECT * FROM patient_visits WHERE id = ?", [$visit_id])->fetch();
            
            if (!$patient || !$visit) {
                throw new Exception("Patient or visit not found");
            }
            
            // Generate bill number
            $bill_number = $this->generateBillNumber($patient['hospital_id']);
            
            // Calculate totals
            $subtotal = 0;
            $bill_items = [];
            
            // Auto-add consultation fee if doctor is assigned
            if ($visit['assigned_doctor_id']) {
                $doctor = $this->db->query("SELECT consultation_fee FROM doctors WHERE id = ?", [$visit['assigned_doctor_id']])->fetch();
                if ($doctor && $doctor['consultation_fee'] > 0) {
                    $bill_items[] = [
                        'item_type' => 'consultation',
                        'item_name' => 'Doctor Consultation',
                        'item_code' => 'CONS',
                        'quantity' => 1,
                        'unit_price' => $doctor['consultation_fee'],
                        'total_price' => $doctor['consultation_fee'],
                        'discount_amount' => 0,
                        'final_price' => $doctor['consultation_fee']
                    ];
                    $subtotal += $doctor['consultation_fee'];
                }
            }
            
            // Auto-add bed charges if inpatient
            if ($patient['patient_type'] === 'inpatient') {
                $bed_assignment = $this->db->query(
                    "SELECT ba.*, b.daily_rate FROM bed_assignments ba 
                     JOIN beds b ON ba.bed_id = b.id 
                     WHERE ba.patient_id = ? AND ba.status = 'active'",
                    [$patient_id]
                )->fetch();
                
                if ($bed_assignment && $bed_assignment['daily_rate'] > 0) {
                    $days = max(1, (strtotime(date('Y-m-d')) - strtotime($bed_assignment['assigned_date'])) / (24 * 60 * 60));
                    $bed_charge = $bed_assignment['daily_rate'] * $days;
                    
                    $bill_items[] = [
                        'item_type' => 'bed',
                        'item_name' => 'Bed Charges (' . $days . ' days)',
                        'item_code' => 'BED',
                        'quantity' => $days,
                        'unit_price' => $bed_assignment['daily_rate'],
                        'total_price' => $bed_charge,
                        'discount_amount' => 0,
                        'final_price' => $bed_charge
                    ];
                    $subtotal += $bed_charge;
                }
            }
            
            // Add prescription medicines
            $prescriptions = $this->db->query(
                "SELECT pm.*, m.name as medicine_name, m.unit_price 
                 FROM prescription_medicines pm 
                 JOIN medicines m ON pm.medicine_id = m.id 
                 JOIN prescriptions p ON pm.prescription_id = p.id 
                 WHERE p.patient_id = ? AND p.status = 'active' AND pm.dispensed_quantity < pm.quantity",
                [$patient_id]
            )->fetchAll();
            
            foreach ($prescriptions as $prescription) {
                $remaining_quantity = $prescription['quantity'] - $prescription['dispensed_quantity'];
                if ($remaining_quantity > 0) {
                    $medicine_total = $remaining_quantity * $prescription['unit_price'];
                    
                    $bill_items[] = [
                        'item_type' => 'medicine',
                        'item_name' => $prescription['medicine_name'],
                        'item_code' => 'MED',
                        'quantity' => $remaining_quantity,
                        'unit_price' => $prescription['unit_price'],
                        'total_price' => $medicine_total,
                        'discount_amount' => 0,
                        'final_price' => $medicine_total
                    ];
                    $subtotal += $medicine_total;
                }
            }
            
            // Add lab tests
            $lab_orders = $this->db->query(
                "SELECT lot.*, lt.test_name, lt.cost 
                 FROM lab_order_tests lot 
                 JOIN lab_orders lo ON lot.lab_order_id = lo.id 
                 JOIN lab_tests lt ON lot.lab_test_id = lt.id 
                 WHERE lo.patient_id = ? AND lot.status = 'completed' AND lot.result_value IS NOT NULL",
                [$patient_id]
            )->fetchAll();
            
            foreach ($lab_orders as $lab_test) {
                $bill_items[] = [
                    'item_type' => 'lab_test',
                    'item_name' => $lab_test['test_name'],
                    'item_code' => 'LAB',
                    'quantity' => 1,
                    'unit_price' => $lab_test['cost'],
                    'total_price' => $lab_test['cost'],
                    'discount_amount' => 0,
                    'final_price' => $lab_test['cost']
                ];
                $subtotal += $lab_test['cost'];
            }
            
            // Add custom items
            foreach ($items as $item) {
                $bill_items[] = $item;
                $subtotal += $item['final_price'];
            }
            
            // Calculate tax and discounts
            $tax_amount = $subtotal * 0.05; // 5% tax
            $discount_amount = 0; // Can be calculated based on patient type, insurance, etc.
            $total_amount = $subtotal + $tax_amount - $discount_amount;
            
            // Create bill
            $bill_sql = "INSERT INTO bills (hospital_id, patient_id, visit_id, bill_number, bill_date, bill_type, 
                                          subtotal, discount_amount, tax_amount, total_amount, balance_amount, 
                                          payment_status, created_by) 
                         VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'pending', ?)";
            
            $this->db->query($bill_sql, [
                $patient['hospital_id'], $patient_id, $visit_id, $bill_number, $bill_type,
                $subtotal, $discount_amount, $tax_amount, $total_amount, $total_amount, $_SESSION['user_id']
            ]);
            
            $bill_id = $this->db->lastInsertId();
            
            // Add bill items
            foreach ($bill_items as $item) {
                $item_sql = "INSERT INTO bill_items (bill_id, item_type, item_name, item_code, quantity, 
                                                   unit_price, total_price, discount_amount, final_price) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $this->db->query($item_sql, [
                    $bill_id, $item['item_type'], $item['item_name'], $item['item_code'],
                    $item['quantity'], $item['unit_price'], $item['total_price'],
                    $item['discount_amount'], $item['final_price']
                ]);
            }
            
            // Log billing activity
            $this->logBillingActivity($bill_id, 'created', $_SESSION['user_id'], 'Comprehensive bill created');
            
            $this->db->getConnection()->commit();
            return $bill_id;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Create comprehensive bill error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process payment
     */
    public function processPayment($bill_id, $payment_amount, $payment_method = 'cash', $payment_notes = '') {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Get bill details
            $bill = $this->db->query("SELECT * FROM bills WHERE id = ?", [$bill_id])->fetch();
            if (!$bill) {
                throw new Exception("Bill not found");
            }
            
            // Calculate new paid amount
            $new_paid_amount = $bill['paid_amount'] + $payment_amount;
            $new_balance_amount = $bill['total_amount'] - $new_paid_amount;
            
            // Determine payment status
            $payment_status = 'partial';
            if ($new_balance_amount <= 0) {
                $payment_status = 'paid';
                $new_balance_amount = 0;
            }
            
            // Update bill
            $this->db->query(
                "UPDATE bills SET paid_amount = ?, balance_amount = ?, payment_status = ?, 
                                 payment_method = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?",
                [$new_paid_amount, $new_balance_amount, $payment_status, $payment_method, $bill_id]
            );
            
            // Log payment
            $this->logPayment($bill_id, $payment_amount, $payment_method, $payment_notes);
            
            // Log billing activity
            $this->logBillingActivity($bill_id, 'payment_received', $_SESSION['user_id'], 
                                    "Payment of ₹" . number_format($payment_amount, 2) . " received");
            
            $this->db->getConnection()->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Process payment error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create insurance claim
     */
    public function createInsuranceClaim($bill_id, $insurance_provider, $insurance_number, $claim_amount) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Get bill details
            $bill = $this->db->query("SELECT * FROM bills WHERE id = ?", [$bill_id])->fetch();
            if (!$bill) {
                throw new Exception("Bill not found");
            }
            
            // Create insurance claim
            $claim_sql = "INSERT INTO insurance_claims (patient_id, bill_id, insurance_provider, 
                                                       insurance_number, claim_amount, claim_date, status) 
                         VALUES (?, ?, ?, ?, ?, CURDATE(), 'pending')";
            
            $this->db->query($claim_sql, [
                $bill['patient_id'], $bill_id, $insurance_provider, $insurance_number, $claim_amount
            ]);
            
            $claim_id = $this->db->lastInsertId();
            
            // Update bill with insurance claim
            $this->db->query(
                "UPDATE bills SET insurance_claim_id = ? WHERE id = ?",
                [$claim_id, $bill_id]
            );
            
            $this->db->getConnection()->commit();
            return $claim_id;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Create insurance claim error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process insurance claim
     */
    public function processInsuranceClaim($claim_id, $status, $approved_amount = null, $rejection_reason = '') {
        try {
            $this->db->getConnection()->beginTransaction();
            
            $update_data = ['status' => $status];
            
            if ($status === 'approved' && $approved_amount) {
                $update_data['approved_amount'] = $approved_amount;
                $update_data['approved_by'] = $_SESSION['user_id'];
                $update_data['approved_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'rejected') {
                $update_data['rejection_reason'] = $rejection_reason;
            }
            
            $sql = "UPDATE insurance_claims SET " . implode(', ', array_map(fn($k) => "$k = ?", array_keys($update_data))) . " WHERE id = ?";
            $params = array_values($update_data);
            $params[] = $claim_id;
            
            $this->db->query($sql, $params);
            
            // If approved, process payment
            if ($status === 'approved' && $approved_amount) {
                $claim = $this->db->query("SELECT bill_id FROM insurance_claims WHERE id = ?", [$claim_id])->fetch();
                if ($claim) {
                    $this->processPayment($claim['bill_id'], $approved_amount, 'insurance', 'Insurance claim approved');
                }
            }
            
            $this->db->getConnection()->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollBack();
            error_log("Process insurance claim error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate payment receipt
     */
    public function generateReceipt($bill_id) {
        try {
            $bill = $this->db->query(
                "SELECT b.*, p.first_name, p.last_name, p.patient_id as patient_number,
                        v.visit_type, v.visit_reason
                 FROM bills b 
                 JOIN patients p ON b.patient_id = p.id 
                 LEFT JOIN patient_visits v ON b.visit_id = v.id 
                 WHERE b.id = ?",
                [$bill_id]
            )->fetch();
            
            if (!$bill) {
                throw new Exception("Bill not found");
            }
            
            $bill_items = $this->db->query(
                "SELECT * FROM bill_items WHERE bill_id = ? ORDER BY id",
                [$bill_id]
            )->fetchAll();
            
            return [
                'bill' => $bill,
                'items' => $bill_items
            ];
            
        } catch (Exception $e) {
            error_log("Generate receipt error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get billing statistics
     */
    public function getBillingStats($date_from = null, $date_to = null, $hospital_id = 1) {
        try {
            $where_conditions = ["hospital_id = ?"];
            $params = [$hospital_id];
            
            if ($date_from) {
                $where_conditions[] = "DATE(created_at) >= ?";
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $where_conditions[] = "DATE(created_at) <= ?";
                $params[] = $date_to;
            }
            
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
            
            $sql = "SELECT 
                        COUNT(*) as total_bills,
                        SUM(total_amount) as total_revenue,
                        SUM(paid_amount) as total_paid,
                        SUM(balance_amount) as total_pending,
                        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_bills,
                        COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_bills,
                        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_bills,
                        AVG(total_amount) as average_bill_amount
                    FROM bills " . $where_clause;
            
            return $this->db->query($sql, $params)->fetch();
            
        } catch (Exception $e) {
            error_log("Get billing stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get patient billing history
     */
    public function getPatientBillingHistory($patient_id) {
        try {
            return $this->db->query(
                "SELECT b.*, 
                        (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id) as item_count
                 FROM bills b 
                 WHERE b.patient_id = ? 
                 ORDER BY b.created_at DESC",
                [$patient_id]
            )->fetchAll();
        } catch (Exception $e) {
            error_log("Get patient billing history error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log payment
     */
    private function logPayment($bill_id, $amount, $method, $notes) {
        try {
            $this->db->query(
                "INSERT INTO payment_logs (bill_id, amount, payment_method, notes, created_by) 
                 VALUES (?, ?, ?, ?, ?)",
                [$bill_id, $amount, $method, $notes, $_SESSION['user_id']]
            );
        } catch (Exception $e) {
            error_log("Log payment error: " . $e->getMessage());
        }
    }
    
    /**
     * Log billing activity
     */
    private function logBillingActivity($bill_id, $action, $user_id, $details = '') {
        try {
            $this->db->query(
                "INSERT INTO audit_logs (user_id, action, table_name, record_id, notes) 
                 VALUES (?, ?, 'bills', ?, ?)",
                [$user_id, $action, $bill_id, $details]
            );
        } catch (Exception $e) {
            error_log("Log billing activity error: " . $e->getMessage());
        }
    }
    
    /**
     * Send bill notification
     */
    public function sendBillNotification($bill_id, $notification_type = 'created') {
        try {
            $bill = $this->db->query(
                "SELECT b.*, p.first_name, p.last_name, p.email, p.phone 
                 FROM bills b 
                 JOIN patients p ON b.patient_id = p.id 
                 WHERE b.id = ?",
                [$bill_id]
            )->fetch();
            
            if (!$bill) {
                return false;
            }
            
            // Email notification
            if ($this->isEmailNotificationsEnabled()) {
                $this->sendBillEmail($bill, $notification_type);
            }
            
            // SMS notification
            if ($this->isSMSNotificationsEnabled()) {
                $this->sendBillSMS($bill, $notification_type);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Send bill notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email notifications are enabled
     */
    private function isEmailNotificationsEnabled() {
        try {
            $setting = $this->db->query(
                "SELECT setting_value FROM system_settings 
                 WHERE hospital_id = 1 AND setting_key = 'enable_email_notifications'"
            )->fetch();
            
            return $setting && $setting['setting_value'] === 'true';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if SMS notifications are enabled
     */
    private function isSMSNotificationsEnabled() {
        try {
            $setting = $this->db->query(
                "SELECT setting_value FROM system_settings 
                 WHERE hospital_id = 1 AND setting_key = 'enable_sms_notifications'"
            )->fetch();
            
            return $setting && $setting['setting_value'] === 'true';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Send bill email
     */
    private function sendBillEmail($bill, $notification_type) {
        try {
            $template = $this->db->query(
                "SELECT * FROM email_templates 
                 WHERE hospital_id = 1 AND template_name = 'bill_" . $notification_type . "'"
            )->fetch();
            
            if (!$template) {
                return false;
            }
            
            $subject = $template['subject'];
            $body = $template['body'];
            
            // Replace variables
            $variables = [
                '{patient_name}' => $bill['first_name'] . ' ' . $bill['last_name'],
                '{bill_amount}' => '₹' . number_format($bill['total_amount'], 2),
                '{bill_number}' => $bill['bill_number'],
                '{due_date}' => date('F d, Y', strtotime('+30 days'))
            ];
            
            $body = str_replace(array_keys($variables), array_values($variables), $body);
            
            // Log email
            $this->db->query(
                "INSERT INTO email_logs (template_id, recipient_email, subject, body, status) 
                 VALUES (?, ?, ?, ?, 'pending')",
                [$template['id'], $bill['email'], $subject, $body]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Send bill email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send bill SMS
     */
    private function sendBillSMS($bill, $notification_type) {
        try {
            $message = "Bill " . $notification_type . " for " . $bill['first_name'] . " " . $bill['last_name'] . 
                      ". Amount: ₹" . number_format($bill['total_amount'], 2) . ". Bill #: " . $bill['bill_number'];
            
            // Log SMS (implement actual SMS sending here)
            error_log("SMS to " . $bill['phone'] . ": " . $message);
            
            return true;
        } catch (Exception $e) {
            error_log("Send bill SMS error: " . $e->getMessage());
            return false;
        }
    }
}
?>