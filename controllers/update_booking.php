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
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate booking ID
if ($booking_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
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

// Check permissions
if ($user_type !== 'admin' && $booking['trainer_id'] !== $user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Handle different actions
switch ($action) {
    case 'update_meeting_link':
        $meeting_link = isset($_POST['meeting_link']) ? $_POST['meeting_link'] : '';
        
        // Validate meeting link
        if (!filter_var($meeting_link, FILTER_VALIDATE_URL) && !empty($meeting_link)) {
            echo json_encode(['success' => false, 'message' => 'Invalid meeting link format']);
            exit();
        }

        $sql = "UPDATE bookings SET meeting_link = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $meeting_link, $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Meeting link updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update meeting link']);
        }
        break;

    case 'update_notes':
        $booking_notes = isset($_POST['booking_notes']) ? $_POST['booking_notes'] : '';
        
        $sql = "UPDATE bookings SET booking_notes = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $booking_notes, $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Notes updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update notes']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

mysqli_close($conn); 