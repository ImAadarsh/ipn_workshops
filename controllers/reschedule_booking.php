<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../config/config.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$date = isset($_POST['date']) ? $_POST['date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';

// Validate input
if ($booking_id <= 0 || !$date || !$start_time || !$end_time || !$reason) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Get user type and ID
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Get booking details to check permissions
$check_sql = "SELECT b.*, ta.trainer_id 
              FROM bookings b
              JOIN time_slots ts ON b.time_slot_id = ts.id
              JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
              WHERE b.id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $booking_id);
mysqli_stmt_execute($check_stmt);
$booking = mysqli_stmt_get_result($check_stmt)->fetch_assoc();

// Check if booking exists
if (!$booking) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit();
}

// Determine who is requesting the reschedule
$requested_by = ($user_id === $booking['trainer_id']) ? 'trainer' : 'user';

// Check if there's already a pending reschedule request
$check_request_sql = "SELECT id FROM reschedule_requests 
                     WHERE booking_id = ? AND status = 'pending'";
$check_request_stmt = mysqli_prepare($conn, $check_request_sql);
mysqli_stmt_bind_param($check_request_stmt, "i", $booking_id);
mysqli_stmt_execute($check_request_stmt);
if (mysqli_stmt_get_result($check_request_stmt)->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'A reschedule request is already pending for this booking']);
    exit();
}

// Insert reschedule request
$sql = "INSERT INTO reschedule_requests (booking_id, user_id, trainer_id, original_time_slot_id, 
        requested_date, requested_start_time, requested_end_time, reason, requested_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiiisssss", 
    $booking_id, 
    $booking['user_id'],
    $booking['trainer_id'],
    $booking['time_slot_id'],
    $date,
    $start_time,
    $end_time,
    $reason,
    $requested_by
);

if (mysqli_stmt_execute($stmt)) {
    // Update booking status to pending_reschedule
    $update_sql = "UPDATE bookings SET status = 'pending_reschedule' WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $booking_id);
    mysqli_stmt_execute($update_stmt);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Reschedule request submitted successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to submit reschedule request']);
}

mysqli_close($conn); 