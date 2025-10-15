# Instamojo Payment Integration Summary

## Overview
This document provides a comprehensive summary of how Instamojo payment gateway is integrated with the IPN Workshop Admin system, including API keys, URIs, and quiz functionality integration.

## 🔑 API Credentials & Configuration

### Instamojo API Keys
```php
// API Credentials (found in multiple files)
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

## 🏗️ System Architecture

### Core Files Structure
```
├── instamojo_dashboard.php          # Main dashboard for creating payment links
├── instamojo_links.php             # Manage existing payment links
├── instamojo_payments.php          # View payment history
├── instamojo_success.php           # Payment success page
├── instamojo_webhook.php           # Webhook handler for payment notifications
├── debug_instamojo_api.php         # API testing and debugging
└── test_instamojo_api.php          # API connection testing
```

### Database Tables
```sql
-- Main payment links table
CREATE TABLE instamojo_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    link_name VARCHAR(255),
    workshop_ids TEXT,  -- Comma-separated workshop IDs
    instamojo_link_id VARCHAR(255),
    amount DECIMAL(10,2),
    status ENUM('active', 'inactive'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payment transactions table
CREATE TABLE instamojo_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    link_id INT,
    payment_id VARCHAR(255),
    buyer_name VARCHAR(255),
    buyer_email VARCHAR(255),
    buyer_phone VARCHAR(20),
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'INR',
    status ENUM('pending', 'completed', 'failed'),
    user_id INT,
    link_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Workshop enrollments tracking
CREATE TABLE instamojo_workshop_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT,  -- References payments table
    workshop_id INT,
    user_id INT,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Main payments table (existing)
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    workshop_id INT,
    payment_id VARCHAR(255),
    amount DECIMAL(10,2),
    order_id VARCHAR(255),
    verify_token VARCHAR(255),
    payment_status TINYINT(1) DEFAULT 0,
    cpd INT,
    instamojo_upload TINYINT(1) DEFAULT 0,  -- Flag for Instamojo payments
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🔄 Payment Flow Process

### 1. Payment Link Creation
```php
// OAuth2 Token Generation
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
    return false;
}

// Payment Request Creation
$instamojo_data = [
    'purpose' => $link_name,
    'amount' => $amount,
    'redirect_url' => 'https://workshops.ipnacademy.in/instamojo_success.php',
    'send_email' => 'False',
    'webhook' => 'https://workshops.ipnacademy.in/instamojo_webhook.php',
    'allow_repeated_payments' => 'True'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/');
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
]);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($instamojo_data));
```

### 2. Webhook Processing
```php
// Webhook receives payment notifications
$webhook_data = file_get_contents('php://input');
parse_str($webhook_data, $data);

$payment_id = $data['payment_id'] ?? '';
$payment_request_id = $data['payment_request_id'] ?? '';
$status = $data['status'] ?? '';
$buyer_name = $data['buyer_name'] ?? '';
$buyer_email = $data['buyer'] ?? '';
$buyer_phone = $data['buyer_phone'] ?? '';
$amount = $data['amount'] ?? 0;

// Process enrollment if payment is successful
if ($status === 'Credit') {
    processEnrollment($conn, $payment_db_id, $link_data, $payment_id, $user_id, $amount);
}
```

### 3. User Matching & Enrollment
```php
function processEnrollment($conn, $payment_db_id, $link_data, $payment_id, $user_id, $amount) {
    $workshop_ids = explode(',', $link_data['workshop_ids']);
    
    foreach ($workshop_ids as $workshop_id) {
        // Insert into payments table
        $payment_insert_sql = "INSERT INTO payments (user_id, workshop_id, payment_id, amount, order_id, mail_send, verify_token, payment_status, cpd, instamojo_upload, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, 0, ?, 1, ?, 1, NOW(), NOW())";
        
        // Insert into enrollment tracking
        $enrollment_sql = "INSERT INTO instamojo_workshop_enrollments (payment_id, workshop_id, user_id, enrollment_date) 
                           VALUES (?, ?, ?, NOW())";
    }
    
    // Send confirmation email
    sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
}
```

## 🧠 Quiz System Integration

### Quiz Database Structure
```sql
-- MCQ Questions Table
CREATE TABLE workshop_mcq_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workshop_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option1 VARCHAR(255) NOT NULL,
    option2 VARCHAR(255) NOT NULL,
    option3 VARCHAR(255) NOT NULL,
    option4 VARCHAR(255) NOT NULL,
    correct_option INT NOT NULL,  -- 1, 2, 3, or 4
    question_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assessment Questions (Open-ended)
CREATE TABLE workshop_assessment_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workshop_id BIGINT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    question_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assessment Responses
CREATE TABLE workshop_assessment_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workshop_id BIGINT UNSIGNED NOT NULL,
    question_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    designation VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    school_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    answer TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Quiz Management Files
```
├── workshop_questions.php          # Overview of workshops with questions
├── manage_workshop_questions.php   # CRUD operations for MCQ questions
└── workshop-details.php           # Workshop details with quiz integration
```

### Quiz Integration with Payments
The quiz system is integrated with the payment system through:

1. **Workshop Association**: Each quiz is linked to specific workshops via `workshop_id`
2. **Payment Verification**: Users must complete payment before accessing quizzes
3. **Enrollment Tracking**: Payment completion automatically enrolls users in workshops
4. **Access Control**: Quiz access is controlled by payment status in the `payments` table

## 🔧 Key Functions & Methods

### OAuth2 Authentication
```php
function getInstamojoAccessToken($client_id, $client_secret, $oauth_url) {
    // Returns access token for API calls
}
```

### Payment Processing
```php
function processEnrollment($conn, $payment_db_id, $link_data, $payment_id, $user_id, $amount) {
    // Handles workshop enrollment after successful payment
}
```

### User Matching
```php
// Matches payment data with existing users or creates new users
// Priority: Email + Mobile > Mobile only > Email only > Create new
```

### Email Notifications
```php
function sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data) {
    // Sends confirmation emails after successful payment
}
```

## 🌐 Webhook Configuration

### Webhook URL
```
https://workshops.ipnacademy.in/instamojo_webhook.php
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
    'purpose' => 'Workshop Payment'
];
```

## 🔒 Security Features

### 1. OAuth2 Authentication
- Client credentials flow for server-to-server communication
- Access tokens for API requests

### 2. Webhook Verification
```php
// Webhook signature verification (implemented but commented)
// $signature = $_SERVER['HTTP_X_INSTAMOJO_SIGNATURE'] ?? '';
```

### 3. Database Security
- Prepared statements for all database queries
- Input validation and sanitization
- SQL injection prevention

## 📧 Email Integration

### Email Helper Functions
```php
// Located in config/email_helper.php
function sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data) {
    // Sends detailed payment confirmation with workshop details
}
```

### Email Features
- Payment confirmation emails
- Workshop enrollment notifications
- Email resend functionality
- PHPMailer integration

## 🚀 Deployment URLs

### Production URLs
```
Base Domain: https://workshops.ipnacademy.in/
Success Page: https://workshops.ipnacademy.in/instamojo_success.php
Webhook: https://workshops.ipnacademy.in/instamojo_webhook.php
```

### Admin Panel URLs
```
Dashboard: /instamojo_dashboard.php
Links Management: /instamojo_links.php
Payment History: /instamojo_payments.php
```

## 🛠️ Testing & Debugging

### Debug Files
```
debug_instamojo_api.php    # Comprehensive API testing
test_instamojo_api.php     # Basic connection testing
```

### Testing Features
- OAuth2 token generation testing
- Payment request creation testing
- API endpoint validation
- Webhook simulation

## 📊 Analytics & Reporting

### Payment Analytics
- Payment success/failure rates
- Workshop enrollment statistics
- Revenue tracking
- User engagement metrics

### Quiz Analytics
- Question performance analysis
- User response tracking
- Assessment completion rates

## 🔄 Integration Points for Other Projects

### 1. API Integration
```php
// Use these credentials and endpoints
$client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
$base_url = 'https://api.instamojo.com/v2/';
$oauth_url = 'https://api.instamojo.com/oauth2/token/';
```

### 2. Database Schema
- Copy the table structures provided above
- Implement the same enrollment tracking system
- Use similar user matching logic

### 3. Webhook Implementation
- Implement the same webhook processing logic
- Use similar payment status handling
- Follow the same enrollment workflow

### 4. Quiz System
- Use the MCQ question structure
- Implement similar assessment tracking
- Follow the same workshop-quiz association pattern

## 📝 Notes for AI Integration

When asking AI to integrate this system into another project, provide:

1. **API Credentials**: The client_id and client_secret above
2. **Database Schema**: The table structures provided
3. **Webhook URLs**: The success and webhook endpoints
4. **Payment Flow**: The complete enrollment process
5. **Quiz Structure**: The MCQ and assessment question formats
6. **Email Integration**: The confirmation email system

This integration provides a complete payment gateway solution with quiz functionality, user management, and automated enrollment processing.
