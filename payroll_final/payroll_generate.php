<?php
/**
 * Payroll System - Generate Payroll (Batch)
 */

require_once 'includes/config.php';

$pageTitle = 'Generate Payroll';
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = sanitize($_POST['payroll_month']);
    $year = (int)$_POST['payroll_year'];
    $period = sanitize($_POST['period_type']);
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    
    // Get employees
    $where = "e.is_active = 1";
    if ($deptId) {
        $where .= " AND e.department_id = $deptId";
    }
    
    $employees = $conn->query("
        SELECT e.id, p.basic_salary
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE $where AND p.basic_salary > 0
    ");
    
    $created = 0;
    $skipped = 0;
    
    while ($emp = $employees->fetch_assoc()) {
        // Check if already exists
        $check = $conn->query("SELECT id FROM payroll WHERE employee_id = {$emp['id']} AND payroll_month = '$month' AND payroll_year = $year AND period_type = '$period'");
        
        if ($check->num_rows > 0) {
            $skipped++;
            continue;
        }
        
        $basic = $emp['basic_salary'];
        $pera = 2000;
        $gross = $basic + $pera;
        $gsis = $basic * 0.09;
        $philhealth = min($basic * 0.025, 5000);
        $pagibig = $basic > 5000 ? 200 : ($basic > 1500 ? $basic * 0.02 : $basic * 0.01);
        $taxable = $gross - $gsis - $philhealth - $pagibig;
        $wtax = calculateWithholdingTax($taxable);
        $totalDed = $gsis + $philhealth + $pagibig + $wtax;
        $net = $gross - $totalDed;
        
        $payrollPeriod = "$month $period, $year";
        
        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, payroll_period, payroll_month, payroll_year, period_type, basic_salary, pera, gross_pay, wtax, philhealth, gsis, pagibig, total_deductions, net_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issisddddddddd", $emp['id'], $payrollPeriod, $month, $year, $period, $basic, $pera, $gross, $wtax, $philhealth, $gsis, $pagibig, $totalDed, $net);
        
        if ($stmt->execute()) {
            $created++;
        }
        $stmt->close();
    }
    
    if ($created > 0) {
        $message = "Successfully generated $created payroll record(s). Skipped $skipped (already exists).";
        $messageType = 'success';
    } else {
        $message = "No new payroll records created. $skipped record(s) already exist.";
        $messageType = 'warning';
    }
}

// Get departments
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get employee count
$empCount = $conn->query("SELECT COUNT(*) as count FROM employees WHERE is_active = 1")->fetch_assoc()['count'];

require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>Generate</span>
    </div>
    <h1 class="page-title">Generate Payroll</h1>
    <p class="page-subtitle">Batch generate payroll for all active employees</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-calculator"></i> Batch Payroll Generation</h2>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="alert-icon fas fa-info-circle"></i>
            <div class="alert-content">
                <strong>About Batch Generation</strong><br>
                This will generate payroll records for all active employees with assigned positions. 
                Records that already exist for the selected period will be skipped.
                <br><br>
                <strong><?php echo $empCount; ?></strong> active employee(s) in the system.
            </div>
        </div>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Month</label>
                    <select name="payroll_month" class="form-control" required>
                        <?php 
                        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        $currentMonth = date('F');
                        foreach($months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $currentMonth ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Year</label>
                    <select name="payroll_year" class="form-control" required>
                        <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Period</label>
                    <select name="period_type" class="form-control" required>
                        <option value="1-15">1-15 (First Half)</option>
                        <option value="16-31">16-31 (Second Half)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Department (Optional)</label>
                <select name="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php while($dept = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $dept['id']; ?>">
                            [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <span class="form-hint">Leave empty to generate for all departments</span>
            </div>
            
            <hr style="margin: 2rem 0;">
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Generate payroll for all employees in the selected period?')">
                    <i class="fas fa-play"></i> Generate Payroll
                </button>
                <a href="payroll.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
