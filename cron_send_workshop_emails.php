<?php
/**
 * Cron endpoint for automatically sending workshop reminder emails
 * This script checks for workshops with prepared emails and sends them automatically
 * 
 * Usage: 
 * - Add to crontab: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/cron_send_workshop_emails.php
 * - Or call via HTTP: https://yoursite.com/cron_send_workshop_emails.php
 */

// Set execution time limit for cron jobs
set_time_limit(1800); // 30 minutes

// Log file for cron job tracking
$log_file = __DIR__ . '/logs/cron_workshop_emails.log';

// Create logs directory if it doesn't exist
$logs_dir = dirname($log_file);
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Function to log messages
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry; // Also output to console for immediate feedback
}

// Start logging
logMessage("=== CRON JOB STARTED ===");

try {
    require_once 'config/config.php';
    require_once 'config/email_helper.php';
    
    logMessage("Configuration loaded successfully");
    
    // Get all workshops that have prepared emails (is_sent = 0) and are scheduled for today or earlier
    $workshops_sql = "SELECT DISTINCT w.id, w.name, w.start_date, w.duration, w.meeting_id, w.passcode, 
                             w.trainer_id, t.name as trainer_name, COUNT(we.id) as pending_emails
                      FROM workshops w
                      LEFT JOIN trainers t ON w.trainer_id = t.id
                      INNER JOIN workshops_emails we ON w.id = we.workshop_id
                      WHERE we.is_sent = 0 
                      AND w.start_date <= DATE_ADD(NOW(), INTERVAL 1 DAY)
                      AND w.status = 1
                      GROUP BY w.id
                      HAVING pending_emails > 0
                      ORDER BY w.start_date ASC";
    
    $workshops_result = mysqli_query($conn, $workshops_sql);
    
    if (!$workshops_result) {
        throw new Exception('Failed to fetch workshops: ' . mysqli_error($conn));
    }
    
    $workshops = mysqli_fetch_all($workshops_result, MYSQLI_ASSOC);
    $total_workshops = count($workshops);
    
    logMessage("Found $total_workshops workshops with pending emails");
    
    if ($total_workshops == 0) {
        logMessage("No workshops with pending emails found. Exiting.");
        exit(0);
    }
    
    $total_emails_sent = 0;
    $total_emails_failed = 0;
    $processed_workshops = 0;
    
    foreach ($workshops as $workshop) {
        $workshop_id = $workshop['id'];
        $workshop_name = $workshop['name'];
        $pending_emails = $workshop['pending_emails'];
        
        logMessage("Processing workshop: $workshop_name (ID: $workshop_id) with $pending_emails pending emails");
        
        try {
            // Get prepared emails for this workshop
            $emails_sql = "SELECT we.*, u.name as user_name, u.email as user_email 
                           FROM workshops_emails we 
                           INNER JOIN users u ON we.user_id = u.id 
                           WHERE we.workshop_id = $workshop_id AND we.is_sent = 0
                           LIMIT 200"; // Process max 200 emails per workshop per cron run
            
            $emails_result = mysqli_query($conn, $emails_sql);
            
            if (!$emails_result) {
                throw new Exception('Failed to fetch emails for workshop ' . $workshop_id . ': ' . mysqli_error($conn));
            }
            
            $emails_to_send = mysqli_fetch_all($emails_result, MYSQLI_ASSOC);
            $emails_count = count($emails_to_send);
            
            if ($emails_count == 0) {
                logMessage("No emails to send for workshop $workshop_name");
                continue;
            }
            
            logMessage("Sending $emails_count emails for workshop: $workshop_name");
            
            $workshop_sent = 0;
            $workshop_failed = 0;
            $sending_email = '';
            
            foreach ($emails_to_send as $email_data) {
                try {
                    // Validate email address
                    if (empty($email_data['user_email']) || !filter_var($email_data['user_email'], FILTER_VALIDATE_EMAIL)) {
                        logMessage("Invalid email address for user {$email_data['user_name']}: {$email_data['user_email']}");
                        $workshop_failed++;
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
                    
                    // Generate joining link using actual workshop data
                    $joining_link = 'https://meet.ipnacademy.in/?display_name=' . $email_data['user_id'] . '_' . urlencode($email_data['user_name']) . '&mn=' . urlencode($workshop['meeting_id']) . '&pwd=' . urlencode($workshop['passcode']) . '&meeting_email=' . urlencode($email_data['user_email']);
                    
                    // Start transaction for this email
                    mysqli_begin_transaction($conn);
                    
                    // Send email
                    $email_sent = sendWorkshopReminderEmail($user_data, $workshop_data, $joining_link);
                    
                    if ($email_sent) {
                        // Update workshops_emails table
                        $update_sql = "UPDATE workshops_emails 
                                       SET is_sent = 1, 
                                           sent_at = NOW(), 
                                           updated_at = NOW() 
                                       WHERE id = " . $email_data['id'];
                        
                        $update_result = mysqli_query($conn, $update_sql);
                        
                        if ($update_result) {
                            // Update payments table
                            $update_payment_sql = "UPDATE payments 
                                                   SET mail_send = 1, 
                                                       updated_at = NOW() 
                                                   WHERE id = " . $email_data['payment_id'];
                            
                            $update_payment_result = mysqli_query($conn, $update_payment_sql);
                            
                            if ($update_payment_result) {
                                $workshop_sent++;
                                $total_emails_sent++;
                                
                                // Get the actual sending email that was used
                                $actual_sending_email = getLastUsedEmail();
                                
                                // Update sending email with the actual email used
                                $update_sending_email_sql = "UPDATE workshops_emails 
                                                             SET sending_user_email = '$actual_sending_email' 
                                                             WHERE id = " . $email_data['id'];
                                mysqli_query($conn, $update_sending_email_sql);
                                
                                logMessage("✓ Email sent using: $actual_sending_email to: {$email_data['user_email']}");
                                
                                // Commit the transaction immediately after successful email send
                                mysqli_commit($conn);
                                
                                logMessage("✓ Sent email to: {$email_data['user_email']} for workshop: $workshop_name - Database committed");
                            } else {
                                // Rollback transaction on payment update failure
                                mysqli_rollback($conn);
                                logMessage("✗ Failed to update payments table for: {$email_data['user_email']} - Transaction rolled back");
                                logEmailError($conn, $workshop_id, $email_data, 'database_error', 'Failed to update payment record: ' . mysqli_error($conn));
                                $workshop_failed++;
                                $total_emails_failed++;
                            }
                        } else {
                            // Rollback transaction on workshops_emails update failure
                            mysqli_rollback($conn);
                            logMessage("✗ Failed to update workshops_emails table for: {$email_data['user_email']} - Transaction rolled back");
                            logEmailError($conn, $workshop_id, $email_data, 'database_error', 'Failed to update email record: ' . mysqli_error($conn));
                            $workshop_failed++;
                            $total_emails_failed++;
                        }
                    } else {
                        // Rollback transaction on email send failure
                        mysqli_rollback($conn);
                        logMessage("✗ Failed to send email to: {$email_data['user_email']} - Transaction rolled back");
                        logEmailError($conn, $workshop_id, $email_data, 'smtp_error', 'Failed to send email to: ' . $email_data['user_email']);
                        $workshop_failed++;
                        $total_emails_failed++;
                    }
                    
                    // Small delay to prevent overwhelming the email server
                    usleep(50000); // 0.05 second delay (50ms)
                    
                } catch (Exception $e) {
                    // Rollback transaction on any exception
                    mysqli_rollback($conn);
                    logMessage("✗ Error processing email for {$email_data['user_email']}: " . $e->getMessage() . " - Transaction rolled back");
                    logEmailError($conn, $workshop_id, $email_data, 'other', 'Exception: ' . $e->getMessage());
                    $workshop_failed++;
                    $total_emails_failed++;
                }
            }
            
            $processed_workshops++;
            logMessage("Workshop $workshop_name completed: $workshop_sent sent, $workshop_failed failed");
            
        } catch (Exception $e) {
            logMessage("✗ Error processing workshop $workshop_name: " . $e->getMessage());
            continue;
        }
    }
    
    // Final summary
    logMessage("=== CRON JOB COMPLETED ===");
    logMessage("Processed workshops: $processed_workshops");
    logMessage("Total emails sent: $total_emails_sent");
    logMessage("Total emails failed: $total_emails_failed");
    logMessage("Success rate: " . ($total_emails_sent + $total_emails_failed > 0 ? round(($total_emails_sent / ($total_emails_sent + $total_emails_failed)) * 100, 2) : 0) . "%");
    
    // If called via HTTP, return JSON response
    if (isset($_SERVER['HTTP_HOST'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Cron job completed successfully',
            'processed_workshops' => $processed_workshops,
            'total_emails_sent' => $total_emails_sent,
            'total_emails_failed' => $total_emails_failed,
            'success_rate' => $total_emails_sent + $total_emails_failed > 0 ? round(($total_emails_sent / ($total_emails_sent + $total_emails_failed)) * 100, 2) : 0
        ]);
    }
    
} catch (Exception $e) {
    $error_message = "CRON JOB ERROR: " . $e->getMessage();
    logMessage($error_message);
    
    // If called via HTTP, return JSON error response
    if (isset($_SERVER['HTTP_HOST'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
    }
    
    exit(1);
}

// Helper function to log email errors
function logEmailError($conn, $workshop_id, $email_data, $error_type, $error_message) {
    $sql = "INSERT INTO email_errors 
            (workshop_id, user_id, payment_id, user_email, user_name, error_type, error_message, retry_count, is_resolved, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            payment_id = VALUES(payment_id),
            user_email = VALUES(user_email),
            user_name = VALUES(user_name),
            error_type = VALUES(error_type),
            error_message = VALUES(error_message),
            retry_count = retry_count + 1,
            is_resolved = 0,
            resolved_at = NULL,
            updated_at = NOW()";
    
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
?>
