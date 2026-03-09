<?php
/**
 * LOGIN PAGE - DEBUG VERSION
 * This will help you see what's wrong
 */

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'includes/config.php';

$error = '';
$success = '';
$debug_info = [];

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been logged out successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Debug: Show what was entered
    $debug_info[] = "Username entered: " . htmlspecialchars($username);
    $debug_info[] = "Password length: " . strlen($password);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $debug_info[] = "Users found in database: " . $result->num_rows;
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            $debug_info[] = "User status: " . $user['status'];
            $debug_info[] = "Password hash starts with: " . substr($user['password'], 0, 20);
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated.';
            }
            // Verify password
            elseif (password_verify($password, $user['password'])) {
                // SUCCESS!
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;
                
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $expires = date('Y-m-d H:i:s', time() + ($remember ? 2592000 : 86400));
                
                $stmt = $conn->prepare("
                    INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("issss", $user['id'], $session_token, $ip, $user_agent, $expires);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                header("Location: index.php");
                exit();
            } else {
                $error = 'Invalid username or password';
                $debug_info[] = "❌ Password verification FAILED";
                $debug_info[] = "The password hash in database does not match 'admin123'";
            }
        } else {
            $error = 'Invalid username or password';
            $debug_info[] = "❌ No user found with username: " . htmlspecialchars($username);
        }
    }
}
?>
<link rel="stylesheet" href="css/login.css">
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Payroll System (Debug)</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    
    </head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/bcd.png" alt="Logo">
                </div>
                <h1 class="login-title">Payroll System</h1>
                <p class="login-subtitle">City of Bacolod</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($debug_info)): ?>
                <div class="debug-box">
                    <h4>🔍 Debug Information:</h4>
                    <ul>
                        <?php foreach ($debug_info as $info): ?>
                            <li><?php echo $info; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            value=""
                            required
                        >
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me for 30 days</label>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <!-- <div class="default-credentials">
                <strong><i class="fas fa-info-circle"></i> Default Login:</strong>
                Username: <code><strong>admin</strong></code><br>
                Password: <code><strong>admin123</strong></code>
            </div>
            
            <div class="alert alert-info" style="margin-top: 1rem;">
                <strong>Can't login?</strong> Upload <code>test_password.php</code> and visit it to diagnose the problem.
            </div> -->
            
            <div class="login-footer">
                © <?php echo date('Y'); ?> City of Bacolod. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>