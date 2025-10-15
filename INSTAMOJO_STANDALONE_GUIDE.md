# Instamojo Payment Gateway Integration Guide

## Overview
This document provides a complete guide for integrating Instamojo payment gateway into any PHP project, including API setup, webhook handling, and database implementation.

## 🔑 API Credentials & Configuration

### Instamojo API Keys
```php
// API Credentials
$instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
```

### API Endpoints
```php
// Base URLs
$instamojo_base_url = 'https://api.instamojo.com/v2/';
$instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';

// Key Endpoints
$payment_requests_url = $instamojo_base_url . 'payment_requests/';
$payments_url = $instamojo_base_url . 'payments/';
```

## 🏗️ Database Schema

### Payment Links Table
```sql
CREATE TABLE instamojo_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    link_name VARCHAR(255) NOT NULL,
    product_ids TEXT,  -- Comma-separated product/service IDs
    instamojo_link_id VARCHAR(255) UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Payment Transactions Table
```sql
CREATE TABLE instamojo_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    link_id INT,
    payment_id VARCHAR(255) UNIQUE,
    buyer_name VARCHAR(255),
    buyer_email VARCHAR(255),
    buyer_phone VARCHAR(20),
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'INR',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    user_id INT,
    link_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (link_id) REFERENCES instamojo_links(id)
);
```

### Main Payments Table
```sql
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    product_id INT,  -- Your product/service ID
    payment_id VARCHAR(255),
    amount DECIMAL(10,2),
    order_id VARCHAR(255),
    verify_token VARCHAR(255),
    payment_status TINYINT(1) DEFAULT 0,
    instamojo_upload TINYINT(1) DEFAULT 0,  -- Flag for Instamojo payments
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🔄 Core Integration Functions

### 1. OAuth2 Authentication
```php
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
        return $token_data['access_token'] ?? false;
    }
    
    error_log("Failed to get Instamojo access token. HTTP Code: $http_code, Response: $response");
    return false;
}
```

### 2. Create Payment Link
```php
function createInstamojoPaymentLink($link_name, $amount, $product_ids, $redirect_url, $webhook_url) {
    global $instamojo_client_id, $instamojo_client_secret, $instamojo_base_url, $instamojo_oauth_url;
    
    // Get access token
    $access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);
    
    if (!$access_token) {
        return ['success' => false, 'error' => 'Failed to get access token'];
    }
    
    // Prepare payment request data
    $instamojo_data = [
        'purpose' => $link_name,
        'amount' => $amount,
        'redirect_url' => $redirect_url,
        'send_email' => 'False',
        'webhook' => $webhook_url,
        'allow_repeated_payments' => 'True'
    ];
    
    // Create payment request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/');
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($instamojo_data));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 201) {
        $response_data = json_decode($response, true);
        return [
            'success' => true,
            'payment_request_id' => $response_data['payment_request']['id'],
            'payment_url' => $response_data['payment_request']['longurl']
        ];
    }
    
    return [
        'success' => false,
        'error' => "HTTP Code: $http_code, Response: $response, Curl Error: $curl_error"
    ];
}
```

### 3. Webhook Handler
```php
// webhook.php
<?php
// Include your database connection
require_once 'config/database.php';

// Log webhook data for debugging
$webhook_data = file_get_contents('php://input');
$log_file = 'instamojo_webhook.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Webhook received: " . $webhook_data . "\n", FILE_APPEND);

try {
    // Parse POST data (webhook sends form data, not JSON)
    parse_str($webhook_data, $data);
    
    if (!$data) {
        http_response_code(400);
        exit('Invalid webhook data');
    }
    
    // Extract payment information
    $payment_id = $data['payment_id'] ?? '';
    $payment_request_id = $data['payment_request_id'] ?? '';
    $status = $data['status'] ?? '';
    $buyer_name = $data['buyer_name'] ?? '';
    $buyer_email = $data['buyer'] ?? '';
    $buyer_phone = $data['buyer_phone'] ?? '';
    $amount = $data['amount'] ?? 0;
    $purpose = $data['purpose'] ?? '';
    
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
        $status_completed = ($status === 'Credit') ? 'completed' : 'failed';
        $update_sql = "UPDATE instamojo_payments SET status = ?, updated_at = NOW() WHERE payment_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ss", $status_completed, $payment_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        // Process if payment is now completed
        if ($status === 'Credit' && $existing_payment['status'] !== 'completed') {
            processPaymentSuccess($conn, $existing_payment, $link_data, $payment_id, $amount);
        }
    } else {
        // Create new payment record
        $status_completed = ($status === 'Credit') ? 'completed' : 'failed';
        
        $insert_sql = "INSERT INTO instamojo_payments (link_id, payment_id, buyer_name, buyer_email, buyer_phone, amount, currency, status, link_name, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, 'INR', ?, ?, NOW(), NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "issssss", $link_data['id'], $payment_id, $buyer_name, $buyer_email, $buyer_phone, $amount, $status_completed, $purpose);
        mysqli_stmt_execute($insert_stmt);
        $payment_db_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);
        
        // Process if payment is completed
        if ($status === 'Credit') {
            processPaymentSuccess($conn, ['id' => $payment_db_id, 'user_id' => null], $link_data, $payment_id, $amount);
        }
    }
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log("Instamojo webhook error: " . $e->getMessage());
    http_response_code(500);
    echo 'Error processing webhook';
}
?>
```

