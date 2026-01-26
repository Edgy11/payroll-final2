<?php
/**
 * Payroll System - Step Increment History
 * Automatically calculates step increments every 3 years based on date hired
 * Integrates with salary table for accurate salary rates
 */

require_once 'includes/config.php';

$pageTitle = 'Step Increment History';

// Get employee ID from URL
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

if ($employeeId <= 0) {
    header('Location: employees.php');
    exit;
}

// Get employee details
$employeeQuery = $conn->prepare("
    SELECT e.*, d.department_name, d.department_code, p.position_title, p.salary_grade
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.id = ?
");
$employeeQuery->bind_param("i", $employeeId);
$employeeQuery->execute();
$employee = $employeeQuery->get_result()->fetch_assoc();

if (!$employee) {
    header('Location: employees.php');
    exit;
}

// Function to calculate step increment history based on date hired
function calculateStepIncrementHistory($conn, $dateHired, $salaryGrade, $employeeId) {
    if (!$dateHired || !$salaryGrade) {
        return [];
    }
    
    $history = [];
    $hireDate = new DateTime($dateHired);
    $today = new DateTime();
    
    // Calculate years of service
    $yearsOfService = $hireDate->diff($today)->y;
    
    // Calculate current step (starts at 1, increases every 3 years, max 8)
    $currentStep = min(8, floor($yearsOfService / 3) + 1);
    
    // Generate history for each step increment
    for ($step = 1; $step <= $currentStep; $step++) {
        // Calculate the date for this step increment
        $yearsToAdd = ($step - 1) * 3;
        $stepDate = clone $hireDate;
        $stepDate->modify("+{$yearsToAdd} years");
        
        // Check if this step date has passed
        if ($stepDate <= $today) {
            $isActive = ($step == $currentStep) ? 'Y' : 'N';
            
            // Get salary information from salary table
            $salaryQuery = $conn->prepare("
                SELECT salary_id, salary_code, salary_rate 
                FROM salary 
                WHERE salary_grade = ? AND step_no = ?
                LIMIT 1
            ");
            $salaryQuery->bind_param("si", $salaryGrade, $step);
            $salaryQuery->execute();
            $salaryData = $salaryQuery->get_result()->fetch_assoc();
            
            $history[] = [
                'step_inc' => $step,
                'salary_id' => $salaryData ? $salaryData['salary_id'] : null,
                'salary_code' => $salaryData ? $salaryData['salary_code'] : null,
                'salary_rate' => $salaryData ? $salaryData['salary_rate'] : null,
                'is_active' => $isActive,
                'effective_date' => $stepDate->format('Y-m-d'),
                'created_at' => $stepDate->format('Y-m-d H:i:s')
            ];
        }
    }
    
    return $history;
}

// Calculate step increment history
$stepHistory = calculateStepIncrementHistory(
    $conn,
    $employee['date_hired'], 
    $employee['salary_grade'],
    $employee['id']
);

// Get position details
$positionDetails = null;
if ($employee['position_id']) {
    $posQuery = $conn->prepare("SELECT * FROM positions WHERE id = ?");
    $posQuery->bind_param("i", $employee['position_id']);
    $posQuery->execute();
    $positionDetails = $posQuery->get_result()->fetch_assoc();
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i></a>
        <span>/</span>
        <a href="employees.php">Employees</a>
        <span>/</span>
        <span>Step Increment History</span>
    </div>
    <h1 class="page-title">Step Increment History</h1>
    <p class="page-subtitle">
        <?php 
        $fullName = $employee['last_name'] . ', ' . $employee['first_name'];
        if ($employee['middle_name']) {
            $fullName .= ' ' . substr($employee['middle_name'], 0, 1) . '.';
        }
        if ($employee['suffix']) {
            $fullName .= ' ' . $employee['suffix'];
        }
        echo htmlspecialchars($fullName);
        ?>
        <code style="margin-left: 1rem;"><?php echo htmlspecialchars($employee['employee_id']); ?></code>
    </p>
</div>

<!-- Employee Summary Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body">
        <div class="employee-info-grid">
            <div class="info-item">
                <div class="info-label">Department</div>
                <div class="info-value">
                    <?php if ($employee['department_name']): ?>
                        <span class="badge badge-primary"><?php echo htmlspecialchars($employee['department_code']); ?></span>
                        <?php echo htmlspecialchars($employee['department_name']); ?>
                    <?php else: ?>
                        <span class="text-muted">Not assigned</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Current Position</div>
                <div class="info-value">
                    <?php if ($employee['position_title']): ?>
                        <?php echo htmlspecialchars($employee['position_title']); ?>
                        <small class="text-muted">(SG-<?php echo $employee['salary_grade']; ?>)</small>
                    <?php else: ?>
                        <span class="text-muted">Not assigned</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Date Hired</div>
                <div class="info-value">
                    <?php if ($employee['date_hired']): ?>
                        <strong><?php echo date('F d, Y', strtotime($employee['date_hired'])); ?></strong>
                    <?php else: ?>
                        <span class="text-muted">Not specified</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Years of Service</div>
                <div class="info-value">
                    <?php 
                    if ($employee['date_hired']) {
                        $hireDate = new DateTime($employee['date_hired']);
                        $today = new DateTime();
                        $interval = $hireDate->diff($today);
                        $yearsOfService = $interval->y;
                        $monthsOfService = $interval->m;
                        echo "<strong style='color: var(--primary); font-size: 1.2rem;'>$yearsOfService</strong> years";
                        if ($monthsOfService > 0) {
                            echo " and <strong>$monthsOfService</strong> months";
                        }
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Current Step Increment</div>
                <div class="info-value">
                    <?php 
                    if ($employee['date_hired']) {
                        $hireDate = new DateTime($employee['date_hired']);
                        $today = new DateTime();
                        $yearsOfService = $hireDate->diff($today)->y;
                        $currentStep = min(8, floor($yearsOfService / 3) + 1);
                        echo "<strong style='color: var(--success); font-size: 1.3rem;'>Step $currentStep</strong>";
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Next Increment Date</div>
                <div class="info-value">
                    <?php 
                    if ($employee['date_hired']) {
                        $hireDate = new DateTime($employee['date_hired']);
                        $today = new DateTime();
                        $yearsOfService = $hireDate->diff($today)->y;
                        $currentStep = min(8, floor($yearsOfService / 3) + 1);
                        
                        if ($currentStep < 8) {
                            $yearsToNextIncrement = $currentStep * 3;
                            $nextIncrementDate = clone $hireDate;
                            $nextIncrementDate->modify("+{$yearsToNextIncrement} years");
                            echo "<strong style='color: var(--warning);'>" . $nextIncrementDate->format('F d, Y') . "</strong>";
                            
                            // Calculate days until next increment
                            $daysUntil = $today->diff($nextIncrementDate)->days;
                            if ($daysUntil > 0) {
                                $yearsUntil = floor($daysUntil / 365);
                                $remainingDays = $daysUntil % 365;
                                if ($yearsUntil > 0) {
                                    echo "<br><small class='text-muted'>($yearsUntil year(s) and $remainingDays days remaining)</small>";
                                } else {
                                    echo "<br><small class='text-muted'>($daysUntil days remaining)</small>";
                                }
                            }
                        } else {
                            echo '<span class="badge badge-info">Maximum Step Reached</span>';
                        }
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step Increment History Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i>
            Step Increment Records
            <small class="text-muted" style="font-size: 0.875rem; font-weight: normal; margin-left: 1rem;">
                (Automatic calculation: Step increases every 3 years)
            </small>
        </h2>
        <div class="btn-group">
            <a href="employees.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Employees
            </a>
        </div>
    </div>
    
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="data-table" id="stepIncTable">
                <thead>
                    <tr>
                        <th>Step Increment</th>
                        <th>Salary ID</th>
                        <th>Salary Code</th>
                        <th>Effective Date</th>
                        <th>Years of Service</th>
                        <th>Salary Grade</th>
                        <th>Monthly Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stepHistory)): ?>
                        <?php foreach ($stepHistory as $row): ?>
                            <?php
                            // Calculate years of service at this step
                            $stepDate = new DateTime($row['effective_date']);
                            $hireDate = new DateTime($employee['date_hired']);
                            $yearsAtStep = $hireDate->diff($stepDate)->y;
                            ?>
                            <tr class="<?php echo $row['is_active'] === 'Y' ? 'current-step-row' : ''; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <?php if ($row['is_active'] === 'Y'): ?>
                                            <i class="fas fa-star" style="color: var(--warning);"></i>
                                        <?php endif; ?>
                                        <strong style="font-size: 1.2rem; color: var(--primary);">
                                            Step <?php echo $row['step_inc']; ?>
                                        </strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['salary_id']): ?>
                                        <code><?php echo $row['salary_id']; ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['salary_code']): ?>
                                        <code><?php echo htmlspecialchars($row['salary_code']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="white-space: nowrap;">
                                        <strong><?php echo date('M d, Y', strtotime($row['effective_date'])); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                            $effectiveDate = new DateTime($row['effective_date']);
                                            $today = new DateTime();
                                            $diff = $effectiveDate->diff($today);
                                            if ($diff->y > 0) {
                                                echo $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
                                            } elseif ($diff->m > 0) {
                                                echo $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
                                            } elseif ($diff->d > 0) {
                                                echo $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                                            } else {
                                                echo 'Today';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo $yearsAtStep; ?></strong> year<?php echo $yearsAtStep != 1 ? 's' : ''; ?>
                                </td>
                                <td>
                                    <?php if ($employee['salary_grade']): ?>
                                        <span class="badge badge-info">SG-<?php echo $employee['salary_grade']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['salary_rate']): ?>
                                        <strong style="font-size: 1.1rem; color: var(--success);">
                                            <?php echo formatCurrency($row['salary_rate']); ?>
                                        </strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['is_active'] === 'Y'): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle"></i> Current
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-history"></i> Previous
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding: 3rem;">
                                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                                <strong>No step increment records available.</strong>
                                <p style="margin-top: 0.5rem;">
                                    <?php if (!$employee['date_hired']): ?>
                                        Employee's date hired is not specified.
                                    <?php elseif (!$employee['salary_grade']): ?>
                                        Employee's salary grade is not assigned.
                                    <?php else: ?>
                                        Employee just started.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if (!empty($stepHistory)): ?>
    <div class="card-footer" style="background: var(--gray-50); padding: 1rem 1.5rem; border-top: 1px solid var(--gray-200);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="text-muted">
                <i class="fas fa-info-circle"></i>
                Total Step Increments: <strong><?php echo count($stepHistory); ?></strong>
            </div>
            <div class="text-muted">
                <i class="fas fa-calculator"></i>
                <strong>Formula:</strong> New step every 3 years (Max: Step 8 at 21+ years)
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Information Box -->


<style>
.employee-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1rem;
    color: var(--gray-900);
    font-weight: 500;
}

.current-step-row {
    background-color: #fff9e6 !important;
    border-left: 4px solid var(--warning);
}

.current-step-row:hover {
    background-color: #fff4d5 !important;
}

.data-table tbody tr:hover {
    background-color: var(--gray-50);
}

.card-footer {
    font-size: 0.875rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>