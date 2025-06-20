<?php
include 'config/show_errors.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$conn = require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
$workshop_id = isset($_POST['workshop_id']) ? (int)$_POST['workshop_id'] : 0;

header('Content-Type: application/json');
if (!$workshop_id || !is_array($user_ids) || count($user_ids) === 0) {
    echo json_encode(['success' => false, 'enrolled' => 0, 'errors' => ['Invalid input']]);
    exit;
}

// Get workshop details
$workshop_sql = "SELECT price, cpd FROM workshops WHERE id = $workshop_id";
$workshop_result = mysqli_query($conn, $workshop_sql);
if (!$workshop_result || mysqli_num_rows($workshop_result) === 0) {
    echo json_encode(['success' => false, 'enrolled' => 0, 'errors' => ['Workshop not found']]);
    exit;
}
$workshop = mysqli_fetch_assoc($workshop_result);

$enrolled = 0;
$errors = [];
foreach ($user_ids as $user_id) {
    $user_id = (int)$user_id;
    // Check if already enrolled
    $check_sql = "SELECT id FROM payments WHERE user_id = $user_id AND workshop_id = $workshop_id AND payment_status = 1";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        continue;
    }
    // Enroll user
    $verify_token = bin2hex(random_bytes(32));
    $order_id = rand(11111111111111111, 99999999999999999);
    $payment_sql = "INSERT INTO payments (user_id, workshop_id, amount, payment_status, cpd, verify_token, payment_id, order_id, created_at, updated_at) 
                  VALUES ($user_id, $workshop_id, '{$workshop['price']}', 1, {$workshop['cpd']}, '$verify_token', 'Bulk-Enroll', '$order_id', NOW(), NOW())";
    if (mysqli_query($conn, $payment_sql)) {
        $enrolled++;
    } else {
        $errors[] = "User ID $user_id: " . mysqli_error($conn);
    }
}
echo json_encode(['success' => true, 'enrolled' => $enrolled, 'errors' => $errors]); 