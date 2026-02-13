<?php
/**
 * Payroll System - Print Payroll (BCD City Hall Format)
 * Official General Payroll format for Bacolod City
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Print Payroll';

$deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$month = isset($_GET['month']) ? sanitize($_GET['month']) : date('F');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$period = isset($_GET['period']) ? sanitize($_GET['period']) : '1-15';

if ($deptId <= 0) {
    header('Location: payroll.php');
    exit;
}

// Get department info
$dept = $conn->query("SELECT * FROM departments WHERE id = $deptId")->fetch_assoc();

if (!$dept) {
    header('Location: payroll.php');
    exit;
}

// Get payroll records for this department and period
$payrollRecords = $conn->query("
    SELECT 
        p.*,
        e.employee_id as emp_number,
        e.first_name,
        e.last_name,
        e.middle_name,
        e.date_hired,
        pos.position_title,
        pos.salary_grade,
        s.step_no,
        s.salary_rate
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN salary s ON p.salary_id = s.salary_id
    WHERE p.department_id = $deptId 
      AND p.payroll_month = '$month' 
      AND p.payroll_year = $year
      AND p.period_type = '$period'
    ORDER BY e.last_name, e.first_name
");

// Calculate totals
$totals = [
    'basic_salary' => 0,
    'pera' => 0,
    'gross_pay' => 0,
    'wtax' => 0,
    'gsis' => 0,
    'philhealth' => 0,
    'pagibig' => 0,
    'total_deductions' => 0,
    'net_pay' => 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Payroll - <?php echo htmlspecialchars($dept['department_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
            background: #fff;
        }
        
        .print-container {
            width: 13in;
            padding: 0.3in;
            margin: 0 auto;
            background: #fff;
        }
        
        /* Header */
        .payroll-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .header-top {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 8px;
        }
        
        .logo {
            width: 70px;
            height: 70px;
        }
        
        .header-text {
            text-align: center;
        }
        
        .republic {
            font-size: 10pt;
            font-weight: normal;
        }
        
        .city-name {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .office-name {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 3px;
        }
        
        .payroll-title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin: 15px 0 10px;
        }
        
        .payroll-info {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            margin-top: 10px;
        }
        
        .payroll-info-item {
            display: flex;
            gap: 5px;
        }
        
        .payroll-info-label {
            font-weight: bold;
        }
        
        /* Table */
        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 20px;
        }
        
        .payroll-table th,
        .payroll-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            vertical-align: middle;
        }
        
        .payroll-table th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        
        .payroll-table th.rotate {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            height: 80px;
            padding: 5px 2px;
            font-size: 7pt;
        }
        
        .payroll-table td.name {
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .payroll-table td.position {
            text-align: left;
            font-size: 7.5pt;
        }
        
        .payroll-table td.currency {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 8pt;
        }
        
        .payroll-table tr.total-row {
            background: #e8e8e8;
            font-weight: bold;
        }
        
        .payroll-table tr.total-row td {
            border-top: 2px solid #000;
            padding: 6px;
        }
        
        .col-no { width: 25px; }
        .col-name { width: 150px; }
        .col-position { width: 120px; }
        .col-sg { width: 35px; }
        .col-step { width: 35px; }
        .col-money { width: 75px; }
        .col-deduction { width: 60px; }
        .col-signature { width: 100px; }
        
        /* Footer */
        .payroll-footer {
            margin-top: 20px;
            font-size: 9pt;
        }
        
        .certification {
            text-align: center;
            font-style: italic;
            margin-bottom: 30px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #fafafa;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 40px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
        }
        
        .signature-title {
            font-size: 8pt;
            color: #333;
        }
        
        /* Print styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .print-container {
                width: 100%;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            @page {
                size: legal landscape;
                margin: 0.3in;
            }
        }
        
        /* Screen controls */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .print-controls button,
        .print-controls a {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print {
            background: #2d6394;
            color: white;
        }
        
        .btn-print:hover {
            background: #1e4a70;
        }
        
        .btn-back {
            background: #6b7280;
            color: white;
        }
        
        .btn-back:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            Print Payroll
        </button>
        <a href="payroll.php?department_id=<?php echo $deptId; ?>" class="btn-back">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            Back
        </a>
    </div>

    <div class="print-container">
        <!-- Header -->
        <div class="payroll-header">
            <div class="header-top">
                <img src="assets/bcd.png" alt="BCD Logo" class="logo">
                <div class="header-text">
                    <div class="republic">Republic of the Philippines</div>
                    <div class="city-name">City of Bacolod</div>
                    <div class="office-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                </div>
                <img src="assets/bcd.png" alt="BCD Logo" class="logo">
            </div>
            
            <div class="payroll-title">GENERAL PAYROLL</div>
            
            <div class="payroll-info">
                <div class="payroll-info-item">
                    <span class="payroll-info-label">Period:</span>
                    <span><?php echo $month . ' ' . $period . ', ' . $year; ?></span>
                </div>
                <div class="payroll-info-item">
                    <span class="payroll-info-label">Department:</span>
                    <span><?php echo htmlspecialchars($dept['department_code']); ?></span>
                </div>
                <div class="payroll-info-item">
                    <span class="payroll-info-label">Fund:</span>
                    <span>General Fund</span>
                </div>
            </div>
        </div>
        
        <!-- Main Table -->
        <table class="payroll-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-no">No.</th>
                    <th rowspan="2" class="col-name">Name of Employee</th>
                    <th rowspan="2" class="col-position">Position/Designation</th>
                    <th rowspan="2" class="col-sg">SG</th>
                    <th rowspan="2" class="col-step">Step</th>
                    <th colspan="3">EARNINGS</th>
                    <th colspan="6">DEDUCTIONS</th>
                    <th rowspan="2" class="col-money">Net Amount</th>
                    <th rowspan="2" class="col-signature">Signature</th>
                </tr>
                <tr>
                    <th class="col-money">Basic Salary</th>
                    <th class="col-money">PERA</th>
                    <th class="col-money">Gross</th>
                    <th class="col-deduction">W-Tax</th>
                    <th class="col-deduction">GSIS</th>
                    <th class="col-deduction">PhilHealth</th>
                    <th class="col-deduction">Pag-IBIG</th>
                    <th class="col-deduction">Others</th>
                    <th class="col-money">Total Ded.</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rowNum = 1;
                if ($payrollRecords && $payrollRecords->num_rows > 0):
                    while($row = $payrollRecords->fetch_assoc()): 
                        $empName = strtoupper($row['last_name'] . ', ' . $row['first_name']);
                        if ($row['middle_name']) {
                            $empName .= ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
                        }
                        
                        // Get step from date_hired
                        $step = 1;
                        if ($row['date_hired']) {
                            $hireDate = new DateTime($row['date_hired']);
                            $today = new DateTime();
                            $years = $hireDate->diff($today)->y;
                            $step = min(8, floor($years / 3) + 1);
                        }
                        
                        // Use salary rate from salary table if available
                        $basicSalary = $row['salary_rate'] ? $row['salary_rate'] : $row['basic_salary'];
                        $grossPay = $basicSalary + $row['pera'];
                        
                        // Other deductions combined
                        $otherDed = $row['provident'] + $row['bcgeu'] + $row['nocgem'] + $row['bacgem'] + $row['other_deductions'];
                        
                        // Update totals
                        $totals['basic_salary'] += $basicSalary;
                        $totals['pera'] += $row['pera'];
                        $totals['gross_pay'] += $grossPay;
                        $totals['wtax'] += $row['wtax'];
                        $totals['gsis'] += $row['gsis'];
                        $totals['philhealth'] += $row['philhealth'];
                        $totals['pagibig'] += $row['pagibig'];
                        $totals['total_deductions'] += $row['total_deductions'];
                        $totals['net_pay'] += $row['net_pay'];
                ?>
                    <tr>
                        <td><?php echo $rowNum++; ?></td>
                        <td class="name"><?php echo htmlspecialchars($empName); ?></td>
                        <td class="position"><?php echo htmlspecialchars($row['position_title'] ?? 'N/A'); ?></td>
                        <td><?php echo $row['salary_grade'] ?? '-'; ?></td>
                        <td><?php echo $step; ?></td>
                        <td class="currency"><?php echo number_format($basicSalary, 2); ?></td>
                        <td class="currency"><?php echo number_format($row['pera'], 2); ?></td>
                        <td class="currency"><?php echo number_format($grossPay, 2); ?></td>
                        <td class="currency"><?php echo number_format($row['wtax'], 2); ?></td>
                        <td class="currency"><?php echo number_format($row['gsis'], 2); ?></td>
                        <td class="currency"><?php echo number_format($row['philhealth'], 2); ?></td>
                        <td class="currency"><?php echo number_format($row['pagibig'], 2); ?></td>
                        <td class="currency"><?php echo number_format($otherDed, 2); ?></td>
                        <td class="currency"><?php echo number_format($row['total_deductions'], 2); ?></td>
                        <td class="currency" style="font-weight: bold;"><?php echo number_format($row['net_pay'], 2); ?></td>
                        <td></td>
                    </tr>
                <?php 
                    endwhile;
                endif;
                ?>
                
                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="5" style="text-align: right; font-weight: bold;">TOTAL:</td>
                    <td class="currency"><?php echo number_format($totals['basic_salary'], 2); ?></td>
                    <td class="currency"><?php echo number_format($totals['pera'], 2); ?></td>
                    <td class="currency"><?php echo number_format($totals['gross_pay'], 2); ?></td>
                    <td class="currency"><?php echo number_format($totals['wtax'], 2); ?></td>
                    <td class="currency"><?php echo number_format($totals['gsis'], 2); ?></td>
                    <td class="currency"><?php echo number_format($totals['philhealth'], 2); ?></td>
                    <td class="currency"><?php echo number_format($totals['pagibig'], 2); ?></td>
                    <td class="currency">-</td>
                    <td class="currency"><?php echo number_format($totals['total_deductions'], 2); ?></td>
                    <td class="currency" style="font-size: 10pt;"><?php echo number_format($totals['net_pay'], 2); ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Footer -->
        <div class="payroll-footer">
            <div class="certification">
                We acknowledge receipt of the sum shown opposite our names as full compensation for services rendered for the period stated.
            </div>
            
            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line">
                        _______________________
                    </div>
                    <div class="signature-title">Prepared by</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        _______________________
                    </div>
                    <div class="signature-title">Checked by</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        _______________________
                    </div>
                    <div class="signature-title">Approved by</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">
                        _______________________
                    </div>
                    <div class="signature-title">City Accountant</div>
                </div>
            </div>
            
            <p style="text-align: center; margin-top: 30px; font-size: 8pt; color: #666;">
                Printed on: <?php echo date('F d, Y h:i A'); ?>
            </p>
        </div>
    </div>
</body>
</html>