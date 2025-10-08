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
$track_ids = isset($_POST['track_ids']) ? $_POST['track_ids'] : [];
$workshop_id = isset($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;

if (empty($track_ids) || $workshop_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid track IDs or workshop ID']);
    exit();
}

// Validate track_ids array
$valid_track_ids = [];
foreach ($track_ids as $id) {
    $id = intval($id);
    if ($id > 0) {
        $valid_track_ids[] = $id;
    }
}

if (empty($valid_track_ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid track IDs provided']);
    exit();
}

try {
    // Get user details before deletion for logging
    $placeholders = str_repeat('?,', count($valid_track_ids) - 1) . '?';
    $get_records_sql = "SELECT we.id, we.user_id, u.name as user_name, u.email as user_email 
                        FROM workshops_emails we 
                        LEFT JOIN users u ON we.user_id = u.id 
                        WHERE we.id IN ($placeholders) AND we.workshop_id = ?";
    
    $get_stmt = mysqli_prepare($conn, $get_records_sql);
    $params = array_merge($valid_track_ids, [$workshop_id]);
    $types = str_repeat('i', count($valid_track_ids)) . 'i';
    mysqli_stmt_bind_param($get_stmt, $types, ...$params);
    mysqli_stmt_execute($get_stmt);
    $records_result = mysqli_stmt_get_result($get_stmt);
    $records = mysqli_fetch_all($records_result, MYSQLI_ASSOC);
    mysqli_stmt_close($get_stmt);
    
    if (empty($records)) {
        echo json_encode(['success' => false, 'message' => 'No valid email records found']);
        exit();
    }
    
    // Delete the email records from workshops_emails table
    $delete_sql = "DELETE FROM workshops_emails WHERE id IN ($placeholders) AND workshop_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    $params = array_merge($valid_track_ids, [$workshop_id]);
    $types = str_repeat('i', count($valid_track_ids)) . 'i';
    mysqli_stmt_bind_param($delete_stmt, $types, ...$params);
    $delete_result = mysqli_stmt_execute($delete_stmt);
    $affected_rows = mysqli_stmt_affected_rows($delete_stmt);
    mysqli_stmt_close($delete_stmt);
    
    if (!$delete_result) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete email records: ' . mysqli_error($conn)]);
        exit();
    }
    
    // Log the bulk action
    $user_names = array_column($records, 'user_name');
    $user_emails = array_column($records, 'user_email');
    error_log("Bulk user removal from workshop emails - Workshop ID: $workshop_id, Removed Count: $affected_rows, Users: " . implode(', ', $user_names) . ", Emails: " . implode(', ', $user_emails) . ", Removed by: {$_SESSION['user_id']}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Users removed successfully from workshop email list',
        'removed_count' => $affected_rows,
        'user_names' => $user_names,
        'user_emails' => $user_emails
    ]);

} catch (Exception $e) {
    error_log("Error in bulk removing users from workshop: " . $e->getMessage());
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
