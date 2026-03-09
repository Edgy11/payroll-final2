<?php
/**
 * Payroll System - Employees Management
 * Shows departments first, then employees when a department is selected
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Employees';
$message   = '';
$messageType = '';

$selectedDeptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// ── Shared: parse common employee fields from POST ────────────────────────────
function parseEmployeePost(array $post): array {
    $dob = $post['date_of_birth'] ?: null;
    return [
        'empId'      => sanitize($post['employee_id']),
        'firstName'  => sanitize($post['first_name']),
        'middleName' => sanitize($post['middle_name']),
        'lastName'   => sanitize($post['last_name']),
        'suffix'     => sanitize($post['suffix']),
        'sex'        => sanitize($post['sex']),
        'dob'        => $dob,
        'age'        => $dob ? (new DateTime())->diff(new DateTime($dob))->y : null,
        'email'      => sanitize($post['email']),
        'phone'      => sanitize($post['phone']),
        'address'    => sanitize($post['address']),
        'dateHired'  => $post['date_hired'] ?: null,
        'deptId'     => !empty($post['department_id']) ? (int)$post['department_id'] : null,
        'posId'      => !empty($post['position_id'])   ? (int)$post['position_id']   : null,
        'status'     => sanitize($post['employment_status']),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $f    = parseEmployeePost($_POST);
        $stmt = $conn->prepare("
            INSERT INTO employees
                (employee_id, first_name, middle_name, last_name, suffix, sex,
                 date_of_birth, age, email, phone, address, date_hired,
                 department_id, position_id, employment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssssssssiis",
            $f['empId'], $f['firstName'], $f['middleName'], $f['lastName'],
            $f['suffix'], $f['sex'], $f['dob'], $f['age'],
            $f['email'], $f['phone'], $f['address'], $f['dateHired'],
            $f['deptId'], $f['posId'], $f['status']
        );
        if ($stmt->execute()) {
            $message     = 'Employee added successfully!';
            $messageType = 'success';
            if (!empty($_POST['department_id'])) {
                $selectedDeptId = (int)$_POST['department_id'];
            }
        } else {
            $message     = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }

    if ($action === 'edit') {
        $id       = (int)$_POST['id'];
        $f        = parseEmployeePost($_POST);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("
            UPDATE employees
            SET employee_id = ?, first_name = ?, middle_name = ?, last_name = ?,
                suffix = ?, sex = ?, date_of_birth = ?, age = ?,
                email = ?, phone = ?, address = ?, date_hired = ?,
                department_id = ?, position_id = ?, employment_status = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssssssssssiisii",
            $f['empId'], $f['firstName'], $f['middleName'], $f['lastName'],
            $f['suffix'], $f['sex'], $f['dob'], $f['age'],
            $f['email'], $f['phone'], $f['address'], $f['dateHired'],
            $f['deptId'], $f['posId'], $f['status'], $isActive, $id
        );
        if ($stmt->execute()) { $message = 'Employee updated successfully!'; $messageType = 'success'; }
        else                  { $message = 'Error: ' . $conn->error;         $messageType = 'danger';  }
        $stmt->close();
    }

    if ($action === 'delete') {
        $id    = (int)$_POST['id'];
        $count = $conn->query("SELECT COUNT(*) AS c FROM payroll WHERE employee_id = $id")->fetch_assoc()['c'];
        if ($count > 0) {
            $message     = "Cannot delete employee. They have $count payroll record(s). Consider deactivating instead.";
            $messageType = 'warning';
        } else {
            $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) { $message = 'Employee deleted successfully!'; $messageType = 'success'; }
            else                  { $message = 'Error: ' . $conn->error;         $messageType = 'danger';  }
            $stmt->close();
        }
    }
}

// ── Data fetching ──────────────────────────────────────────────────────────────
$departments = $conn->query("
    SELECT d.*,
           COUNT(CASE WHEN e.is_active = 1 THEN 1 END) AS active_count,
           COUNT(e.id) AS total_count
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id
    GROUP BY d.id
    ORDER BY CASE WHEN COUNT(CASE WHEN e.is_active = 1 THEN 1 END) > 0 THEN 0 ELSE 1 END,
             d.department_name ASC
");

$selectedDept = null;
if ($selectedDeptId > 0) {
    $ds = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $ds->bind_param("i", $selectedDeptId);
    $ds->execute();
    $selectedDept = $ds->get_result()->fetch_assoc();
    $ds->close();
}

$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filterSex    = isset($_GET['sex'])    ? sanitize($_GET['sex'])    : '';

$employees = null;
if ($selectedDeptId > 0) {
    $where = "e.department_id = $selectedDeptId";
    if ($filterStatus !== '') { $where .= " AND e.is_active = " . ($filterStatus === 'active' ? 1 : 0); }
    if ($filterSex    !== '') { $where .= " AND e.sex = '" . $conn->real_escape_string($filterSex) . "'"; }
    $employees = $conn->query("
        SELECT e.*, d.department_name, d.department_code,
               p.position_title, p.salary_grade, p.basic_salary
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions   p ON e.position_id   = p.id
        WHERE $where
        ORDER BY e.last_name, e.first_name
    ");
}

// Fetch into arrays once — avoids data_seek() duplication
$deptDropRows = [];
$r = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");
while ($row = $r->fetch_assoc()) { $deptDropRows[] = $row; }

$posRows = [];
$r = $conn->query("SELECT id, position_title, salary_grade, basic_salary FROM positions ORDER BY salary_grade+0, position_title");
while ($row = $r->fetch_assoc()) { $posRows[] = $row; }

// Next employee ID suggestion
$nextEmpId = '0001';
$last = $conn->query("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1");
if ($last && $row = $last->fetch_assoc()) {
    if (preg_match('/(\d+)$/', $row['employee_id'], $m)) {
        $nextEmpId = str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
    }
}

require_once 'includes/header.php';
?>

<link rel="stylesheet" href="css/employees.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<?php if ($selectedDeptId == 0): ?>
<!-- ── DEPARTMENT SELECTION VIEW ────────────────────────────────────────────── -->
<div class="page-header">
    <div class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><span>/</span><span>Employees</span></div>
    <h1 class="page-title">Employees</h1>
    <p class="page-subtitle">Select a department to view and manage employees</p>
</div>
<div class="page-intro">
    <p><i class="fas fa-info-circle" style="color:var(--primary-500); margin-right:8px;"></i>
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
        <?php while ($dept = $departments->fetch_assoc()): ?>
        <div class="department-card <?php echo $dept['active_count'] > 0 ? 'has-employees' : 'empty'; ?>"
             onclick="window.location.href='employees.php?department_id=<?php echo $dept['id']; ?>'">
            <div class="department-card-header">
                <div class="department-icon"><i class="fas fa-building"></i></div>
                <span class="department-badge"><?php echo htmlspecialchars($dept['department_code']); ?></span>
            </div>
            <div class="department-card-body">
                <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                <p><?php echo htmlspecialchars($dept['description'] ?: 'No description available'); ?></p>
            </div>
            <div class="department-stats">
                <div class="department-stat"><span class="department-stat-value"><?php echo $dept['active_count']; ?></span><span class="department-stat-label">Active</span></div>
                <div class="department-stat"><span class="department-stat-value"><?php echo $dept['total_count']; ?></span><span class="department-stat-label">Total</span></div>
            </div>
            <div class="department-card-arrow"><i class="fas fa-arrow-right"></i></div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card" style="grid-column:1/-1;">
            <div class="card-body text-center" style="padding:var(--space-3xl);">
                <i class="fas fa-building" style="font-size:3rem; color:var(--gray-300); margin-bottom:var(--space-lg);"></i>
                <h3 style="color:var(--gray-600); margin-bottom:var(--space-sm);">No Departments Found</h3>
                <p style="color:var(--gray-500);">Please create departments first before adding employees.</p>
                <a href="departments.php" class="btn btn-primary" style="margin-top:var(--space-lg);"><i class="fas fa-plus"></i> Create Department</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ── EMPLOYEE LIST VIEW ───────────────────────────────────────────────────── -->
<a href="employees.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Departments</a>
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a><span>/</span>
        <a href="employees.php">Employees</a><span>/</span>
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
            <div class="selected-department-icon"><i class="fas fa-building"></i></div>
            <div class="selected-department-text">
                <h2><?php echo htmlspecialchars($selectedDept['department_name']); ?></h2>
                <p><?php echo htmlspecialchars($selectedDept['description'] ?: 'No description'); ?></p>
            </div>
        </div>
        <div class="selected-department-stats">
            <?php
            $counts = $conn->query("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                       SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive
                FROM employees WHERE department_id = $selectedDeptId
            ")->fetch_assoc();
            foreach ([['Total','total'],['Active','active'],['Inactive','inactive']] as [$label, $key]): ?>
            <div class="selected-dept-stat">
                <div class="selected-dept-stat-value"><?php echo $counts[$key]; ?></div>
                <div class="selected-dept-stat-label"><?php echo $label; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-users"></i> Employee List</h2>
        <button class="btn btn-primary" onclick="openModal('addEmployeeModal')"><i class="fas fa-plus"></i> Add Employee</button>
    </div>
    <div class="card-body" style="padding:var(--space-md) var(--space-xl); border-bottom:1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="department_id" value="<?php echo $selectedDeptId; ?>">
            <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active"   <?php echo $filterStatus === 'active'   ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <select name="sex" class="form-control" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Gender</option>
                <option value="Male"   <?php echo $filterSex === 'Male'   ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $filterSex === 'Female' ? 'selected' : ''; ?>>Female</option>
            </select>
            <?php if ($filterStatus !== '' || $filterSex !== ''): ?>
                <a href="employees.php?department_id=<?php echo $selectedDeptId; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table class="data-table" id="employeesTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID</th>
                        <th>Contact</th>
                        <th>Age/Sex</th>
                        <th>Date Of Appointment</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($employees && $employees->num_rows > 0): ?>
                    <?php while ($row = $employees->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="employee-info">
                                <div class="employee-avatar"><?php echo strtoupper(substr($row['first_name'],0,1).substr($row['last_name'],0,1)); ?></div>
                                <div>
                                    <div class="employee-name">
                                        <?php
                                        $fullName = $row['last_name'] . ', ' . $row['first_name'];
                                        if ($row['middle_name']) $fullName .= ' ' . substr($row['middle_name'],0,1) . '.';
                                        if ($row['suffix'])      $fullName .= ' ' . $row['suffix'];
                                        echo htmlspecialchars($fullName);
                                        ?>
                                    </div>
                                    <div class="employee-id"><?php echo htmlspecialchars($row['email'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code><?php echo htmlspecialchars($row['employee_id']); ?></code></td>
                        <td>
                            <?php if ($row['phone']): ?><div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?></div><?php endif; ?>
                            <?php if ($row['email']): ?><div><i class="fas fa-envelope"></i> <small><?php echo htmlspecialchars($row['email']); ?></small></div><?php endif; ?>
                            <?php if (!$row['phone'] && !$row['email']): ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['age'] ? '<strong>'.$row['age'].' yrs</strong>' : '<span class="text-muted">-</span>'; ?>
                            <br>
                            <?php if ($row['sex']): ?>
                                <small class="text-muted"><i class="fas fa-<?php echo $row['sex']==='Male'?'mars':'venus'; ?>"></i> <?php echo htmlspecialchars($row['sex']); ?></small>
                            <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                        </td>
                        <td><?php echo $row['date_hired'] ? '<strong>'.date('M d, Y', strtotime($row['date_hired'])).'</strong>' : '<span class="text-muted">-</span>'; ?></td>
                        <td>
                            <?php if ($row['position_title']): ?>
                                <span class="badge badge-info"><?php echo htmlspecialchars($row['position_title']); ?></span>
                                <?php if ($row['salary_grade']): ?><br><small class="text-muted">SG-<?php echo $row['salary_grade']; ?></small><?php endif; ?>
                                <?php if ($row['basic_salary']): ?><br><small style="color:#059669;font-weight:600;"><?php echo formatCurrency($row['basic_salary']); ?></small><?php endif; ?>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td>
                            <?php echo $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?>
                            <br><small class="text-muted"><?php echo $row['employment_status']; ?></small>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="employee_details.php?id=<?php echo $row['id']; ?>" class="action-btn action-btn-view"><i class="fas fa-eye"></i> View</a>
                                <button class="action-btn action-btn-edit" onclick='editEmployee(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i> Edit</button>
                                <a href="payroll_create.php?employee=<?php echo $row['id']; ?>" class="action-btn action-btn-payroll"><i class="fas fa-file-invoice-dollar"></i> Payroll</a>
                                <button class="action-btn action-btn-delete" onclick="deleteEmployee(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['first_name'].' '.$row['last_name'])); ?>')"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:2rem;">
                            <i class="fas fa-users" style="font-size:2rem; color:var(--gray-300); display:block; margin-bottom:var(--space-md);"></i>
                            No employees found in this department.
                            <br>
                            <button class="btn btn-primary" style="margin-top:var(--space-md);" onclick="openModal('addEmployeeModal')"><i class="fas fa-plus"></i> Add First Employee</button>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Shared position options — rendered once, injected into both modals via JS -->
<template id="positionOptsTpl">
    <option value="">-- Select Position --</option>
    <?php foreach ($posRows as $p): ?>
    <option value="<?php echo $p['id']; ?>">[SG-<?php echo $p['salary_grade']; ?>] <?php echo htmlspecialchars($p['position_title']); ?> — <?php echo formatCurrency($p['basic_salary']); ?></option>
    <?php endforeach; ?>
</template>

<!-- Add Employee Modal -->
<div id="addEmployeeModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:flex-start; justify-content:center; padding:2rem; overflow-y:auto;">
<div style="background:#fff; border-radius:16px; width:100%; max-width:860px; margin:0 auto; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; background:#1a3a5c;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#fff;"><i class="fas fa-user-plus" style="margin-right:8px;"></i>Add New Employee</h3>
        <button onclick="closeModal('addEmployeeModal')" style="border:none; background:rgba(255,255,255,0.15); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:.9rem; color:#fff;">&#10005;</button>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="department_id" value="<?php echo $selectedDeptId; ?>">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Employee ID</label>
                    <input type="text" name="employee_id" class="form-control" required value="<?php echo htmlspecialchars($nextEmpId); ?>">
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
                <div class="form-group"><label class="form-label required">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
                <div class="form-group"><label class="form-label required">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Suffix</label><input type="text" name="suffix" class="form-control" placeholder="Jr., Sr., III"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" id="add_dob" class="form-control" onchange="calculateAge(this.value,'add_age_display')"></div>
                <div class="form-group"><label class="form-label">Age</label><input type="text" id="add_age_display" class="form-control" readonly placeholder="Auto-calculated"></div>
                <div class="form-group"><label class="form-label">Sex</label><select name="sex" class="form-control"><option value="">-- Select --</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="form-group"><label class="form-label">Date Hired</label><input type="date" name="date_hired" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($selectedDept['department_name']); ?>" readonly style="background:var(--gray-100);">
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <select name="position_id" id="add_position_id" class="form-control"></select>
                </div>
            </div>
        </div>
        <div style="padding:1rem 1.5rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:.75rem;">
            <button type="button" onclick="closeModal('addEmployeeModal')" style="padding:.5rem 1.25rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151;">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Employee</button>
        </div>
    </form>
</div>
</div>

<!-- Edit Employee Modal -->
<div id="editEmployeeModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:flex-start; justify-content:center; padding:2rem; overflow-y:auto;">
<div style="background:#fff; border-radius:16px; width:100%; max-width:860px; margin:0 auto; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; background:#1a3a5c;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#fff;"><i class="fas fa-user-edit" style="margin-right:8px;"></i>Edit Employee</h3>
        <button onclick="closeModal('editEmployeeModal')" style="border:none; background:rgba(255,255,255,0.15); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:.9rem; color:#fff;">&#10005;</button>
    </div>
    <form method="POST" action="employees.php?department_id=<?php echo $selectedDeptId; ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label class="form-label required">Employee ID</label><input type="text" name="employee_id" id="edit_employee_id" class="form-control" required></div>
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
                <div class="form-group"><label class="form-label required">First Name</label><input type="text" name="first_name" id="edit_first_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" id="edit_middle_name" class="form-control"></div>
                <div class="form-group"><label class="form-label required">Last Name</label><input type="text" name="last_name" id="edit_last_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Suffix</label><input type="text" name="suffix" id="edit_suffix" class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" id="edit_dob" class="form-control" onchange="calculateAge(this.value,'edit_age_display')"></div>
                <div class="form-group"><label class="form-label">Age</label><input type="text" id="edit_age_display" class="form-control" readonly placeholder="Auto-calculated"></div>
                <div class="form-group"><label class="form-label">Sex</label><select name="sex" id="edit_sex" class="form-control"><option value="">-- Select --</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                <div class="form-group"><label class="form-label">Date Hired</label><input type="date" name="date_hired" id="edit_date_hired" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="edit_address" class="form-control" rows="2"></textarea></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="edit_department_id" class="form-control">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($deptDropRows as $d): ?>
                        <option value="<?php echo $d['id']; ?>">[<?php echo htmlspecialchars($d['department_code']); ?>] <?php echo htmlspecialchars($d['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <select name="position_id" id="edit_position_id" class="form-control"></select>
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                    <span>Active Employee</span>
                </label>
            </div>
        </div>
        <div style="padding:1rem 1.5rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:.75rem;">
            <button type="button" onclick="closeModal('editEmployeeModal')" style="padding:.5rem 1.25rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151;">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Employee</button>
        </div>
    </form>
</div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" action="employees.php?department_id=<?php echo $selectedDeptId; ?>" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
// Inject shared position options into both modals from a single template
(function() {
    var tpl = document.getElementById('positionOptsTpl').innerHTML;
    document.getElementById('add_position_id').innerHTML  = tpl;
    document.getElementById('edit_position_id').innerHTML = tpl;
})();

function calculateAge(dob, displayId) {
    if (!dob) { document.getElementById(displayId).value = ''; return; }
    var d = new Date(dob), today = new Date();
    var age = today.getFullYear() - d.getFullYear();
    if (today.getMonth() - d.getMonth() < 0 || (today.getMonth() === d.getMonth() && today.getDate() < d.getDate())) age--;
    document.getElementById(displayId).value = age + ' years old';
}

function editEmployee(data) {
    var map = {
        edit_id: 'id', edit_employee_id: 'employee_id',
        edit_first_name: 'first_name', edit_middle_name: 'middle_name',
        edit_last_name: 'last_name',   edit_suffix: 'suffix',
        edit_sex: 'sex',               edit_email: 'email',
        edit_phone: 'phone',           edit_address: 'address',
        edit_department_id: 'department_id', edit_position_id: 'position_id',
        edit_employment_status: 'employment_status'
    };
    Object.entries(map).forEach(function([elId, key]) {
        var el = document.getElementById(elId);
        if (el) el.value = data[key] || '';
    });
    document.getElementById('edit_dob').value        = data.date_of_birth || '';
    document.getElementById('edit_date_hired').value = data.date_hired    || '';
    document.getElementById('edit_is_active').checked = data.is_active == 1;
    if (data.date_of_birth) calculateAge(data.date_of_birth, 'edit_age_display');
    else document.getElementById('edit_age_display').value = '';
    openModal('editEmployeeModal');
}

function deleteEmployee(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function openModal(id)  { var el = document.getElementById(id); if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; } }
function closeModal(id) { var el = document.getElementById(id); if (el) { el.style.display = 'none';  document.body.style.overflow = '';       } }
document.addEventListener('click', function(e) {
    ['addEmployeeModal','editEmployeeModal'].forEach(function(id) {
        var el = document.getElementById(id); if (el && e.target === el) closeModal(id);
    });
});

$(document).ready(function() {
    if ($('#employeesTable tbody tr').length > 0 && $('#employeesTable tbody tr:first td').length === 8) {
        $('#employeesTable').DataTable({
            pageLength: 10,
            lengthMenu: [[10,25,50,100],[10,25,50,100]],
            order: [[0,'asc']],
            language: { search:'Search:', lengthMenu:'Show _MENU_ entries', info:'Showing _START_ to _END_ of _TOTAL_ employees', infoEmpty:'No employees to display', infoFiltered:'(filtered from _MAX_ total)', paginate:{first:'First',last:'Last',next:'Next',previous:'Prev'} },
            columnDefs: [{ orderable: false, targets: [7] }],
            autoWidth: false
        });
    }
});
</script>

<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>