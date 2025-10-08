<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$workshop_id = isset($input['workshop_id']) ? intval($input['workshop_id']) : 0;

if (!$workshop_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid workshop ID']);
    exit();
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Check if workshops_emails table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'workshops_emails'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        throw new Exception('workshops_emails table does not exist');
    }
    
    // Get count of records to be deleted
    $count_sql = "SELECT COUNT(*) as count FROM workshops_emails WHERE workshop_id = $workshop_id";
    $count_result = mysqli_query($conn, $count_sql);
    
    if (!$count_result) {
        throw new Exception('Failed to count records: ' . mysqli_error($conn));
    }
    
    $count_data = mysqli_fetch_assoc($count_result);
    $deleted_count = $count_data['count'];
    
    // Delete all records for this workshop
    $delete_sql = "DELETE FROM workshops_emails WHERE workshop_id = $workshop_id";
    $delete_result = mysqli_query($conn, $delete_sql);
    
    if (!$delete_result) {
        throw new Exception('Failed to delete records: ' . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Joining links reverted successfully',
        'deleted_count' => $deleted_count
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    error_log("Revert joining links error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
