    <!-- Department Summary -->

<?php
/**
 * Payroll System - Dashboard
 */

require_once 'includes/config.php';

$pageTitle = 'Dashboard';

// Get statistics
$stats = [];

// Total Employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE is_active = 1");
$stats['employees'] = $result->fetch_assoc()['count'];

// Total Departments
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
$stats['departments'] = $result->fetch_assoc()['count'];

// This Month's Payroll
$currentMonth = date('F');
$currentYear = date('Y');
$result = $conn->query("SELECT COALESCE(SUM(net_pay), 0) as total FROM payroll WHERE payroll_month = '$currentMonth' AND payroll_year = $currentYear");
$stats['monthlyPayroll'] = $result->fetch_assoc()['total'];

// Pending Payrolls
$result = $conn->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'Draft'");
$stats['pendingPayroll'] = $result->fetch_assoc()['count'];

// Recent Payroll Records
$recentPayroll = $conn->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_id, d.department_name
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    ORDER BY p.created_at DESC
    LIMIT 10
");

// Department Summary
$deptSummary = $conn->query("
    SELECT d.department_name, d.department_code, COUNT(e.id) as employee_count
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id AND e.is_active = 1
    GROUP BY d.id
    ORDER BY employee_count DESC
    LIMIT 5
");

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Dashboard</span>
    </div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Welcome to the Payroll Management System</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['employees']); ?></div>
        <div class="stat-label">Active Employees</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['departments']); ?></div>
        <div class="stat-label">Departments</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-peso-sign"></i>
        </div>
        <div class="stat-value"><?php echo formatCurrency($stats['monthlyPayroll']); ?></div>
        <div class="stat-label"><?php echo $currentMonth; ?> <?php echo $currentYear; ?> Payroll</div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-value"><?php echo number_format($stats['pendingPayroll']); ?></div>
        <div class="stat-label">Pending Payroll</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-xl);">
    <!-- Recent Payroll -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-history"></i>
                Recent Payroll Records
            </h2>
            <a href="payroll.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Net Pay</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentPayroll && $recentPayroll->num_rows > 0): ?>
                            <?php while($row = $recentPayroll->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="employee-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                                <div class="employee-id"><?php echo htmlspecialchars($row['emp_id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['payroll_month'] . ' ' . $row['period_type'] . ', ' . $row['payroll_year']); ?>
                                    </td>
                                    <td class="currency"><?php echo formatCurrency($row['net_pay']); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'badge-warning';
                                        if ($row['status'] == 'Approved') $statusClass = 'badge-info';
                                        if ($row['status'] == 'Paid') $statusClass = 'badge-success';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $row['status']; ?></span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding: 2rem;">
                                    No payroll records yet. <a href="payroll_create.php">Create one now</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Department Summary -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-sitemap"></i>
                Department Summary
            </h2>
        </div>
        <div class="card-body">
            <?php if ($deptSummary && $deptSummary->num_rows > 0): ?>
                <?php while($dept = $deptSummary->fetch_assoc()): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-100);">
                        <div>
                            <div style="font-weight: 600; color: var(--gray-800);">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                <?php echo htmlspecialchars($dept['department_code']); ?>
                            </div>
                        </div>
                        <div class="badge badge-primary">
                            <?php echo $dept['employee_count']; ?> staff
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-muted text-center">No departments found</p>
            <?php endif; ?>
            
            <div style="margin-top: 1rem;">
                <a href="departments.php" class="btn btn-secondary btn-sm" style="width: 100%;">
                    <i class="fas fa-plus"></i> Manage Departments
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-top: var(--space-xl);">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-bolt"></i>
            Quick Actions
        </h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg);">
            <a href="employees.php?action=add" class="btn btn-primary btn-lg" style="justify-content: flex-start;">
                <i class="fas fa-user-plus"></i>
                Add Employee
            </a>
            <a href="payroll_create.php" class="btn btn-success btn-lg" style="justify-content: flex-start;">
                <i class="fas fa-file-invoice-dollar"></i>
                Create Payroll
            </a>
            <a href="payroll_generate.php" class="btn btn-warning btn-lg" style="justify-content: flex-start;">
                <i class="fas fa-calculator"></i>
                Generate Payroll
            </a>
            
             
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
