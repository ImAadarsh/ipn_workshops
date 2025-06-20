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
    'discarded' => 0,
    'updated' => 0,
    'new' => 0,
    'errors' => 0
];

// Function to validate email
function validateEmail($email) {
    // Remove whitespace
    $email = trim($email);
    
    // Check if email is empty
    if (empty($email)) {
        return false;
    }
    
    // Check for valid email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Check for common email issues
    if (strpos($email, ' ') !== false) {
        return false;
    }
    
    // Check for multiple @ symbols
    if (substr_count($email, '@') !== 1) {
        return false;
    }
    
    // Check for valid domain
    $domain = substr(strrchr($email, "@"), 1);
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return false;
    }
    
    return true;
}

// Function to save discarded entry
function saveDiscardedEntry($conn, $name, $email, $mobile, $payment_id, $workshop_id, $reason, $csv_data) {
    $name = mysqli_real_escape_string($conn, $name);
    $email = mysqli_real_escape_string($conn, $email);
    $mobile = mysqli_real_escape_string($conn, $mobile);
    $payment_id = mysqli_real_escape_string($conn, $payment_id);
    $reason = mysqli_real_escape_string($conn, $reason);
    $csv_data = mysqli_real_escape_string($conn, $csv_data);
    
    // Generate a random verification token
    $verification_token = bin2hex(random_bytes(32));
    
    $sql = "INSERT INTO discarded_entries (name, email, mobile, payment_id, workshop_id, reason, csv_data, verification_token) 
            VALUES ('$name', '$email', '$mobile', '$payment_id', $workshop_id, '$reason', '$csv_data', '$verification_token')";
    
    return mysqli_query($conn, $sql);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workshop_id = (int)$_POST['workshop_id'];
    
    // Get workshop details
    $workshop_sql = "SELECT price, cpd, name FROM workshops WHERE id = $workshop_id";
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
        $_SESSION['processing_message'] = "Processing CSV file for workshop: {$workshop['name']}...";
        
        while (($csv = fgetcsv($file, 1000, ",")) !== false) {
            if ($pass == 1) {
                $pass = 0;
                continue;
            }
            
            // Extract data from CSV
            $name = mysqli_real_escape_string($conn, $csv[7]); // Buyer Name
            $email = mysqli_real_escape_string($conn, $csv[8]); // Buyer Email
            $contact = mysqli_real_escape_string($conn, $csv[9]); // Buyer Phone
            $payment_id = mysqli_real_escape_string($conn, $csv[0]); // Payment ID
            
            // Validate email
            if (!validateEmail($email)) {
                saveDiscardedEntry(
                    $conn,
                    $name,
                    $email,
                    $contact,
                    $payment_id,
                    $workshop_id,
                    'Invalid email format',
                    json_encode($csv)
                );
                $stats['discarded']++;
                continue;
            }
            
            // Check if user exists
            $user_sql = "SELECT id FROM users WHERE mobile = '$contact' OR email = '$email'";
            $user_result = mysqli_query($conn, $user_sql);
            
            if (mysqli_num_rows($user_result) > 0) {
                // User exists
                $user = mysqli_fetch_assoc($user_result);
                $user_id = $user['id'];
                
                // Check if payment already exists
                $payment_check_sql = "SELECT id, payment_status FROM payments WHERE user_id = $user_id AND workshop_id = $workshop_id";
                $payment_check_result = mysqli_query($conn, $payment_check_sql);
                
                if (mysqli_num_rows($payment_check_result) > 0) {
                    // Payment exists, update if status is 0
                    $payment = mysqli_fetch_assoc($payment_check_result);
                    if ($payment['payment_status'] == 0) {
                        $update_sql = "UPDATE payments SET payment_status = 1, instamojo_upload = 1, updated_at = NOW() WHERE id = {$payment['id']}";
                        if (mysqli_query($conn, $update_sql)) {
                            $stats['updated']++;
                            $stats['processed']++;
                        } else {
                            $stats['errors']++;
                        }
                    }
                } else {
                    // Generate verification token
                    $verify_token = bin2hex(random_bytes(32));
                    
                    // Create new payment
                    $payment_sql = "INSERT INTO payments (user_id, workshop_id, payment_id, amount, order_id, payment_status, cpd, verify_token, created_at, updated_at, instamojo_upload) 
                                  VALUES ($user_id, $workshop_id, '$payment_id', '{$workshop['price']}', '" . rand(11111111111111111, 99999999999999999) . "', 1, {$workshop['cpd']}, '$verify_token', NOW(), NOW(), 1)";
                    if (mysqli_query($conn, $payment_sql)) {
                        $stats['new']++;
                        $stats['processed']++;
                    } else {
                        $stats['errors']++;
                    }
                }
            } else {
                // Create new user
                $user_sql = "INSERT INTO users (name, email, mobile, user_type, created_at, updated_at) 
                           VALUES ('$name', '$email', '$contact', 'user', NOW(), NOW())";
                if (mysqli_query($conn, $user_sql)) {
                    $user_id = mysqli_insert_id($conn);
                    
                    // Generate verification token
                    $verify_token = bin2hex(random_bytes(32));
                    
                    // Create payment for new user
                    $payment_sql = "INSERT INTO payments (user_id, workshop_id, payment_id, amount, order_id, payment_status, cpd, verify_token, created_at, updated_at, instamojo_upload) 
                                  VALUES ($user_id, $workshop_id, '$payment_id', '{$workshop['price']}', '" . rand(11111111111111111, 99999999999999999) . "', 1, {$workshop['cpd']}, '$verify_token', NOW(), NOW(), 1)";
                    if (mysqli_query($conn, $payment_sql)) {
                        $stats['new']++;
                        $stats['processed']++;
                    } else {
                        $stats['errors']++;
                    }
                } else {
                    $stats['errors']++;
                }
            }
        }
        fclose($file);
        
        // Set success message with detailed statistics
        $_SESSION['processing_message'] = "✅ CSV Processing Complete!\n\n" .
            "📊 Processing Statistics:\n" .
            "• Total Records Processed: {$stats['processed']}\n" .
            "• Total Payment Recorded: " . ($stats['new'] + $stats['updated']) . "\n" .
            "• Discarded Entries: {$stats['discarded']}\n" .
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