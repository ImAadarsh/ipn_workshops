<?php
include 'config/show_errors.php';

// Log webhook data for debugging
$webhook_data = file_get_contents('php://input');
$log_file = 'instamojo_webhook.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Webhook received: " . $webhook_data . "\n", FILE_APPEND);

$conn = require_once 'config/config.php';

// Verify webhook signature (you should implement this for security)
// $signature = $_SERVER['HTTP_X_INSTAMOJO_SIGNATURE'] ?? '';
// Verify signature here

try {
    // Parse POST data (webhook sends form data, not JSON)
    parse_str($webhook_data, $data);
    
    if (!$data) {
        http_response_code(400);
        exit('Invalid webhook data');
    }
    
    // Extract payment information from POST data
    $payment_id = $data['payment_id'] ?? '';
    $payment_request_id = $data['payment_request_id'] ?? '';
    $status = $data['status'] ?? '';
    $buyer_name = $data['buyer_name'] ?? '';
    $buyer_email = $data['buyer'] ?? ''; // 'buyer' field contains email
    $buyer_phone = $data['buyer_phone'] ?? '';
    $amount = $data['amount'] ?? 0;
    $purpose = $data['purpose'] ?? ''; // This is the title/link_name
    
    if (empty($payment_id) || empty($payment_request_id)) {
        http_response_code(400);
        exit('Missing required payment data');
    }
    
    // Find the link by Instamojo payment request ID
    $link_sql = "SELECT * FROM instamojo_links WHERE instamojo_link_id = ?";
    $link_stmt = mysqli_prepare($conn, $link_sql);
    mysqli_stmt_bind_param($link_stmt, "s", $payment_request_id);
    mysqli_stmt_execute($link_stmt);
    $link_result = mysqli_stmt_get_result($link_stmt);
    $link_data = mysqli_fetch_assoc($link_result);
    mysqli_stmt_close($link_stmt);
    
    if (!$link_data) {
        http_response_code(404);
        exit('Payment link not found');
    }
    
    // Check if payment already exists
    $existing_sql = "SELECT * FROM instamojo_payments WHERE payment_id = ?";
    $existing_stmt = mysqli_prepare($conn, $existing_sql);
    mysqli_stmt_bind_param($existing_stmt, "s", $payment_id);
    mysqli_stmt_execute($existing_stmt);
    $existing_result = mysqli_stmt_get_result($existing_stmt);
    $existing_payment = mysqli_fetch_assoc($existing_result);
    mysqli_stmt_close($existing_stmt);
    
    if ($existing_payment) {
        // Update existing payment status
        $update_sql = "UPDATE instamojo_payments SET status = ?, updated_at = NOW() WHERE payment_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ss", $status, $payment_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // If payment is now completed and wasn't before, process enrollment
        if ($status === 'Credit' && $existing_payment['status'] !== 'Credit') {
            processEnrollment($conn, $existing_payment['id'], $link_data, $payment_id, $existing_payment['user_id'], $existing_payment['amount']);
        }
    } else {
        // User matching logic (same as success page)
        $user_id = null;
        $user_found = false;
        $processing_message = "";

        // First, check if both email and mobile match
        if ($buyer_email && $buyer_phone) {
            $user_sql = "SELECT id FROM users WHERE email = ? AND mobile = ?";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "ss", $buyer_email, $buyer_phone);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);

            if ($user) {
                $user_id = $user['id'];
                $user_found = true;
                $processing_message = "User found with matching email and mobile.";
            }
        }

        // If not found, check if phone matches
        if (!$user_found && $buyer_phone) {
            $user_sql = "SELECT id FROM users WHERE mobile = ?";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "s", $buyer_phone);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);

            if ($user) {
                $user_id = $user['id'];
                $user_found = true;
                $processing_message = "User found with matching mobile. Updating email.";

                // Update user's email
                $update_sql = "UPDATE users SET email = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $buyer_email, $user_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
        }

        // If not found, check if email matches
        if (!$user_found && $buyer_email) {
            $user_sql = "SELECT id FROM users WHERE email = ?";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "s", $buyer_email);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);

            if ($user) {
                $user_id = $user['id'];
                $user_found = true;
                $processing_message = "User found with matching email. Updating mobile.";

                // Update user's mobile
                $update_sql = "UPDATE users SET mobile = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $buyer_phone, $user_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
        }

        // If no match found, create new user
        if (!$user_found) {
            $processing_message = "Creating new user.";

            $create_sql = "INSERT INTO users (name, email, mobile, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
            $create_stmt = mysqli_prepare($conn, $create_sql);
            mysqli_stmt_bind_param($create_stmt, "sss", $buyer_name, $buyer_email, $buyer_phone);
            mysqli_stmt_execute($create_stmt);
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($create_stmt);
        }
        
        // Create new payment record in instamojo_payments table
        $insert_sql = "INSERT INTO instamojo_payments (link_id, payment_id, buyer_name, buyer_email, buyer_phone, amount, currency, status, user_id, link_name, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, 'INR', ?, ?, ?, NOW(), NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        if (!$insert_stmt) {
            error_log("MySQL prepare error (webhook instamojo_payments): " . mysqli_error($conn));
            // Fallback to query without link_name column
            $insert_sql = "INSERT INTO instamojo_payments (link_id, payment_id, buyer_name, buyer_email, buyer_phone, amount, currency, status, user_id, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, ?, 'INR', ?, ?, NOW(), NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            if (!$insert_stmt) {
                error_log("MySQL prepare error (webhook instamojo_payments fallback): " . mysqli_error($conn));
                http_response_code(500);
                exit('Database error');
            }
            mysqli_stmt_bind_param($insert_stmt, "issssssi", $link_data['id'], $payment_id, $buyer_name, $buyer_email, $buyer_phone, $amount, $status, $user_id);
        } else {
            mysqli_stmt_bind_param($insert_stmt, "isssssssi", $link_data['id'], $payment_id, $buyer_name, $buyer_email, $buyer_phone, $amount, $status, $user_id, $purpose);
        }
        mysqli_stmt_execute($insert_stmt);
        $payment_db_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);
        
        // If payment is completed, process enrollment
        if ($status === 'Credit') {
            processEnrollment($conn, $payment_db_id, $link_data, $payment_id, $user_id, $amount);
        }
    }
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log("Instamojo webhook error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error processing webhook';
}

