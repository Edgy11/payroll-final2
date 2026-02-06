<?php
/**
 * Payroll System - Edit Payroll
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Edit Payroll';
$message = '';
$messageType = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: payroll.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $basicSalary = (float)$_POST['basic_salary'];
    $pera = (float)$_POST['pera'];
    $grossPay = (float)$_POST['gross_pay'];
    $wtax = (float)$_POST['wtax_amount'];
    $philhealth = (float)$_POST['philhealth_amount'];
    $gsis = (float)$_POST['gsis_amount'];
    $pagibig = (float)$_POST['pagibig_amount'];
    $provident = (float)$_POST['provident'];
    $bcgeu = (float)$_POST['bcgeu'];
    $nocgem = (float)$_POST['nocgem'];
    $bacgem = (float)$_POST['bacgem'];
    $others = (float)$_POST['other_deductions'];
    $totalDeductions = (float)$_POST['total_deductions'];
    $netPay = (float)$_POST['net_pay'];
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE payroll SET basic_salary=?, pera=?, gross_pay=?, wtax=?, philhealth=?, gsis=?, pagibig=?, provident=?, bcgeu=?, nocgem=?, bacgem=?, other_deductions=?, total_deductions=?, net_pay=?, status=? WHERE id=?");
    $stmt->bind_param("ddddddddddddddsi", $basicSalary, $pera, $grossPay, $wtax, $philhealth, $gsis, $pagibig, $provident, $bcgeu, $nocgem, $bacgem, $others, $totalDeductions, $netPay, $status, $id);
    
    if ($stmt->execute()) {
        $message = 'Payroll updated successfully!';
        $messageType = 'success';
    } else {
        $message = 'Error: ' . $conn->error;
        $messageType = 'danger';
    }
    $stmt->close();
}

// Get payroll record
$payroll = $conn->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_id,
           d.department_name, pos.position_title
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE p.id = $id
")->fetch_assoc();

if (!$payroll) {
    header('Location: payroll.php');
    exit;
}

require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>Edit</span>
    </div>
    <h1 class="page-title">Edit Payroll</h1>
    <p class="page-subtitle"><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?> - <?php echo $payroll['payroll_month'] . ' ' . $payroll['period_type'] . ', ' . $payroll['payroll_year']; ?></p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<form method="POST" id="payrollForm">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        
        <!-- Left Column -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-user"></i> Employee Info</h2>
            </div>
            <div class="card-body">
                <div class="employee-info" style="margin-bottom: 1.5rem;">
                    <div class="employee-avatar" style="width: 50px; height: 50px;">
                        <?php echo strtoupper(substr($payroll['first_name'], 0, 1) . substr($payroll['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 700;"><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></div>
                        <div class="text-muted"><?php echo htmlspecialchars($payroll['emp_id']); ?></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <div id="position_display" class="form-control" style="background: var(--gray-100);"><?php echo htmlspecialchars($payroll['position_title'] ?: '-'); ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <div id="department_display" class="form-control" style="background: var(--gray-100);"><?php echo htmlspecialchars($payroll['department_name'] ?: '-'); ?></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Basic Salary (Monthly)</label>
                    <input type="number" name="basic_salary" id="basic_salary" class="form-control" step="0.01" required value="<?php echo $payroll['basic_salary']; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">PERA</label>
                    <input type="number" name="pera" id="pera" class="form-control" step="0.01" value="<?php echo $payroll['pera']; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="Draft" <?php echo $payroll['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="Approved" <?php echo $payroll['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Paid" <?php echo $payroll['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><i class="fas fa-calculator"></i> Deductions</h2>
            </div>
            <div class="card-body">
                <div class="deduction-grid">
                    <div class="deduction-item">
                        <div class="deduction-label">GSIS</div>
                        <div class="deduction-value" id="calc_gsis"><?php echo formatCurrency($payroll['gsis']); ?></div>
                    </div>
                    <div class="deduction-item">
                        <div class="deduction-label">PhilHealth</div>
                        <div class="deduction-value" id="calc_philhealth"><?php echo formatCurrency($payroll['philhealth']); ?></div>
                    </div>
                    <div class="deduction-item">
                        <div class="deduction-label">Pag-IBIG</div>
                        <div class="deduction-value" id="calc_pagibig"><?php echo formatCurrency($payroll['pagibig']); ?></div>
                    </div>
                    <div class="deduction-item">
                        <div class="deduction-label">W-Tax</div>
                        <div class="deduction-value" id="calc_wtax"><?php echo formatCurrency($payroll['wtax']); ?></div>
                    </div>
                </div>
                
                <hr style="margin: 1.5rem 0; border-color: var(--gray-200);">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Provident</label>
                        <input type="number" name="provident" id="provident" class="form-control" step="0.01" value="<?php echo $payroll['provident']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BCGEU</label>
                        <input type="number" name="bcgeu" id="bcgeu" class="form-control" step="0.01" value="<?php echo $payroll['bcgeu']; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">NOCGEM</label>
                        <input type="number" name="nocgem" id="nocgem" class="form-control" step="0.01" value="<?php echo $payroll['nocgem']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BACGEM</label>
                        <input type="number" name="bacgem" id="bacgem" class="form-control" step="0.01" value="<?php echo $payroll['bacgem']; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Other Deductions</label>
                    <input type="number" name="other_deductions" id="other_deductions" class="form-control" step="0.01" value="<?php echo $payroll['other_deductions']; ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header" style="background: linear-gradient(135deg, var(--primary-800), var(--primary-900)); color: white;">
            <h2 class="card-title" style="color: white;"><i class="fas fa-receipt"></i> Summary</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; text-align: center;">
                <div>
                    <div class="text-muted">Gross Pay</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-600);" id="calc_grossPay"><?php echo formatCurrency($payroll['gross_pay']); ?></div>
                </div>
                <div>
                    <div class="text-muted">Total Deductions</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-500);" id="calc_totalDeductions"><?php echo formatCurrency($payroll['total_deductions']); ?></div>
                </div>
                <div>
                    <div class="text-muted">Net Pay</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-500);" id="calc_netPay"><?php echo formatCurrency($payroll['net_pay']); ?></div>
                </div>
                <div>
                    <button type="submit" class="btn btn-success btn-lg" style="width: 100%;">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="gsis_amount" id="gsis_amount" value="<?php echo $payroll['gsis']; ?>">
    <input type="hidden" name="philhealth_amount" id="philhealth_amount" value="<?php echo $payroll['philhealth']; ?>">
    <input type="hidden" name="pagibig_amount" id="pagibig_amount" value="<?php echo $payroll['pagibig']; ?>">
    <input type="hidden" name="wtax_amount" id="wtax_amount" value="<?php echo $payroll['wtax']; ?>">
    <input type="hidden" name="gross_pay" id="gross_pay" value="<?php echo $payroll['gross_pay']; ?>">
    <input type="hidden" name="total_deductions" id="total_deductions" value="<?php echo $payroll['total_deductions']; ?>">
    <input type="hidden" name="net_pay" id="net_pay" value="<?php echo $payroll['net_pay']; ?>">
</form>

<div style="margin-top: 1.5rem;">
    <a href="payroll.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Payroll List
    </a>
</div>

<?php require_once 'includes/footer.php'; ?>
