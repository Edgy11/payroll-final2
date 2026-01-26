<?php
/**
 * Payroll System - Reports
 */

require_once 'includes/config.php';

$pageTitle = 'Reports';

// Get filter parameters
$filterMonth = isset($_GET['month']) ? sanitize($_GET['month']) : date('F');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Monthly Summary
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
    WHERE payroll_month = '$filterMonth' AND payroll_year = $filterYear
")->fetch_assoc();

// Department Summary
$deptSummary = $conn->query("
    SELECT d.department_name, d.department_code,
           COUNT(DISTINCT p.employee_id) as employee_count,
           COALESCE(SUM(p.net_pay), 0) as total_net
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE p.payroll_month = '$filterMonth' AND p.payroll_year = $filterYear
    GROUP BY d.id
    ORDER BY total_net DESC
");

// Yearly Trend
$yearlyTrend = $conn->query("
    SELECT payroll_month, 
           COALESCE(SUM(net_pay), 0) as total_net,
           COUNT(DISTINCT employee_id) as employee_count
    FROM payroll
    WHERE payroll_year = $filterYear
    GROUP BY payroll_month
    ORDER BY FIELD(payroll_month, 'January','February','March','April','May','June','July','August','September','October','November','December')
");

require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Reports</span>
    </div>
    <h1 class="page-title">Payroll Reports</h1>
    <p class="page-subtitle">View payroll summaries and analytics</p>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <select name="month" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <?php 
                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach($months as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filterMonth === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </form>
    </div>
</div>

<!-- Monthly Summary -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-chart-pie"></i> Monthly Summary - <?php echo $filterMonth . ' ' . $filterYear; ?></h2>
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
                            <tr>
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code'] ?: 'N/A'); ?></span>
                                    <?php echo htmlspecialchars($row['department_name'] ?: 'Unassigned'); ?>
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

<?php require_once 'includes/footer.php'; ?>
