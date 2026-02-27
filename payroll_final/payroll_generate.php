<?php
/**
 * Payroll System - Generate Payroll for All Departments
 * Bulk payroll generation across multiple departments
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Generate Payroll';

// Handle batch generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    $payrollMonth = sanitize($_POST['payroll_month']);
    $payrollYear = (int)$_POST['payroll_year'];
    $periodType = sanitize($_POST['period_type']);
    $payrollPeriod = $payrollMonth . ' ' . $periodType . ', ' . $payrollYear;
    
    $selectedDepartments = $_POST['departments'] ?? [];
    
    // Optional deductions (same for all)
    $provident = (float)($_POST['provident'] ?? 0);
    $bcgeu = (float)($_POST['bcgeu'] ?? 0);
    $nocgem = (float)($_POST['nocgem'] ?? 0);
    $bacgem = (float)($_POST['bacgem'] ?? 0);
    $otherDeductions = (float)($_POST['other_deductions'] ?? 0);
    $defaultPera = (float)($_POST['default_pera'] ?? 2000);
    
    $totalSuccess = 0;
    $totalSkipped = 0;
    $totalErrors = 0;
    $departmentResults = [];
    
    foreach ($selectedDepartments as $deptId) {
        $deptId = (int)$deptId;
        
        // Get department name
        $deptStmt = $conn->prepare("SELECT department_name FROM departments WHERE id = ?");
        $deptStmt->bind_param("i", $deptId);
        $deptStmt->execute();
        $deptName = $deptStmt->get_result()->fetch_assoc()['department_name'];
        $deptStmt->close();
        
        $deptSuccess = 0;
        $deptSkipped = 0;
        $deptErrors = 0;
        
        // Get all active employees in this department
        $empQuery = "
            SELECT 
                e.id, e.employee_id, e.first_name, e.last_name, e.date_hired,
                p.salary_grade, p.basic_salary
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.department_id = ? AND e.is_active = 1
        ";
        $empStmt = $conn->prepare($empQuery);
        $empStmt->bind_param("i", $deptId);
        $empStmt->execute();
        $employees = $empStmt->get_result();
        
        while ($emp = $employees->fetch_assoc()) {
            $empId = $emp['id'];
            
            // Check for duplicate
            $checkStmt = $conn->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ? AND payroll_year = ? AND period_type = ?");
            $checkStmt->bind_param("isis", $empId, $payrollMonth, $payrollYear, $periodType);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $deptSkipped++;
                $checkStmt->close();
                continue;
            }
            $checkStmt->close();
            
            // Calculate step increment
            $currentStep = 1;
            if ($emp['date_hired']) {
                $hireDate = new DateTime($emp['date_hired']);
                $today = new DateTime();
                $yearsOfService = $hireDate->diff($today)->y;
                $currentStep = min(8, floor($yearsOfService / 3) + 1);
            }
            
            // Get correct salary from salary table
            $basicSalary = $emp['basic_salary'] ?? 0;
            $salaryId = 0;
            
            if ($emp['salary_grade']) {
                $salaryQuery = $conn->prepare("SELECT salary_id, salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
                $salaryQuery->bind_param("si", $emp['salary_grade'], $currentStep);
                $salaryQuery->execute();
                $salaryResult = $salaryQuery->get_result();
                if ($salaryData = $salaryResult->fetch_assoc()) {
                    $basicSalary = $salaryData['salary_rate'];
                    $salaryId = $salaryData['salary_id'];
                }
                $salaryQuery->close();
            }
            
            $pera = $defaultPera;
            
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
                $deptSuccess++;
            } else {
                $deptErrors++;
            }
            $stmt->close();
        }
        $empStmt->close();
        
        $totalSuccess += $deptSuccess;
        $totalSkipped += $deptSkipped;
        $totalErrors += $deptErrors;
        
        $departmentResults[] = [
            'name' => $deptName,
            'success' => $deptSuccess,
            'skipped' => $deptSkipped,
            'errors' => $deptErrors
        ];
    }
    
    // Build success message
    $message = "Payroll Generation Complete!<br>";
    $message .= "<strong>Total: $totalSuccess created, $totalSkipped skipped, $totalErrors errors</strong><br><br>";
    $message .= "<strong>Breakdown by Department:</strong><br>";
    foreach ($departmentResults as $result) {
        $message .= "• {$result['name']}: {$result['success']} created";
        if ($result['skipped'] > 0) $message .= ", {$result['skipped']} skipped";
        if ($result['errors'] > 0) $message .= ", {$result['errors']} errors";
        $message .= "<br>";
    }
    
    if ($totalSuccess > 0) {
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = "No payroll records were created. " . 
            ($totalSkipped > 0 ? "$totalSkipped already exist. " : "") .
            ($totalErrors > 0 ? "$totalErrors failed." : "");
    }
    
    header('Location: payroll_generate.php');
    exit;
}

// Get all departments with employee counts
$departmentsQuery = $conn->query("
    SELECT 
        d.*,
        COUNT(e.id) as employee_count,
        COUNT(CASE WHEN e.is_active = 1 THEN 1 END) as active_count
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id
    GROUP BY d.id
    ORDER BY d.department_name ASC
");

$departments = [];
while ($dept = $departmentsQuery->fetch_assoc()) {
    $departments[] = $dept;
}

require_once 'includes/header.php';
?>

<style>
.generate-header {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.generate-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.generate-header-content {
    position: relative;
    z-index: 1;
}

.generate-header h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.generate-header p {
    font-size: 1.125rem;
    opacity: 0.95;
}

.period-config-card {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.period-config-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.period-config-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.period-config-header i {
    color: #059669;
    font-size: 1.75rem;
}

.period-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.period-field label {
    display: block;
    font-weight: 700;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.period-field select,
.period-field input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    transition: all 0.2s;
}

.period-field select:focus,
.period-field input:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
}

.deductions-section {
    background: #f9fafb;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 2rem;
}

.deductions-section h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
}

.deductions-section p {
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.deductions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.deduction-field label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.deduction-field input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    text-align: right;
    font-weight: 600;
}

.departments-selection-card {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.departments-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.departments-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.departments-header i {
    color: #059669;
}

.select-all-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 10px 20px;
    background: #f3f4f6;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.select-all-button:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
}

.departments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.department-checkbox-card {
    background: #f9fafb;
    border: 3px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.department-checkbox-card:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.department-checkbox-card.selected {
    background: #d1fae5;
    border-color: #10b981;
}

.department-checkbox-card input[type="checkbox"] {
    width: 24px;
    height: 24px;
    cursor: pointer;
    accent-color: #059669;
}

.department-info {
    flex: 1;
}

.department-info h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.25rem;
}

.department-info p {
    font-size: 0.875rem;
    color: #6b7280;
}

.department-badge {
    background: #e0f2fe;
    color: #0369a1;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.employee-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #059669;
}

.generate-summary {
    background: linear-gradient(135deg, #059669, #047857);
    color: white;
    border-radius: 16px;
    padding: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.summary-info h3 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.summary-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.summary-stat {
    display: flex;
    flex-direction: column;
}

.summary-stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.summary-stat-value {
    font-size: 2rem;
    font-weight: 800;
}

.btn-generate {
    background: white;
    color: #059669;
    padding: 16px 40px;
    border-radius: 12px;
    font-weight: 800;
    font-size: 1.25rem;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-generate:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
}

.btn-generate:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.alert-info {
    background: #dbeafe;
    border: 2px solid #3b82f6;
    color: #1e40af;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.alert-info i {
    font-size: 1.5rem;
    margin-top: 0.125rem;
}

.no-departments {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.no-departments i {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}
</style>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>Generate Payroll</span>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="alert-icon fas fa-check-circle"></i>
        <div class="alert-content"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <i class="alert-icon fas fa-exclamation-circle"></i>
        <div class="alert-content"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    </div>
<?php endif; ?>

<div class="generate-header">
    <div class="generate-header-content">
        <h1>
            <i class="fas fa-calculator"></i>
            Generate Payroll
        </h1>
        <p>Create payroll records for multiple departments in one operation</p>
    </div>
</div>


<?php if (!empty($departments)): ?>

<form method="POST" id="generateForm">
    <input type="hidden" name="generate_payroll" value="1">
    
    <!-- Period Configuration -->
    <div class="period-config-card">
        <div class="period-config-header">
            <i class="fas fa-calendar-alt"></i>
            <h2>Payroll Period Configuration</h2>
        </div>
        
        <div class="period-grid">
            <div class="period-field">
                <label><i class="fas fa-calendar"></i> Month</label>
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
            
            <div class="period-field">
                <label><i class="fas fa-calendar-year"></i> Year</label>
                <select name="payroll_year" id="payroll_year" class="form-control" required onchange="updatePeriodOptions()">
                    <?php
                    $currentYear = date('Y');
                    for($y = $currentYear + 1; $y >= $currentYear - 2; $y--):
                    ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="period-field">
                <label><i class="fas fa-clock"></i> Period Type</label>
                <select name="period_type" id="period_type" class="form-control" required>
                    <option value="1-15">1-15 (First Half)</option>
                    <option value="16-31">16-31 (Second Half)</option>
                    <option value="1-31">1-31 (Full Month)</option>
                </select>
            </div>
            
            <div class="period-field">
                <label><i class="fas fa-money-bill-wave"></i> Default PERA</label>
                <input type="number" name="default_pera" id="default_pera" class="form-control" 
                       value="2000.00" step="0.01" min="0" required>
            </div>
        </div>
        
        <!-- Additional Deductions -->
        <div class="deductions-section">
            <h3><i class="fas fa-minus-circle"></i> Additional Deductions (Applied to All Employees)</h3>
            <p>Note: GSIS (9%), PhilHealth, Pag-IBIG, and Withholding Tax are calculated automatically based on salary.</p>
            
            <div class="deductions-grid">
                <div class="deduction-field">
                    <label>Provident Fund</label>
                    <input type="number" name="provident" value="0.00" step="0.01" min="0">
                </div>
                <div class="deduction-field">
                    <label>BCGEU</label>
                    <input type="number" name="bcgeu" value="0.00" step="0.01" min="0">
                </div>
                <div class="deduction-field">
                    <label>NOCGEM</label>
                    <input type="number" name="nocgem" value="0.00" step="0.01" min="0">
                </div>
                <div class="deduction-field">
                    <label>BACGEM</label>
                    <input type="number" name="bacgem" value="0.00" step="0.01" min="0">
                </div>
                <div class="deduction-field">
                    <label>Other Deductions</label>
                    <input type="number" name="other_deductions" value="0.00" step="0.01" min="0">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Department Selection -->
    <div class="departments-selection-card">
        <div class="departments-header">
            <h2>
                <i class="fas fa-building"></i>
                Select Departments
            </h2>
            <button type="button" class="select-all-button" onclick="toggleAllDepartments()">
                <input type="checkbox" id="selectAllDepts" checked>
                <span>Select All</span>
            </button>
        </div>
        
        <div class="departments-grid">
            <?php foreach ($departments as $dept): ?>
                <label class="department-checkbox-card selected" id="dept-card-<?php echo $dept['id']; ?>">
                    <input type="checkbox" 
                           name="departments[]" 
                           value="<?php echo $dept['id']; ?>" 
                           class="dept-checkbox"
                           data-dept-id="<?php echo $dept['id']; ?>"
                           onchange="updateDepartmentCard(this)"
                           checked>
                    <div class="department-info">
                        <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                        <div style="display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem;">
                            <span class="department-badge"><?php echo htmlspecialchars($dept['department_code']); ?></span>
                            <span class="employee-count">
                                <i class="fas fa-users"></i>
                                <span><?php echo $dept['active_count']; ?> active</span>
                            </span>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Generate Summary -->
    
        <button type="submit" class="btn-generate" id="generateBtn">
            <i class="fas fa-bolt"></i> Generate Payroll
        </button>
    </div>
</form>

<?php else: ?>

<div class="card">
    <div class="card-body">
        <div class="no-departments">
            <i class="fas fa-building"></i>
            <h3>No Departments Found</h3>
            <p>Please create departments and add employees before generating payroll.</p>
            <a href="departments.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Create Department
            </a>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
// Department employee counts for calculation
const departmentCounts = {
    <?php foreach ($departments as $dept): ?>
    <?php echo $dept['id']; ?>: <?php echo $dept['active_count']; ?>,
    <?php endforeach; ?>
};

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

function getDaysInMonth(month, year) {
    const monthIndex = ['January','February','March','April','May','June','July','August','September','October','November','December'].indexOf(month);
    return new Date(year, monthIndex + 1, 0).getDate();
}

function updateDepartmentCard(checkbox) {
    const deptId = checkbox.getAttribute('data-dept-id');
    const card = document.getElementById('dept-card-' + deptId);
    
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
    
    updateCounts();
}

function toggleAllDepartments() {
    const selectAll = document.getElementById('selectAllDepts');
    const checkboxes = document.querySelectorAll('.dept-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
        updateDepartmentCard(cb);
    });
    
    updateCounts();
}

function updateCounts() {
    const checkedBoxes = document.querySelectorAll('.dept-checkbox:checked');
    const deptCount = checkedBoxes.length;
    
    let totalEmployees = 0;
    checkedBoxes.forEach(cb => {
        const deptId = parseInt(cb.value);
        totalEmployees += departmentCounts[deptId] || 0;
    });
    
    document.getElementById('deptCount').textContent = deptCount;
    document.getElementById('empCount').textContent = totalEmployees;
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.dept-checkbox');
    const selectAll = document.getElementById('selectAllDepts');
    selectAll.checked = checkedBoxes.length === allCheckboxes.length;
    
    // Enable/disable generate button
    const generateBtn = document.getElementById('generateBtn');
    generateBtn.disabled = deptCount === 0;
}

// Form submission
document.getElementById('generateForm').addEventListener('submit', function(e) {
    const checkedCount = document.querySelectorAll('.dept-checkbox:checked').length;
    const totalEmployees = parseInt(document.getElementById('empCount').textContent);
    
    if (checkedCount === 0) {
        e.preventDefault();
        alert('Please select at least one department.');
        return false;
    }
    
    const month = document.getElementById('payroll_month').value;
    const year = document.getElementById('payroll_year').value;
    const period = document.getElementById('period_type').value;
    
    const confirmMsg = `Generate payroll for:\n\n` +
        `• ${checkedCount} department(s)\n` +
        `• ${totalEmployees} employee(s)\n` +
        `• Period: ${month} ${period}, ${year}\n\n` +
        `This will create payroll records for all selected employees.\n` +
        `Existing records will be skipped.\n\n` +
        `Continue?`;
    
    if (!confirm(confirmMsg)) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    const btn = document.getElementById('generateBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodOptions();
    updateCounts();
});

// Update select all when individual checkboxes change
document.querySelectorAll('.dept-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('.dept-checkbox');
        const checkedBoxes = document.querySelectorAll('.dept-checkbox:checked');
        const selectAll = document.getElementById('selectAllDepts');
        selectAll.checked = checkedBoxes.length === allCheckboxes.length;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>