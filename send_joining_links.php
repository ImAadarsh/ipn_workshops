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
    
    // Get workshop details
    $workshop_sql = "SELECT id, name, trainer_id, trainer_name FROM workshops WHERE id = $workshop_id AND is_deleted = 0";
    $workshop_result = mysqli_query($conn, $workshop_sql);
    
    if (!$workshop_result || mysqli_num_rows($workshop_result) == 0) {
        throw new Exception('Workshop not found');
    }
    
    $workshop = mysqli_fetch_assoc($workshop_result);
    
    // Get total count first
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total_count
                  FROM payments p
                  WHERE p.workshop_id = $workshop_id 
                  AND p.payment_status = 1";
    
    $count_result = mysqli_query($conn, $count_sql);
    $total_count = mysqli_fetch_assoc($count_result)['total_count'];
    
    // Only insert users who don't already exist in workshops_emails table
    $insert_sql = "INSERT INTO workshops_emails 
                   (workshop_id, trainer_id, user_id, payment_id, user_email, sending_email_id, is_sent, created_at, updated_at) 
                   SELECT 
                       $workshop_id as workshop_id,
                       {$workshop['trainer_id']} as trainer_id,
                       p.user_id,
                       p.id as payment_id,
                       u.email as user_email,
                       {$_SESSION['user_id']} as sending_email_id,
                       0 as is_sent,
                       NOW() as created_at,
                       NOW() as updated_at
                   FROM payments p
                   INNER JOIN users u ON p.user_id = u.id
                   WHERE p.workshop_id = $workshop_id 
                   AND p.payment_status = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM workshops_emails we 
                       WHERE we.workshop_id = $workshop_id 
                       AND we.payment_id = p.id 
                       AND we.user_id = p.user_id
                   )";
    
    $insert_result = mysqli_query($conn, $insert_sql);
    
    if (!$insert_result) {
        throw new Exception('Failed to prepare joining links: ' . mysqli_error($conn));
    }
    
    $affected_rows = mysqli_affected_rows($conn);
    
    // Get the new total count after insertion
    $new_total_sql = "SELECT COUNT(*) as new_total_count 
                      FROM workshops_emails 
                      WHERE workshop_id = $workshop_id";
    $new_total_result = mysqli_query($conn, $new_total_sql);
    $new_total_count = mysqli_fetch_assoc($new_total_result)['new_total_count'];
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Joining links prepared successfully',
        'total_count' => $new_total_count,
        'sent_count' => 0, // Always 0 since we're preparing (is_sent = 0)
        'new_entries_added' => $affected_rows
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    error_log("Send joining links error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
