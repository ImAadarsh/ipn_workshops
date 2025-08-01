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

// Handle delete payment link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_link') {
    $link_id = intval($_POST['link_id']);
    
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
            // Delete the payment request on Instamojo
            $instamojo_result = deleteInstamojoPaymentRequest(
                $link_data['instamojo_link_id'], 
                $access_token, 
                $instamojo_base_url
            );
            
            if ($instamojo_result['success']) {
                // Delete from our database
                $sql = "DELETE FROM instamojo_links WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $link_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Payment link deleted successfully from both Instamojo and database!";
                } else {
                    $_SESSION['error_message'] = "Link deleted from Instamojo but failed to delete from database.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['error_message'] = "Failed to delete link on Instamojo. HTTP Code: " . $instamojo_result['http_code'];
                if ($instamojo_result['curl_error']) {
                    $_SESSION['error_message'] .= ", Error: " . $instamojo_result['curl_error'];
                }
            }
        } else {
            $_SESSION['error_message'] = "Failed to get Instamojo access token. Please check your API credentials.";
        }
    } else {
        $_SESSION['error_message'] = "Payment link not found or missing Instamojo ID.";
    }
}

// Fetch existing links with workshop details
$links_sql = "SELECT il.*, 
              GROUP_CONCAT(w.name ORDER BY w.start_date ASC SEPARATOR '|') as workshop_names,
              GROUP_CONCAT(w.start_date ORDER BY w.start_date ASC SEPARATOR '|') as workshop_dates
              FROM instamojo_links il
              LEFT JOIN workshops w ON FIND_IN_SET(w.id, il.workshop_ids)
              GROUP BY il.id
              ORDER BY il.created_at DESC";
$links_result = mysqli_query($conn, $links_sql);
$links = [];
while ($row = mysqli_fetch_assoc($links_result)) {
    $links[] = $row;
}

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
                                                <th>Workshops</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($links as $link): ?>
                                                <?php 
                                                $workshop_names = explode('|', $link['workshop_names']);
                                                $workshop_dates = explode('|', $link['workshop_dates']);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($link['link_name']); ?></strong>
                                                            <?php if ($link['instamojo_link_id']): ?>
                                                                <!-- <br><small class="text-muted">Instamojo ID: <?php echo htmlspecialchars($link['instamojo_link_id']); ?></small> -->
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="workshop-list">
                                                            <?php for ($i = 0; $i < count($workshop_names); $i++): ?>
                                                                <?php if (!empty($workshop_names[$i])): ?>
                                                                    <div class="workshop-item mb-1">
                                                                        <span class="badge bg-primary me-1"><?php echo htmlspecialchars($workshop_names[$i]); ?></span>
                                                                        <small class="text-muted"><?php echo date('d M Y', strtotime($workshop_dates[$i])); ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
    if (confirm(`Are you sure you want to delete the payment link "${linkName}"?\n\nThis action will:\n• Delete the link from Instamojo\n• Remove it from your database\n• This action cannot be undone!`)) {
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