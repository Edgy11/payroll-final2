<?php
/**
 * Payroll System - Payroll List
 * Shows departments first, then payroll records when a department is selected
 * Bulk status change - all employees change together (only non-paid records)
 * Paid status is permanent
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Payroll Records';

$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle BULK status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_status_change']) && !empty($_POST['bulk_status_change'])) {
    $newStatus = sanitize($_POST['bulk_status_change']);
    $deptId = (int)$_POST['dept_id'];
    
    $allowedStatuses = ['Draft', 'Approved', 'Paid'];
    if (in_array($newStatus, $allowedStatuses) && $deptId > 0) {
        // Only update records that are NOT already paid
        $updateStmt = $conn->prepare("UPDATE payroll SET status = ?, updated_at = NOW() WHERE department_id = ? AND status != 'Paid'");
        $updateStmt->bind_param("si", $newStatus, $deptId);
        
        if ($updateStmt->execute()) {
            $affectedRows = $updateStmt->affected_rows;
            if ($affectedRows > 0) {
                $_SESSION['success_message'] = "Status changed to \"$newStatus\" for $affectedRows payroll record(s).";
            } else {
                $_SESSION['error_message'] = 'No records were updated. Records may already be paid.';
            }
        } else {
            $_SESSION['error_message'] = 'Error updating status: ' . $conn->error;
        }
        $updateStmt->close();
    }
    
    header('Location: payroll.php?department_id=' . $deptId);
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payroll_id'])) {
    $deleteId = (int)$_POST['delete_payroll_id'];
    
    $checkStmt = $conn->prepare("SELECT status FROM payroll WHERE id = ?");
    $checkStmt->bind_param("i", $deleteId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($checkResult && $checkResult['status'] === 'Paid') {
        $_SESSION['error_message'] = 'Cannot delete a paid payroll record.';
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM payroll WHERE id = ?");
        $deleteStmt->bind_param("i", $deleteId);
        
        if ($deleteStmt->execute()) {
            $_SESSION['success_message'] = 'Payroll record deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Error deleting payroll record.';
        }
        $deleteStmt->close();
    }
    
    header('Location: payroll.php?department_id=' . $selectedDeptId);
    exit;
}

// Get all departments with payroll counts
$departments = $conn->query("
    SELECT d.*, 
           COUNT(p.id) as total_payroll,
           SUM(CASE WHEN p.status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
           SUM(CASE WHEN p.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN p.status = 'Paid' THEN 1 ELSE 0 END) as paid_count
    FROM departments d 
    LEFT JOIN payroll p ON d.id = p.department_id
    GROUP BY d.id 
    ORDER BY d.department_name ASC
");

// Get selected department info
$selectedDept = null;
$currentDeptStatus = null;
if ($selectedDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $selectedDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
    
    // Get the current status of non-paid records
    $statusCheck = $conn->query("SELECT status FROM payroll WHERE department_id = $selectedDeptId AND status != 'Paid' LIMIT 1");
    if ($statusCheck && $statusCheck->num_rows > 0) {
        $currentDeptStatus = $statusCheck->fetch_assoc()['status'];
    }
}

// Get payroll records for selected department
$payrollRecords = null;
$totalRecords = 0;
$paidCount = 0;
$nonPaidCount = 0;
$allPaid = false;

if ($selectedDeptId > 0) {
    $countQuery = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN status != 'Paid' THEN 1 ELSE 0 END) as non_paid
        FROM payroll WHERE department_id = $selectedDeptId
    ");
    $counts = $countQuery->fetch_assoc();
    $totalRecords = $counts['total'];
    $paidCount = $counts['paid'];
    $nonPaidCount = $counts['non_paid'];
    $allPaid = ($totalRecords > 0 && $paidCount == $totalRecords);
    
    $query = "
        SELECT 
            p.*,
            e.employee_id as emp_number,
            e.first_name,
            e.last_name,
            e.middle_name,
            e.date_hired,
            d.department_name,
            d.department_code,
            s.step_no,
            s.salary_grade,
            s.salary_rate as current_salary_rate
        FROM payroll p
        LEFT JOIN employees e ON p.employee_id = e.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN salary s ON p.salary_id = s.salary_id
        WHERE p.department_id = ?
        ORDER BY e.last_name ASC, e.first_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selectedDeptId);
    $stmt->execute();
    $payrollRecords = $stmt->get_result();
}

require_once 'includes/header.php';
?>

<style>
.departments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.department-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.department-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4a8bc7, #234e78);
}

.department-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    border-color: #7eb3e0;
}

.department-card.has-payroll::before {
    background: linear-gradient(90deg, #10b981, #059669);
}

.department-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.department-icon {
    width: 56px; height: 56px;
    border-radius: 12px;
    background: linear-gradient(135deg, #e3f0fa, #b5d5f0);
    color: #234e78;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.department-card.has-payroll .department-icon {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #10b981;
}

.department-badge {
    padding: 4px 8px;
    background: #e3f0fa;
    color: #234e78;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.department-card-body h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.department-card-body p {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1rem;
}

.department-stats {
    display: flex;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f3f4f6;
}

.department-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4px 8px;
    border-radius: 6px;
    min-width: 60px;
}

.department-stat.draft { background: #fff3cd; }
.department-stat.approved { background: #cfe2ff; }
.department-stat.paid { background: #d1e7dd; }

.department-stat-value {
    font-size: 1.125rem;
    font-weight: 800;
    color: #111827;
}

.department-stat-label {
    font-size: 0.625rem;
    color: #4b5563;
    text-transform: uppercase;
}

.department-card-arrow {
    position: absolute;
    bottom: 1.5rem;
    right: 1.5rem;
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #f3f4f6;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.department-card:hover .department-card-arrow {
    background: #2d6394;
    color: white;
    transform: translateX(4px);
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

.selected-department-header {
    background: linear-gradient(135deg, #132840, #0c1929);
    color: white;
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.selected-department-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(201, 162, 39, 0.15) 0%, transparent 70%);
    border-radius: 50%;
}

.selected-department-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.selected-department-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.selected-department-icon {
    width: 64px; height: 64px;
    border-radius: 12px;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.selected-department-text h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 4px;
}

.selected-department-text p {
    color: #7eb3e0;
}

/* Bulk Status Control */
.bulk-status-control {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.bulk-status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.bulk-status-header h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #111827;
}

