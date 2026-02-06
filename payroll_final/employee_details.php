<?php
/**
 * Payroll System - Employee Details Page
 * Shows correct salary based on current step increment
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$pageTitle = 'Employee Details';

// Get employee ID from URL
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($employeeId <= 0) {
    header('Location: employees.php');
    exit;
}

// Get employee details
$employeeQuery = $conn->prepare("
    SELECT e.*, d.department_name, d.department_code, p.position_title, p.salary_grade, p.basic_salary
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

// Calculate current step based on date hired
$currentStep = 1;
$yearsOfService = 0;
$monthsOfService = 0;
$currentSalary = $employee['basic_salary']; // Default to position salary

if ($employee['date_hired']) {
    $hireDate = new DateTime($employee['date_hired']);
    $today = new DateTime();
    $interval = $hireDate->diff($today);
    $yearsOfService = $interval->y;
    $monthsOfService = $interval->m;
    
    // Calculate current step (every 3 years, max 8)
    $currentStep = min(8, floor($yearsOfService / 3) + 1);
    
    // Get correct salary from salary table based on salary grade and current step
    if ($employee['salary_grade']) {
        $salaryQuery = $conn->prepare("
            SELECT salary_rate 
            FROM salary 
            WHERE salary_grade = ? AND step_no = ?
            LIMIT 1
        ");
        $salaryQuery->bind_param("si", $employee['salary_grade'], $currentStep);
        $salaryQuery->execute();
        $salaryResult = $salaryQuery->get_result();
        
        if ($salaryData = $salaryResult->fetch_assoc()) {
            $currentSalary = $salaryData['salary_rate'];
        }
    }
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
        <span>Employee Details</span>
    </div>
    <h1 class="page-title">Employee Details</h1>
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

<!-- Action Buttons -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem;">
        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
            
                
            <a href="step_inc_history.php?employee_id=<?php echo $employee['id']; ?>" class="btn btn-info">
                <i class="fas fa-history"></i> Step Increment History
            </a>
            <a href="payroll_create.php?employee=<?php echo $employee['id']; ?>" class="btn btn-success">
                <i class="fas fa-file-invoice-dollar"></i> Create Payroll
            </a>
        </div>
    </div>
</div>

<!-- Employee Details Grid -->
<div class="employee-details-grid">
    <!-- Personal Information Section -->
    <div class="details-card">
        <div class="details-card-header">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
        </div>
        <div class="details-card-body">
            <div class="details-row">
                <div class="details-label">Full Name:</div>
                <div class="details-value">
                    <strong>
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
                    </strong>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Employee ID:</div>
                <div class="details-value"><code><?php echo htmlspecialchars($employee['employee_id']); ?></code></div>
            </div>
            <div class="details-row">
                <div class="details-label">Date of Birth:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['date_of_birth']) {
                        echo date('F d, Y', strtotime($employee['date_of_birth']));
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Age:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['age']) {
                        echo $employee['age'] . ' years old';
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Sex:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['sex']) {
                        $icon = $employee['sex'] === 'Male' ? 'mars' : 'venus';
                        echo '<i class="fas fa-' . $icon . '"></i> ' . htmlspecialchars($employee['sex']);
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information Section -->
    <div class="details-card">
        <div class="details-card-header">
            <h3><i class="fas fa-address-book"></i> Contact Information</h3>
        </div>
        <div class="details-card-body">
            <div class="details-row">
                <div class="details-label">Email:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['email']) {
                        echo '<i class="fas fa-envelope"></i> ' . htmlspecialchars($employee['email']);
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Phone:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['phone']) {
                        echo '<i class="fas fa-phone"></i> ' . htmlspecialchars($employee['phone']);
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Address:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['address']) {
                        echo '<i class="fas fa-map-marker-alt"></i> ' . nl2br(htmlspecialchars($employee['address']));
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Employment Information Section -->
    <div class="details-card">
        <div class="details-card-header">
            <h3><i class="fas fa-briefcase"></i> Employment Information</h3>
        </div>
        <div class="details-card-body">
            <div class="details-row">
                <div class="details-label">Date Hired:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['date_hired']) {
                        echo '<strong>' . date('F d, Y', strtotime($employee['date_hired'])) . '</strong>';
                    } else {
                        echo '<span class="text-muted">Not specified</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Department:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['department_name']) {
                        echo '<span class="badge badge-primary">' . htmlspecialchars($employee['department_code']) . '</span> ';
                        echo htmlspecialchars($employee['department_name']);
                    } else {
                        echo '<span class="text-muted">Not assigned</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Position:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['position_title']) {
                        echo htmlspecialchars($employee['position_title']);
                        if ($employee['salary_grade']) {
                            echo ' <span class="badge badge-info">SG-' . $employee['salary_grade'] . '</span>';
                        }
                    } else {
                        echo '<span class="text-muted">Not assigned</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Basic Salary:</div>
                <div class="details-value">
                    <?php 
                    if ($currentSalary) {
                        echo '<strong style="color: var(--success); font-size: 1.1rem;">' . formatCurrency($currentSalary) . '</strong>';
                    } else {
                        echo '<span class="text-muted">Not assigned</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Employment Status:</div>
                <div class="details-value">
                    <?php echo htmlspecialchars($employee['employment_status']); ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Status:</div>
                <div class="details-value">
                    <?php if ($employee['is_active']): ?>
                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information Section -->
    <div class="details-card">
        <div class="details-card-header">
            <h3><i class="fas fa-info-circle"></i> System Information</h3>
        </div>
        <div class="details-card-body">
            <div class="details-row">
                <div class="details-label">Created At:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['created_at']) {
                        echo date('F d, Y g:i A', strtotime($employee['created_at']));
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="details-row">
                <div class="details-label">Last Updated:</div>
                <div class="details-value">
                    <?php 
                    if ($employee['updated_at']) {
                        echo date('F d, Y g:i A', strtotime($employee['updated_at']));
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.employee-details-grid {
    display: flex;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: .5rem;
}

.details-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.details-card-header {
    background: linear-gradient(135deg, var(--primary), #4f46e5);
    color: white;
    padding: 1rem 1.5rem;
}

.details-card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.details-card-body {
    padding: 0;
}

.details-row {
    display: flex;
    grid-template-columns: 140px 1fr;
    gap: 5.0rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.details-row:last-child {
    border-bottom: none;
}

.details-row:hover {
    background: var(--gray-50);
}

.details-label {
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.9rem;
}

.details-value {
    color: var(--gray-900);
    font-size: 0.95rem;
}

.details-value code {
    background: var(--gray-200);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
}

.details-value i {
    color: var(--gray-500);
    margin-right: 0.5rem;
}

@media (max-width: 768px) {
    .employee-details-grid {
        grid-template-columns: 1fr;
    }
    
    .details-row {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
    
    .details-label {
        font-size: 0.85rem;
    }
    
    .details-value {
        font-size: 0.9rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>