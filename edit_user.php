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

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$user_id = mysqli_real_escape_string($conn, $_GET['id']);
// Set user type based on URL parameter or default to 'user'
$user_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'user';

// Validate user type
if (!in_array($user_type, ['admin', 'user'])) {
    header("Location: users.php");
    exit();
}

// Check if current user has permission to edit
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_id'] != $user_id) {
    header("Location: users.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $school = mysqli_real_escape_string($conn, $_POST['school']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $country_code = mysqli_real_escape_string($conn, $_POST['country_code']);
    $grade = mysqli_real_escape_string($conn, $_POST['grade']);
    
    // Update user information
    $update_query = "UPDATE users SET 
        first_name = ?,
        last_name = ?,
        email = ?,
        mobile = ?,
        school = ?,
        city = ?,
        country_code = ?,
        grade = ?
        WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssssi", 
            $first_name, 
            $last_name, 
            $email, 
            $mobile, 
            $school, 
            $city, 
            $country_code,
            $grade,
            $user_id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_msg'] = "User updated successfully.";
            if ($_SESSION['user_type'] === 'admin') {
                header("Location: " . ($user_type === 'admin' ? 'admin_view.php' : 'users.php'));
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['error_msg'] = "Failed to update user.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Get user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user) {
        header("Location: users.php");
        exit();
    }
} else {
    header("Location: users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Edit User | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
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
                                    <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?php echo $user_type === 'admin' ? 'admin_view.php' : 'users.php'; ?>">
                                            <?php echo ucfirst($user_type); ?> Management
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li class="breadcrumb-item active">Edit User</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Edit User</h4>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="mobile" class="form-label">Mobile</label>
                                                <input type="text" class="form-control" id="mobile" name="mobile" 
                                                       value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="school" class="form-label">School</label>
                                                <input type="text" class="form-control" id="school" name="school" 
                                                       value="<?php echo htmlspecialchars($user['school']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="city" class="form-label">City</label>
                                                <input type="text" class="form-control" id="city" name="city" 
                                                       value="<?php echo htmlspecialchars($user['city']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="country_code" class="form-label">Country Code</label>
                                                <input type="text" class="form-control" id="country_code" name="country_code" 
                                                       value="<?php echo htmlspecialchars($user['country_code']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="grade" class="form-label">Grade</label>
                                                <input type="text" class="form-control" id="grade" name="grade" 
                                                       value="<?php echo htmlspecialchars($user['grade']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <a href="<?php echo $_SESSION['user_type'] === 'admin' ? ($user_type === 'admin' ? 'admin_view.php' : 'users.php') : 'dashboard.php'; ?>" 
                                           class="btn btn-secondary me-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Update User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core js -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- App js -->
    <script src="assets/js/app.min.js"></script>
</body>
</html> 