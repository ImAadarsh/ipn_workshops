<?php
require_once 'config/config.php';

echo "=== CLEANING UP DUPLICATE EMAIL ERRORS ===\n";

// Check for duplicate entries in email_errors table
$duplicate_sql = "SELECT workshop_id, user_id, COUNT(*) as count 
                  FROM email_errors 
                  GROUP BY workshop_id, user_id 
                  HAVING count > 1 
                  ORDER BY count DESC";

$result = mysqli_query($conn, $duplicate_sql);

if ($result) {
    $duplicates = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    if (empty($duplicates)) {
        echo "✅ No duplicate entries found!\n";
    } else {
        echo "❌ Found duplicate entries:\n";
        foreach ($duplicates as $dup) {
            echo "Workshop ID: {$dup['workshop_id']}, User ID: {$dup['user_id']} - Count: {$dup['count']}\n";
        }
        
        echo "\n=== CLEANING UP DUPLICATES ===\n";
        
        // For each duplicate combination, keep only the latest entry (highest ID) and delete the rest
        foreach ($duplicates as $dup) {
            $workshop_id = $dup['workshop_id'];
            $user_id = $dup['user_id'];
            
            // Get all entries for this workshop_id and user_id combination
            $entries_sql = "SELECT id, created_at, is_resolved 
                           FROM email_errors 
                           WHERE workshop_id = $workshop_id AND user_id = $user_id 
                           ORDER BY id ASC";
            
            $entries_result = mysqli_query($conn, $entries_sql);
            $entries = mysqli_fetch_all($entries_result, MYSQLI_ASSOC);
            
            if (count($entries) > 1) {
                // Keep the latest entry (highest ID)
                $keep_id = $entries[count($entries) - 1]['id'];
                $delete_ids = array_slice(array_column($entries, 'id'), 0, -1);
                
                echo "Workshop $workshop_id, User $user_id - Keeping ID: $keep_id, Deleting IDs: " . implode(', ', $delete_ids) . "\n";
                
                // Delete the duplicate entries
                $delete_sql = "DELETE FROM email_errors 
                              WHERE id IN (" . implode(',', $delete_ids) . ")";
                
                if (mysqli_query($conn, $delete_sql)) {
                    echo "✅ Deleted " . count($delete_ids) . " duplicate entries\n";
                } else {
                    echo "❌ Failed to delete duplicates: " . mysqli_error($conn) . "\n";
                }
            }
        }
    }
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}

// Check final count
$total_sql = "SELECT COUNT(*) as total FROM email_errors";
$total_result = mysqli_query($conn, $total_sql);
$total = mysqli_fetch_assoc($total_result);

echo "\nFinal total entries in email_errors: {$total['total']}\n";

mysqli_close($conn);
?>
