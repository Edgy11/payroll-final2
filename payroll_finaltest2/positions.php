<?php
/**
 * Payroll System - Positions Management
 * With Accurate Salary ID Tracking for Your Database Structure
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Positions';
$message = '';
$messageType = '';

// ── Helper: resolve salary_id from grade (step 1 default) ─────────────────────
function salaryByGrade(mysqli $conn, string $grade): array|null {
    $sq = $conn->prepare("SELECT salary_id, salary_rate FROM salary WHERE salary_grade = ? AND step_no = 1 LIMIT 1");
    $sq->bind_param("s", $grade);
    $sq->execute();
    $row = $sq->get_result()->fetch_assoc();
    $sq->close();
    return $row ?: null;
}

// ── Helper: format grade as SG-XX ─────────────────────────────────────────────
function fmtSG(string $grade): string {
    return 'SG-' . str_pad($grade, 2, '0', STR_PAD_LEFT);
}

// ── Helper: resolve position title from POST ──────────────────────────────────
function resolveTitle(array $post): string {
    if (!empty($post['position_title']) && $post['position_title'] === 'Custom') {
        return sanitize($post['custom_position_title']);
    }
    return sanitize($post['position_title']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title  = resolveTitle($_POST);
        $grade  = $_POST['salary_grade'];
        $salary = (float)$_POST['basic_salary'];
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        $salRow = salaryByGrade($conn, $grade);
        if ($salRow) {
            $salaryId = $salRow['salary_id'];
            $sgLabel  = fmtSG($grade);

            $stmt = $conn->prepare("INSERT INTO positions (salary_id, position_title, salary_grade, basic_salary, department_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issdi", $salaryId, $title, $grade, $salary, $deptId);

            if ($stmt->execute()) {
                $message     = "Position added successfully! (Position ID: {$stmt->insert_id}, Salary ID: {$salaryId}, Code: {$sgLabel})";
                $messageType = 'success';
            } else {
                $message     = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } else {
            $message     = "Error: Salary Grade {$grade} not found in salary table. Please add it in Salary Management first.";
            $messageType = 'danger';
        }
    }

    if ($action === 'edit') {
        $id     = (int)$_POST['id'];
        $title  = resolveTitle($_POST);
        $grade  = $_POST['salary_grade'];
        $salary = (float)$_POST['basic_salary'];
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        $salRow = salaryByGrade($conn, $grade);
        if ($salRow) {
            $salaryId = $salRow['salary_id'];
            $sgLabel  = fmtSG($grade);

            $stmt = $conn->prepare("UPDATE positions SET salary_id = ?, position_title = ?, salary_grade = ?, basic_salary = ?, department_id = ? WHERE id = ?");
            $stmt->bind_param("issdii", $salaryId, $title, $grade, $salary, $deptId, $id);

            if ($stmt->execute()) {
                $message     = "Position updated successfully! (Position ID: {$id}, Salary ID: {$salaryId}, Code: {$sgLabel})";
                $messageType = 'success';
            } else {
                $message     = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } else {
            $message     = "Error: Salary Grade {$grade} not found in salary table.";
            $messageType = 'danger';
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        $checkQuery = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE position_id = ?");
        $checkQuery->bind_param("i", $id);
        $checkQuery->execute();
        $checkRow = $checkQuery->get_result()->fetch_assoc();
        $checkQuery->close();

        if ($checkRow['count'] > 0) {
            $message     = "Cannot delete! This position is assigned to {$checkRow['count']} employee(s).";
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message     = "Position deleted successfully! (Position ID: {$id})";
                $messageType = 'success';
            } else {
                $message     = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get filter parameters
$filterDept  = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$whereClause = $filterDept > 0 ? "AND p.department_id = $filterDept" : '';

// Salary grades — step_1 rate only
$salaryGrades = $conn->query("
    SELECT salary_grade,
           MIN(CASE WHEN step_no = 1 THEN salary_rate END) AS step_1_rate
    FROM salary
    GROUP BY salary_grade
    ORDER BY CAST(salary_grade AS UNSIGNED)
");

// Departments
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");
$deptRows = [];
while ($d = $departments->fetch_assoc()) { $deptRows[] = $d; }

// Positions list — salary_code no longer joined; derived as SG-XX in PHP
$positions = $conn->query("
    SELECT p.*,
           d.department_name, d.department_code,
           (SELECT COUNT(*) FROM employees WHERE position_id = p.id) AS employee_count
    FROM positions p
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE 1=1 $whereClause
    ORDER BY CAST(p.salary_grade AS UNSIGNED), p.position_title
");
$posRows = [];
while ($r = $positions->fetch_assoc()) { $posRows[] = $r; }

// Statistics
$totalPositions = count($posRows);
$totalEmployees = array_sum(array_column($posRows, 'employee_count'));
$totalDepts     = count($deptRows);

require_once 'includes/header.php';
?>

<!-- Page Header -->
<link rel="stylesheet" href="css/positions.css">
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Positions</span>
    </div>
    <h1 class="page-title">Positions Management</h1>
    <p class="page-subtitle">Manage job positions with accurate salary grade assignments</p>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $messageType; ?>" id="alertMessage">
    <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
    <div class="alert-content"><?php echo $message; ?></div>
    <button class="alert-close" onclick="document.getElementById('alertMessage').remove()"><i class="fas fa-times"></i></button>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid" style="margin-bottom:2rem; display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:1.5rem;">
    <?php
    $statCards = [
        ['icon' => 'briefcase', 'color' => '#e3f2fd', 'icolor' => '#1976d2', 'label' => 'Total Positions',    'value' => $totalPositions],
        ['icon' => 'users',     'color' => '#e8f5e9', 'icolor' => '#388e3c', 'label' => 'Assigned Employees', 'value' => $totalEmployees],
        ['icon' => 'building',  'color' => '#fff3e0', 'icolor' => '#f57c00', 'label' => 'Departments',        'value' => $totalDepts],
    ];
    foreach ($statCards as $sc): ?>
    <div class="stat-card" style="background:#fff; padding:1.5rem; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
        <div style="display:flex; align-items:center; gap:1rem;">
            <div style="width:60px; height:60px; background:<?php echo $sc['color']; ?>; border-radius:12px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-<?php echo $sc['icon']; ?>" style="color:<?php echo $sc['icolor']; ?>; font-size:1.5rem;"></i>
            </div>
            <div>
                <div style="font-size:0.875rem; color:#6c757d;"><?php echo $sc['label']; ?></div>
                <div style="font-size:1.75rem; font-weight:700; color:#212529;"><?php echo $sc['value']; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-briefcase"></i> Position List</h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search positions..." id="searchInput" onkeyup="searchTable()">
            </div>
            <button class="btn btn-primary" onclick="openModal('addPositionModal')">
                <i class="fas fa-plus"></i> Add Position
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card-body" style="padding:var(--space-md) var(--space-xl); border-bottom:1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <select name="department" class="form-control" style="width:auto; min-width:200px;" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php foreach ($deptRows as $dept): ?>
                <option value="<?php echo $dept['id']; ?>" <?php echo $filterDept == $dept['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($dept['department_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterDept > 0): ?>
                <a href="positions.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-body" style="padding:0;">
        <div class="table-container">
            <table class="data-table" id="positionsTable">
                <thead>
                    <tr>
                        <th>Position ID</th>
                        <th>Salary ID</th>
                        <th>Salary Code</th>
                        <th>Position Title</th>
                        <th>SG</th>
                        <th>Basic Salary</th>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($posRows)): ?>
                    <?php foreach ($posRows as $row): ?>
                    <tr>
                        <td><strong><?php echo $row['id']; ?></strong></td>
                        <td>
                            <?php if ($row['salary_id']): ?>
                                <code style="background:#e3f2fd; padding:.25rem .5rem; border-radius:4px; color:#1976d2; font-weight:600;"><?php echo $row['salary_id']; ?></code>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td>
                            <code style="background:#f3e5f5; padding:.25rem .5rem; border-radius:4px; color:#7b1fa2; font-size:.875rem;">
                                <?php echo fmtSG($row['salary_grade']); ?>
                            </code>
                        </td>
                        <td><strong><?php echo htmlspecialchars($row['position_title']); ?></strong></td>
                        <td><span class="badge badge-info">SG-<?php echo $row['salary_grade']; ?></span></td>
                        <td class="currency"><strong><?php echo formatCurrency($row['basic_salary']); ?></strong></td>
                        <td>
                            <?php if ($row['department_name']): ?>
                                <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                            <?php else: ?><span class="text-muted">No Department</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['employee_count'] > 0): ?>
                                <span class="badge badge-success"><i class="fas fa-user"></i> <?php echo $row['employee_count']; ?></span>
                            <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-secondary btn-icon sm"
                                        onclick="editPosition(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                        title="Edit (ID: <?php echo $row['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-icon sm"
                                        onclick="deletePosition(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['position_title']); ?>', <?php echo $row['employee_count']; ?>)"
                                        title="Delete (ID: <?php echo $row['id']; ?>)"
                                        <?php echo $row['employee_count'] > 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding:2rem;">
                            <i class="fas fa-inbox fa-3x" style="opacity:.3; margin-bottom:1rem;"></i>
                            <p>No positions found. Add your first position above.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Shared SG dropdown options -->
<template id="sgOptionsTpl">
    <option value="">-- Select Salary Grade --</option>
    <?php
    if ($salaryGrades && $salaryGrades->num_rows > 0):
        $salaryGrades->data_seek(0);
        while ($sg = $salaryGrades->fetch_assoc()):
            if ($sg['step_1_rate']):
    ?>
    <option value="<?php echo $sg['salary_grade']; ?>"
            data-rate="<?php echo $sg['step_1_rate']; ?>">
        <?php echo fmtSG($sg['salary_grade']); ?> — ₱<?php echo number_format($sg['step_1_rate'], 2); ?>
    </option>
    <?php
            endif;
        endwhile;
    endif;
    ?>
</template>

<!-- Shared Department dropdown options -->
<template id="deptOptionsTpl">
    <option value="">-- Select Department (Optional) --</option>
    <?php foreach ($deptRows as $dept): ?>
    <option value="<?php echo $dept['id']; ?>">
        [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
    </option>
    <?php endforeach; ?>
</template>

<!-- Add Position Modal -->
<div id="addPositionModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:flex-start; justify-content:center; padding:2rem; overflow-y:auto;">
<div style="background:#fff; border-radius:16px; width:100%; max-width:580px; margin:0 auto; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; background:#1a3a5c;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#fff;"><i class="fas fa-plus-circle" style="margin-right:8px;"></i>Add New Position</h3>
        <button onclick="closeModal('addPositionModal')" style="border:none; background:rgba(255,255,255,0.15); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:.9rem; color:#fff;">&#10005;</button>
    </div>
    <form method="POST" id="addPositionForm">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="basic_salary" id="add_basic_salary">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label required">Position Title</label>
                <select name="position_title" id="add_position_title" class="form-control" required onchange="toggleCustomPosition('add')">
                    <option value="">-- Select Position Title --</option>
                    <optgroup label="SG 1-4 (Entry Level)">
                        <option value="Utility Worker I">Utility Worker I</option>
                        <option value="Utility Worker II">Utility Worker II</option>
                        <option value="Laborer I">Laborer I</option>
                        <option value="Laborer II">Laborer II</option>
                        <option value="Messenger">Messenger</option>
                        <option value="Helper">Helper</option>
                    </optgroup>
                    <optgroup label="SG 5-8 (Clerical/Technical)">
                        <option value="Clerk I">Clerk I</option>
                        <option value="Clerk II">Clerk II</option>
                        <option value="Clerk III">Clerk III</option>
                        <option value="Clerk IV">Clerk IV</option>
                        <option value="Administrative Aide I">Administrative Aide I</option>
                        <option value="Administrative Aide II">Administrative Aide II</option>
                        <option value="Administrative Aide III">Administrative Aide III</option>
                        <option value="Driver I">Driver I</option>
                        <option value="Driver II">Driver II</option>
                    </optgroup>
                    <optgroup label="SG 9-11 (Administrative Assistant)">
                        <option value="Administrative Assistant I">Administrative Assistant I</option>
                        <option value="Administrative Assistant II">Administrative Assistant II</option>
                        <option value="Administrative Assistant III">Administrative Assistant III</option>
                        <option value="Bookkeeper I">Bookkeeper I</option>
                        <option value="Bookkeeper II">Bookkeeper II</option>
                    </optgroup>
                    <optgroup label="SG 12-15 (Officers/Specialists)">
                        <option value="Administrative Officer I">Administrative Officer I</option>
                        <option value="Administrative Officer II">Administrative Officer II</option>
                        <option value="Accountant I">Accountant I</option>
                        <option value="Accountant II">Accountant II</option>
                        <option value="Engineer I">Engineer I</option>
                        <option value="Engineer II">Engineer II</option>
                    </optgroup>
                    <optgroup label="Other Positions">
                        <option value="Custom">-- Type Custom Position --</option>
                    </optgroup>
                </select>
                <input type="text" id="add_custom_position" name="custom_position_title" class="form-control" style="display:none; margin-top:10px;" placeholder="Enter custom position title">
            </div>

            <div class="form-group">
                <label class="form-label">Department</label>
                <select name="department_id" id="add_department" class="form-control"></select>
            </div>

            <div class="form-group">
                <label class="form-label required">Salary Grade</label>
                <select name="salary_grade" id="add_salary_grade" class="form-control" required onchange="updateSalary('add')"></select>
            </div>

            <div class="form-group">
                <label class="form-label">Basic Salary (Monthly)</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="text" id="add_salary_display" class="form-control" readonly style="background:#f0f0f0; font-weight:bold;" placeholder="Select Salary Grade first">
                </div>
                <small class="text-muted">Salary is automatically pulled from the salary table (Step 1)</small>
            </div>

            <div style="background:#e3f2fd; border-left:4px solid #2196f3; padding:1rem; border-radius:4px; margin-top:1rem;">
                <div style="display:flex; gap:.75rem; align-items:start;">
                    <i class="fas fa-info-circle" style="color:#2196f3; margin-top:.25rem;"></i>
                    <div style="font-size:.875rem; color:#0d47a1;">
                        <strong>Salary ID Assignment</strong>
                        <p style="margin:.25rem 0 0;">The correct Salary ID will be automatically assigned based on the selected Salary Grade from the salary table.</p>
                    </div>
                </div>
            </div>
        </div>
        <div style="padding:1rem 1.5rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:.75rem;">
            <button type="button" onclick="closeModal('addPositionModal')" style="padding:.5rem 1.25rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151;">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Position</button>
        </div>
    </form>
</div>
</div>

<!-- Edit Position Modal -->
<div id="editPositionModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:flex-start; justify-content:center; padding:2rem; overflow-y:auto;">
<div style="background:#fff; border-radius:16px; width:100%; max-width:580px; margin:0 auto; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; background:#1a3a5c;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#fff;"><i class="fas fa-edit" style="margin-right:8px;"></i>Edit Position</h3>
        <button onclick="closeModal('editPositionModal')" style="border:none; background:rgba(255,255,255,0.15); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:.9rem; color:#fff;">&#10005;</button>
    </div>
    <form method="POST" id="editPositionForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="basic_salary" id="edit_basic_salary">
        <div class="modal-body">
            <div style="background:#fff3e0; border-left:4px solid #ff9800; padding:1rem; border-radius:4px; margin-bottom:1rem;">
                <div style="display:flex; gap:.75rem; align-items:center;">
                    <i class="fas fa-edit" style="color:#f57c00;"></i>
                    <div style="font-size:.875rem; color:#e65100;">
                        <strong>Editing Position ID: <span id="edit_position_id_display">-</span></strong><br>
                        Current Salary ID: <span id="edit_salary_id_display">-</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label required">Position Title</label>
                <input type="text" name="position_title" id="edit_position_title" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Department</label>
                <select name="department_id" id="edit_department" class="form-control"></select>
            </div>

            <div class="form-group">
                <label class="form-label required">Salary Grade</label>
                <select name="salary_grade" id="edit_salary_grade" class="form-control" required onchange="updateSalary('edit')"></select>
            </div>

            <div class="form-group">
                <label class="form-label">Basic Salary (Monthly)</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="text" id="edit_salary_display" class="form-control" readonly style="background:#f0f0f0; font-weight:bold;">
                </div>
                <small class="text-muted">New Salary ID will be assigned based on selection</small>
            </div>
        </div>
        <div style="padding:1rem 1.5rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:.75rem;">
            <button type="button" onclick="closeModal('editPositionModal')" style="padding:.5rem 1.25rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151;">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Position</button>
        </div>
    </form>
</div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
(function() {
    var sgTpl   = document.getElementById('sgOptionsTpl').innerHTML;
    var deptTpl = document.getElementById('deptOptionsTpl').innerHTML;
    ['add_salary_grade', 'edit_salary_grade'].forEach(function(id) {
        document.getElementById(id).innerHTML = sgTpl;
    });
    ['add_department', 'edit_department'].forEach(function(id) {
        document.getElementById(id).innerHTML = deptTpl;
    });
})();

function updateSalary(mode) {
    var sel     = document.getElementById(mode + '_salary_grade');
    var opt     = sel.options[sel.selectedIndex];
    var display = document.getElementById(mode + '_salary_display');
    var hidden  = document.getElementById(mode + '_basic_salary');
    var rate    = opt ? opt.getAttribute('data-rate') : null;
    if (rate) {
        display.value = parseFloat(rate).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
        hidden.value  = rate;
    } else {
        display.value = '';
        hidden.value  = '';
    }
}

function toggleCustomPosition(mode) {
    var sel    = document.getElementById(mode + '_position_title');
    var custom = document.getElementById(mode + '_custom_position');
    var show   = sel.value === 'Custom';
    custom.style.display = show ? 'block' : 'none';
    custom.required      = show;
}

function editPosition(data) {
    document.getElementById('edit_id').value                         = data.id;
    document.getElementById('edit_position_id_display').textContent  = data.id;
    document.getElementById('edit_salary_id_display').textContent    = data.salary_id || '-';
    document.getElementById('edit_position_title').value             = data.position_title;
    document.getElementById('edit_department').value                 = data.department_id || '';
    document.getElementById('edit_salary_grade').value               = data.salary_grade;
    updateSalary('edit');
    openModal('editPositionModal');
}

function deletePosition(id, title, employeeCount) {
    if (employeeCount > 0) {
        alert('Cannot delete "' + title + '" (Position ID: ' + id + ')!\n\nThis position is assigned to ' + employeeCount + ' employee(s).\n\nPlease reassign employees before deleting.');
        return;
    }
    if (confirm('Are you sure you want to delete:\n\nPosition: ' + title + '\nPosition ID: ' + id + '\n\nThis action cannot be undone.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function searchTable() {
    var filter = document.getElementById('searchInput').value.toUpperCase();
    var rows   = document.getElementById('positionsTable').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var text = rows[i].textContent || rows[i].innerText;
        rows[i].style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    }
}

function openModal(id)  { var el = document.getElementById(id); if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; } }
function closeModal(id) { var el = document.getElementById(id); if (el) { el.style.display = 'none';  document.body.style.overflow = '';       } }
document.addEventListener('click', function(e) {
    ['addPositionModal', 'editPositionModal'].forEach(function(id) {
        var el = document.getElementById(id); if (el && e.target === el) closeModal(id);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>