<?php
/**
 * Payroll System - Salary Management
 * Based on Philippine Government Salary Standardization Law (SSL)
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Salary Grades';
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $salaryCode = sanitize($_POST['salary_code']);
        $salaryGrade = sanitize($_POST['salary_grade']);
        $stepNo = (int)$_POST['step_no'];
        $salaryRate = (float)$_POST['salary_rate'];
        
        $stmt = $conn->prepare("INSERT INTO salary (salary_code, salary_grade, step_no, salary_rate) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssid", $salaryCode, $salaryGrade, $stepNo, $salaryRate);
        
        if ($stmt->execute()) {
            $message = 'Salary grade added successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['salary_id'];
        $salaryCode = sanitize($_POST['salary_code']);
        $salaryGrade = sanitize($_POST['salary_grade']);
        $stepNo = (int)$_POST['step_no'];
        $salaryRate = (float)$_POST['salary_rate'];
        
        $stmt = $conn->prepare("UPDATE salary SET salary_code = ?, salary_grade = ?, step_no = ?, salary_rate = ? WHERE salary_id = ?");
        $stmt->bind_param("ssidi", $salaryCode, $salaryGrade, $stepNo, $salaryRate, $id);
        
        if ($stmt->execute()) {
            $message = 'Salary grade updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['salary_id'];
        
        $stmt = $conn->prepare("DELETE FROM salary WHERE salary_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = 'Salary grade deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'update_step') {
        $id = (int)$_POST['salary_id'];
        $stepNo = (int)$_POST['step_no'];
        $salaryRate = isset($_POST['salary_rate']) ? (float)$_POST['salary_rate'] : null;
        
        if ($salaryRate) {
            // Update both step and rate
            $stmt = $conn->prepare("UPDATE salary SET step_no = ?, salary_rate = ? WHERE salary_id = ?");
            $stmt->bind_param("idi", $stepNo, $salaryRate, $id);
        } else {
            // Update only step
            $stmt = $conn->prepare("UPDATE salary SET step_no = ? WHERE salary_id = ?");
            $stmt->bind_param("ii", $stepNo, $id);
        }
        
        if ($stmt->execute()) {
            $message = "Step updated to Step {$stepNo} successfully!";
            $messageType = 'success';
        } else {
            $message = 'Error: ' . $conn->error;
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all salary grades grouped
$salaries = $conn->query("
    SELECT * FROM salary 
    ORDER BY CAST(salary_grade AS UNSIGNED), step_no
");

// Group salaries by grade
$salaryGroups = [];
if ($salaries && $salaries->num_rows > 0) {
    while($row = $salaries->fetch_assoc()) {
        $salaryGroups[$row['salary_grade']][] = $row;
    }
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span>Salary Grades</span>
    </div>
    <h1 class="page-title">Salary Grades & Step Increments</h1>
    <p class="page-subtitle">Manage government salary standardization</p>
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
            <i class="fas fa-money-bill-wave"></i>
            Salary Grade List
        </h2>
        <div class="btn-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search salary grades..." data-search-table="salaryTable">
            </div>
            <button class="btn btn-primary" onclick="Modal.open('addSalaryModal')">
                <i class="fas fa-plus"></i> Add Salary Grade
            </button>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="salaryTable">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Salary Grade</th>
                        <th>Salary Code</th>
                        <th>Steps</th>
                        <th>Salary Range</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($salaryGroups)): ?>
                        <?php foreach($salaryGroups as $grade => $steps): ?>
                            <?php 
                            $firstStep = $steps[0];
                            $lastStep = end($steps);
                            $stepCount = count($steps);
                            ?>
                            <!-- Main Grade Row -->
                            <tr class="grade-row" data-grade="<?php echo $grade; ?>">
                                <td>
                                    <button class="expand-btn" onclick="toggleGrade('<?php echo $grade; ?>')" title="Show all steps">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </td>
                                <td><span class="badge badge-primary badge-lg">SG-<?php echo htmlspecialchars($grade); ?></span></td>
                                <td><code><?php echo htmlspecialchars($firstStep['salary_code']); ?> - <?php echo htmlspecialchars($lastStep['salary_code']); ?></code></td>
                                <td><span class="badge badge-info"><?php echo $stepCount; ?> steps</span></td>
                                <td>
                                    <strong><?php echo formatCurrency($firstStep['salary_rate']); ?></strong>
                                    <span class="text-muted"> to </span>
                                    <strong><?php echo formatCurrency($lastStep['salary_rate']); ?></strong>
                                </td>
                                <td class="text-muted"><?php echo date('M d, Y', strtotime($firstStep['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="toggleGrade('<?php echo $grade; ?>')">
                                        <i class="fas fa-eye"></i> View Steps
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Step Detail Rows (Hidden by default) -->
                            <?php foreach($steps as $step): ?>
                                <tr class="step-row step-row-<?php echo $grade; ?>" style="display: none;">
                                    <td></td>
                                    <td style="padding-left: 2rem;">
                                        <i class="fas fa-level-up-alt fa-rotate-90 text-muted"></i>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($step['salary_code']); ?></code></td>
                                    <td>
                                        <span class="badge badge-info">Step <?php echo $step['step_no']; ?></span>
                                    </td>
                                    <td><strong><?php echo formatCurrency($step['salary_rate']); ?></strong></td>
                                    <td class="text-muted"><?php echo date('M d, Y', strtotime($step['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-secondary btn-icon sm" 
                                                    onclick="editSalary(<?php echo htmlspecialchars(json_encode($step)); ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-icon sm" 
                                                    onclick="deleteSalary(<?php echo $step['salary_id']; ?>, '<?php echo htmlspecialchars($step['salary_code']); ?>')"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2rem;">
                                No salary grades found. Add your first salary grade above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Salary Modal -->
<div class="modal-overlay" id="addSalaryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Add New Salary Grade</h3>
            <button class="modal-close" onclick="Modal.close('addSalaryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="addSalaryForm">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Salary Code</label>
                    <input type="text" name="salary_code" class="form-control" required placeholder="e.g., SG15-STEP3">
                    <span class="form-hint">Unique identifier for this salary grade</span>
                </div>
                <div class="form-group">
                    <label class="form-label required">Salary Grade</label>
                    <select name="salary_grade" class="form-control" required id="add_salary_grade" onchange="updateSalaryRate('add')">
                        <option value="">-- Select Salary Grade --</option>
                        <?php for($i = 1; $i <= 33; $i++): ?>
                            <option value="<?php echo $i; ?>">Salary Grade <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Step No. (Drop down) 1-8</label>
                    <select name="step_no" class="form-control" required id="add_step_no" onchange="updateSalaryRate('add')">
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
                    <span class="form-hint">Step increments represent years of service</span>
                </div>
                <div class="form-group">
                    <label class="form-label required">Salary Rate</label>
                    <input type="number" name="salary_rate" id="add_salary_rate" class="form-control" required step="0.01" min="0" placeholder="0.00">
                    <span class="form-hint">Monthly salary amount in Philippine Pesos</span>
                </div>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Salary Standardization Law (SSL)</strong>
                        <p>Based on Philippine government salary grades. Each step increment typically represents one year of service, with corresponding salary increases.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('addSalaryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Salary Grade
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Salary Modal -->
<div class="modal-overlay" id="editSalaryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Salary Grade</h3>
            <button class="modal-close" onclick="Modal.close('editSalaryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editSalaryForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="salary_id" id="edit_salary_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Salary Code</label>
                    <input type="text" name="salary_code" id="edit_salary_code" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">Salary Grade</label>
                    <select name="salary_grade" id="edit_salary_grade" class="form-control" required onchange="updateSalaryRate('edit')">
                        <option value="">-- Select Salary Grade --</option>
                        <?php for($i = 1; $i <= 33; $i++): ?>
                            <option value="<?php echo $i; ?>">Salary Grade <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Step No. (Drop down) 1-8</label>
                    <select name="step_no" id="edit_step_no" class="form-control" required onchange="updateSalaryRate('edit')">
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
                <div class="form-group">
                    <label class="form-label required">Salary Rate</label>
                    <input type="number" name="salary_rate" id="edit_salary_rate" class="form-control" required step="0.01" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('editSalaryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Salary Grade
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="salary_id" id="delete_salary_id">
</form>

<script>
// Philippine Government Salary Standardization Law (SSL) 2024 rates
const salaryMatrix = {
     1: [13000, 13325, 13655, 13990, 14330, 14675, 15025, 15380],
    2: [14000, 14350, 14705, 15065, 15430, 15800, 16175, 16555],
    3: [15000, 15375, 15755, 16140, 16530, 16925, 17325, 17730],
    4: [16000, 16400, 16805, 17215, 17630, 18050, 18475, 18905],
    5: [17000, 17425, 17855, 18290, 18730, 19175, 19625, 20080],
    6: [18500, 18963, 19430, 19903, 20381, 20864, 21352, 21846],
    7: [20000, 20500, 21008, 21521, 22041, 22567, 23100, 23639],
    8: [21500, 22038, 22583, 23134, 23691, 24255, 24826, 25404],
    9: [23000, 23575, 24158, 24747, 25342, 25944, 26553, 27169],
    10: [24500, 25113, 25733, 26359, 26992, 27632, 28279, 28933],
    11: [26641, 27307, 27981, 28662, 29351, 30047, 30751, 31463],
    12: [29165, 29894, 30631, 31377, 32131, 32894, 33665, 34445],
    13: [32053, 32855, 33666, 34485, 35313, 36150, 36996, 37851],
    14: [35299, 36181, 37072, 37972, 38881, 39799, 40726, 41662],
    15: [38916, 39887, 40867, 41856, 42854, 43861, 44877, 45902],
    16: [42933, 44006, 45088, 46179, 47279, 48388, 49506, 50633],
    17: [47424, 48609, 49803, 51006, 52218, 53439, 54669, 55908],
    18: [52455, 53766, 55086, 56415, 57753, 59100, 60456, 61821],
    19: [58118, 59571, 61033, 62504, 63984, 65473, 66971, 68478],
    20: [64474, 66086, 67707, 69337, 70976, 72624, 74281, 75947],
    21: [71595, 73385, 75184, 76992, 78809, 80635, 82470, 84314],
    22: [79630, 81595, 83569, 85552, 87544, 89545, 91555, 93574],
    23: [88664, 90880, 93105, 95339, 97582, 99834, 102095, 104365],
    24: [98742, 101211, 103689, 106176, 108672, 111177, 113691, 116214],
    25: [109939, 112688, 115446, 118213, 120989, 123774, 126568, 129371],
    26: [122474, 125536, 128607, 131687, 134776, 137874, 140981, 144097],
    27: [136478, 139890, 143311, 146741, 150180, 153628, 157085, 160551],
    28: [152073, 155875, 159686, 163506, 167335, 171173, 175020, 178876],
    29: [169518, 173756, 178003, 182259, 186524, 190798, 195081, 199373],
    30: [188916, 193639, 198371, 203112, 207862, 212621, 217389, 222166],
    31: [210456, 215717, 220987, 226266, 231554, 236851, 242157, 247472],
    32: [234561, 240400, 246248, 252105, 257971, 263846, 269730, 275623],
    33: [261384, 267919, 274463, 281016, 287578, 294149, 300729, 307318]
};

// Toggle grade expansion
function toggleGrade(grade) {
    const stepRows = document.querySelectorAll('.step-row-' + grade);
    const expandBtn = document.querySelector('.grade-row[data-grade="' + grade + '"] .expand-btn i');
    const isVisible = stepRows[0].style.display !== 'none';
    
    stepRows.forEach(row => {
        row.style.display = isVisible ? 'none' : 'table-row';
    });
    
    // Toggle icon
    if (expandBtn) {
        expandBtn.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    }
}

function updateSalaryRate(mode) {
    const gradeSelect = document.getElementById(mode + '_salary_grade');
    const stepSelect = document.getElementById(mode + '_step_no');
    const rateInput = document.getElementById(mode + '_salary_rate');
    
    const grade = parseInt(gradeSelect.value);
    const step = parseInt(stepSelect.value);
    
    if (grade && step && salaryMatrix[grade] && salaryMatrix[grade][step - 1]) {
        rateInput.value = salaryMatrix[grade][step - 1].toFixed(2);
    }
}

function editSalary(data) {
    document.getElementById('edit_salary_id').value = data.salary_id;
    document.getElementById('edit_salary_code').value = data.salary_code;
    document.getElementById('edit_salary_grade').value = data.salary_grade;
    document.getElementById('edit_step_no').value = data.step_no;
    document.getElementById('edit_salary_rate').value = parseFloat(data.salary_rate).toFixed(2);
    Modal.open('editSalaryModal');
}

function deleteSalary(id, code) {
    if (confirm(`Are you sure you want to delete salary grade "${code}"? This action cannot be undone.`)) {
        document.getElementById('delete_salary_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Handle step change functionality removed - use Edit button instead
;
</script>

<style>
.info-box {
    background: #e3f2fd;
    border: 1px solid #2196f3;
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    gap: 1rem;
    align-items: start;
    margin-top: 1rem;
}

.info-box i {
    color: #2196f3;
    font-size: 1.25rem;
    margin-top: 0.25rem;
}

.info-box strong {
    display: block;
    color: #1976d2;
    margin-bottom: 0.25rem;
}

.info-box p {
    margin: 0;
    color: #0d47a1;
    font-size: 0.875rem;
    line-height: 1.4;
}

/* Expandable rows styles */
.grade-row {
    background-color: #f8f9fa;
    font-weight: 500;
}

.grade-row:hover {
    background-color: #e9ecef;
}

.step-row {
    background-color: #fff;
}

.step-row:hover {
    background-color: #f8f9fa;
}

.expand-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    color: var(--primary);
    font-size: 1rem;
    transition: transform 0.2s;
}

.expand-btn:hover {
    color: var(--primary-dark);
    transform: scale(1.1);
}

.badge-lg {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>