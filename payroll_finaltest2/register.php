<?php
/**
 * REGISTER PAGE
 * Only accessible by logged-in superadmin users.
 * Creates new admin2 (Payroll Officer) accounts.
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Only superadmins can create accounts
requireSuperAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $role      = $_POST['role'] ?? 'admin2';
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    // --- Validation ---
    if (empty($username) || empty($full_name) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
        $error = 'Username must be 4–30 characters and contain only letters, numbers, or underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['superadmin', 'admin2'])) {
        $error = 'Invalid role selected.';
    } else {
        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->get_result()->num_rows > 0
            ? $error = 'Username "' . htmlspecialchars($username) . '" is already taken.'
            : null;
        $check->close();

        if (!$error) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $status = 'active';
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed, $full_name, $email, $role, $status);

            if ($stmt->execute()) {
                $success = 'Account for <strong>' . htmlspecialchars($full_name) . '</strong> (<code>' . htmlspecialchars($username) . '</code>) created successfully!';
                // Clear form
                $username = $full_name = $email = $password = $confirm = '';
                $role = 'admin2';
            } else {
                $error = 'Database error: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<link rel="stylesheet" href="css/register.css">
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User - Payroll System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">

    </head>
<body>
<div class="register-wrapper">

    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="register-card">
        <div class="register-header">
            <div class="register-logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="register-title">Create User Account</h1>
            <p class="register-subtitle">Add a new staff member to the Payroll System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm" novalidate>

            <!-- Role Selection -->
            <p class="section-label">Account Role</p>
            <div class="role-selector">
                <input type="radio" name="role" id="role_superadmin" value="superadmin" class="role-option"
                    <?php echo (($_POST['role'] ?? 'admin2') === 'superadmin') ? 'checked' : ''; ?>>
                <label for="role_superadmin" class="role-label role-superadmin">
                    <div class="role-icon"><i class="fas fa-shield-halved"></i></div>
                    <span class="role-name">Super Admin</span>
                    <span class="role-desc">Full system access</span>
                </label>

                <input type="radio" name="role" id="role_admin2" value="admin2" class="role-option"
                    <?php echo (($_POST['role'] ?? 'admin2') === 'admin2') ? 'checked' : ''; ?>>
                <label for="role_admin2" class="role-label role-admin2">
                    <div class="role-icon"><i class="fas fa-money-check-dollar"></i></div>
                    <span class="role-name">Payroll Officer</span>
                    <span class="role-desc">Dashboard &amp; payroll list only</span>
                </label>
            </div>

            <hr class="form-divider">

            <!-- Account Info -->
            <p class="section-label">Account Information</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-at"></i>
                        <input type="text" id="username" name="username" placeholder="e.g. jdelacruz"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            autocomplete="off" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-user"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="e.g. Juan Dela Cruz"
                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="e.g. juan@payroll.gov"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <hr class="form-divider">

            <!-- Password -->
            <p class="section-label">Set Password</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password"
                            placeholder="Min. 6 characters" required class="pw-field">
                        <button type="button" class="toggle-pw" onclick="togglePassword('password', this)" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <div class="strength-text" id="strengthText"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Re-enter password" required class="pw-field">
                        <button type="button" class="toggle-pw" onclick="togglePassword('confirm_password', this)" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="strength-text" id="matchText"></div>
                </div>
            </div>

            <button type="submit" class="btn-register">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="login-footer">
            © <?php echo date('Y'); ?> City Mayor's Office. All rights reserved.
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Password strength meter
document.getElementById('password').addEventListener('input', function () {
    const val = this.value;
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w: '0%',   bg: '#e5e7eb', label: '' },
        { w: '25%',  bg: '#ef4444', label: '🔴 Weak' },
        { w: '50%',  bg: '#f59e0b', label: '🟡 Fair' },
        { w: '75%',  bg: '#3b82f6', label: '🔵 Good' },
        { w: '100%', bg: '#10b981', label: '🟢 Strong' },
    ];
    const level = val.length === 0 ? levels[0] : levels[Math.min(score, 4)];
    fill.style.width = level.w;
    fill.style.background = level.bg;
    text.textContent = level.label;
    checkMatch();
});

// Password match check
document.getElementById('confirm_password').addEventListener('input', checkMatch);
function checkMatch() {
    const pw  = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    const mt  = document.getElementById('matchText');
    if (cpw.length === 0) { mt.textContent = ''; return; }
    if (pw === cpw) {
        mt.textContent = '✅ Passwords match';
        mt.style.color = '#10b981';
    } else {
        mt.textContent = '❌ Passwords do not match';
        mt.style.color = '#ef4444';
    }
}

// Username lowercase auto-format
document.getElementById('username').addEventListener('input', function () {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
});
</script>
</body>
</html>