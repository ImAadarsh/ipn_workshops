<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Initialize counters
$stats = [
    'processed' => 0,
    'updated' => 0,
    'errors' => 0,
    'not_found' => 0,
    'multiple_entries' => 0
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workshop_id = (int)$_POST['workshop_id'];
    
    // Get workshop details
    $workshop_sql = "SELECT name FROM workshops WHERE id = $workshop_id";
    $workshop_result = mysqli_query($conn, $workshop_sql);
    if (!$workshop_result || mysqli_num_rows($workshop_result) === 0) {
        $_SESSION['error_message'] = "Workshop not found!";
        header("Location: workshop-details.php?id=" . $workshop_id);
        exit();
    }
    $workshop = mysqli_fetch_assoc($workshop_result);
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $pass = 1; // Skip header row
        
        // Start processing message
        $_SESSION['processing_message'] = "Processing Zoom attendance CSV for workshop: {$workshop['name']}...";
        
        // Array to store user durations
        $user_durations = [];
        
        while (($csv = fgetcsv($file, 1000, ",")) !== false) {
            if ($pass == 1) {
                $pass = 0;
                continue;
            }
            
            // Extract data from CSV
            $name = mysqli_real_escape_string($conn, $csv[0]); // Name (original name)
            $duration = (int)$csv[2]; // Total duration (minutes)
            
            // Extract user_id from name (format: {user_id}_{Full Name})
            if (preg_match('/^(\d+)_/', $name, $matches)) {
                $user_id = (int)$matches[1];
                
                // Add duration to user's total
                if (!isset($user_durations[$user_id])) {
                    $user_durations[$user_id] = 0;
                }
                $user_durations[$user_id] += $duration;
                $stats['processed']++;
            } else {
                $stats['errors']++;
            }
        }
        fclose($file);
        
        // Process the accumulated durations
        foreach ($user_durations as $user_id => $total_duration) {
            // Check if payment exists for this user and workshop
            $payment_sql = "SELECT id FROM payments WHERE user_id = $user_id AND workshop_id = $workshop_id";
            $payment_result = mysqli_query($conn, $payment_sql);
            
            if (mysqli_num_rows($payment_result) > 0) {
                $payment = mysqli_fetch_assoc($payment_result);
                $payment_id = $payment['id'];
                
                // Update attendance with total duration
                $update_sql = "UPDATE payments SET 
                             is_attended = 1,
                             attended_duration = $total_duration,
                             updated_at = NOW()
                             WHERE id = $payment_id";
                
                if (mysqli_query($conn, $update_sql)) {
                    $stats['updated']++;
                } else {
                    $stats['errors']++;
                }
            } else {
                $stats['not_found']++;
            }
        }
        
        // Set success message with detailed statistics
        $_SESSION['processing_message'] = "✅ Zoom Attendance Processing Complete!\n\n" .
            "📊 Processing Statistics:\n" .
            "• Total Records Processed: {$stats['processed']}\n" .
            "• Unique Users Updated: {$stats['updated']}\n" .
            "• Users Not Found: {$stats['not_found']}\n" .
            "• Errors Encountered: {$stats['errors']}\n\n" .
            "📝 Details:\n" .
            "• Workshop: {$workshop['name']}\n" .
            "• Processing Time: " . date('Y-m-d H:i:s') . "\n\n" .
            "Please check the workshop details page for updated statistics.";

        // Redirect back to workshop details
        header("Location: workshop-details.php?id=" . $workshop_id);
        exit();
    } else {
        $_SESSION['error_message'] = "Error uploading file. Please try again.";
        header("Location: workshop-details.php?id=" . $workshop_id);
        exit();
    }
}

// Redirect back to workshop details
header("Location: workshop-details.php?id=" . $workshop_id);
exit(); 