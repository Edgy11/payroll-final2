<?php
/**
 * Payroll System - Batch Payroll Creation
 * Create payroll for all employees in a department at once
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Create Batch Payroll';
$message = '';
$messageType = '';

$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle batch form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_batch'])) {
    $deptId = (int)$_POST['department_id'];
    $payrollMonth = sanitize($_POST['payroll_month']);
    $payrollYear = (int)$_POST['payroll_year'];
    $periodType = sanitize($_POST['period_type']);
    $payrollPeriod = $payrollMonth . ' ' . $periodType . ', ' . $payrollYear;
    
    $employeeIds = $_POST['employee_ids'] ?? [];
    $basicSalaries = $_POST['basic_salaries'] ?? [];
    $peras = $_POST['peras'] ?? [];
    $salaryIds = $_POST['salary_ids'] ?? [];
    
    // Optional deductions (same for all or individual)
    $provident = (float)($_POST['provident'] ?? 0);
    $bcgeu = (float)($_POST['bcgeu'] ?? 0);
    $nocgem = (float)($_POST['nocgem'] ?? 0);
    $bacgem = (float)($_POST['bacgem'] ?? 0);
    $otherDeductions = (float)($_POST['other_deductions'] ?? 0);
    
    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;
    
    foreach ($employeeIds as $index => $empId) {
        $empId = (int)$empId;
        $basicSalary = (float)$basicSalaries[$index];
        $pera = (float)$peras[$index];
        $salaryId = (int)$salaryIds[$index];
        
        // Check for duplicate
        $checkStmt = $conn->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ? AND payroll_year = ? AND period_type = ?");
        $checkStmt->bind_param("isis", $empId, $payrollMonth, $payrollYear, $periodType);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $skipCount++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();
        
        // Calculate deductions
        $gsis = $basicSalary * 0.09;
        $philhealth = calculatePhilHealth($basicSalary);
        $pagibig = calculatePagIbig($basicSalary);
        $wtax = calculateWithholdingTax($basicSalary);
        
        $grossPay = $basicSalary + $pera;
        $totalDeductions = $gsis + $philhealth + $pagibig + $wtax + $provident + $bcgeu + $nocgem + $bacgem + $otherDeductions;
        $netPay = $grossPay - $totalDeductions;
        $status = 'Draft';
        
        // Insert payroll
        $stmt = $conn->prepare("
            INSERT INTO payroll (
                employee_id, department_id, salary_id, payroll_period, payroll_month, payroll_year, period_type,
                basic_salary, pera, gross_pay,
                wtax, philhealth, gsis, pagibig, provident, bcgeu, nocgem, bacgem, other_deductions,
                total_deductions, net_pay, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "iiissisdddddddddddddds",
            $empId, $deptId, $salaryId, $payrollPeriod, $payrollMonth, $payrollYear, $periodType,
            $basicSalary, $pera, $grossPay,
            $wtax, $philhealth, $gsis, $pagibig, $provident, $bcgeu, $nocgem, $bacgem, $otherDeductions,
            $totalDeductions, $netPay, $status
        );
        
        if ($stmt->execute()) {
            $successCount++;
        } else {
            $errorCount++;
        }
        $stmt->close();
    }
    
    if ($successCount > 0) {
        $_SESSION['success_message'] = "Batch payroll created! $successCount record(s) created" . 
            ($skipCount > 0 ? ", $skipCount skipped (already exists)" : "") .
            ($errorCount > 0 ? ", $errorCount failed" : "");
    } else {
        $_SESSION['error_message'] = "No records created. " . 
            ($skipCount > 0 ? "$skipCount already exist. " : "") .
            ($errorCount > 0 ? "$errorCount failed." : "");
    }
    
    header('Location: payroll.php?department_id=' . $deptId);
    exit;
}

// Helper functions are defined in config.php

// Get all departments
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get selected department info and employees
$selectedDept = null;
$employees = [];

if ($selectedDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $selectedDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
    
    // Get active employees in this department
    $empQuery = "
        SELECT 
            e.id, e.employee_id, e.first_name, e.last_name, e.middle_name, e.date_hired,
            p.position_title, p.salary_grade, p.basic_salary
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.department_id = ? AND e.is_active = 1
        ORDER BY e.last_name, e.first_name
    ";
    $empStmt = $conn->prepare($empQuery);
    $empStmt->bind_param("i", $selectedDeptId);
    $empStmt->execute();
    $empResult = $empStmt->get_result();
    
    while ($emp = $empResult->fetch_assoc()) {
        // Calculate step increment
        $currentStep = 1;
        $yearsOfService = 0;
        if ($emp['date_hired']) {
            $hireDate = new DateTime($emp['date_hired']);
            $today = new DateTime();
            $yearsOfService = $hireDate->diff($today)->y;
            $currentStep = min(8, floor($yearsOfService / 3) + 1);
        }
        
        // Get correct salary from salary table
        $currentSalary = $emp['basic_salary'] ?? 0;
        $salaryId = 0;
        
        if ($emp['salary_grade']) {
            $salaryQuery = $conn->prepare("SELECT salary_id, salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
            $salaryQuery->bind_param("si", $emp['salary_grade'], $currentStep);
            $salaryQuery->execute();
            $salaryResult = $salaryQuery->get_result();
            if ($salaryData = $salaryResult->fetch_assoc()) {
                $currentSalary = $salaryData['salary_rate'];
                $salaryId = $salaryData['salary_id'];
            }
            $salaryQuery->close();
        }
        
        $employees[] = [
            'id' => $emp['id'],
            'employee_id' => $emp['employee_id'],
            'full_name' => $emp['last_name'] . ', ' . $emp['first_name'] . ($emp['middle_name'] ? ' ' . substr($emp['middle_name'], 0, 1) . '.' : ''),
            'position' => $emp['position_title'] ?? 'N/A',
            'salary_grade' => $emp['salary_grade'] ?? 'N/A',
            'current_step' => $currentStep,
            'basic_salary' => $currentSalary,
            'salary_id' => $salaryId,
            'years_of_service' => $yearsOfService
        ];
    }
    $empStmt->close();
}

require_once 'includes/header.php';
?>

<style>
.dept-select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.dept-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
    border: 3px solid transparent;
    text-align: center;
}

.dept-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    border-color: #2d6394;
}

.dept-card-icon {
    width: 64px; height: 64px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #e3f0fa, #b5d5f0);
    color: #2d6394;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.dept-card h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.dept-card p {
    font-size: 0.875rem;
    color: #6b7280;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #4b5563;
    font-weight: 600;
    margin-bottom: 1.5rem;
    text-decoration: none;
}

.back-link:hover { color: #2d6394; }

.batch-header {
    background: linear-gradient(135deg, #132840, #0c1929);
    color: white;
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
}

.batch-header h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.batch-header p {
    color: #7eb3e0;
}

.period-selector {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.period-selector h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #111827;
}

.period-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.employee-batch-table {
    width: 100%;
    border-collapse: collapse;
}

.employee-batch-table th {
    background: #f3f4f6;
    padding: 12px 16px;
    text-align: left;
    font-weight: 700;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.employee-batch-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.employee-batch-table tr:hover {
    background: #f9fafb;
}

.employee-batch-table .employee-name {
    font-weight: 600;
    color: #111827;
}

.employee-batch-table .employee-id {
    font-family: monospace;
    color: #6b7280;
    font-size: 0.875rem;
}

.salary-input {
    width: 120px;
    padding: 8px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-weight: 600;
    text-align: right;
}

.salary-input:focus {
    outline: none;
    border-color: #2d6394;
}

.step-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.grade-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.common-deductions {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.common-deductions h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #111827;
}

.deduction-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
}

.deduction-item label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.deduction-item input {
    width: 100%;
    padding: 8px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    text-align: right;
}

.batch-summary {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.batch-summary-info h3 {
    font-size: 1.25rem;
    font-weight: 700;
}

.batch-summary-info p {
    opacity: 0.9;
}

.btn-create-batch {
    background: white;
    color: #059669;
    padding: 14px 32px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-create-batch:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
}

.no-employees {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.no-employees i {
    font-size: 3rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.select-all-row {
    background: #e0f2fe !important;
}

.select-all-row td {
    padding: 8px 16px !important;
}
</style>

<?php if ($selectedDeptId == 0): ?>
<!-- DEPARTMENT SELECTION -->

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>Create Batch Payroll</span>
    </div>
    <h1 class="page-title">Create Batch Payroll</h1>
    <p class="page-subtitle">Select a department to create payroll for all employees</p>
</div>

<div class="dept-select-grid">
    <?php 
    $departments->data_seek(0);
    while($dept = $departments->fetch_assoc()): 
        // Count active employees
        $countQuery = $conn->query("SELECT COUNT(*) as count FROM employees WHERE department_id = {$dept['id']} AND is_active = 1");
        $empCount = $countQuery->fetch_assoc()['count'];
    ?>
        <div class="dept-card" onclick="window.location.href='payroll_create.php?department_id=<?php echo $dept['id']; ?>'">
            <div class="dept-card-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
            <p><?php echo $empCount; ?> active employee<?php echo $empCount != 1 ? 's' : ''; ?></p>
        </div>
    <?php endwhile; ?>
</div>

<?php else: ?>
<!-- BATCH PAYROLL FORM -->

<a href="payroll.php?department_id=<?php echo $selectedDeptId; ?>" class="back-link">
    <i class="fas fa-arrow-left"></i>
    Back to Payroll List
</a>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <a href="payroll.php?department_id=<?php echo $selectedDeptId; ?>"><?php echo htmlspecialchars($selectedDept['department_code']); ?></a>
        <span>/</span>
        <span>Create Batch</span>
    </div>
</div>

<div class="batch-header">
    <h2><i class="fas fa-layer-group"></i> Batch Payroll - <?php echo htmlspecialchars($selectedDept['department_name']); ?></h2>
    <p>Create payroll for <?php echo count($employees); ?> active employee<?php echo count($employees) != 1 ? 's' : ''; ?></p>
</div>

<?php if (count($employees) > 0): ?>

<form method="POST" id="batchForm">
    <input type="hidden" name="create_batch" value="1">
    <input type="hidden" name="department_id" value="<?php echo $selectedDeptId; ?>">
    
    <!-- Period Selector -->
    <div class="period-selector">
        <h3><i class="fas fa-calendar-alt"></i> Payroll Period</h3>
        
        <div class="period-grid">
            <div class="form-group">
                <label class="form-label">Month</label>
                <select name="payroll_month" id="payroll_month" class="form-control" required onchange="updatePeriodOptions()">
                    <?php
                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                    $currentMonth = date('F');
                    foreach($months as $m):
                    ?>
                        <option value="<?php echo $m; ?>" <?php echo $m === $currentMonth ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Year</label>
                <select name="payroll_year" id="payroll_year" class="form-control" required onchange="updatePeriodOptions()">
                    <?php
                    $currentYear = date('Y');
                    for($y = $currentYear + 1; $y >= $currentYear - 2; $y--):
                    ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Period</label>
                <select name="period_type" id="period_type" class="form-control" required>
                    <option value="1-15">1-15 (First Half)</option>
                    <option value="16-31">16-31 (Second Half)</option>
                    <option value="1-31">1-31 (Full Month)</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Employee List -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-users"></i>
                Employees
            </h2>
            <span style="background: #e0f2fe; color: #0369a1; padding: 6px 14px; border-radius: 20px; font-weight: 600;">
                <?php echo count($employees); ?> employee<?php echo count($employees) != 1 ? 's' : ''; ?>
            </span>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="employee-batch-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll" checked onchange="toggleSelectAll(this)">
                        </th>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Grade</th>
                        <th>Step</th>
                        <th>Basic Salary</th>
                        <th>PERA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $index => $emp): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>" class="emp-checkbox" checked>
                            <input type="hidden" name="salary_ids[]" value="<?php echo $emp['salary_id']; ?>">
                        </td>
                        <td>
                            <div class="employee-name"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                            <div class="employee-id"><?php echo htmlspecialchars($emp['employee_id']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                        <td><span class="grade-badge">SG-<?php echo $emp['salary_grade']; ?></span></td>
                        <td><span class="step-badge">Step <?php echo $emp['current_step']; ?></span></td>
                        <td>
                            <input type="number" name="basic_salaries[]" class="salary-input" 
                                   value="<?php echo number_format($emp['basic_salary'], 2, '.', ''); ?>" 
                                   step="0.01" min="0">
                        </td>
                        <td>
                            <input type="number" name="peras[]" class="salary-input" 
                                   value="2000.00" step="0.01" min="0" style="width: 100px;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Common Deductions -->
    <div class="common-deductions">
        <h3><i class="fas fa-minus-circle"></i> Additional Deductions (Applied to All)</h3>
        <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem;">
            Note: GSIS (9%), PhilHealth, Pag-IBIG, and Withholding Tax are calculated automatically.
        </p>
        <div class="deduction-grid">
            <div class="deduction-item">
                <label>Provident Fund</label>
                <input type="number" name="provident" value="0.00" step="0.01" min="0">
            </div>
            <div class="deduction-item">
                <label>BCGEU</label>
                <input type="number" name="bcgeu" value="0.00" step="0.01" min="0">
            </div>
            <div class="deduction-item">
                <label>NOCGEM</label>
                <input type="number" name="nocgem" value="0.00" step="0.01" min="0">
            </div>
            <div class="deduction-item">
                <label>BACGEM</label>
                <input type="number" name="bacgem" value="0.00" step="0.01" min="0">
            </div>
            <div class="deduction-item">
                <label>Other</label>
                <input type="number" name="other_deductions" value="0.00" step="0.01" min="0">
            </div>
        </div>
    </div>
    
    <!-- Submit -->
    <div class="batch-summary">
        <div class="batch-summary-info">
            <h3><i class="fas fa-check-circle"></i> Ready to Create</h3>
            <p><span id="selectedCount"><?php echo count($employees); ?></span> employee(s) selected</p>
        </div>
        <button type="submit" class="btn-create-batch">
            <i class="fas fa-save"></i> Create Batch Payroll
        </button>
    </div>
</form>

<script>
// Get days in month
function getDaysInMonth(month, year) {
    const monthIndex = ['January','February','March','April','May','June','July','August','September','October','November','December'].indexOf(month);
    return new Date(year, monthIndex + 1, 0).getDate();
}

// Update period options based on selected month/year
function updatePeriodOptions() {
    const month = document.getElementById('payroll_month').value;
    const year = document.getElementById('payroll_year').value;
    const daysInMonth = getDaysInMonth(month, year);
    
    const periodSelect = document.getElementById('period_type');
    const currentValue = periodSelect.value;
    
    periodSelect.innerHTML = `
        <option value="1-15">1-15 (First Half)</option>
        <option value="16-${daysInMonth}">16-${daysInMonth} (Second Half)</option>
        <option value="1-${daysInMonth}">1-${daysInMonth} (Full Month)</option>
    `;
    
    // Try to keep the same selection type
    if (currentValue === '1-15') {
        periodSelect.value = '1-15';
    } else if (currentValue.startsWith('16-')) {
        periodSelect.value = '16-' + daysInMonth;
    } else if (currentValue.startsWith('1-') && currentValue !== '1-15') {
        periodSelect.value = '1-' + daysInMonth;
    }
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.emp-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.emp-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
}

document.querySelectorAll('.emp-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

document.getElementById('batchForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.emp-checkbox:checked').length;
    if (checked === 0) {
        e.preventDefault();
        alert('Please select at least one employee.');
        return false;
    }
    
    if (!confirm('Create payroll for ' + checked + ' employee(s)?')) {
        e.preventDefault();
        return false;
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodOptions();
});
</script>

<?php else: ?>

<div class="card">
    <div class="card-body">
        <div class="no-employees">
            <i class="fas fa-users-slash"></i>
            <h3>No Active Employees</h3>
            <p>There are no active employees in this department.</p>
            <a href="employees.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Add Employees
            </a>
        </div>
    </div>
</div>

<?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>