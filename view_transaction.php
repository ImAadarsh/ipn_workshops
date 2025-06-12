<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once 'config/config.php';

// Get transaction ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    header("Location: transactions.php");
    exit();
}

// Get payment details with related information
$sql = "SELECT p.*, 
        b.status as booking_status,
        u.first_name as user_first_name, 
        u.last_name as user_last_name,
        u.email as user_email,
        t.first_name as trainer_first_name, 
        t.last_name as trainer_last_name,
        t.email as trainer_email,
        ta.date as booking_date,
        ts.start_time,
        ts.end_time,
        ts.duration_minutes
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        JOIN users u ON b.user_id = u.id
        JOIN time_slots ts ON b.time_slot_id = ts.id
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        JOIN trainers t ON ta.trainer_id = t.id
        WHERE p.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $payment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);

if (!$payment) {
    header("Location: transactions.php");
    exit();
}

// Get user type for permission checks
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Check if user has permission to view this transaction
if ($user_type !== 'admin' && $payment['user_id'] !== $user_id && $payment['trainer_id'] !== $user_id) {
    header("Location: transactions.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>View Transaction | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-failed { background-color: #fee2e2; color: #991b1b; }
        .status-refunded { background-color: #f3e8ff; color: #6b21a8; }
    </style>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <!-- Topbar Start -->
        <?php include 'includes/topbar.php'; ?>
        <!-- Topbar End -->

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->
        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="transactions.php">Transactions</a></li>
                                    <li class="breadcrumb-item active">Transaction Details</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Transaction Details</h4>
                            <div class="mt-3 mt-sm-0">
                                <a href="transactions.php" class="btn btn-outline-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Transactions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-xxl-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">Transaction Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Transaction ID:</strong> #<?php echo $payment['id']; ?></p>
                                            <p class="mb-1"><strong>Amount:</strong> ₹<?php echo number_format($payment['amount'], 2); ?></p>
                                            <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($payment['payment_method']); ?></p>
                                            <p class="mb-1"><strong>Transaction ID:</strong> <?php echo $payment['transaction_id'] ?? 'N/A'; ?></p>
                                            <p class="mb-1">
                                                <strong>Status:</strong> 
                                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </p>
                                            <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($payment['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">Booking Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Booking ID:</strong> #<?php echo $payment['booking_id']; ?></p>
                                            <p class="mb-1">
                                                <strong>Booking Status:</strong>
                                                <span class="status-badge status-<?php echo $payment['booking_status']; ?>">
                                                    <?php echo ucfirst($payment['booking_status']); ?>
                                                </span>
                                            </p>
                                            <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($payment['booking_date'])); ?></p>
                                            <p class="mb-1"><strong>Time:</strong> <?php echo date('h:i A', strtotime($payment['start_time'])) . ' - ' . date('h:i A', strtotime($payment['end_time'])); ?></p>
                                            <p class="mb-1"><strong>Duration:</strong> <?php echo $payment['duration_minutes']; ?> minutes</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">User Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Name:</strong> <?php echo $payment['user_first_name'] . ' ' . $payment['user_last_name']; ?></p>
                                            <p class="mb-1"><strong>Email:</strong> <?php echo $payment['user_email']; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">Trainer Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Name:</strong> <?php echo $payment['trainer_first_name'] . ' ' . $payment['trainer_last_name']; ?></p>
                                            <p class="mb-1"><strong>Email:</strong> <?php echo $payment['trainer_email']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-muted fs-13 text-uppercase">Actions</h5>
                                <div class="mt-3">
                                    <?php if ($user_type === 'admin'): ?>
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <button class="btn btn-success w-100 mb-2" onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'completed')">
                                                <i class="ti ti-check me-1"></i> Mark as Completed
                                            </button>
                                            <button class="btn btn-danger w-100 mb-2" onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'failed')">
                                                <i class="ti ti-x me-1"></i> Mark as Failed
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($payment['status'] === 'completed'): ?>
                                            <button class="btn btn-warning w-100 mb-2" onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'refunded')">
                                                <i class="ti ti-receipt-refund me-1"></i> Issue Refund
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <h5 class="text-muted fs-13 text-uppercase">Timeline</h5>
                                <div class="mt-3">
                                    <div class="timeline-alt pb-0">
                                        <div class="timeline-item">
                                            <i class="ti ti-calendar bg-info-subtle text-info timeline-icon"></i>
                                            <div class="timeline-item-info">
                                                <a href="javascript:void(0);" class="text-body fw-semibold mb-1 d-block">Transaction Created</a>
                                                <p class="mb-0 pb-2">
                                                    <small class="text-muted"><?php echo date('j M Y, g:i a', strtotime($payment['created_at'])); ?></small>
                                                </p>
                                            </div>
                                        </div>

                                        <?php if ($payment['status'] !== 'pending'): ?>
                                        <div class="timeline-item">
                                            <i class="ti ti-<?php echo $payment['status'] === 'completed' ? 'check' : ($payment['status'] === 'failed' ? 'x' : 'receipt-refund'); ?> 
                                               bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'warning'); ?>-subtle 
                                               text-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'warning'); ?> 
                                               timeline-icon"></i>
                                            <div class="timeline-item-info">
                                                <a href="javascript:void(0);" class="text-body fw-semibold mb-1 d-block">
                                                    Payment <?php echo ucfirst($payment['status']); ?>
                                                </a>
                                                <p class="mb-0 pb-2">
                                                    <small class="text-muted"><?php echo date('j M Y, g:i a', strtotime($payment['updated_at'])); ?></small>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

    <script>
        function updatePaymentStatus(paymentId, status) {
            if (!confirm('Are you sure you want to mark this payment as ' + status + '?')) {
                return;
            }

            fetch('controllers/update_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `payment_id=${paymentId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update payment status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the payment status');
            });
        }
    </script>
</body>
</html> 