<?php
/**
 * Appointment Helper Functions
 * Enhanced with conflict checking, security, and role-based access
 */

class AppointmentHelper {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check for appointment conflicts
     */
    public function checkAppointmentConflict($doctor_id, $appointment_date, $appointment_time, $duration_minutes = 30, $exclude_id = null) {
        try {
            $start_time = $appointment_time;
            $end_time = date('H:i:s', strtotime($appointment_time . ' + ' . $duration_minutes . ' minutes'));
            
            $sql = "SELECT COUNT(*) as count FROM appointments 
                    WHERE doctor_id = ? 
                    AND appointment_date = ? 
                    AND status NOT IN ('cancelled', 'no_show')
                    AND (
                        (appointment_time <= ? AND DATE_ADD(appointment_time, INTERVAL duration_minutes MINUTE) > ?)
                        OR (appointment_time < ? AND DATE_ADD(appointment_time, INTERVAL duration_minutes MINUTE) >= ?)
                        OR (appointment_time >= ? AND appointment_time < ?)
                    )";
            
            $params = [$doctor_id, $appointment_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time];
            
            if ($exclude_id) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            
            $result = $this->db->query($sql, $params)->fetch();
            return $result['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Appointment conflict check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available time slots for a doctor on a specific date
     */
    public function getAvailableTimeSlots($doctor_id, $appointment_date, $duration_minutes = 30) {
        try {
            $doctor_schedule = $this->getDoctorSchedule($doctor_id, $appointment_date);
            $booked_slots = $this->getBookedTimeSlots($doctor_id, $appointment_date);
            
            $available_slots = [];
            $start_time = strtotime($doctor_schedule['start_time']);
            $end_time = strtotime($doctor_schedule['end_time']);
            
            while ($start_time < $end_time) {
                $slot_time = date('H:i:s', $start_time);
                $slot_end = date('H:i:s', strtotime($slot_time . ' + ' . $duration_minutes . ' minutes'));
                
                // Check if this slot conflicts with any booked appointments
                $conflict = false;
                foreach ($booked_slots as $booked) {
                    if ($this->timeSlotsOverlap($slot_time, $slot_end, $booked['start'], $booked['end'])) {
                        $conflict = true;
                        break;
                    }
                }
                
                if (!$conflict) {
                    $available_slots[] = $slot_time;
                }
                
                $start_time = strtotime($slot_time . ' + ' . $duration_minutes . ' minutes');
            }
            
            return $available_slots;
            
        } catch (Exception $e) {
            error_log("Get available time slots error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get doctor's schedule for a specific date
     */
    private function getDoctorSchedule($doctor_id, $appointment_date) {
        try {
            // Default schedule (can be enhanced with doctor_schedules table)
            $day_of_week = date('w', strtotime($appointment_date));
            
            // Check if doctor has custom schedule
            $schedule = $this->db->query(
                "SELECT start_time, end_time FROM doctor_schedules 
                 WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1",
                [$doctor_id, $day_of_week]
            )->fetch();
            
            if ($schedule) {
                return $schedule;
            }
            
            // Default schedule
            return [
                'start_time' => '09:00:00',
                'end_time' => '17:00:00'
            ];
            
        } catch (Exception $e) {
            error_log("Get doctor schedule error: " . $e->getMessage());
            return [
                'start_time' => '09:00:00',
                'end_time' => '17:00:00'
            ];
        }
    }
    
    /**
     * Get booked time slots for a doctor on a specific date
     */
    private function getBookedTimeSlots($doctor_id, $appointment_date) {
        try {
            $booked = $this->db->query(
                "SELECT appointment_time, duration_minutes FROM appointments 
                 WHERE doctor_id = ? AND appointment_date = ? AND status NOT IN ('cancelled', 'no_show')",
                [$doctor_id, $appointment_date]
            )->fetchAll();
            
            $slots = [];
            foreach ($booked as $appointment) {
                $slots[] = [
                    'start' => $appointment['appointment_time'],
                    'end' => date('H:i:s', strtotime($appointment['appointment_time'] . ' + ' . $appointment['duration_minutes'] . ' minutes'))
                ];
            }
            
            return $slots;
            
        } catch (Exception $e) {
            error_log("Get booked time slots error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if two time slots overlap
     */
    private function timeSlotsOverlap($start1, $end1, $start2, $end2) {
        return $start1 < $end2 && $start2 < $end1;
    }
    
    /**
     * Assign patient to doctor and create consultation bill
     */
    public function assignPatientToDoctor($patient_id, $doctor_id) {
        try {
            // Update patient assignment
            $this->db->query(
                "UPDATE patients SET assigned_doctor_id = ? WHERE id = ?",
                [$doctor_id, $patient_id]
            );
            
            // Get doctor's consultation fee
            $doctor = $this->db->query(
                "SELECT consultation_fee, CONCAT(first_name, ' ', last_name) as doctor_name FROM doctors WHERE id = ?",
                [$doctor_id]
            )->fetch();
            
            if ($doctor && $doctor['consultation_fee'] > 0) {
                // Generate bill number
                $bill_number = 'BILL' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                
                // Create consultation bill automatically
                $this->db->query(
                    "INSERT INTO bills (patient_id, bill_number, bill_date, bill_type, subtotal, discount_amount, tax_amount, total_amount, balance_amount, payment_status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $patient_id,
                        $bill_number,
                        date('Y-m-d'),
                        'consultation',
                        $doctor['consultation_fee'],
                        0, // no discount
                        0, // no tax by default
                        $doctor['consultation_fee'],
                        $doctor['consultation_fee'], // full balance
                        'pending',
                        'Auto-generated consultation bill for Dr. ' . $doctor['doctor_name'],
                        $_SESSION['user_id'] ?? 1
                    ]
                );
                
                // Get the bill ID and create bill items
                $bill_id = $this->db->lastInsertId();
                
                $this->db->query(
                    "INSERT INTO bill_items (bill_id, item_name, item_type, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $bill_id,
                        'Consultation - Dr. ' . $doctor['doctor_name'],
                        'consultation',
                        1,
                        $doctor['consultation_fee'],
                        $doctor['consultation_fee']
                    ]
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Assign patient to doctor error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get doctor's assigned patients
     */
    public function getDoctorPatients($doctor_id) {
        try {
            return $this->db->query(
                "SELECT p.*, 
                        (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND doctor_id = ?) as appointment_count,
                        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id AND doctor_id = ?) as prescription_count
                 FROM patients p 
                 WHERE p.assigned_doctor_id = ? 
                 ORDER BY p.first_name, p.last_name",
                [$doctor_id, $doctor_id, $doctor_id]
            )->fetchAll();
        } catch (Exception $e) {
            error_log("Get doctor patients error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if doctor can access patient
     */
    public function canDoctorAccessPatient($doctor_id, $patient_id) {
        try {
            $result = $this->db->query(
                "SELECT COUNT(*) as count FROM patients 
                 WHERE id = ? AND assigned_doctor_id = ?",
                [$patient_id, $doctor_id]
            )->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Check doctor access error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate appointment number
     */
    public function generateAppointmentNumber($hospital_id) {
        try {
            $date = date('Ymd');
            $count = $this->db->query(
                "SELECT COUNT(*) as count FROM appointments 
                 WHERE hospital_id = ? AND DATE(created_at) = CURDATE()",
                [$hospital_id]
            )->fetch()['count'];
            
            return 'APT' . $date . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            error_log("Generate appointment number error: " . $e->getMessage());
            return 'APT' . date('YmdHis');
        }
    }
    
    /**
     * Validate appointment data
     */
    public function validateAppointmentData($data) {
        $errors = [];
        
        // Required fields
        $required_fields = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Date validation
        if (!empty($data['appointment_date'])) {
            $appointment_date = strtotime($data['appointment_date']);
            $today = strtotime(date('Y-m-d'));
            
            if ($appointment_date < $today) {
                $errors[] = 'Appointment date cannot be in the past';
            }
        }
        
        // Time validation
        if (!empty($data['appointment_time'])) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $data['appointment_time'])) {
                $errors[] = 'Invalid appointment time format';
            }
        }
        
        // Duration validation
        if (!empty($data['duration_minutes'])) {
            if (!is_numeric($data['duration_minutes']) || $data['duration_minutes'] < 15 || $data['duration_minutes'] > 180) {
                $errors[] = 'Duration must be between 15 and 180 minutes';
            }
        }
        
        return $errors;
    }
    
    /**
     * Log appointment activity
     */
    public function logAppointmentActivity($appointment_id, $action, $user_id, $details = '') {
        try {
            $this->db->query(
                "INSERT INTO audit_logs (user_id, action, table_name, record_id, notes) 
                 VALUES (?, ?, 'appointments', ?, ?)",
                [$user_id, $action, $appointment_id, $details]
            );
        } catch (Exception $e) {
            error_log("Log appointment activity error: " . $e->getMessage());
        }
    }
    
    /**
     * Send appointment notifications
     */
    public function sendAppointmentNotifications($appointment_id, $notification_type = 'created') {
        try {
            $appointment = $this->db->query(
                "SELECT a.*, p.first_name, p.last_name, p.email, p.phone, 
                        d.first_name as doctor_first_name, d.last_name as doctor_last_name
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 JOIN doctors d ON a.doctor_id = d.id
                 WHERE a.id = ?",
                [$appointment_id]
            )->fetch();
            
            if (!$appointment) {
                return false;
            }
            
            // Email notification
            if ($this->isEmailNotificationsEnabled()) {
                $this->sendAppointmentEmail($appointment, $notification_type);
            }
            
            // SMS notification
            if ($this->isSMSNotificationsEnabled()) {
                $this->sendAppointmentSMS($appointment, $notification_type);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Send appointment notifications error: " . $e->getMessage());
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
     * Send appointment email
     */
    private function sendAppointmentEmail($appointment, $notification_type) {
        try {
            $template = $this->db->query(
                "SELECT * FROM email_templates 
                 WHERE hospital_id = 1 AND template_name = 'appointment_" . $notification_type . "'"
            )->fetch();
            
            if (!$template) {
                return false;
            }
            
            $subject = $template['subject'];
            $body = $template['body'];
            
            // Replace variables
            $variables = [
                '{patient_name}' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                '{doctor_name}' => $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'],
                '{appointment_date}' => date('F d, Y', strtotime($appointment['appointment_date'])),
                '{appointment_time}' => date('g:i A', strtotime($appointment['appointment_time'])),
                '{appointment_number}' => $appointment['appointment_number']
            ];
            
            $body = str_replace(array_keys($variables), array_values($variables), $body);
            
            // Log email
            $this->db->query(
                "INSERT INTO email_logs (template_id, recipient_email, subject, body, status) 
                 VALUES (?, ?, ?, ?, 'pending')",
                [$template['id'], $appointment['email'], $subject, $body]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Send appointment email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send appointment SMS
     */
    private function sendAppointmentSMS($appointment, $notification_type) {
        try {
            // This would integrate with SMS service like Twilio
            // For now, just log the SMS
            $message = "Appointment " . $notification_type . " for " . $appointment['first_name'] . " " . $appointment['last_name'];
            
            // Log SMS (implement actual SMS sending here)
            error_log("SMS to " . $appointment['phone'] . ": " . $message);
            
            return true;
        } catch (Exception $e) {
            error_log("Send appointment SMS error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get appointment statistics
     */
    public function getAppointmentStats($doctor_id = null, $date_from = null, $date_to = null) {
        try {
            $where_conditions = [];
            $params = [];
            
            if ($doctor_id) {
                $where_conditions[] = "doctor_id = ?";
                $params[] = $doctor_id;
            }
            
            if ($date_from) {
                $where_conditions[] = "appointment_date >= ?";
                $params[] = $date_from;
            }
            
            if ($date_to) {
                $where_conditions[] = "appointment_date <= ?";
                $params[] = $date_to;
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
            
            $sql = "SELECT 
                        COUNT(*) as total_appointments,
                        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                        COUNT(CASE WHEN status = 'no_show' THEN 1 END) as no_show
                    FROM appointments " . $where_clause;
            
            return $this->db->query($sql, $params)->fetch();
        } catch (Exception $e) {
            error_log("Get appointment stats error: " . $e->getMessage());
            return [];
        }
    }
}
?>