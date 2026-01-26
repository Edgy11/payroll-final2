<?php
/**
 * Database Configuration for Payroll System
 * Configure these settings for your XAMPP environment
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'payroll_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    $conn->select_db(DB_NAME);
} else {
    die("Error creating database: " . $conn->error);
}

// Create departments table
$sql = "CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);

// Create positions table
$sql = "CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_title VARCHAR(100) NOT NULL,
    salary_grade INT NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
)";
$conn->query($sql);

// Create employees table
$sql = "CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    date_hired DATE,
    department_id INT,
    position_id INT,
    employment_status ENUM('Regular', 'Contractual', 'Job Order', 'Casual') DEFAULT 'Regular',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL
)";
$conn->query($sql);

// Create payroll table
$sql = "CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_period VARCHAR(50) NOT NULL,
    payroll_month VARCHAR(20) NOT NULL,
    payroll_year INT NOT NULL,
    period_type ENUM('1-15', '16-31') NOT NULL,
    basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    pera DECIMAL(12,2) NOT NULL DEFAULT 2000.00,
    gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
    
    -- Deductions
    wtax DECIMAL(12,2) NOT NULL DEFAULT 0,
    philhealth DECIMAL(12,2) NOT NULL DEFAULT 0,
    gsis DECIMAL(12,2) NOT NULL DEFAULT 0,
    pagibig DECIMAL(12,2) NOT NULL DEFAULT 0,
    provident DECIMAL(12,2) NOT NULL DEFAULT 0,
    bcgeu DECIMAL(12,2) NOT NULL DEFAULT 0,
    nocgem DECIMAL(12,2) NOT NULL DEFAULT 0,
    bacgem DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Draft', 'Approved', 'Paid') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)";
$conn->query($sql);

// Create salary table
$sql = "CREATE TABLE IF NOT EXISTS salary (
    salary_id INT AUTO_INCREMENT PRIMARY KEY,
    salary_rate DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($sql);


// Insert default departments if empty
$result = $conn->query("SELECT COUNT(*) as count FROM departments");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $departments = [
        ['City Mayor\'s Office', 'CMO', 'Office of the City Mayor'],
        ['City Administrator\'s Office', 'CAO', 'Office of the City Administrator'],
        ['City Accounting Office', 'CACCO', 'City Accounting Department'],
        ['City Budget Office', 'CBO', 'City Budget Department'],
        ['City Treasurer\'s Office', 'CTO', 'City Treasurer Department'],
        ['City Human Resource Office', 'CHRO', 'Human Resource Management'],
        ['City Engineering Office', 'CEO', 'Engineering Department'],
        ['City Health Office', 'CHO', 'Health Services Department'],
        ['City Social Welfare Office', 'CSWO', 'Social Welfare and Development']
    ];
    
    $stmt = $conn->prepare("INSERT INTO departments (department_name, department_code, description) VALUES (?, ?, ?)");
    foreach ($departments as $dept) {
        $stmt->bind_param("sss", $dept[0], $dept[1], $dept[2]);
        $stmt->execute();
    }
    $stmt->close();
}

// Helper functions
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}

function formatCurrency($amount) {
    return '₱ ' . number_format($amount, 2);
}

function calculateGSIS($basicSalary) {
    // GSIS contribution rate: 9% of basic salary
    return $basicSalary * 0.09;
}

function calculatePhilHealth($basicSalary) {
    // PhilHealth: 5% of basic salary (employee share is 2.5%)
    $monthlyPremium = $basicSalary * 0.05;
    return min($monthlyPremium / 2, 5000); // Employee share, max 5000
}

function calculatePagibig($basicSalary) {
    // Pag-IBIG: Fixed 200 for > 5000 salary
    if ($basicSalary > 5000) {
        return 200;
    } else if ($basicSalary > 1500) {
        return $basicSalary * 0.02;
    } else {
        return $basicSalary * 0.01;
    }
}

function calculateWithholdingTax($taxableIncome) {
    // Simplified tax computation based on Philippine tax table
    if ($taxableIncome <= 20833) {
        return 0;
    } else if ($taxableIncome <= 33333) {
        return ($taxableIncome - 20833) * 0.15;
    } else if ($taxableIncome <= 66667) {
        return 1875 + ($taxableIncome - 33333) * 0.20;
    } else if ($taxableIncome <= 166667) {
        return 8541.80 + ($taxableIncome - 66667) * 0.25;
    } else if ($taxableIncome <= 666667) {
        return 33541.80 + ($taxableIncome - 166667) * 0.30;
    } else {
        return 183541.80 + ($taxableIncome - 666667) * 0.35;
    }
}
?>
