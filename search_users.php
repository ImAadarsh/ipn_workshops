<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Get search parameters from both POST and GET
$name = isset($_REQUEST['name']) ? mysqli_real_escape_string($conn, $_REQUEST['name']) : '';
$email = isset($_REQUEST['email']) ? mysqli_real_escape_string($conn, $_REQUEST['email']) : '';
$mobile = isset($_REQUEST['mobile']) ? mysqli_real_escape_string($conn, $_REQUEST['mobile']) : '';

// Build the query
$sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation, u.institute_name, u.city 
        FROM users u 
        WHERE 1=1";

if (!empty($name)) {
    $sql .= " AND u.name LIKE '%$name%'";
}
if (!empty($email)) {
    $sql .= " AND u.email LIKE '%$email%'";
}
if (!empty($mobile)) {
    $sql .= " AND u.mobile LIKE '%$mobile%'";
}

$sql .= " ORDER BY u.name ASC LIMIT 50";

try {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($conn));
    }

    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Ensure all fields are properly escaped for JSON
        $users[] = array_map('htmlspecialchars', $row);
    }

    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Return JSON response
    echo json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}