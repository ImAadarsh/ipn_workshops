<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

$success_message = '';
$error_message = '';
$preview_data = [];
$total_affected = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'preview':
                // Preview the changes that will be made
                $sql = "SELECT id, name, email, mobile 
                        FROM users 
                        WHERE mobile LIKE '0%' 
                        AND mobile IS NOT NULL 
                        AND mobile != '' 
                        ORDER BY id ASC";
                $result = mysqli_query($conn, $sql);
                
                if (!$result) {
                    $error_message = "Error fetching data: " . mysqli_error($conn);
                } else {
                    $total_affected = mysqli_num_rows($result);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $new_mobile = ltrim($row['mobile'], '0'); // Remove leading zeros
                        $preview_data[] = [
                            'id' => $row['id'],
                            'name' => $row['name'],
                            'email' => $row['email'],
                            'old_mobile' => $row['mobile'],
                            'new_mobile' => $new_mobile
                        ];
                    }
                }
                break;
                
            case 'execute':
                // Execute the bulk update
                $sql = "UPDATE users 
                        SET mobile = TRIM(LEADING '0' FROM mobile), 
                            updated_at = NOW() 
                        WHERE mobile LIKE '0%' 
                        AND mobile IS NOT NULL 
                        AND mobile != ''";
                
                if (mysqli_query($conn, $sql)) {
                    $total_affected = mysqli_affected_rows($conn);
                    $success_message = "Successfully updated $total_affected users' mobile numbers!";
                } else {
                    $error_message = "Error updating mobile numbers: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get current count of users with mobile starting with 0
$count_sql = "SELECT COUNT(*) as count FROM users WHERE mobile LIKE '0%' AND mobile IS NOT NULL AND mobile != ''";
$count_result = mysqli_query($conn, $count_sql);
$current_count = mysqli_fetch_assoc($count_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Bulk Fix Mobile Numbers | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .preview-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .mobile-change {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 8px 12px;
            margin: 4px 0;
        }
        .old-mobile {
            text-decoration: line-through;
            color: #dc3545;
        }
        .new-mobile {
            color: #198754;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Bulk Fix Mobile Numbers</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="bad_users.php" class="btn btn-primary me-2">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Bad Users
                                </a>
                                <a href="user_management.php" class="btn btn-secondary">
                                    <i class="ti ti-users me-1"></i> User Management
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="ti ti-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Current Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card preview-card">
                            <div class="card-header bg-warning text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-alert-triangle me-2"></i>Current Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-warning">Users with Mobile Starting with 0</h6>
                                        <h3 class="text-warning fw-bold"><?php echo number_format($current_count); ?></h3>
                                        <p class="text-muted mb-0">These users have mobile numbers that start with 0</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-info">What This Tool Does</h6>
                                        <ul class="text-muted mb-0">
                                            <li>Removes leading zeros from mobile numbers</li>
                                            <li>Example: 09876543210 → 9876543210</li>
                                            <li>Safe operation - only removes leading zeros</li>
                                            <li>Updates the <code>updated_at</code> timestamp</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card preview-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-tools me-2"></i>Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="preview">
                                            <button type="submit" class="btn btn-info btn-lg w-100">
                                                <i class="ti ti-eye me-2"></i>Preview Changes
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($preview_data)): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to update <?php echo count($preview_data); ?> users? This action cannot be undone!')">
                                                <input type="hidden" name="action" value="execute">
                                                <button type="submit" class="btn btn-success btn-lg w-100">
                                                    <i class="ti ti-check me-2"></i>Execute Update
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-lg w-100" disabled>
                                                <i class="ti ti-check me-2"></i>Execute Update
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Results -->
                <?php if (!empty($preview_data)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card preview-card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0">
                                        <i class="ti ti-eye me-2"></i>Preview Changes (<?php echo count($preview_data); ?> users)
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <i class="ti ti-info-circle me-2"></i>
                                        <strong>Preview:</strong> This shows what will be changed. Review carefully before executing.
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User ID</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Mobile Number Change</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($preview_data as $user): ?>
                                                    <tr>
                                                        <td><code><?php echo $user['id']; ?></code></td>
                                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                        <td>
                                                            <div class="mobile-change">
                                                                <span class="old-mobile"><?php echo htmlspecialchars($user['old_mobile']); ?></span>
                                                                <i class="ti ti-arrow-right mx-2 text-muted"></i>
                                                                <span class="new-mobile"><?php echo htmlspecialchars($user['new_mobile']); ?></span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Safety Information -->
                <div class="row">
                    <div class="col-12">
                        <div class="card preview-card border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-shield-check me-2"></i>Safety Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-success">What This Tool Does:</h6>
                                        <ul class="text-muted">
                                            <li>Only removes leading zeros from mobile numbers</li>
                                            <li>Preserves the actual phone number</li>
                                            <li>Updates the <code>updated_at</code> timestamp</li>
                                            <li>Only affects users with mobile numbers starting with 0</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-success">Safety Measures:</h6>
                                        <ul class="text-muted">
                                            <li>Preview mode shows exactly what will change</li>
                                            <li>Confirmation dialog before execution</li>
                                            <li>Only processes valid mobile numbers</li>
                                            <li>Safe SQL query with proper conditions</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Start -->
        <?php include 'includes/footer.php'; ?>
        <!-- end Footer -->
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 