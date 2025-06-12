<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Start session if not already started
session_start();
// Include database connection
require_once 'config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Check if coupon ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Coupon ID is required.";
    header('Location: coupons.php');
    exit;
}

$coupon_id = intval($_GET['id']);

// Fetch coupon details
$query = "SELECT * FROM coupons WHERE id = ?";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    $_SESSION['error_message'] = "Error preparing query: " . $conn->error;
    header('Location: coupons.php');
    exit;
}

$stmt->bind_param('i', $coupon_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Coupon not found.";
    header('Location: coupons.php');
    exit;
}

$coupon = $result->fetch_assoc();

// Fetch assigned trainers
$trainers_query = "SELECT t.id, t.first_name, t.last_name 
                  FROM trainers t 
                  JOIN trainer_coupons tc ON t.id = tc.trainer_id 
                  WHERE tc.coupon_id = ?";
$stmt = $conn->prepare($trainers_query);
if ($stmt === false) {
    // Just log the error and continue with empty assigned trainers
    error_log("Error preparing trainers query: " . $conn->error);
    $assigned_trainers = [];
} else {
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $trainers_result = $stmt->get_result();
    $assigned_trainers = [];

    if ($trainers_result->num_rows > 0) {
        while ($row = $trainers_result->fetch_assoc()) {
            $assigned_trainers[] = $row;
        }
    }
}

// Fetch assigned users
$users_query = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name 
               FROM users u 
               JOIN user_coupons uc ON u.id = uc.user_id 
               WHERE uc.coupon_id = ?";
$stmt = $conn->prepare($users_query);
if ($stmt === false) {
    // Just log the error and continue with empty assigned users
    error_log("Error preparing users query: " . $conn->error);
    $assigned_users = [];
} else {
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $users_result = $stmt->get_result();
    $assigned_users = [];

    if ($users_result->num_rows > 0) {
        while ($row = $users_result->fetch_assoc()) {
            $assigned_users[] = $row;
        }
    }
}

// Fetch usage history
$usage_query = "SELECT cu.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, b.id AS booking_id 
                FROM coupon_usage cu 
                LEFT JOIN users u ON cu.user_id = u.id 
                LEFT JOIN bookings b ON cu.booking_id = b.id 
                WHERE cu.coupon_id = ? 
                ORDER BY cu.created_at DESC";
$stmt = $conn->prepare($usage_query);
if ($stmt === false) {
    // Just log the error and continue with empty usage history
    error_log("Error preparing usage query: " . $conn->error);
    $usage_history = [];
} else {
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $usage_result = $stmt->get_result();
    $usage_history = [];

    if ($usage_result->num_rows > 0) {
        while ($row = $usage_result->fetch_assoc()) {
            $usage_history[] = $row;
        }
    }
}

