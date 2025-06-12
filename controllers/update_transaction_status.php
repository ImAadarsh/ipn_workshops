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
$transaction_id = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate input
if ($transaction_id <= 0 || !in_array($status, ['completed', 'failed', 'refunded'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Update transaction status
$sql = "UPDATE payments SET status = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $status, $transaction_id);

if (mysqli_stmt_execute($stmt)) {
    // If status is refunded, also update booking status if exists
    if ($status === 'refunded') {
        $booking_sql = "UPDATE bookings SET status = 'cancelled' WHERE id IN (SELECT booking_id FROM payments WHERE id = ?)";
        $booking_stmt = mysqli_prepare($conn, $booking_sql);
        mysqli_stmt_bind_param($booking_stmt, "i", $transaction_id);
        mysqli_stmt_execute($booking_stmt);
        mysqli_stmt_close($booking_stmt);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Transaction status updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update transaction status']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn); 