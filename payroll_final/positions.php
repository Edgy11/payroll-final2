<?php
/**
 * Payroll System - Positions Management (FIXED)
 * With Accurate Salary ID Tracking for Your Database Structure
 */

require_once 'includes/config.php';

$pageTitle = 'Positions';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Handle custom position title
        $title = '';
        if (!empty($_POST['position_title']) && $_POST['position_title'] === 'Custom') {
            $title = sanitize($_POST['custom_position_title']);
        } else {
            $title = sanitize($_POST['position_title']);
        }
        
        $grade = $_POST['salary_grade']; // Keep as string
        $step = (int)$_POST['salary_step'];
        $salary = (float)$_POST['basic_salary'];
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        // Find the correct salary_id from salary table based on grade and step
        $salaryIdQuery = $conn->prepare("SELECT salary_id, salary_code FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
        $salaryIdQuery->bind_param("si", $grade, $step);
        $salaryIdQuery->execute();
        $salaryIdResult = $salaryIdQuery->get_result();
        
        if ($salaryIdRow = $salaryIdResult->fetch_assoc()) {
            $salaryId = $salaryIdRow['salary_id'];
            $salaryCode = $salaryIdRow['salary_code'];
            
            $stmt = $conn->prepare("INSERT INTO positions (salary_id, position_title, salary_grade, salary_step, basic_salary, department_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isiidi", $salaryId, $title, $grade, $step, $salary, $deptId);
            
            if ($stmt->execute()) {
                $insertedId = $stmt->insert_id;
                $message = "Position added successfully! (Position ID: {$insertedId}, Salary ID: {$salaryId}, Code: {$salaryCode})";
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } else {
            $message = "Error: Salary Grade {$grade}, Step {$step} not found in salary table. Please add it in Salary Management first.";
            $messageType = 'danger';
        }
        $salaryIdQuery->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        
        // Handle custom position title
        $title = '';
        if (!empty($_POST['position_title']) && $_POST['position_title'] === 'Custom') {
            $title = sanitize($_POST['custom_position_title']);
        } else {
            $title = sanitize($_POST['position_title']);
        }
        
        $grade = $_POST['salary_grade']; // Keep as string
        $step = (int)$_POST['salary_step'];
        $salary = (float)$_POST['basic_salary'];
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        // Find the correct salary_id from salary table
        $salaryIdQuery = $conn->prepare("SELECT salary_id, salary_code FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
        $salaryIdQuery->bind_param("si", $grade, $step);
        $salaryIdQuery->execute();
        $salaryIdResult = $salaryIdQuery->get_result();
        
        if ($salaryIdRow = $salaryIdResult->fetch_assoc()) {
            $salaryId = $salaryIdRow['salary_id'];
            $salaryCode = $salaryIdRow['salary_code'];
            
            $stmt = $conn->prepare("UPDATE positions SET salary_id = ?, position_title = ?, salary_grade = ?, salary_step = ?, basic_salary = ?, department_id = ? WHERE id = ?");
            $stmt->bind_param("isiidii", $salaryId, $title, $grade, $step, $salary, $deptId, $id);
            
            if ($stmt->execute()) {
                $message = "Position updated successfully! (Position ID: {$id}, Salary ID: {$salaryId}, Code: {$salaryCode})";
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        } else {
            $message = "Error: Salary Grade {$grade}, Step {$step} not found in salary table.";
            $messageType = 'danger';
        }
        $salaryIdQuery->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Check if position is assigned to any employee
        $checkQuery = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE position_id = ?");
        $checkQuery->bind_param("i", $id);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        $checkRow = $checkResult->fetch_assoc();
        
        if ($checkRow['count'] > 0) {
            $message = "Cannot delete! This position is assigned to {$checkRow['count']} employee(s).";
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Position deleted successfully! (Position ID: {$id})";
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
        $checkQuery->close();
    }
}

// Get filter parameters
$filterDept = isset($_GET['department']) ? (int)$_GET['department'] : 0;

// Build query with filters
$whereClause = "1=1";
if ($filterDept > 0) {
    $whereClause .= " AND p.department_id = $filterDept";
}

// Get all salary grades for dropdown (grouped by grade, showing step 1 and step 8 range)
$salaryGradesQuery = "
    SELECT 
        salary_grade,
        MIN(CASE WHEN step_no = 1 THEN salary_rate END) as step_1,
        MIN(CASE WHEN step_no = 2 THEN salary_rate END) as step_2,
        MIN(CASE WHEN step_no = 3 THEN salary_rate END) as step_3,
        MIN(CASE WHEN step_no = 4 THEN salary_rate END) as step_4,
        MIN(CASE WHEN step_no = 5 THEN salary_rate END) as step_5,
        MIN(CASE WHEN step_no = 6 THEN salary_rate END) as step_6,
        MIN(CASE WHEN step_no = 7 THEN salary_rate END) as step_7,
        MIN(CASE WHEN step_no = 8 THEN salary_rate END) as step_8
    FROM salary
    GROUP BY salary_grade
    ORDER BY CAST(salary_grade AS UNSIGNED)
";
$salaryGrades = $conn->query($salaryGradesQuery);

// Get all departments for dropdown
$departments = $conn->query("SELECT id, department_name, department_code FROM departments ORDER BY department_name");

// Get all positions with department and salary info
$positions = $conn->query("
    SELECT 
        p.*,
        d.department_name, 
        d.department_code,
        s.salary_code,
        (SELECT COUNT(*) FROM employees WHERE position_id = p.id) as employee_count
    FROM positions p 
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN salary s ON p.salary_id = s.salary_id
    WHERE $whereClause
    ORDER BY CAST(p.salary_grade AS UNSIGNED), p.position_title
");

require_once 'includes/header.php';
?>

<!-- Page Header -->
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
        <button class="alert-close" onclick="document.getElementById('alertMessage').remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid" style="margin-bottom: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
    <?php
    $totalPositions = $positions ? $positions->num_rows : 0;
    $positions->data_seek(0);
    $totalEmployees = 0;
    while($p = $positions->fetch_assoc()) {
        $totalEmployees += $p['employee_count'];
    }
    $positions->data_seek(0);
    ?>
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 60px; height: 60px; background: #e3f2fd; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-briefcase" style="color: #1976d2; font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 0.875rem; color: #6c757d;">Total Positions</div>
                <div style="font-size: 1.75rem; font-weight: 700; color: #212529;"><?php echo $totalPositions; ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 60px; height: 60px; background: #e8f5e9; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-users" style="color: #388e3c; font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 0.875rem; color: #6c757d;">Assigned Employees</div>
                <div style="font-size: 1.75rem; font-weight: 700; color: #212529;"><?php echo $totalEmployees; ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 60px; height: 60px; background: #fff3e0; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-building" style="color: #f57c00; font-size: 1.5rem;"></i>
            </div>
            <div>
                <div style="font-size: 0.875rem; color: #6c757d;">Departments</div>
                <div style="font-size: 1.75rem; font-weight: 700; color: #212529;">
                    <?php 
                    $deptCount = $departments ? $departments->num_rows : 0;
                    echo $deptCount;
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-briefcase"></i>
            Position List
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search positions..." id="searchInput" onkeyup="searchTable()">
            </div>
            <button class="btn btn-primary" onclick="Modal.open('addPositionModal')">
                <i class="fas fa-plus"></i> Add Position
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card-body" style="padding: var(--space-md) var(--space-xl); border-bottom: 1px solid var(--gray-100);">
        <form method="GET" class="filter-bar">
            <select name="department" class="form-control" style="width: auto; min-width: 200px;" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php 
                $departments->data_seek(0);
                while($dept = $departments->fetch_assoc()): 
                ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $filterDept == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <?php if ($filterDept > 0): ?>
                <a href="positions.php" class="btn btn-secondary btn-sm">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="positionsTable">
                <thead>
                    <tr>
                        <th>Position ID</th>
                        <th>Salary ID</th>
                        <th>Salary Code</th>
                        <th>Position Title</th>
                        <th>SG</th>
                        <th>Step</th>
                        <th>Basic Salary</th>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($positions && $positions->num_rows > 0): ?>
                        <?php 
                        $positions->data_seek(0);
                        while($row = $positions->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo $row['id']; ?></strong></td>
                                <td>
                                    <?php if ($row['salary_id']): ?>
                                        <code style="background: #e3f2fd; padding: 0.25rem 0.5rem; border-radius: 4px; color: #1976d2; font-weight: 600;">
                                            <?php echo $row['salary_id']; ?>
                                        </code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['salary_code']): ?>
                                        <code style="background: #f3e5f5; padding: 0.25rem 0.5rem; border-radius: 4px; color: #7b1fa2; font-size: 0.875rem;">
                                            <?php echo htmlspecialchars($row['salary_code']); ?>
                                        </code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['position_title']); ?></strong></td>
                                <td><span class="badge badge-info">SG-<?php echo $row['salary_grade']; ?></span></td>
                                <td><span class="badge badge-secondary">Step <?php echo $row['salary_step'] ?? 1; ?></span></td>
                                <td class="currency"><strong><?php echo formatCurrency($row['basic_salary']); ?></strong></td>
                                <td>
                                    <?php if ($row['department_name']): ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No Department</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['employee_count'] > 0): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-user"></i> <?php echo $row['employee_count']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
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
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding: 2rem;">
                                <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 1rem;"></i>
                                <p>No positions found. Add your first position above.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Position Modal -->
<div class="modal-overlay" id="addPositionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New Position</h3>
            <button class="modal-close" onclick="Modal.close('addPositionModal')">
                <i class="fas fa-times"></i>
            </button>
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
                    <input type="text" id="add_custom_position" name="custom_position_title" class="form-control" style="display: none; margin-top: 10px;" placeholder="Enter custom position title">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- Select Department (Optional) --</option>
                        <?php 
                        $departments->data_seek(0);
                        while($dept = $departments->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $dept['id']; ?>">
                                [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <select name="salary_grade" id="add_salary_grade" class="form-control" required onchange="updateSalaryAdd()">
                            <option value="">-- Select Salary Grade --</option>
                            <?php 
                            if ($salaryGrades && $salaryGrades->num_rows > 0):
                                $salaryGrades->data_seek(0);
                                while($sg = $salaryGrades->fetch_assoc()): 
                                    if ($sg['step_1'] && $sg['step_8']):
                            ?>
                                <option value="<?php echo $sg['salary_grade']; ?>" 
                                        data-step1="<?php echo $sg['step_1']; ?>"
                                        data-step2="<?php echo $sg['step_2']; ?>"
                                        data-step3="<?php echo $sg['step_3']; ?>"
                                        data-step4="<?php echo $sg['step_4']; ?>"
                                        data-step5="<?php echo $sg['step_5']; ?>"
                                        data-step6="<?php echo $sg['step_6']; ?>"
                                        data-step7="<?php echo $sg['step_7']; ?>"
                                        data-step8="<?php echo $sg['step_8']; ?>">
                                    SG-<?php echo $sg['salary_grade']; ?> (₱<?php echo number_format($sg['step_1'], 2); ?> - ₱<?php echo number_format($sg['step_8'], 2); ?>)
                                </option>
                            <?php 
                                    endif;
                                endwhile; 
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Step</label>
                        <select name="salary_step" id="add_salary_step" class="form-control" required onchange="updateSalaryAdd()">
                            <option value="">-- Select Step --</option>
                            <option value="1" selected>Step 1</option>
                            <option value="2">Step 2</option>
                            <option value="3">Step 3</option>
                            <option value="4">Step 4</option>
                            <option value="5">Step 5</option>
                            <option value="6">Step 6</option>
                            <option value="7">Step 7</option>
                            <option value="8">Step 8</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Basic Salary (Monthly)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="add_salary_display" class="form-control" readonly style="background-color: #f0f0f0; font-weight: bold;" placeholder="Select SG and Step first">
                    </div>
                    <small class="text-muted">Salary is automatically calculated from salary table</small>
                </div>
                
                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                    <div style="display: flex; gap: 0.75rem; align-items: start;">
                        <i class="fas fa-info-circle" style="color: #2196f3; margin-top: 0.25rem;"></i>
                        <div style="font-size: 0.875rem; color: #0d47a1;">
                            <strong>Salary ID Assignment</strong>
                            <p style="margin: 0.25rem 0 0 0;">The correct Salary ID will be automatically assigned based on the selected Salary Grade and Step from the salary table.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('addPositionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Position
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Position Modal -->
<div class="modal-overlay" id="editPositionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Position</h3>
            <button class="modal-close" onclick="Modal.close('editPositionModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editPositionForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="basic_salary" id="edit_basic_salary">
            <div class="modal-body">
                <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <i class="fas fa-edit" style="color: #f57c00;"></i>
                        <div style="font-size: 0.875rem; color: #e65100;">
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
                    <select name="department_id" id="edit_department" class="form-control">
                        <option value="">-- Select Department (Optional) --</option>
                        <?php 
                        $departments->data_seek(0);
                        while($dept = $departments->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $dept['id']; ?>">
                                [<?php echo htmlspecialchars($dept['department_code']); ?>] <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Salary Grade</label>
                        <select name="salary_grade" id="edit_salary_grade" class="form-control" required onchange="updateSalaryEdit()">
                            <option value="">-- Select Salary Grade --</option>
                            <?php 
                            if ($salaryGrades && $salaryGrades->num_rows > 0):
                                $salaryGrades->data_seek(0);
                                while($sg = $salaryGrades->fetch_assoc()): 
                                    if ($sg['step_1'] && $sg['step_8']):
                            ?>
                                <option value="<?php echo $sg['salary_grade']; ?>" 
                                        data-step1="<?php echo $sg['step_1']; ?>"
                                        data-step2="<?php echo $sg['step_2']; ?>"
                                        data-step3="<?php echo $sg['step_3']; ?>"
                                        data-step4="<?php echo $sg['step_4']; ?>"
                                        data-step5="<?php echo $sg['step_5']; ?>"
                                        data-step6="<?php echo $sg['step_6']; ?>"
                                        data-step7="<?php echo $sg['step_7']; ?>"
                                        data-step8="<?php echo $sg['step_8']; ?>">
                                    SG-<?php echo $sg['salary_grade']; ?> (₱<?php echo number_format($sg['step_1'], 2); ?> - ₱<?php echo number_format($sg['step_8'], 2); ?>)
                                </option>
                            <?php 
                                    endif;
                                endwhile; 
                            endif;
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Salary Step</label>
                        <select name="salary_step" id="edit_salary_step" class="form-control" required onchange="updateSalaryEdit()">
                            <option value="">-- Select Step --</option>
                            <option value="1">Step 1</option>
                            <option value="2">Step 2</option>
                            <option value="3">Step 3</option>
                            <option value="4">Step 4</option>
                            <option value="5">Step 5</option>
                            <option value="6">Step 6</option>
                            <option value="7">Step 7</option>
                            <option value="8">Step 8</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Basic Salary (Monthly)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" id="edit_salary_display" class="form-control" readonly style="background-color: #f0f0f0; font-weight: bold;">
                    </div>
                    <small class="text-muted">New Salary ID will be assigned based on selection</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('editPositionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Position
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function toggleCustomPosition(mode) {
    const select = document.getElementById(mode + '_position_title');
    const customInput = document.getElementById(mode + '_custom_position');
    
    if (select.value === 'Custom') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
    }
}

function updateSalaryAdd() {
    const gradeSelect = document.getElementById('add_salary_grade');
    const stepSelect = document.getElementById('add_salary_step');
    const salaryDisplay = document.getElementById('add_salary_display');
    const salaryHidden = document.getElementById('add_basic_salary');
    
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const step = stepSelect.value;
    
    if (selectedOption && step) {
        const stepKey = 'step' + step;
        const salary = selectedOption.getAttribute('data-' + stepKey);
        
        if (salary) {
            salaryDisplay.value = parseFloat(salary).toLocaleString('en-PH', {minimumFractionDigits: 2});
            salaryHidden.value = salary;
        }
    }
}

function updateSalaryEdit() {
    const gradeSelect = document.getElementById('edit_salary_grade');
    const stepSelect = document.getElementById('edit_salary_step');
    const salaryDisplay = document.getElementById('edit_salary_display');
    const salaryHidden = document.getElementById('edit_basic_salary');
    
    const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
    const step = stepSelect.value;
    
    if (selectedOption && step) {
        const stepKey = 'step' + step;
        const salary = selectedOption.getAttribute('data-' + stepKey);
        
        if (salary) {
            salaryDisplay.value = parseFloat(salary).toLocaleString('en-PH', {minimumFractionDigits: 2});
            salaryHidden.value = salary;
        }
    }
}

function editPosition(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_position_id_display').textContent = data.id;
    document.getElementById('edit_salary_id_display').textContent = data.salary_id || '-';
    document.getElementById('edit_position_title').value = data.position_title;
    document.getElementById('edit_department').value = data.department_id || '';
    document.getElementById('edit_salary_grade').value = data.salary_grade;
    document.getElementById('edit_salary_step').value = data.salary_step || 1;
    
    // Update salary display
    updateSalaryEdit();
    
    Modal.open('editPositionModal');
}

function deletePosition(id, title, employeeCount) {
    if (employeeCount > 0) {
        alert(`Cannot delete "${title}" (Position ID: ${id})!\n\nThis position is assigned to ${employeeCount} employee(s).\n\nPlease reassign employees before deleting.`);
        return;
    }
    
    if (confirm(`Are you sure you want to delete:\n\nPosition: ${title}\nPosition ID: ${id}\n\nThis action cannot be undone.`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Search functionality
function searchTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('positionsTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td');
        let txtValue = '';
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                txtValue += td[j].textContent || td[j].innerText;
            }
        }
        tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    }
}
</script>

<style>
.alert-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    margin-left: auto;
    color: inherit;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.alert-close:hover {
    opacity: 1;
}
</style>

<?php require_once 'includes/footer.php'; ?>