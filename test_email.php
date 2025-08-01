<?php
// Test Email Functionality
include 'config/show_errors.php';
include_once 'config/email_helper.php';

echo "<h2>Email Functionality Test</h2>";

// Test data
$user_data = [
    'name' => 'Test User',
    'email' => 'test@example.com'
];

$payment_data = [
    'payment_id' => 'TEST_PAYMENT_123',
    'amount' => 999.00,
    'created_at' => date('Y-m-d H:i:s')
];

$workshops_data = [
    [
        'name' => 'Advanced PHP Workshop',
        'start_date' => '2024-01-15 10:00:00',
        'trainer_name' => 'John Doe',
        'duration' => '3',
        'price' => 599.00
    ],
    [
        'name' => 'Web Development Bootcamp',
        'start_date' => '2024-01-20 14:00:00',
        'trainer_name' => 'Jane Smith',
        'duration' => '4',
        'price' => 799.00
    ]
];

echo "<h3>Test Data:</h3>";
echo "<pre>";
echo "User: " . $user_data['name'] . " (" . $user_data['email'] . ")\n";
echo "Payment: " . $payment_data['payment_id'] . " - ₹" . $payment_data['amount'] . "\n";
echo "Workshops: " . count($workshops_data) . " workshops\n";
echo "</pre>";

// Test email sending
echo "<h3>Testing Email Sending...</h3>";

try {
    $result = sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to send email.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Check PHPMailer availability
echo "<h3>PHPMailer Status:</h3>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer is available</p>";
} else {
    echo "<p style='color: orange;'>⚠️ PHPMailer not available, will use PHP's built-in mail function</p>";
    echo "<p>To install PHPMailer, run: <code>composer require phpmailer/phpmailer</code></p>";
}

// Check mail function
echo "<h3>PHP Mail Function Status:</h3>";
if (function_exists('mail')) {
    echo "<p style='color: green;'>✅ PHP mail() function is available</p>";
} else {
    echo "<p style='color: red;'>❌ PHP mail() function is not available</p>";
}

echo "<h3>Email Configuration:</h3>";
echo "<p>Using Google SMTP with multiple email accounts for redundancy.</p>";
echo "<p>Email accounts configured:</p>";
echo "<ul>";
echo "<li>ipnacademy2023@gmail.com</li>";
echo "<li>ipnforum@gmail.com</li>";
echo "<li>ipnfoundation.tlc.02@gmail.com</li>";
echo "<li>ipn.foundation.tlc.01@gmail.com</li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If PHPMailer is not available, install it for better email delivery</li>";
echo "<li>Test with a real email address</li>";
echo "<li>Check server logs for any email errors</li>";
echo "<li>Verify email delivery in spam/junk folders</li>";
echo "</ol>";
?> 