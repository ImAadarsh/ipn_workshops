<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

// Instamojo API credentials
$instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
$instamojo_base_url = 'https://api.instamojo.com/v2/';
$instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';

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

// Function to update Instamojo payment request status
function updateInstamojoPaymentRequest($payment_request_id, $new_status, $access_token, $base_url) {
    // For Instamojo v2, we need to use specific endpoints for enable/disable
    if ($new_status === 'inactive') {
        // Disable the payment request
        $endpoint = $base_url . 'payment_requests/' . $payment_request_id . '/disable/';
        $method = 'POST';
        $data = null; // No data needed for disable
    } else {
        // Enable the payment request
        $endpoint = $base_url . 'payment_requests/' . $payment_request_id . '/enable/';
        $method = 'POST';
        $data = null; // No data needed for enable
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ]);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("Instamojo API Update - Payment Request ID: $payment_request_id, New Status: $new_status, Endpoint: $endpoint, HTTP Code: $http_code, Response: $response");
    if ($curl_error) {
        error_log("Instamojo API Update - Curl Error: $curl_error");
    }
    
    return [
        'success' => ($http_code === 200 || $http_code === 204),
        'http_code' => $http_code,
        'response' => $response,
        'curl_error' => $curl_error
    ];
}

// Function to delete Instamojo payment request
function deleteInstamojoPaymentRequest($payment_request_id, $access_token, $base_url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . 'payment_requests/' . $payment_request_id . '/');
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("Instamojo API Delete - Payment Request ID: $payment_request_id, HTTP Code: $http_code, Response: $response");
    if ($curl_error) {
        error_log("Instamojo API Delete - Curl Error: $curl_error");
    }
    
    return [
        'success' => ($http_code === 204 || $http_code === 200),
        'http_code' => $http_code,
        'response' => $response,
        'curl_error' => $curl_error
    ];
}

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $link_id = intval($_POST['link_id']);
    $new_status = $_POST['new_status'];
    
    // Get the payment request details from our database
    $get_link_sql = "SELECT instamojo_link_id FROM instamojo_links WHERE id = ?";
    $get_link_stmt = mysqli_prepare($conn, $get_link_sql);
    mysqli_stmt_bind_param($get_link_stmt, "i", $link_id);
    mysqli_stmt_execute($get_link_stmt);
    $link_result = mysqli_stmt_get_result($get_link_stmt);
    $link_data = mysqli_fetch_assoc($link_result);
    mysqli_stmt_close($get_link_stmt);
    
    if ($link_data && $link_data['instamojo_link_id']) {
        // Get OAuth2 access token
        $access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);
        
        if ($access_token) {
            // Update the payment request on Instamojo
            $instamojo_result = updateInstamojoPaymentRequest(
                $link_data['instamojo_link_id'], 
                $new_status, 
                $access_token, 
                $instamojo_base_url
            );
            
            if ($instamojo_result['success']) {
                // Update our database
                $sql = "UPDATE instamojo_links SET status = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $new_status, $link_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Link status updated successfully on both Instamojo and database!";
                } else {
                    $_SESSION['error_message'] = "Link updated on Instamojo but failed to update database.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error_message'] = "Failed to update link on Instamojo. HTTP Code: " . $instamojo_result['http_code'];
                if ($instamojo_result['curl_error']) {
                    $_SESSION['error_message'] .= ", Error: " . $instamojo_result['curl_error'];
                }
                $_SESSION['error_message'] .= "<br><strong>Payment Request ID:</strong> " . $link_data['instamojo_link_id'];
                $_SESSION['error_message'] .= "<br><strong>Response:</strong> " . $instamojo_result['response'];
            }
        } else {
            $_SESSION['error_message'] = "Failed to get Instamojo access token. Please check your API credentials.";
        }
    } else {
        $_SESSION['error_message'] = "Payment link not found or missing Instamojo ID.";
    }
}

