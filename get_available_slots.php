<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if trainer_id is provided
if (!isset($_POST['trainer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Trainer ID is required']);
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

$trainer_id = mysqli_real_escape_string($conn, $_POST['trainer_id']);

// Get available time slots for the trainer
$sql = "SELECT ts.*, ta.date,
        DATE_FORMAT(ts.start_time, '%h:%i %p') as formatted_start_time,
        DATE_FORMAT(ts.end_time, '%h:%i %p') as formatted_end_time
        FROM time_slots ts
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        WHERE ta.trainer_id = ? 
        AND ts.status = 'available'
        AND ta.date >= CURDATE()
        ORDER BY ta.date ASC, ts.start_time ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $trainer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$slots = [];
while ($row = mysqli_fetch_assoc($result)) {
    $slots[] = [
        'id' => $row['id'],
        'date' => date('F d, Y', strtotime($row['date'])),
        'start_time' => $row['formatted_start_time'],
        'end_time' => $row['formatted_end_time'],
        'duration_minutes' => $row['duration_minutes'],
        'price' => number_format($row['price'], 2)
    ];
}

echo json_encode($slots);
?> 