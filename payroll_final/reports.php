<?php
/**
 * Payroll System - Reports
 * With department detail view
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Reports';

// Get filter parameters
$filterMonth = isset($_GET['month']) ? sanitize($_GET['month']) : date('F');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filterDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Get department info if selected
$selectedDept = null;
if ($filterDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $filterDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
}

// Monthly Summary (filtered by department if selected)
$summaryWhere = "WHERE payroll_month = '$filterMonth' AND payroll_year = $filterYear";
if ($filterDeptId > 0) {
    $summaryWhere .= " AND department_id = $filterDeptId";
}

$monthlySummary = $conn->query("
    SELECT 
        COUNT(DISTINCT employee_id) as total_employees,
        COALESCE(SUM(basic_salary), 0) as total_basic,
        COALESCE(SUM(gross_pay), 0) as total_gross,
        COALESCE(SUM(total_deductions), 0) as total_deductions,
        COALESCE(SUM(net_pay), 0) as total_net,
        COALESCE(SUM(gsis), 0) as total_gsis,
        COALESCE(SUM(philhealth), 0) as total_philhealth,
        COALESCE(SUM(pagibig), 0) as total_pagibig,
        COALESCE(SUM(wtax), 0) as total_wtax
    FROM payroll
    $summaryWhere
")->fetch_assoc();

// Department Summary (only show if not viewing specific department)
$deptSummary = null;
if ($filterDeptId == 0) {
    $deptSummary = $conn->query("
        SELECT d.id, d.department_name, d.department_code,
               COUNT(DISTINCT p.employee_id) as employee_count,
               COALESCE(SUM(p.net_pay), 0) as total_net
        FROM payroll p
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE p.payroll_month = '$filterMonth' AND p.payroll_year = $filterYear
        GROUP BY d.id
        ORDER BY total_net DESC
    ");
}

// Yearly Trend (filtered by department if selected)
$trendWhere = "WHERE payroll_year = $filterYear";
if ($filterDeptId > 0) {
    $trendWhere .= " AND department_id = $filterDeptId";
}

$yearlyTrend = $conn->query("
    SELECT payroll_month, 
           COALESCE(SUM(net_pay), 0) as total_net,
           COUNT(DISTINCT employee_id) as employee_count
    FROM payroll
    $trendWhere
    GROUP BY payroll_month
    ORDER BY FIELD(payroll_month, 'January','February','March','April','May','June','July','August','September','October','November','December')
");

// Department Employee Details (only if department selected)
$employeeDetails = null;
if ($filterDeptId > 0) {
    $employeeDetails = $conn->query("
        SELECT 
            p.*,
            e.employee_id as emp_number,
            e.first_name,
            e.last_name,
            e.middle_name,
            pos.position_title
        FROM payroll p
        LEFT JOIN employees e ON p.employee_id = e.id
        LEFT JOIN positions pos ON e.position_id = pos.id
        WHERE p.department_id = $filterDeptId 
          AND p.payroll_month = '$filterMonth' 
          AND p.payroll_year = $filterYear
        ORDER BY e.last_name, e.first_name
    ");
}

require_once 'includes/header.php';
?>

<style>
.dept-link {
    cursor: pointer;
    transition: all 0.2s;
}
.dept-link:hover {
    background: #e0f2fe !important;
}
.dept-link td {
    cursor: pointer;
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #4b5563;
    font-weight: 600;
    margin-bottom: 1rem;
    text-decoration: none;
}
.back-link:hover {
    color: #2d6394;
}
.dept-header {
    background: linear-gradient(135deg, #132840, #0c1929);
    color: white;
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
}
.dept-header h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 4px;
}
.dept-header p {
    color: #7eb3e0;
}
.employee-payroll-table {
    width: 100%;
    border-collapse: collapse;
}
.employee-payroll-table th {
    background: #f3f4f6;
    padding: 12px 16px;
    text-align: left;
    font-weight: 700;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
    font-size: 0.8rem;
    text-transform: uppercase;
}
.employee-payroll-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}
.employee-payroll-table tr:hover {
    background: #f9fafb;
}
.employee-payroll-table .currency {
    text-align: right;
    font-family: 'Courier New', monospace;
}
.employee-payroll-table .total-row {
    background: #f0fdf4 !important;
    font-weight: 700;
}
.employee-payroll-table .total-row td {
    border-top: 2px solid #10b981;
}
.print-title {
    display: none;
}
@media print {
    .no-print { display: none !important; }
    .print-title { display: block; margin-bottom: 1rem; }
    .card { box-shadow: none !important; border: 1px solid #ddd; }
}
</style>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <?php if ($filterDeptId > 0): ?>
            <a href="reports.php?month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>">Reports</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($selectedDept['department_code']); ?></span>
        <?php else: ?>
            <span>Reports</span>
        <?php endif; ?>
    </div>
    <h1 class="page-title">Payroll Reports</h1>
    <p class="page-subtitle">
        <?php if ($filterDeptId > 0): ?>
            Department Report - <?php echo htmlspecialchars($selectedDept['department_name']); ?>
        <?php else: ?>
            View payroll summaries and analytics
        <?php endif; ?>
    </p>
</div>

<?php if ($filterDeptId > 0): ?>
<a href="reports.php?month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>" class="back-link no-print">
    <i class="fas fa-arrow-left"></i>
    Back to All Departments
</a>
<?php endif; ?>

<!-- Filters -->
<div class="card no-print" style="margin-bottom: 1.5rem;">
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <?php if ($filterDeptId > 0): ?>
                <input type="hidden" name="department_id" value="<?php echo $filterDeptId; ?>">
            <?php endif; ?>
            <select name="month" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <?php 
                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach($months as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <?php for($y = date('Y') + 1; $y >= 2020; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </form>
    </div>
</div>

<!-- Print Title -->
<div class="print-title">
    <h2 style="text-align: center; margin-bottom: 0.5rem;">
        <?php if ($filterDeptId > 0): ?>
            <?php echo htmlspecialchars($selectedDept['department_name']); ?> - Payroll Report
        <?php else: ?>
            Payroll Summary Report
        <?php endif; ?>
    </h2>
    <p style="text-align: center; color: #666;"><?php echo $filterMonth . ' ' . $filterYear; ?></p>
</div>

<?php if ($filterDeptId > 0): ?>
<!-- Department Header -->
<div class="dept-header no-print">
    <h2><i class="fas fa-building"></i> <?php echo htmlspecialchars($selectedDept['department_name']); ?></h2>
    <p><?php echo htmlspecialchars($selectedDept['department_code']); ?> • <?php echo $filterMonth . ' ' . $filterYear; ?></p>
</div>
<?php endif; ?>

<!-- Monthly Summary -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-chart-pie"></i> 
            <?php if ($filterDeptId > 0): ?>
                Department Summary - <?php echo $filterMonth . ' ' . $filterYear; ?>
            <?php else: ?>
                Monthly Summary - <?php echo $filterMonth . ' ' . $filterYear; ?>
            <?php endif; ?>
        </h2>
    </div>
    <div class="card-body">
        <div class="stats-grid">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo number_format($monthlySummary['total_employees']); ?></div>
                <div class="stat-label">Employees Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                <div class="stat-value"><?php echo formatCurrency($monthlySummary['total_gross']); ?></div>
                <div class="stat-label">Total Gross Pay</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-minus-circle"></i></div>
                <div class="stat-value"><?php echo formatCurrency($monthlySummary['total_deductions']); ?></div>
                <div class="stat-label">Total Deductions</div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-value"><?php echo formatCurrency($monthlySummary['total_net']); ?></div>
                <div class="stat-label">Total Net Pay</div>
            </div>
        </div>
        
        <h3 style="margin: 2rem 0 1rem; font-size: 1rem; color: var(--gray-600);">Deduction Breakdown</h3>
        <div class="deduction-grid">
            <div class="deduction-item">
                <div class="deduction-label">GSIS</div>
                <div class="deduction-value"><?php echo formatCurrency($monthlySummary['total_gsis']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">PhilHealth</div>
                <div class="deduction-value"><?php echo formatCurrency($monthlySummary['total_philhealth']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">Pag-IBIG</div>
                <div class="deduction-value"><?php echo formatCurrency($monthlySummary['total_pagibig']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">Withholding Tax</div>
                <div class="deduction-value"><?php echo formatCurrency($monthlySummary['total_wtax']); ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($filterDeptId > 0 && $employeeDetails): ?>
<!-- Employee Details Table (Department View) -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-users"></i> Employee Payroll Details</h2>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="employee-payroll-table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Employee Name</th>
                    <th>Position</th>
                    <th>Period</th>
                    <th class="currency">Basic Salary</th>
                    <th class="currency">PERA</th>
                    <th class="currency">Gross Pay</th>
                    <th class="currency">Deductions</th>
                    <th class="currency">Net Pay</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandTotalBasic = 0;
                $grandTotalPera = 0;
                $grandTotalGross = 0;
                $grandTotalDeductions = 0;
                $grandTotalNet = 0;
                
                if ($employeeDetails->num_rows > 0): 
                    while($row = $employeeDetails->fetch_assoc()): 
                        $empName = $row['last_name'] . ', ' . $row['first_name'];
                        if ($row['middle_name']) $empName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
                        
                        $grandTotalBasic += $row['basic_salary'];
                        $grandTotalPera += $row['pera'];
                        $grandTotalGross += $row['gross_pay'];
                        $grandTotalDeductions += $row['total_deductions'];
                        $grandTotalNet += $row['net_pay'];
                ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($row['emp_number']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($empName); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['position_title'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['payroll_period']); ?></td>
                        <td class="currency"><?php echo formatCurrency($row['basic_salary']); ?></td>
                        <td class="currency"><?php echo formatCurrency($row['pera']); ?></td>
                        <td class="currency" style="color: #10b981; font-weight: 600;"><?php echo formatCurrency($row['gross_pay']); ?></td>
                        <td class="currency" style="color: #ef4444;"><?php echo formatCurrency($row['total_deductions']); ?></td>
                        <td class="currency" style="color: #10b981; font-weight: 700;"><?php echo formatCurrency($row['net_pay']); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo $row['status'] == 'Paid' ? '#d1e7dd' : ($row['status'] == 'Approved' ? '#cfe2ff' : '#fff3cd'); ?>; color: <?php echo $row['status'] == 'Paid' ? '#0f5132' : ($row['status'] == 'Approved' ? '#084298' : '#856404'); ?>;">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                <?php 
                    endwhile; 
                ?>
                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
                    <td class="currency"><?php echo formatCurrency($grandTotalBasic); ?></td>
                    <td class="currency"><?php echo formatCurrency($grandTotalPera); ?></td>
                    <td class="currency" style="color: #10b981;"><?php echo formatCurrency($grandTotalGross); ?></td>
                    <td class="currency" style="color: #ef4444;"><?php echo formatCurrency($grandTotalDeductions); ?></td>
                    <td class="currency" style="color: #10b981;"><?php echo formatCurrency($grandTotalNet); ?></td>
                    <td></td>
                </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted" style="padding: 2rem;">No payroll records found for this period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Department Monthly Trend -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-line"></i> Department Yearly Trend - <?php echo $filterYear; ?></h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Employees</th>
                    <th>Total Net Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($yearlyTrend && $yearlyTrend->num_rows > 0): ?>
                    <?php while($row = $yearlyTrend->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['payroll_month']; ?></td>
                            <td><?php echo $row['employee_count']; ?></td>
                            <td class="currency font-bold"><?php echo formatCurrency($row['total_net']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">No data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- All Departments View -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    
    <!-- Department Summary -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-building"></i> By Department</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Total Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($deptSummary && $deptSummary->num_rows > 0): ?>
                        <?php while($row = $deptSummary->fetch_assoc()): ?>
                            <tr class="dept-link" onclick="window.location.href='reports.php?month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>&department_id=<?php echo $row['id']; ?>'">
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code'] ?: 'N/A'); ?></span>
                                    <?php echo htmlspecialchars($row['department_name'] ?: 'Unassigned'); ?>
                                    <i class="fas fa-chevron-right" style="float: right; color: #9ca3af;"></i>
                                </td>
                                <td><?php echo $row['employee_count']; ?></td>
                                <td class="currency font-bold"><?php echo formatCurrency($row['total_net']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Yearly Trend -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-line"></i> Yearly Trend - <?php echo $filterYear; ?></h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Employees</th>
                        <th>Total Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($yearlyTrend && $yearlyTrend->num_rows > 0): ?>
                        <?php while($row = $yearlyTrend->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['payroll_month']; ?></td>
                                <td><?php echo $row['employee_count']; ?></td>
                                <td class="currency font-bold"><?php echo formatCurrency($row['total_net']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>