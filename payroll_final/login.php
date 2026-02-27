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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Payroll System (Debug)</title>
    
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
            padding: 1rem;
        }
        
        .login-container {
            width: 100%;
            max-width: 550px;
        }
        
        .login-card {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #c9a227, #ddb93a);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 4px 20px rgba(201, 162, 39, 0.3);
        }
        
        .login-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: #718096;
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2d6394;
            box-shadow: 0 0 0 3px rgba(45, 99, 148, 0.1);
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        
        .input-icon .form-control {
            padding-left: 2.75rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: 400;
            font-size: 0.875rem;
            color: #4a5568;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #2d6394, #1a3a5c);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(45, 99, 148, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 0.875rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .debug-box {
            background: #f9fafb;
            border: 1px solid #d1d5db;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.8125rem;
            font-family: monospace;
        }
        
        .debug-box h4 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .debug-box ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        
        .debug-box li {
            margin: 0.25rem 0;
            color: #4b5563;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #a0aec0;
        }
        
        .default-credentials {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #78350f;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1.5rem;
            font-size: 0.8125rem;
        }
        
        .default-credentials strong {
            display: block;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-building-columns"></i>
                </div>
                <h1 class="login-title">Payroll System</h1>
                <p class="login-subtitle">City Mayor's Office</p>
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
                            value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>"
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
                            value="admin123"
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
                © <?php echo date('Y'); ?> City Mayor's Office. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>