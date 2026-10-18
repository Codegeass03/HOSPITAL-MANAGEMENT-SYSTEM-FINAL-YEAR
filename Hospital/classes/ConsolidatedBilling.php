<?php
/**
 * Consolidated Billing Handler
 * Manages billing for all hospital services: Consultation, Labs, Medications, Admissions
 */

class ConsolidatedBilling {
    private $pdo;
    private $link; // MySQLi connection for backward compatibility
    
    public function __construct($pdo, $link = null) {
        $this->pdo = $pdo;
        $this->link = $link;
    }
    
    /**
     * Generate unique bill number
     */
    private function generateBillNumber() {
        $prefix = 'BILL-' . date('Ym') . '-';
        
        $stmt = $this->pdo->prepare("
            SELECT bill_number FROM billing 
            WHERE bill_number LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $lastBill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastBill) {
            $lastNum = (int)substr($lastBill['bill_number'], -4);
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNum = '0001';
        }
        
        return $prefix . $newNum;
    }
    
    /**
     * Create a new bill for a patient
     * 
     * @param int $patient_id Patient ID
     * @param string $payment_method Payment method
     * @param int $created_by User ID who created the bill
     * @param string $notes Optional notes
     * @return array ['success' => bool, 'bill_id' => int, 'bill_number' => string, 'message' => string]
     */
    public function createBill($patient_id, $payment_method = 'cash', $created_by = null, $notes = null) {
        try {
            $bill_number = $this->generateBillNumber();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO billing (
                    patient_id, bill_number, bill_date, payment_method, 
                    notes, created_by
                ) VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            
            $stmt->execute([
                $patient_id,
                $bill_number,
                $payment_method,
                $notes,
                $created_by
            ]);
            
            $bill_id = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'bill_id' => $bill_id,
                'bill_number' => $bill_number,
                'message' => 'Bill created successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error creating bill: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Add consultation fee to bill
     */
    public function addConsultation($bill_id, $service_id, $description, $price, $department = null, $appointment_id = null) {
        return $this->addBillItem(
            $bill_id,
            'consultation',
            $description,
            1,
            $price,
            $department,
            $service_id,
            $appointment_id
        );
    }
    
    /**
     * Add lab test to bill
     */
    public function addLabTest($bill_id, $test_name, $price, $lab_order_id = null) {
        return $this->addBillItem(
            $bill_id,
            'lab_test',
            $test_name,
            1,
            $price,
            'Laboratory',
            null,
            $lab_order_id
        );
    }
    
    /**
     * Add medication to bill
     */
    public function addMedication($bill_id, $medication_name, $quantity, $unit_price, $prescription_id = null) {
        return $this->addBillItem(
            $bill_id,
            'medication',
            $medication_name,
            $quantity,
            $unit_price,
            'Pharmacy',
            null,
            $prescription_id
        );
    }
    
    /**
     * Add bed charges to bill
     */
    public function addBedCharges($bill_id, $bed_description, $days, $price_per_day, $admission_id = null) {
        return $this->addBillItem(
            $bill_id,
            'bed_charge',
            $bed_description,
            $days,
            $price_per_day,
            'Accommodation',
            null,
            $admission_id
        );
    }
    
    /**
     * Add procedure to bill
     */
    public function addProcedure($bill_id, $procedure_name, $price, $department = null, $service_id = null) {
        return $this->addBillItem(
            $bill_id,
            'procedure',
            $procedure_name,
            1,
            $price,
            $department,
            $service_id
        );
    }
    
    /**
     * Generic method to add any bill item
     */
    private function addBillItem($bill_id, $item_type, $description, $quantity, $unit_price, 
                                  $department = null, $service_id = null, $reference_id = null, 
                                  $discount = 0, $service_date = null) {
        try {
            $total_amount = ($unit_price * $quantity) - $discount;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO bill_items (
                    bill_id, item_type, item_description, department,
                    service_id, reference_id, quantity, unit_price,
                    discount, total_amount, service_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $bill_id,
                $item_type,
                $description,
                $department,
                $service_id,
                $reference_id,
                $quantity,
                $unit_price,
                $discount,
                $total_amount,
                $service_date ?? date('Y-m-d')
            ]);
            
            return [
                'success' => true,
                'item_id' => $this->pdo->lastInsertId(),
                'message' => 'Item added to bill'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error adding item: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Record a payment against a bill
     */
    public function recordPayment($bill_id, $amount, $payment_method, $reference_number = null, 
                                   $received_by = null, $notes = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payments (
                    bill_id, amount_paid, payment_method, reference_number,
                    received_by, notes, payment_date
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $bill_id,
                $amount,
                $payment_method,
                $reference_number,
                $received_by,
                $notes
            ]);
            
            return [
                'success' => true,
                'payment_id' => $this->pdo->lastInsertId(),
                'message' => 'Payment recorded successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error recording payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get complete bill details
     */
    public function getBillDetails($bill_id) {
        try {
            // Get bill header
            $stmt = $this->pdo->prepare("
                SELECT b.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.patient_id as patient_number,
                       p.phone, p.email
                FROM billing b
                INNER JOIN patients p ON b.patient_id = p.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bill_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill) {
                return ['success' => false, 'message' => 'Bill not found'];
            }
            
            // Get bill items
            $stmt = $this->pdo->prepare("
                SELECT * FROM bill_items 
                WHERE bill_id = ? 
                ORDER BY item_type, created_at
            ");
            $stmt->execute([$bill_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get payments
            $stmt = $this->pdo->prepare("
                SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as received_by_name
                FROM payments p
                LEFT JOIN users u ON p.received_by = u.id
                WHERE p.bill_id = ?
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$bill_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'bill' => $bill,
                'items' => $items,
                'payments' => $payments
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error fetching bill: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get patient billing history
     */
    public function getPatientBills($patient_id, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM v_billing_summary
                WHERE patient_id = ?
                ORDER BY bill_date DESC
                LIMIT ?
            ");
            $stmt->execute([$patient_id, $limit]);
            
            return [
                'success' => true,
                'bills' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error fetching patient bills: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create consolidated bill from discharge
     * This combines all services during hospital stay
     */
    public function createDischargeB ($patient_id, $admission_id, $created_by) {
        try {
            $this->pdo->beginTransaction();
            
            // Create bill
            $billResult = $this->createBill($patient_id, 'cash', $created_by, 'Discharge bill - consolidated services');
            if (!$billResult['success']) {
                throw new Exception($billResult['message']);
            }
            
            $bill_id = $billResult['bill_id'];
            
            // Get admission details
            $stmt = $this->pdo->prepare("
                SELECT ba.*, b.bed_number, b.price_per_day, w.ward_name,
                       DATEDIFF(COALESCE(ba.actual_discharge_date, NOW()), ba.admission_date) as days_stayed
                FROM bed_assignments ba
                INNER JOIN beds b ON ba.bed_id = b.id
                INNER JOIN ward w ON b.ward_id = w.id
                WHERE ba.id = ?
            ");
            $stmt->execute([$admission_id]);
            $admission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admission) {
                // Add bed charges
                $days = max(1, $admission['days_stayed']);
                $bed_description = "Bed Charges - {$admission['ward_name']} (Bed: {$admission['bed_number']}) - {$days} days @ Ksh " . number_format($admission['price_per_day'], 2) . "/day";
                $this->addBedCharges($bill_id, $bed_description, $days, $admission['price_per_day'], $admission_id);
            }
            
            // Add consultations from appointments during stay
            $stmt = $this->pdo->prepare("
                SELECT a.*, s.name as service_name, s.price
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.id
                WHERE a.patient_id = ? 
                AND a.appointment_date BETWEEN ? AND COALESCE(?, NOW())
                AND a.status = 'completed'
            ");
            $stmt->execute([
                $patient_id,
                $admission['admission_date'],
                $admission['actual_discharge_date']
            ]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($appointments as $apt) {
                if ($apt['service_id'] && $apt['price']) {
                    $this->addConsultation(
                        $bill_id,
                        $apt['service_id'],
                        $apt['service_name'] . ' - Consultation',
                        $apt['price'],
                        null,
                        $apt['id']
                    );
                }
            }
            
            // Add lab tests
            $stmt = $this->pdo->prepare("
                SELECT lo.*, GROUP_CONCAT(loi.test_name SEPARATOR ', ') as tests
                FROM lab_orders lo
                LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
                WHERE lo.patient_id = ?
                AND lo.order_date BETWEEN ? AND COALESCE(?, NOW())
                AND lo.status = 'completed'
                GROUP BY lo.id
            ");
            $stmt->execute([
                $patient_id,
                $admission['admission_date'],
                $admission['actual_discharge_date']
            ]);
            $labs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($labs as $lab) {
                // You'll need to add pricing logic for lab tests
                $this->addLabTest($bill_id, $lab['tests'], 800, $lab['id']);
            }
            
            // Add prescriptions/medications
            $stmt = $this->pdo->prepare("
                SELECT p.*
                FROM prescriptions p
                WHERE p.patient_id = ?
                AND p.prescription_date BETWEEN ? AND COALESCE(?, NOW())
                AND p.dispensed = 0
            ");
            $stmt->execute([
                $patient_id,
                $admission['admission_date'],
                $admission['actual_discharge_date']
            ]);
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($prescriptions as $rx) {
                // You'll need pricing logic here
                $this->addMedication($bill_id, $rx['medication_name'], 1, 500, $rx['id']);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'bill_id' => $bill_id,
                'bill_number' => $billResult['bill_number'],
                'message' => 'Discharge bill created successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Error creating discharge bill: ' . $e->getMessage()
            ];
        }
    }
}

// ==========================================
// USAGE EXAMPLES
// ==========================================

/*
// Initialize
$billing = new ConsolidatedBilling($pdo, $link);

// Example 1: Create a simple outpatient bill
$bill = $billing->createBill(1, 'cash', 55, 'Outpatient consultation');
if ($bill['success']) {
    $bill_id = $bill['bill_id'];
    
    // Add consultation
    $billing->addConsultation($bill_id, 17, 'General Consultation', 500);
    
    // Add lab test
    $billing->addLabTest($bill_id, 'Complete Blood Count', 800);
    
    // Add medications
    $billing->addMedication($bill_id, 'Amoxicillin 500mg', 10, 85);
    $billing->addMedication($bill_id, 'Paracetamol', 20, 5);
    
    // Record payment
    $billing->recordPayment($bill_id, 1500, 'mpesa', 'QSX123456', 55, 'Partial payment');
}

// Example 2: Create discharge bill (consolidates everything)
$dischargeBill = $billing->createDischargeBill(1, 10, 55);

// Example 3: Get bill details
$details = $billing->getBillDetails($bill_id);
if ($details['success']) {
    $bill = $details['bill'];
    $items = $details['items'];
    $payments = $details['payments'];
}

// Example 4: Get patient billing history
$history = $billing->getPatientBills(1, 5);
*/
?>