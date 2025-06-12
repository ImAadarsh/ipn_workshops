<?php
session_start();
require_once 'config/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search query
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

if (empty($search)) {
    header('Content-Type: application/json');
    echo json_encode(['results' => []]);
    exit;
}

// Search in multiple tables
$results = [];

// Search users
$query = "SELECT 
    'user' as type,
    id,
    CONCAT(first_name, ' ', last_name) as name,
    email,
    mobile,
    'users.php' as link
    FROM users 
    WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR mobile LIKE ?)
    AND user_type = 'user'
    LIMIT 5";

$stmt = mysqli_prepare($conn, $query);
$search_param = "%$search%";
mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $results[] = $row;
}

// Search trainers
$query = "SELECT 
    'trainer' as type,
    id,
    CONCAT(first_name, ' ', last_name) as name,
    email,
    mobile,
    'trainers.php' as link
    FROM trainers 
    WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR mobile LIKE ?
    LIMIT 5";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $results[] = $row;
}

// Search bookings
$query = "SELECT 
    'booking' as type,
    b.id,
    CONCAT(u.first_name, ' ', u.last_name) as name,
    b.status,
    DATE_FORMAT(ta.date, '%d %b %Y') as booking_date,
    'bookings.php' as link
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN time_slots ts ON b.time_slot_id = ts.id
    JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
    WHERE u.first_name LIKE ? OR u.last_name LIKE ?
    LIMIT 5";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $results[] = $row;
}

// Return results as JSON
header('Content-Type: application/json');
echo json_encode(['results' => $results]);
?> 