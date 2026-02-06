<?php
/**
 * Payroll System - Employees Management
 * Shows departments first, then employees when a department is selected
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Employees';
$message = '';
$messageType = '';

// Check if a department is selected
$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $empId = sanitize($_POST['employee_id']);
        $firstName = sanitize($_POST['first_name']);
        $middleName = sanitize($_POST['middle_name']);
        $lastName = sanitize($_POST['last_name']);
        $suffix = sanitize($_POST['suffix']);
        $sex = sanitize($_POST['sex']);
        $dateOfBirth = $_POST['date_of_birth'] ?: null;
        
        $age = null;
        if ($dateOfBirth) {
            $dob = new DateTime($dateOfBirth);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        }
        
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $dateHired = $_POST['date_hired'] ?: null;
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $posId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $status = sanitize($_POST['employment_status']);
        
        $stmt = $conn->prepare("INSERT INTO employees (employee_id, first_name, middle_name, last_name, suffix, sex, date_of_birth, age, email, phone, address, date_hired, department_id, position_id, employment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssssiis", $empId, $firstName, $middleName, $lastName, $suffix, $sex, $dateOfBirth, $age, $email, $phone, $address, $dateHired, $deptId, $posId, $status);
        
        if ($stmt->execute()) {
            $message = 'Employee added successfully!';
            $messageType = 'success';
            if (!empty($_POST['department_id'])) {
                $selectedDeptId = (int)$_POST['department_id'];
            }
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $empId = sanitize($_POST['employee_id']);
        $firstName = sanitize($_POST['first_name']);
        $middleName = sanitize($_POST['middle_name']);
        $lastName = sanitize($_POST['last_name']);
        $suffix = sanitize($_POST['suffix']);
        $sex = sanitize($_POST['sex']);
        $dateOfBirth = $_POST['date_of_birth'] ?: null;
        
        $age = null;
        if ($dateOfBirth) {
            $dob = new DateTime($dateOfBirth);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        }
        
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $dateHired = $_POST['date_hired'] ?: null;
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $posId = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $status = sanitize($_POST['employment_status']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE employees SET employee_id = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, sex = ?, date_of_birth = ?, age = ?, email = ?, phone = ?, address = ?, date_hired = ?, department_id = ?, position_id = ?, employment_status = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssssssssssssiisii", $empId, $firstName, $middleName, $lastName, $suffix, $sex, $dateOfBirth, $age, $email, $phone, $address, $dateHired, $deptId, $posId, $status, $isActive, $id);
        
        if ($stmt->execute()) {
            $message = 'Employee updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        $check = $conn->query("SELECT COUNT(*) as count FROM payroll WHERE employee_id = $id");
        $count = $check->fetch_assoc()['count'];
        
        if ($count > 0) {
            $message = "Cannot delete employee. They have $count payroll record(s). Consider deactivating instead.";
            $messageType = 'warning';
        } else {
            $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Employee deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get all departments with employee count
$departments = $conn->query("
    SELECT d.*, 
           COUNT(CASE WHEN e.is_active = 1 THEN 1 END) as active_count,
           COUNT(e.id) as total_count
    FROM departments d 
    LEFT JOIN employees e ON d.id = e.department_id
    GROUP BY d.id 
    ORDER BY 
        CASE WHEN COUNT(CASE WHEN e.is_active = 1 THEN 1 END) > 0 THEN 0 ELSE 1 END,
        d.department_name ASC
");

// Get selected department info
$selectedDept = null;
if ($selectedDeptId > 0) {
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $selectedDeptId);
    $deptStmt->execute();
    $selectedDept = $deptStmt->get_result()->fetch_assoc();
    $deptStmt->close();
}

// Get filter parameters for employee list
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filterSex = isset($_GET['sex']) ? sanitize($_GET['sex']) : '';

// Build query with filters (only when department is selected)
$employees = null;
if ($selectedDeptId > 0) {
    $whereClause = "e.department_id = $selectedDeptId";
    if ($filterStatus !== '') {
        $whereClause .= " AND e.is_active = " . ($filterStatus === 'active' ? 1 : 0);
    }
    if ($filterSex !== '') {
        $whereClause .= " AND e.sex = '" . $conn->real_escape_string($filterSex) . "'";
    }
    
    $employees = $conn->query("
        SELECT e.*, d.department_name, d.department_code, p.position_title, p.salary_grade, p.basic_salary
        FROM employees e 
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE $whereClause
        ORDER BY e.last_name, e.first_name
    ");
}

// Get all departments for dropdown (in modals)
$departmentsForDropdown = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get all positions for dropdown
$positions = $conn->query("SELECT id, position_title, salary_grade, basic_salary FROM positions ORDER BY salary_grade, position_title");

require_once 'includes/header.php';
?>

<style>
.departments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-lg);
    margin-bottom: var(--space-xl);
}

.department-card {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: var(--space-xl);
    box-shadow: var(--shadow-md);
    cursor: pointer;
    transition: all var(--transition-base);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.department-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-400), var(--primary-600));
}

.department-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary-300);
}

.department-card.has-employees::before {
    background: linear-gradient(90deg, var(--success-500), #059669);
}

.department-card.empty::before {
    background: linear-gradient(90deg, var(--gray-400), var(--gray-500));
}

.department-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: var(--space-md);
}

.department-icon {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-lg);
    background: linear-gradient(135deg, var(--primary-100), var(--primary-200));
    color: var(--primary-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.department-card.has-employees .department-icon {
    background: linear-gradient(135deg, var(--success-100), #d1fae5);
    color: var(--success-500);
}

.department-badge {
    padding: var(--space-xs) var(--space-sm);
    background: var(--primary-100);
    color: var(--primary-700);
    border-radius: var(--radius-md);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
}

.department-card-body h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--gray-900);
    margin-bottom: var(--space-xs);
}

.department-card-body p {
    font-size: 0.875rem;
    color: var(--gray-500);
    margin-bottom: var(--space-md);
    min-height: 40px;
}

.department-stats {
    display: flex;
    gap: var(--space-lg);
    padding-top: var(--space-md);
    border-top: 1px solid var(--gray-100);
}

.department-stat {
    display: flex;
    flex-direction: column;
}

.department-stat-value {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--gray-900);
}

.department-stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.department-card-arrow {
    position: absolute;
    bottom: var(--space-lg);
    right: var(--space-lg);
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    background: var(--gray-100);
    color: var(--gray-500);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-base);
}

.department-card:hover .department-card-arrow {
    background: var(--primary-500);
    color: var(--white);
    transform: translateX(4px);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    color: var(--gray-600);
    font-weight: 600;
    margin-bottom: var(--space-lg);
    transition: color var(--transition-fast);
}

.back-link:hover {
    color: var(--primary-500);
}

.back-link i {
    font-size: 1rem;
}

.selected-department-header {
    background: linear-gradient(135deg, var(--primary-800), var(--primary-900));
    color: var(--white);
    padding: var(--space-xl);
    border-radius: var(--radius-xl);
    margin-bottom: var(--space-xl);
    position: relative;
    overflow: hidden;
}

.selected-department-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
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
    gap: var(--space-lg);
}

.selected-department-info {
    display: flex;
    align-items: center;
    gap: var(--space-lg);
}

.selected-department-icon {
    width: 64px;
    height: 64px;
    border-radius: var(--radius-lg);
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.selected-department-text h2 {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: var(--space-xs);
}

.selected-department-text p {
    color: var(--primary-200);
    font-size: 0.9375rem;
}

.selected-department-stats {
    display: flex;
    gap: var(--space-xl);
}

.selected-dept-stat {
    text-align: center;
    padding: var(--space-md) var(--space-lg);
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-lg);
}

.selected-dept-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
}

.selected-dept-stat-label {
    font-size: 0.75rem;
    color: var(--primary-200);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.page-intro {
    max-width: 600px;
    margin-bottom: var(--space-xl);
}

.page-intro p {
    color: var(--gray-600);
    font-size: 1rem;
    line-height: 1.6;
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
        <span>Employees</span>
    </div>
    <h1 class="page-title">Employees</h1>
    <p class="page-subtitle">Select a department to view and manage employees</p>
</div>

<div class="page-intro">
    <p><i class="fas fa-info-circle" style="color: var(--primary-500); margin-right: 8px;"></i>
    Click on a department card below to view the employees in that department. You can add, edit, or remove employees once you've selected a department.</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<div class="departments-grid">
    <?php if ($departments && $departments->num_rows > 0): ?>
        <?php while($dept = $departments->fetch_assoc()): ?>
            <div class="department-card <?php echo $dept['active_count'] > 0 ? 'has-employees' : 'empty'; ?>" 
                 onclick="window.location.href='employees.php?department_id=<?php echo $dept['id']; ?>'">
                <div class="department-card-header">
                    <div class="department-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <span class="department-badge"><?php echo htmlspecialchars($dept['department_code']); ?></span>
                </div>
                <div class="department-card-body">
                    <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                    <p><?php echo htmlspecialchars($dept['description'] ?: 'No description available'); ?></p>
                </div>
                <div class="department-stats">
                    <div class="department-stat">
                        <span class="department-stat-value"><?php echo $dept['active_count']; ?></span>
                        <span class="department-stat-label">Active</span>
                    </div>
                    <div class="department-stat">
                        <span class="department-stat-value"><?php echo $dept['total_count']; ?></span>
                        <span class="department-stat-label">Total</span>
                    </div>
                </div>
                <div class="department-card-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-body text-center" style="padding: var(--space-3xl);">
                <i class="fas fa-building" style="font-size: 3rem; color: var(--gray-300); margin-bottom: var(--space-lg);"></i>
                <h3 style="color: var(--gray-600); margin-bottom: var(--space-sm);">No Departments Found</h3>
                <p style="color: var(--gray-500);">Please create departments first before adding employees.</p>
                <a href="departments.php" class="btn btn-primary" style="margin-top: var(--space-lg);">
                    <i class="fas fa-plus"></i> Create Department
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- EMPLOYEE LIST VIEW -->

<a href="employees.php" class="back-link">
    <i class="fas fa-arrow-left"></i>
    Back to Departments
</a>

<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="employees.php">Employees</a>
        <span>/</span>
        <span><?php echo htmlspecialchars($selectedDept['department_code']); ?></span>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<div class="selected-department-header">
    <div class="selected-department-content">
        <div class="selected-department-info">
            <div class="selected-department-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="selected-department-text">
                <h2><?php echo htmlspecialchars($selectedDept['department_name']); ?></h2>
                <p><?php echo htmlspecialchars($selectedDept['description'] ?: 'No description'); ?></p>
            </div>
        </div>
        <div class="selected-department-stats">
            <?php
            $countQuery = $conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
                FROM employees WHERE department_id = $selectedDeptId");
            $counts = $countQuery->fetch_assoc();
            ?>
            <div class="selected-dept-stat">
                <div class="selected-dept-stat-value"><?php echo $counts['total']; ?></div>
                <div class="selected-dept-stat-label">Total</div>
            </div>
            <div class="selected-dept-stat">
                <div class="selected-dept-stat-value"><?php echo $counts['active']; ?></div>
                <div class="selected-dept-stat-label">Active</div>
            </div>
            <div class="selected-dept-stat">
                <div class="selected-dept-stat-value"><?php echo $counts['inactive']; ?></div>
                <div class="selected-dept-stat-label">Inactive</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-users"></i>
            Employee List
        </h2>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="Modal.open('addEmployeeModal')">
                <i class="fas fa-plus"></i> Add Employee
            </button>
        </div>
    </div>
    
    <div class="card-body" style="padding: var(--space-md) var(--space-xl); border-bottom: 1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="department_id" value="<?php echo $selectedDeptId; ?>">
            <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <select name="sex" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Gender</option>
                <option value="Male" <?php echo $filterSex === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $filterSex === 'Female' ? 'selected' : ''; ?>>Female</option>
            </select>
            <?php if ($filterStatus !== '' || $filterSex !== ''): ?>
                <a href="employees.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="employeesTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID</th>
                        <th>Contact</th>
                        <th>Age/Sex</th>
                        <th>Date Hired</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employees && $employees->num_rows > 0): ?>
                        <?php while($row = $employees->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="employee-name">
                                                <?php 
                                                $fullName = $row['last_name'] . ', ' . $row['first_name'];
                                                if ($row['middle_name']) {
                                                    $fullName .= ' ' . substr($row['middle_name'], 0, 1) . '.';
                                                }
                                                if ($row['suffix']) {
                                                    $fullName .= ' ' . $row['suffix'];
                                                }
                                                echo htmlspecialchars($fullName);
                                                ?>
                                            </div>
                                            <div class="employee-id"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['employee_id']); ?></code></td>
                                <td>
                                    <?php if ($row['phone']): ?>
                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['email']): ?>
                                        <div><i class="fas fa-envelope"></i> <small><?php echo htmlspecialchars($row['email']); ?></small></div>
                                    <?php endif; ?>
                                    <?php if (!$row['phone'] && !$row['email']): ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['age']): ?>
                                        <strong><?php echo $row['age']; ?> yrs</strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    <br>
                                    <?php if ($row['sex']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-<?php echo $row['sex'] === 'Male' ? 'mars' : 'venus'; ?>"></i>
                                            <?php echo htmlspecialchars($row['sex']); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['date_hired']): ?>
                                        <strong><?php echo date('M d, Y', strtotime($row['date_hired'])); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['position_title']): ?>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($row['position_title']); ?></span>
                                        <?php if ($row['salary_grade']): ?>
                                            <br><small class="text-muted">SG-<?php echo $row['salary_grade']; ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo $row['employment_status']; ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="employee_details.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-info btn-icon sm" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-secondary btn-icon sm" 
                                                onclick='editEmployee(<?php echo json_encode($row); ?>)'
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="payroll_create.php?employee=<?php echo $row['id']; ?>" 
                                           class="btn btn-success btn-icon sm" title="Create Payroll">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </a>
                                        <button class="btn btn-danger btn-icon sm" 
                                                onclick="deleteEmployee(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding: 2rem;">
                                <i class="fas fa-users" style="font-size: 2rem; color: var(--gray-300); display: block; margin-bottom: var(--space-md);"></i>
                                No employees found in this department. 
                                <br>
                                <button class="btn btn-primary" style="margin-top: var(--space-md);" onclick="Modal.open('addEmployeeModal')">
                                    <i class="fas fa-plus"></i> Add First Employee
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal-overlay" id="addEmployeeModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Add New Employee</h3>
            <button class="modal-close" onclick="Modal.close('addEmployeeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addEmployeeForm">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="department_id" value="<?php echo $selectedDeptId; ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" required placeholder="e.g., EMP-2025-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Employment Status</label>
                        <select name="employment_status" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Regular">Regular</option>
                            <option value="Casual">Casual</option>
                            <option value="Contractual">Contractual</option>
                            <option value="Job Order">Job Order</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Suffix</label>
                        <input type="text" name="suffix" class="form-control" placeholder="Jr., Sr., III">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="add_date_of_birth" class="form-control" onchange="calculateAge(this.value, 'add_age_display')">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age</label>
                        <input type="text" id="add_age_display" class="form-control" readonly placeholder="Auto-calculated">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sex</label>
                        <select name="sex" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Hired</label>
                        <input type="date" name="date_hired" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($selectedDept['department_name']); ?>" readonly style="background: var(--gray-100);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position_id" class="form-control">
                            <option value="">-- Select Position --</option>
                            <?php 
                            $positions->data_seek(0);
                            while($pos = $positions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $pos['id']; ?>">
                                    [SG-<?php echo $pos['salary_grade']; ?>] <?php echo htmlspecialchars($pos['position_title']); ?> - <?php echo formatCurrency($pos['basic_salary']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('addEmployeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Employee
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal-overlay" id="editEmployeeModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">Edit Employee</h3>
            <button class="modal-close" onclick="Modal.close('editEmployeeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editEmployeeForm" action="employees.php?department_id=<?php echo $selectedDeptId; ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Employee ID</label>
                        <input type="text" name="employee_id" id="edit_employee_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Employment Status</label>
                        <select name="employment_status" id="edit_employment_status" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Regular">Regular</option>
                            <option value="Casual">Casual</option>
                            <option value="Contractual">Contractual</option>
                            <option value="Job Order">Job Order</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Suffix</label>
                        <input type="text" name="suffix" id="edit_suffix" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="edit_date_of_birth" class="form-control" onchange="calculateAge(this.value, 'edit_age_display')">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age</label>
                        <input type="text" id="edit_age_display" class="form-control" readonly placeholder="Auto-calculated">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sex</label>
                        <select name="sex" id="edit_sex" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date Hired</label>
                        <input type="date" name="date_hired" id="edit_date_hired" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="edit_department_id" class="form-control">
                            <option value="">-- Select Department --</option>
                            <?php 
                            $departmentsForDropdown->data_seek(0);
                            while($dept = $departmentsForDropdown->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position_id" id="edit_position_id" class="form-control">
                            <option value="">-- Select Position --</option>
                            <?php 
                            $positions->data_seek(0);
                            while($pos = $positions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $pos['id']; ?>">
                                    [SG-<?php echo $pos['salary_grade']; ?>] <?php echo htmlspecialchars($pos['position_title']); ?> - <?php echo formatCurrency($pos['basic_salary']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Active Employee</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('editEmployeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Employee
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" action="employees.php?department_id=<?php echo $selectedDeptId; ?>" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
$(document).ready(function() {
    const hasData = $('#employeesTable tbody tr').length > 0 && $('#employeesTable tbody tr:first td').length === 8;
    
    if (hasData) {
        $('#employeesTable').DataTable({
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "order": [[0, "asc"]],
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ employees",
                "infoEmpty": "No employees to display",
                "infoFiltered": "(filtered from _MAX_ total)",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Prev"
                }
            },
            "columnDefs": [
                { "orderable": false, "targets": [7] }
            ],
            "autoWidth": false
        });
    }
});

function calculateAge(dateOfBirth, displayElementId) {
    if (!dateOfBirth) {
        document.getElementById(displayElementId).value = '';
        return;
    }
    
    const dob = new Date(dateOfBirth);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    document.getElementById(displayElementId).value = age + ' years old';
}

function editEmployee(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_employee_id').value = data.employee_id;
    document.getElementById('edit_first_name').value = data.first_name;
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_last_name').value = data.last_name;
    document.getElementById('edit_suffix').value = data.suffix || '';
    document.getElementById('edit_sex').value = data.sex || '';
    document.getElementById('edit_date_of_birth').value = data.date_of_birth || '';
    
    if (data.date_of_birth) {
        calculateAge(data.date_of_birth, 'edit_age_display');
    } else {
        document.getElementById('edit_age_display').value = '';
    }
    
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_phone').value = data.phone || '';
    document.getElementById('edit_address').value = data.address || '';
    document.getElementById('edit_date_hired').value = data.date_hired || '';
    document.getElementById('edit_department_id').value = data.department_id || '';
    document.getElementById('edit_position_id').value = data.position_id || '';
    document.getElementById('edit_employment_status').value = data.employment_status;
    document.getElementById('edit_is_active').checked = data.is_active == 1;
    Modal.open('editEmployeeModal');
}

function deleteEmployee(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>