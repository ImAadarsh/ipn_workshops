<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

// Function to get OAuth2 access token
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
        if (isset($token_data['access_token'])) {
            return $token_data['access_token'];
        }
    }
    
    error_log("Failed to get Instamojo access token. HTTP Code: $http_code, Response: $response");
    return false;
}

// Instamojo API credentials (you'll need to add these to your config)
$instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
$instamojo_base_url = 'https://api.instamojo.com/v2/';
$instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_link':
                $link_name = trim($_POST['link_name']);
                $workshop_ids = implode(',', $_POST['workshop_ids']);
                $amount = floatval($_POST['amount']);
                
                if (empty($link_name) || empty($workshop_ids) || $amount <= 0) {
                    $_SESSION['error_message'] = "Please fill all required fields correctly.";
                } else {
                    // Get OAuth2 access token first
                    $access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);
                    
                    if (!$access_token) {
                        $_SESSION['error_message'] = "Failed to get Instamojo access token. Please check your credentials.";
                    } else {
                        // Create Instamojo payment request via API v2
                        $instamojo_data = [
                            'purpose' => $link_name,
                            'amount' => $amount,
                            'redirect_url' => 'https://workshops.ipnacademy.in/instamojo_success.php',
                            'send_email' => 'False',
                            'webhook' => 'https://workshops.ipnacademy.in/instamojo_webhook.php',
                            'allow_repeated_payments' => 'True'
                        ];
                        
                        
                        // Debug: Log the request data

                        
                        error_log("Instamojo API Request: " . json_encode($instamojo_data));
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/');
                        curl_setopt($ch, CURLOPT_HEADER, FALSE);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer $access_token"
                        ]);
                        curl_setopt($ch, CURLOPT_POST, TRUE);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($instamojo_data));
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        
                        // Debug: Log the response
                        error_log("Instamojo API Response Code: $http_code");
                        error_log("Instamojo API Response: $response");
                        if ($curl_error) {
                            error_log("Instamojo API Curl Error: $curl_error");
                        }
                        
                        if ($http_code === 201 || $http_code === 200) {
                            $response_data = json_decode($response, true);
                            if (isset($response_data['id'])) {
                                $instamojo_link_id = $response_data['id'];
                                $instamojo_link_url = $response_data['longurl'];
                                
                                // Save to database
                                $sql = "INSERT INTO instamojo_links (link_name, workshop_ids, amount, instamojo_link_id, instamojo_link_url) VALUES (?, ?, ?, ?, ?)";
                                $stmt = mysqli_prepare($conn, $sql);
                                mysqli_stmt_bind_param($stmt, "ssdss", $link_name, $workshop_ids, $amount, $instamojo_link_id, $instamojo_link_url);
                                
                                if (mysqli_stmt_execute($stmt)) {
                                    $_SESSION['success_message'] = "Payment link created successfully! Link ID: $instamojo_link_id";
                                } else {
                                    $_SESSION['error_message'] = "Error saving link to database: " . mysqli_error($conn);
                                }
                                mysqli_stmt_close($stmt);
                            } else {
                                $_SESSION['error_message'] = "Error creating Instamojo link. Response: " . $response;
                            }
                        } else {
                            $_SESSION['error_message'] = "Error connecting to Instamojo API. HTTP Code: $http_code, Response: $response";
                            if ($curl_error) {
                                $_SESSION['error_message'] .= ", Curl Error: $curl_error";
                            }
                        }
                    }
                }
                break;
                
            case 'test_api':
                // Manual API test
                $access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);
                
                if (!$access_token) {
                    $_SESSION['error_message'] = "Failed to get Instamojo access token for manual test.";
                } else {
                    $test_data = [
                        'purpose' => 'Test Payment Link',
                        'amount' => '1',
                        'buyer_name' => 'Test User',
                        'email' => 'test@example.com',
                        'phone' => '9999999999',
                        'redirect_url' => 'https://ipnacademy.in/instamojo_success.php',
                        'send_email' => 'True',
                        'webhook' => 'https://ipnacademy.in/instamojo_webhook.php',
                        'allow_repeated_payments' => 'False'
                    ];
                    
                    error_log("Manual API Test - Request: " . json_encode($test_data));
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/');
                    curl_setopt($ch, CURLOPT_HEADER, FALSE);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer $access_token"
                    ]);
                    curl_setopt($ch, CURLOPT_POST, TRUE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_data));
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    error_log("Manual API Test - HTTP Code: $http_code, Response: $response");
                    if ($curl_error) {
                        error_log("Manual API Test - Curl Error: $curl_error");
                    }
                    
                    if ($http_code === 201 || $http_code === 200) {
                        $response_data = json_decode($response, true);
                        if (isset($response_data['id'])) {
                            $_SESSION['success_message'] = "Manual API test successful! Payment Request ID: " . $response_data['id'];
                        } else {
                            $_SESSION['success_message'] = "Manual API test successful! HTTP Code: $http_code";
                        }
                    } else {
                        $_SESSION['error_message'] = "Manual API test failed. HTTP Code: $http_code, Response: $response";
                        if ($curl_error) {
                            $_SESSION['error_message'] .= ", Curl Error: $curl_error";
                        }
                    }
                }
                break;
                
            case 'toggle_status':
                $link_id = intval($_POST['link_id']);
                $new_status = $_POST['new_status'];
                
                $sql = "UPDATE instamojo_links SET status = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $new_status, $link_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Link status updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Error updating link status.";
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Fetch existing links
$links_sql = "SELECT * FROM instamojo_links ORDER BY created_at DESC";
$links_result = mysqli_query($conn, $links_sql);
$links = [];
while ($row = mysqli_fetch_assoc($links_result)) {
    $links[] = $row;
}

