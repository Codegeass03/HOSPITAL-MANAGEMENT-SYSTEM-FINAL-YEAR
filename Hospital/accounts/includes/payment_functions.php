<?php
/**
 * Payment Utility Functions
 * Centralized functions for checking payment status across all services
 */

/**
 * Check if a bill is still outstanding
 * @param PDO $pdo Database connection
 * @param string $bill_type Type of bill (hospital, lab, pharmacy, consultation, admission)
 * @param int $bill_id ID of the bill
 * @return bool True if bill has outstanding balance, false otherwise
 */
function isBillStillOutstanding($pdo, $bill_type, $bill_id) {
    try {
        switch($bill_type) {
            case 'hospital':
            case 'general':
                $stmt = $pdo->prepare("SELECT balance_amount FROM billing WHERE id = ?");
                $stmt->execute([$bill_id]);
                $result = $stmt->fetch();
                return ($result && $result['balance_amount'] > 0);
                
            case 'lab':
                $stmt = $pdo->prepare("
                    SELECT (total_amount - COALESCE(paid_amount, 0)) as balance 
                    FROM lab_orders 
                    WHERE id = ?
                ");
                $stmt->execute([$bill_id]);
                $result = $stmt->fetch();
                return ($result && $result['balance'] > 0);
                
            case 'consultation':
                $stmt = $pdo->prepare("
                    SELECT a.consultation_fee
                    FROM appointments a
                    WHERE a.id = ?
                    AND a.status = 'completed'
                    AND a.consultation_fee > 0
                    AND NOT EXISTS (
                        SELECT 1 FROM billing b 
                        WHERE b.patient_id = a.patient_id
                        AND b.status = 'paid'
                        AND b.total_amount = a.consultation_fee
                        AND DATE(b.bill_date) = DATE(a.appointment_date)
                    )
                ");
                $stmt->execute([$bill_id]);
                $result = $stmt->fetch();
                return ($result && $result['consultation_fee'] > 0);
                
            case 'admission':
                $stmt = $pdo->prepare("
                    SELECT (COALESCE(total_charges, 0) - COALESCE(paid_amount, 0)) as balance
                    FROM bed_assignments
                    WHERE id = ?
                    AND status = 'active'
                ");
                $stmt->execute([$bill_id]);
                $result = $stmt->fetch();
                return ($result && $result['balance'] > 0);
                
            case 'pharmacy':
                // For pharmacy, we need to use MySQLi
                global $link;
                if (!$link) {
                    include "admin/connection.php";
                }
                mysqli_select_db($link, "hospital");
                
                $query = "SELECT balance_amount FROM pharmacy_billing WHERE id = " . intval($bill_id);
                $result = mysqli_query($link, $query);
                
                if ($result && $row = mysqli_fetch_assoc($result)) {
                    return ($row['balance_amount'] ?? 0) > 0;
                }
                return false;
                
            default:
                return false;
        }
    } catch (Exception $e) {
        error_log("Error checking bill status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all outstanding bills for a patient
 * @param PDO $pdo Database connection
 * @param int $patient_id Patient ID
 * @return array Array of outstanding bills
 */
function getPatientOutstandingBills($pdo, $patient_id) {
    $outstanding_balances = [];
    
    try {
        // 1. Hospital Bills
        $stmt = $pdo->prepare("
            SELECT 
                'general' as service_type,
                b.bill_number as description,
                b.total_amount,
                b.paid_amount,
                b.balance_amount as balance,
                b.id as reference_id,
                b.bill_date as service_date
            FROM billing b
            WHERE b.patient_id = ?
            AND b.balance_amount > 0
        ");
        $stmt->execute([$patient_id]);
        $outstanding_balances = array_merge($outstanding_balances, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 2. Lab Orders
        $stmt = $pdo->prepare("
            SELECT 
                'lab' as service_type,
                CONCAT('Lab Order #', lo.order_number) as description,
                lo.total_amount,
                lo.paid_amount,
                (lo.total_amount - COALESCE(lo.paid_amount, 0)) as balance,
                lo.id as reference_id,
                lo.order_date as service_date
            FROM lab_orders lo
            WHERE lo.patient_id = ?
            AND (lo.total_amount - COALESCE(lo.paid_amount, 0)) > 0
        ");
        $stmt->execute([$patient_id]);
        $outstanding_balances = array_merge($outstanding_balances, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 3. Consultations
        $stmt = $pdo->prepare("
            SELECT 
                'consultation' as service_type,
                CONCAT('Consultation - Appointment #', a.appointment_number) as description,
                a.consultation_fee as total_amount,
                0 as paid_amount,
                a.consultation_fee as balance,
                a.id as reference_id,
                a.appointment_date as service_date
            FROM appointments a
            WHERE a.patient_id = ?
            AND a.status = 'completed'
            AND a.consultation_fee > 0
            AND NOT EXISTS (
                SELECT 1 FROM billing b 
                WHERE b.patient_id = a.patient_id
                AND b.status = 'paid'
                AND b.total_amount = a.consultation_fee
                AND DATE(b.bill_date) = DATE(a.appointment_date)
            )
        ");
        $stmt->execute([$patient_id]);
        $outstanding_balances = array_merge($outstanding_balances, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 4. Admissions
        $stmt = $pdo->prepare("
            SELECT 
                'admission' as service_type,
                CONCAT('Bed Charges - Ward ', w.ward_name) as description,
                ba.total_charges as total_amount,
                COALESCE(ba.paid_amount, 0) as paid_amount,
                (ba.total_charges - COALESCE(ba.paid_amount, 0)) as balance,
                ba.id as reference_id,
                ba.admission_date as service_date
            FROM bed_assignments ba
            INNER JOIN beds b ON ba.bed_id = b.id
            INNER JOIN ward w ON b.ward_id = w.id
            WHERE ba.patient_id = ?
            AND ba.status = 'active'
            AND (ba.total_charges - COALESCE(ba.paid_amount, 0)) > 0
        ");
        $stmt->execute([$patient_id]);
        $outstanding_balances = array_merge($outstanding_balances, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // 5. Pharmacy Bills
        global $link;
        if (!$link) {
            include "admin/connection.php";
        }
        mysqli_select_db($link, "hospital");
        
        $query = "
            SELECT 
                'pharmacy' as service_type,
                CONCAT('Pharmacy Bill #', pb.bill_no) as description,
                pb.total_amount,
                COALESCE(pb.paid_amount, 0) as paid_amount,
                COALESCE(pb.balance_amount, pb.total_amount - COALESCE(pb.paid_amount, 0)) as balance,
                pb.id as reference_id,
                pb.date as service_date
            FROM pharmacy_billing pb
            WHERE pb.patient_id = $patient_id
            AND COALESCE(pb.balance_amount, pb.total_amount - COALESCE(pb.paid_amount, 0)) > 0
        ";
        
        $result = mysqli_query($link, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $outstanding_balances[] = $row;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching patient outstanding bills: " . $e->getMessage());
    }
    
    return $outstanding_balances;
}

/**
 * Update payment status across all services for a patient
 * This ensures consistency after payment processing
 */
function updatePaymentStatusAcrossServices($pdo, $patient_id) {
    try {
        // Update hospital bills status
        $pdo->query("
            UPDATE billing 
            SET status = CASE 
                WHEN balance_amount <= 0 THEN 'paid'
                WHEN paid_amount > 0 THEN 'partial'
                ELSE 'pending'
            END
            WHERE patient_id = $patient_id
        ");
        
        // Update lab orders status
        $pdo->query("
            UPDATE lab_orders 
            SET payment_status = CASE 
                WHEN (total_amount - COALESCE(paid_amount, 0)) <= 0 THEN 'paid'
                WHEN paid_amount > 0 THEN 'partial'
                ELSE 'unpaid'
            END
            WHERE patient_id = $patient_id
        ");
        
        // Update pharmacy bills (if using MySQLi)
        global $link;
        if ($link) {
            mysqli_select_db($link, "hospital");
            mysqli_query($link, "
                UPDATE pharmacy_billing 
                SET payment_status = CASE 
                    WHEN balance_amount <= 0 THEN 'paid'
                    WHEN paid_amount > 0 THEN 'partial'
                    ELSE 'unpaid'
                END
                WHERE patient_id = $patient_id
            ");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating payment statuses: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate total outstanding balance for a patient
 */
function calculateTotalOutstanding($pdo, $patient_id) {
    $bills = getPatientOutstandingBills($pdo, $patient_id);
    $total = 0;
    
    foreach ($bills as $bill) {
        $total += $bill['balance'];
    }
    
    return $total;
}