.bulk-status-header h3 i {
    color: #2d6394;
    margin-right: 8px;
}

.record-count {
    background: #f3f4f6;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #4b5563;
}

.bulk-status-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.bulk-status-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s;
    border: 3px solid;
    text-transform: uppercase;
    letter-spacing: 1px;
    min-width: 160px;
}

.bulk-status-btn:hover:not(.active) {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
}

.bulk-status-btn.draft {
    background: #fff3cd;
    color: #856404;
    border-color: #ffc107;
}
.bulk-status-btn.draft:hover, .bulk-status-btn.draft.active {
    background: #ffe69c;
    border-color: #e0a800;
}

.bulk-status-btn.approved {
    background: #cfe2ff;
    color: #084298;
    border-color: #0d6efd;
}
.bulk-status-btn.approved:hover, .bulk-status-btn.approved.active {
    background: #9ec5fe;
    border-color: #0a58ca;
}

.bulk-status-btn.paid {
    background: #d1e7dd;
    color: #0f5132;
    border-color: #198754;
}
.bulk-status-btn.paid:hover, .bulk-status-btn.paid.active {
    background: #a3cfbb;
    border-color: #146c43;
}

.paid-locked-notice {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #d1e7dd;
    border: 2px solid #198754;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    color: #0f5132;
}

.paid-locked-notice i { font-size: 1.5rem; }

.paid-locked-notice h4 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 2px;
}

.paid-locked-notice p {
    font-size: 0.875rem;
    opacity: 0.8;
    margin: 0;
}

.partial-paid-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    color: #856404;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    font-size: 0.875rem;
    font-weight: 700;
    border: 2px solid;
    border-radius: 6px;
    text-transform: uppercase;
}

