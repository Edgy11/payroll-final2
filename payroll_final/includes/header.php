<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Payroll System | City Mayor's Office</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💰</text></svg>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Main Stylesheet -->
   <link rel="stylesheet" href="css/style.css?v=2">
<script src="js/main.js?v=2"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                   <img src="assets/bcd.png" alt="Payroll System Logo" />
                </div>
                <h1 class="sidebar-title">Payroll System</h1>
                <p class="sidebar-subtitle">City Mayor's Office</p>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-title">Main Menu</span>
                    <a href="index.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <span class="nav-section-title">Management</span>
                    <a href="departments.php" class="nav-item">
                        <i class="fas fa-building"></i>
                        <span>Departments</span>
                    </a>
                    <a href="positions.php" class="nav-item">
                        <i class="fas fa-briefcase"></i>
                        <span>Positions</span>
                    </a>
                    <a href="employees.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>Employees</span>
                    </a>
                    <a href="salary.php" class="nav-item">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Salary</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <span class="nav-section-title">Payroll</span>
                    <a href="payroll.php" class="nav-item">
                        <i class="fas fa-money-check-dollar"></i>
                        <span>Payroll List</span>
                    </a>
                    <a href="payroll_create.php" class="nav-item">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Create Payroll</span>
                    </a>
                    <a href="payroll_generate.php" class="nav-item">
                        <i class="fas fa-calculator"></i>
                        <span>Generate Payroll</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <span class="nav-section-title">Reports</span>
                    <a href="reports.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </nav>
            
            <div style="padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
    <!-- User Info -->
    <?php if (function_exists('getUserInfo')): 
        $user = getUserInfo();
        if ($user):
    ?>
    <div style="padding: 0.75rem; background: rgba(255,255,255,0.1); border-radius: 0.5rem; margin-bottom: 0.75rem;">
        <div style="color: white; font-weight: 600; font-size: 0.875rem;">
            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
        <div style="color: var(--primary-300); font-size: 0.75rem; text-transform: capitalize;">
            <?php echo htmlspecialchars($user['role']); ?>
        </div>
    </div>
    <?php endif; endif; ?>
    
    <!-- Logout Button -->
    <a href="logout.php" class="nav-item" style="background: rgba(239, 68, 68, 0.2); color: #fee2e2; justify-content: center;">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
    
    <div style="text-align: center; margin-top: 0.75rem;">
        <small style="color: var(--primary-400);">
            v1.0.0 &copy; <?php echo date('Y'); ?>
        </small>
    </div>
</div>
        </aside>
        
        <!-- Mobile Toggle -->
        <button class="btn btn-primary btn-icon no-print" 
                onclick="toggleSidebar()" 
                style="position: fixed; top: 1rem; left: 1rem; z-index: 99; display: none;">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Main Content -->
        <main class="main-content">
