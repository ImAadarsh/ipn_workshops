<?php
session_start();
require_once 'config/config.php';
require_once 'config/email_helper.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Get workshop_id from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $workshop_id = isset($input['workshop_id']) ? intval($input['workshop_id']) : 0;

    if (!$workshop_id) {
        throw new Exception('Workshop ID is required');
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    // Get workshop details
    $workshop_sql = "SELECT w.*, t.name as trainer_name 
                     FROM workshops w 
                     LEFT JOIN trainers t ON w.trainer_id = t.id 
                     WHERE w.id = $workshop_id";
    $workshop_result = mysqli_query($conn, $workshop_sql);
    
    if (!$workshop_result || mysqli_num_rows($workshop_result) == 0) {
        throw new Exception('Workshop not found');
    }
    
    $workshop = mysqli_fetch_assoc($workshop_result);

    // Get prepared emails that haven't been sent yet (is_sent = 0)
    $emails_sql = "SELECT we.*, u.name as user_name, u.email as user_email 
                   FROM workshops_emails we 
                   INNER JOIN users u ON we.user_id = u.id 
                   WHERE we.workshop_id = $workshop_id AND we.is_sent = 0";
    $emails_result = mysqli_query($conn, $emails_sql);
    
    if (!$emails_result) {
        throw new Exception('Failed to fetch prepared emails: ' . mysqli_error($conn));
    }

    $emails_to_send = mysqli_fetch_all($emails_result, MYSQLI_ASSOC);
    $total_emails = count($emails_to_send);
    $sent_count = 0;
    $failed_count = 0;
    $sending_email = '';

    if ($total_emails == 0) {
        throw new Exception('No prepared emails found for this workshop');
    }

    // Process each email one by one
    $is_first_email = true;
    foreach ($emails_to_send as $email_data) {
        try {
            // Validate email address first
            if (empty($email_data['user_email']) || !filter_var($email_data['user_email'], FILTER_VALIDATE_EMAIL)) {
                // Log invalid email error
                logEmailError($conn, $workshop_id, $email_data, 'invalid_email', 'Invalid email address: ' . $email_data['user_email']);
                $failed_count++;
                continue;
            }

            // Prepare user data
            $user_data = [
                'name' => $email_data['user_name'],
                'email' => $email_data['user_email']
            ];

            // Prepare workshop data
            $workshop_data = [
                'name' => $workshop['name'],
                'trainer_name' => $workshop['trainer_name'],
                'start_date' => $workshop['start_date'],
                'duration' => $workshop['duration'],
                'meeting_id' => $workshop['meeting_id'],
                'passcode' => $workshop['passcode']
            ];

            // Generate unique joining link using the correct format
            $joining_link = 'https://meet.ipnacademy.in/?display_name=' . $email_data['user_id'] . '_' . urlencode($email_data['user_name']) . '&mn=' . urlencode($workshop['meeting_id']) . '&pwd=' . urlencode($workshop['passcode']) . '&meeting_email=' . urlencode($email_data['user_email']);

            // Send email with timeout
            $email_sent = sendEmailWithTimeout($user_data, $workshop_data, $joining_link);
            
            if ($email_sent) {
                // Update the email record as sent
                $update_sql = "UPDATE workshops_emails 
                               SET is_sent = 1, 
                                   sent_at = NOW(), 
                                   updated_at = NOW() 
                               WHERE id = " . $email_data['id'];
                
                $update_result = mysqli_query($conn, $update_sql);
                
                if ($update_result) {
                    // Also update the payments table to mark mail_send = 1
                    $update_payment_sql = "UPDATE payments 
                                           SET mail_send = 1, 
                                               updated_at = NOW() 
                                           WHERE id = " . $email_data['payment_id'];
                    
                    $update_payment_result = mysqli_query($conn, $update_payment_sql);
                    
                    if ($update_payment_result) {
                        $sent_count++;
                        
                        // Check if this is the first email and verify database updates
                        if ($is_first_email) {
                            // Verify the updates actually worked
                            $verify_workshop_sql = "SELECT is_sent, sent_at FROM workshops_emails WHERE id = " . $email_data['id'];
                            $verify_workshop_result = mysqli_query($conn, $verify_workshop_sql);
                            $workshop_verify = mysqli_fetch_assoc($verify_workshop_result);
                            
                            $verify_payment_sql = "SELECT mail_send, last_mail FROM payments WHERE id = " . $email_data['payment_id'];
                            $verify_payment_result = mysqli_query($conn, $verify_payment_sql);
                            $payment_verify = mysqli_fetch_assoc($verify_payment_result);
                            
                            // Check if updates actually worked
                            if ($workshop_verify['is_sent'] != 1 || $payment_verify['mail_send'] != 1) {
                                // Database updates failed - stop the process
                                mysqli_rollback($conn);
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'Database update verification failed after first email. Stopping process.',
                                    'details' => [
                                        'workshop_emails_is_sent' => $workshop_verify['is_sent'],
                                        'payments_mail_send' => $payment_verify['mail_send'],
                                        'workshop_emails_id' => $email_data['id'],
                                        'payments_id' => $email_data['payment_id'],
                                        'user_email' => $email_data['user_email']
                                    ]
                                ]);
                                exit;
                            }
                            
                            $is_first_email = false; // Mark that we've processed the first email
                        }
                        
                        // Get the sending email from the first successful send
                        if (empty($sending_email)) {
                            // Get the email that was used to send (from EmailHelper config)
                            $email_helper = new EmailHelper();
                            $reflection = new ReflectionClass($email_helper);
                            $property = $reflection->getProperty('email_configs');
                            $property->setAccessible(true);
                            $configs = $property->getValue($email_helper);
                            
                            // Get the current email index (this is a simplified approach)
                            // In a real implementation, you might want to track which email was used
                            $sending_email = $configs[0]['username']; // Default to first config
                        }
                        
                        // Log success
                        error_log("Successfully sent email to: " . $email_data['user_email']);
                    } else {
                        if ($is_first_email) {
                            // First email failed database update - stop process
                            mysqli_rollback($conn);
                            echo json_encode([
                                'success' => false,
                                'message' => 'Failed to update payments table after first email. Stopping process.',
                                'error' => mysqli_error($conn),
                                'payment_id' => $email_data['payment_id'],
                                'user_email' => $email_data['user_email']
                            ]);
                            exit;
                        }
                        logEmailError($conn, $workshop_id, $email_data, 'database_error', 'Failed to update payment record: ' . mysqli_error($conn));
                        $failed_count++;
                    }
                } else {
                    if ($is_first_email) {
                        // First email failed database update - stop process
                        mysqli_rollback($conn);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Failed to update workshops_emails table after first email. Stopping process.',
                            'error' => mysqli_error($conn),
                            'workshop_emails_id' => $email_data['id'],
                            'user_email' => $email_data['user_email']
                        ]);
                        exit;
                    }
                    logEmailError($conn, $workshop_id, $email_data, 'database_error', 'Failed to update email record: ' . mysqli_error($conn));
                    $failed_count++;
                }
            } else {
                if ($is_first_email) {
                    // First email failed to send - stop process
                    mysqli_rollback($conn);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to send first email. Stopping process.',
                        'user_email' => $email_data['user_email']
                    ]);
                    exit;
                }
                logEmailError($conn, $workshop_id, $email_data, 'smtp_error', 'Failed to send email to: ' . $email_data['user_email']);
                $failed_count++;
            }

            // Add a small delay to prevent overwhelming the email server
            usleep(100000); // 0.1 second delay

        } catch (Exception $e) {
            if ($is_first_email) {
                // First email had exception - stop process
                mysqli_rollback($conn);
                echo json_encode([
                    'success' => false,
                    'message' => 'Exception occurred while processing first email. Stopping process.',
                    'error' => $e->getMessage(),
                    'user_email' => $email_data['user_email']
                ]);
                exit;
            }
            logEmailError($conn, $workshop_id, $email_data, 'other', 'Exception: ' . $e->getMessage());
            $failed_count++;
            error_log("Error sending email to " . $email_data['user_email'] . ": " . $e->getMessage());
        }
    }

    // Update sending_user_email for all sent emails
    if ($sent_count > 0 && !empty($sending_email)) {
        $update_sending_email_sql = "UPDATE workshops_emails 
                                     SET sending_user_email = '$sending_email' 
                                     WHERE workshop_id = $workshop_id AND is_sent = 1 AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        mysqli_query($conn, $update_sending_email_sql);
    }

    // Commit transaction
    mysqli_commit($conn);

    // Get updated counts
    $total_sent_sql = "SELECT COUNT(*) as total_sent 
                       FROM workshops_emails 
                       WHERE workshop_id = $workshop_id AND is_sent = 1";
    $total_sent_result = mysqli_query($conn, $total_sent_sql);
    $total_sent = mysqli_fetch_assoc($total_sent_result)['total_sent'];

    $total_prepared_sql = "SELECT COUNT(*) as total_prepared 
                           FROM workshops_emails 
                           WHERE workshop_id = $workshop_id";
    $total_prepared_result = mysqli_query($conn, $total_prepared_sql);
    $total_prepared = mysqli_fetch_assoc($total_prepared_result)['total_prepared'];

    echo json_encode([
        'success' => true,
        'message' => 'Workshop reminder emails processed successfully',
        'total_processed' => $total_emails,
        'sent_count' => $sent_count,
        'failed_count' => $failed_count,
        'total_sent' => $total_sent,
        'total_prepared' => $total_prepared,
        'sending_email' => $sending_email
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    
    error_log("Send workshop reminder emails error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper function to log email errors
function logEmailError($conn, $workshop_id, $email_data, $error_type, $error_message) {
    $sql = "INSERT INTO email_errors 
            (workshop_id, user_id, payment_id, user_email, user_name, error_type, error_message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iiissss", 
            $workshop_id, 
            $email_data['user_id'], 
            $email_data['payment_id'], 
            $email_data['user_email'], 
            $email_data['user_name'], 
            $error_type, 
            $error_message
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Helper function to send email with timeout
function sendEmailWithTimeout($user_data, $workshop_data, $joining_link) {
    // Set a timeout for email sending (30 seconds)
    $timeout = 30;
    $start_time = time();
    
    try {
        // Send email
        $result = sendWorkshopReminderEmail($user_data, $workshop_data, $joining_link);
        
        // Check if we've exceeded the timeout
        if ((time() - $start_time) > $timeout) {
            error_log("Email sending timeout for: " . $user_data['email']);
            return false;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Email sending exception for " . $user_data['email'] . ": " . $e->getMessage());
        return false;
    }
}
?>
