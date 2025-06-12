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

// Validate booking ID
if ($booking_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// Get user type
$user_type = $_SESSION['user_type'];

// Only admin can delete bookings
if ($user_type !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Delete related records first
    // Delete payments
    $sql = "DELETE FROM payments WHERE booking_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);

    // Delete reschedule requests
    $sql = "DELETE FROM reschedule_requests WHERE booking_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);

    // Delete trainer reviews
    $sql = "DELETE FROM trainer_reviews WHERE booking_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);

    // Finally, delete the booking
    $sql = "DELETE FROM bookings WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);

    // Commit transaction
    mysqli_commit($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to delete booking: ' . $e->getMessage()]);
}

mysqli_close($conn); 