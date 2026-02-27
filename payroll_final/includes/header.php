<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Payroll System | City Mayor's Office</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💰</text></svg>">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>

<!-- ── Sidebar Overlay (mobile) ── -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ── Mobile Topbar ── -->
<header class="mobile-topbar d-flex d-lg-none align-items-center justify-content-between px-3 no-print">
    <button class="topbar-toggle" onclick="toggleSidebar()" aria-label="Open menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-brand">
        <img src="assets/bcd.png" alt="Logo" style="height:32px;width:32px;object-fit:contain;border-radius:6px;">
        <span>Payroll System</span>
    </div>
    <div style="width:40px"></div><!-- spacer -->
</header>

<div class="app-container">

    <!-- ── Sidebar ── -->
    <aside class="sidebar" id="appSidebar">

        <!-- Close button (mobile only) -->
        <button class="sidebar-close d-lg-none" onclick="closeSidebar()">
            <i class="fas fa-times"></i>
        </button>

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
                    <i class="fas fa-home"></i><span>Dashboard</span>
                </a>
            </div>

            <?php if (!function_exists('isAdmin2') || !isAdmin2()): ?>
            <div class="nav-section">
                <span class="nav-section-title">Management</span>
                <a href="departments.php" class="nav-item">
                    <i class="fas fa-building"></i><span>Departments</span>
                </a>
                <a href="positions.php" class="nav-item">
                    <i class="fas fa-briefcase"></i><span>Positions</span>
                </a>
                <a href="employees.php" class="nav-item">
                    <i class="fas fa-users"></i><span>Employees</span>
                </a>
                <a href="salary.php" class="nav-item">
                    <i class="fas fa-money-bill-wave"></i><span>Salary</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <span class="nav-section-title">Payroll</span>
                <a href="payroll.php" class="nav-item">
                    <i class="fas fa-money-check-dollar"></i><span>Payroll List</span>
                </a>
                <?php if (!function_exists('isAdmin2') || !isAdmin2()): ?>
                <a href="payroll_create.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i><span>Create Payroll</span>
                </a>
                <a href="payroll_generate.php" class="nav-item">
                    <i class="fas fa-calculator"></i><span>Generate Payroll</span>
                </a>
                <?php endif; ?>
            </div>

            <?php if (!function_exists('isAdmin2') || !isAdmin2()): ?>
            <div class="nav-section">
                <span class="nav-section-title">Reports</span>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i><span>Reports</span>
                </a>
            </div>
            <div class="nav-section">
                <span class="nav-section-title">System</span>
                <a href="register.php" class="nav-item">
                    <i class="fas fa-user-plus"></i><span>Register User</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <?php if (function_exists('getUserInfo')):
                $user = getUserInfo();
                if ($user): ?>
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="sidebar-user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
            </div>
            <?php endif; endif; ?>

            <a href="logout.php" class="nav-item sidebar-logout">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>

            <div class="sidebar-version">v1.0.0 &copy; <?php echo date('Y'); ?></div>
        </div>
    </aside>

    <!-- ── Main Content ── -->
    <main class="main-content">