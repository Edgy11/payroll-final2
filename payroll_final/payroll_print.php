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

// Fetch per-period records so we can split 1-15 and 16-31 net pays
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
        COALESCE(p.aca,0)       AS aca,
        p.gross_pay,
        p.wtax,
        p.philhealth,
        p.gsis,
        p.pagibig,
        COALESCE(p.bcc,0)       AS bcc,
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
                'emp_number'    => $r['emp_number'],
                'last_name'     => $r['last_name'],
                'first_name'    => $r['first_name'],
                'middle_name'   => $r['middle_name'],
                'position_title'=> $r['position_title'],
                'salary_grade'  => $r['salary_grade'],
                'salary_rate'   => $r['salary_rate'],
                'basic_salary'  => 0,
                'pera'          => 0,
                'gross_pay'     => 0,
                'wtax'          => 0,
                'philhealth'    => 0,
                'gsis'          => 0,
                'pagibig'       => 0,
                'provident'     => 0,
                'bcgeu'         => 0,
                'nocgem'        => 0,
                'bacgem'        => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_1_15'      => 0,
                'net_16_31'     => 0,
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
        if ($r['period_type'] === '1-15') {
            $empMap[$eid]['net_1_15']  += $r['net_pay'];
        } else {
            $empMap[$eid]['net_16_31'] += $r['net_pay'];
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>General Payroll – <?php echo htmlspecialchars($dept['department_name']); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Courier New',Courier,monospace;font-size:6.5pt;color:#000;background:#bbb}

.sheet{
    width:17in;background:#fff;
    margin:16px auto;
    padding:.22in .28in .18in;
    page-break-after:always;
}
.sheet:last-child{page-break-after:avoid}

.sys-line{
    display:flex;justify-content:space-between;
    font-size:5.8pt;margin-bottom:3px;
}

.header-block{
    display:flex;justify-content:space-between;align-items:flex-start;
    margin-bottom:2px;
}
.oracle-left{display:flex;align-items:flex-start;gap:7px;}
.oracle-box{
    font-family:'Arial Black',Arial,sans-serif;font-size:10pt;font-weight:900;
    letter-spacing:3px;color:#777;border:2px solid #999;padding:2px 6px;
    background:linear-gradient(135deg,#e5e5e5,#ccc);
    text-shadow:1px 1px 1px #bbb;box-shadow:inset 0 0 3px rgba(0,0,0,0.2);
    white-space:nowrap;
}
.oracle-meta{font-size:5.8pt;line-height:1.65;}

.header-center{text-align:center;flex:1;padding:0 10px;}
.main-title{font-family:Arial,sans-serif;font-size:12pt;font-weight:bold;letter-spacing:3px;}
.sub-title{font-family:Arial,sans-serif;font-size:7.5pt;font-weight:bold;margin-top:1px;}
.dept-line{font-size:7pt;margin-top:1px;}
.period-for{font-size:6.5pt;margin-top:1px;font-weight:bold;}

.header-right{text-align:right;font-size:6pt;min-width:80px;}

.ack{font-size:5.8pt;font-style:italic;margin:2px 0 2px;line-height:1.3;}

/* ── Table ── */
table.pv{width:100%;border-collapse:collapse;font-size:5.8pt;}
table.pv th,table.pv td{
    border:1px solid #000;padding:1px 2px;
    text-align:center;vertical-align:middle;
    white-space:nowrap;line-height:1.2;
}
table.pv th{font-weight:bold;font-size:5.3pt;background:#f0f0f0;line-height:1.3;}
table.pv td.L{text-align:left;}
table.pv td.R{text-align:right;}
table.pv td.name-col{text-align:left;white-space:normal;min-width:100px;max-width:130px;line-height:1.2;}
table.pv td.pos-col{text-align:left;white-space:normal;min-width:50px;max-width:65px;font-size:5.3pt;}
table.pv tr.tot-row td{font-weight:bold;background:#e8e8e8;border-top:2px solid #000;}
table.pv tr.grand-row td{font-weight:bold;background:#d5d5f0;border-top:2px solid #000;}

.sheet-footer{
    display:flex;justify-content:space-between;
    font-size:5.5pt;margin-top:3px;
}

.no-records{text-align:center;padding:50px;font-size:11pt;color:#555;}

.print-controls{position:fixed;top:14px;right:14px;display:flex;gap:8px;z-index:999;}
.print-controls button,.print-controls a{
    padding:8px 18px;font-size:12px;font-weight:bold;border:none;border-radius:6px;
    cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;
}
.btn-print{background:#1a3a5c;color:#fff}.btn-print:hover{background:#0e2540}
.btn-print:disabled{background:#9ca3af;cursor:not-allowed}
.btn-back{background:#6b7280;color:#fff}.btn-back:hover{background:#4b5563}

@media print{
    body{background:#fff}
    .print-controls{display:none!important}
    .sheet{margin:0;width:100%;padding:.15in .18in .1in;}
    @page{size:17in 11in landscape;margin:0}
}
</style>
</head>
<body>

<div class="print-controls">
    <button class="btn-print" onclick="window.print()" <?php echo !$hasPaidRecords?'disabled':''; ?>>
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
    $GT = ['monthly_rate'=>0,'pera'=>0,'gross'=>0,'wtax'=>0,'philhealth'=>0,
           'gsis'=>0,'pagibig'=>0,'philhealth_cons'=>0,'provident'=>0,
           'bacgem'=>0,'nocgem_bcgeu'=>0,'total_ded'=>0,'net_1_15'=>0,'net_16_31'=>0];

    $sheetChunks = array_chunk($rows, $rowsPerSheet);
    foreach ($sheetChunks as $si => $chunk):
        $sn  = $si + 1;
        $lbl = str_pad($sn,3,'0',STR_PAD_LEFT).' of '.str_pad($totalSheets,3,'0',STR_PAD_LEFT);
        $ST  = ['monthly_rate'=>0,'pera'=>0,'gross'=>0,'wtax'=>0,'philhealth'=>0,
                'gsis'=>0,'pagibig'=>0,'philhealth_cons'=>0,'provident'=>0,
                'bacgem'=>0,'nocgem_bcgeu'=>0,'total_ded'=>0,'net_1_15'=>0,'net_16_31'=>0];
?>
<div class="sheet">

  <div class="sys-line">
    <span>MTCS control: vpayroll.rdf, July 3, 2003</span>
    <span>Sheet No. : <?php echo $lbl; ?></span>
  </div>

  <div class="header-block">
    <div class="oracle-left">
      <div class="oracle-box">ORACLE</div>
      <div class="oracle-meta">
        RevDate: 12/19/2018<br>
        Rev 3.2<br>
        Printdate: <?php echo $printDate; ?>
      </div>
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
        <th rowspan="2" style="width:24px">NO.</th>
        <th rowspan="2" style="width:125px">EMPLOYEE NAME</th>
        <th rowspan="2" style="width:62px">POSITION</th>
        <th rowspan="2" style="width:58px">MONTHLY<br>RATE</th>
        <th rowspan="2" style="width:50px">PERA</th>
        <th rowspan="2" style="width:60px">GROSS<br>PAY</th>
        <th rowspan="2" style="width:46px">WTAX</th>
        <th rowspan="2" style="width:52px">PREMILBASE<br>PHILHEALTH</th>
        <th rowspan="2" style="width:56px">GSIS<br>CONSOLIDATED</th>
        <th colspan="5" style="background:#e8e8f5">CONSOLIDATED</th>
        <th rowspan="2" style="width:58px">TOTAL<br>DEDUCTIONS<br>CONSOLIDATED</th>
        <th rowspan="2" style="width:54px">NET PAY<br>1-15</th>
        <th rowspan="2" style="width:54px">NET PAY<br>16-31</th>
      </tr>
      <tr>
        <th style="width:52px;background:#e8e8f5">PAGIBIG<br>CONSOLIDATED</th>
        <th style="width:56px;background:#e8e8f5">PHILHEALTH<br>CONSOLIDATED</th>
        <th style="width:52px;background:#e8e8f5">PROVIDENT<br>CONSOLIDATED</th>
        <th style="width:52px;background:#e8e8f5">BACGEM<br>CONSOLIDATED</th>
        <th style="width:56px;background:#e8e8f5">NOCGEM/<br>BCGEU<br>CONSOLIDATED</th>
      </tr>
    </thead>
    <tbody>
<?php
        $rowNo = ($si * $rowsPerSheet) + 1;
        foreach ($chunk as $row):
            $name = strtoupper($row['last_name'].', '.$row['first_name']);
            if ($row['middle_name']) $name .= ' '.strtoupper(substr($row['middle_name'],0,1)).'.';

            $monthlyRate  = $row['salary_rate'] ? (float)$row['salary_rate'] : (float)$row['basic_salary'];
            $pera         = (float)$row['pera'];
            $gross        = (float)$row['gross_pay'];
            $wtax         = (float)$row['wtax'];
            $philBase     = (float)$row['philhealth'];
            $gsis         = (float)$row['gsis'];
            $pagibig      = (float)$row['pagibig'];
            $philCons     = (float)$row['philhealth'];
            $provident    = (float)$row['provident'];
            $bacgem       = (float)$row['bacgem'];
            $nocgemBcgeu  = (float)($row['nocgem'] + $row['bcgeu']);
            $totalDed     = (float)$row['total_deductions'];
            $net1         = (float)$row['net_1_15'];
            $net2         = (float)$row['net_16_31'];

            $ST['monthly_rate']    += $monthlyRate;
            $ST['pera']            += $pera;
            $ST['gross']           += $gross;
            $ST['wtax']            += $wtax;
            $ST['philhealth']      += $philBase;
            $ST['gsis']            += $gsis;
            $ST['pagibig']         += $pagibig;
            $ST['philhealth_cons'] += $philCons;
            $ST['provident']       += $provident;
            $ST['bacgem']          += $bacgem;
            $ST['nocgem_bcgeu']    += $nocgemBcgeu;
            $ST['total_ded']       += $totalDed;
            $ST['net_1_15']        += $net1;
            $ST['net_16_31']       += $net2;

            $GT['monthly_rate']    += $monthlyRate;
            $GT['pera']            += $pera;
            $GT['gross']           += $gross;
            $GT['wtax']            += $wtax;
            $GT['philhealth']      += $philBase;
            $GT['gsis']            += $gsis;
            $GT['pagibig']         += $pagibig;
            $GT['philhealth_cons'] += $philCons;
            $GT['provident']       += $provident;
            $GT['bacgem']          += $bacgem;
            $GT['nocgem_bcgeu']    += $nocgemBcgeu;
            $GT['total_ded']       += $totalDed;
            $GT['net_1_15']        += $net1;
            $GT['net_16_31']       += $net2;
?>
      <tr>
        <td><?php echo $rowNo++; ?></td>
        <td class="name-col"><?php echo htmlspecialchars($name); ?></td>
        <td class="pos-col"><?php echo htmlspecialchars($row['position_title'] ?? ''); ?></td>
        <td class="R"><?php echo fmt($monthlyRate); ?></td>
        <td class="R"><?php echo fmt($pera); ?></td>
        <td class="R"><?php echo fmt($gross); ?></td>
        <td class="R"><?php echo fmt($wtax); ?></td>
        <td class="R"><?php echo fmt($philBase); ?></td>
        <td class="R"><?php echo fmt($gsis); ?></td>
        <td class="R"><?php echo fmt($pagibig); ?></td>
        <td class="R"><?php echo fmt($philCons); ?></td>
        <td class="R"><?php echo fmt($provident); ?></td>
        <td class="R"><?php echo fmt($bacgem); ?></td>
        <td class="R"><?php echo fmt($nocgemBcgeu); ?></td>
        <td class="R"><strong><?php echo fmt($totalDed); ?></strong></td>
        <td class="R"><strong><?php echo fmt($net1); ?></strong></td>
        <td class="R"><strong><?php echo fmt($net2); ?></strong></td>
      </tr>
<?php endforeach; ?>

      <tr class="tot-row">
        <td colspan="3" class="R">TOTALS</td>
        <td class="R"><?php echo fmt($ST['monthly_rate']); ?></td>
        <td class="R"><?php echo fmt($ST['pera']); ?></td>
        <td class="R"><?php echo fmt($ST['gross']); ?></td>
        <td class="R"><?php echo fmt($ST['wtax']); ?></td>
        <td class="R"><?php echo fmt($ST['philhealth']); ?></td>
        <td class="R"><?php echo fmt($ST['gsis']); ?></td>
        <td class="R"><?php echo fmt($ST['pagibig']); ?></td>
        <td class="R"><?php echo fmt($ST['philhealth_cons']); ?></td>
        <td class="R"><?php echo fmt($ST['provident']); ?></td>
        <td class="R"><?php echo fmt($ST['bacgem']); ?></td>
        <td class="R"><?php echo fmt($ST['nocgem_bcgeu']); ?></td>
        <td class="R"><?php echo fmt($ST['total_ded']); ?></td>
        <td class="R"><?php echo fmt($ST['net_1_15']); ?></td>
        <td class="R"><?php echo fmt($ST['net_16_31']); ?></td>
      </tr>

      <?php if ($sn === $totalSheets && $totalSheets > 1): ?>
      <tr class="grand-row">
        <td colspan="3" class="R">GRAND TOTALS</td>
        <td class="R"><?php echo fmt($GT['monthly_rate']); ?></td>
        <td class="R"><?php echo fmt($GT['pera']); ?></td>
        <td class="R"><?php echo fmt($GT['gross']); ?></td>
        <td class="R"><?php echo fmt($GT['wtax']); ?></td>
        <td class="R"><?php echo fmt($GT['philhealth']); ?></td>
        <td class="R"><?php echo fmt($GT['gsis']); ?></td>
        <td class="R"><?php echo fmt($GT['pagibig']); ?></td>
        <td class="R"><?php echo fmt($GT['philhealth_cons']); ?></td>
        <td class="R"><?php echo fmt($GT['provident']); ?></td>
        <td class="R"><?php echo fmt($GT['bacgem']); ?></td>
        <td class="R"><?php echo fmt($GT['nocgem_bcgeu']); ?></td>
        <td class="R"><?php echo fmt($GT['total_ded']); ?></td>
        <td class="R"><?php echo fmt($GT['net_1_15']); ?></td>
        <td class="R"><?php echo fmt($GT['net_16_31']); ?></td>
      </tr>
      <?php endif; ?>

    </tbody>
  </table>

  <div class="sheet-footer">
    <span>Printed: <?php echo date('m/d/Y h:i A'); ?></span>
    <span><?php echo htmlspecialchars($dept['department_name']); ?> &mdash; <?php echo $periodLabel; ?></span>
    <span>Sheet No.: <?php echo $lbl; ?></span>
  </div>

</div>
<?php
    endforeach;
endif;
?>
</body>
</html>