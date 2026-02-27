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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User - Payroll System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0c1929 0%, #2d6394 100%);
            margin: 0;
            padding: 1.5rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .register-wrapper {
            width: 100%;
            max-width: 560px;
        }

        /* Back link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }

        .register-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        /* Header */
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-logo {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #2d6394, #1a3a5c);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.75rem;
            color: white;
            box-shadow: 0 4px 20px rgba(45, 99, 148, 0.35);
        }
        .register-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        .register-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Alerts */
        .alert {
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
        }
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

        /* Form */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
        }
        .form-group label .required {
            color: #ef4444;
            margin-left: 2px;
        }
        .input-wrap {
            position: relative;
        }

        /* FIX: Use child combinator (>) so only the direct leading icon gets pointer-events: none */
        /* This prevents the rule from blocking clicks on the eye toggle button's icon */
        .input-wrap > i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.875rem;
            pointer-events: none;
        }

        .input-wrap input,
        .input-wrap select {
            width: 100%;
            padding: 0.7rem 0.9rem 0.7rem 2.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-family: inherit;
            color: #1f2937;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
            appearance: none;
            -webkit-appearance: none;
        }
        .input-wrap input:focus,
        .input-wrap select:focus {
            outline: none;
            border-color: #2d6394;
            box-shadow: 0 0 0 3px rgba(45, 99, 148, 0.12);
        }
        .input-wrap input.error,
        .input-wrap select.error {
            border-color: #ef4444;
        }

        /* Role selector pills */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .role-option { display: none; }
        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 0.9rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .role-label:hover { border-color: #93c5fd; background: #eff6ff; }
        .role-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .role-superadmin .role-icon { background: #fef3c7; color: #d97706; }
        .role-admin2    .role-icon { background: #dbeafe; color: #2563eb; }
        .role-name { font-weight: 700; font-size: 0.8125rem; color: #374151; }
        .role-desc { font-size: 0.7rem; color: #6b7280; line-height: 1.3; }

        .role-option:checked + .role-label {
            border-color: #2d6394;
            background: #eff6ff;
            box-shadow: 0 0 0 3px rgba(45, 99, 148, 0.12);
        }
        .role-option:checked + .role-label .role-name { color: #1a3a5c; }

        /* Divider */
        .form-divider {
            border: none;
            border-top: 1px solid #f3f4f6;
            margin: 0.25rem 0 1.25rem;
        }
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin-bottom: 0.75rem;
        }

        /* Password strength */
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: #e5e7eb;
            margin-top: 0.4rem;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 2px;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }
        .strength-text {
            font-size: 0.7rem;
            margin-top: 0.25rem;
            color: #9ca3af;
        }

        /* FIX: Toggle password button — added z-index so it sits above the input layer */
        .toggle-pw {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 0;
            font-size: 0.875rem;
            z-index: 2;           /* ensures button is always clickable */
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-pw:hover { color: #6b7280; }

        /* FIX: Password fields need right padding to prevent text going under the eye icon */
        .input-wrap input.pw-field {
            padding-right: 2.75rem;
        }

        /* Submit */
        .btn-register {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #2d6394, #1a3a5c);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(45, 99, 148, 0.4);
        }
        .btn-register:active { transform: translateY(0); }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #a0aec0;
        }

        @media (max-width: 520px) {
            .register-card { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
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