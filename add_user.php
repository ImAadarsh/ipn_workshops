<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in or not admin
if (!isset($_SESSION['user_id']) ) {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $school = mysqli_real_escape_string($conn, $_POST['school']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $country_code = mysqli_real_escape_string($conn, $_POST['country_code']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $grade = mysqli_real_escape_string($conn, $_POST['grade']);
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);
    $password = isset($_POST['password']) ? mysqli_real_escape_string($conn, $_POST['password']) : '';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email already exists
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $error_message = "Email already exists";
        } else {
            // Validate password for admin users
            if ($user_type === 'admin' && empty($password)) {
                $error_message = "Password is required for admin users";
            } else {
                // Hash password if provided, otherwise set to null
                $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
                
                // Insert new user
                $query = "INSERT INTO users (email, first_name, last_name, school, city, country_code, mobile, 
                         grade, user_type, password, mobile_verified, email_verified, is_data, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1, NOW(), NOW())";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ssssssssss", 
                    $email, $first_name, $last_name, $school, $city, $country_code, 
                    $mobile, $grade, $user_type, $hashed_password);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "User created successfully!";
                } else {
                    $error_message = "Error creating user: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Add New User | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">

        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                    <li class="breadcrumb-item active">Add New User</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Add New User</h4>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                            <div class="invalid-feedback">Please enter first name.</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                            <div class="invalid-feedback">Please enter last name.</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                            <div class="invalid-feedback">Please enter a valid email.</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="user_type" name="user_type" required>
                                                <option value="user">User</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <div class="invalid-feedback">Please select user type.</div>
                                        </div>
                                    </div>

                                    <div class="row password-section" style="display: none;">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password <span class="text-danger password-required" style="display: none;">*</span></label>
                                            <div class="input-group input-group-merge">
                                                <input type="password" class="form-control" id="password" name="password">
                                                <div class="input-group-text" data-password="false">
                                                    <span class="password-eye"></span>
                                                </div>
                                            </div>
                                            <div class="invalid-feedback">Please enter password.</div>
                                            <small class="text-muted">Password is required only for admin users.</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="school" class="form-label">School/Institution</label>
                                            <input type="text" class="form-control" id="school" name="school">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-2 mb-3">
                                            <label for="country_code" class="form-label">Country Code</label>
                                            <select class="form-select" id="country_code" name="country_code">
                                                <option value="+91">+91 (India)</option>
                                                <option value="+1">+1 (USA/Canada)</option>
                                                <option value="+44">+44 (UK)</option>
                                                <option value="+61">+61 (Australia)</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="mobile" name="mobile" required>
                                            <div class="invalid-feedback">Please enter mobile number.</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="grade" class="form-label">Grade/Class</label>
                                            <select class="form-select" id="grade" name="grade">
                                                <option value="">Select Grade</option>
                                                <?php for($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">Create User</button>
                                            <a href="users.php" class="btn btn-light ms-2">Cancel</a>
                                        </div>
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

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        // Form validation
        (function () {
            'use strict'

            // Fetch all forms we want to apply validation styles to
            var forms = document.querySelectorAll('.needs-validation')

            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }

                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Password toggle
        $("[data-password]").on('click', function() {
            if($(this).attr('data-password') == "false"){
                $(this).siblings('input').attr("type", "text");
                $(this).attr('data-password', 'true');
                $(this).find('.password-eye').addClass("show");
            } else {
                $(this).siblings('input').attr("type", "password");
                $(this).attr('data-password', 'false');
                $(this).find('.password-eye').removeClass("show");
            }
        });

        // Handle user type change
        document.getElementById('user_type').addEventListener('change', function() {
            const passwordSection = document.querySelector('.password-section');
            const passwordInput = document.getElementById('password');
            const passwordRequired = document.querySelector('.password-required');

            if (this.value === 'admin') {
                passwordSection.style.display = 'block';
                passwordInput.setAttribute('required', 'required');
                passwordRequired.style.display = 'inline';
            } else {
                passwordSection.style.display = 'none';
                passwordInput.removeAttribute('required');
                passwordRequired.style.display = 'none';
                passwordInput.value = ''; // Clear password when switching to user
            }
        });

        // Trigger user type change on page load
        document.getElementById('user_type').dispatchEvent(new Event('change'));
    </script>
</body>
</html> 