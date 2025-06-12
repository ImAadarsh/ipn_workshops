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

$user_id = $_SESSION['user_id'];

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
    $about = mysqli_real_escape_string($conn, $_POST['about']);
    
    // Update user information
    $update_query = "UPDATE users SET 
        first_name = ?,
        last_name = ?,
        email = ?,
        mobile = ?,
        school = ?,
        city = ?,
        country_code = ?,
        grade = ?,
        about = ?
        WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssssssi", 
            $first_name, 
            $last_name, 
            $email, 
            $mobile, 
            $school, 
            $city, 
            $country_code,
            $grade,
            $about,
            $user_id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_msg'] = "Profile updated successfully.";
            header("Location: profile.php");
            exit();
        } else {
            $_SESSION['error_msg'] = "Failed to update profile.";
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
} else {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>My Profile | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .profile-header {
            background: linear-gradient(to right, #3283f6, #0e5bb7);
            padding: 2rem 0;
            color: white;
            margin-bottom: 2rem;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-info {
            padding: 1rem;
        }
        .profile-stats {
            border-right: 1px solid #eee;
        }
        .profile-stats:last-child {
            border-right: none;
        }
    </style>
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

                <!-- Profile Header -->
                <div class="profile-header mt-2">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <!-- use name initials -->
                                <div style="font-size: 2rem; font-weight: bold; color: white; border-radius: 50%; background-color:rgb(0, 0, 0); padding: 1rem;" class="profile-initials">
                                    <?php echo substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1); ?>
                                </div>
                               
                            </div>
                            <div class="col">
                                <h2 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                <p class="mb-0"><i class="ti ti-mail me-1"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="mb-0"><i class="ti ti-phone me-1"></i> <?php echo htmlspecialchars($user['country_code'] . ' ' . $user['mobile']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4 col-lg-5">
                        <!-- Personal Information Card -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Personal Information</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Full Name</strong>
                                    <p><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong>Email</strong>
                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong>Phone</strong>
                                    <p><?php echo htmlspecialchars($user['country_code'] . ' ' . $user['mobile']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong>School</strong>
                                    <p><?php echo htmlspecialchars($user['school']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong>Grade</strong>
                                    <p><?php echo htmlspecialchars($user['grade']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <strong>Location</strong>
                                    <p><?php echo htmlspecialchars($user['city']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8 col-lg-7">
                        <!-- Edit Profile Card -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Edit Profile</h4>
                            </div>
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
                                                <div class="input-group">
                                                    <input type="text" class="form-control w-25" id="country_code" name="country_code" 
                                                           value="<?php echo htmlspecialchars($user['country_code']); ?>" required>
                                                    <input type="text" class="form-control" id="mobile" name="mobile" 
                                                           value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                                                </div>
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
                                                <label for="grade" class="form-label">Grade</label>
                                                <input type="text" class="form-control" id="grade" name="grade" 
                                                       value="<?php echo htmlspecialchars($user['grade']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($user['city']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="about" class="form-label">About Me</label>
                                        <textarea class="form-control" id="about" name="about" rows="4"><?php echo htmlspecialchars($user['about']); ?></textarea>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
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