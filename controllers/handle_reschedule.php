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
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate input
if ($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Get user type and ID
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Get reschedule request details
$sql = "SELECT r.*, b.status as booking_status, ta.trainer_id
        FROM reschedule_requests r
        JOIN bookings b ON r.booking_id = b.id
        JOIN time_slots ts ON b.time_slot_id = ts.id
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        WHERE r.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$request = mysqli_stmt_get_result($stmt)->fetch_assoc();

// Check if request exists and is pending
if (!$request || $request['status'] !== 'pending') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid reschedule request']);
    exit();
}

// Check permissions
if ($user_type !== 'admin' && 
    (($request['requested_by'] === 'user' && $user_id !== $request['trainer_id']) ||
     ($request['requested_by'] === 'trainer' && $user_id !== $request['user_id']))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    if ($action === 'approve') {
        // Create new trainer availability if it doesn't exist
        $check_availability_sql = "SELECT id FROM trainer_availabilities 
                                 WHERE trainer_id = ? AND date = ?";
        $check_stmt = mysqli_prepare($conn, $check_availability_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $request['trainer_id'], $request['requested_date']);
        mysqli_stmt_execute($check_stmt);
        $availability_result = mysqli_stmt_get_result($check_stmt);
        
        if ($availability_result->num_rows === 0) {
            $insert_availability_sql = "INSERT INTO trainer_availabilities (trainer_id, date) 
                                      VALUES (?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_availability_sql);
            mysqli_stmt_bind_param($insert_stmt, "is", $request['trainer_id'], $request['requested_date']);
            mysqli_stmt_execute($insert_stmt);
            $availability_id = mysqli_insert_id($conn);
        } else {
            $availability = mysqli_fetch_assoc($availability_result);
            $availability_id = $availability['id'];
        }

        // Create new time slot
        $insert_slot_sql = "INSERT INTO time_slots (trainer_availability_id, start_time, end_time, duration_minutes, price, status) 
                           SELECT ?, ?, ?, duration_minutes, price, 'booked'
                           FROM time_slots 
                           WHERE id = ?";
        $insert_slot_stmt = mysqli_prepare($conn, $insert_slot_sql);
        mysqli_stmt_bind_param($insert_slot_stmt, "issi", 
            $availability_id,
            $request['requested_start_time'],
            $request['requested_end_time'],
            $request['original_time_slot_id']
        );
        mysqli_stmt_execute($insert_slot_stmt);
        $new_slot_id = mysqli_insert_id($conn);

        // Update booking with new time slot
        $update_booking_sql = "UPDATE bookings SET time_slot_id = ?, status = 'confirmed' 
                             WHERE id = ?";
        $update_booking_stmt = mysqli_prepare($conn, $update_booking_sql);
        mysqli_stmt_bind_param($update_booking_stmt, "ii", $new_slot_id, $request['booking_id']);
        mysqli_stmt_execute($update_booking_stmt);

        // Free up the old time slot
        $update_old_slot_sql = "UPDATE time_slots SET status = 'available' 
                               WHERE id = ?";
        $update_old_slot_stmt = mysqli_prepare($conn, $update_old_slot_sql);
        mysqli_stmt_bind_param($update_old_slot_stmt, "i", $request['original_time_slot_id']);
        mysqli_stmt_execute($update_old_slot_stmt);

        // Update reschedule request status
        $update_request_sql = "UPDATE reschedule_requests SET status = 'approved' 
                             WHERE id = ?";
        $update_request_stmt = mysqli_prepare($conn, $update_request_sql);
        mysqli_stmt_bind_param($update_request_stmt, "i", $request_id);
        mysqli_stmt_execute($update_request_stmt);

        // Record the approval
        $insert_approval_sql = "INSERT INTO reschedule_approvals 
                              (reschedule_request_id, approved_by_id, approved_by_type, new_time_slot_id) 
                              VALUES (?, ?, ?, ?)";
        $insert_approval_stmt = mysqli_prepare($conn, $insert_approval_sql);
        mysqli_stmt_bind_param($insert_approval_stmt, "iisi", 
            $request_id, 
            $user_id, 
            $user_type,
            $new_slot_id
        );
        mysqli_stmt_execute($insert_approval_stmt);

    } else {
        // Reject the request
        $update_sql = "UPDATE reschedule_requests SET status = 'rejected' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);

        // Update booking status back to original
        $update_booking_sql = "UPDATE bookings SET status = 'confirmed' WHERE id = ?";
        $update_booking_stmt = mysqli_prepare($conn, $update_booking_sql);
        mysqli_stmt_bind_param($update_booking_stmt, "i", $request['booking_id']);
        mysqli_stmt_execute($update_booking_stmt);
    }

    mysqli_commit($conn);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Reschedule request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully'
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to process reschedule request: ' . $e->getMessage()]);
}

mysqli_close($conn); 