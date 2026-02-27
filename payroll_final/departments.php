<?php
/**
 * Payroll System - Departments Management
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

$pageTitle = 'Departments';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['department_name']);
        $code = sanitize($_POST['department_code']);
        $desc = sanitize($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO departments (department_name, department_code, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $code, $desc);
        
        if ($stmt->execute()) {
            $message = 'Department added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['department_name']);
        $code = sanitize($_POST['department_code']);
        $desc = sanitize($_POST['description']);
        
        $stmt = $conn->prepare("UPDATE departments SET department_name = ?, department_code = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $code, $desc, $id);
        
        if ($stmt->execute()) {
            $message = 'Department updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Check if department has employees - properly filter by department_id
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE department_id = ? AND is_active = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        if ($count > 0) {
            $message = "Cannot delete department. It has $count employee(s) assigned.";
            $messageType = 'warning';
        } else {
            $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Department deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
}

// Get all departments with employee count - properly filtered by department
// Sort by: 1) Departments with active employees first, 2) Then alphabetically
$departments = $conn->query("
    SELECT d.*, 
           COUNT(CASE WHEN e.is_active = 1 THEN 1 END) as employee_count 
    FROM departments d 
    LEFT JOIN employees e ON d.id = e.department_id
    GROUP BY d.id 
    ORDER BY 
        CASE WHEN COUNT(CASE WHEN e.is_active = 1 THEN 1 END) > 0 THEN 0 ELSE 1 END,
        d.department_name ASC
");

require_once 'includes/header.php';

// Calculate department statistics
$totalDepartments = 0;
$departmentsWithEmployees = 0;
$emptyDepartments = 0;

if ($departments) {
    $departments->data_seek(0); // Reset pointer
    while($dept = $departments->fetch_assoc()) {
        $totalDepartments++;
        if ($dept['employee_count'] > 0) {
            $departmentsWithEmployees++;
        } else {
            $emptyDepartments++;
        }
    }
    $departments->data_seek(0); // Reset pointer again for display
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Departments</span>
    </div>
    <h1 class="page-title">Departments</h1>
    <p class="page-subtitle">Manage organizational departments</p>
</div>

<!-- Department Statistics -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: var(--space-lg);">
    <div class="stat-card info">
        <div class="stat-icon">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-value"><?php echo $totalDepartments; ?></div>
        <div class="stat-label">Total Departments</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?php echo $departmentsWithEmployees; ?></div>
        <div class="stat-label">Active Departments</div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-value"><?php echo $emptyDepartments; ?></div>
        <div class="stat-label">Empty Departments</div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <i class="alert-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
        <div class="alert-content"><?php echo $message; ?></div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-building"></i>
            Department List
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search departments..." data-search-table="departmentsTable">
            </div>
            <button class="btn btn-primary" onclick="openModal('addDepartmentModal')">
                <i class="fas fa-plus"></i> Add Department
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="departmentsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Employees</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($departments && $departments->num_rows > 0): ?>
                        <?php while($row = $departments->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($row['department_code']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['department_name']); ?></strong>
                                    <?php if ($row['employee_count'] > 0): ?>
                                        <i class="fas fa-star" style="color: var(--warning); margin-left: 8px;" title="Active Department"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($row['description'] ?: '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['employee_count'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo $row['employee_count']; ?> staff
                                    </span>
                                </td>
                                <td class="text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-info btn-icon sm" 
                                                onclick="window.location.href='employees.php?department_id=<?php echo $row['id']; ?>'"
                                                title="View Employees">
                                            <i class="fas fa-users"></i>
                                        </button>
                                        <button class="btn btn-secondary btn-icon sm" 
                                                onclick="editDepartment(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-icon sm" 
                                                onclick="deleteDepartment(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['department_name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding: 2rem;">
                                No departments found. Add your first department above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div id="addDepartmentModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
<div style="background:#fff; border-radius:16px; width:100%; max-width:560px; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; background:#1a3a5c;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#fff;"><i class="fas fa-plus-circle" style="margin-right:8px;"></i>Add New Department</h3>
        <button onclick="closeModal('addDepartmentModal')" style="border:none; background:rgba(255,255,255,0.15); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:0.9rem; color:#fff;">&#10005;</button>
    </div>
        <form method="POST" id="addDepartmentForm">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Department Code</label>
                    <input type="text" name="department_code" class="form-control" required maxlength="20" placeholder="e.g., CMO">
                    <span class="form-hint">Short code for the department (max 20 characters)</span>
                </div>
                <div class="form-group">
                    <label class="form-label required">Department Name</label>
                    <input type="text" name="department_name" class="form-control" required placeholder="e.g., City Mayor's Office">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the department"></textarea>
                </div>
            </div>
            <div style="padding:1rem 1.5rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" onclick="closeModal('addDepartmentModal')" style="padding:0.5rem 1.25rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151;">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Department
                </button>
            </div>
        </form>
</div>
</div>

<!-- Edit Department Modal -->
<div id="editDepartmentModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
<div style="background:#fff; border-radius:16px; width:100%; max-width:560px; box-shadow:0 25px 50px rgba(0,0,0,0.25); overflow:hidden;">
    <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; background:#1a3a5c;">
        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:#fff;"><i class="fas fa-edit" style="margin-right:8px;"></i>Edit Department</h3>
        <button onclick="closeModal('editDepartmentModal')" style="border:none; background:rgba(255,255,255,0.15); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:0.9rem; color:#fff;">&#10005;</button>
    </div>
        <form method="POST" id="editDepartmentForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Department Code</label>
                    <input type="text" name="department_code" id="edit_code" class="form-control" required maxlength="20">
                </div>
                <div class="form-group">
                    <label class="form-label required">Department Name</label>
                    <input type="text" name="department_name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div style="padding:1rem 1.5rem; background:#f9fafb; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:0.75rem;">
                <button type="button" onclick="closeModal('editDepartmentModal')" style="padding:0.5rem 1.25rem; border:2px solid #d1d5db; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#374151;">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Department
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
function editDepartment(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_code').value = data.department_code;
    document.getElementById('edit_name').value = data.department_name;
    document.getElementById('edit_description').value = data.description || '';
    openModal('editDepartmentModal');
}

function deleteDepartment(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function openModal(id) {
    var el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
document.addEventListener('click', function(e) {
    ['addDepartmentModal','editDepartmentModal'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && e.target === el) closeModal(id);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>