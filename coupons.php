<?php
session_start();
// Include database connection
require_once 'config/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Handle coupon deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $coupon_id = $_GET['delete'];
    
    // Delete coupon
    $delete_query = "DELETE FROM coupons WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $coupon_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Coupon deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Error deleting coupon: " . $conn->error;
    }
    
    header('Location: coupons.php');
    exit;
}

// Handle coupon activation/deactivation
if (isset($_GET['toggle']) && !empty($_GET['toggle'])) {
    $coupon_id = $_GET['toggle'];
    
    // Get current status
    $status_query = "SELECT is_active FROM coupons WHERE id = ?";
    $stmt = $conn->prepare($status_query);
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $coupon = $result->fetch_assoc();
    
    // Toggle status
    $new_status = $coupon['is_active'] ? 0 : 1;
    
    $update_query = "UPDATE coupons SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ii', $new_status, $coupon_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Coupon status updated successfully.";
    } else {
        $_SESSION['error_message'] = "Error updating coupon status: " . $conn->error;
    }
    
    header('Location: coupons.php');
    exit;
}

// Fetch all coupons
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM trainer_coupons WHERE coupon_id = c.id) AS trainer_count,
          (SELECT COUNT(*) FROM user_coupons WHERE coupon_id = c.id) AS user_count
          FROM coupons c ORDER BY created_at DESC";
$result = $conn->query($query);
$coupons = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $coupons[] = $row;
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
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Coupon Management</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">Coupon Management</h4>
                            </div>
                        </div>
                    </div>
                    <!-- end page title -->

                    <div class="row">
                        <div class="col-12">
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php 
                                    echo $_SESSION['success_message']; 
                                    unset($_SESSION['success_message']);
                                    ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php 
                                    echo $_SESSION['error_message']; 
                                    unset($_SESSION['error_message']);
                                    ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-centered table-striped dt-responsive nowrap w-100" id="coupons-datatable">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Description</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Valid Period</th>
                                                    <th>Usage</th>
                                                    <th>Assignment</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($coupons as $coupon): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($coupon['code']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($coupon['description']); ?></td>
                                                        <td>
                                                            <?php if ($coupon['discount_type'] == 'flat'): ?>
                                                                <span class="badge bg-primary">Flat</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-info">Percentage</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($coupon['discount_type'] == 'flat'): ?>
                                                                $<?php echo number_format($coupon['discount_amount'], 2); ?>
                                                            <?php else: ?>
                                                                <?php echo $coupon['discount_amount']; ?>%
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('M d, Y', strtotime($coupon['start_date'])); ?> - 
                                                            <?php echo date('M d, Y', strtotime($coupon['end_date'])); ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $coupon['current_uses']; ?> / 
                                                            <?php echo $coupon['max_uses'] ? $coupon['max_uses'] : '∞'; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($coupon['trainer_count'] > 0): ?>
                                                                <span class="badge bg-warning">Trainer (<?php echo $coupon['trainer_count']; ?>)</span>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($coupon['user_count'] > 0): ?>
                                                                <span class="badge bg-success">User (<?php echo $coupon['user_count']; ?>)</span>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($coupon['trainer_count'] == 0 && $coupon['user_count'] == 0): ?>
                                                                <span class="badge bg-secondary">Global</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($coupon['is_active']): ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Inactive</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="coupon_edit.php?id=<?php echo $coupon['id']; ?>" class="btn btn-sm btn-info"><i class="ti ti-edit"></i></a>
                                                            <a href="coupons.php?toggle=<?php echo $coupon['id']; ?>" class="btn btn-sm <?php echo $coupon['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                                <i class="ti ti-<?php echo $coupon['is_active'] ? 'power' : 'power'; ?>"></i>
                                                            </a>
                                                            <a href="coupon_view.php?id=<?php echo $coupon['id']; ?>" class="btn btn-sm btn-primary"><i class="ti ti-eye"></i></a>
                                                            <a href="coupons.php?delete=<?php echo $coupon['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this coupon?')"><i class="ti ti-trash"></i></a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                
                                                <?php if (empty($coupons)): ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center">No coupons found</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div> <!-- end card-body-->
                            </div> <!-- end card-->
                        </div> <!-- end col -->
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

    <!-- Datatable js -->
    <script src="assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
    <script src="assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#coupons-datatable').DataTable({
                responsive: true,
                "language": {
                    "paginate": {
                        "previous": "<i class='ti ti-chevron-left'></i>",
                        "next": "<i class='ti ti-chevron-right'></i>"
                    }
                },
                "drawCallback": function () {
                    $('.dataTables_paginate > .pagination').addClass('pagination-rounded');
                }
            });
        });
    </script>

</body>
</html> 