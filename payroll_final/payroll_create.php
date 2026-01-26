<?php
/**
 * Payroll System - Create Payroll
 * Complete version with all database fields
 */

require_once 'includes/config.php';

$pageTitle = 'Create Payroll';
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Employee and Period Info
    $employeeId = (int)$_POST['employee_id'];
    $departmentId = (int)$_POST['department_id'];
    $salaryId = (int)$_POST['salary_id'];
    $payrollPeriod = sanitize($_POST['payroll_period']);
    $payrollMonth = sanitize($_POST['payroll_month']);
    $payrollYear = (int)$_POST['payroll_year'];
    $periodType = sanitize($_POST['period_type']);
    
    // Salary Info
    $basicSalary = (float)$_POST['basic_salary'];
    $pera = (float)$_POST['pera'];
    $grossPay = (float)$_POST['gross_pay'];
    
    // Deductions
    $wtax = (float)$_POST['wtax'];
    $philhealth = (float)$_POST['philhealth'];
    $gsis = (float)$_POST['gsis'];
    $pagibig = (float)$_POST['pagibig'];
    $provident = (float)$_POST['provident'];
    $bcgeu = (float)$_POST['bcgeu'];
    $nocgem = (float)$_POST['nocgem'];
    $bacgem = (float)$_POST['bacgem'];
    $otherDeductions = (float)$_POST['other_deductions'];
    $totalDeductions = (float)$_POST['total_deductions'];
    
    // Net Pay
    $netPay = (float)$_POST['net_pay'];
    $status = 'Active';
    
    // Check for duplicate payroll
    $checkStmt = $conn->prepare("SELECT id FROM payroll WHERE employee_id = ? AND payroll_month = ? AND payroll_year = ? AND period_type = ?");
    $checkStmt->bind_param("isis", $employeeId, $payrollMonth, $payrollYear, $periodType);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $message = 'Payroll record already exists for this employee and period!';
        $messageType = 'warning';
    } else {
        // Insert payroll record
        $stmt = $conn->prepare("
            INSERT INTO payroll (
                employee_id, department_id, salary_id, payroll_period, payroll_month, payroll_year, period_type,
                basic_salary, pera, gross_pay,
                wtax, philhealth, gsis, pagibig, provident, bcgeu, nocgem, bacgem, other_deductions,
                total_deductions, net_pay, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "iiissiisddddddddddddds",
            $employeeId, $departmentId, $salaryId, $payrollPeriod, $payrollMonth, $payrollYear, $periodType,
            $basicSalary, $pera, $grossPay,
            $wtax, $philhealth, $gsis, $pagibig, $provident, $bcgeu, $nocgem, $bacgem, $otherDeductions,
            $totalDeductions, $netPay, $status
        );
        
        if ($stmt->execute()) {
            $message = 'Payroll created successfully!';
            $messageType = 'success';
            // Redirect after 2 seconds
            header("refresh:2;url=payroll.php");
        } else {
            $message = 'Error creating payroll: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    $checkStmt->close();
}

// Get employees with their salary information
$employeesQuery = "
    SELECT 
        e.id, e.employee_id, e.first_name, e.last_name, e.middle_name, e.date_hired,
        e.department_id,
        d.department_name, d.department_code,
        p.position_title, p.salary_grade, p.basic_salary
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.is_active = 1
    ORDER BY e.last_name, e.first_name
";
$employeesResult = $conn->query($employeesQuery);

// Build employee data array with correct salaries based on step increment
$employeeData = [];
if ($employeesResult && $employeesResult->num_rows > 0) {
    while($emp = $employeesResult->fetch_assoc()) {
        // Calculate current step based on date hired
        $currentStep = 1;
        $yearsOfService = 0;
        if ($emp['date_hired']) {
            $hireDate = new DateTime($emp['date_hired']);
            $today = new DateTime();
            $yearsOfService = $hireDate->diff($today)->y;
            $currentStep = min(8, floor($yearsOfService / 3) + 1);
        }
        
        // Get correct salary from salary table based on salary grade and current step
        $correctSalary = $emp['basic_salary'];
        $salaryId = 0;
        
        if ($emp['salary_grade']) {
            $salaryQuery = $conn->prepare("
                SELECT salary_id, salary_rate 
                FROM salary 
                WHERE salary_grade = ? AND step_no = ?
                LIMIT 1
            ");
            $salaryQuery->bind_param("si", $emp['salary_grade'], $currentStep);
            $salaryQuery->execute();
            $salaryResult = $salaryQuery->get_result();
            
            if ($salaryData = $salaryResult->fetch_assoc()) {
                $correctSalary = $salaryData['salary_rate'];
                $salaryId = $salaryData['salary_id'];
            }
        }
        
        $employeeData[] = [
            'id' => $emp['id'],
            'employee_id' => $emp['employee_id'],
            'full_name' => $emp['last_name'] . ', ' . $emp['first_name'] . ($emp['middle_name'] ? ' ' . substr($emp['middle_name'], 0, 1) . '.' : ''),
            'department_id' => $emp['department_id'],
            'department' => $emp['department_name'],
            'department_code' => $emp['department_code'],
            'position' => $emp['position_title'],
            'salary_grade' => $emp['salary_grade'],
            'current_step' => $currentStep,
            'basic_salary' => $correctSalary,
            'salary_id' => $salaryId,
            'years_of_service' => $yearsOfService
        ];
    }
}

// Pre-select employee if passed via URL
$selectedEmployee = isset($_GET['employee']) ? (int)$_GET['employee'] : 0;

require_once 'includes/header.php';
?>

<style>
.payroll-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.employee-info-box {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border: 2px solid #2196f3;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.employee-info-box h4 {
    color: #1976d2;
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 0.75rem;
    color: #1565c0;
    font-weight: 600;
    text-transform: uppercase;
}

.info-value {
    font-size: 0.9rem;
    color: #0d47a1;
    font-weight: 600;
}

.salary-box {
    background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
    border: 2px solid #4caf50;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    margin-top: 1rem;
}

.salary-box h4 {
    color: #2e7d32;
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
}

.salary-amount {
    font-size: 2rem;
    font-weight: 700;
    color: #1b5e20;
}

.deduction-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.deduction-card {
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-left: 3px solid #2196f3;
    border-radius: 6px;
    padding: 1rem;
}

.deduction-label {
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
}

.deduction-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #333;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    text-align: center;
}

.summary-item {
    padding: 1.5rem;
}

.summary-label {
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.summary-value {
    font-size: 2rem;
    font-weight: 700;
}

.summary-value.gross {
    color: #2196f3;
}

.summary-value.deduction {
    color: #f44336;
}

.summary-value.net {
    color: #4caf50;
}

@media (max-width: 768px) {
    .payroll-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>Create</span>
    </div>
    <h1 class="page-title">Create Payroll</h1>
    <p class="page-subtitle">Generate new payroll record for an employee</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<form method="POST" id="payrollForm">
    <div class="payroll-grid">
        <!-- Left Column: Employee & Period Selection -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-user-tie"></i> Employee & Payroll Period
                </h2>
            </div>
            <div class="card-body">
                <!-- Employee Selection -->
                <div class="form-group">
                    <label class="form-label required">Select Employee</label>
                    <select name="employee_id" id="employee_id" class="form-control" required onchange="loadEmployeeData()">
                        <option value="">-- Choose Employee --</option>
                        <?php foreach($employeeData as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"
                                    data-dept-id="<?php echo $emp['department_id']; ?>"
                                    data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                                    data-dept-code="<?php echo htmlspecialchars($emp['department_code']); ?>"
                                    data-position="<?php echo htmlspecialchars($emp['position']); ?>"
                                    data-grade="<?php echo $emp['salary_grade']; ?>"
                                    data-step="<?php echo $emp['current_step']; ?>"
                                    data-salary="<?php echo $emp['basic_salary']; ?>"
                                    data-salary-id="<?php echo $emp['salary_id']; ?>"
                                    data-years="<?php echo $emp['years_of_service']; ?>"
                                    <?php echo $selectedEmployee == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name'] . ' - ' . $emp['employee_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Employee Info Display -->
                <div id="employee_info_display" style="display: none;">
                    <div class="employee-info-box">
                        <h4><i class="fas fa-info-circle"></i> Employee Information</h4>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value" id="display_department">-</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Position</div>
                                <div class="info-value" id="display_position">-</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Salary Grade</div>
                                <div class="info-value" id="display_grade">-</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Current Step</div>
                                <div class="info-value" id="display_step">-</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Years of Service</div>
                                <div class="info-value" id="display_years">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="salary-box">
                        <h4><i class="fas fa-money-bill-wave"></i> Monthly Basic Salary</h4>
                        <div class="salary-amount" id="display_salary">₱ 0.00</div>
                    </div>
                </div>
                
                <!-- Period Selection -->
                <div class="form-row" style="margin-top: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label required">Month</label>
                        <select name="payroll_month" id="payroll_month" class="form-control" required onchange="updatePeriodDisplay()">
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
                        <select name="payroll_year" id="payroll_year" class="form-control" required onchange="updatePeriodDisplay()">
                            <?php 
                            $currentYear = date('Y');
                            for($y = $currentYear; $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">Pay Period</label>
                    <select name="period_type" id="period_type" class="form-control" required onchange="updatePeriodDisplay()">
                        <option value="1-15">1-15 (First Half of Month)</option>
                        <option value="16-31">16-31 (Second Half of Month)</option>
                    </select>
                </div>
                
                <!-- Payroll Period Display -->
                <div class="form-group">
                    <label class="form-label">Payroll Period</label>
                    <input type="text" name="payroll_period" id="payroll_period" class="form-control" readonly style="background: #e3f2fd; font-weight: 600;">
                </div>
                
              
                <div class="form-group">
                    <label class="form-label required">Basic Salary (Monthly)</label>
                    <input type="number" name="basic_salary" id="basic_salary" class="form-control" step="0.01" required readonly style="background: #c8e6c9; font-weight: 700; font-size: 1.2rem; color: #1b5e20;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">PERA (Personnel Economic Relief Allowance)</label>
                    <input type="number" name="pera" id="pera" class="form-control" step="0.01" value="2000.00" onchange="calculatePayroll()">
                    <small class="form-hint">Standard PERA amount is ₱2,000.00</small>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Deductions -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-calculator"></i> Deductions
                </h2>
            </div>
            <div class="card-body">
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; color: #666;">
                    <i class="fas fa-cog"></i> Automatic Calculations
                </h4>
                
                <!-- Automatic Deductions Display -->
                <div class="deduction-grid">
                    <div class="deduction-card">
                        <div class="deduction-label">GSIS (9%)</div>
                        <div class="deduction-value" id="display_gsis">₱ 0.00</div>
                    </div>
                    <div class="deduction-card">
                        <div class="deduction-label">PhilHealth</div>
                        <div class="deduction-value" id="display_philhealth">₱ 0.00</div>
                    </div>
                    <div class="deduction-card">
                        <div class="deduction-label">Pag-IBIG</div>
                        <div class="deduction-value" id="display_pagibig">₱ 0.00</div>
                    </div>
                    <div class="deduction-card">
                        <div class="deduction-label">Withholding Tax</div>
                        <div class="deduction-value" id="display_wtax">₱ 0.00</div>
                    </div>
                </div>
                
                <hr style="margin: 1.5rem 0; border-color: #ddd;">
                
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; color: #666;">
                    <i class="fas fa-edit"></i> Other Deductions (Manual Input)
                </h4>
                
                <!-- Manual Deductions Input -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Provident Fund</label>
                        <input type="number" name="provident" id="provident" class="form-control" step="0.01" value="0.00" onchange="calculatePayroll()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BCGEU</label>
                        <input type="number" name="bcgeu" id="bcgeu" class="form-control" step="0.01" value="0.00" onchange="calculatePayroll()">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">NOCGEM</label>
                        <input type="number" name="nocgem" id="nocgem" class="form-control" step="0.01" value="0.00" onchange="calculatePayroll()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BACGEM</label>
                        <input type="number" name="bacgem" id="bacgem" class="form-control" step="0.01" value="0.00" onchange="calculatePayroll()">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Other Deductions</label>
                    <input type="number" name="other_deductions" id="other_deductions" class="form-control" step="0.01" value="0.00" onchange="calculatePayroll()">
                    <small class="form-hint">Loans, advances, etc.</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payroll Summary -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header" style="background: linear-gradient(135deg, #1976d2, #1565c0); color: white;">
            <h2 class="card-title" style="color: white;">
                <i class="fas fa-receipt"></i> Payroll Summary
            </h2>
        </div>
        <div class="card-body">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Gross Pay</div>
                    <div class="summary-value gross" id="display_gross">₱ 0.00</div>
                    <small class="text-muted">Basic Salary + PERA</small>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Deductions</div>
                    <div class="summary-value deduction" id="display_total_deductions">₱ 0.00</div>
                    <small class="text-muted">All deductions combined</small>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Net Pay</div>
                    <div class="summary-value net" id="display_net">₱ 0.00</div>
                    <small class="text-muted">Take home pay</small>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <button type="submit" class="btn btn-success btn-lg" style="min-width: 300px;">
                    <i class="fas fa-save"></i> Create Payroll Record
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden Fields -->
    <input type="hidden" name="department_id" id="department_id" value="0">
    <input type="hidden" name="salary_id" id="salary_id" value="0">
    <input type="hidden" name="gross_pay" id="gross_pay" value="0">
    <input type="hidden" name="wtax" id="wtax" value="0">
    <input type="hidden" name="philhealth" id="philhealth" value="0">
    <input type="hidden" name="gsis" id="gsis" value="0">
    <input type="hidden" name="pagibig" id="pagibig" value="0">
    <input type="hidden" name="total_deductions" id="total_deductions" value="0">
    <input type="hidden" name="net_pay" id="net_pay" value="0">
</form>

<script>
function formatCurrency(amount) {
    return '₱ ' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function loadEmployeeData() {
    const select = document.getElementById('employee_id');
    const option = select.options[select.selectedIndex];
    
    if (!option.value) {
        document.getElementById('employee_info_display').style.display = 'none';
        document.getElementById('basic_salary').value = '0.00';
        return;
    }
    
    // Show employee info
    document.getElementById('employee_info_display').style.display = 'block';
    
    // Populate employee data
    const deptId = option.dataset.deptId;
    const dept = option.dataset.dept + ' (' + option.dataset.deptCode + ')';
    const position = option.dataset.position;
    const grade = 'SG-' + option.dataset.grade;
    const step = 'Step ' + option.dataset.step + ' of 8';
    const salary = parseFloat(option.dataset.salary);
    const salaryId = option.dataset.salaryId;
    const years = option.dataset.years + ' years';
    
    document.getElementById('display_department').textContent = dept;
    document.getElementById('display_position').textContent = position;
    document.getElementById('display_grade').textContent = grade;
    document.getElementById('display_step').textContent = step;
    document.getElementById('display_years').textContent = years;
    document.getElementById('display_salary').textContent = formatCurrency(salary);
    
    document.getElementById('department_id').value = deptId;
    document.getElementById('salary_id').value = salaryId;
    document.getElementById('basic_salary').value = salary.toFixed(2);
    
    updatePeriodDisplay();
    calculatePayroll();
}

function updatePeriodDisplay() {
    const month = document.getElementById('payroll_month').value;
    const year = document.getElementById('payroll_year').value;
    const period = document.getElementById('period_type').value;
    
    if (month && year && period) {
        document.getElementById('payroll_period').value = month + ' ' + period + ', ' + year;
    }
}

function calculatePayroll() {
    const basicSalary = parseFloat(document.getElementById('basic_salary').value) || 0;
    const pera = parseFloat(document.getElementById('pera').value) || 0;
    
    // Calculate Gross Pay
    const grossPay = basicSalary + pera;
    
    // Calculate Mandatory Deductions
    const gsis = basicSalary * 0.09; // 9%
    const philhealth = calculatePhilHealth(basicSalary);
    const pagibig = calculatePagIbig(basicSalary);
    const wtax = calculateWithholdingTax(basicSalary);
    
    // Get Other Deductions
    const provident = parseFloat(document.getElementById('provident').value) || 0;
    const bcgeu = parseFloat(document.getElementById('bcgeu').value) || 0;
    const nocgem = parseFloat(document.getElementById('nocgem').value) || 0;
    const bacgem = parseFloat(document.getElementById('bacgem').value) || 0;
    const otherDeductions = parseFloat(document.getElementById('other_deductions').value) || 0;
    
    // Calculate Total Deductions
    const totalDeductions = gsis + philhealth + pagibig + wtax + provident + bcgeu + nocgem + bacgem + otherDeductions;
    
    // Calculate Net Pay
    const netPay = grossPay - totalDeductions;
    
    // Update Display
    document.getElementById('display_gsis').textContent = formatCurrency(gsis);
    document.getElementById('display_philhealth').textContent = formatCurrency(philhealth);
    document.getElementById('display_pagibig').textContent = formatCurrency(pagibig);
    document.getElementById('display_wtax').textContent = formatCurrency(wtax);
    document.getElementById('display_gross').textContent = formatCurrency(grossPay);
    document.getElementById('display_total_deductions').textContent = formatCurrency(totalDeductions);
    document.getElementById('display_net').textContent = formatCurrency(netPay);
    
    // Update Hidden Fields
    document.getElementById('gross_pay').value = grossPay.toFixed(2);
    document.getElementById('gsis').value = gsis.toFixed(2);
    document.getElementById('philhealth').value = philhealth.toFixed(2);
    document.getElementById('pagibig').value = pagibig.toFixed(2);
    document.getElementById('wtax').value = wtax.toFixed(2);
    document.getElementById('total_deductions').value = totalDeductions.toFixed(2);
    document.getElementById('net_pay').value = netPay.toFixed(2);
}

function calculatePhilHealth(basicSalary) {
    // PhilHealth 2024 contribution rates
    if (basicSalary <= 10000) return 450;
    if (basicSalary >= 80000) return 3600;
    return basicSalary * 0.045; // 4.5%
}

function calculatePagIbig(basicSalary) {
    // Pag-IBIG contribution rates
    if (basicSalary <= 1500) return basicSalary * 0.01; // 1%
    if (basicSalary <= 5000) return basicSalary * 0.02; // 2%
    return 100; // Maximum ₱100
}

function calculateWithholdingTax(monthlyBasic) {
    // Philippine withholding tax 2024 (simplified)
    if (monthlyBasic <= 20833) return 0;
    if (monthlyBasic <= 33332) return (monthlyBasic - 20833) * 0.15;
    if (monthlyBasic <= 66666) return 1875 + (monthlyBasic - 33333) * 0.20;
    if (monthlyBasic <= 166666) return 8541.80 + (monthlyBasic - 66667) * 0.25;
    if (monthlyBasic <= 666666) return 33541.80 + (monthlyBasic - 166667) * 0.30;
    return 183541.80 + (monthlyBasic - 666667) * 0.35;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodDisplay();
    
    // If employee is pre-selected, load their data
    if (document.getElementById('employee_id').value) {
        loadEmployeeData();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>