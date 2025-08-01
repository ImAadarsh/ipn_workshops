<?php
// Email Helper Functions for Google SMTP
// Supports both PHPMailer (if available) and PHP's built-in mail function

class EmailHelper {
    private $current_email_index = 0;
    private $email_configs = [
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
            // Try PHPMailer first if available
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendWithPHPMailer($user_data, $payment_data, $workshops_data);
            } else {
                // Fallback to PHP's built-in mail function
                return $this->sendWithBuiltInMail($user_data, $payment_data, $workshops_data);
            }
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }

    private function sendWithPHPMailer($user_data, $payment_data, $workshops_data) {
        // PHPMailer implementation
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Get current email config
        $config = $this->email_configs[$this->current_email_index];
        
        // Server settings
        $mailer->isSMTP();
        $mailer->Host = 'smtp.gmail.com';
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = 587;
        
        // Recipients
        $mailer->setFrom($config['username'], 'IPN Academy');
        $mailer->addAddress($user_data['email'], $user_data['name']);
        
        // Content
        $mailer->isHTML(true);
        $mailer->Subject = 'Payment Confirmation - IPN Academy Workshop Enrollment';
        $mailer->Body = $this->generatePaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
        
        return $mailer->send();
    }

    private function sendWithBuiltInMail($user_data, $payment_data, $workshops_data) {
        // PHP's built-in mail function implementation
        $config = $this->email_configs[$this->current_email_index];
        
        $to = $user_data['email'];
        $subject = 'Payment Confirmation - IPN Academy Workshop Enrollment';
        $message = $this->generatePaymentConfirmationEmail($user_data, $payment_data, $workshops_data);
        
        // Email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: IPN Academy <' . $config['username'] . '>',
            'Reply-To: ' . $config['username'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
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
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f4f4f4;
                }
                .email-container {
                    background-color: #ffffff;
                    border-radius: 10px;
                    padding: 30px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #e9ecef;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    color: #667eea;
                    margin-bottom: 10px;
                }
                .greeting {
                    font-size: 18px;
                    color: #333;
                    margin-bottom: 20px;
                }
                .payment-details {
                    background-color: #f8f9fa;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .payment-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 10px 0;
                    padding: 8px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .payment-row:last-child {
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 16px;
                    color: #667eea;
                }
                .workshop-list {
                    margin: 20px 0;
                }
                .workshop-item {
                    background-color: #f8f9fa;
                    border-left: 4px solid #667eea;
                    padding: 15px;
                    margin: 10px 0;
                    border-radius: 0 8px 8px 0;
                }
                .workshop-title {
                    font-weight: bold;
                    color: #333;
                    margin-bottom: 5px;
                }
                .workshop-details {
                    color: #666;
                    font-size: 14px;
                }
                .savings-highlight {
                    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                    color: white;
                    padding: 15px;
                    border-radius: 8px;
                    text-align: center;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 2px solid #e9ecef;
                    color: #666;
                    font-size: 14px;
                }
                .button {
                    display: inline-block;
                    background-color: #667eea;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    margin: 10px 5px;
                }
                .contact-info {
                    background-color: #e8f4fd;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <div class="logo">IPN Academy</div>
                    <h2 style="color: #667eea; margin: 10px 0;">Payment Confirmation</h2>
                </div>
                
                <div class="greeting">
                    Dear <strong>' . htmlspecialchars($user_data['name']) . '</strong>,
                </div>
                
                <p>Thank you for your payment! Your workshop enrollment has been confirmed successfully.</p>
                
                <div class="payment-details">
                    <h3 style="color: #667eea; margin-top: 0;">Payment Details</h3>
                    <div class="payment-row">
                        <span>Payment ID:</span>
                        <span><strong>' . htmlspecialchars($payment_data['payment_id']) . '</strong></span>
                    </div>
                    <div class="payment-row">
                        <span>Payment Date:</span>
                        <span>' . date('d M Y, h:i A', strtotime($payment_data['created_at'])) . '</span>
                    </div>
                    <div class="payment-row">
                        <span>Payment Method:</span>
                        <span>Instamojo</span>
                    </div>
                    <div class="payment-row">
                        <span>Status:</span>
                        <span style="color: #28a745; font-weight: bold;">✓ Completed</span>
                    </div>
                </div>';

        if ($savings > 0) {
            $html .= '
                <div class="savings-highlight">
                    <h3 style="margin: 0 0 10px 0;">🎉 You Saved ₹' . number_format($savings, 2) . '!</h3>
                    <p style="margin: 0;">Original Price: ₹' . number_format($total_original_price, 2) . ' | You Paid: ₹' . number_format($total_paid_amount, 2) . '</p>
                    <p style="margin: 5px 0 0 0; font-size: 14px;">That\'s a ' . $savings_percentage . '% discount!</p>
                </div>';
        }

        $html .= '
                <div class="workshop-list">
                    <h3 style="color: #667eea;">Enrolled Workshops</h3>';

        foreach ($workshops_data as $workshop) {
            $html .= '
                    <div class="workshop-item">
                        <div class="workshop-title">' . htmlspecialchars($workshop['name']) . '</div>
                        <div class="workshop-details">
                            <strong>Date:</strong> ' . date('d M Y, h:i A', strtotime($workshop['start_date'])) . '<br>
                            <strong>Trainer:</strong> ' . htmlspecialchars($workshop['trainer_name']) . '<br>
                            <strong>Duration:</strong> ' . htmlspecialchars($workshop['duration']) . ' hours<br>
                            <strong>Original Price:</strong> ₹' . number_format($workshop['price'], 2) . '
                        </div>
                    </div>';
        }

        $html .= '
                </div>
                
                <div class="contact-info">
                    <h4 style="margin-top: 0; color: #667eea;">Need Help?</h4>
                    <p style="margin: 5px 0;">If you have any questions about your enrollment, please contact us:</p>
                    <p style="margin: 5px 0;"><strong>Email:</strong> ipnacademy@ipnindia.in</p>
                    <p style="margin: 5px 0;"><strong>Phone:</strong> +91 7697001231, +91 8400700199</p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="https://workshops.ipnacademy.in" class="button">Visit IPN Academy</a>
                    <a href="https://workshops.ipnacademy.in/dashboard" class="button">My Dashboard</a>
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
?> 