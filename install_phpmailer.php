<?php
// PHPMailer Installation Script
// Run this script to install PHPMailer via Composer

echo "Installing PHPMailer...\n";

// Check if composer is available
if (!file_exists('composer.json')) {
    // Create composer.json
    $composer_json = [
        'require' => [
            'phpmailer/phpmailer' => '^6.8'
        ]
    ];
    
    file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT));
    echo "Created composer.json\n";
}

// Install PHPMailer
$command = 'composer install';
echo "Running: $command\n";
system($command, $return_code);

if ($return_code === 0) {
    echo "PHPMailer installed successfully!\n";
    echo "You can now use the email functionality.\n";
} else {
    echo "Failed to install PHPMailer. Please install it manually:\n";
    echo "1. Run: composer require phpmailer/phpmailer\n";
    echo "2. Or download PHPMailer manually and place it in the vendor directory\n";
}

// Alternative manual installation instructions
echo "\nAlternative manual installation:\n";
echo "1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer\n";
echo "2. Extract and place the 'src' folder in 'vendor/phpmailer/phpmailer/src'\n";
echo "3. Create 'vendor/autoload.php' with the following content:\n";
echo "<?php\n";
echo "require_once __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';\n";
echo "require_once __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';\n";
echo "require_once __DIR__ . '/phpmailer/phpmailer/src/Exception.php';\n";
?> 