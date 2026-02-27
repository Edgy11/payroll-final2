<?php
/**
 * AUTHENTICATION SECURITY
 * This file blocks unauthorized access to all pages
 * Include this at the top of EVERY page to protect it
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/config.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['username']) && 
           isset($_SESSION['login_time']);
}

/**
 * Check session timeout (30 minutes of inactivity)
 */
function checkSessionTimeout() {
    $timeout = 1800; // 30 minutes in seconds
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > $timeout) {
            destroySession();
            return false;
        }
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Validate session token
 */
function validateSessionToken() {
    global $conn;
    
    if (!isset($_SESSION['session_token']) || !isset($_SESSION['user_id'])) {
        return false;
    }
    
    $token = $_SESSION['session_token'];
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT id FROM user_sessions 
        WHERE user_id = ? 
        AND session_token = ? 
        AND is_active = 1 
        AND expires_at > NOW()
    ");
    $stmt->bind_param("is", $user_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Get user information
 */
function getUserInfo() {
    global $conn;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT id, username, full_name, email, role, status 
        FROM users 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get current user's role
 */
function getUserRole() {
    return $_SESSION['role'] ?? '';
}

/**
 * Check if current user is superadmin (admin1)
 */
function isSuperAdmin() {
    $role = getUserRole();
    return $role === 'superadmin' || $role === 'admin';
}

/**
 * Check if current user is admin2 (limited access)
 */
function isAdmin2() {
    return getUserRole() === 'admin2';
}

/**
 * Require superadmin role — redirect with error if not superadmin
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission to view this page.';
        header('Location: index.php');
        exit();
    }
}

/**
 * Destroy session and logout
 */
function destroySession() {
    global $conn;
    
    if (isset($_SESSION['session_token'])) {
        $token = $_SESSION['session_token'];
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
    }
    
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Redirect to login page
 */
function redirectToLogin() {
    // Get the base path dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_path = dirname($_SERVER['PHP_SELF']);
    
    // Remove filename if present
    if (basename($base_path) == 'includes') {
        $base_path = dirname($base_path);
    }
    
    $login_url = $protocol . '://' . $host . $base_path . '/login.php';
    
    // Store the page they were trying to access
    if (!isset($_SESSION['redirect_after_login'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    }
    
    header("Location: $login_url");
    exit();
}

// ============================================
// MAIN SECURITY CHECK
// This runs automatically when file is included
// ============================================

// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Check session timeout
if (!checkSessionTimeout()) {
    redirectToLogin();
}

// Validate session token
if (!validateSessionToken()) {
    destroySession();
    redirectToLogin();
}

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Set security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
?>