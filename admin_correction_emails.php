<?php
require_once 'config/email_helper.php';

class ProfileCorrectionEmails {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Send approval notification to user
     */
    public function sendApprovalNotification($request_id, $user_email, $user_name, $admin_notes = '') {
        $subject = "Profile Correction Request Approved - IPN Academy";
        
        $message = "
        <html>
        <head>
            <title>Profile Correction Request Approved</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .success { color: #28a745; font-weight: bold; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Profile Correction Request Approved</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$user_name</strong>,</p>
                    
                    <p class='success'>Great news! Your profile correction request has been approved.</p>
                    
                    <p>Your profile information has been updated with the requested changes. You can now log in to your account to verify the changes.</p>
                    
                    " . (!empty($admin_notes) ? "<p><strong>Admin Notes:</strong><br>" . nl2br(htmlspecialchars($admin_notes)) . "</p>" : "") . "
                    
                    <p>If you have any questions or need further assistance, please don't hesitate to contact our support team.</p>
                    
                    <p>Thank you for using IPN Academy!</p>
                    
                    <p style='margin-top: 30px;'>
                        <a href='https://ipnacademy.in/login' class='btn'>Login to Your Account</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from IPN Academy. Please do not reply to this email.</p>
                    <p>© " . date('Y') . " IPN Academy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send rejection notification to user
     */
    public function sendRejectionNotification($request_id, $user_email, $user_name, $admin_notes = '') {
        $subject = "Profile Correction Request Update - IPN Academy";
        
        $message = "
        <html>
        <head>
            <title>Profile Correction Request Update</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .info { color: #17a2b8; font-weight: bold; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Profile Correction Request Update</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$user_name</strong>,</p>
                    
                    <p class='info'>Your profile correction request has been reviewed by our team.</p>
                    
                    <p>Unfortunately, we are unable to approve your request at this time. This could be due to various reasons such as incomplete information, policy restrictions, or verification requirements.</p>
                    
                    " . (!empty($admin_notes) ? "<p><strong>Admin Notes:</strong><br>" . nl2br(htmlspecialchars($admin_notes)) . "</p>" : "") . "
                    
                    <p>If you believe this is an error or would like to provide additional information, please feel free to submit a new correction request or contact our support team.</p>
                    
                    <p>We appreciate your understanding and look forward to serving you better.</p>
                    
                    <p style='margin-top: 30px;'>
                        <a href='https://ipnacademy.in/profile' class='btn'>Update Your Profile</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from IPN Academy. Please do not reply to this email.</p>
                    <p>© " . date('Y') . " IPN Academy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($user_email, $subject, $message);
    }
    
    /**
     * Send notification to admin about new request
     */
    public function sendAdminNotification($request_id, $user_name, $user_email, $admin_email = 'admin@ipnacademy.in') {
        $subject = "New Profile Correction Request - IPN Academy Admin";
        
        $message = "
        <html>
        <head>
            <title>New Profile Correction Request</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .urgent { color: #dc3545; font-weight: bold; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Profile Correction Request</h2>
                </div>
                <div class='content'>
                    <p class='urgent'>A new profile correction request has been submitted and requires your attention.</p>
                    
                    <p><strong>Request Details:</strong></p>
                    <ul>
                        <li><strong>Request ID:</strong> $request_id</li>
                        <li><strong>User Name:</strong> $user_name</li>
                        <li><strong>User Email:</strong> $user_email</li>
                        <li><strong>Submitted:</strong> " . date('M j, Y g:i A') . "</li>
                    </ul>
                    
                    <p>Please review the request and take appropriate action (approve or reject) as soon as possible.</p>
                    
                    <p style='margin-top: 30px;'>
                        <a href='https://ipnacademy.in/admin/admin_profile_corrections.php' class='btn'>Review Request</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from IPN Academy Admin System.</p>
                    <p>© " . date('Y') . " IPN Academy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($admin_email, $subject, $message);
    }
    
    /**
     * Send bulk action notification
     */
    public function sendBulkActionNotification($action, $count, $admin_email, $admin_name) {
        $subject = "Bulk Profile Correction Action Completed - IPN Academy Admin";
        
        $action_text = $action === 'approve' ? 'approved' : 'rejected';
        $action_color = $action === 'approve' ? '#28a745' : '#dc3545';
        
        $message = "
        <html>
        <head>
            <title>Bulk Action Completed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: $action_color; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .info { color: $action_color; font-weight: bold; }
                .footer { background: #6c757d; color: white; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Bulk Action Completed</h2>
                </div>
                <div class='content'>
                    <p>Dear <strong>$admin_name</strong>,</p>
                    
                    <p class='info'>Your bulk action has been completed successfully.</p>
                    
                    <p><strong>Action:</strong> $count profile correction request(s) $action_text</p>
                    <p><strong>Completed by:</strong> $admin_name</p>
                    <p><strong>Completed at:</strong> " . date('M j, Y g:i A') . "</p>
                    
                    <p>All affected users have been notified via email about the status of their requests.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from IPN Academy Admin System.</p>
                    <p>© " . date('Y') . " IPN Academy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($admin_email, $subject, $message);
    }
    
    /**
     * Send email using the existing email helper
     */
    private function sendEmail($to, $subject, $message) {
        try {
            // Use the existing email helper
            $emailHelper = new EmailHelper();
            return $emailHelper->sendEmail($to, $subject, $message);
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template for different scenarios
     */
    public function getEmailTemplate($template_name, $data = []) {
        $templates = [
            'approval' => [
                'subject' => 'Profile Correction Request Approved - IPN Academy',
                'template' => 'approval_template'
            ],
            'rejection' => [
                'subject' => 'Profile Correction Request Update - IPN Academy',
                'template' => 'rejection_template'
            ],
            'admin_notification' => [
                'subject' => 'New Profile Correction Request - IPN Academy Admin',
                'template' => 'admin_notification_template'
            ]
        ];
        
        return $templates[$template_name] ?? null;
    }
    
    /**
     * Log email sending activity
     */
    private function logEmailActivity($request_id, $email_type, $recipient, $status) {
        $log_sql = "INSERT INTO email_logs (request_id, email_type, recipient, status, sent_at) 
                    VALUES (?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($this->conn, $log_sql);
        mysqli_stmt_bind_param($stmt, 'isss', $request_id, $email_type, $recipient, $status);
        mysqli_stmt_execute($stmt);
    }
}

// Helper function to send approval email
function sendApprovalEmail($conn, $request_id, $user_email, $user_name, $admin_notes = '') {
    $emailSender = new ProfileCorrectionEmails($conn);
    return $emailSender->sendApprovalNotification($request_id, $user_email, $user_name, $admin_notes);
}

// Helper function to send rejection email
function sendRejectionEmail($conn, $request_id, $user_email, $user_name, $admin_notes = '') {
    $emailSender = new ProfileCorrectionEmails($conn);
    return $emailSender->sendRejectionNotification($request_id, $user_email, $user_name, $admin_notes);
}

// Helper function to send admin notification
function sendAdminNotification($conn, $request_id, $user_name, $user_email, $admin_email) {
    $emailSender = new ProfileCorrectionEmails($conn);
    return $emailSender->sendAdminNotification($request_id, $user_name, $user_email, $admin_email);
}
?>
