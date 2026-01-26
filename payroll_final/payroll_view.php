<?php
/**
 * Payroll System - View Payroll
 */

require_once 'includes/config.php';

$pageTitle = 'View Payroll';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: payroll.php');
    exit;
}

// Get payroll record
$payroll = $conn->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_id, e.email, e.phone,
           d.department_name, d.department_code, pos.position_title, pos.salary_grade
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

<div class="page-header no-print">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>View</span>
    </div>
    <h1 class="page-title">Payroll Details</h1>
</div>

<div class="card">
    <!-- Payroll Header -->
    <div class="payroll-header">
        <div class="payroll-header-content">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <h1 class="payroll-title">GENERAL PAYROLL</h1>
                    <p class="payroll-period">City Mayor's Office</p>
                </div>
                <div class="no-print">
                    <span class="badge <?php 
                        echo $payroll['status'] === 'Paid' ? 'badge-success' : 
                            ($payroll['status'] === 'Approved' ? 'badge-info' : 'badge-warning'); 
                    ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                        <?php echo $payroll['status']; ?>
                    </span>
                </div>
            </div>
            <div class="payroll-meta">
                <div class="payroll-meta-item">
                    <span class="payroll-meta-label">Period</span>
                    <span class="payroll-meta-value"><?php echo $payroll['payroll_month'] . ' ' . $payroll['period_type'] . ', ' . $payroll['payroll_year']; ?></span>
                </div>
                <div class="payroll-meta-item">
                    <span class="payroll-meta-label">Agency</span>
                    <span class="payroll-meta-value"><?php echo htmlspecialchars($payroll['department_name'] ?: 'City Mayor\'s Office'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Employee Info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h3 style="margin-bottom: 1rem; color: var(--gray-600); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Employee Information</h3>
                <div class="employee-info" style="margin-bottom: 1rem;">
                    <div class="employee-avatar" style="width: 60px; height: 60px; font-size: 1.25rem;">
                        <?php echo strtoupper(substr($payroll['first_name'], 0, 1) . substr($payroll['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-size: 1.25rem; font-weight: 700;"><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></div>
                        <div class="text-muted"><?php echo htmlspecialchars($payroll['emp_id']); ?></div>
                    </div>
                </div>
                <table style="width: 100%;">
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Position:</td>
                        <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($payroll['position_title'] ?: '-'); ?> (SG-<?php echo $payroll['salary_grade']; ?>)</td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Department:</td>
                        <td style="padding: 0.5rem 0;"><?php echo htmlspecialchars($payroll['department_name'] ?: '-'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div>
                <h3 style="margin-bottom: 1rem; color: var(--gray-600); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Earnings</h3>
                <table style="width: 100%;">
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">Basic Salary:</td>
                        <td class="text-right font-mono" style="padding: 0.5rem 0;"><?php echo formatCurrency($payroll['basic_salary']); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding: 0.5rem 0;">PERA:</td>
                        <td class="text-right font-mono" style="padding: 0.5rem 0;"><?php echo formatCurrency($payroll['pera']); ?></td>
                    </tr>
                    <tr style="border-top: 2px solid var(--gray-200);">
                        <td style="padding: 0.75rem 0; font-weight: 700;">Gross Pay:</td>
                        <td class="text-right font-mono" style="padding: 0.75rem 0; font-weight: 700; font-size: 1.125rem; color: var(--primary-600);"><?php echo formatCurrency($payroll['gross_pay']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Deductions -->
        <h3 style="margin-bottom: 1rem; color: var(--gray-600); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">Deductions</h3>
        <div class="deduction-grid" style="margin-bottom: 2rem;">
            <div class="deduction-item">
                <div class="deduction-label">GSIS</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['gsis']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">PhilHealth</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['philhealth']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">Pag-IBIG</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['pagibig']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">W-Tax</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['wtax']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">Provident</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['provident']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">BCGEU</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['bcgeu']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">NOCGEM</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['nocgem']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">BACGEM</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['bacgem']); ?></div>
            </div>
            <div class="deduction-item">
                <div class="deduction-label">Others</div>
                <div class="deduction-value"><?php echo formatCurrency($payroll['other_deductions']); ?></div>
            </div>
        </div>
        
        <!-- Summary -->
        <div style="background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg); display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
            <div>
                <div class="text-muted" style="font-size: 0.875rem;">Total Deductions</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--danger-500);"><?php echo formatCurrency($payroll['total_deductions']); ?></div>
            </div>
            <div>
                <div class="text-muted" style="font-size: 0.875rem;">Net Pay</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--success-500);"><?php echo formatCurrency($payroll['net_pay']); ?></div>
            </div>
            <div class="no-print">
                <div class="text-muted" style="font-size: 0.875rem;">Actions</div>
                <div class="btn-group" style="margin-top: 0.5rem;">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="payroll_edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-footer">
        <p class="text-muted text-center" style="font-size: 0.8125rem;">
            We acknowledge receipt of the sum shown opposite our names as full compensation for services rendered for the period stated.
            <br>Printed on: <?php echo date('F d, Y h:i A'); ?>
        </p>
    </div>
</div>

<div class="no-print" style="margin-top: 1.5rem;">
    <a href="payroll.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Payroll List
    </a>
</div>

<?php require_once 'includes/footer.php'; ?>
