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
                        'email' => 'aadarshkavita@gmail.com',
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
        <!-- Hero Section -->
        <div class="hero-section mb-4">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="hero-content">
                            <h1 class="hero-title">
                                <span class="gradient-text">Instamojo</span> Payment Management
                            </h1>
                            <p class="hero-subtitle">
                                Create, manage, and track payment links for your workshops with ease
                            </p>
                            <div class="hero-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo count($links); ?></span>
                                    <span class="stat-label">Active Links</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo count($recent_payments); ?></span>
                                    <span class="stat-label">Total Payments</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo count(array_filter($recent_payments, function($p) { return $p['status'] === 'completed'; })); ?></span>
                                    <span class="stat-label">Completed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="hero-illustration">
                            <div class="floating-card">
                                <i class="ti ti-credit-card"></i>
                            </div>
                            <div class="floating-card">
                                <i class="ti ti-link"></i>
                            </div>
                            <div class="floating-card">
                                <i class="ti ti-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show custom-alert" role="alert">
                    <div class="alert-content">
                        <i class="ti ti-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show custom-alert" role="alert">
                    <div class="alert-content">
                        <i class="ti ti-alert-circle me-2"></i>
                        <?php echo $_SESSION['error_message']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="quick-actions">
                        <div class="action-card primary-gradient">
                            <div class="action-icon">
                                <i class="ti ti-plus"></i>
                            </div>
                            <div class="action-content">
                                <h5>Create New Link</h5>
                                <p>Generate payment links for workshops</p>
                                <a href="#createLinkSection" class="btn btn-light btn-sm">
                                    <i class="ti ti-arrow-down me-1"></i> Get Started
                                </a>
                            </div>
                        </div>
                        
                        <div class="action-card success-gradient">
                            <div class="action-icon">
                                <i class="ti ti-link"></i>
                            </div>
                            <div class="action-content">
                                <h5>Manage Links</h5>
                                <p>View and manage existing links</p>
                                <a href="instamojo_links.php" class="btn btn-light btn-sm">
                                    <i class="ti ti-arrow-right me-1"></i> View All
                                </a>
                            </div>
                        </div>
                        
                        <div class="action-card info-gradient">
                            <div class="action-icon">
                                <i class="ti ti-credit-card"></i>
                            </div>
                            <div class="action-content">
                                <h5>Payment History</h5>
                                <p>Track all transactions</p>
                                <a href="instamojo_payments.php" class="btn btn-light btn-sm">
                                    <i class="ti ti-arrow-right me-1"></i> View Payments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New Link Section -->
            <div class="row mb-5" id="createLinkSection">
                <div class="col-12">
                    <div class="card modern-card">
                        <div class="card-header modern-header">
                            <div class="header-content">
                                <div class="header-icon">
                                    <i class="ti ti-plus-circle"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Create New Payment Link</h4>
                                    <p class="text-muted mb-0">Select workshops and set payment details</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" class="modern-form">
                                <input type="hidden" name="action" value="create_link">
                                
                                <div class="form-section">
                                    <h5 class="section-title">
                                        <i class="ti ti-info-circle me-2"></i>
                                        Basic Information
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">Link Name *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="ti ti-tag"></i>
                                                    </span>
                                                    <input type="text" class="form-control" name="link_name" required 
                                                           placeholder="e.g., Advanced Workshop Package">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">Amount (₹) *</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">
                                                        <i class="ti ti-currency-rupee"></i>
                                                    </span>
                                                    <input type="number" class="form-control" name="amount" required 
                                                           min="1" step="0.01" placeholder="999.00">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5 class="section-title">
                                        <i class="ti ti-book me-2"></i>
                                        Select Workshops
                                    </h5>
                                    <div class="workshop-grid">
                                        <?php foreach ($workshops as $workshop): ?>
                                            <div class="workshop-card">
                                                <input type="checkbox" name="workshop_ids[]" 
                                                       value="<?php echo $workshop['id']; ?>" 
                                                       id="workshop_<?php echo $workshop['id']; ?>" 
                                                       class="workshop-checkbox">
                                                <label for="workshop_<?php echo $workshop['id']; ?>" class="workshop-label">
                                                    <div class="workshop-content">
                                                        <div class="workshop-header">
                                                            <h6 class="workshop-title"><?php echo htmlspecialchars($workshop['name']); ?></h6>
                                                            <div class="workshop-badge">
                                                                <span class="badge bg-primary">Upcoming</span>
                                                            </div>
                                                        </div>
                                                        <div class="workshop-details">
                                                            <div class="detail-item">
                                                                <i class="ti ti-calendar"></i>
                                                                <span><?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?></span>
                                                            </div>
                                                            <div class="detail-item">
                                                                <i class="ti ti-currency-rupee"></i>
                                                                <span>₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="ti ti-info-circle me-1"></i>
                                        Select one or more workshops for this payment link
                                    </small>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="ti ti-plus me-2"></i>
                                        Create Payment Link
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card modern-card">
                        <div class="card-header modern-header">
                            <div class="header-content">
                                <div class="header-icon">
                                    <i class="ti ti-activity"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Recent Payments</h4>
                                    <p class="text-muted mb-0">Latest payment transactions</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_payments)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ti ti-credit-card"></i>
                                    </div>
                                    <h5>No payments yet</h5>
                                    <p>Payments will appear here once they are processed</p>
                                </div>
                            <?php else: ?>
                                <div class="payment-list">
                                    <?php foreach (array_slice($recent_payments, 0, 5) as $payment): ?>
                                        <div class="payment-item">
                                            <div class="payment-icon">
                                                <i class="ti ti-<?php echo $payment['status'] === 'completed' ? 'check' : 'clock'; ?>"></i>
                                            </div>
                                            <div class="payment-details">
                                                <h6 class="payment-title"><?php echo htmlspecialchars($payment['link_name']); ?></h6>
                                                <p class="payment-info">
                                                    ₹<?php echo number_format($payment['amount'], 2); ?> • 
                                                    <?php echo date('d M Y, h:i A', strtotime($payment['created_at'])); ?>
                                                </p>
                                            </div>
                                            <div class="payment-status">
                                                <span class="status-badge <?php echo $payment['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card modern-card">
                        <div class="card-header modern-header">
                            <div class="header-content">
                                <div class="header-icon">
                                    <i class="ti ti-chart-pie"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Quick Stats</h4>
                                    <p class="text-muted mb-0">Payment overview</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon primary">
                                        <i class="ti ti-link"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3><?php echo count($links); ?></h3>
                                        <p>Total Links</p>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon success">
                                        <i class="ti ti-credit-card"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3><?php echo count($recent_payments); ?></h3>
                                        <p>Total Payments</p>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon warning">
                                        <i class="ti ti-check"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3><?php echo count(array_filter($recent_payments, function($p) { return $p['status'] === 'completed'; })); ?></h3>
                                        <p>Completed</p>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon info">
                                        <i class="ti ti-clock"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3><?php echo count(array_filter($recent_payments, function($p) { return $p['status'] === 'pending'; })); ?></h3>
                                        <p>Pending</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
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

    // Smooth scroll to create link section
    $('a[href="#createLinkSection"]').click(function(e) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $('#createLinkSection').offset().top - 100
        }, 800);
    });
});
</script>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3rem 0;
    border-radius: 0 0 2rem 2rem;
    margin: -1rem -1rem 2rem -1rem;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.gradient-text {
    background: linear-gradient(45deg, #ffd700, #ffed4e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 2rem;
}

.hero-stats {
    display: flex;
    gap: 2rem;
    margin-top: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #ffd700;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
}

.hero-illustration {
    position: relative;
    height: 200px;
}

.floating-card {
    position: absolute;
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    animation: float 3s ease-in-out infinite;
}

.floating-card:nth-child(1) {
    top: 20px;
    left: 20px;
    animation-delay: 0s;
}

.floating-card:nth-child(2) {
    top: 80px;
    right: 40px;
    animation-delay: 1s;
}

.floating-card:nth-child(3) {
    bottom: 20px;
    left: 50px;
    animation-delay: 2s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.action-card {
    padding: 2rem;
    border-radius: 1rem;
    color: white;
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.primary-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.success-gradient {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.info-gradient {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.action-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.9;
}

.action-content h5 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
}

.action-content p {
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

/* Modern Cards */
.modern-card {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.modern-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
}

.modern-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    padding: 1.5rem;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

/* Form Styling */
.modern-form {
    padding: 0;
}

.form-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.75rem;
}

.section-title {
    color: #495057;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.input-group-text {
    background: #e9ecef;
    border: 1px solid #ced4da;
    color: #6c757d;
}

.form-control {
    border: 1px solid #ced4da;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-actions {
    text-align: center;
    padding: 2rem 0;
}

/* Workshop Grid */
.workshop-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.workshop-card {
    position: relative;
    border: 2px solid #e9ecef;
    border-radius: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
    background: white;
    overflow: hidden;
}

.workshop-card:hover {
    border-color: #667eea;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
    transform: translateY(-2px);
}

.workshop-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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

.workshop-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.workshop-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    margin: 0;
    line-height: 1.3;
}

.workshop-badge {
    flex-shrink: 0;
}

.workshop-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.9rem;
}

.detail-item i {
    color: #667eea;
    width: 16px;
}

.workshop-card.selected .workshop-title {
    color: #667eea;
}

.workshop-card.selected::before {
    content: '✓';
    position: absolute;
    top: 10px;
    right: 10px;
    background: #667eea;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    z-index: 2;
}

/* Payment List */
.payment-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.payment-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.75rem;
    transition: background-color 0.3s ease;
}

.payment-item:hover {
    background: #e9ecef;
}

.payment-icon {
    width: 40px;
    height: 40px;
    background: #667eea;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.payment-details {
    flex: 1;
}

.payment-title {
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: #333;
}

.payment-info {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.payment-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.success {
    background: #d4edda;
    color: #155724;
}

.status-badge.warning {
    background: #fff3cd;
    color: #856404;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.75rem;
    transition: background-color 0.3s ease;
}

.stat-card:hover {
    background: #e9ecef;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-icon.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-icon.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-info h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 3rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}

.empty-state h5 {
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #adb5bd;
    margin: 0;
}

/* Custom Alerts */
.custom-alert {
    border: none;
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
}

.alert-content {
    display: flex;
    align-items: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .workshop-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modern-card {
    animation: fadeInUp 0.6s ease-out;
}

.action-card {
    animation: fadeInUp 0.6s ease-out;
}

.action-card:nth-child(2) {
    animation-delay: 0.1s;
}

.action-card:nth-child(3) {
    animation-delay: 0.2s;
}
</style>

</body>
</html> 