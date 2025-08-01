<?php
include 'config/show_errors.php';
// No session_start() - this page is independent of login/session

$conn = require_once 'config/config.php';

$payment_id = $_GET['payment_id'] ?? '';
$payment_request_id = $_GET['payment_request_id'] ?? '';

$payment_details = null;
$enrollments = [];
$user_id = null;
$processing_message = '';
$already_processed = false;

// Function to generate random alphanumeric string
function generateRandomString($length = 15) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

if ($payment_id && $payment_request_id) {
    // First, check if this payment has already been processed
    $check_sql = "SELECT COUNT(*) as count FROM payments WHERE payment_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $payment_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if ($check_data['count'] > 0) {
        $already_processed = true;
        $processing_message = "Payment already processed. Showing existing enrollment details.";
        
        // Get amount directly from instamojo_payments table
        $instamojo_amount_sql = "SELECT amount FROM instamojo_payments WHERE payment_id = ?";
        $instamojo_amount_stmt = mysqli_prepare($conn, $instamojo_amount_sql);
        mysqli_stmt_bind_param($instamojo_amount_stmt, "s", $payment_id);
        mysqli_stmt_execute($instamojo_amount_stmt);
        $instamojo_amount_result = mysqli_stmt_get_result($instamojo_amount_stmt);
        $instamojo_amount_data = mysqli_fetch_assoc($instamojo_amount_result);
        mysqli_stmt_close($instamojo_amount_stmt);
        
        $original_payment_amount = $instamojo_amount_data ? $instamojo_amount_data['amount'] : null;
        
        // Get existing payment details
        $existing_sql = "SELECT p.*, u.name as buyer_name, u.email as buyer_email, u.mobile as buyer_phone, w.name as workshop_name, w.start_date, w.trainer_name, w.price, ip.amount as instamojo_amount
                        FROM payments p 
                        JOIN users u ON p.user_id = u.id 
                        JOIN workshops w ON p.workshop_id = w.id 
                        LEFT JOIN instamojo_payments ip ON p.payment_id = ip.payment_id
                        WHERE p.payment_id = ?";
        $existing_stmt = mysqli_prepare($conn, $existing_sql);
        if (!$existing_stmt) {
            error_log("MySQL prepare error: " . mysqli_error($conn));
            // Fallback to query without instamojo_payments join
            $existing_sql = "SELECT p.*, u.name as buyer_name, u.email as buyer_email, u.mobile as buyer_phone, w.name as workshop_name, w.start_date, w.trainer_name, w.price
                            FROM payments p 
                            JOIN users u ON p.user_id = u.id 
                            JOIN workshops w ON p.workshop_id = w.id 
                            WHERE p.payment_id = ?";
            $existing_stmt = mysqli_prepare($conn, $existing_sql);
            if (!$existing_stmt) {
                error_log("MySQL prepare error (fallback): " . mysqli_error($conn));
                die("Database error occurred");
            }
        }
        mysqli_stmt_bind_param($existing_stmt, "s", $payment_id);
        mysqli_stmt_execute($existing_stmt);
        $existing_result = mysqli_stmt_get_result($existing_stmt);
        $total_amount = 0;
        $instamojo_amount = 0;
        while ($payment = mysqli_fetch_assoc($existing_result)) {
            $enrollments[] = [
                'id' => $payment['workshop_id'],
                'name' => $payment['workshop_name'],
                'start_date' => $payment['start_date'],
                'trainer_name' => $payment['trainer_name']
            ];
            $total_amount += $payment['price'];
            $instamojo_amount = isset($payment['instamojo_amount']) ? $payment['instamojo_amount'] : null; // Get amount from instamojo_payments if available
            $this_is_original_payment = $original_payment_amount ? $original_payment_amount : $payment['amount'];
            // Store payment details from first record
            if (!$payment_details) {
                $payment_details = [
                    'payment_id' => $payment['payment_id'],
                    'amount' => $instamojo_amount ? $instamojo_amount : $payment['amount'], // Use instamojo amount if available, otherwise use payment amount
                    'status' => 'Completed',
                    'buyer_name' => $payment['buyer_name'],
                    'buyer_email' => $payment['buyer_email'],
                    'buyer_phone' => $payment['buyer_phone'],
                    'processing_message' => $processing_message
                ];
            }
        }
        mysqli_stmt_close($existing_stmt);
    } else {
        // Process new payment
        // First, get payment details from Instamojo API
        $instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
        $instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
        $instamojo_base_url = 'https://api.instamojo.com/v2/';
        $instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';
        
        // Function to get OAuth2 access token
        function getInstamojoAccessToken($client_id, $client_secret, $oauth_url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $oauth_url);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            
            $payload = array(
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret
            );
            
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $token_data = json_decode($response, true);
                if (isset($token_data['access_token'])) {
                    return $token_data['access_token'];
                }
            }
            return false;
        }
        
        // Get payment details from Instamojo API
        $access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);
        
        if ($access_token) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payments/' . $payment_id . '/');
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $access_token"
            ]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $payment_data = json_decode($response, true);
                
                // Extract payment information
                $buyer_name = $payment_data['name'] ?? '';
                $buyer_email = $payment_data['email'] ?? '';
                $buyer_phone = $payment_data['phone'] ?? '';
                $amount = $payment_data['amount'] ?? 0;
                $status = $payment_data['status'] ?? false;
                
                // Get payment request details to find associated workshops
                $payment_request_url = $payment_data['payment_request'] ?? '';
                $payment_request_id_from_api = basename(rtrim($payment_request_url, '/'));
                
                // Find the link in our database
                $link_sql = "SELECT * FROM instamojo_links WHERE instamojo_link_id = ?";
                $link_stmt = mysqli_prepare($conn, $link_sql);
                mysqli_stmt_bind_param($link_stmt, "s", $payment_request_id_from_api);
                mysqli_stmt_execute($link_stmt);
                $link_result = mysqli_stmt_get_result($link_stmt);
                $link_data = mysqli_fetch_assoc($link_result);
                mysqli_stmt_close($link_stmt);
                
                if ($link_data && $status) {
                    // User matching logic
                    $user_id = null;
                    $user_found = false;
                    
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
                    
                    // Now process workshop enrollments
                    if ($user_id) {
                        $workshop_ids = explode(',', $link_data['workshop_ids']);
                        
                        // First, insert into instamojo_payments table
                        $instamojo_payment_sql = "INSERT INTO instamojo_payments (link_id, payment_id, buyer_name, buyer_email, buyer_phone, amount, currency, status, user_id, link_name, created_at, updated_at) 
                                                  VALUES (?, ?, ?, ?, ?, ?, 'INR', ?, ?, ?, NOW(), NOW())";
                        $instamojo_payment_stmt = mysqli_prepare($conn, $instamojo_payment_sql);
                        if (!$instamojo_payment_stmt) {
                            error_log("MySQL prepare error (instamojo_payments): " . mysqli_error($conn));
                            // Fallback to query without link_name column
                            $instamojo_payment_sql = "INSERT INTO instamojo_payments (link_id, payment_id, buyer_name, buyer_email, buyer_phone, amount, currency, status, user_id, created_at, updated_at) 
                                                      VALUES (?, ?, ?, ?, ?, ?, 'INR', ?, ?, NOW(), NOW())";
                            $instamojo_payment_stmt = mysqli_prepare($conn, $instamojo_payment_sql);
                            if (!$instamojo_payment_stmt) {
                                error_log("MySQL prepare error (instamojo_payments fallback): " . mysqli_error($conn));
                                die("Database error occurred");
                            }
                            $payment_status = $status ? 'completed' : 'pending';
                            mysqli_stmt_bind_param($instamojo_payment_stmt, "issssssi", $link_data['id'], $payment_id, $buyer_name, $buyer_email, $buyer_phone, $amount, $payment_status, $user_id);
                        } else {
                            $payment_status = $status ? 'completed' : 'pending';
                            mysqli_stmt_bind_param($instamojo_payment_stmt, "isssssssi", $link_data['id'], $payment_id, $buyer_name, $buyer_email, $buyer_phone, $amount, $payment_status, $user_id, $payment_data['title']);
                        }
                        mysqli_stmt_execute($instamojo_payment_stmt);
                        mysqli_stmt_close($instamojo_payment_stmt);
                        
                        foreach ($workshop_ids as $workshop_id) {
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
                                
                                // Add to enrollments array for display
                                $enrollments[] = [
                                    'id' => $workshop['id'],
                                    'name' => $workshop['name'],
                                    'start_date' => $workshop['start_date'],
                                    'trainer_name' => $workshop['trainer_name']
                                ];
                            }
                        }
                        
                        // Store payment details for display
                        $payment_details = [
                            'payment_id' => $payment_id,
                            'amount' => $amount,
                            'status' => $status ? 'Completed' : 'Pending',
                            'buyer_name' => $buyer_name,
                            'buyer_email' => $buyer_email,
                            'buyer_phone' => $buyer_phone,
                            'processing_message' => $processing_message
                        ];
                    }
                }
            }
        }
    }
}