.status-badge.draft {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.status-badge.approved {
    background: #cfe2ff;
    border-color: #0d6efd;
    color: #084298;
}

.status-badge.paid {
    background: #d1e7dd;
    border-color: #198754;
    color: #0f5132;
}

.status-help {
    margin-top: 1rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.status-help strong {
    color: #dc3545;
}
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<?php if ($selectedDeptId == 0): ?>
<!-- DEPARTMENT SELECTION VIEW -->

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Payroll</span>
    </div>
    <h1 class="page-title">Payroll</h1>
    <p class="page-subtitle">Select a department to view and manage payroll records</p>
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

<div class="departments-grid">
    <?php if ($departments && $departments->num_rows > 0): ?>
        <?php while($dept = $departments->fetch_assoc()): ?>
            <div class="department-card <?php echo $dept['total_payroll'] > 0 ? 'has-payroll' : ''; ?>" 
                 onclick="window.location.href='payroll.php?department_id=<?php echo $dept['id']; ?>'">
                <div class="department-card-header">
                    <div class="department-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <span class="department-badge"><?php echo htmlspecialchars($dept['department_code']); ?></span>
                </div>
                <div class="department-card-body">
                    <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                    <p><?php echo $dept['total_payroll']; ?> payroll record<?php echo $dept['total_payroll'] != 1 ? 's' : ''; ?></p>
                </div>
                <div class="department-stats">
                    <div class="department-stat draft">
                        <span class="department-stat-value"><?php echo $dept['draft_count'] ?: 0; ?></span>
                        <span class="department-stat-label">Draft</span>
                    </div>
                    <div class="department-stat approved">
                        <span class="department-stat-value"><?php echo $dept['approved_count'] ?: 0; ?></span>
                        <span class="department-stat-label">Approved</span>
                    </div>
                    <div class="department-stat paid">
                        <span class="department-stat-value"><?php echo $dept['paid_count'] ?: 0; ?></span>
                        <span class="department-stat-label">Paid</span>
                    </div>
                </div>
                <div class="department-card-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-body text-center" style="padding: 3rem;">
                <i class="fas fa-building" style="font-size: 3rem; color: #d1d5db; margin-bottom: 1rem;"></i>
                <h3 style="color: #4b5563;">No Departments Found</h3>
                <p style="color: #6b7280;">Please create departments first.</p>
                <a href="departments.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Create Department
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- PAYROLL LIST VIEW -->

<a href="payroll.php" class="back-link">
    <i class="fas fa-arrow-left"></i>
    Back to Departments
</a>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span><?php echo htmlspecialchars($selectedDept['department_code']); ?></span>
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

<div class="selected-department-header">
    <div class="selected-department-content">
        <div class="selected-department-info">
            <div class="selected-department-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="selected-department-text">
                <h2><?php echo htmlspecialchars($selectedDept['department_name']); ?></h2>
                <p>Payroll Records</p>
            </div>
        </div>
        <?php if (!$allPaid): ?>
        <a href="payroll_create.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);">
            <i class="fas fa-plus"></i> Add Payroll
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Status Control -->
<?php if ($totalRecords > 0): ?>
<div class="bulk-status-control">
    <div class="bulk-status-header">
        <h3><i class="fas fa-tasks"></i> Change Status for All Records</h3>
        <span class="record-count"><?php echo $totalRecords; ?> total (<?php echo $nonPaidCount; ?> can be changed)</span>
    </div>
    
    <?php if ($allPaid): ?>
        <div class="paid-locked-notice">
            <i class="fas fa-lock"></i>
            <div>
                <h4>All Payroll Records are PAID and Locked</h4>
                <p>This department's payroll has been marked as paid and cannot be changed.</p>
            </div>
        </div>
    <?php else: ?>
        <?php if ($paidCount > 0): ?>
            <div class="partial-paid-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <span><strong><?php echo $paidCount; ?> record(s) are already PAID</strong> and locked. Only <?php echo $nonPaidCount; ?> record(s) will be affected.</span>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="statusForm">
            <input type="hidden" name="dept_id" value="<?php echo $selectedDeptId; ?>">
            <input type="hidden" name="bulk_status_change" id="statusInput" value="">
            
            <div class="bulk-status-buttons">
                <button type="button" class="bulk-status-btn draft <?php echo $currentDeptStatus === 'Draft' ? 'active' : ''; ?>" onclick="changeStatus('Draft')">
                    ✏️ DRAFT
                </button>
                
                <button type="button" class="bulk-status-btn approved <?php echo $currentDeptStatus === 'Approved' ? 'active' : ''; ?>" onclick="changeStatus('Approved')">
                    ✅ APPROVED
                </button>
                
                <button type="button" class="bulk-status-btn paid" onclick="changeStatus('Paid')">
                    🔒 PAID
                </button>
            </div>
        </form>
        <p class="status-help">
            <i class="fas fa-info-circle"></i> Click a button to change status for all non-paid records. <strong>Warning: "PAID" is permanent and cannot be undone!</strong>
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i>
            Payroll Records
        </h2>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="payrollTable">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Period</th>
                        <th>Step Inc</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payrollRecords && $payrollRecords->num_rows > 0): ?>
                        <?php while($row = $payrollRecords->fetch_assoc()): 
                            $employeeName = $row['last_name'] . ', ' . $row['first_name'];
                            if ($row['middle_name']) {
                                $employeeName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
                            }
                            
                            $stepInc = '-';
                            $correctSalary = $row['basic_salary'];
                            
                            if ($row['date_hired']) {
                                $hireDate = new DateTime($row['date_hired']);
                                $today = new DateTime();
                                $yearsOfService = $hireDate->diff($today)->y;
                                $currentStep = min(8, floor($yearsOfService / 3) + 1);
                                $stepInc = $currentStep;
                                
                                if ($row['current_salary_rate']) {
                                    $correctSalary = $row['current_salary_rate'];
                                }
                            } elseif ($row['step_no']) {
                                $stepInc = $row['step_no'];
                                if ($row['current_salary_rate']) {
                                    $correctSalary = $row['current_salary_rate'];
                                }
                            }
                            
                            $correctGrossPay = $correctSalary + $row['pera'];
                            $correctNetPay = $correctGrossPay - $row['total_deductions'];
                            $rowIsPaid = $row['status'] === 'Paid';
                        ?>
                            <tr>
                                <td><strong><code><?php echo htmlspecialchars($row['emp_number']); ?></code></strong></td>
                                <td><strong><?php echo htmlspecialchars($employeeName); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['payroll_period']); ?></td>
                                <td>
                                    <?php if ($stepInc !== '-'): ?>
                                        <span class="badge" style="background: #fef3c7; color: #92400e;">
                                            <i class="fas fa-layer-group"></i> Step <?php echo $stepInc; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: #10b981;">₱<?php echo number_format($correctGrossPay, 2); ?></strong></td>
                                <td><strong style="color: #ef4444;">₱<?php echo number_format($row['total_deductions'], 2); ?></strong></td>
                                <td><strong style="color: #10b981;">₱<?php echo number_format($correctNetPay, 2); ?></strong></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                        <?php if ($rowIsPaid): ?>🔒 <?php endif; ?>
                                        <?php echo strtoupper($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="payroll_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-icon sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (!$rowIsPaid): ?>
                                            <a href="payroll_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-icon sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['emp_number'])); ?>')" class="btn btn-danger btn-icon sm" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted" style="padding: 2rem;">
                                <i class="fas fa-file-invoice-dollar" style="font-size: 2rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                                No payroll records found in this department.
                                <br>
                                <a href="payroll_create.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i> Create Payroll
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="delete_payroll_id" id="deletePayrollId">
</form>

<script>
$(document).ready(function() {
    if ($('#payrollTable tbody tr').length > 0 && $('#payrollTable tbody tr:first td').length === 9) {
        $('#payrollTable').DataTable({
            "pageLength": 25,
            "order": [[1, "asc"]],
            "columnDefs": [{ "orderable": false, "targets": [8] }]
        });
    }
});

function changeStatus(status) {
    var nonPaidCount = <?php echo $nonPaidCount; ?>;
    var msg = 'Change ' + nonPaidCount + ' record(s) to "' + status + '"?';
    
    if (status === 'Paid') {
        msg = '⚠️ WARNING!\n\nYou are about to mark ' + nonPaidCount + ' record(s) as "PAID".\n\nThis is PERMANENT and cannot be undone!\n\nPaid records:\n- Cannot be edited\n- Cannot be deleted\n- Cannot change status\n\nAre you absolutely sure?';
    }
    
    if (confirm(msg)) {
        document.getElementById('statusInput').value = status;
        document.getElementById('statusForm').submit();
    }
}

function confirmDelete(id, empId) {
    if (confirm('Delete payroll for ' + empId + '?')) {
        document.getElementById('deletePayrollId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php endif; ?>

<?php 
if (isset($stmt)) $stmt->close();
require_once 'includes/footer.php'; 
?>