<?php
// Comprehensive Email Debugging Script
include 'config/show_errors.php';

echo "<h1>Email Debugging Script</h1>";

// Step 1: Check if autoloader exists
echo "<h2>Step 1: Checking Autoloader</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "<p style='color: green;'>✅ vendor/autoload.php exists</p>";
    require_once 'vendor/autoload.php';
} else {
    echo "<p style='color: red;'>❌ vendor/autoload.php not found</p>";
}

// Step 2: Check PHPMailer availability
echo "<h2>Step 2: Checking PHPMailer</h2>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer class is available</p>";
    
    // Test PHPMailer instantiation
    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "<p style='color: green;'>✅ PHPMailer instantiation successful</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ PHPMailer instantiation failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ PHPMailer class not found</p>";
}

// Step 3: Check email helper
echo "<h2>Step 3: Checking Email Helper</h2>";
if (file_exists('config/email_helper.php')) {
    echo "<p style='color: green;'>✅ email_helper.php exists</p>";
    include_once 'config/email_helper.php';
    
    if (function_exists('sendPaymentConfirmationEmail')) {
        echo "<p style='color: green;'>✅ sendPaymentConfirmationEmail function is available</p>";
    } else {
        echo "<p style='color: red;'>❌ sendPaymentConfirmationEmail function not found</p>";
    }
} else {
    echo "<p style='color: red;'>❌ email_helper.php not found</p>";
}

// Step 4: Test email configuration
echo "<h2>Step 4: Testing Email Configuration</h2>";
if (class_exists('EmailHelper')) {
    $email_helper = new EmailHelper();
    echo "<p style='color: green;'>✅ EmailHelper class instantiated</p>";
    
    // Test email configs
    $reflection = new ReflectionClass($email_helper);
    $configs_property = $reflection->getProperty('email_configs');
    $configs_property->setAccessible(true);
    $configs = $configs_property->getValue($email_helper);
    
    echo "<p>Email configurations found: " . count($configs) . "</p>";
    foreach ($configs as $index => $config) {
        echo "<p>Config $index: " . $config['username'] . " (password: " . substr($config['password'], 0, 4) . "...)</p>";
    }
} else {
    echo "<p style='color: red;'>❌ EmailHelper class not found</p>";
}

// Step 5: Test with sample data
echo "<h2>Step 5: Testing with Sample Data</h2>";
$test_user_data = [
    'name' => 'Test User',
    'email' => 'aadarshkavita@gmail.com' // Change this to a real email for testing
];

$test_payment_data = [
    'payment_id' => 'TEST_PAYMENT_123',
    'amount' => 1000,
    'created_at' => date('Y-m-d H:i:s')
];

$test_workshops_data = [
    [
        'name' => 'Test Workshop 1',
        'start_date' => '2024-01-15 10:00:00',
        'trainer_name' => 'Test Trainer',
        'duration' => '2 hours',
        'price' => 500
    ]
];

echo "<p>Test data prepared:</p>";
echo "<ul>";
echo "<li>User: " . $test_user_data['name'] . " (" . $test_user_data['email'] . ")</li>";
echo "<li>Payment ID: " . $test_payment_data['payment_id'] . "</li>";
echo "<li>Workshops: " . count($test_workshops_data) . "</li>";
echo "</ul>";

// Step 6: Test email sending
echo "<h2>Step 6: Testing Email Sending</h2>";
if (function_exists('sendPaymentConfirmationEmail')) {
    echo "<p>Attempting to send test email...</p>";
    
    // Enable error reporting for this test
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $result = sendPaymentConfirmationEmail($test_user_data, $test_payment_data, $test_workshops_data);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Email sending failed</p>";
    }
} else {
    echo "<p style='color: red;'>❌ sendPaymentConfirmationEmail function not available</p>";
}

// Step 7: Check error logs
echo "<h2>Step 7: Recent Error Logs</h2>";
$error_log_file = ini_get('error_log');
if ($error_log_file && file_exists($error_log_file)) {
    $log_content = file_get_contents($error_log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -30); // Last 30 lines
    
    echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
    echo "<pre style='margin: 0; font-size: 12px;'>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'Email') !== false || 
            strpos($line, 'PHPMailer') !== false || 
            strpos($line, 'Mail') !== false ||
            strpos($line, 'SMTP') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
    echo "</div>";
} else {
    echo "<p>Error log file not found or not accessible.</p>";
    echo "<p>Error log path: " . ($error_log_file ?: 'Not set') . "</p>";
}

// Step 8: System Information
echo "<h2>Step 8: System Information</h2>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "<li><strong>Mail Function:</strong> " . (function_exists('mail') ? 'Available' : 'Not Available') . "</li>";
echo "<li><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? 'Loaded' : 'Not Loaded') . "</li>";
echo "<li><strong>cURL:</strong> " . (extension_loaded('curl') ? 'Loaded' : 'Not Loaded') . "</li>";
echo "<li><strong>Error Reporting:</strong> " . error_reporting() . "</li>";
echo "<li><strong>Display Errors:</strong> " . (ini_get('display_errors') ? 'On' : 'Off') . "</li>";
echo "<li><strong>Log Errors:</strong> " . (ini_get('log_errors') ? 'On' : 'Off') . "</li>";
echo "<li><strong>Error Log:</strong> " . (ini_get('error_log') ?: 'Not set') . "</li>";
echo "</ul>";

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Change the test email address to a real one</li>";
echo "<li>Check the error logs for specific error messages</li>";
echo "<li>Verify SMTP credentials are correct</li>";
echo "<li>Test with a different email provider if needed</li>";
echo "</ol>";

echo "<p><a href='test_email.php'>Run Simple Email Test</a></p>";
echo "<p><a href='instamojo_success.php?payment_id=TEST_PAYMENT_123&payment_request_id=TEST_REQUEST_123'>Test Success Page</a></p>";
?> 