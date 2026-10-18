<?php
session_start();
// if (!isset($_SESSION['username'])) {
//     header("Location: index.php");
//     exit();
// }

include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get summary data from the view
$summary_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT patient_id) as total_patients,
        SUM(total_outstanding) as total_outstanding,
        SUM(total_items) as total_records
    FROM vw_patient_outstanding_balances
");
$summary_stmt->execute();
$summary = $summary_stmt->fetch();

// Get patient outstanding balances
$patients_stmt = $pdo->prepare("
    SELECT 
        patient_id,
        patient_number,
        patient_name,
        phone,
        email,
        consultation_outstanding,
        consultation_count,
        lab_outstanding,
        lab_count,
        billing_outstanding,
        billing_count,
        admission_outstanding,
        admission_count,
        pharmacy_outstanding,
        pharmacy_count,
        total_outstanding,
        total_items,
        last_service_date
    FROM vw_patient_outstanding_balances
    WHERE total_outstanding > 0
    ORDER BY total_outstanding DESC
");
$patients_stmt->execute();
$patients_data = $patients_stmt->fetchAll();

// Process the data for display
$patients_summary = [];
foreach ($patients_data as $patient) {
    $patients_summary[$patient['patient_id']] = [
        'patient_info' => [
            'patient_id' => $patient['patient_id'],
            'patient_number' => $patient['patient_number'],
            'patient_name' => $patient['patient_name'],
            'phone' => $patient['phone'],
            'email' => $patient['email']
        ],
        'items' => [],
        'total_balance' => $patient['total_outstanding'],
        'item_count' => $patient['total_items']
    ];
    
    // Create item entries for each service type with outstanding balance
    $service_types = [
        'consultation' => [
            'balance' => $patient['consultation_outstanding'],
            'count' => $patient['consultation_count'],
            'description' => 'Consultation Fees'
        ],
        'lab' => [
            'balance' => $patient['lab_outstanding'],
            'count' => $patient['lab_count'],
            'description' => 'Laboratory Tests'
        ],
        'billing' => [
            'balance' => $patient['billing_outstanding'],
            'count' => $patient['billing_count'],
            'description' => 'Hospital Bills'
        ],
        'admission' => [
            'balance' => $patient['admission_outstanding'],
            'count' => $patient['admission_count'],
            'description' => 'Admission/Bed Charges'
        ],
        'pharmacy' => [
            'balance' => $patient['pharmacy_outstanding'],
            'count' => $patient['pharmacy_count'],
            'description' => 'Pharmacy Bills'
        ]
    ];
    
    foreach ($service_types as $type => $data) {
        if ($data['balance'] > 0) {
            for ($i = 0; $i < $data['count']; $i++) {
                $patients_summary[$patient['patient_id']]['items'][] = [
                    'service_type' => $type,
                    'description' => $data['description'],
                    'balance' => $data['balance'] / max($data['count'], 1), // Distribute balance evenly
                    'service_date' => $patient['last_service_date'],
                    'reference_id' => null, // We don't have individual item IDs in this view
                    'paid_amount' => 0, // We don't have this info in the summary view
                    'total_amount' => $data['balance'] / max($data['count'], 1), // Estimate
                    'status' => 'pending'
                ];
            }
        }
    }
}

// Calculate totals
$total_outstanding = $summary['total_outstanding'] ?? 0;
$total_patients = $summary['total_patients'] ?? 0;
$total_items = $summary['total_records'] ?? 0;

// Alternative approach: If you need individual item details, you'll need to query the individual tables
// Here's an example of how you could get detailed items:
function getOutstandingItemsDetails($pdo) {
    $items = [];
    
    // 1. Get outstanding consultations
    $consultation_stmt = $pdo->prepare("
        SELECT 
            a.id as reference_id,
            a.patient_id,
            p.patient_id as patient_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            'consultation' as service_type,
            CONCAT('Consultation on ', DATE_FORMAT(a.appointment_date, '%Y-%m-%d')) as description,
            a.consultation_fee as total_amount,
            (SELECT COALESCE(SUM(amount), 0) FROM payments 
             WHERE service_type = 'consultation' AND reference_id = a.id) as paid_amount,
            a.consultation_fee - COALESCE((SELECT SUM(amount) FROM payments 
                                          WHERE service_type = 'consultation' AND reference_id = a.id), 0) as balance,
            a.appointment_date as service_date,
            a.payment_status as status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.status = 'completed' 
          AND a.consultation_fee > 0
          AND a.payment_status IN ('pending', 'partial')
          AND a.consultation_fee > COALESCE((SELECT SUM(amount) FROM payments 
                                            WHERE service_type = 'consultation' AND reference_id = a.id), 0)
    ");
    $consultation_stmt->execute();
    $consultations = $consultation_stmt->fetchAll();
    
    // 2. Get outstanding lab orders
    $lab_stmt = $pdo->prepare("
        SELECT 
            lo.id as reference_id,
            lo.patient_id,
            p.patient_id as patient_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            'lab' as service_type,
            CONCAT('Lab Order #', lo.id, ' - ', lo.test_type) as description,
            lo.total_amount as total_amount,
            COALESCE(lo.paid_amount, 0) as paid_amount,
            lo.total_amount - COALESCE(lo.paid_amount, 0) as balance,
            lo.order_date as service_date,
            lo.payment_status as status
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        WHERE lo.total_amount > 0
          AND lo.payment_status IN ('pending', 'partial')
          AND lo.total_amount > COALESCE(lo.paid_amount, 0)
    ");
    $lab_stmt->execute();
    $labs = $lab_stmt->fetchAll();
    
    // 3. Get outstanding billing (excluding consultation bills)
    $billing_stmt = $pdo->prepare("
        SELECT 
            b.id as reference_id,
            b.patient_id,
            p.patient_id as patient_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            'billing' as service_type,
            CONCAT('Hospital Bill #', b.bill_number, ' - ', b.description) as description,
            b.total_amount as total_amount,
            b.paid_amount as paid_amount,
            b.balance_amount as balance,
            b.bill_date as service_date,
            b.status
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        WHERE b.total_amount > 0
          AND b.balance_amount > 0
          AND b.status IN ('pending', 'partial')
          AND NOT EXISTS (
              SELECT 1 FROM bill_items bi 
              WHERE bi.bill_id = b.id AND bi.item_type = 'consultation'
          )
    ");
    $billing_stmt->execute();
    $billings = $billing_stmt->fetchAll();
    
    // 4. Get outstanding admissions/bed charges
    $admission_stmt = $pdo->prepare("
        SELECT 
            ba.id as reference_id,
            ba.patient_id,
            p.patient_id as patient_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            'admission' as service_type,
            CONCAT('Bed Charge - Bed #', bd.bed_number, ' (', 
                   CASE 
                     WHEN ba.status = 'active' THEN 'Active admission since '
                     ELSE 'Discharged on '
                   END,
                   DATE_FORMAT(ba.admission_date, '%Y-%m-%d'), ')') as description,
            CASE 
              WHEN ba.status = 'active' AND ba.actual_discharge_date IS NULL THEN
                (DATEDIFF(CURDATE(), ba.admission_date) + 1) * bd.price_per_day
              WHEN ba.status = 'discharged' AND ba.actual_discharge_date IS NOT NULL THEN
                (DATEDIFF(ba.actual_discharge_date, ba.admission_date) + 1) * bd.price_per_day
              ELSE 0
            END as total_amount,
            0 as paid_amount,  -- This needs to be calculated from payments table
            CASE 
              WHEN ba.status = 'active' AND ba.actual_discharge_date IS NULL THEN
                (DATEDIFF(CURDATE(), ba.admission_date) + 1) * bd.price_per_day
              WHEN ba.status = 'discharged' AND ba.actual_discharge_date IS NOT NULL THEN
                (DATEDIFF(ba.actual_discharge_date, ba.admission_date) + 1) * bd.price_per_day
              ELSE 0
            END as balance,  -- This needs to subtract any payments made
            ba.admission_date as service_date,
            ba.payment_status as status
        FROM bed_assignments ba
        JOIN beds bd ON ba.bed_id = bd.id
        JOIN patients p ON ba.patient_id = p.id
        WHERE ba.payment_status IN ('pending', 'partial')
          AND (ba.status = 'active' OR ba.status = 'discharged')
    ");
    $admission_stmt->execute();
    $admissions = $admission_stmt->fetchAll();
    
    // 5. Get outstanding pharmacy bills
    $pharmacy_stmt = $pdo->prepare("
        SELECT 
            pb.id as reference_id,
            pb.patient_id,
            p.patient_id as patient_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            'pharmacy' as service_type,
            CONCAT('Pharmacy Bill #', pb.bill_number) as description,
            pb.total_amount as total_amount,
            pb.paid_amount as paid_amount,
            pb.balance_amount as balance,
            pb.date as service_date,
            pb.payment_status as status
        FROM pharmacy_billing pb
        JOIN patients p ON pb.patient_id = p.id
        WHERE pb.total_amount > 0
          AND pb.balance_amount > 0
          AND pb.payment_status IN ('pending', 'partial')
    ");
    $pharmacy_stmt->execute();
    $pharmacies = $pharmacy_stmt->fetchAll();
    
    // Combine all items
    $items = array_merge($consultations, $labs, $billings, $admissions, $pharmacies);
    
    return $items;
}

// Uncomment the line below if you want to use detailed items instead of summary
// $outstanding_details = getOutstandingItemsDetails($pdo);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <style>
        :root {
            --primary: #0891b2;
            --primary-light: #06b6d4;
            --primary-dark: #0e7490;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #134e4a;
            --light: #f0fdfa;
            --gray: #64748b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
        }
        
        .main-content {
            margin-left: 300px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header p {
            color: var(--gray);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .patient-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(6, 182, 212, 0.1);
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .patient-header {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .patient-info h3 {
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .patient-info p {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .patient-actions {
            text-align: right;
        }
        
        .patient-items {
            padding: 0;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 120px;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-header {
            background: #f8fafc;
            font-weight: 600;
            color: var(--gray);
            font-size: 0.875rem;
            text-transform: uppercase;
        }
        
        .service-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .badge-consultation { background: #dbeafe; color: #0369a1; }
        .badge-lab { background: #fef3c7; color: #b45309; }
        .badge-billing { background: #dcfce7; color: #15803d; }
        .badge-admission { background: #f3e8ff; color: #7c3aed; }
        .badge-pharmacy { background: #fce7f3; color: #be185d; }
        
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }
        
        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.8125rem;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #15803d;
            border-color: #bbf7d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        
        .amount {
            font-weight: 600;
            color: var(--dark);
        }
        
        .balance-amount {
            color: var(--danger);
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--success);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--gray);
        }
        
        .item-description {
            color: var(--dark);
            font-weight: 500;
        }
        
        .item-status {
            color: var(--gray);
            font-size: 0.8125rem;
            margin-top: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { background: #fef3c7; color: #b45309; }
        .status-partial { background: #dbeafe; color: #0369a1; }
        .status-paid { background: #dcfce7; color: #15803d; }
        
        /* Enhanced Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-card h3 {
                font-size: 1.5rem;
            }
            
            .patient-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }
            
            .patient-actions {
                width: 100%;
                text-align: left;
                margin-top: 1rem;
            }
            
            .item-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                padding: 0.75rem 1rem;
            }
            
            .item-header {
                display: none;
            }
            
            .patient-card {
                margin-bottom: 1rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8125rem;
            }
            
            .alert {
                padding: 0.75rem 1rem;
                margin-bottom: 1rem;
            }
            
            .empty-state {
                padding: 2rem 1rem;
            }
            
            .empty-state i {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1><i class="fas fa-credit-card"></i> Pending Payments Management</h1>
            <p>Track and manage all outstanding payments across all services</p>
        </div>
        
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo number_format($total_patients); ?></h3>
                <p>Patients with Outstanding Dues</p>
            </div>
            <div class="stat-card">
                <h3>Ksh <?php echo number_format($total_outstanding, 2); ?></h3>
                <p>Total Outstanding Amount</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_items); ?></h3>
                <p>Total Outstanding Items</p>
            </div>
        </div>
        
        <?php if (count($patients_summary) > 0): ?>
            <?php foreach ($patients_summary as $patient_id => $patient): ?>
            <div class="patient-card">
                <div class="patient-header">
                    <div class="patient-info">
                        <h3><?php echo htmlspecialchars($patient['patient_info']['patient_name']); ?></h3>
                        <p>
                            Patient #<?php echo htmlspecialchars($patient['patient_info']['patient_number']); ?>
                            | Tel: <?php echo htmlspecialchars($patient['patient_info']['phone']); ?>
                            - <?php echo $patient['item_count']; ?> outstanding item(s)
                        </p>
                    </div>
                    <div class="patient-actions">
                        <h4 class="balance-amount" style="margin-bottom: 0.75rem;">
                            Total Due: Ksh <?php echo number_format($patient['total_balance'], 2); ?>
                        </h4>
                        <a href="unified_payment.php?patient_id=<?php echo $patient['patient_info']['patient_id']; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                    </div>
                </div>
                
                <?php if (count($patient['items']) > 0): ?>
                <div class="patient-items">
                    <div class="item-row item-header">
                        <div>Service Description</div>
                        <div>Service Type</div>
                        <div>Date</div>
                        <div>Amount Due</div>
                        <div>Action</div>
                    </div>
                    
                    <?php foreach ($patient['items'] as $index => $item): 
                        $service_names = [
                            'consultation' => 'Consultation',
                            'lab' => 'Lab Test',
                            'billing' => 'Hospital Bill',
                            'admission' => 'Bed Charges',
                            'pharmacy' => 'Pharmacy'
                        ];
                    ?>
                    <div class="item-row">
                        <div>
                            <div class="item-description">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </div>
                            <div class="item-status">
                                <span class="status-badge status-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                                <?php if ($item['paid_amount'] > 0): ?>
                                    | Paid: Ksh <?php echo number_format($item['paid_amount'], 2); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="service-badge badge-<?php echo $item['service_type']; ?>">
                                <?php echo $service_names[$item['service_type']]; ?>
                            </span>
                        </div>
                        <div>
                            <?php echo date('M d, Y', strtotime($item['service_date'])); ?>
                        </div>
                        <div class="amount balance-amount">
                            Ksh <?php echo number_format($item['balance'], 2); ?>
                        </div>
                        <div>
                            <a href="unified_payment.php?patient_id=<?php echo $patient['patient_info']['patient_id']; ?>&service_type=<?php echo $item['service_type']; ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-dollar-sign"></i> Pay
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Outstanding Payments</h3>
                <p>All payments are up to date. There are no pending bills.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>