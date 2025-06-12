<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Handle admin status toggle
if (isset($_POST['toggle_status'])) {
    $admin_id = mysqli_real_escape_string($conn, $_POST['admin_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    $update_query = "UPDATE users SET mobile_verified = ? WHERE id = ? AND user_type = 'admin'";
    $stmt = mysqli_prepare($conn, $update_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $new_status, $admin_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($success) {
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    echo json_encode(['status' => 'error']);
    exit;
}

// Handle admin deletion
if (isset($_POST['delete_admin'])) {
    $admin_id = mysqli_real_escape_string($conn, $_POST['admin_id']);
    
    // Don't allow deletion of own account
    if ($admin_id != $_SESSION['user_id']) {
        $delete_query = "DELETE FROM users WHERE id = ? AND user_type = 'admin'";
        $stmt = mysqli_prepare($conn, $delete_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $admin_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_msg'] = "Admin user deleted successfully.";
            } else {
                $_SESSION['error_msg'] = "Failed to delete admin user.";
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: admin_view.php");
        exit();
    }
}

// Get all admin users with proper columns from the users table
$query = "SELECT id, first_name, last_name, email, mobile, mobile_verified as is_active, created_at 
          FROM users 
          WHERE user_type = 'admin' 
          ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// Check for query execution error
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$admins = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Admin Management | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- jQuery -->
    <script src="assets/js/jquery.min.js"></script>
    
    <!-- DataTables CSS -->
    <link href="assets/vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/vendor/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    
    <!-- Toastr CSS -->
    <link href="assets/vendor/toastr/toastr.min.css" rel="stylesheet" type="text/css" />
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success_msg'];
                        unset($_SESSION['success_msg']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error_msg'];
                        unset($_SESSION['error_msg']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Admin Management</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Admin Management</h4>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <a href="add_user.php?type=admin" class="btn btn-primary">
                                            <i class="ti ti-plus me-1"></i> Add New Admin
                                        </a>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table id="admins-table" class="table table-centered table-striped dt-responsive nowrap w-100">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Mobile</th>
                                                <th>Status</th>
                                                <th>Created Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($admins as $admin): ?>
                                                <tr>
                                                    <td>#<?php echo $admin['id']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-grow-1">
                                                                <h5 class="m-0"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h5>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($admin['mobile']); ?></td>
                                                    <td>
                                                        <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                            <div class="form-check form-switch">
                                                                <input type="checkbox" class="form-check-input status-toggle" 
                                                                       data-admin-id="<?php echo $admin['id']; ?>"
                                                                       <?php echo $admin['is_active'] ? 'checked' : ''; ?>>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Current User</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('d M Y', strtotime($admin['created_at'])); ?></td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <a href="edit_user.php?id=<?php echo $admin['id']; ?>&type=admin" 
                                                               class="btn btn-sm btn-soft-primary">
                                                                <i class="ti ti-pencil"></i>
                                                            </a>
                                                            <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-soft-danger delete-admin"
                                                                        data-admin-id="<?php echo $admin['id']; ?>"
                                                                        data-admin-name="<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>">
                                                                    <i class="ti ti-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
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
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this admin user? This action cannot be undone.</p>
                    <p class="text-danger mb-0" id="deleteAdminName"></p>
                </div>
                <div class="modal-footer">
                    <form id="deleteForm" action="admin_view.php" method="POST">
                        <input type="hidden" name="admin_id" id="deleteAdminId">
                        <input type="hidden" name="delete_admin" value="1">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core js -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables js -->
    <script src="assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
    <script src="assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

    <!-- Toastr js -->
    <script src="assets/vendor/toastr/toastr.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            if ($.fn.DataTable) {
                $('#admins-table').DataTable({
                    responsive: true,
                    language: {
                        paginate: {
                            previous: "<i class='ti ti-chevron-left'>",
                            next: "<i class='ti ti-chevron-right'>"
                        }
                    },
                    drawCallback: function() {
                        $('.dataTables_paginate > .pagination').addClass('pagination-rounded');
                    }
                });
            } else {
                console.error('DataTables is not loaded properly');
            }

            // Handle status toggle
            $('.status-toggle').change(function() {
                const adminId = $(this).data('admin-id');
                const newStatus = $(this).prop('checked') ? 1 : 0;
                const toggleElement = $(this);

                $.ajax({
                    url: 'admin_view.php',
                    method: 'POST',
                    data: {
                        toggle_status: true,
                        admin_id: adminId,
                        new_status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            toastr.success('Status updated successfully');
                        } else {
                            toastr.error('Failed to update status');
                            toggleElement.prop('checked', !newStatus);
                        }
                    },
                    error: function() {
                        toastr.error('Failed to update status');
                        toggleElement.prop('checked', !newStatus);
                    }
                });
            });

            // Handle delete button click
            $('.delete-admin').on('click', function() {
                const adminId = $(this).data('admin-id');
                const adminName = $(this).data('admin-name');
                
                $('#deleteAdminId').val(adminId);
                $('#deleteAdminName').text('Admin: ' + adminName);
                $('#deleteModal').modal('show');
            });

            // Handle delete form submission
            $('#deleteForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                
                $.ajax({
                    url: form.attr('action'),
                    method: form.attr('method'),
                    data: form.serialize(),
                    success: function(response) {
                        $('#deleteModal').modal('hide');
                        // Reload the page to show updated list and success message
                        window.location.reload();
                    },
                    error: function() {
                        toastr.error('Failed to delete admin user');
                        $('#deleteModal').modal('hide');
                    }
                });
            });

            // Initialize Toastr
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "timeOut": "3000"
            };
        });
    </script>
</body>
</html> 