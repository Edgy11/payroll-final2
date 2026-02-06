<?php
/**
 * LOGOUT PAGE
 * Destroys session and redirects to login
 */

session_start();

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Deactivate session token
    if (isset($_SESSION['session_token'])) {
        $token = $_SESSION['session_token'];
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
    }
}

// Destroy session
$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

// Redirect to login
header("Location: login.php?logout=success");
exit();
?>