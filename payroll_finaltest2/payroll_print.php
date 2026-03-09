<?php
/**
 * Payroll System - General Payroll Print
 * Format matches official Oracle dot-matrix voucher photo exactly
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Print Payroll';

$deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$month  = isset($_GET['month'])         ? sanitize($_GET['month'])      : date('F');
$year   = isset($_GET['year'])          ? (int)$_GET['year']            : date('Y');

if ($deptId <= 0) { header('Location: payroll.php'); exit; }

$dept = $conn->query("SELECT * FROM departments WHERE id = $deptId")->fetch_assoc();
if (!$dept) { header('Location: payroll.php'); exit; }

// Fetch per-period records
$payrollRecords = $conn->query("
    SELECT
        e.id            AS emp_id,
        e.employee_id   AS emp_number,
        e.first_name, e.last_name, e.middle_name,
        pos.position_title, pos.salary_grade,
        s.step_no, s.salary_rate,
        p.period_type,
        p.basic_salary,
        p.pera,
        p.gross_pay,
        p.wtax,
        p.philhealth,
        p.gsis,
        p.pagibig,
        p.provident,
        p.bcgeu,
        p.nocgem,
        p.bacgem,
        p.other_deductions,
        p.total_deductions,
        p.net_pay
    FROM payroll p
    LEFT JOIN employees e   ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN salary    s   ON p.salary_id   = s.salary_id
    WHERE p.department_id = $deptId
      AND p.payroll_month = '$month'
      AND p.payroll_year  = $year
      AND p.status        = 'Paid'
    ORDER BY e.last_name, e.first_name, p.period_type
");

// Group by employee: combine 1-15 and 16-31, split net pays
$empMap = [];
if ($payrollRecords && $payrollRecords->num_rows > 0) {
    while ($r = $payrollRecords->fetch_assoc()) {
        $eid = $r['emp_id'];
        if (!isset($empMap[$eid])) {
            $empMap[$eid] = [
                'emp_number'       => $r['emp_number'],
                'last_name'        => $r['last_name'],
                'first_name'       => $r['first_name'],
                'middle_name'      => $r['middle_name'],
                'position_title'   => $r['position_title'],
                'salary_grade'     => $r['salary_grade'],
                'salary_rate'      => $r['salary_rate'],
                'basic_salary'     => 0,
                'pera'             => 0,
                'gross_pay'        => 0,
                'wtax'             => 0,
                'philhealth'       => 0,
                'gsis'             => 0,
                'pagibig'          => 0,
                'provident'        => 0,
                'bcgeu'            => 0,
                'nocgem'           => 0,
                'bacgem'           => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_1_15'         => 0,  // always initialized to 0
                'net_16_31'        => 0,  // always initialized to 0
            ];
        }
        $empMap[$eid]['basic_salary']     += $r['basic_salary'];
        $empMap[$eid]['pera']             += $r['pera'];
        $empMap[$eid]['gross_pay']        += $r['gross_pay'];
        $empMap[$eid]['wtax']             += $r['wtax'];
        $empMap[$eid]['philhealth']       += $r['philhealth'];
        $empMap[$eid]['gsis']             += $r['gsis'];
        $empMap[$eid]['pagibig']          += $r['pagibig'];
        $empMap[$eid]['provident']        += $r['provident'];
        $empMap[$eid]['bcgeu']            += $r['bcgeu'];
        $empMap[$eid]['nocgem']           += $r['nocgem'];
        $empMap[$eid]['bacgem']           += $r['bacgem'];
        $empMap[$eid]['other_deductions'] += $r['other_deductions'];
        $empMap[$eid]['total_deductions'] += $r['total_deductions'];
        $netPay = (float)($r['net_pay'] ?? 0);
        if ($r['period_type'] === '1-15') {
            $empMap[$eid]['net_1_15']  += $netPay;
        } elseif ($r['period_type'] === '16-31') {
            $empMap[$eid]['net_16_31'] += $netPay;
        } else {
            // full_month (or any other period): split equally into both halves
            $empMap[$eid]['net_1_15']  += $netPay / 2;
            $empMap[$eid]['net_16_31'] += $netPay / 2;
        }
    }
}

$rows           = array_values($empMap);
$hasPaidRecords = count($rows) > 0;
$rowsPerSheet   = 14;
$totalSheets    = $hasPaidRecords ? (int)ceil(count($rows) / $rowsPerSheet) : 1;
$printDate      = date('m/d/Y') . ' ' . date('H:i:s');
$periodLabel    = strtoupper($month) . ' ' . $year;

function fmt($v) { return number_format((float)$v, 2); }
?>
<link rel="stylesheet" href="css/payroll_print.css">
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>General Payroll – <?php echo htmlspecialchars($dept['department_name']); ?></title></head>
<body>

<div class="print-controls">
    <button class="btn-print" onclick="window.print()" <?php echo !$hasPaidRecords ? 'disabled' : ''; ?>>
        &#128438; Print Payroll
    </button>
    <a class="btn-back" href="payroll.php?department_id=<?php echo $deptId; ?>&month=<?php echo urlencode($month); ?>&year=<?php echo $year; ?>">
        &larr; Back
    </a>
</div>

<?php if (!$hasPaidRecords): ?>
<div class="sheet">
    <div class="no-records">
        <p>&#128274; No <strong>Paid</strong> payroll records for <strong><?php echo "$month $year"; ?></strong>.</p>
        <p style="margin-top:10px;font-size:9pt;">Only <em>Paid</em> records can be printed.</p>
    </div>
</div>
<?php else:

    // Grand total accumulators
    $GT = ['monthly_rate'=>0,'pera'=>0,'gross'=>0,'wtax'=>0,'philhealth'=>0,
           'gsis'=>0,'pagibig'=>0,'provident'=>0,'bacgem'=>0,'nocgem_bcgeu'=>0,
           'other'=>0,'total_ded'=>0,'net_1_15'=>0,'net_16_31'=>0,'net_whole'=>0];

    $sheetChunks = array_chunk($rows, $rowsPerSheet);
    foreach ($sheetChunks as $si => $chunk):
        $sn  = $si + 1;
        $lbl = str_pad($sn,3,'0',STR_PAD_LEFT).' of '.str_pad($totalSheets,3,'0',STR_PAD_LEFT);

        // Sheet total accumulators
        $ST = ['monthly_rate'=>0,'pera'=>0,'gross'=>0,'wtax'=>0,'philhealth'=>0,
               'gsis'=>0,'pagibig'=>0,'provident'=>0,'bacgem'=>0,'nocgem_bcgeu'=>0,
               'other'=>0,'total_ded'=>0,'net_1_15'=>0,'net_16_31'=>0,'net_whole'=>0];
?>
<div class="sheet">

    <div class="sys-line">
        <span>MTCS control: vpayroll.rdf, July 3, 2003</span>
        <span>Sheet No. : <?php echo $lbl; ?></span>
    </div>

    <div class="header-block">
        <div style="font-size:5.8pt;line-height:1.65;min-width:100px;">
            Printdate: <?php echo $printDate; ?>
        </div>
        <div class="header-center">
            <div class="main-title">GENERAL &nbsp; PAYROLL</div>
            <div class="sub-title">Management Information Technology and Computer Services</div>
            <div class="dept-line"><?php echo htmlspecialchars($dept['department_name']); ?></div>
            <div class="period-for">FOR THE MONTH OF <?php echo $periodLabel; ?></div>
        </div>
        <div class="header-right">Period</div>
    </div>

    <div class="ack">We acknowledge receipt of the sum shown opposite our names as full compensation for services rendered for the period stated.</div>

    <table class="pv">
        <thead>
            <tr>
                <th style="width:22px">NO.</th>
                <th style="width:128px">EMPLOYEE NAME</th>
                <th style="width:62px">POSITION</th>
                <th style="width:58px">MONTHLY<br>RATE</th>
                <th style="width:48px">PERA</th>
                <th style="width:60px">GROSS<br>PAY</th>
                <th style="width:44px">WTAX</th>
                <th style="width:54px">PHILHEALTH</th>
                <th style="width:56px">GSIS<br>CONSOLIDATED</th>
                <th style="width:52px">PAGIBIG<br>CONSOLIDATED</th>
                <th style="width:52px">PROVIDENT<br>CONSOLIDATED</th>
                <th style="width:52px">BACGEM<br>CONSOLIDATED</th>
                <th style="width:56px">NOCGEM/<br>BCGEU<br>CONSOLIDATED</th>
                <th style="width:52px">OTHER<br>DEDUCTIONS</th>
                <th style="width:60px">TOTAL<br>DEDUCTIONS<br>CONSOLIDATED</th>
                <th style="width:54px">NET PAY<br>1-15</th>
                <th style="width:54px">NET PAY<br>16-31</th>
                <th class="wm-col" style="width:64px">WHOLE<br>MONTH<br>NET PAY</th>
                <th style="width:22px">NO.</th>
            </tr>
        </thead>
        <tbody>
<?php
        $rowNo = ($si * $rowsPerSheet) + 1;
        foreach ($chunk as $row):
            $name = strtoupper($row['last_name'] . ', ' . $row['first_name']);
            if ($row['middle_name']) {
                $name .= ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
            }

            $monthlyRate  = $row['salary_rate'] ? (float)$row['salary_rate'] : (float)$row['basic_salary'];
            $pera         = (float)$row['pera'];
            $gross        = (float)$row['gross_pay'];
            $wtax         = (float)$row['wtax'];
            $philhealth   = (float)$row['philhealth'];
            $gsis         = (float)$row['gsis'];
            $pagibig      = (float)$row['pagibig'];
            $provident    = (float)$row['provident'];
            $bacgem       = (float)$row['bacgem'];
            $nocgemBcgeu  = (float)($row['nocgem'] + $row['bcgeu']);
            $other        = (float)$row['other_deductions'];
            $totalDed     = (float)$row['total_deductions'];
            $net1         = (float)($row['net_1_15']  ?? 0);   // 0 if no 1-15 record
            $net2         = (float)($row['net_16_31'] ?? 0);   // 0 if no 16-31 record
            $netWhole     = $net1 + $net2;
            $rowNum       = $rowNo++;

            $ST['monthly_rate']  += $monthlyRate;  $GT['monthly_rate']  += $monthlyRate;
            $ST['pera']          += $pera;          $GT['pera']          += $pera;
            $ST['gross']         += $gross;         $GT['gross']         += $gross;
            $ST['wtax']          += $wtax;          $GT['wtax']          += $wtax;
            $ST['philhealth']    += $philhealth;    $GT['philhealth']    += $philhealth;
            $ST['gsis']          += $gsis;          $GT['gsis']          += $gsis;
            $ST['pagibig']       += $pagibig;       $GT['pagibig']       += $pagibig;
            $ST['provident']     += $provident;     $GT['provident']     += $provident;
            $ST['bacgem']        += $bacgem;        $GT['bacgem']        += $bacgem;
            $ST['nocgem_bcgeu']  += $nocgemBcgeu;   $GT['nocgem_bcgeu']  += $nocgemBcgeu;
            $ST['other']         += $other;         $GT['other']         += $other;
            $ST['total_ded']     += $totalDed;      $GT['total_ded']     += $totalDed;
            $ST['net_1_15']      += $net1;          $GT['net_1_15']      += $net1;
            $ST['net_16_31']     += $net2;          $GT['net_16_31']     += $net2;
            $ST['net_whole']     += $netWhole;      $GT['net_whole']     += $netWhole;
?>
            <tr>
                <td><?php echo $rowNum; ?></td>
                <td class="name-col"><?php echo htmlspecialchars($name); ?></td>
                <td class="pos-col"><?php echo htmlspecialchars($row['position_title'] ?? ''); ?></td>
                <td class="R"><?php echo fmt($monthlyRate); ?></td>
                <td class="R"><?php echo fmt($pera); ?></td>
                <td class="R"><?php echo fmt($gross); ?></td>
                <td class="R"><?php echo fmt($wtax); ?></td>
                <td class="R"><?php echo fmt($philhealth); ?></td>
                <td class="R"><?php echo fmt($gsis); ?></td>
                <td class="R"><?php echo fmt($pagibig); ?></td>
                <td class="R"><?php echo fmt($provident); ?></td>
                <td class="R"><?php echo fmt($bacgem); ?></td>
                <td class="R"><?php echo fmt($nocgemBcgeu); ?></td>
                <td class="R"><?php echo fmt($other); ?></td>
                <td class="R"><strong><?php echo fmt($totalDed); ?></strong></td>
                <td class="R"><strong><?php echo fmt($net1); ?></strong></td>
                <td class="R"><strong><?php echo fmt($net2); ?></strong></td>
                <td class="R wm-col"><?php echo fmt($netWhole); ?></td>
                <td><?php echo $rowNum; ?></td>
            </tr>
<?php endforeach; ?>

            <!-- Sheet Totals -->
            <tr class="tot-row">
                <td colspan="3" class="R">TOTALS</td>
                <td class="R"><?php echo fmt($ST['monthly_rate']); ?></td>
                <td class="R"><?php echo fmt($ST['pera']); ?></td>
                <td class="R"><?php echo fmt($ST['gross']); ?></td>
                <td class="R"><?php echo fmt($ST['wtax']); ?></td>
                <td class="R"><?php echo fmt($ST['philhealth']); ?></td>
                <td class="R"><?php echo fmt($ST['gsis']); ?></td>
                <td class="R"><?php echo fmt($ST['pagibig']); ?></td>
                <td class="R"><?php echo fmt($ST['provident']); ?></td>
                <td class="R"><?php echo fmt($ST['bacgem']); ?></td>
                <td class="R"><?php echo fmt($ST['nocgem_bcgeu']); ?></td>
                <td class="R"><?php echo fmt($ST['other']); ?></td>
                <td class="R"><?php echo fmt($ST['total_ded']); ?></td>
                <td class="R"><?php echo fmt($ST['net_1_15']); ?></td>
                <td class="R"><?php echo fmt($ST['net_16_31']); ?></td>
                <td class="R wm-col"><?php echo fmt($ST['net_whole']); ?></td>
                <td></td>
            </tr>

            <?php if ($sn === $totalSheets && $totalSheets > 1): ?>
            <!-- Grand Totals (last sheet only) -->
            <tr class="grand-row">
                <td colspan="3" class="R">GRAND TOTALS</td>
                <td class="R"><?php echo fmt($GT['monthly_rate']); ?></td>
                <td class="R"><?php echo fmt($GT['pera']); ?></td>
                <td class="R"><?php echo fmt($GT['gross']); ?></td>
                <td class="R"><?php echo fmt($GT['wtax']); ?></td>
                <td class="R"><?php echo fmt($GT['philhealth']); ?></td>
                <td class="R"><?php echo fmt($GT['gsis']); ?></td>
                <td class="R"><?php echo fmt($GT['pagibig']); ?></td>
                <td class="R"><?php echo fmt($GT['provident']); ?></td>
                <td class="R"><?php echo fmt($GT['bacgem']); ?></td>
                <td class="R"><?php echo fmt($GT['nocgem_bcgeu']); ?></td>
                <td class="R"><?php echo fmt($GT['other']); ?></td>
                <td class="R"><?php echo fmt($GT['total_ded']); ?></td>
                <td class="R"><?php echo fmt($GT['net_1_15']); ?></td>
                <td class="R"><?php echo fmt($GT['net_16_31']); ?></td>
                <td class="R wm-col"><?php echo fmt($GT['net_whole']); ?></td>
                <td></td>
            </tr>
            <?php endif; ?>

        </tbody>
    </table>

    <!-- Signature block -->
    <div class="sig-block">
        <div class="sig-item">
            <div>Prepared by:</div>
            <div class="sig-line">________________________________</div>
            <div style="font-size:5pt;margin-top:1px;">Signature over Printed Name / Date</div>
        </div>
        <div class="sig-item">
            <div>Certified Correct:</div>
            <div class="sig-line">________________________________</div>
            <div style="font-size:5pt;margin-top:1px;">Department Head / Date</div>
        </div>
        <div class="sig-item">
            <div>Approved for Payment:</div>
            <div class="sig-line">________________________________</div>
            <div style="font-size:5pt;margin-top:1px;">Approving Authority / Date</div>
        </div>
        <div class="sig-item">
            <div>Received Payment:</div>
            <div class="sig-line">________________________________</div>
            <div style="font-size:5pt;margin-top:1px;">Cashier / Date</div>
        </div>
    </div>

    <!-- Sheet footer -->
    <div class="sheet-footer">
        <span>Printed: <?php echo date('m/d/Y h:i A'); ?></span>
        <span><?php echo htmlspecialchars($dept['department_name']); ?> &mdash; <?php echo $periodLabel; ?></span>
        <span>Sheet No.: <?php echo $lbl; ?></span>
    </div>

</div><!-- /.sheet -->
<?php
    endforeach;
endif;
?>
</body>
</html>