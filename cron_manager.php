<?php
/**
 * Cron Manager - Web interface for managing workshop email cron jobs
 * This provides a simple web interface to view cron logs and test the cron endpoint
 */

session_start();
require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$log_file = __DIR__ . '/logs/cron_workshop_emails.log';
$logs_dir = dirname($log_file);

// Handle actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'test_cron':
            // Test the cron endpoint
            $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/cron_send_workshop_emails.php';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $test_result = [
                'success' => $http_code == 200 && !$error,
                'http_code' => $http_code,
                'error' => $error,
                'response' => $response
            ];
            break;
            
        case 'clear_logs':
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
                $clear_result = "Logs cleared successfully.";
            } else {
                $clear_result = "No log file found.";
            }
            break;
    }
}

// Get log content
$log_content = '';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $log_content = implode("\n", array_slice($log_lines, -100)); // Show last 100 lines
} else {
    $log_content = "No log file found. Cron job has not been run yet.";
}

// Get pending emails count
$pending_sql = "SELECT COUNT(*) as pending FROM workshops_emails WHERE is_sent = 0";
$pending_result = mysqli_query($conn, $pending_sql);
$pending_count = mysqli_fetch_assoc($pending_result)['pending'];

// Get workshops with pending emails
$workshops_sql = "SELECT w.id, w.name, w.start_date, COUNT(we.id) as pending_emails
                  FROM workshops w
                  INNER JOIN workshops_emails we ON w.id = we.workshop_id
                  WHERE we.is_sent = 0
                  GROUP BY w.id
                  ORDER BY w.start_date ASC";
$workshops_result = mysqli_query($conn, $workshops_sql);
$workshops = mysqli_fetch_all($workshops_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Manager - Workshop Emails</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h1><i class="bi bi-clock-history"></i> Cron Manager - Workshop Emails</h1>
                    <a href="workshop-details.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Workshop Details
                    </a>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-envelope"></i> Pending Emails</h5>
                        <h2><?php echo $pending_count; ?></h2>
                        <p class="card-text">Emails waiting to be sent</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-calendar-event"></i> Workshops</h5>
                        <h2><?php echo count($workshops); ?></h2>
                        <p class="card-text">Workshops with pending emails</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-check-circle"></i> Log Status</h5>
                        <h2><?php echo file_exists($log_file) ? 'Active' : 'Inactive'; ?></h2>
                        <p class="card-text">Cron job logging status</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-gear"></i> Actions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="test_cron">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-play-circle"></i> Test Cron Endpoint
                            </button>
                        </form>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn btn-warning me-2" onclick="return confirm('Are you sure you want to clear the logs?')">
                                <i class="bi bi-trash"></i> Clear Logs
                            </button>
                        </form>
                        
                        <a href="cron_send_workshop_emails.php" class="btn btn-success" target="_blank">
                            <i class="bi bi-external-link"></i> Run Cron Manually
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <?php if (isset($test_result)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle"></i> Test Results</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($test_result['success']): ?>
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> Test Successful!</h6>
                                <p><strong>HTTP Code:</strong> <?php echo $test_result['http_code']; ?></p>
                                <pre><?php echo htmlspecialchars($test_result['response']); ?></pre>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h6><i class="bi bi-x-circle"></i> Test Failed!</h6>
                                <p><strong>HTTP Code:</strong> <?php echo $test_result['http_code']; ?></p>
                                <?php if ($test_result['error']): ?>
                                    <p><strong>Error:</strong> <?php echo htmlspecialchars($test_result['error']); ?></p>
                                <?php endif; ?>
                                <pre><?php echo htmlspecialchars($test_result['response']); ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($clear_result)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <?php echo $clear_result; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Workshops with Pending Emails -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list-ul"></i> Workshops with Pending Emails</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($workshops) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Workshop ID</th>
                                            <th>Workshop Name</th>
                                            <th>Start Date</th>
                                            <th>Pending Emails</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($workshops as $workshop): ?>
                                        <tr>
                                            <td><?php echo $workshop['id']; ?></td>
                                            <td><?php echo htmlspecialchars($workshop['name']); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($workshop['start_date'])); ?></td>
                                            <td><span class="badge bg-warning"><?php echo $workshop['pending_emails']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> No workshops with pending emails found!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Logs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bi bi-file-text"></i> Cron Logs (Last 100 lines)</h5>
                        <small class="text-muted">Log file: <?php echo $log_file; ?></small>
                    </div>
                    <div class="card-body">
                        <pre style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;"><?php echo htmlspecialchars($log_content); ?></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Setup Instructions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-terminal"></i> Cron Setup Instructions</h5>
                    </div>
                    <div class="card-body">
                        <h6>To set up automatic email sending, add this to your crontab:</h6>
                        <pre class="bg-dark text-light p-3 rounded"># Run every 5 minutes
*/5 * * * * /usr/bin/php <?php echo __DIR__; ?>/cron_send_workshop_emails.php

# Or run every 10 minutes
*/10 * * * * /usr/bin/php <?php echo __DIR__; ?>/cron_send_workshop_emails.php

# Or run every hour
0 * * * * /usr/bin/php <?php echo __DIR__; ?>/cron_send_workshop_emails.php</pre>
                        
                        <h6 class="mt-3">To edit crontab:</h6>
                        <pre class="bg-dark text-light p-3 rounded">crontab -e</pre>
                        
                        <h6 class="mt-3">To view current crontab:</h6>
                        <pre class="bg-dark text-light p-3 rounded">crontab -l</pre>
                        
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Note:</strong> The cron job will automatically process workshops that have pending emails and are scheduled for today or earlier. It processes a maximum of 200 emails per workshop per run with a 30-minute timeout to prevent timeouts.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
