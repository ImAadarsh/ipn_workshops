<?php
// Include Composer autoloader for PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Email Helper Functions for Google SMTP
// Supports both PHPMailer (if available) and PHP's built-in mail function

class EmailHelper {
    private $current_email_index = 0;
    private $email_configs = [
        [
            'username' => 'ipnacademy2024@gmail.com',
            'password' => 'qplqwdflqyyntbho'
        ],
        [
            'username' => 'ipnacademy2023@gmail.com',
            'password' => 'aopkalikqhzmvpuq'
        ],
        [
            'username' => 'ipnforum@gmail.com',
            'password' => 'lbmsmqkmuziwxobc'
        ],
        [
            'username' => 'ipnfoundation.tlc.02@gmail.com',
            'password' => 'eppkbnhcovaowdfp'
        ],
        [
            'username' => 'ipn.foundation.tlc.01@gmail.com',
            'password' => 'bxgzrsypyaqyykem'
        ]
    ];

    public function sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data) {
        try {
            error_log("EmailHelper: Starting email sending process");
            error_log("EmailHelper: User email: " . ($user_data['email'] ?? 'NOT SET'));
            error_log("EmailHelper: Payment ID: " . ($payment_data['payment_id'] ?? 'NOT SET'));
            error_log("EmailHelper: Workshops count: " . count($workshops_data));
            
            // Validate email address
            if (empty($user_data['email']) || !filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
                error_log("EmailHelper: Invalid email address: " . ($user_data['email'] ?? 'EMPTY'));
                return false;
            }
            
            // Validate required data
            if (empty($payment_data['payment_id']) || empty($workshops_data)) {
                error_log("EmailHelper: Missing required data - payment_id: " . ($payment_data['payment_id'] ?? 'EMPTY') . ", workshops count: " . count($workshops_data));
                return false;
            }
            
            // Try PHPMailer first if available
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log("EmailHelper: Using PHPMailer");
                return $this->sendWithPHPMailerWithFallback($user_data, $payment_data, $workshops_data);
            } else {
                error_log("EmailHelper: Using PHP built-in mail function");
                // Fallback to PHP's built-in mail function
                return $this->sendWithBuiltInMailWithFallback($user_data, $payment_data, $workshops_data);
            }
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            error_log("Email sending error trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function sendWorkshopReminderEmail($user_data, $workshop_data, $joining_link) {
        try {
            error_log("EmailHelper: Starting workshop reminder email sending process");
            error_log("EmailHelper: User email: " . ($user_data['email'] ?? 'NOT SET'));
            error_log("EmailHelper: Workshop: " . ($workshop_data['name'] ?? 'NOT SET'));
            error_log("EmailHelper: Joining link: " . ($joining_link ? 'PROVIDED' : 'NOT PROVIDED'));
            
            // Validate email address
            if (empty($user_data['email']) || !filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
                error_log("EmailHelper: Invalid email address: " . ($user_data['email'] ?? 'EMPTY'));
                return false;
            }
            
            // Validate required data
            if (empty($workshop_data['name']) || empty($joining_link)) {
                error_log("EmailHelper: Missing required data - workshop name: " . ($workshop_data['name'] ?? 'EMPTY') . ", joining link: " . ($joining_link ? 'PROVIDED' : 'NOT PROVIDED'));
                return false;
            }
            
            // Try PHPMailer first if available
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log("EmailHelper: Using PHPMailer for workshop reminder");
                return $this->sendWorkshopReminderWithPHPMailerWithFallback($user_data, $workshop_data, $joining_link);
            } else {
                error_log("EmailHelper: Using PHP built-in mail function for workshop reminder");
                // Fallback to PHP's built-in mail function
                return $this->sendWorkshopReminderWithBuiltInMailWithFallback($user_data, $workshop_data, $joining_link);
            }
        } catch (Exception $e) {
            error_log("Workshop reminder email sending error: " . $e->getMessage());
            error_log("Workshop reminder email sending error trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function getLastUsedEmail() {
        return $this->email_configs[$this->current_email_index]['username'];
    }

    private function sendWithPHPMailerWithFallback($user_data, $payment_data, $workshops_data) {
        // Try each email configuration
        for ($i = 0; $i < count($this->email_configs); $i++) {
            $this->current_email_index = $i;
            error_log("PHPMailer: Trying email config index $i");
            
            $result = $this->sendWithPHPMailer($user_data, $payment_data, $workshops_data);
            if ($result) {
                error_log("PHPMailer: Email sent successfully with config index $i");
                return true;
            } else {
                error_log("PHPMailer: Failed with config index $i, trying next...");
            }
        }
        
        error_log("PHPMailer: All email configurations failed");
        return false;
    }

    private function sendWithPHPMailer($user_data, $payment_data, $workshops_data) {
        try {
            error_log("PHPMailer: Starting PHPMailer email sending");
            
            // PHPMailer implementation
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Get current email config
            $config = $this->email_configs[$this->current_email_index];
            error_log("PHPMailer: Using email config index " . $this->current_email_index . " with username: " . $config['username']);
            
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];
            $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = 587;
            
            // Enable debugging
            $mailer->SMTPDebug = 2; // Enable verbose debug output
            $mailer->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
            
            error_log("PHPMailer: Setting up recipients");
            // Recipients
            $mailer->setFrom($config['username'], 'IPN Academy');
            $mailer->addAddress($user_data['email'], $user_data['name']);
            
            error_log("PHPMailer: Setting up content");
            // Content
            $mailer->isHTML(true);
            $mailer->Subject = 'Payment Confirmation - IPN Academy Workshop Enrollment';
            $mailer->Body = $this->generatePaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
            
            error_log("PHPMailer: Attempting to send email");
            $result = $mailer->send();
            error_log("PHPMailer: Email send result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            return $result;
        } catch (Exception $e) {
            error_log("PHPMailer Exception: " . $e->getMessage());
            error_log("PHPMailer Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function sendWithBuiltInMailWithFallback($user_data, $payment_data, $workshops_data) {
        // Try each email configuration
        for ($i = 0; $i < count($this->email_configs); $i++) {
            $this->current_email_index = $i;
            error_log("BuiltInMail: Trying email config index $i");
            
            $result = $this->sendWithBuiltInMail($user_data, $payment_data, $workshops_data);
            if ($result) {
                error_log("BuiltInMail: Email sent successfully with config index $i");
                return true;
            } else {
                error_log("BuiltInMail: Failed with config index $i, trying next...");
            }
        }
        
        error_log("BuiltInMail: All email configurations failed");
        return false;
    }

    private function sendWithBuiltInMail($user_data, $payment_data, $workshops_data) {
        try {
            error_log("BuiltInMail: Starting built-in mail function");
            
            // PHP's built-in mail function implementation
            $config = $this->email_configs[$this->current_email_index];
            error_log("BuiltInMail: Using email config index " . $this->current_email_index . " with username: " . $config['username']);
            
            $to = $user_data['email'];
            $subject = 'Payment Confirmation - IPN Academy Workshop Enrollment';
            $message = $this->generatePaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
            
            error_log("BuiltInMail: To: $to");
            error_log("BuiltInMail: Subject: $subject");
            error_log("BuiltInMail: Message length: " . strlen($message));
            
            // Email headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: IPN Academy <' . $config['username'] . '>',
                'Reply-To: ' . $config['username'],
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $headers_string = implode("\r\n", $headers);
            error_log("BuiltInMail: Headers: $headers_string");
            
            $result = mail($to, $subject, $message, $headers_string);
            error_log("BuiltInMail: Email send result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            return $result;
        } catch (Exception $e) {
            error_log("BuiltInMail Exception: " . $e->getMessage());
            error_log("BuiltInMail Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function sendWorkshopReminderWithPHPMailerWithFallback($user_data, $workshop_data, $joining_link) {
        // Try each email configuration
        for ($i = 0; $i < count($this->email_configs); $i++) {
            $this->current_email_index = $i;
            error_log("PHPMailer Workshop Reminder: Trying email config index $i");
            
            $result = $this->sendWorkshopReminderWithPHPMailer($user_data, $workshop_data, $joining_link);
            if ($result) {
                error_log("PHPMailer Workshop Reminder: Email sent successfully with config index $i");
                return true;
            } else {
                error_log("PHPMailer Workshop Reminder: Failed with config index $i, trying next...");
            }
        }
        
        error_log("PHPMailer Workshop Reminder: All email configurations failed");
        return false;
    }

    private function sendWorkshopReminderWithPHPMailer($user_data, $workshop_data, $joining_link) {
        try {
            error_log("PHPMailer Workshop Reminder: Starting PHPMailer email sending");
            
            // PHPMailer implementation
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Get current email config
            $config = $this->email_configs[$this->current_email_index];
            error_log("PHPMailer Workshop Reminder: Using email config index " . $this->current_email_index . " with username: " . $config['username']);
            
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];
            $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = 587;
            
            // Enable debugging
            $mailer->SMTPDebug = 2; // Enable verbose debug output
            $mailer->Debugoutput = function($str, $level) {
                error_log("PHPMailer Workshop Reminder Debug: $str");
            };
            
            error_log("PHPMailer Workshop Reminder: Setting up recipients");
            // Recipients
            $mailer->setFrom($config['username'], 'IPN Academy');
            $mailer->addAddress($user_data['email'], $user_data['name']);
            
            error_log("PHPMailer Workshop Reminder: Setting up content");
            // Content
            $mailer->isHTML(true);
            $mailer->Subject = 'IPN Academy Workshop Reminder | Details Of Your Upcoming Workshop';
            $mailer->Body = $this->generateWorkshopReminderEmail($user_data, $workshop_data, $joining_link);
            
            error_log("PHPMailer Workshop Reminder: Attempting to send email");
            $result = $mailer->send();
            error_log("PHPMailer Workshop Reminder: Email send result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            return $result;
        } catch (Exception $e) {
            error_log("PHPMailer Workshop Reminder Exception: " . $e->getMessage());
            error_log("PHPMailer Workshop Reminder Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function sendWorkshopReminderWithBuiltInMailWithFallback($user_data, $workshop_data, $joining_link) {
        // Try each email configuration
        for ($i = 0; $i < count($this->email_configs); $i++) {
            $this->current_email_index = $i;
            error_log("BuiltInMail Workshop Reminder: Trying email config index $i");
            
            $result = $this->sendWorkshopReminderWithBuiltInMail($user_data, $workshop_data, $joining_link);
            if ($result) {
                error_log("BuiltInMail Workshop Reminder: Email sent successfully with config index $i");
                return true;
            } else {
                error_log("BuiltInMail Workshop Reminder: Failed with config index $i, trying next...");
            }
        }
        
        error_log("BuiltInMail Workshop Reminder: All email configurations failed");
        return false;
    }

    private function sendWorkshopReminderWithBuiltInMail($user_data, $workshop_data, $joining_link) {
        try {
            error_log("BuiltInMail Workshop Reminder: Starting built-in mail function");
            
            // PHP's built-in mail function implementation
            $config = $this->email_configs[$this->current_email_index];
            error_log("BuiltInMail Workshop Reminder: Using email config index " . $this->current_email_index . " with username: " . $config['username']);
            
            $to = $user_data['email'];
            $subject = 'IPN Academy Workshop Reminder | Details Of Your Upcoming Workshop';
            $message = $this->generateWorkshopReminderEmail($user_data, $workshop_data, $joining_link);
            
            error_log("BuiltInMail Workshop Reminder: To: $to");
            error_log("BuiltInMail Workshop Reminder: Subject: $subject");
            error_log("BuiltInMail Workshop Reminder: Message length: " . strlen($message));
            
            // Email headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: IPN Academy <' . $config['username'] . '>',
                'Reply-To: ' . $config['username'],
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $headers_string = implode("\r\n", $headers);
            error_log("BuiltInMail Workshop Reminder: Headers: $headers_string");
            
            $result = mail($to, $subject, $message, $headers_string);
            error_log("BuiltInMail Workshop Reminder: Email send result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            return $result;
        } catch (Exception $e) {
            error_log("BuiltInMail Workshop Reminder Exception: " . $e->getMessage());
            error_log("BuiltInMail Workshop Reminder Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function generatePaymentConfirmationEmail($user_data, $payment_data, $workshops_data) {
        $total_original_price = 0;
        $total_paid_amount = $payment_data['amount'];
        
        // Calculate original prices
        foreach ($workshops_data as $workshop) {
            $total_original_price += $workshop['price'];
        }
        
        $savings = $total_original_price - $total_paid_amount;
        $savings_percentage = $total_original_price > 0 ? round(($savings / $total_original_price) * 100, 2) : 0;

        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Confirmation - IPN Academy</title>
            <style>
                /* Reset and base styles */
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #2d3748;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px 0;
                    min-height: 100vh;
                }
                
                /* Email container */
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                }
                
                /* Header section */
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: "";
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
                
                .logo {
                    font-size: 32px;
                    font-weight: 700;
                    margin-bottom: 15px;
                    position: relative;
                    z-index: 1;
                }
                
                .success-icon {
                    font-size: 48px;
                    margin-bottom: 20px;
                    position: relative;
                    z-index: 1;
                    animation: bounce 2s ease-in-out infinite;
                }
                
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-10px); }
                    60% { transform: translateY(-5px); }
                }
                
                .header h1 {
                    font-size: 28px;
                    font-weight: 700;
                    margin-bottom: 10px;
                    position: relative;
                    z-index: 1;
                }
                
                .header p {
                    font-size: 16px;
                    opacity: 0.9;
                    position: relative;
                    z-index: 1;
                }
                
                /* Content section */
                .content {
                    padding: 40px 30px;
                }
                
                .greeting {
                    font-size: 20px;
                    color: #2d3748;
                    margin-bottom: 25px;
                    font-weight: 600;
                }
                
                .greeting strong {
                    color: #667eea;
                }
                
                /* Payment details card */
                .payment-card {
                    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
                    border-radius: 16px;
                    padding: 25px;
                    margin: 25px 0;
                    border: 1px solid #e2e8f0;
                }
                
                .payment-card h3 {
                    color: #667eea;
                    font-size: 20px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .payment-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 12px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                
                .payment-row:last-child {
                    border-bottom: none;
                    font-weight: 700;
                    font-size: 18px;
                    color: #667eea;
                }
                
                .payment-row .label {
                    color: #4a5568;
                    font-weight: 500;
                }
                
                .payment-row .value {
                    color: #2d3748;
                    font-weight: 600;
                }
                
                .status-badge {
                    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                    color: white;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                }
                
                /* Savings highlight */
                .savings-highlight {
                    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                    color: white;
                    padding: 25px;
                    border-radius: 16px;
                    text-align: center;
                    margin: 25px 0;
                    position: relative;
                    overflow: hidden;
                }
                
                .savings-highlight::before {
                    content: "";
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                    animation: float 8s ease-in-out infinite;
                }
                
                .savings-highlight h3 {
                    font-size: 24px;
                    margin-bottom: 10px;
                    position: relative;
                    z-index: 1;
                }
                
                .savings-highlight p {
                    margin: 5px 0;
                    position: relative;
                    z-index: 1;
                }
                
                /* Workshop list */
                .workshop-section h3 {
                    color: #667eea;
                    font-size: 20px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .workshop-item {
                    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
                    border-left: 4px solid #667eea;
                    padding: 20px;
                    margin: 15px 0;
                    border-radius: 0 12px 12px 0;
                    transition: all 0.3s ease;
                }
                
                .workshop-item:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
                }
                
                .workshop-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: #2d3748;
                    margin-bottom: 10px;
                }
                
                .workshop-details {
                    color: #4a5568;
                    font-size: 14px;
                    line-height: 1.8;
                }
                
                .workshop-details strong {
                    color: #2d3748;
                }
                
                /* Contact info */
                .contact-info {
                    background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
                    border-radius: 16px;
                    padding: 25px;
                    margin: 25px 0;
                    border: 1px solid #bee3f8;
                }
                
                .contact-info h4 {
                    color: #667eea;
                    font-size: 18px;
                    margin-bottom: 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .contact-info p {
                    margin: 8px 0;
                    color: #2d3748;
                }
                
                .contact-info strong {
                    color: #4a5568;
                }
                
                /* Buttons */
                .button-container {
                    text-align: center;
                    margin: 35px 0;
                }
                
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 25px;
                    margin: 10px 8px;
                    font-weight: 600;
                    font-size: 16px;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                }
                
                .button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
                }
                
                /* Footer */
                .footer {
                    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
                    padding: 30px;
                    text-align: center;
                    border-top: 1px solid #e2e8f0;
                }
                
                .footer p {
                    color: #4a5568;
                    font-size: 14px;
                    margin: 5px 0;
                }
                
                /* Mobile responsiveness */
                @media only screen and (max-width: 600px) {
                    body {
                        padding: 10px 0;
                    }
                    
                    .email-wrapper {
                        margin: 0 10px;
                        border-radius: 15px;
                    }
                    
                    .header {
                        padding: 30px 20px;
                    }
                    
                    .logo {
                        font-size: 28px;
                    }
                    
                    .success-icon {
                        font-size: 40px;
                    }
                    
                    .header h1 {
                        font-size: 24px;
                    }
                    
                    .content {
                        padding: 30px 20px;
                    }
                    
                    .greeting {
                        font-size: 18px;
                    }
                    
                    .payment-card {
                        padding: 20px;
                    }
                    
                    .payment-row {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 5px;
                    }
                    
                    .workshop-item {
                        padding: 15px;
                    }
                    
                    .workshop-title {
                        font-size: 16px;
                    }
                    
                    .button {
                        display: block;
                        margin: 10px 0;
                        padding: 12px 20px;
                        font-size: 14px;
                    }
                    
                    .savings-highlight {
                        padding: 20px;
                    }
                    
                    .savings-highlight h3 {
                        font-size: 20px;
                    }
                }
                
                /* Dark mode support */
                @media (prefers-color-scheme: dark) {
                    .email-wrapper {
                        background: #1a202c;
                        color: #e2e8f0;
                    }
                    
                    .payment-card,
                    .workshop-item,
                    .contact-info,
                    .footer {
                        background: #2d3748;
                        border-color: #4a5568;
                    }
                    
                    .greeting,
                    .workshop-title {
                        color: #e2e8f0;
                    }
                    
                    .payment-row .label,
                    .workshop-details {
                        color: #a0aec0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header">
                    <div class="success-icon">🎉</div>
                    <div class="logo">IPN Academy</div>
                    <h1>Payment Successful!</h1>
                    <p>Your workshop enrollment has been confirmed</p>
                </div>
                
                <div class="content">
                    <div class="greeting">
                        Dear <strong>' . htmlspecialchars($user_data['name']) . '</strong>,
                    </div>
                    
                    <p style="font-size: 16px; color: #4a5568; margin-bottom: 25px;">
                        Thank you for your payment! Your workshop enrollment has been confirmed successfully. 
                        We\'re excited to have you join our learning community.
                    </p>
                    
                    <div class="payment-card">
                        <h3>💳 Payment Details</h3>
                        <div class="payment-row">
                            <span class="label">Payment ID:</span>
                            <span class="value"><strong>' . htmlspecialchars($payment_data['payment_id']) . '</strong></span>
                        </div>
                        <div class="payment-row">
                            <span class="label">Payment Date:</span>
                            <span class="value">' . date('d M Y, h:i A', strtotime($payment_data['created_at'])) . '</span>
                        </div>
                        <div class="payment-row">
                            <span class="label">Payment Method:</span>
                            <span class="value">Instamojo</span>
                        </div>
                        <div class="payment-row">
                            <span class="label">Status:</span>
                            <span class="status-badge">✓ Completed</span>
                        </div>
                    </div>';

        if ($savings > 0) {
            $html .= '
                    <div class="savings-highlight">
                        <h3>🎉 You Saved ₹' . number_format($savings, 2) . '!</h3>
                        <p>Original Price: ₹' . number_format($total_original_price, 2) . '</p>
                        <p>You Paid: ₹' . number_format($total_paid_amount, 2) . '</p>
                        <p style="font-size: 16px; margin-top: 10px;">That\'s a <strong>' . $savings_percentage . '% discount!</strong></p>
                    </div>';
        }

        $html .= '
                    <div class="workshop-section">
                        <h3>📚 Enrolled Workshops</h3>';

        foreach ($workshops_data as $workshop) {
            $html .= '
                        <div class="workshop-item">
                            <div class="workshop-title">' . htmlspecialchars($workshop['name']) . '</div>
                            <div class="workshop-details">
                                <strong>📅 Date:</strong> ' . date('d M Y, h:i A', strtotime($workshop['start_date'])) . '<br>
                                <strong>👨‍🏫 Trainer:</strong> ' . htmlspecialchars($workshop['trainer_name']) . '<br>
                                <strong>⏱️ Duration:</strong> ' . htmlspecialchars($workshop['duration']) . ' hours<br>
                                <strong>💰 Original Price:</strong> ₹' . number_format($workshop['price'], 2) . '
                            </div>
                        </div>';
        }

        $html .= '
                    </div>
                    
                    <div class="contact-info">
                        <h4>📞 Need Help?</h4>
                        <p>If you have any questions about your enrollment, please contact us:</p>
                        <p><strong>📧 Email:</strong> ipnacademy@ipnindia.in</p>
                        <p><strong>📱 Phone:</strong> +91 7697001231, +91 8400700199</p>
                    </div>
                    
                    <div class="button-container">
                        <a href="https://ipnacademy.in" class="button">Visit IPN Academy</a>
                        <a href="https://app.ipnacademy.in/" class="button">My Dashboard</a>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Thank you for choosing IPN Academy!</p>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; ' . date('Y') . ' IPN Academy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function generateWorkshopReminderEmail($user_data, $workshop_data, $joining_link) {
        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>IPN Academy Workshop Reminder</title>
            <style>
                /* Reset and base styles */
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #2d3748;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px 0;
                    min-height: 100vh;
                }
                
                /* Email container */
                .email-wrapper {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #ffffff;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                }
                
                /* Header section */
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .header::before {
                    content: "";
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
                
                .logo {
                    font-size: 32px;
                    font-weight: 700;
                    margin-bottom: 15px;
                    position: relative;
                    z-index: 1;
                }
                
                .logo img {
                    max-width: 200px;
                    height: auto;
                }
                
                
                .header h1 {
                    font-size: 28px;
                    font-weight: 700;
                    margin-bottom: 10px;
                    position: relative;
                    z-index: 1;
                }
                
                .header p {
                    font-size: 16px;
                    opacity: 0.9;
                    position: relative;
                    z-index: 1;
                }
                
                /* Content section */
                .content {
                    padding: 40px 30px;
                }
                
                .greeting {
                    font-size: 20px;
                    color: #2d3748;
                    margin-bottom: 25px;
                    font-weight: 600;
                }
                
                .greeting strong {
                    color: #667eea;
                }
                
                /* Workshop details card */
                .workshop-card {
                    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
                    border-radius: 16px;
                    padding: 25px;
                    margin: 25px 0;
                    border: 1px solid #e2e8f0;
                }
                
                .workshop-card h3 {
                    color: #000000;
                    font-size: 20px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .workshop-detail-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 12px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                
                .workshop-detail-row:last-child {
                    border-bottom: none;
                }
                
                .workshop-detail-row .label {
                    color: #4a5568;
                    font-weight: 500;
                }
                
                .workshop-detail-row .value {
                    color: #2d3748;
                    font-weight: 600;
                }
                
                /* Join button */
                .join-button-container {
                    text-align: center;
                    margin: 35px 0;
                }
                
                .join-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                    color: white;
                    padding: 20px 40px;
                    text-decoration: none;
                    border-radius: 25px;
                    font-weight: 700;
                    font-size: 18px;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                .join-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
                }
                
                /* Certificate instructions */
                .certificate-section {
                    background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
                    border-radius: 16px;
                    padding: 25px;
                    margin: 25px 0;
                    border: 1px solid #bee3f8;
                }
                
                .certificate-section h4 {
                    color: #000000;
                    font-size: 18px;
                    margin-bottom: 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .certificate-section ol {
                    color: #2d3748;
                    padding-left: 20px;
                }
                
                .certificate-section li {
                    margin: 8px 0;
                }
                
                .certificate-section a {
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 600;
                }
                
                .certificate-section a:hover {
                    text-decoration: underline;
                }
                
                /* Disclaimer */
                .disclaimer {
                    background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
                    border-radius: 16px;
                    padding: 25px;
                    margin: 25px 0;
                    border: 1px solid #fed7aa;
                }
                
                .disclaimer h4 {
                    color: #d69e2e;
                    font-size: 16px;
                    margin-bottom: 10px;
                }
                
                .disclaimer p {
                    color: #744210;
                    font-size: 14px;
                    line-height: 1.6;
                    text-align: justify;
                }
                
                /* Footer */
                .footer {
                    background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
                    padding: 30px;
                    text-align: center;
                    border-top: 1px solid #e2e8f0;
                }
                
                .footer p {
                    color: #4a5568;
                    font-size: 14px;
                    margin: 5px 0;
                }
                
                .footer .unsubscribe {
                    color: #a0aec0;
                    font-size: 12px;
                    margin-top: 15px;
                }
                
                /* Dark mode support */
                @media (prefers-color-scheme: dark) {
                    .email-wrapper {
                        background: #1a202c !important;
                        color: #e2e8f0 !important;
                    }
                    
                    .header {
                        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%) !important;
                    }
                    
                    .content {
                        background: #1a202c !important;
                        color: #e2e8f0 !important;
                    }
                    
                    .greeting {
                        color: #e2e8f0 !important;
                    }
                    
                    .greeting strong {
                        color: #90cdf4 !important;
                    }
                    
                    .workshop-card {
                        background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%) !important;
                        border-color: #4a5568 !important;
                    }
                    
                    .workshop-card h3 {
                        color: #ffffff !important;
                    }
                    
                    .workshop-detail-row .label {
                        color: #a0aec0 !important;
                    }
                    
                    .workshop-detail-row .value {
                        color: #e2e8f0 !important;
                    }
                    
                    .certificate-section {
                        background: linear-gradient(135deg, #2a4365 0%, #2c5282 100%) !important;
                        border-color: #2c5282 !important;
                    }
                    
                    .certificate-section h4 {
                        color: #ffffff !important;
                    }
                    
                    .certificate-section ol {
                        color: #e2e8f0 !important;
                    }
                    
                    .certificate-section a {
                        color: #90cdf4 !important;
                    }
                    
                    .disclaimer {
                        background: linear-gradient(135deg, #744210 0%, #975a16 100%) !important;
                        border-color: #975a16 !important;
                    }
                    
                    .disclaimer h4 {
                        color: #fbd38d !important;
                    }
                    
                    .disclaimer p {
                        color: #f7e98e !important;
                    }
                    
                    .footer {
                        background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%) !important;
                        border-color: #4a5568 !important;
                    }
                    
                    .footer p {
                        color: #a0aec0 !important;
                    }
                    
                    .footer .unsubscribe {
                        color: #718096 !important;
                    }
                    
                    /* Ensure text is visible in dark mode */
                    p, div, span, li {
                        color: #e2e8f0 !important;
                    }
                    
                    /* Override any white text that might be invisible */
                    .text-muted {
                        color: #a0aec0 !important;
                    }
                }
                
                /* Mobile responsiveness */
                @media only screen and (max-width: 600px) {
                    body {
                        padding: 10px 0;
                    }
                    
                    .email-wrapper {
                        margin: 0 10px;
                        border-radius: 15px;
                    }
                    
                    .header {
                        padding: 30px 20px;
                    }
                    
                    .logo {
                        font-size: 28px;
                    }
                    
                    
                    .header h1 {
                        font-size: 24px;
                    }
                    
                    .content {
                        padding: 30px 20px;
                    }
                    
                    .greeting {
                        font-size: 18px;
                    }
                    
                    .workshop-card {
                        padding: 20px;
                    }
                    
                    .workshop-detail-row {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 5px;
                    }
                    
                    .join-button {
                        padding: 15px 30px;
                        font-size: 16px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="header">
                    <div class="logo">
                        <img src="https://ipnacademy.in/new_assets/img/ipn/ipn.png" alt="IPN Academy" style="max-width: 200px; height: auto;">
                    </div>
                    <h1>Get Ready For Your Upcoming Workshop</h1>
                    <p>Please Join the workshop from the below link.</p>
                </div>
                
                <div class="content">
                    <div class="greeting">
                        Greetings from IPN Leadership Academy !<br>
                        Hello <strong>' . htmlspecialchars($user_data['name']) . '</strong>
                    </div>
                    
                    <p style="font-size: 16px; color: #4a5568; margin-bottom: 25px;">
                        In case of any queries, do contact the undersigned.
                    </p>
                    
                    <div class="workshop-card">
                        <h3>📚 Workshop Details</h3>
                        <div class="workshop-detail-row">
                            <span class="label">Workshop Name:</span>
                            <span class="value">' . htmlspecialchars($workshop_data['name']) . '</span>
                        </div>
                        <div class="workshop-detail-row">
                            <span class="label">Trainer Name:</span>
                            <span class="value">' . htmlspecialchars($workshop_data['trainer_name']) . '</span>
                        </div>
                        <div class="workshop-detail-row">
                            <span class="label">Time:</span>
                            <span class="value">' . date('D | d M Y | h:i A', strtotime($workshop_data['start_date'])) . '</span>
                        </div>
                        <div class="workshop-detail-row">
                            <span class="label">Duration:</span>
                            <span class="value">' . htmlspecialchars($workshop_data['duration']) . ' minutes</span>
                        </div>
                        <div class="workshop-detail-row">
                            <span class="label">Your Registered Email ID:</span>
                            <span class="value">' . htmlspecialchars($user_data['email']) . '</span>
                        </div>
                        <div class="workshop-detail-row">
                            <span class="label">Meeting ID:</span>
                            <span class="value">' . htmlspecialchars($workshop_data['meeting_id']) . '</span>
                        </div>
                        <div class="workshop-detail-row">
                            <span class="label">PassCode:</span>
                            <span class="value">' . htmlspecialchars($workshop_data['passcode']) . '</span>
                        </div>
                    </div>
                    
                    <div class="join-button-container">
                        <a href="' . htmlspecialchars($joining_link) . '" class="join-button">JOIN NOW</a>
                    </div>
                    
                    <div class="certificate-section">
                        <h4>📜 Steps To Download Certificate:-</h4>
                        <ol>
                            <li>Login to IPN Academy <a href="https://ipnacademy.in/user/index.php" target="_blank">Click Here</a>.</li>
                            <li>Click On The Workshop Tab.</li>
                            <li>On The Selected Workshop Click On View Option.</li>
                            <li>After Posting The Review, Download The Certificate.</li>
                        </ol>
                    </div>
                    
                    <div class="disclaimer">
                        <h4>⚠️ Disclaimer</h4>
                        <p>The information contained in this communication from the sender is confidential. It is intended solely for use by the recipient and others authorized to receive it. If you are not the recipient, you are hereby notified that any disclosure, copying, distribution or taking action in relation to the contents of this information is strictly prohibited and may be unlawful.</p>
                    </div>
                    
                    <p style="text-align: center; font-size: 16px; color: #4a5568; margin: 30px 0;">
                        <strong>Best Regards<br>Team IPN Academy</strong>
                    </p>
                </div>
                
                <div class="footer">
                    <p>© ' . date('Y') . ' Copyright IPN Academy. All Rights Reserved.</p>
                    <p class="unsubscribe">Unsubscribe</p>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }
}

// Function to send payment confirmation email
function sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data) {
    try {
        $email_helper = new EmailHelper();
        return $email_helper->sendPaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
    } catch (Exception $e) {
        error_log("Payment confirmation email error: " . $e->getMessage());
        return false;
    }
}

// Function to send workshop reminder email
function sendWorkshopReminderEmail($user_data, $workshop_data, $joining_link) {
    try {
        $email_helper = new EmailHelper();
        $result = $email_helper->sendWorkshopReminderEmail($user_data, $workshop_data, $joining_link);
        
        // Store the email helper instance globally so we can get the last used email
        $GLOBALS['last_email_helper'] = $email_helper;
        
        return $result;
    } catch (Exception $e) {
        error_log("Workshop reminder email error: " . $e->getMessage());
        return false;
    }
}

// Function to get the last used email
function getLastUsedEmail() {
    if (isset($GLOBALS['last_email_helper'])) {
        return $GLOBALS['last_email_helper']->getLastUsedEmail();
    }
    return 'ipnacademy2023@gmail.com'; // fallback
}
?> 