$page_title = "Payment Successful";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - IPN Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .success-container {
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        .success-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .success-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        .success-icon {
            font-size: 5rem;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
            animation: bounce 2s ease-in-out infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        .success-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        .success-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        .card-body {
            padding: 40px;
        }
        .workshop-item {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .workshop-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .workshop-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            border-color: #28a745;
        }
        .workshop-date {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .trainer-name {
            color: #495057;
            font-weight: 600;
        }
        .alert {
            border-radius: 15px;
            border: none;
            padding: 20px;
        }
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            color: #1565c0;
        }
        .badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        .payment-details {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .contact-info {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-top: 30px;
        }
        .contact-info a {
            color: #856404;
            text-decoration: none;
            font-weight: 600;
        }
        .contact-info a:hover {
            color: #533f03;
        }
        .processing-message {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-header <?php echo !$payment_details ? 'error' : ''; ?>" <?php echo !$payment_details ? 'style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);"' : ''; ?>>
                <?php if ($payment_details): ?>
                    <div class="success-icon">
                        <i class="bx bx-check-circle"></i>
                    </div>
                    <h2 class="mb-3">Payment Successful!</h2>
                    <p class="mb-0">Thank you for your payment. You have been successfully enrolled in the selected workshops.</p>
                <?php else: ?>
                    <div class="success-icon">
                        <i class="bx bx-error-circle"></i>
                    </div>
                    <h2 class="mb-3">Payment Issue</h2>
                    <p class="mb-0">We couldn't find your payment information. Please contact our support team.</p>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <?php if ($payment_details): ?>
                    <!-- Processing Message -->
                    <?php if (!empty($processing_message)): ?>
                    <div class="processing-message">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Processing:</strong> <?php echo htmlspecialchars($processing_message); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Payment Details -->
                    <div class="payment-details">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">
                                    <i class="bx bx-credit-card me-2"></i>
                                    Payment Details
                                </h6>
                                <p class="mb-2"><strong>Payment ID:</strong> <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($payment_details['payment_id']); ?></code></p>
                                <p class="mb-2"><strong>Amount:</strong> <span class="text-success fw-bold">₹<?php echo number_format($this_is_original_payment, 2); ?></span></p>
                                <p class="mb-0"><strong>Status:</strong> 
                                    <span class="badge bg-success"><?php echo ucfirst($payment_details['status']); ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">
                                    <i class="bx bx-user me-2"></i>
                                    Buyer Information
                                </h6>
                                <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($payment_details['buyer_name']); ?></p>
                                <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($payment_details['buyer_email']); ?></p>
                                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($payment_details['buyer_phone']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enrolled Workshops -->
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">
                            <i class="bx bx-book-open me-2"></i>
                            Enrolled Workshops
                        </h6>
                        <?php if (!empty($enrollments)): ?>
                            <?php foreach ($enrollments as $workshop): ?>
                                <div class="workshop-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-2 fw-bold"><?php echo htmlspecialchars($workshop['name']); ?></h6>
                                            <p class="workshop-date mb-2">
                                                <i class="bx bx-calendar me-2"></i>
                                                <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                            </p>
                                            <p class="trainer-name mb-0">
                                                <i class="bx bx-user me-2"></i>
                                                <?php echo htmlspecialchars($workshop['trainer_name']); ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-primary">
                                            <i class="bx bx-check me-1"></i>
                                            Enrolled
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle me-2"></i>
                                Workshop details are being processed. You will receive an email confirmation shortly.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading mb-3">
                            <i class="bx bx-info-circle me-2"></i>
                            What's Next?
                        </h6>
                        <ul class="mb-0">
                            <li>You will receive a confirmation email with workshop details</li>
                            <li>Workshop links and meeting details will be shared before the session</li>
                            <li>Please check your email regularly for updates</li>
                        </ul>
                    </div>
                    
                <?php else: ?>
                    <!-- Payment not found - just show contact support -->
                    <div class="text-center py-5">
                        <div class="contact-info">
                            <h5 class="mb-3">Need Help?</h5>
                            <p class="text-muted mb-4">
                                If you're experiencing issues with your payment, please contact our support team.
                            </p>
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="bx bx-envelope me-2"></i>
                                        <a href="mailto:support@ipnacademy.in">support@ipnacademy.in</a>
                                    </p>
                                    <p class="mb-0">
                                        <i class="bx bx-phone me-2"></i>
                                        <a href="tel:+919876543210">+91 98765 43210</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Contact Information -->
                <div class="contact-info">
                    <p class="text-muted mb-2">Need help? Contact us:</p>
                    <p class="mb-0">
                        <i class="bx bx-envelope me-2"></i>
                        <a href="mailto:support@ipnacademy.in">support@ipnacademy.in</a>
                        <span class="mx-3">|</span>
                        <i class="bx bx-phone me-2"></i>
                        <a href="tel:+919876543210">+91 98765 43210</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 