### 4. Payment Success Processing
```php
function processPaymentSuccess($conn, $payment_data, $link_data, $payment_id, $amount) {
    // Get product IDs from link
    $product_ids = explode(',', $link_data['product_ids']);
    
    // Process each product
    foreach ($product_ids as $product_id) {
        $product_id = trim($product_id);
        if (empty($product_id)) continue;
        
        // Generate unique order_id and verify_token
        $order_id = generateRandomString(15);
        $verify_token = generateRandomString(15);
        
        // Insert into main payments table
        $payment_insert_sql = "INSERT INTO payments (user_id, product_id, payment_id, amount, order_id, verify_token, payment_status, instamojo_upload, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())";
        $payment_insert_stmt = mysqli_prepare($conn, $payment_insert_sql);
        mysqli_stmt_bind_param($payment_insert_stmt, "iissss", $payment_data['user_id'], $product_id, $payment_id, $amount, $order_id, $verify_token);
        mysqli_stmt_execute($payment_insert_stmt);
        mysqli_stmt_close($payment_insert_stmt);
    }
    
    // Log successful processing
    $log_message = "Payment $payment_id processed successfully for products: " . implode(', ', $product_ids);
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
```

## 🌐 Webhook Configuration

### Webhook URL Setup
```
Your Webhook URL: https://yourdomain.com/instamojo_webhook.php
Success Redirect URL: https://yourdomain.com/payment_success.php
```

### Webhook Data Structure
```php
// Received webhook data
$data = [
    'payment_id' => 'MOJO1234567890',
    'payment_request_id' => '051e6f0fa0434aa7b2e1aa7213002da7',
    'status' => 'Credit',  // or 'Failed'
    'buyer_name' => 'John Doe',
    'buyer' => 'john@example.com',  // Email field
    'buyer_phone' => '+919876543210',
    'amount' => '500.00',
    'purpose' => 'Product Payment'
];
```

## 🔒 Security Features

### 1. OAuth2 Authentication
- Client credentials flow for server-to-server communication
- Access tokens for API requests

### 2. Webhook Verification (Recommended)
```php
// Verify webhook signature
$signature = $_SERVER['HTTP_X_INSTAMOJO_SIGNATURE'] ?? '';
// Implement signature verification logic here
```

### 3. Database Security
- Prepared statements for all database queries
- Input validation and sanitization
- SQL injection prevention

## 📧 Email Integration

### Payment Confirmation Email
```php
function sendPaymentConfirmationEmail($buyer_email, $buyer_name, $payment_id, $amount, $products) {
    // Your email sending logic here
    // Use PHPMailer or similar library
    
    $subject = "Payment Confirmation - Payment ID: $payment_id";
    $message = "
        Dear $buyer_name,
        
        Your payment of ₹$amount has been processed successfully.
        Payment ID: $payment_id
        
        Products/Services:
        " . implode("\n", $products) . "
        
        Thank you for your purchase!
    ";
    
    // Send email logic
    return mail($buyer_email, $subject, $message);
}
```

## 🚀 Implementation Example

### Creating a Payment Link
```php
<?php
// config.php
$instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
$instamojo_base_url = 'https://api.instamojo.com/v2/';
$instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';

// Include the functions above

// Create payment link
$result = createInstamojoPaymentLink(
    'Product Purchase',  // Link name
    500.00,             // Amount
    '1,2,3',           // Product IDs (comma-separated)
    'https://yourdomain.com/success.php',  // Success URL
    'https://yourdomain.com/webhook.php'   // Webhook URL
);

if ($result['success']) {
    // Store link in database
    $insert_sql = "INSERT INTO instamojo_links (link_name, product_ids, instamojo_link_id, amount, status) VALUES (?, ?, ?, ?, 'active')";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "sssd", 'Product Purchase', '1,2,3', $result['payment_request_id'], 500.00);
    mysqli_stmt_execute($stmt);
    
    // Redirect user to payment page
    header("Location: " . $result['payment_url']);
} else {
    echo "Error: " . $result['error'];
}
?>
```

## 🛠️ Testing & Debugging

### Test API Connection
```php
<?php
// test_connection.php
$access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);

if ($access_token) {
    echo "✅ API Connection Successful!";
    echo "Access Token: " . substr($access_token, 0, 20) . "...";
} else {
    echo "❌ API Connection Failed!";
}
?>
```

### Debug Webhook
```php
// Add to webhook.php for debugging
error_log("Webhook Data: " . print_r($data, true));
error_log("Payment ID: " . $payment_id);
error_log("Status: " . $status);
```

## 📊 Payment Management

### View Payment History
```php
function getPaymentHistory($conn, $limit = 50) {
    $sql = "SELECT ip.*, il.link_name 
            FROM instamojo_payments ip 
            LEFT JOIN instamojo_links il ON ip.link_id = il.id 
            ORDER BY ip.created_at DESC 
            LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

### Update Payment Status
```php
function updatePaymentStatus($conn, $payment_id, $new_status) {
    $sql = "UPDATE instamojo_payments SET status = ?, updated_at = NOW() WHERE payment_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $new_status, $payment_id);
    return mysqli_stmt_execute($stmt);
}
```

## 🔄 Integration Checklist

- [ ] Set up database tables
- [ ] Configure API credentials
- [ ] Implement OAuth2 authentication
- [ ] Create payment link function
- [ ] Set up webhook handler
- [ ] Implement payment success processing
- [ ] Add email notifications
- [ ] Test API connection
- [ ] Test webhook processing
- [ ] Implement security measures
- [ ] Add error handling and logging

## 📝 Notes

1. **API Credentials**: Keep your client_id and client_secret secure
2. **Webhook Security**: Implement signature verification for production
3. **Error Handling**: Always handle API failures gracefully
4. **Logging**: Log all payment transactions for audit purposes
5. **Testing**: Test thoroughly in sandbox mode before going live

This integration provides a complete Instamojo payment gateway solution that can be adapted to any PHP project.
