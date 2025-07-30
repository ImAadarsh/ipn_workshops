<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get form data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$workshop_id = isset($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;
$is_attended = isset($_POST['is_attended']) ? intval($_POST['is_attended']) : 0;
$attended_duration = isset($_POST['attended_duration']) ? intval($_POST['attended_duration']) : 0;

// Validate input
if (!$user_id || !$workshop_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user or workshop ID']);
    exit();
}

// Validate duration (0-120 minutes)
if ($attended_duration < 0 || $attended_duration > 120) {
    echo json_encode(['success' => false, 'message' => 'Duration must be between 0 and 120 minutes']);
    exit();
}

// Validate attendance status
if (!in_array($is_attended, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance status']);
    exit();
}

try {
    // Check if the payment record exists
    $check_sql = "SELECT id FROM payments WHERE user_id = ? AND workshop_id = ? AND payment_status = 1";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $workshop_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Payment record not found for this user and workshop']);
        exit();
    }
    
    // Update attendance and duration
    $update_sql = "UPDATE payments SET 
                   is_attended = ?, 
                   attended_duration = ?,
                   updated_at = NOW()
                   WHERE user_id = ? AND workshop_id = ? AND payment_status = 1";
    
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "iiii", $is_attended, $attended_duration, $user_id, $workshop_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        // Check if any rows were affected
        if (mysqli_stmt_affected_rows($update_stmt) > 0) {
            echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made to attendance']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($update_stmt);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

mysqli_close($conn);
?> 