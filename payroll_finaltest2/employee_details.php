<?php
/**
 * Payroll System - Employee Details Page
 * Shows correct salary based on current step increment
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireSuperAdmin();

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

// Get active step from step_increment table, then re-lookup salary by grade+step
$currentStep     = null;
$currentSalary   = $employee['basic_salary'];
$yearsOfService  = 0;
$monthsOfService = 0;

if ($employee['date_hired']) {
    $interval = (new DateTime($employee['date_hired']))->diff(new DateTime());
    $yearsOfService  = $interval->y;
    $monthsOfService = $interval->m;
}

// PRIMARY: active step from DB
$siQ = $conn->prepare("
    SELECT step_inc
    FROM step_increment
    WHERE employee_id = ? AND is_active = 'Y'
    LIMIT 1
");
$siQ->bind_param("i", $employeeId);
$siQ->execute();
$siRow = $siQ->get_result()->fetch_assoc();
$siQ->close();

if ($siRow) {
    $currentStep = $siRow['step_inc'];
    // Always use employee's own salary_grade — never trust stored salary_id
    if ($employee['salary_grade']) {
        $salaryQuery = $conn->prepare("SELECT salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
        $salaryQuery->bind_param("si", $employee['salary_grade'], $currentStep);
        $salaryQuery->execute();
        $salaryData = $salaryQuery->get_result()->fetch_assoc();
        if ($salaryData) $currentSalary = $salaryData['salary_rate'];
        $salaryQuery->close();
    }
} else {
    // FALLBACK: calculate from date_hired
    $currentStep = $employee['date_hired']
        ? min(8, (int)floor($yearsOfService / 3) + 1)
        : 1;
    if ($employee['salary_grade']) {
        $salaryQuery = $conn->prepare("SELECT salary_rate FROM salary WHERE salary_grade = ? AND step_no = ? LIMIT 1");
        $salaryQuery->bind_param("si", $employee['salary_grade'], $currentStep);
        $salaryQuery->execute();
        $salaryData = $salaryQuery->get_result()->fetch_assoc();
        if ($salaryData) $currentSalary = $salaryData['salary_rate'];
        $salaryQuery->close();
    }
}

require_once 'includes/header.php';
?>

<!-- Page Header -->
<link rel="stylesheet" href="css/employee_details.css">
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
                <i class="fas fa-history"></i> Promotion and Increment History
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

<?php require_once 'includes/footer.php'; ?>