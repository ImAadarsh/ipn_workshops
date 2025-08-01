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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $institute_name = mysqli_real_escape_string($conn, $_POST['institute_name']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $school_id = !empty($_POST['school_id']) ? intval($_POST['school_id']) : null;
    
    // Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error_message'] = "A user with this email already exists!";
    } else {
        // Generate a random password
        $password = bin2hex(random_bytes(8)); // 16 character random password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate token
        $token = bin2hex(random_bytes(16));
        
        $sql = "INSERT INTO users (name, email, mobile, designation, institute_name, city, school_id, password, token, user_type, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssss", $name, $email, $mobile, $designation, $institute_name, $city, $school_id, $hashed_password, $token);
        
        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($conn);
            $_SESSION['success_message'] = "User created successfully! User ID: $user_id, Temporary Password: $password";
            header("Location: user_management.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error creating user: " . mysqli_error($conn);
        }
    }
}

// Get schools for dropdown
$schools_sql = "SELECT id, name FROM schools ORDER BY name ASC";
$schools_result = mysqli_query($conn, $schools_sql);
$schools = [];
while ($school = mysqli_fetch_assoc($schools_result)) {
    $schools[] = $school;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Add New User | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .form-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Add New User</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="user_management.php" class="btn btn-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Add User Form -->
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-8">
                        <div class="card form-card">
                            <div class="card-header bg-gradient-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-user-plus me-2"></i>Create New User
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" id="addUserForm">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Name *</label>
                                            <input type="text" class="form-control" name="name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Mobile *</label>
                                            <input type="text" class="form-control" name="mobile" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Designation</label>
                                            <input type="text" class="form-control" name="designation">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Institute Name</label>
                                            <input type="text" class="form-control" name="institute_name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" name="city">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">School</label>
                                            <select class="form-select" name="school_id">
                                                <option value="">Select School (Optional)</option>
                                                <?php foreach ($schools as $school): ?>
                                                    <option value="<?php echo $school['id']; ?>">
                                                        <?php echo htmlspecialchars($school['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="ti ti-info-circle me-2"></i>
                                        <strong>Note:</strong> A random password will be generated for the user. The password will be displayed after successful creation.
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-4">
                                        <a href="user_management.php" class="btn btn-secondary me-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-user-plus me-1"></i> Create User
                                        </button>
                                    </div>
                                </form>
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

    <script>
        // Form validation
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const mobile = document.querySelector('input[name="mobile"]').value;
            const email = document.querySelector('input[name="email"]').value;
            
            // Mobile validation
            if (mobile.length < 10) {
                alert('Mobile number must be at least 10 digits long.');
                e.preventDefault();
                return;
            }
            
            // Email validation
            if (!email.includes('@')) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html> 