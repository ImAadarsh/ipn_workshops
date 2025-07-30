<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Get parameters
$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (!$workshop_id || !$type) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$users = [];

switch ($type) {
    case 'b2b':
        // B2B users (excluding B2C2B users)
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.mobile, u.institute_name, p.payment_id
                FROM payments p
                INNER JOIN users u ON p.user_id = u.id
                LEFT JOIN schools s ON p.school_id = s.id
                WHERE p.workshop_id = ? 
                AND p.payment_status = 1 
                AND p.school_id IS NOT NULL
                AND (s.b2c2b = 0 OR s.b2c2b IS NULL)
                ORDER BY u.name ASC";
        break;
        
    case 'b2c':
        // All B2C users
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.mobile, u.institute_name, p.payment_id
                FROM payments p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.workshop_id = ? 
                AND p.payment_status = 1 
                AND p.school_id IS NULL
                ORDER BY u.name ASC";
        break;
        
    case 'b2c2b':
        // B2C2B users (B2B users whose school has b2c2b = 1)
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.mobile, u.institute_name, p.payment_id
                FROM payments p
                INNER JOIN users u ON p.user_id = u.id
                INNER JOIN schools s ON p.school_id = s.id
                WHERE p.workshop_id = ? 
                AND p.payment_status = 1 
                AND p.school_id IS NOT NULL
                AND s.b2c2b = 1
                ORDER BY u.name ASC";
        break;
        
    case 'platform':
        // Platform enrolled users (B2C users with valid payments)
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.mobile, u.institute_name, p.payment_id
                FROM payments p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.workshop_id = ? 
                AND p.payment_status = 1 
                AND p.school_id IS NULL 
                AND p.payment_id NOT LIKE '%Membership Redeem%' 
                AND p.payment_id NOT LIKE '%499%' 
                AND p.payment_id NOT LIKE '%Google-Form-Paid%'
                AND p.payment_id NOT LIKE '%G-FORM%' 
                AND p.payment_id NOT LIKE '%G-FORM-PAID%' 
                AND p.payment_id NOT LIKE '%G-Form-Paid%' 
                AND p.payment_id NOT LIKE '%B2B-ENRL%'
                AND p.payment_id IS NOT NULL 
                AND p.payment_id != ''
                ORDER BY u.name ASC";
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type']);
        exit();
}

// Execute query
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $workshop_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'email' => htmlspecialchars($row['email']),
            'mobile' => htmlspecialchars($row['mobile']),
            'institute_name' => htmlspecialchars($row['institute_name']),
            'payment_id' => htmlspecialchars($row['payment_id'])
        ];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($users);
?> 