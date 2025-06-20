<?php
include 'config/show_errors.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$conn = require_once 'config/config.php';

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$workshop_id = isset($_GET['workshop_id']) ? (int)$_GET['workshop_id'] : 0;

header('Content-Type: application/json');
if (!$school_id || !$workshop_id) {
    echo json_encode([]);
    exit;
}

$users = [];
$sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation,
        (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.workshop_id = $workshop_id AND p.payment_status = 1) as enrolled
        FROM users u WHERE u.school_id = $school_id ORDER BY u.name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'mobile' => $row['mobile'],
        'designation' => $row['designation'],
        'enrolled' => $row['enrolled'] > 0
    ];
}
echo json_encode($users); 