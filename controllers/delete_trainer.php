<?php
session_start();
include '../config/show_errors.php';

// Check if user is not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
$conn = require_once '../config/config.php';

// Check if trainer_id is provided
if (!isset($_POST['trainer_id']) || empty($_POST['trainer_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Trainer ID is required']);
    exit();
}

$trainer_id = mysqli_real_escape_string($conn, $_POST['trainer_id']);

// Function to log SQL errors
function logSqlError($conn, $query) {
    return "Query failed: " . $query . " - Error: " . mysqli_error($conn);
}

// Start a transaction
mysqli_begin_transaction($conn);

try {
    // Delete trainer from local database
    // First delete from related tables to maintain referential integrity
    
    // Delete trainer reviews
    $query = "DELETE FROM trainer_reviews WHERE trainer_id = '$trainer_id'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception(logSqlError($conn, $query));
    }
    
    // Delete trainer bookings (need to delete from time_slots first)
    $time_slots_query = "SELECT ts.id FROM time_slots ts 
                         JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
                         WHERE ta.trainer_id = '$trainer_id'";
    $time_slots_result = mysqli_query($conn, $time_slots_query);
    
    if (!$time_slots_result) {
        throw new Exception(logSqlError($conn, $time_slots_query));
    }
    
    while ($time_slot = mysqli_fetch_assoc($time_slots_result)) {
        $query = "DELETE FROM bookings WHERE time_slot_id = '{$time_slot['id']}'";
        if (!mysqli_query($conn, $query)) {
            throw new Exception(logSqlError($conn, $query));
        }
    }
    
    // Delete time_slots
    $query = "DELETE ts FROM time_slots ts 
              JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
              WHERE ta.trainer_id = '$trainer_id'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception(logSqlError($conn, $query));
    }
    
    // Delete trainer availabilities
    $query = "DELETE FROM trainer_availabilities WHERE trainer_id = '$trainer_id'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception(logSqlError($conn, $query));
    }
    
    // Delete trainer specializations
    $query = "DELETE FROM trainer_specializations WHERE trainer_id = '$trainer_id'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception(logSqlError($conn, $query));
    }
    
    // Finally delete the trainer
    $query = "DELETE FROM trainers WHERE id = '$trainer_id'";
    if (!mysqli_query($conn, $query)) {
        throw new Exception(logSqlError($conn, $query));
    }
    
    // Commit the transaction
    mysqli_commit($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Trainer deleted successfully']);
    
} catch (Exception $e) {
    // Rollback the transaction
    mysqli_rollback($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