function processEnrollment($conn, $payment_db_id, $link_data, $payment_id, $user_id, $amount) {
    // Get workshop IDs from link
    $workshop_ids = explode(',', $link_data['workshop_ids']);
    
    // Enroll user in each workshop
    foreach ($workshop_ids as $workshop_id) {
        $workshop_id = trim($workshop_id);
        if (empty($workshop_id)) continue;
        
        // Get workshop details
        $workshop_sql = "SELECT * FROM workshops WHERE id = ?";
        $workshop_stmt = mysqli_prepare($conn, $workshop_sql);
        mysqli_stmt_bind_param($workshop_stmt, "i", $workshop_id);
        mysqli_stmt_execute($workshop_stmt);
        $workshop_result = mysqli_stmt_get_result($workshop_stmt);
        $workshop = mysqli_fetch_assoc($workshop_result);
        mysqli_stmt_close($workshop_stmt);
        
        if ($workshop) {
            // Generate unique order_id and verify_token
            $order_id = generateRandomString(15);
            $verify_token = generateRandomString(15);
            
            // Insert into payments table with instamojo_upload=1
            $payment_insert_sql = "INSERT INTO payments (user_id, workshop_id, payment_id, amount, order_id, mail_send, verify_token, payment_status, cpd, instamojo_upload, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, 0, ?, 1, ?, 1, NOW(), NOW())";
            $payment_insert_stmt = mysqli_prepare($conn, $payment_insert_sql);
            mysqli_stmt_bind_param($payment_insert_stmt, "iissssi", $user_id, $workshop_id, $payment_id, $workshop['price'], $order_id, $verify_token, $workshop['cpd']);
            mysqli_stmt_execute($payment_insert_stmt);
            $payment_table_id = mysqli_insert_id($conn); // Get the ID from payments table
            mysqli_stmt_close($payment_insert_stmt);
            
            // Insert into instamojo_workshop_enrollments table using payments table ID
            $enrollment_sql = "INSERT INTO instamojo_workshop_enrollments (payment_id, workshop_id, user_id, enrollment_date) 
                               VALUES (?, ?, ?, NOW())";
            $enrollment_stmt = mysqli_prepare($conn, $enrollment_sql);
            mysqli_stmt_bind_param($enrollment_stmt, "iii", $payment_table_id, $workshop_id, $user_id);
            mysqli_stmt_execute($enrollment_stmt);
            mysqli_stmt_close($enrollment_stmt);
        }
    }
    
    // Log successful enrollment
    $log_message = "User $user_id enrolled in workshops: " . implode(', ', $workshop_ids) . " via payment $payment_id";
    error_log($log_message);
}

function generateRandomString($length = 15) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
?> 