// Function to test Instamojo API connection and list payment requests
function testInstamojoAPI($access_token, $base_url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . 'payment_requests/');
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("Instamojo API Test - HTTP Code: $http_code, Response: $response");
    if ($curl_error) {
        error_log("Instamojo API Test - Curl Error: $curl_error");
    }
    
    return [
        'success' => ($http_code === 200),
        'http_code' => $http_code,
        'response' => $response,
        'curl_error' => $curl_error
    ];
}

// Handle update local database only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_local_only') {
    $link_id = intval($_POST['link_id']);
    $new_status = $_POST['new_status'];
    
    // Update only the local database
    $sql = "UPDATE instamojo_links SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $new_status, $link_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Link status updated in local database only. Note: This change was not synced with Instamojo due to API issues.";
    } else {
        $_SESSION['error_message'] = "Failed to update local database.";
    }
    mysqli_stmt_close($stmt);
    
    header("Location: instamojo_links.php");
    exit();
}

// Handle soft delete payment link (disable on Instamojo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_link') {
    $link_id = intval($_POST['link_id']);
    
    // Get the payment request details from our database
    $get_link_sql = "SELECT instamojo_link_id FROM instamojo_links WHERE id = ? AND is_deleted = 0";
    $get_link_stmt = mysqli_prepare($conn, $get_link_sql);
    mysqli_stmt_bind_param($get_link_stmt, "i", $link_id);
    mysqli_stmt_execute($get_link_stmt);
    $link_result = mysqli_stmt_get_result($get_link_stmt);
    $link_data = mysqli_fetch_assoc($link_result);
    mysqli_stmt_close($get_link_stmt);
    
    if ($link_data && $link_data['instamojo_link_id']) {
        // Get OAuth2 access token
        $access_token = getInstamojoAccessToken($instamojo_client_id, $instamojo_client_secret, $instamojo_oauth_url);
        
        if ($access_token) {
            // Disable the payment request on Instamojo instead of deleting
            $instamojo_result = updateInstamojoPaymentRequest(
                $link_data['instamojo_link_id'], 
                'inactive', 
                $access_token, 
                $instamojo_base_url
            );
            
            if ($instamojo_result['success']) {
                // Soft delete from our database (set is_deleted = 1)
                $sql = "UPDATE instamojo_links SET is_deleted = 1, updated_at = NOW() WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $link_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Payment link disabled on Instamojo and removed from database!";
                } else {
                    $_SESSION['error_message'] = "Link disabled on Instamojo but failed to update database.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error_message'] = "Failed to disable link on Instamojo. HTTP Code: " . $instamojo_result['http_code'];
                if ($instamojo_result['curl_error']) {
                    $_SESSION['error_message'] .= ", Error: " . $instamojo_result['curl_error'];
                }
                $_SESSION['error_message'] .= "<br><strong>Payment Request ID:</strong> " . $link_data['instamojo_link_id'];
                $_SESSION['error_message'] .= "<br><strong>Response:</strong> " . $instamojo_result['response'];
            }
        } else {
            $_SESSION['error_message'] = "Failed to get Instamojo access token. Please check your API credentials.";
        }
    } else {
        $_SESSION['error_message'] = "Payment link not found or missing Instamojo ID.";
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$amount_filter = isset($_GET['amount']) ? $_GET['amount'] : '';

// Build query
$where_conditions = ["il.is_deleted = 0"]; // Only show non-deleted links
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(il.link_name LIKE ? OR il.instamojo_link_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "il.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(il.created_at) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

if (!empty($amount_filter)) {
    switch ($amount_filter) {
        case '0-100':
            $where_conditions[] = "il.amount BETWEEN 0 AND 100";
            break;
        case '100-500':
            $where_conditions[] = "il.amount BETWEEN 100 AND 500";
            break;
        case '500-1000':
            $where_conditions[] = "il.amount BETWEEN 500 AND 1000";
            break;
        case '1000+':
            $where_conditions[] = "il.amount >= 1000";
            break;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM instamojo_links il $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_count = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
    $total_count = mysqli_fetch_assoc($count_result)['total'];
}

$total_pages = ceil($total_count / $per_page);

// Fetch existing links
$links_sql = "SELECT il.*, il.workshop_ids
              FROM instamojo_links il
              $where_clause
              ORDER BY il.created_at DESC 
              LIMIT ? OFFSET ?";
$links_stmt = mysqli_prepare($conn, $links_sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    mysqli_stmt_bind_param($links_stmt, $param_types, ...$params);
} else {
    mysqli_stmt_bind_param($links_stmt, 'ii', $per_page, $offset);
}

mysqli_stmt_execute($links_stmt);
$links_result = mysqli_stmt_get_result($links_stmt);
$links = [];
while ($row = mysqli_fetch_assoc($links_result)) {
    $links[] = $row;
}
mysqli_stmt_close($links_stmt);

$page_title = "Payment Links";
include 'includes/head.php';
?>

<div class="page-wrapper">
    <?php include 'includes/sidenav.php'; ?>
    
    <div class="page-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="container-fluid">
            <div class="row mt-2 mb-4">
                <div class="col-12">
                    <div class="page-title-box d-flex align-items-center justify-content-between">
                        <h4 class="mb-0">Payment Links</h4>
                        <div>
                            <a href="instamojo_dashboard.php" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i> Create New Link
                            </a>
                        </div>
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

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by link name or Instamojo ID">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" name="date" 
                                           value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Amount Range</label>
                                    <select class="form-select" name="amount">
                                        <option value="">All Amounts</option>
                                        <option value="0-100" <?php echo $amount_filter === '0-100' ? 'selected' : ''; ?>>₹0 - ₹100</option>
                                        <option value="100-500" <?php echo $amount_filter === '100-500' ? 'selected' : ''; ?>>₹100 - ₹500</option>
                                        <option value="500-1000" <?php echo $amount_filter === '500-1000' ? 'selected' : ''; ?>>₹500 - ₹1000</option>
                                        <option value="1000+" <?php echo $amount_filter === '1000+' ? 'selected' : ''; ?>>₹1000+</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-search me-1"></i> Filter
                                        </button>
                                        <a href="instamojo_links.php" class="btn btn-outline-secondary">
                                            <i class="ti ti-refresh me-1"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Summary -->
            <?php if (!empty($search) || !empty($status_filter) || !empty($date_filter) || !empty($amount_filter)): ?>
            <div class="alert alert-info mb-3">
                <i class="ti ti-info-circle me-1"></i>
                <strong>Filtered Results:</strong> 
                <?php echo $total_count; ?> link(s) found
                <?php if (!empty($search)): ?> • Search: "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                <?php if (!empty($status_filter)): ?> • Status: <?php echo ucfirst($status_filter); ?><?php endif; ?>
                <?php if (!empty($date_filter)): ?> • Date: <?php echo $date_filter; ?><?php endif; ?>
                <?php if (!empty($amount_filter)): ?> • Amount: <?php echo $amount_filter; ?><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Payment Links -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Existing Payment Links</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($links)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="ti ti-link-off fs-1"></i>
                                    <p class="mt-2">No payment links created yet.</p>
                                    <a href="instamojo_dashboard.php" class="btn btn-primary">
                                        <i class="ti ti-plus me-1"></i> Create Your First Link
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Link Name</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($links as $link): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <a href="#" class="text-primary fw-bold" 
                                                               onclick="viewLinkWorkshops(<?php echo $link['id']; ?>, '<?php echo htmlspecialchars($link['workshop_ids']); ?>')"
                                                               title="View Workshops">
                                                                <?php echo htmlspecialchars($link['link_name']); ?>
                                                                <i class="ti ti-external-link ms-1"></i>
                                                            </a>
                                                            <?php if ($link['instamojo_link_id']): ?>
                                                                <br><small class="text-muted">ID: <?php echo htmlspecialchars($link['instamojo_link_id']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold fs-5">₹<?php echo number_format($link['amount'], 2); ?></span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <span class="badge <?php echo $link['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?> fs-6">
                                                                <?php echo ucfirst($link['status']); ?>
                                                            </span>
                                                            <?php if ($link['instamojo_link_id']): ?>
                                                                <br><small class="text-muted">
                                                                    <i class="ti ti-check text-success me-1"></i>Synced with Instamojo
                                                                </small>
                                                            <?php else: ?>
                                                                <br><small class="text-warning">
                                                                    <i class="ti ti-alert-triangle me-1"></i>Not synced
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <div><?php echo date('d M Y', strtotime($link['created_at'])); ?></div>
                                                            <small class="text-muted"><?php echo date('h:i A', strtotime($link['created_at'])); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <?php if ($link['instamojo_link_url']): ?>
                                                                <a href="<?php echo $link['instamojo_link_url']; ?>" 
                                                                   target="_blank" class="btn btn-sm btn-outline-primary" 
                                                                   title="View Payment Link">
                                                                    <i class="ti ti-external-link"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                                        onclick="copyToClipboard('<?php echo $link['instamojo_link_url']; ?>')"
                                                                        title="Copy Link">
                                                                    <i class="ti ti-copy"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                                                <input type="hidden" name="new_status" 
                                                                       value="<?php echo $link['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-warning" 
                                                                        title="<?php echo $link['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Link on Instamojo"
                                                                        onclick="return confirm('Are you sure you want to <?php echo $link['status'] === 'active' ? 'deactivate' : 'activate'; ?> this payment link? This will update the status on Instamojo as well.')">
                                                                    <i class="ti ti-toggle-left"></i>
                                                                    <?php echo $link['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deletePaymentLink(<?php echo $link['id']; ?>, '<?php echo htmlspecialchars($link['link_name']); ?>')"
                                                                    title="Delete Payment Link">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center mt-4">
                                    <nav>
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>&amount=<?php echo urlencode($amount_filter); ?>">
                                                        Previous
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>&amount=<?php echo urlencode($amount_filter); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>&amount=<?php echo urlencode($amount_filter); ?>">
                                                        Next
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Link Workshops Modal -->
<div class="modal fade" id="linkWorkshopsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Workshops in Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="linkWorkshopsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-header">
                    <i class="ti ti-check text-success me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    Payment link copied to clipboard!
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 3000);
    });
}

function deletePaymentLink(linkId, linkName) {
    if (confirm(`Are you sure you want to delete the payment link "${linkName}"?\n\nThis action will:\n• Disable the link on Instamojo\n• Mark it as deleted in your database (soft delete)\n• This action cannot be undone!`)) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_link';
        
        const linkIdInput = document.createElement('input');
        linkIdInput.type = 'hidden';
        linkIdInput.name = 'link_id';
        linkIdInput.value = linkId;
        
        form.appendChild(actionInput);
        form.appendChild(linkIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function updateLocalOnly(linkId, newStatus) {
    if (confirm(`Update link status in local database only?\n\nThis will:\n• Update the status in your database\n• NOT sync with Instamojo (due to API issues)\n• The payment link will remain unchanged on Instamojo`)) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'update_local_only';
        
        const linkIdInput = document.createElement('input');
        linkIdInput.type = 'hidden';
        linkIdInput.name = 'link_id';
        linkIdInput.value = linkId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'new_status';
        statusInput.value = newStatus;
        
        form.appendChild(actionInput);
        form.appendChild(linkIdInput);
        form.appendChild(statusInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function viewLinkWorkshops(linkId, workshopIds) {
    const modal = new bootstrap.Modal(document.getElementById('linkWorkshopsModal'));
    const content = document.getElementById('linkWorkshopsContent');
    
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading workshops...</p></div>';
    modal.show();
    
    // Load workshop details via AJAX
    fetch(`get_link_workshops.php?workshop_ids=${encodeURIComponent(workshopIds)}&link_id=${linkId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading workshop details.</div>';
        });
}
</script>

<style>
.workshop-list {
    max-height: 120px;
    overflow-y: auto;
}

.workshop-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}
</style>

</body>
</html>