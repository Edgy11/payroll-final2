<?php
/**
 * Payroll System - Payroll List
 */

require_once 'includes/config.php';

$pageTitle = 'Payroll';
$message = '';
$messageType = '';

// Handle status updates and deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE payroll SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $message = 'Payroll status updated!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM payroll WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = 'Payroll record deleted!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get filter parameters
$filterMonth = isset($_GET['month']) ? sanitize($_GET['month']) : '';
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filterPeriod = isset($_GET['period']) ? sanitize($_GET['period']) : '';
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filterDept = isset($_GET['department']) ? (int)$_GET['department'] : 0;

// Build query
$whereClause = "p.payroll_year = $filterYear";
if ($filterMonth !== '') $whereClause .= " AND p.payroll_month = '$filterMonth'";
if ($filterPeriod !== '') $whereClause .= " AND p.period_type = '$filterPeriod'";
if ($filterStatus !== '') $whereClause .= " AND p.status = '$filterStatus'";
if ($filterDept > 0) $whereClause .= " AND p.department_id = $filterDept";

// Get all departments for filter dropdown
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get payroll records
$payrolls = $conn->query("
    SELECT p.*, e.first_name, e.last_name, e.employee_id as emp_id, 
           d.department_name, d.department_code
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE $whereClause
    ORDER BY p.created_at DESC
");

// Get summary
$summary = $conn->query("
    SELECT COUNT(*) as total, COALESCE(SUM(net_pay), 0) as total_net
    FROM payroll p WHERE $whereClause
")->fetch_assoc();

require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Payroll</span>
    </div>
    <h1 class="page-title">Payroll Records</h1>
    <p class="page-subtitle">View and manage payroll records</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-value"><?php echo number_format($summary['total']); ?></div>
        <div class="stat-label">Total Records</div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
        <div class="stat-value"><?php echo formatCurrency($summary['total_net']); ?></div>
        <div class="stat-label">Total Net Pay</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-list"></i> Payroll List</h2>
        <div class="btn-group">
            <a href="payroll_create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Payroll
            </a>
            <button class="btn btn-secondary" onclick="exportToExcel('payrollTable', 'payroll')">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <select name="department" class="form-control" style="width: auto; min-width: 200px;" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php 
                $departments->data_seek(0);
                while($dept = $departments->fetch_assoc()): 
                ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $filterDept == $dept['id'] ? 'selected' : ''; ?>>
                        [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select name="month" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Months</option>
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
            <select name="period" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Periods</option>
                <option value="1-15" <?php echo $filterPeriod === '1-15' ? 'selected' : ''; ?>>1-15</option>
                <option value="16-31" <?php echo $filterPeriod === '16-31' ? 'selected' : ''; ?>>16-31</option>
            </select>
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="Draft" <?php echo $filterStatus === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="Approved" <?php echo $filterStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Paid" <?php echo $filterStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
            <?php if ($filterDept > 0 || $filterMonth !== '' || $filterPeriod !== '' || $filterStatus !== ''): ?>
                <a href="payroll.php?year=<?php echo $filterYear; ?>" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="payrollTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Period</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payrolls && $payrolls->num_rows > 0): ?>
                        <?php while($row = $payrolls->fetch_assoc()): ?>
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
                                    <?php if ($row['department_name']): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['department_name']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No Dept</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $row['payroll_month']; ?></strong>
                                    <br><small class="text-muted"><?php echo $row['period_type'] . ', ' . $row['payroll_year']; ?></small>
                                </td>
                                <td class="currency"><?php echo formatCurrency($row['gross_pay']); ?></td>
                                <td class="currency text-danger"><?php echo formatCurrency($row['total_deductions']); ?></td>
                                <td class="currency font-bold"><?php echo formatCurrency($row['net_pay']); ?></td>
                                <td>
                                    <select class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: 0.75rem;"
                                            onchange="updateStatus(<?php echo $row['id']; ?>, this.value)">
                                        <option value="Draft" <?php echo $row['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="Approved" <?php echo $row['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Paid" <?php echo $row['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="payroll_view.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-icon sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="payroll_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-icon sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-danger btn-icon sm" onclick="deletePayroll(<?php echo $row['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding: 2rem;">
                                No payroll records found. <a href="payroll_create.php">Create one now</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form method="POST" id="statusForm" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="id" id="status_id">
    <input type="hidden" name="status" id="status_value">
</form>

<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function updateStatus(id, status) {
    document.getElementById('status_id').value = id;
    document.getElementById('status_value').value = status;
    document.getElementById('statusForm').submit();
}

function deletePayroll(id) {
    if (confirm('Are you sure you want to delete this payroll record?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>