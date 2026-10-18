<?php
session_start();
include "includes/db_connect.php";
include "admin/connection.php"; // MySQLi connection for pharmacy

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['patient_id'])) {
    echo json_encode(['error' => 'Unauthorized or missing patient ID']);
    exit();
}

$patient_id = intval($_GET['patient_id']);

try {
    // Get patient details
    $stmt = $pdo->prepare("
        SELECT 
            id,
            patient_id as patient_number,
            CONCAT(first_name, ' ', last_name) as name,
            phone,
            email
        FROM patients 
        WHERE id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(['error' => 'Patient not found']);
        exit();
    }

    $outstanding_balances = [];

    // 1. Consultation Fees from Appointments
    $stmt = $pdo->prepare("
        SELECT 
            'consultation' as service_type,
            CONCAT('Appointment #', appointment_number, ' - ', DATE_FORMAT(appointment_date, '%M %d, %Y')) as description,
            consultation_fee as total_amount,
            0 as paid_amount,
            consultation_fee as balance,
            id as reference_id,
            appointment_date as service_date
        FROM appointments
        WHERE patient_id = ? 
        AND status = 'completed'
        AND payment_status = 'unpaid'
        AND consultation_fee > 0
        ORDER BY appointment_date DESC
    ");
    $stmt->execute([$patient_id]);
    $consultation_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $outstanding_balances = array_merge($outstanding_balances, $consultation_balances);

    // 2. Lab Orders
    $stmt = $pdo->prepare("
        SELECT 
            'lab' as service_type,
            CONCAT('Lab Order #', order_number, ' - ', DATE_FORMAT(order_date, '%M %d, %Y')) as description,
            total_amount,
            paid_amount,
            (total_amount - paid_amount) as balance,
            id as reference_id,
            order_date as service_date
        FROM lab_orders
        WHERE patient_id = ? 
        AND payment_status IN ('unpaid', 'partial')
        AND (total_amount - paid_amount) > 0
        ORDER BY order_date DESC
    ");
    $stmt->execute([$patient_id]);
    $lab_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $outstanding_balances = array_merge($outstanding_balances, $lab_balances);

    // 3. General Billing Items
    $stmt = $pdo->prepare("
        SELECT 
            'general' as service_type,
            CONCAT('Bill #', b.bill_number, ' - ', bi.item_description) as description,
            bi.total_amount,
            0 as paid_amount,
            bi.total_amount as balance,
            bi.id as reference_id,
            bi.service_date as service_date
        FROM bill_items bi
        INNER JOIN billing b ON bi.bill_id = b.id
        WHERE b.patient_id = ?
        AND b.status IN ('pending', 'partial')
        AND b.balance_amount > 0
        ORDER BY bi.service_date DESC
    ");
    $stmt->execute([$patient_id]);
    $billing_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $outstanding_balances = array_merge($outstanding_balances, $billing_balances);

    // 4. Bed Charges from Active Admissions
    $stmt = $pdo->prepare("
        SELECT 
            'admission' as service_type,
            CONCAT('Bed Charges - ', w.ward_name, ' (Bed: ', b.bed_number, ') - ', 
                   DATEDIFF(CURDATE(), ba.admission_date), ' days') as description,
            DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day as total_amount,
            0 as paid_amount,
            DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day as balance,
            ba.id as reference_id,
            ba.admission_date as service_date
        FROM bed_assignments ba
        INNER JOIN beds b ON ba.bed_id = b.id
        INNER JOIN ward w ON b.ward_id = w.id
        WHERE ba.patient_id = ?
        AND ba.status = 'active'
        AND DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day > 0
        ORDER BY ba.admission_date DESC
    ");
    $stmt->execute([$patient_id]);
    $admission_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $outstanding_balances = array_merge($outstanding_balances, $admission_balances);

    // 5. Pharmacy Bills
    mysqli_select_db($link, "hospital");
    
    // Check if paid_amount column exists
    $check_column_query = "SHOW COLUMNS FROM pharmacy_billing LIKE 'paid_amount'";
    $column_result = mysqli_query($link, $check_column_query);
    $has_paid_amount = mysqli_num_rows($column_result) > 0;

    if ($has_paid_amount) {
        $pharmacy_query = "
            SELECT 
                'pharmacy' as service_type,
                CONCAT('Pharmacy Bill #', pb.bill_no, ' - ', DATE_FORMAT(pb.date, '%M %d, %Y')) as description,
                pb.total_amount,
                COALESCE(pb.paid_amount, 0) as paid_amount,
                (pb.total_amount - COALESCE(pb.paid_amount, 0)) as balance,
                pb.id as reference_id,
                pb.date as service_date
            FROM pharmacy_billing pb
            WHERE pb.patient_id = '$patient_id'
            AND pb.payment_status IN ('pending', 'partial')
            AND (pb.total_amount - COALESCE(pb.paid_amount, 0)) > 0
            ORDER BY pb.date DESC
        ";
        
        $pharmacy_result = mysqli_query($link, $pharmacy_query);
        if ($pharmacy_result) {
            while ($row = mysqli_fetch_assoc($pharmacy_result)) {
                if ($row['balance'] > 0) {
                    $outstanding_balances[] = $row;
                }
            }
        }
    }

    // Calculate total balance
    $total_balance = 0;
    foreach ($outstanding_balances as $balance) {
        $total_balance += floatval($balance['balance']);
    }

    // Format the response
    $response = [
        'patient' => $patient,
        'balances' => $outstanding_balances,
        'total_balance' => $total_balance
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error fetching patient balances: ' . $e->getMessage()]);
}
?>