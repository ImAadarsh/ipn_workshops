<?php
session_start();
include 'config/show_errors.php';
$conn = require_once 'config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$track_id = isset($_POST['track_id']) ? intval($_POST['track_id']) : 0;
$workshop_id = isset($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;

if ($track_id <= 0 || $workshop_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid track ID or workshop ID']);
    exit();
}

try {
    // Get the email record details before deletion
    $get_record_sql = "SELECT we.*, u.name as user_name, u.email as user_email 
                       FROM workshops_emails we 
                       LEFT JOIN users u ON we.user_id = u.id 
                       WHERE we.id = ? AND we.workshop_id = ?";
    $get_stmt = mysqli_prepare($conn, $get_record_sql);
    mysqli_stmt_bind_param($get_stmt, "ii", $track_id, $workshop_id);
    mysqli_stmt_execute($get_stmt);
    $record_result = mysqli_stmt_get_result($get_stmt);
    $record = mysqli_fetch_assoc($record_result);
    mysqli_stmt_close($get_stmt);
    
    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Email record not found']);
        exit();
    }
    
    // Delete the email record from workshops_emails table
    $delete_sql = "DELETE FROM workshops_emails WHERE id = ? AND workshop_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "ii", $track_id, $workshop_id);
    $delete_result = mysqli_stmt_execute($delete_stmt);
    mysqli_stmt_close($delete_stmt);
    
    if (!$delete_result) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete email record: ' . mysqli_error($conn)]);
        exit();
    }
    
    // Log the action
    error_log("User removed from workshop emails - Workshop ID: $workshop_id, User ID: {$record['user_id']}, User: {$record['user_name']}, Removed by: {$_SESSION['user_id']}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'User removed successfully from workshop email list',
        'user_name' => $record['user_name'],
        'user_email' => $record['user_email']
    ]);

} catch (Exception $e) {
    error_log("Error removing user from workshop: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($get_stmt)) {
        mysqli_stmt_close($get_stmt);
    }
    if (isset($delete_stmt)) {
        mysqli_stmt_close($delete_stmt);
    }
    mysqli_close($conn);
}
?>
