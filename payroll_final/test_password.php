<?php
/**
 * PASSWORD TEST SCRIPT
 * This script will help you debug the login issue
 * Upload this to your root folder as test_password.php
 * Access: http://localhost/payroll_final/test_password.php
 */

require_once 'includes/config.php';

echo "<h1>Password Test & Debug</h1>";
echo "<hr>";

// Test 1: Check if users table exists
echo "<h2>Test 1: Check Users Table</h2>";
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "✅ Users table exists<br>";
} else {
    echo "❌ Users table DOES NOT exist. Run the SQL file!<br>";
}

// Test 2: Check if admin user exists
echo "<h2>Test 2: Check Admin User</h2>";
$result = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "✅ Admin user exists<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "Full Name: " . $user['full_name'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Status: " . $user['status'] . "<br>";
    echo "Password Hash: " . substr($user['password'], 0, 30) . "...<br>";
} else {
    echo "❌ Admin user DOES NOT exist. Run the SQL file!<br>";
}

// Test 3: Test password verification
echo "<h2>Test 3: Password Verification Test</h2>";
$test_password = 'admin123';
$result = $conn->query("SELECT password FROM users WHERE username = 'admin'");
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $stored_hash = $user['password'];
    
    echo "Testing password: <strong>$test_password</strong><br>";
    
    if (password_verify($test_password, $stored_hash)) {
        echo "✅ Password 'admin123' is CORRECT!<br>";
        echo "<strong style='color: green;'>The password should work in login.php</strong><br>";
    } else {
        echo "❌ Password 'admin123' is WRONG!<br>";
        echo "<strong style='color: red;'>The password hash in database is incorrect</strong><br>";
        
        // Generate a new hash
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<br><strong>FIX:</strong> Run this SQL command:<br>";
        echo "<textarea style='width: 100%; height: 80px; font-family: monospace;'>";
        echo "UPDATE users SET password = '$new_hash' WHERE username = 'admin';";
        echo "</textarea><br>";
    }
}

// Test 4: Generate new password hash
echo "<h2>Test 4: Generate New Password Hash</h2>";
echo "<form method='POST'>";
echo "Enter password to hash: <input type='text' name='new_password' value='admin123'>";
echo " <button type='submit'>Generate Hash</button>";
echo "</form>";

if (isset($_POST['new_password'])) {
    $new_pass = $_POST['new_password'];
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    echo "<br><strong>New hash for password '$new_pass':</strong><br>";
    echo "<textarea style='width: 100%; height: 100px; font-family: monospace;'>$new_hash</textarea><br>";
    echo "<br><strong>SQL to update admin password:</strong><br>";
    echo "<textarea style='width: 100%; height: 80px; font-family: monospace;'>";
    echo "UPDATE users SET password = '$new_hash' WHERE username = 'admin';";
    echo "</textarea>";
}

// Test 5: Check database connection
echo "<h2>Test 5: Database Connection</h2>";
if ($conn->ping()) {
    echo "✅ Database connection is working<br>";
    echo "Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "<br>";
} else {
    echo "❌ Database connection failed<br>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If all tests pass with ✅, your login should work.</p>";
echo "<p>If you see ❌, follow the instructions above to fix it.</p>";
echo "<br>";
echo "<a href='login.php'>Go to Login Page</a>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 50px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1 {
        color: #2d6394;
    }
    h2 {
        color: #1a3a5c;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #ddd;
    }
    textarea {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin: 10px 0;
    }
    button {
        background: #2d6394;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
    }
    button:hover {
        background: #1a3a5c;
    }
    input[type="text"] {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 5px;
        width: 200px;
    }
</style>