// Fetch workshops for dropdown
$workshops_sql = "SELECT id, name, start_date, price FROM workshops WHERE is_deleted = 0 AND type=0 AND price > 0 ORDER BY start_date ASC limit 9";
$workshops_result = mysqli_query($conn, $workshops_sql);
$workshops = [];
while ($row = mysqli_fetch_assoc($workshops_result)) {
    $workshops[] = $row;
}

// Fetch recent payments
$payments_sql = "SELECT ip.*, il.link_name 
                 FROM instamojo_payments ip 
                 JOIN instamojo_links il ON ip.link_id = il.id 
                 ORDER BY ip.created_at DESC 
                 LIMIT 10";
$payments_result = mysqli_query($conn, $payments_sql);
$recent_payments = [];
while ($row = mysqli_fetch_assoc($payments_result)) {
    $recent_payments[] = $row;
}

$page_title = "Instamojo Dashboard";
include 'includes/head.php';
?>

<div class="page-wrapper">
    <?php include 'includes/sidenav.php'; ?>
    
    
        <?php include 'includes/topbar.php'; ?>
        <div class="page-content">
        <div class="container-fluid">
            <div class="row mt-2 mb-4">
                <div class="col-12">
                    <div class="page-title-box d-flex align-items-center justify-content-between">
                        <h4 class="mb-0">Instamojo Dashboard</h4>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- API Debug Info -->
            <!-- <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">API Configuration & Debug</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Current Configuration:</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>API Base URL:</strong> <?php echo htmlspecialchars($instamojo_base_url); ?></li>
                                        <li><strong>OAuth URL:</strong> <?php echo htmlspecialchars($instamojo_oauth_url); ?></li>
                                        <li><strong>Client ID:</strong> <?php echo substr($instamojo_client_id, 0, 8) . '...'; ?></li>
                                        <li><strong>Client Secret:</strong> <?php echo substr($instamojo_client_secret, 0, 8) . '...'; ?></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Test API Connection:</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApiConnection()">
                                        <i class="ti ti-test-pipe me-1"></i> Test Connection
                                    </button>
                                    <div id="apiTestResult" class="mt-2"></div>
                                    
                                    <h6 class="mt-3">Recent Error Logs:</h6>
                                    <div class="small text-muted">
                                        <?php
                                        $log_file = 'instamojo_webhook.log';
                                        if (file_exists($log_file)) {
                                            $logs = file($log_file);
                                            $recent_logs = array_slice($logs, -5); // Last 5 lines
                                            foreach ($recent_logs as $log) {
                                                echo htmlspecialchars(trim($log)) . '<br>';
                                            }
                                        } else {
                                            echo 'No log file found';
                                        }
                                        ?>
                                    </div>
                                    
                                    <h6 class="mt-3">Manual API Test:</h6>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="test_api">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="ti ti-bug me-1"></i> Manual Test
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Navigation Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-link fs-1 mb-3"></i>
                            <h5 class="card-title">Payment Links</h5>
                            <p class="card-text">Manage existing payment links</p>
                            <a href="instamojo_links.php" class="btn btn-light">
                                <i class="ti ti-arrow-right me-1"></i> View Links
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-credit-card fs-1 mb-3"></i>
                            <h5 class="card-title">Payment History</h5>
                            <p class="card-text">View all payment transactions</p>
                            <a href="instamojo_payments.php" class="btn btn-light">
                                <i class="ti ti-arrow-right me-1"></i> View Payments
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-plus fs-1 mb-3"></i>
                            <h5 class="card-title">Create New Link</h5>
                            <p class="card-text">Generate new payment links</p>
                            <a href="#createLinkSection" class="btn btn-light">
                                <i class="ti ti-arrow-down me-1"></i> Create Link
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New Link -->
            <div class="row mb-4" id="createLinkSection">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Create New Payment Link</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="create_link">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Link Name *</label>
                                            <input type="text" class="form-control" name="link_name" required 
                                                   placeholder="e.g., Advanced Workshop Package">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Amount (₹) *</label>
                                            <input type="number" class="form-control" name="amount" required 
                                                   min="1" step="0.01" placeholder="999.00">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Select Workshops *</label>
                                            <div class="workshop-grid">
                                                <?php foreach ($workshops as $workshop): ?>
                                                    <div class="workshop-card">
                                                        <input type="checkbox" name="workshop_ids[]" 
                                                               value="<?php echo $workshop['id']; ?>" 
                                                               id="workshop_<?php echo $workshop['id']; ?>" 
                                                               class="workshop-checkbox">
                                                        <label for="workshop_<?php echo $workshop['id']; ?>" class="workshop-label">
                                                            <div class="workshop-content">
                                                                <h6 class="workshop-title"><?php echo htmlspecialchars($workshop['name']); ?></h6>
                                                                <p class="workshop-date">
                                                                    <i class="ti ti-calendar me-1"></i>
                                                                    <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                                                </p>
                                                                <p class="workshop-date">
                                                                    <i class="ti ti-money me-1"></i>
                                                                    Rs. <?php echo (($workshop['price'])); ?>
                                                                </p>
                                                                <div class="workshop-status">
                                                                    <span class="badge bg-primary">Upcoming</span>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <small class="text-muted">Select one or more workshops for this payment link</small>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i> Create Payment Link
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-link fs-2 mb-2"></i>
                            <h4 class="mb-1"><?php echo count($links); ?></h4>
                            <p class="mb-0">Total Links</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-credit-card fs-2 mb-2"></i>
                            <h4 class="mb-1"><?php echo count($recent_payments); ?></h4>
                            <p class="mb-0">Total Payments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-check fs-2 mb-2"></i>
                            <h4 class="mb-1"><?php echo count(array_filter($recent_payments, function($p) { return $p['status'] === 'completed'; })); ?></h4>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="ti ti-clock fs-2 mb-2"></i>
                            <h4 class="mb-1"><?php echo count(array_filter($recent_payments, function($p) { return $p['status'] === 'pending'; })); ?></h4>
                            <p class="mb-0">Pending</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function testApiConnection() {
    const resultDiv = document.getElementById('apiTestResult');
    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Testing connection...';
    
    fetch('test_instamojo_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success">✅ API connection successful!</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">❌ API connection failed: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="alert alert-danger">❌ Network error: ' + error.message + '</div>';
        });
}