// Include header
include 'includes/head.php';
?>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <!-- ========== Topbar Start ========== -->
        <?php include 'includes/topbar.php'; ?>
        <!-- ========== Topbar End ========== -->

        <!-- ========== Left Sidebar Start ========== -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- ========== Left Sidebar End ========== -->

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->

        <div class="page-content">
            <div class="page-container">

                <!-- Start Content-->
                <div class="container-fluid">

                    <!-- start page title -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="page-title-box">
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0 mb-2">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="coupons.php">Coupons</a></li>
                                        <li class="breadcrumb-item active">Coupon Details</li>
                                    </ol>
                                    <a href="coupon_edit.php?id=<?php echo $coupon_id; ?>" class="btn btn-info me-1"><i class="ti ti-edit"></i> Edit Coupon</a>
                                    <a href="coupons.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back to Coupons</a>
                                </div>
                                <h4 class="page-title mt-3">Coupon Details</h4>
                            </div>
                        </div>
                    </div>
                    <!-- end page title -->

                    <div class="row">
                        <div class="col-xl-8 col-lg-7">
                            <!-- Coupon Details Card -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h4 class="mt-0"><?php echo htmlspecialchars($coupon['code']); ?></h4>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($coupon['description']); ?></p>
                                        </div>
                                        <div class="badge <?php echo $coupon['is_active'] ? 'bg-success' : 'bg-danger'; ?> p-1">
                                            <?php echo $coupon['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14">Discount</h5>
                                                <p class="text-muted mb-0">
                                                    <?php if ($coupon['discount_type'] == 'flat'): ?>
                                                        <span class="badge bg-primary">Flat</span> $<?php echo number_format($coupon['discount_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Percentage</span> <?php echo $coupon['discount_amount']; ?>%
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14">Minimum Purchase</h5>
                                                <p class="text-muted mb-0">
                                                    <?php if ($coupon['min_purchase_amount'] > 0): ?>
                                                        $<?php echo number_format($coupon['min_purchase_amount'], 2); ?>
                                                    <?php else: ?>
                                                        No minimum
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14">Valid Period</h5>
                                                <p class="text-muted mb-0">
                                                    <?php echo date('M d, Y', strtotime($coupon['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($coupon['end_date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14">Usage Limit</h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1">
                                                        <p class="text-muted mb-0">
                                                            <?php echo $coupon['current_uses']; ?> / 
                                                            <?php echo $coupon['max_uses'] ? $coupon['max_uses'] : '∞'; ?>
                                                        </p>
                                                    </div>
                                                    <?php if ($coupon['max_uses']): ?>
                                                        <div class="progress" style="width: 120px; height: 6px;">
                                                            <div class="progress-bar" role="progressbar" 
                                                                style="width: <?php echo min(($coupon['current_uses'] / $coupon['max_uses']) * 100, 100); ?>%" 
                                                                aria-valuenow="<?php echo $coupon['current_uses']; ?>" aria-valuemin="0" 
                                                                aria-valuemax="<?php echo $coupon['max_uses']; ?>">
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14">Created</h5>
                                                <p class="text-muted mb-0">
                                                    <?php echo date('M d, Y H:i', strtotime($coupon['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14">Last Updated</h5>
                                                <p class="text-muted mb-0">
                                                    <?php echo date('M d, Y H:i', strtotime($coupon['updated_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <h5 class="font-size-14">Coupon Status</h5>
                                        <?php
                                        $now = new DateTime();
                                        $start_date = new DateTime($coupon['start_date']);
                                        $end_date = new DateTime($coupon['end_date']);
                                        $is_expired = $now > $end_date;
                                        $not_started = $now < $start_date;
                                        $max_reached = $coupon['max_uses'] && $coupon['current_uses'] >= $coupon['max_uses'];
                                        ?>
                                        
                                        <?php if (!$coupon['is_active']): ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php elseif ($is_expired): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php elseif ($not_started): ?>
                                            <span class="badge bg-warning">Not Started Yet</span>
                                        <?php elseif ($max_reached): ?>
                                            <span class="badge bg-warning">Usage Limit Reached</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Usage History -->
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-3">Usage History</h4>

                                    <div class="table-responsive">
                                        <table class="table table-centered table-nowrap table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Booking</th>
                                                    <th>Discount</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($usage_history)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center">No usage history found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($usage_history as $usage): ?>
                                                        <tr>
                                                            <td>
                                                                <a href="edit_user.php?id=<?php echo $usage['user_id']; ?>" class="text-body">
                                                                    <?php echo htmlspecialchars($usage['user_name']); ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <a href="view_booking.php?id=<?php echo $usage['booking_id']; ?>" class="text-body">
                                                                    #<?php echo $usage['booking_id']; ?>
                                                                </a>
                                                            </td>
                                                            <td>$<?php echo number_format($usage['discount_amount'], 2); ?></td>
                                                            <td><?php echo date('M d, Y H:i', strtotime($usage['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-lg-5">
                            <!-- Coupon Assignments -->
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-3">Coupon Assignments</h4>

                                    <?php if (empty($assigned_trainers) && empty($assigned_users)): ?>
                                        <div class="alert alert-info">
                                            This is a global coupon available to all users.
                                        </div>
                                    <?php else: ?>
                                        <!-- Trainer Assignments -->
                                        <?php if (!empty($assigned_trainers)): ?>
                                            <h5 class="font-size-14 mb-2">Assigned Trainers</h5>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-centered table-nowrap table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach ($assigned_trainers as $trainer): ?>
                                                            <tr>
                                                                <td>
                                                                    <a href="edit_trainer.php?id=<?php echo $trainer['id']; ?>" class="text-body">
                                                                        <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>

                                        <!-- User Assignments -->
                                        <?php if (!empty($assigned_users)): ?>
                                            <h5 class="font-size-14 mb-2">Assigned Users</h5>
                                            <div class="table-responsive">
                                                <table class="table table-centered table-nowrap table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach ($assigned_users as $user): ?>
                                                            <tr>
                                                                <td>
                                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="text-body">
                                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="header-title mb-3">Quick Actions</h4>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="coupon_edit.php?id=<?php echo $coupon_id; ?>" class="btn btn-primary">
                                            <i class="ti ti-edit me-1"></i> Edit Coupon
                                        </a>
                                        
                                        <?php if ($coupon['is_active']): ?>
                                            <a href="coupons.php?toggle=<?php echo $coupon_id; ?>" class="btn btn-warning">
                                                <i class="ti ti-power-off me-1"></i> Deactivate Coupon
                                            </a>
                                        <?php else: ?>
                                            <a href="coupons.php?toggle=<?php echo $coupon_id; ?>" class="btn btn-success">
                                                <i class="ti ti-power me-1"></i> Activate Coupon
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="coupons.php?delete=<?php echo $coupon_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this coupon?')">
                                            <i class="ti ti-trash me-1"></i> Delete Coupon
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->
                </div> <!-- container -->

            </div> <!-- content -->

            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->

        </div>

        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->

    </div>
    <!-- END wrapper -->

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>
    
    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

</body>
</html> 