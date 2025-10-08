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

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;

if (!$workshop_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid workshop ID']);
    exit();
}

try {
    // Check if workshops_emails table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'workshops_emails'");
    $total_count = 0;
    $sent_count = 0;
    
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        // Get total entries count in workshops_emails table for this workshop
        $total_sql = "SELECT COUNT(*) as total_count 
                      FROM workshops_emails 
                      WHERE workshop_id = $workshop_id";
        
        $total_result = mysqli_query($conn, $total_sql);
        
        if ($total_result) {
            $total_data = mysqli_fetch_assoc($total_result);
            $total_count = $total_data['total_count'];
        }
        
        // Get sent emails count (is_sent = 1 means actually sent)
        $sent_sql = "SELECT COUNT(*) as sent_count 
                     FROM workshops_emails 
                     WHERE workshop_id = $workshop_id AND is_sent = 1";
        
        $sent_result = mysqli_query($conn, $sent_sql);
        
        if ($sent_result) {
            $sent_data = mysqli_fetch_assoc($sent_result);
            $sent_count = $sent_data['sent_count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_count' => $total_count,
        'sent_count' => $sent_count
    ]);
    
} catch (Exception $e) {
    error_log("Get joining links status error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
