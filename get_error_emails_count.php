<?php
header('Content-Type: application/json');
require_once 'config/config.php';

// Get workshop ID from query parameter
$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;

if ($workshop_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid workshop ID']);
    exit();
}

try {
    // Count error emails for this workshop
    $count_sql = "SELECT COUNT(*) as error_count 
                  FROM email_errors 
                  WHERE workshop_id = ? AND is_resolved = 0";
    
    $stmt = mysqli_prepare($conn, $count_sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $workshop_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $count = $row['error_count'];
        
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
    } else {
        throw new Exception('Failed to execute query: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
