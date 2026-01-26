<?php
/**
 * API - Get Employee Details
 */

header('Content-Type: application/json');

require_once '../includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit;
}

$result = $conn->query("
    SELECT e.*, d.department_name, p.position_title, p.basic_salary, p.salary_grade
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.id = $id
");

if ($result && $result->num_rows > 0) {
    $employee = $result->fetch_assoc();
    echo json_encode(['success' => true, 'employee' => $employee]);
} else {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
}
?>
