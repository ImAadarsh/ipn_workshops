<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

$error = '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Check in users table for admin
    $sql = "SELECT * FROM users WHERE email = ? AND user_type = 'admin'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        // Verify password hash
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['token'] = $user['remember_token'];
            $_SESSION['user_name'] = $user['name'];
            
            header("Location: dashboard.php");
            exit();
        }
    } else {
        // If not found in users, check trainers table
        $sql = "SELECT * FROM trainers WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $trainer = mysqli_fetch_assoc($result);
            // For trainers table, verify password
            if ($trainer['passcode'] == $password) {
                $_SESSION['user_id'] = $trainer['id'];
                $_SESSION['token'] = $trainer['remember_token'];
                $_SESSION['user_type'] = 'trainer';
                $_SESSION['user_name'] = $trainer['first_name'] . ' ' . $trainer['last_name'];
                
                header("Location: dashboard.php");
                exit();
            }
        }
    }
    
    $error = "Invalid email or password";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Log In | IPN Academy - Shape Your Future with Confidence</title>
<?php include 'includes/head.php'; ?>
</head>

<body>

    <div class="auth-bg d-flex min-vh-100 justify-content-center align-items-center">
        <div class="row g-0 justify-content-center w-100 m-xxl-5 px-xxl-4 m-3">
            <div class="col-xl-4 col-lg-5 col-md-6">
                <div class="card overflow-hidden text-center h-100 p-xxl-4 p-3 mb-0">
                    <a href="index.php" class="auth-brand mb-3">
                   
                            <span ><img width="350px" src="logo.svg" alt="IPN Academy Logo" class="logo-img"></span>
                         
                   
                    </a>

                    <h4 class="fw-semibold mb-2">Login your account</h4>

                    <p class="text-muted mb-4">Enter your email address and password to access admin panel.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="index.php" method="POST" class="text-start mb-3">
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="checkbox-signin" name="remember">
                                <label class="form-check-label" for="checkbox-signin">Remember me</label>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-primary" type="submit">Login</button>
                        </div>
                    </form>



                    <p class="mt-auto mb-0">
                        <script>document.write(new Date().getFullYear())</script> © IPN Academy - Developed By <span class="fw-bold text-decoration-underline text-uppercase text-reset fs-12"><a href="https://endeavourdigital.in">Endeavour Digital</a></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.js"></script>

</body>

</html>