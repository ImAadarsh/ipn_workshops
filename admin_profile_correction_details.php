<?php
session_start();
require_once 'config/config.php';

// Check admin authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($request_id <= 0) {
    http_response_code(400);
    exit('Invalid request ID');
}

// Get request details with user information
$sql = "SELECT pcr.*, u.name as current_name, u.email as current_email, u.mobile as current_mobile, u.institute_name as current_institute
        FROM profile_correction_requests pcr 
        LEFT JOIN users u ON pcr.user_id = u.id 
        WHERE pcr.id = $request_id";

$result = mysqli_query($conn, $sql);
$request = mysqli_fetch_assoc($result);

if (!$request) {
    http_response_code(404);
    exit('Request not found');
}

// Get admin who processed the request
$processed_by_name = 'Unknown';
if ($request['processed_by']) {
    $admin_sql = "SELECT name FROM admin_users WHERE id = " . $request['processed_by'];
    $admin_result = mysqli_query($conn, $admin_sql);
    if ($admin_result && $admin = mysqli_fetch_assoc($admin_result)) {
        $processed_by_name = $admin['name'];
    }
}

if ($action === 'changes') {
    // Show changes comparison
    ?>
    <div class="row">
        <div class="col-12">
            <h6 class="mb-3">Profile Changes Comparison</h6>
            <div class="table-responsive">
                <table class="table table-bordered comparison-table">
                    <thead class="table-light">
                        <tr>
                            <th>Field</th>
                            <th>Current Value</th>
                            <th>Requested Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fields = [
                            'name' => 'Name',
                            'email' => 'Email',
                            'mobile' => 'Mobile',
                            'institute_name' => 'Institute Name'
                        ];
                        
                        foreach ($fields as $field => $label):
                            $current_value = $request["current_$field"];
                            $requested_value = $request[$field];
                            $is_changed = $current_value !== $requested_value;
                            $row_class = $is_changed ? 'changed' : 'unchanged';
                            $status_icon = $is_changed ? '<i class="ti ti-edit text-warning"></i> Changed' : '<i class="ti ti-check text-success"></i> No Change';
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><strong><?php echo $label; ?></strong></td>
                                <td><?php echo htmlspecialchars($current_value); ?></td>
                                <td><?php echo htmlspecialchars($requested_value); ?></td>
                                <td><?php echo $status_icon; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <h6>Reason for Changes:</h6>
                <div class="alert alert-info">
                    <i class="ti ti-info-circle me-2"></i>
                    <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
    // Show full request details
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="mb-3">Request Information</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Request ID:</strong></td>
                    <td><?php echo $request['id']; ?></td>
                </tr>
                <tr>
                    <td><strong>User ID:</strong></td>
                    <td><?php echo $request['user_id']; ?></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>
                        <?php
                        $status_class = [
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger'
                        ][$request['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $status_class; ?>">
                            <?php echo ucfirst($request['status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Created:</strong></td>
                    <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                </tr>
                <?php if ($request['processed_at']): ?>
                <tr>
                    <td><strong>Processed:</strong></td>
                    <td><?php echo date('M j, Y g:i A', strtotime($request['processed_at'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Processed By:</strong></td>
                    <td><?php echo htmlspecialchars($processed_by_name); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6 class="mb-3">User Information</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Current Name:</strong></td>
                    <td><?php echo htmlspecialchars($request['current_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>Current Email:</strong></td>
                    <td><?php echo htmlspecialchars($request['current_email']); ?></td>
                </tr>
                <tr>
                    <td><strong>Current Mobile:</strong></td>
                    <td><?php echo htmlspecialchars($request['current_mobile']); ?></td>
                </tr>
                <tr>
                    <td><strong>Current Institute:</strong></td>
                    <td><?php echo htmlspecialchars($request['current_institute']); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="mb-3">Requested Changes</h6>
            <div class="table-responsive">
                <table class="table table-bordered comparison-table">
                    <thead class="table-light">
                        <tr>
                            <th>Field</th>
                            <th>Current Value</th>
                            <th>Requested Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fields = [
                            'name' => 'Name',
                            'email' => 'Email',
                            'mobile' => 'Mobile',
                            'institute_name' => 'Institute Name'
                        ];
                        
                        foreach ($fields as $field => $label):
                            $current_value = $request["current_$field"];
                            $requested_value = $request[$field];
                            $is_changed = $current_value !== $requested_value;
                            $row_class = $is_changed ? 'changed' : 'unchanged';
                            $status_icon = $is_changed ? '<i class="ti ti-edit text-warning"></i> Changed' : '<i class="ti ti-check text-success"></i> No Change';
                        ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><strong><?php echo $label; ?></strong></td>
                                <td><?php echo htmlspecialchars($current_value); ?></td>
                                <td><?php echo htmlspecialchars($requested_value); ?></td>
                                <td><?php echo $status_icon; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="mb-3">Reason for Changes</h6>
            <div class="alert alert-info">
                <i class="ti ti-info-circle me-2"></i>
                <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
            </div>
        </div>
    </div>
    
    <?php if ($request['admin_notes']): ?>
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="mb-3">Admin Notes</h6>
            <div class="alert alert-secondary">
                <i class="ti ti-note me-2"></i>
                <?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($request['status'] === 'pending'): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success" onclick="approveRequest(<?php echo $request['id']; ?>)">
                    <i class="ti ti-check me-1"></i>Approve Request
                </button>
                <button type="button" class="btn btn-danger" onclick="rejectRequest(<?php echo $request['id']; ?>)">
                    <i class="ti ti-x me-1"></i>Reject Request
                </button>
                <a href="user_details.php?id=<?php echo $request['user_id']; ?>" class="btn btn-outline-primary" target="_blank">
                    <i class="ti ti-user me-1"></i>View User Profile
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <style>
        .comparison-table {
            font-size: 0.9rem;
        }
        .comparison-table .changed {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .comparison-table .unchanged {
            background-color: #f8f9fa;
        }
    </style>
    <?php
}
?>