// Workshop selection functionality
$(document).ready(function() {
    // Handle workshop card selection
    $('.workshop-checkbox').change(function() {
        const card = $(this).closest('.workshop-card');
        if (this.checked) {
            card.addClass('selected');
        } else {
            card.removeClass('selected');
        }
    });
    
    // Handle workshop label click
    $('.workshop-label').click(function(e) {
        e.preventDefault();
        const checkbox = $(this).siblings('.workshop-checkbox');
        checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
    });
});
</script>

<style>
.workshop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.workshop-card {
    position: relative;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.workshop-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.1);
}

.workshop-card.selected {
    border-color: #007bff;
    background-color: #f8f9ff;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
}

.workshop-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.workshop-label {
    display: block;
    padding: 1.5rem;
    margin: 0;
    cursor: pointer;
    height: 100%;
}

.workshop-content {
    text-align: center;
}

.workshop-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

.workshop-date {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.workshop-status {
    margin-top: 0.5rem;
}

.workshop-card.selected .workshop-title {
    color: #007bff;
}

.workshop-card.selected::before {
    content: '✓';
    position: absolute;
    top: 10px;
    right: 10px;
    background: #007bff;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

/* Navigation cards styling */
.card.bg-primary, .card.bg-success, .card.bg-info {
    transition: transform 0.3s ease;
}

.card.bg-primary:hover, .card.bg-success:hover, .card.bg-info:hover {
    transform: translateY(-5px);
}

.card.bg-primary .btn-light, .card.bg-success .btn-light, .card.bg-info .btn-light {
    transition: all 0.3s ease;
}

.card.bg-primary .btn-light:hover, .card.bg-success .btn-light:hover, .card.bg-info .btn-light:hover {
    background-color: #fff;
    color: #333;
    transform: scale(1.05);
}
</style>

</body>
</html> 