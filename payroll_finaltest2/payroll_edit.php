<?php
/**
 * Payroll System - Edit Payroll
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Edit Payroll';
$message = '';
$messageType = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: payroll.php'); exit; }

// ── Fetch payroll record first (needed for both GET and POST) ──
$payroll = $conn->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_id,
           d.department_name, pos.position_title
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE p.id = $id
")->fetch_assoc();

if (!$payroll) { header('Location: payroll.php'); exit; }
if (in_array($payroll['status'], ['Approved', 'Paid'])) {
    header('Location: payroll_view.php?id=' . $id . '&locked=1'); exit;
}

// ── Handle form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newBasicSalary = (float)$_POST['basic_salary'];
    $pera           = (float)$_POST['pera'];
    $status         = sanitize($_POST['status']);
    $isHalfPeriod   = isset($_POST['is_half_period']) && $_POST['is_half_period'] == '1';

    // Only recalculate auto-deductions if basic salary actually changed
    $salaryChanged = abs($newBasicSalary - (float)$payroll['basic_salary']) > 0.01;
    if ($salaryChanged) {
        $philhealth  = calculatePhilHealth($newBasicSalary);
        $pagibigBase = calculatePagIbig($newBasicSalary);
        $wtax        = calculateWithholdingTax($newBasicSalary);
    } else {
        $philhealth  = (float)$payroll['philhealth'];
        $wtax        = (float)$payroll['wtax'];
        // Pagibig base = stored pagibig minus old consolidated extra
        $oldCon = $conn->query("SELECT pagibig_total FROM consolidated WHERE payroll_id=$id")->fetch_assoc();
        $oldExtra    = $oldCon ? (float)$oldCon['pagibig_total'] : 0;
        $pagibigBase = max(0, (float)$payroll['pagibig'] - $oldExtra);
        if ($pagibigBase <= 0) $pagibigBase = calculatePagIbig((float)$payroll['basic_salary']);
    }

    $bcgeuTotal = $isHalfPeriod ? 50.00 : 100.00;

    // GSIS
    $gsis_life_ret    = (float)($_POST['gsis_life_ret']    ?? 0);
    $gsis_emergency   = (float)($_POST['gsis_emergency']   ?? 0);
    $gsis_cpl         = (float)($_POST['gsis_cpl']         ?? 0);
    $gsis_gpal        = (float)($_POST['gsis_gpal']        ?? 0);
    $gsis_mpl         = (float)($_POST['gsis_mpl']         ?? 0);
    $gsis_mpl_lite    = (float)($_POST['gsis_mpl_lite']    ?? 0);
    $gsis_policy_loan = (float)($_POST['gsis_policy_loan'] ?? 0);
    $gsisTotal        = $gsis_life_ret + $gsis_emergency + $gsis_cpl + $gsis_gpal + $gsis_mpl + $gsis_mpl_lite + $gsis_policy_loan;

    // Pag-IBIG extra
    $pagibig_multi     = (float)($_POST['pagibig_multi']     ?? 0);
    $pagibig_emergency = (float)($_POST['pagibig_emergency'] ?? 0);
    $pagibig_premium   = (float)($_POST['pagibig_premium']   ?? 0);
    $pagibig_mp2       = (float)($_POST['pagibig_mp2']       ?? 0);
    $pagibig_housing   = (float)($_POST['pagibig_housing']   ?? 0);
    $pagibigExtraTotal = $pagibig_multi + $pagibig_emergency + $pagibig_premium + $pagibig_mp2 + $pagibig_housing;
    $pagibigTotal      = $pagibigBase + $pagibigExtraTotal;

    // Provident
    $provident_fund      = (float)($_POST['provident_fund']      ?? 0);
    $provident_fund_loan = (float)($_POST['provident_fund_loan'] ?? 0);
    $provident_edu_loan  = (float)($_POST['provident_edu_loan']  ?? 0);
    $provident_term_loan = (float)($_POST['provident_term_loan'] ?? 0);
    $providentTotal      = $provident_fund + $provident_fund_loan + $provident_edu_loan + $provident_term_loan;

    // BCGEU (auto, all sub-fields = 0)
    $bcgeu_edu_loan = $bcgeu_emergency = $bcgeu_grocery = $bcgeu_regular_loan = $bcgeu_others = 0;

    // BACGEM
    $bacgem_edu_loan = (float)($_POST['bacgem_edu_loan'] ?? 0);
    $bacgem_grocery  = (float)($_POST['bacgem_grocery']  ?? 0);
    $bacgem_others   = (float)($_POST['bacgem_others']   ?? 0);
    $bacgem_hcp      = (float)($_POST['bacgem_hcp']      ?? 0);
    $bacgem_loan     = (float)($_POST['bacgem_loan']     ?? 0);
    $bacgemTotal     = $bacgem_edu_loan + $bacgem_grocery + $bacgem_others + $bacgem_hcp + $bacgem_loan;

    // NOCGEM
    $nocgem_edu_loan     = (float)($_POST['nocgem_edu_loan']     ?? 0);
    $nocgem_emergency    = (float)($_POST['nocgem_emergency']    ?? 0);
    $nocgem_grocery      = (float)($_POST['nocgem_grocery']      ?? 0);
    $nocgem_hospital     = (float)($_POST['nocgem_hospital']     ?? 0);
    $nocgem_others       = (float)($_POST['nocgem_others']       ?? 0);
    $nocgem_plp          = (float)($_POST['nocgem_plp']          ?? 0);
    $nocgem_regular_loan = (float)($_POST['nocgem_regular_loan'] ?? 0);
    $nocgemTotal         = $nocgem_edu_loan + $nocgem_emergency + $nocgem_grocery + $nocgem_hospital + $nocgem_others + $nocgem_plp + $nocgem_regular_loan;

    // Other
    $other_cash_advance = (float)($_POST['other_cash_advance'] ?? 0);
    $other_insurance    = (float)($_POST['other_insurance']    ?? 0);
    $other_salary_loan  = (float)($_POST['other_salary_loan']  ?? 0);
    $other_others       = (float)($_POST['other_others']       ?? 0);
    $otherTotal         = $other_cash_advance + $other_insurance + $other_salary_loan + $other_others;

    // Final totals
    $grossPay          = $newBasicSalary + $pera;
    $totalDeductions   = $gsisTotal + $philhealth + $pagibigTotal + $wtax + $providentTotal + $bcgeuTotal + $nocgemTotal + $bacgemTotal + $otherTotal;
    $netPay            = $grossPay - $totalDeductions;
    $consolidatedGrand = $gsisTotal + $pagibigExtraTotal + $providentTotal + $bcgeuTotal + $bacgemTotal + $nocgemTotal + $otherTotal;

    // UPDATE payroll
    $stmt = $conn->prepare("UPDATE payroll SET basic_salary=?, pera=?, gross_pay=?, wtax=?, philhealth=?, gsis=?, pagibig=?, provident=?, bcgeu=?, nocgem=?, bacgem=?, other_deductions=?, total_deductions=?, net_pay=?, status=? WHERE id=?");
    $stmt->bind_param("ddddddddddddddsi", $newBasicSalary, $pera, $grossPay, $wtax, $philhealth, $gsisTotal, $pagibigTotal, $providentTotal, $bcgeuTotal, $nocgemTotal, $bacgemTotal, $otherTotal, $totalDeductions, $netPay, $status, $id);

    if ($stmt->execute()) {
        $stmt->close();

        // UPDATE or INSERT consolidated
        $chk = $conn->prepare("SELECT id FROM consolidated WHERE payroll_id=?");
        $chk->bind_param("i", $id); $chk->execute();
        $exists = $chk->get_result()->num_rows > 0; $chk->close();

        if ($exists) {
            $cStmt = $conn->prepare("UPDATE consolidated SET gsis_life_ret=?,gsis_emergency=?,gsis_cpl=?,gsis_gpal=?,gsis_mpl=?,gsis_mpl_lite=?,gsis_policy_loan=?,gsis_total=?,pagibig_multi=?,pagibig_emergency=?,pagibig_premium=?,pagibig_mp2=?,pagibig_housing=?,pagibig_total=?,provident_fund=?,provident_fund_loan=?,provident_edu_loan=?,provident_term_loan=?,provident_total=?,bcgeu_edu_loan=?,bcgeu_emergency=?,bcgeu_grocery=?,bcgeu_regular_loan=?,bcgeu_others=?,bcgeu_total=?,bacgem_edu_loan=?,bacgem_grocery=?,bacgem_others=?,bacgem_hcp=?,bacgem_loan=?,bacgem_total=?,nocgem_edu_loan=?,nocgem_emergency=?,nocgem_grocery=?,nocgem_hospital=?,nocgem_others=?,nocgem_plp=?,nocgem_regular_loan=?,nocgem_total=?,other_cash_advance=?,other_insurance=?,other_salary_loan=?,other_others=?,other_total=?,grand_total=? WHERE payroll_id=?");
            $cStmt->bind_param("dddddddddddddddddddddddddddddddddddddddddddddi", $gsis_life_ret,$gsis_emergency,$gsis_cpl,$gsis_gpal,$gsis_mpl,$gsis_mpl_lite,$gsis_policy_loan,$gsisTotal,$pagibig_multi,$pagibig_emergency,$pagibig_premium,$pagibig_mp2,$pagibig_housing,$pagibigExtraTotal,$provident_fund,$provident_fund_loan,$provident_edu_loan,$provident_term_loan,$providentTotal,$bcgeu_edu_loan,$bcgeu_emergency,$bcgeu_grocery,$bcgeu_regular_loan,$bcgeu_others,$bcgeuTotal,$bacgem_edu_loan,$bacgem_grocery,$bacgem_others,$bacgem_hcp,$bacgem_loan,$bacgemTotal,$nocgem_edu_loan,$nocgem_emergency,$nocgem_grocery,$nocgem_hospital,$nocgem_others,$nocgem_plp,$nocgem_regular_loan,$nocgemTotal,$other_cash_advance,$other_insurance,$other_salary_loan,$other_others,$otherTotal,$consolidatedGrand,$id);
        } else {
            $pi = $conn->query("SELECT employee_id,department_id,payroll_month,payroll_year,period_type FROM payroll WHERE id=$id")->fetch_assoc();
            $cStmt = $conn->prepare("INSERT INTO consolidated (payroll_id,employee_id,department_id,payroll_month,payroll_year,period_type,gsis_life_ret,gsis_emergency,gsis_cpl,gsis_gpal,gsis_mpl,gsis_mpl_lite,gsis_policy_loan,gsis_total,pagibig_multi,pagibig_emergency,pagibig_premium,pagibig_mp2,pagibig_housing,pagibig_total,provident_fund,provident_fund_loan,provident_edu_loan,provident_term_loan,provident_total,bcgeu_edu_loan,bcgeu_emergency,bcgeu_grocery,bcgeu_regular_loan,bcgeu_others,bcgeu_total,bacgem_edu_loan,bacgem_grocery,bacgem_others,bacgem_hcp,bacgem_loan,bacgem_total,nocgem_edu_loan,nocgem_emergency,nocgem_grocery,nocgem_hospital,nocgem_others,nocgem_plp,nocgem_regular_loan,nocgem_total,other_cash_advance,other_insurance,other_salary_loan,other_others,other_total,grand_total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $cStmt->bind_param("iiissiddddddddddddddddddddddddddddddddddddddddddddd", $id,$pi['employee_id'],$pi['department_id'],$pi['payroll_month'],$pi['payroll_year'],$pi['period_type'],$gsis_life_ret,$gsis_emergency,$gsis_cpl,$gsis_gpal,$gsis_mpl,$gsis_mpl_lite,$gsis_policy_loan,$gsisTotal,$pagibig_multi,$pagibig_emergency,$pagibig_premium,$pagibig_mp2,$pagibig_housing,$pagibigExtraTotal,$provident_fund,$provident_fund_loan,$provident_edu_loan,$provident_term_loan,$providentTotal,$bcgeu_edu_loan,$bcgeu_emergency,$bcgeu_grocery,$bcgeu_regular_loan,$bcgeu_others,$bcgeuTotal,$bacgem_edu_loan,$bacgem_grocery,$bacgem_others,$bacgem_hcp,$bacgem_loan,$bacgemTotal,$nocgem_edu_loan,$nocgem_emergency,$nocgem_grocery,$nocgem_hospital,$nocgem_others,$nocgem_plp,$nocgem_regular_loan,$nocgemTotal,$other_cash_advance,$other_insurance,$other_salary_loan,$other_others,$otherTotal,$consolidatedGrand);
        }
        $cStmt->execute(); $cStmt->close();

        // Reload fresh data
        $payroll = $conn->query("SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_id, d.department_name, pos.position_title FROM payroll p LEFT JOIN employees e ON p.employee_id=e.id LEFT JOIN departments d ON e.department_id=d.id LEFT JOIN positions pos ON e.position_id=pos.id WHERE p.id=$id")->fetch_assoc();
        $message = 'Payroll updated successfully!';
        $messageType = 'success';
    } else {
        $stmt->close();
        $message = 'Error: ' . $conn->error;
        $messageType = 'danger';
    }
}

// ── Load consolidated sub-fields ──
$con = $conn->query("SELECT * FROM consolidated WHERE payroll_id=$id")->fetch_assoc();
$con = $con ?: [];
function cv($con, $key) { return isset($con[$key]) ? number_format((float)$con[$key],2,'.','') : '0.00'; }

$isHalf      = ($payroll['period_type'] === '1-15' || str_starts_with($payroll['period_type'], '16-'));
$bcgeuStored = (float)$payroll['bcgeu'];

// Compute pagibig base for display (stored total - consolidated extra)
$storedPagibigExtra  = isset($con['pagibig_total']) ? (float)$con['pagibig_total'] : 0;
$pagibigBaseDisplay  = max(0, (float)$payroll['pagibig'] - $storedPagibigExtra);
if ($pagibigBaseDisplay <= 0) $pagibigBaseDisplay = (float)$payroll['pagibig'];

require_once 'includes/header.php';
?>

<link rel="stylesheet" href="css/payroll_edit.css">
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a><span>/</span>
        <a href="payroll.php">Payroll</a><span>/</span><span>Edit</span>
    </div>
    <h1 class="page-title">Edit Payroll</h1>
    <p class="page-subtitle">
        <?php echo htmlspecialchars($payroll['first_name'].' '.$payroll['last_name']); ?>
        — <?php echo $payroll['payroll_month'].' '.$payroll['period_type'].', '.$payroll['payroll_year']; ?>
    </p>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>">
    <i class="alert-icon fas fa-<?php echo $messageType==='success'?'check-circle':'exclamation-circle'; ?>"></i>
    <div class="alert-content"><?php echo $message; ?></div>
</div>
<?php endif; ?>

<form method="POST" id="payrollForm">
    <input type="hidden" name="is_half_period" value="<?php echo $isHalf?'1':'0'; ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- Employee Info -->
        <div class="card">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-user"></i> Employee Info</h2></div>
            <div class="card-body">
                <div class="employee-info" style="margin-bottom:1.5rem;">
                    <div class="employee-avatar" style="width:50px;height:50px;">
                        <?php echo strtoupper(substr($payroll['first_name'],0,1).substr($payroll['last_name'],0,1)); ?>
                    </div>
                    <div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($payroll['first_name'].' '.$payroll['last_name']); ?></div>
                        <div class="text-muted"><?php echo htmlspecialchars($payroll['emp_id']); ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <div class="form-control" style="background:var(--gray-100);"><?php echo htmlspecialchars($payroll['position_title']??'-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <div class="form-control" style="background:var(--gray-100);"><?php echo htmlspecialchars($payroll['department_name']??'-'); ?></div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Payroll Period</label>
                    <div class="form-control" style="background:var(--gray-100);"><?php echo htmlspecialchars($payroll['payroll_period']); ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Basic Salary <small style="font-weight:400;color:#6b7280;">(this period's amount)</small></label>
                    <input type="number" name="basic_salary" id="basic_salary" class="form-control" step="0.01" min="0" required value="<?php echo $payroll['basic_salary']; ?>" oninput="onSalaryChange()">
                </div>
                <div class="form-group">
                    <label class="form-label">PERA</label>
                    <input type="number" name="pera" id="pera" class="form-control" step="0.01" min="0" value="<?php echo $payroll['pera']; ?>" oninput="recalcSummary()">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="Draft" <?php echo $payroll['status']==='Draft'?'selected':''; ?>>Draft</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Auto-calc + Summary -->
        <div class="card">
            <div class="card-header"><h2 class="card-title"><i class="fas fa-bolt"></i> Auto-Calculated Deductions</h2></div>
            <div class="card-body">
                <p style="font-size:0.825rem;color:#6b7280;margin-bottom:1rem;">
                    Loaded from database. <strong>Only change if you modify Basic Salary.</strong>
                </p>
                <div class="auto-box-grid">
                    <div class="auto-box">
                        <div class="auto-box-label">PhilHealth</div>
                        <div class="auto-box-value" id="disp_philhealth">₱<?php echo number_format($payroll['philhealth'],2); ?></div>
                        <div class="auto-box-note">from DB</div>
                    </div>
                    <div class="auto-box">
                        <div class="auto-box-label">Pag-IBIG (base)</div>
                        <div class="auto-box-value" id="disp_pagibig_base">₱<?php echo number_format($pagibigBaseDisplay,2); ?></div>
                        <div class="auto-box-note">from DB</div>
                    </div>
                    <div class="auto-box">
                        <div class="auto-box-label">BCGEU</div>
                        <div class="auto-box-value">₱<?php echo number_format($bcgeuStored,2); ?></div>
                        <div class="auto-box-note"><?php echo $isHalf?'₱50 half-period':'₱100 full month'; ?></div>
                    </div>
                </div>
                <div class="auto-box-grid-wtax">
                    <div class="auto-box-wtax">
                        <div>
                            <div class="auto-box-label">Withholding Tax</div>
                            <div class="auto-box-note">Computed separately — based on taxable income</div>
                        </div>
                        <div class="auto-box-value" id="disp_wtax">₱<?php echo number_format($payroll['wtax'],2); ?></div>
                    </div>
                </div>

                <div style="background:linear-gradient(135deg,#132840,#0c1929);color:white;border-radius:12px;padding:1.25rem;">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;text-align:center;">
                        <div>
                            <div style="font-size:0.7rem;opacity:0.7;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Gross Pay</div>
                            <div style="font-size:1.2rem;font-weight:800;color:#6ee7b7;" id="sum_gross">₱<?php echo number_format($payroll['gross_pay'],2); ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.7rem;opacity:0.7;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Total Deductions</div>
                            <div style="font-size:1.2rem;font-weight:800;color:#fca5a5;" id="sum_deductions">₱<?php echo number_format($payroll['total_deductions'],2); ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.7rem;opacity:0.7;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Net Pay</div>
                            <div style="font-size:1.2rem;font-weight:800;color:#fff;" id="sum_net">₱<?php echo number_format($payroll['net_pay'],2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Consolidated Deductions -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2 class="card-title"><i class="fas fa-minus-circle"></i> Consolidated Deductions</h2></div>
        <div class="card-body">
            <p style="color:#6b7280;font-size:0.875rem;margin-bottom:1.25rem;">
                PhilHealth, Pag-IBIG, Withholding Tax, and BCGEU are calculated automatically.
                All values below are saved to the <strong>consolidated</strong> table.
            </p>
            <div class="auto-deduction-info">
                <i class="fas fa-bolt"></i>
                <span>BCGEU — ₱100.00/month deducted automatically:
                    <strong>₱50.00</strong> per half-period · <strong>₱100.00</strong> for full-month.
                    This period: <strong>₱<?php echo number_format($bcgeuStored,2); ?></strong>
                </span>
            </div>

            <!-- OTHER -->
            <div class="coop-block" id="other-block">
                <div class="coop-block-header" onclick="toggleCoopBlock('other')">
                    <div class="coop-block-title">
                        <span class="coop-tag" style="background:#fee2e2;color:#991b1b;">OTHER</span>
                        <span class="coop-label">Miscellaneous &amp; Other Deductions</span>
                    </div>
                    <div class="coop-block-right">
                        <span class="coop-total" id="other-total-display">Total: ₱<?php echo number_format((float)($con['other_total']??0),2); ?></span>
                        <i class="fas fa-chevron-down coop-chevron" id="other-chevron"></i>
                    </div>
                </div>
                <div class="coop-block-body" id="other-body">
                    <div class="coop-subfields" style="grid-template-columns:repeat(4,1fr);">
                        <div class="coop-subfield-item"><label>Cash Advance</label><input type="number" name="other_cash_advance" class="other-sub" value="<?php echo cv($con,'other_cash_advance'); ?>" step="0.01" min="0" oninput="updateCoopTotal('other');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Insurance</label><input type="number" name="other_insurance" class="other-sub" value="<?php echo cv($con,'other_insurance'); ?>" step="0.01" min="0" oninput="updateCoopTotal('other');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Salary Loan</label><input type="number" name="other_salary_loan" class="other-sub" value="<?php echo cv($con,'other_salary_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('other');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Others</label><input type="number" name="other_others" class="other-sub" value="<?php echo cv($con,'other_others'); ?>" step="0.01" min="0" oninput="updateCoopTotal('other');recalcSummary()"></div>
                    </div>
                </div>
            </div>

            <!-- PROVIDENT -->
            <div class="coop-block" id="provident-block">
                <div class="coop-block-header" onclick="toggleCoopBlock('provident')">
                    <div class="coop-block-title">
                        <span class="coop-tag" style="background:#ede9fe;color:#5b21b6;">PROVIDENT</span>
                        <span class="coop-label">Provident Fund Contributions &amp; Loans</span>
                    </div>
                    <div class="coop-block-right">
                        <span class="coop-total" id="provident-total-display">Total: ₱<?php echo number_format((float)($con['provident_total']??0),2); ?></span>
                        <i class="fas fa-chevron-down coop-chevron" id="provident-chevron"></i>
                    </div>
                </div>
                <div class="coop-block-body" id="provident-body">
                    <div class="coop-subfields" style="grid-template-columns:repeat(4,1fr);">
                        <div class="coop-subfield-item"><label>Provident Fund</label><input type="number" name="provident_fund" class="provident-sub" value="<?php echo cv($con,'provident_fund'); ?>" step="0.01" min="0" oninput="updateCoopTotal('provident');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Fund Loan</label><input type="number" name="provident_fund_loan" class="provident-sub" value="<?php echo cv($con,'provident_fund_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('provident');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Education Loan</label><input type="number" name="provident_edu_loan" class="provident-sub" value="<?php echo cv($con,'provident_edu_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('provident');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Term Loan</label><input type="number" name="provident_term_loan" class="provident-sub" value="<?php echo cv($con,'provident_term_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('provident');recalcSummary()"></div>
                    </div>
                </div>
            </div>

            <!-- PAG-IBIG -->
            <div class="coop-block" id="pagibig-block">
                <div class="coop-block-header" onclick="toggleCoopBlock('pagibig')">
                    <div class="coop-block-title">
                        <span class="coop-tag" style="background:#fce7f3;color:#9d174d;">PAG-IBIG</span>
                        <span class="coop-label">Pag-IBIG Fund Loans &amp; Contributions</span>
                    </div>
                    <div class="coop-block-right">
                        <span class="coop-total" id="pagibig-total-display">Total: ₱<?php echo number_format((float)($con['pagibig_total']??0),2); ?></span>
                        <i class="fas fa-chevron-down coop-chevron" id="pagibig-chevron"></i>
                    </div>
                </div>
                <div class="coop-block-body" id="pagibig-body">
                    <p style="font-size:0.78rem;color:#9ca3af;margin-bottom:12px;"><i class="fas fa-info-circle"></i> Standard Pag-IBIG premium is auto-calculated. Enter amounts here for <strong>additional</strong> loan payments only.</p>
                    <div class="coop-subfields" style="grid-template-columns:repeat(5,1fr);">
                        <div class="coop-subfield-item"><label>Multi-Purpose</label><input type="number" name="pagibig_multi" class="pagibig-sub" value="<?php echo cv($con,'pagibig_multi'); ?>" step="0.01" min="0" oninput="updateCoopTotal('pagibig');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Emergency</label><input type="number" name="pagibig_emergency" class="pagibig-sub" value="<?php echo cv($con,'pagibig_emergency'); ?>" step="0.01" min="0" oninput="updateCoopTotal('pagibig');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Premium</label><input type="number" name="pagibig_premium" class="pagibig-sub" value="<?php echo cv($con,'pagibig_premium'); ?>" step="0.01" min="0" oninput="updateCoopTotal('pagibig');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>MP2</label><input type="number" name="pagibig_mp2" class="pagibig-sub" value="<?php echo cv($con,'pagibig_mp2'); ?>" step="0.01" min="0" oninput="updateCoopTotal('pagibig');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Housing Loan</label><input type="number" name="pagibig_housing" class="pagibig-sub" value="<?php echo cv($con,'pagibig_housing'); ?>" step="0.01" min="0" oninput="updateCoopTotal('pagibig');recalcSummary()"></div>
                    </div>
                </div>
            </div>

            <!-- GSIS -->
            <div class="coop-block" id="gsis-block">
                <div class="coop-block-header" onclick="toggleCoopBlock('gsis')">
                    <div class="coop-block-title">
                        <span class="coop-tag" style="background:#dcfce7;color:#14532d;">GSIS</span>
                        <span class="coop-label">GSIS Loans &amp; Policy Deductions</span>
                    </div>
                    <div class="coop-block-right">
                        <span class="coop-total" id="gsis-total-display">Total: ₱<?php echo number_format((float)($con['gsis_total']??0),2); ?></span>
                        <i class="fas fa-chevron-down coop-chevron" id="gsis-chevron"></i>
                    </div>
                </div>
                <div class="coop-block-body" id="gsis-body">
                    <div class="coop-subfields" style="grid-template-columns:repeat(4,1fr);">
                        <div class="coop-subfield-item"><label>Life &amp; Ret.</label><input type="number" name="gsis_life_ret" class="gsis-sub" value="<?php echo cv($con,'gsis_life_ret'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Emergency Loan</label><input type="number" name="gsis_emergency" class="gsis-sub" value="<?php echo cv($con,'gsis_emergency'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>GSIS-CPL</label><input type="number" name="gsis_cpl" class="gsis-sub" value="<?php echo cv($con,'gsis_cpl'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>GSIS-GPAL</label><input type="number" name="gsis_gpal" class="gsis-sub" value="<?php echo cv($con,'gsis_gpal'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>GSIS-MPL</label><input type="number" name="gsis_mpl" class="gsis-sub" value="<?php echo cv($con,'gsis_mpl'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>GSIS-MPL Lite</label><input type="number" name="gsis_mpl_lite" class="gsis-sub" value="<?php echo cv($con,'gsis_mpl_lite'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Policy Loan</label><input type="number" name="gsis_policy_loan" class="gsis-sub" value="<?php echo cv($con,'gsis_policy_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('gsis');recalcSummary()"></div>
                    </div>
                </div>
            </div>

            <!-- BACGEM -->
            <div class="coop-block" id="bacgem-block">
                <div class="coop-block-header" onclick="toggleCoopBlock('bacgem')">
                    <div class="coop-block-title">
                        <span class="coop-tag bacgem-tag">BACGEM</span>
                        <span class="coop-label">BAC General Employees Multi-purpose Cooperative</span>
                    </div>
                    <div class="coop-block-right">
                        <span class="coop-total" id="bacgem-total-display">Total: ₱<?php echo number_format((float)($con['bacgem_total']??0),2); ?></span>
                        <i class="fas fa-chevron-down coop-chevron" id="bacgem-chevron"></i>
                    </div>
                </div>
                <div class="coop-block-body" id="bacgem-body">
                    <div class="coop-subfields" style="grid-template-columns:repeat(5,1fr);">
                        <div class="coop-subfield-item"><label>Education Loan</label><input type="number" name="bacgem_edu_loan" class="bacgem-sub" value="<?php echo cv($con,'bacgem_edu_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('bacgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Grocery</label><input type="number" name="bacgem_grocery" class="bacgem-sub" value="<?php echo cv($con,'bacgem_grocery'); ?>" step="0.01" min="0" oninput="updateCoopTotal('bacgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Others</label><input type="number" name="bacgem_others" class="bacgem-sub" value="<?php echo cv($con,'bacgem_others'); ?>" step="0.01" min="0" oninput="updateCoopTotal('bacgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>HCP</label><input type="number" name="bacgem_hcp" class="bacgem-sub" value="<?php echo cv($con,'bacgem_hcp'); ?>" step="0.01" min="0" oninput="updateCoopTotal('bacgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Loan</label><input type="number" name="bacgem_loan" class="bacgem-sub" value="<?php echo cv($con,'bacgem_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('bacgem');recalcSummary()"></div>
                    </div>
                </div>
            </div>

            <!-- NOCGEM -->
            <div class="coop-block" id="nocgem-block">
                <div class="coop-block-header" onclick="toggleCoopBlock('nocgem')">
                    <div class="coop-block-title">
                        <span class="coop-tag nocgem-tag">NOCGEM</span>
                        <span class="coop-label">NOC General Employees Multi-purpose Cooperative</span>
                    </div>
                    <div class="coop-block-right">
                        <span class="coop-total" id="nocgem-total-display">Total: ₱<?php echo number_format((float)($con['nocgem_total']??0),2); ?></span>
                        <i class="fas fa-chevron-down coop-chevron" id="nocgem-chevron"></i>
                    </div>
                </div>
                <div class="coop-block-body" id="nocgem-body">
                    <div class="coop-subfields" style="grid-template-columns:repeat(4,1fr);">
                        <div class="coop-subfield-item"><label>Education Loan</label><input type="number" name="nocgem_edu_loan" class="nocgem-sub" value="<?php echo cv($con,'nocgem_edu_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Emergency</label><input type="number" name="nocgem_emergency" class="nocgem-sub" value="<?php echo cv($con,'nocgem_emergency'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Grocery</label><input type="number" name="nocgem_grocery" class="nocgem-sub" value="<?php echo cv($con,'nocgem_grocery'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Hospital</label><input type="number" name="nocgem_hospital" class="nocgem-sub" value="<?php echo cv($con,'nocgem_hospital'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Others</label><input type="number" name="nocgem_others" class="nocgem-sub" value="<?php echo cv($con,'nocgem_others'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>PLP</label><input type="number" name="nocgem_plp" class="nocgem-sub" value="<?php echo cv($con,'nocgem_plp'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                        <div class="coop-subfield-item"><label>Regular Loans</label><input type="number" name="nocgem_regular_loan" class="nocgem-sub" value="<?php echo cv($con,'nocgem_regular_loan'); ?>" step="0.01" min="0" oninput="updateCoopTotal('nocgem');recalcSummary()"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div style="display:flex;gap:1rem;align-items:center;margin-bottom:2rem;">
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Update Payroll</button>
        <a href="payroll.php?department_id=<?php echo $payroll['department_id']; ?>" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left"></i> Back to Payroll List</a>
    </div>
</form>

<script>
// ── Baseline values straight from DB — never recalculated on page load ──
var storedPhilhealth  = <?php echo (float)$payroll['philhealth']; ?>;
var storedPagibigBase = <?php echo $pagibigBaseDisplay; ?>;
var storedWtax        = <?php echo (float)$payroll['wtax']; ?>;
var storedBcgeu       = <?php echo $bcgeuStored; ?>;
var storedBasicSalary = <?php echo (float)$payroll['basic_salary']; ?>;

var livePhilhealth  = storedPhilhealth;
var livePagibigBase = storedPagibigBase;
var liveWtax        = storedWtax;

function toggleCoopBlock(prefix) {
    document.getElementById(prefix+'-body').classList.toggle('open');
    document.getElementById(prefix+'-chevron').classList.toggle('open');
}

function updateCoopTotal(prefix) {
    let t = 0;
    document.querySelectorAll('.'+prefix+'-sub').forEach(i => t += parseFloat(i.value)||0);
    document.getElementById(prefix+'-total-display').textContent =
        'Total: ₱'+t.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function sumClass(cls) {
    let t=0; document.querySelectorAll('.'+cls).forEach(i=>t+=parseFloat(i.value)||0); return t;
}

function fmt(v){ return '₱'+v.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }

// Only recalculate auto-deductions if salary changes
function onSalaryChange() {
    const salary = parseFloat(document.getElementById('basic_salary').value)||0;
    if (Math.abs(salary - storedBasicSalary) > 0.01) {
        livePhilhealth  = Math.round(salary*0.05/2*100)/100;
        livePagibigBase = Math.min(salary*0.02, 100);
        document.getElementById('disp_philhealth').textContent   = fmt(livePhilhealth)+' (est.)';
        document.getElementById('disp_pagibig_base').textContent = fmt(livePagibigBase)+' (est.)';
    } else {
        livePhilhealth  = storedPhilhealth;
        livePagibigBase = storedPagibigBase;
        liveWtax        = storedWtax;
        document.getElementById('disp_philhealth').textContent   = fmt(storedPhilhealth);
        document.getElementById('disp_pagibig_base').textContent = fmt(storedPagibigBase);
        document.getElementById('disp_wtax').textContent         = fmt(storedWtax);
    }
    recalcSummary();
}

function recalcSummary() {
    const salary = parseFloat(document.getElementById('basic_salary').value)||0;
    const pera   = parseFloat(document.getElementById('pera').value)||0;

    const grossPay        = salary + pera;
    const totalDeductions = sumClass('gsis-sub')
                          + livePhilhealth
                          + (livePagibigBase + sumClass('pagibig-sub'))
                          + liveWtax
                          + sumClass('provident-sub')
                          + storedBcgeu
                          + sumClass('nocgem-sub')
                          + sumClass('bacgem-sub')
                          + sumClass('other-sub');

    document.getElementById('sum_gross').textContent      = fmt(grossPay);
    document.getElementById('sum_deductions').textContent = fmt(totalDeductions);
    document.getElementById('sum_net').textContent        = fmt(grossPay - totalDeductions);
}

document.addEventListener('DOMContentLoaded', function(){
    ['other','provident','pagibig','gsis','bacgem','nocgem'].forEach(updateCoopTotal);
    recalcSummary();
});
</script>

<?php require_once 'includes/footer.php'; ?>