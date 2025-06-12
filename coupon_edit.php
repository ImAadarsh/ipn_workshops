<?php
include 'config/show_errors.php';
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

// Get all trainers
$trainers_query = "SELECT id, first_name, last_name FROM trainers ORDER BY first_name";
$trainers_result = $conn->query($trainers_query);
$trainers = [];
if ($trainers_result && $trainers_result->num_rows > 0) {
    while ($row = $trainers_result->fetch_assoc()) {
        $trainers[] = $row;
    }
}

// Get all users
$users_query = "SELECT id, first_name, last_name FROM users ORDER BY first_name";
$users_result = $conn->query($users_query);
$users = [];
if ($users_result && $users_result->num_rows > 0) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get currently assigned trainers
$assigned_trainers_query = "SELECT trainer_id FROM trainer_coupons WHERE coupon_id = ?";
$stmt = $conn->prepare($assigned_trainers_query);
if ($stmt === false) {
    // Just log the error and continue with empty assigned trainers
    error_log("Error preparing assigned trainers query: " . $conn->error);
    $assigned_trainers = [];
} else {
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $assigned_trainers_result = $stmt->get_result();
    $assigned_trainers = [];

    if ($assigned_trainers_result->num_rows > 0) {
        while ($row = $assigned_trainers_result->fetch_assoc()) {
            $assigned_trainers[] = $row['trainer_id'];
        }
    }
}

