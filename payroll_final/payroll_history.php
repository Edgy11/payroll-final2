<?php
/**
 * Payroll System - Payroll History
 * Shows complete history of all payroll records across all departments
 * Filterable by department, period, and status
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Payroll History';

// Get filter parameters
$filterDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filterMonth = isset($_GET['month']) ? sanitize($_GET['month']) : '';
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Get all departments for filter dropdown
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");

// Get all available periods (month/year combinations)
$periodsQuery = $conn->query("
    SELECT DISTINCT payroll_month, payroll_year
    FROM payroll 
    ORDER BY payroll_year DESC, 
             FIELD(payroll_month, 'December','November','October','September','August','July','June','May','April','March','February','January')
");
$availablePeriods = [];
while ($p = $periodsQuery->fetch_assoc()) {
    $availablePeriods[] = $p;
}

// Build WHERE clause for filters
$whereConditions = [];
$params = [];
$types = "";

if ($filterDeptId > 0) {
    $whereConditions[] = "p.department_id = ?";
    $params[] = $filterDeptId;
    $types .= "i";
}

if ($filterMonth && $filterYear) {
    $whereConditions[] = "p.payroll_month = ?";
    $whereConditions[] = "p.payroll_year = ?";
    $params[] = $filterMonth;
    $params[] = $filterYear;
    $types .= "si";
}

if ($filterStatus) {
    $whereConditions[] = "p.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get summary statistics
$summaryQuery = "
    SELECT 
        COUNT(*) as total_records,
        SUM(p.gross_pay) as total_gross,
        SUM(p.total_deductions) as total_deductions,
        SUM(p.net_pay) as total_net,
        SUM(CASE WHEN p.status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN p.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN p.status = 'Paid' THEN 1 ELSE 0 END) as paid_count
    FROM payroll p
    $whereClause
";

if (!empty($params)) {
    $summaryStmt = $conn->prepare($summaryQuery);
    $summaryStmt->bind_param($types, ...$params);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();
    $summaryStmt->close();
} else {
    $summary = $conn->query($summaryQuery)->fetch_assoc();
}

// Get payroll history records
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
    $whereClause
    ORDER BY p.payroll_year DESC, 
             FIELD(p.payroll_month, 'December','November','October','September','August','July','June','May','April','March','February','January'),
             d.department_name ASC,
             e.last_name ASC,
             e.first_name ASC
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $payrollRecords = $stmt->get_result();
} else {
    $payrollRecords = $conn->query($query);
}

require_once 'includes/header.php';
?>

<style>
.history-header {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.history-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.history-header-content {
    position: relative;
    z-index: 1;
}

.history-header h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.history-header p {
    font-size: 1.125rem;
    opacity: 0.9;
}

.filter-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.filter-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.filter-header h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
}

.filter-header i {
    color: #1e40af;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.filter-group select {
    padding: 10px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #111827;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-group select:focus {
    outline: none;
    border-color: #1e40af;
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.summary-card.total { border-left-color: #1e40af; }
.summary-card.gross { border-left-color: #10b981; }
.summary-card.deductions { border-left-color: #ef4444; }
.summary-card.net { border-left-color: #8b5cf6; }

.summary-card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 600;
}

.summary-card-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #111827;
}

.summary-card-footer {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: #6b7280;
}

.status-breakdown {
    display: flex;
    gap: 1rem;
    margin-top: 0.75rem;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-chip.draft {
    background: #fff3cd;
    color: #856404;
}

.status-chip.approved {
    background: #cfe2ff;
    color: #084298;
}

.status-chip.paid {
    background: #d1e7dd;
    color: #0f5132;
}

.export-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.no-records {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.no-records i {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.no-records h3 {
    font-size: 1.5rem;
    color: #374151;
    margin-bottom: 0.5rem;
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

.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f3f4f6;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    color: #374151;
}

.filter-tag strong {
    color: #1e40af;
}

.filter-tag button {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
}

.filter-tag button:hover {
    color: #ef4444;
}
</style>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="payroll.php">Payroll</a>
        <span>/</span>
        <span>History</span>
    </div>
</div>

<div class="history-header">
    <div class="history-header-content">
        <h1><i class="fas fa-history"></i> Payroll History</h1>
        <p>Complete history of all payroll records across all departments</p>
    </div>
</div>

<!-- Filter Card -->
<div class="filter-card">
    <div class="filter-header">
        <i class="fas fa-filter"></i>
        <h3>Filter Records</h3>
    </div>
    
    <form method="GET" id="filterForm">
        <div class="filter-grid">
            <div class="filter-group">
                <label for="department_id">
                    <i class="fas fa-building"></i> Department
                </label>
                <select name="department_id" id="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php 
                    $departments->data_seek(0);
                    while($dept = $departments->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $filterDeptId == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="period_select">
                    <i class="fas fa-calendar"></i> Period
                </label>
                <select name="period_select" id="period_select" class="form-control">
                    <option value="">All Periods</option>
                    <?php foreach($availablePeriods as $p): 
                        $periodValue = $p['payroll_month'] . '|' . $p['payroll_year'];
                        $isSelected = ($p['payroll_month'] == $filterMonth && $p['payroll_year'] == $filterYear);
                    ?>
                        <option value="<?php echo $periodValue; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                            <?php echo $p['payroll_month'] . ' ' . $p['payroll_year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status">
                    <i class="fas fa-tag"></i> Status
                </label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Draft" <?php echo $filterStatus == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="Approved" <?php echo $filterStatus == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Paid" <?php echo $filterStatus == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Apply Filters
            </button>
           
        </div>
        
        <!-- Active Filters Display -->
        <?php if ($filterDeptId || $filterMonth || $filterStatus): ?>
        <div class="active-filters">
            <span style="color: #6b7280; font-weight: 600; font-size: 0.875rem;">Active Filters:</span>
            
            <?php if ($filterDeptId): 
                $deptName = $conn->query("SELECT department_name FROM departments WHERE id = $filterDeptId")->fetch_assoc()['department_name'];
            ?>
            <div class="filter-tag">
                <strong>Department:</strong> <?php echo htmlspecialchars($deptName); ?>
                <button type="button" onclick="removeFilter('department_id')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <?php if ($filterMonth && $filterYear): ?>
            <div class="filter-tag">
                <strong>Period:</strong> <?php echo $filterMonth . ' ' . $filterYear; ?>
                <button type="button" onclick="removeFilter('period')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <?php if ($filterStatus): ?>
            <div class="filter-tag">
                <strong>Status:</strong> <?php echo $filterStatus; ?>
                <button type="button" onclick="removeFilter('status')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card total">
        <div class="summary-card-header">
            <i class="fas fa-file-invoice"></i>
            <span>Total Records</span>
        </div>
        <div class="summary-card-value"><?php echo number_format($summary['total_records']); ?></div>
        <div class="status-breakdown">
            <span class="status-chip draft">
                <i class="fas fa-circle"></i> <?php echo $summary['draft_count']; ?> Draft
            </span>
            <span class="status-chip approved">
                <i class="fas fa-circle"></i> <?php echo $summary['approved_count']; ?> Approved
            </span>
            <span class="status-chip paid">
                <i class="fas fa-circle"></i> <?php echo $summary['paid_count']; ?> Paid
            </span>
        </div>
    </div>
    
    <div class="summary-card gross">
        <div class="summary-card-header">
            <i class="fas fa-money-bill-wave"></i>
            <span>Total Gross Pay</span>
        </div>
        <div class="summary-card-value">₱<?php echo number_format($summary['total_gross'], 2); ?></div>
        <div class="summary-card-footer">
            Combined gross pay for all records
        </div>
    </div>
    
    <div class="summary-card deductions">
        <div class="summary-card-header">
            <i class="fas fa-minus-circle"></i>
            <span>Total Deductions</span>
        </div>
        <div class="summary-card-value">₱<?php echo number_format($summary['total_deductions'], 2); ?></div>
        <div class="summary-card-footer">
            Combined deductions for all records
        </div>
    </div>
    
    <div class="summary-card net">
        <div class="summary-card-header">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Total Net Pay</span>
        </div>
        <div class="summary-card-value">₱<?php echo number_format($summary['total_net'], 2); ?></div>
        <div class="summary-card-footer">
            Combined net pay for all records
        </div>
    </div>
</div>

<!-- Payroll Records Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h2 class="card-title">
            <i class="fas fa-list"></i>
            Payroll Records
        </h2>
        <div class="export-buttons">
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="exportToExcel()" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <?php if ($payrollRecords && $payrollRecords->num_rows > 0): ?>
        <div class="table-container">
            <table class="data-table" id="historyTable">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Period</th>
                        <th>Step</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $payrollRecords->fetch_assoc()): 
                        $employeeName = $row['last_name'] . ', ' . $row['first_name'];
                        if ($row['middle_name']) {
                            $employeeName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
                        }
                        
                        $stepInc = '-';
                        if ($row['date_hired']) {
                            $hireDate = new DateTime($row['date_hired']);
                            $today = new DateTime();
                            $yearsOfService = $hireDate->diff($today)->y;
                            $currentStep = min(8, floor($yearsOfService / 3) + 1);
                            $stepInc = $currentStep;
                        } elseif ($row['step_no']) {
                            $stepInc = $row['step_no'];
                        }
                    ?>
                        <tr>
                            <td>
                                <span class="badge" style="background: #e0f2fe; color: #075985; font-weight: 600;">
                                    <?php echo htmlspecialchars($row['department_code']); ?>
                                </span>
                            </td>
                            <td><strong><code><?php echo htmlspecialchars($row['emp_number']); ?></code></strong></td>
                            <td><strong><?php echo htmlspecialchars($employeeName); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['payroll_period']); ?></td>
                            <td>
                                <?php if ($stepInc !== '-'): ?>
                                    <span class="badge" style="background: #fef3c7; color: #92400e;">
                                        Step <?php echo $stepInc; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: #10b981;">₱<?php echo number_format($row['gross_pay'], 2); ?></strong></td>
                            <td><strong style="color: #ef4444;">₱<?php echo number_format($row['total_deductions'], 2); ?></strong></td>
                            <td><strong style="color: #8b5cf6;">₱<?php echo number_format($row['net_pay'], 2); ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                    <?php if ($row['status'] === 'Paid'): ?>🔒 <?php endif; ?>
                                    <?php echo strtoupper($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="payroll_view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-icon sm" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($row['status'] !== 'Paid'): ?>
                                        <a href="payroll_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-icon sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="no-records">
            <i class="fas fa-history"></i>
            <h3>No Payroll Records Found</h3>
            <p>No payroll history matches your current filters.</p>
            <?php if ($filterDeptId || $filterMonth || $filterStatus): ?>
            <a href="payroll_history.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
            <?php else: ?>
            <a href="payroll.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Create Payroll
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($('#historyTable tbody tr').length > 0) {
        $('#historyTable').DataTable({
            "pageLength": 50,
            "order": [[9, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [10] }
            ]
        });
    }
});

// Handle period select change
$('#period_select').on('change', function() {
    const value = $(this).val();
    if (value) {
        const parts = value.split('|');
        // Create hidden inputs for month and year
        $('#filterForm').find('input[name="month"]').remove();
        $('#filterForm').find('input[name="year"]').remove();
        
        $('<input>').attr({
            type: 'hidden',
            name: 'month',
            value: parts[0]
        }).appendTo('#filterForm');
        
        $('<input>').attr({
            type: 'hidden',
            name: 'year',
            value: parts[1]
        }).appendTo('#filterForm');
    }
});

// Initialize hidden inputs if period is selected
$(document).ready(function() {
    const periodSelect = $('#period_select').val();
    if (periodSelect) {
        const parts = periodSelect.split('|');
        $('<input>').attr({
            type: 'hidden',
            name: 'month',
            value: parts[0]
        }).appendTo('#filterForm');
        
        $('<input>').attr({
            type: 'hidden',
            name: 'year',
            value: parts[1]
        }).appendTo('#filterForm');
    }
});

function removeFilter(filterType) {
    const form = document.getElementById('filterForm');
    
    if (filterType === 'department_id') {
        document.getElementById('department_id').value = '';
    } else if (filterType === 'period') {
        document.getElementById('period_select').value = '';
        form.querySelector('input[name="month"]')?.remove();
        form.querySelector('input[name="year"]')?.remove();
    } else if (filterType === 'status') {
        document.getElementById('status').value = '';
    }
    
    form.submit();
}

function exportToExcel() {
    // Get the table
    const table = document.getElementById('historyTable');
    if (!table) {
        alert('No data to export');
        return;
    }
    
    // Convert table to CSV
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip last column (Actions)
            let data = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'payroll_history_' + new Date().getTime() + '.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<style media="print">
@media print {
    .btn, .filter-card, .export-buttons, .page-header, .breadcrumb, 
    .btn-group, .back-link, .filter-actions, .active-filters {
        display: none !important;
    }
    
    .history-header {
        background: #1e40af !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .summary-cards {
        page-break-after: avoid;
    }
    
    table {
        font-size: 10px;
    }
}
</style>

<?php 
if (isset($stmt)) $stmt->close();
require_once 'includes/footer.php'; 
?>