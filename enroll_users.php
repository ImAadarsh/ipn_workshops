<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Get workshop ID and user IDs
$workshop_id = isset($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;
$user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];

if (!$workshop_id || empty($user_ids)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Get workshop details
$workshop_sql = "SELECT price, cpd, name FROM workshops WHERE id = $workshop_id";
$workshop_result = mysqli_query($conn, $workshop_sql);
if (!$workshop_result || mysqli_num_rows($workshop_result) === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Workshop not found']);
    exit();
}
$workshop = mysqli_fetch_assoc($workshop_result);

$success = true;
$enrolled = 0;
$errors = [];

foreach ($user_ids as $user_id) {
    $user_id = intval($user_id);
    
    // Check if payment already exists
    $check_sql = "SELECT id, payment_status FROM payments WHERE user_id = $user_id AND workshop_id = $workshop_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Payment exists, update if status is 0
        $payment = mysqli_fetch_assoc($check_result);
        if ($payment['payment_status'] == 0) {
            $update_sql = "UPDATE payments SET payment_status = 1, updated_at = NOW() WHERE id = {$payment['id']}";
            if (mysqli_query($conn, $update_sql)) {
                $enrolled++;
            } else {
                $errors[] = "Failed to update payment for user ID $user_id: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "User ID $user_id is already enrolled in this workshop";
        }
        continue;
    }
    
    // Generate verification token and order ID
    $verify_token = bin2hex(random_bytes(32));
    $order_id = rand(11111111111111111, 99999999999999999);
    
    // Create payment record
    $sql = "INSERT INTO payments (user_id, workshop_id, amount, payment_status, cpd, verify_token, 
            payment_id, order_id, created_at, updated_at, instamojo_upload) 
            VALUES ($user_id, $workshop_id, '{$workshop['price']}', 0, {$workshop['cpd']}, 
            '$verify_token', 'Google-Form-Paid', '$order_id', NOW(), NOW(), 1)";
    
    if (!mysqli_query($conn, $sql)) {
        $success = false;
        $errors[] = "Failed to enroll user ID $user_id: " . mysqli_error($conn);
    } else {
        $enrolled++;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'enrolled' => $enrolled,
    'errors' => $errors
]);