// Get currently assigned users
$assigned_users_query = "SELECT user_id FROM user_coupons WHERE coupon_id = ?";
$stmt = $conn->prepare($assigned_users_query);
if ($stmt === false) {
    // Just log the error and continue with empty assigned users
    error_log("Error preparing assigned users query: " . $conn->error);
    $assigned_users = [];
} else {
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $assigned_users_result = $stmt->get_result();
    $assigned_users = [];

    if ($assigned_users_result->num_rows > 0) {
        while ($row = $assigned_users_result->fetch_assoc()) {
            $assigned_users[] = $row['user_id'];
        }
    }
}

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $description = trim($_POST['description']);
    $discount_type = $_POST['discount_type'];
    $discount_amount = floatval($_POST['discount_amount']);
    $min_purchase_amount = floatval($_POST['min_purchase_amount']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $max_uses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate coupon code is unique (except for this coupon)
    $check_query = "SELECT id FROM coupons WHERE code = ? AND id != ?";
    $stmt = $conn->prepare($check_query);
    if ($stmt === false) {
        $_SESSION['error_message'] = "Error preparing check query: " . $conn->error;
    } else {
        $stmt->bind_param('si', $code, $coupon_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error_message'] = "Coupon code already exists. Please use a different code.";
        } else {
            // Update coupon
            $update_query = "UPDATE coupons 
                            SET code = ?, description = ?, discount_type = ?, discount_amount = ?, 
                            min_purchase_amount = ?, start_date = ?, end_date = ?, max_uses = ?, is_active = ?
                            WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if ($stmt === false) {
                $_SESSION['error_message'] = "Error preparing update query: " . $conn->error;
            } else {
                $stmt->bind_param('sssddssiis', $code, $description, $discount_type, $discount_amount, 
                                $min_purchase_amount, $start_date, $end_date, $max_uses, $is_active, $coupon_id);
                
                if ($stmt->execute()) {
                    // Clear existing associations
                    $conn->query("DELETE FROM trainer_coupons WHERE coupon_id = $coupon_id");
                    $conn->query("DELETE FROM user_coupons WHERE coupon_id = $coupon_id");
                    
                    // Process trainer assignments
                    if (isset($_POST['assign_to_trainers']) && !empty($_POST['trainers'])) {
                        $trainer_values = [];
                        $trainer_query = "INSERT INTO trainer_coupons (coupon_id, trainer_id) VALUES ";
                        
                        foreach ($_POST['trainers'] as $trainer_id) {
                            $trainer_values[] = "($coupon_id, " . intval($trainer_id) . ")";
                        }
                        
                        $trainer_query .= implode(', ', $trainer_values);
                        $conn->query($trainer_query);
                    }
                    
                    // Process user assignments
                    if (isset($_POST['assign_to_users']) && !empty($_POST['users'])) {
                        $user_values = [];
                        $user_query = "INSERT INTO user_coupons (coupon_id, user_id) VALUES ";
                        
                        foreach ($_POST['users'] as $user_id) {
                            $user_values[] = "($coupon_id, " . intval($user_id) . ")";
                        }
                        
                        $user_query .= implode(', ', $user_values);
                        $conn->query($user_query);
                    }
                    
                    $_SESSION['success_message'] = "Coupon updated successfully.";
                    header('Location: coupons.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = "Error updating coupon: " . $conn->error;
                }
            }
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
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="coupons.php">Coupons</a></li>
                                        <li class="breadcrumb-item active">Edit Coupon</li>
                                    </ol>
                                </div>
                                <h4 class="page-title">Edit Coupon</h4>
                            </div>
                        </div>
                    </div>
                    <!-- end page title -->

                    <div class="row">
                        <div class="col-12">
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
                                    <form action="coupon_edit.php?id=<?php echo $coupon_id; ?>" method="post">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="mb-3">Coupon Details</h5>
                                                
                                                <div class="mb-3">
                                                    <label for="code" class="form-label">Coupon Code <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="code" name="code" required pattern="[A-Za-z0-9]+" 
                                                           value="<?php echo htmlspecialchars($coupon['code']); ?>">
                                                    <small class="text-muted">Alphanumeric only, no spaces or special characters</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="description" class="form-label">Description</label>
                                                    <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($coupon['description']); ?></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="discount_type" class="form-label">Discount Type <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="discount_type" name="discount_type" required>
                                                        <option value="flat" <?php echo ($coupon['discount_type'] == 'flat') ? 'selected' : ''; ?>>Flat Discount ($)</option>
                                                        <option value="percentage" <?php echo ($coupon['discount_type'] == 'percentage') ? 'selected' : ''; ?>>Percentage Discount (%)</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="discount_amount" class="form-label">Discount Amount <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text" id="discount_symbol"><?php echo ($coupon['discount_type'] == 'percentage') ? '%' : '$'; ?></span>
                                                        <input type="number" class="form-control" id="discount_amount" name="discount_amount" required min="0" step="0.01" 
                                                               value="<?php echo $coupon['discount_amount']; ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="min_purchase_amount" class="form-label">Minimum Purchase Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" id="min_purchase_amount" name="min_purchase_amount" min="0" step="0.01" 
                                                               value="<?php echo $coupon['min_purchase_amount']; ?>">
                                                    </div>
                                                    <small class="text-muted">Enter 0 for no minimum</small>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h5 class="mb-3">Validity & Restrictions</h5>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="start_date" name="start_date" required 
                                                                   value="<?php echo $coupon['start_date']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="end_date" name="end_date" required 
                                                                   value="<?php echo $coupon['end_date']; ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="max_uses" class="form-label">Maximum Usage</label>
                                                    <input type="number" class="form-control" id="max_uses" name="max_uses" min="1" 
                                                           value="<?php echo $coupon['max_uses']; ?>" placeholder="Leave empty for unlimited">
                                                    <small class="text-muted">Maximum number of times this coupon can be used</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="mb-1">Current Usage: <?php echo $coupon['current_uses']; ?></label>
                                                    <?php if ($coupon['max_uses']): ?>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar" role="progressbar" 
                                                                style="width: <?php echo min(($coupon['current_uses'] / $coupon['max_uses']) * 100, 100); ?>%" 
                                                                aria-valuenow="<?php echo $coupon['current_uses']; ?>" aria-valuemin="0" 
                                                                aria-valuemax="<?php echo $coupon['max_uses']; ?>">
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <div class="form-check form-switch">
                                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                                               <?php echo $coupon['is_active'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="is_active">Active</label>
                                                    </div>
                                                    <small class="text-muted">Uncheck to save as inactive</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Coupon Assignments</label>
                                                    
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" id="assign_to_trainers" name="assign_to_trainers" value="1"
                                                               <?php echo (!empty($assigned_trainers)) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="assign_to_trainers">
                                                            Assign to specific trainers
                                                        </label>
                                                    </div>
                                                    
                                                    <div id="trainers_container" class="mb-3 <?php echo empty($assigned_trainers) ? 'd-none' : ''; ?>">
                                                        <select class="form-select" id="trainers" name="trainers[]" multiple>
                                                            <?php foreach ($trainers as $trainer): ?>
                                                                <option value="<?php echo $trainer['id']; ?>" 
                                                                        <?php echo in_array($trainer['id'], $assigned_trainers) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="text-muted">Hold Ctrl/Cmd to select multiple trainers</small>
                                                    </div>
                                                    
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" id="assign_to_users" name="assign_to_users" value="1"
                                                               <?php echo (!empty($assigned_users)) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="assign_to_users">
                                                            Assign to specific users
                                                        </label>
                                                    </div>
                                                    
                                                    <div id="users_container" class="mb-3 <?php echo empty($assigned_users) ? 'd-none' : ''; ?>">
                                                        <select class="form-select" id="users" name="users[]" multiple>
                                                            <?php foreach ($users as $user): ?>
                                                                <option value="<?php echo $user['id']; ?>" 
                                                                        <?php echo in_array($user['id'], $assigned_users) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="text-muted">Hold Ctrl/Cmd to select multiple users</small>
                                                    </div>
                                                    
                                                    <div class="form-text">
                                                        If no specific assignments are made, the coupon will be available to all users.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end mt-3">
                                            <button type="submit" class="btn btn-primary">Update Coupon</button>
                                            <a href="coupons.php" class="btn btn-secondary ms-2">Cancel</a>
                                        </div>
                                    </form>
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

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        $(document).ready(function() {
            // Toggle trainers selection
            $('#assign_to_trainers').change(function() {
                if ($(this).is(':checked')) {
                    $('#trainers_container').removeClass('d-none');
                } else {
                    $('#trainers_container').addClass('d-none');
                }
            });
            
            // Toggle users selection
            $('#assign_to_users').change(function() {
                if ($(this).is(':checked')) {
                    $('#users_container').removeClass('d-none');
                } else {
                    $('#users_container').addClass('d-none');
                }
            });
            
            // Change symbol based on discount type
            $('#discount_type').change(function() {
                if ($(this).val() === 'percentage') {
                    $('#discount_symbol').text('%');
                } else {
                    $('#discount_symbol').text('$');
                }
            });
        });
    </script>

</body>
</html> 