<?php
session_start();
require 'vendor/autoload.php'; // PHPSpreadsheet installed using composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

include("config/database.php");

// Get filter parameters
$reportType = $_GET['report'] ?? 'financial';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$supplierId = $_GET['supplier_id'] ?? '';

// Create new blank Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("Equity Afia Hospital Management System")
    ->setTitle("Report - " . ucfirst($reportType))
    ->setSubject("Clinic Report")
    ->setDescription("Generated report from Equity Afia Hospital Management System");

// Header styling function
function styleHeader($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '0891b2']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
}

// Title styling
function styleTitle($sheet, $cell) {
    $sheet->getStyle($cell)->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => '0891b2']
        ]
    ]);
}

$row = 1;

// Add title and date range
$sheet->setCellValue('A' . $row, 'Equity Afia Hospital Management System');
styleTitle($sheet, 'A' . $row);
$row++;

$sheet->setCellValue('A' . $row, ucfirst($reportType) . ' Report');
$sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
$row++;

$sheet->setCellValue('A' . $row, 'Period: ' . date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate)));
$sheet->getStyle('A' . $row)->getFont()->setItalic(true);
$row++;
$row++; // Empty row

// Generate report based on type
if ($reportType == 'financial' || $reportType == 'revenue' || $reportType == 'expenses' || $reportType == 'profit') {
    
    // Calculate Total Revenue
    $revenueQuery = "
        SELECT 
            COALESCE(SUM(amount), 0) as total_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE payment_date BETWEEN ? AND ?
        AND status = 'completed'
    ";
    $stmt = $database->prepare($revenueQuery);
    $startDateTime = $startDate . ' 00:00:00';
    $endDateTime = $endDate . ' 23:59:59';
    $stmt->bind_param("ss", $startDateTime, $endDateTime);
    $stmt->execute();
    $revenueResult = $stmt->get_result()->fetch_assoc();
    $totalRevenue = $revenueResult['total_revenue'];
    $paymentCount = $revenueResult['payment_count'];

    // Calculate Revenue Breakdown
    $revenueBreakdownQuery = "
        SELECT 
            service_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as revenue
        FROM billing_payments 
        WHERE payment_date BETWEEN ? AND ?
        AND status = 'completed'
        GROUP BY service_type
        ORDER BY revenue DESC
    ";
    $stmt = $database->prepare($revenueBreakdownQuery);
    $stmt->bind_param("ss", $startDateTime, $endDateTime);
    $stmt->execute();
    $revenueBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate Total Expenses
    $expensesQuery = "
        SELECT 
            COALESCE(SUM(total), 0) as total_expenses,
            COUNT(*) as purchase_count
        FROM purchase_master 
        WHERE purchase_date BETWEEN ? AND ?
        AND status = 'received'
    ";
    $stmt = $database->prepare($expensesQuery);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $expensesResult = $stmt->get_result()->fetch_assoc();
    $totalExpenses = $expensesResult['total_expenses'];
    $purchaseCount = $expensesResult['purchase_count'];

    $netProfit = $totalRevenue - $totalExpenses;
    $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

    // Summary Section
    if ($reportType == 'financial' || $reportType == 'profit') {
        $sheet->setCellValue('A' . $row, 'Financial Summary');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(13);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Description');
        $sheet->setCellValue('B' . $row, 'Amount (Ksh)');
        $sheet->setCellValue('C' . $row, 'Total Transactions');
        styleHeader($sheet, 'A' . $row . ':C' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Revenue');
        $sheet->setCellValue('B' . $row, number_format($totalRevenue, 2));
        $sheet->setCellValue('C' . $row, $paymentCount);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Total Expenses');
        $sheet->setCellValue('B' . $row, number_format($totalExpenses, 2));
        $sheet->setCellValue('C' . $row, $purchaseCount);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Net Profit');
        $sheet->setCellValue('B' . $row, number_format($netProfit, 2));
        $sheet->setCellValue('C' . $row, number_format($profitMargin, 1) . '%');
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $row++;
        $row++;
    }

    // Revenue Breakdown
    if ($reportType == 'financial' || $reportType == 'revenue') {
        $serviceNames = [
            'consultation' => 'Consultation',
            'lab' => 'Laboratory Tests',
            'pharmacy' => 'Pharmacy Sales',
            'admission' => 'Admission Charges',
            'consolidated' => 'Consolidated Bills'
        ];

        $sheet->setCellValue('A' . $row, 'Revenue Breakdown by Service');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(13);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Service Type');
        $sheet->setCellValue('B' . $row, 'Transactions');
        $sheet->setCellValue('C' . $row, 'Revenue (Ksh)');
        $sheet->setCellValue('D' . $row, '% of Total');
        $sheet->setCellValue('E' . $row, 'Avg/Transaction');
        styleHeader($sheet, 'A' . $row . ':E' . $row);
        $row++;
        
        foreach ($revenueBreakdown as $service) {
            $serviceName = $serviceNames[$service['service_type']] ?? ucfirst($service['service_type']);
            $percentage = $totalRevenue > 0 ? ($service['revenue'] / $totalRevenue) * 100 : 0;
            $average = $service['transaction_count'] > 0 ? $service['revenue'] / $service['transaction_count'] : 0;
            
            $sheet->setCellValue('A' . $row, $serviceName);
            $sheet->setCellValue('B' . $row, $service['transaction_count']);
            $sheet->setCellValue('C' . $row, number_format($service['revenue'], 2));
            $sheet->setCellValue('D' . $row, number_format($percentage, 1) . '%');
            $sheet->setCellValue('E' . $row, number_format($average, 2));
            $row++;
        }
        
        // Total row
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->setCellValue('B' . $row, $paymentCount);
        $sheet->setCellValue('C' . $row, number_format($totalRevenue, 2));
        $sheet->setCellValue('D' . $row, '100%');
        $sheet->setCellValue('E' . $row, number_format($paymentCount > 0 ? $totalRevenue / $paymentCount : 0, 2));
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $row++;
        $row++;

        // Payment Method Breakdown
        $paymentMethodQuery = "
            SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(amount), 0) as revenue
            FROM billing_payments 
            WHERE payment_date BETWEEN ? AND ?
            AND status = 'completed'
            GROUP BY payment_method
            ORDER BY revenue DESC
        ";
        $stmt = $database->prepare($paymentMethodQuery);
        $stmt->bind_param("ss", $startDateTime, $endDateTime);
        $stmt->execute();
        $paymentMethods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($paymentMethods)) {
            $sheet->setCellValue('A' . $row, 'Payment Method Analysis');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(13);
            $row++;
            
            $sheet->setCellValue('A' . $row, 'Payment Method');
            $sheet->setCellValue('B' . $row, 'Transactions');
            $sheet->setCellValue('C' . $row, 'Revenue (Ksh)');
            $sheet->setCellValue('D' . $row, '% of Total');
            styleHeader($sheet, 'A' . $row . ':D' . $row);
            $row++;
            
            foreach ($paymentMethods as $method) {
                $methodName = ucfirst(str_replace('_', ' ', $method['payment_method']));
                $percentage = $totalRevenue > 0 ? ($method['revenue'] / $totalRevenue) * 100 : 0;
                
                $sheet->setCellValue('A' . $row, $methodName);
                $sheet->setCellValue('B' . $row, $method['transaction_count']);
                $sheet->setCellValue('C' . $row, number_format($method['revenue'], 2));
                $sheet->setCellValue('D' . $row, number_format($percentage, 1) . '%');
                $row++;
            }
            $row++;
        }
    }

    // Expense Details
    if ($reportType == 'financial' || $reportType == 'expenses') {
        $expensesDetailQuery = "
            SELECT 
                pm.purchase_date,
                pm.product_name,
                pm.company_name,
                pm.received_quantity,
                pm.price,
                pm.total,
                s.businessname as supplier_name
            FROM purchase_master pm
            LEFT JOIN suppliers s ON pm.party_name = s.businessname
            WHERE pm.purchase_date BETWEEN ? AND ?
            AND pm.status = 'received'
            ORDER BY pm.purchase_date DESC, pm.total DESC
        ";
        $stmt = $database->prepare($expensesDetailQuery);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $expensesDetail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $sheet->setCellValue('A' . $row, 'Expense Details');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(13);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Date');
        $sheet->setCellValue('B' . $row, 'Product');
        $sheet->setCellValue('C' . $row, 'Company');
        $sheet->setCellValue('D' . $row, 'Supplier');
        $sheet->setCellValue('E' . $row, 'Quantity');
        $sheet->setCellValue('F' . $row, 'Unit Price (Ksh)');
        $sheet->setCellValue('G' . $row, 'Total (Ksh)');
        styleHeader($sheet, 'A' . $row . ':G' . $row);
        $row++;
        
        foreach ($expensesDetail as $expense) {
            $sheet->setCellValue('A' . $row, date('M j, Y', strtotime($expense['purchase_date'])));
            $sheet->setCellValue('B' . $row, $expense['product_name']);
            $sheet->setCellValue('C' . $row, $expense['company_name']);
            $sheet->setCellValue('D' . $row, $expense['supplier_name'] ?? $expense['company_name']);
            $sheet->setCellValue('E' . $row, $expense['received_quantity']);
            $sheet->setCellValue('F' . $row, number_format($expense['price'], 2));
            $sheet->setCellValue('G' . $row, number_format($expense['total'], 2));
            $row++;
        }
    }
}

// Auto-size columns
foreach(range('A','G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add borders to all data
$highestRow = $sheet->getHighestRow();
$highestColumn = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ]
]);

// Set the filename
$filename = strtolower($reportType) . '_report_' . date('Y-m-d') . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); //tells the browser that the file is an xlsx file
header('Content-Disposition: attachment;filename="' . $filename . '"'); //tells the browser to download the file with the specified filename, not just display it
header('Cache-Control: max-age=0');//tells browser to redownload new file, not use old version

// Write file to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
?>