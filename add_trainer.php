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

// Get user type and name from session
$userType = $_SESSION['user_type'];
$userName = $_SESSION['user_name'];
$userId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];
    
    // Prepare the data for API
    $postData = [
        'token' => $_SESSION['token'],
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'short_about' => $_POST['short_about'],
        'about' => $_POST['about'],
        'designation' => $_POST['designation'],
        'email' => $_POST['email'],
        'passcode' => $_POST['passcode'],
        'mobile' => $_POST['mobile']
    ];
    
    // Handle file uploads
    $files = [];
    if (isset($_FILES['hero_img']) && $_FILES['hero_img']['error'] === UPLOAD_ERR_OK) {
        $files['hero_img'] = new CURLFile(
            $_FILES['hero_img']['tmp_name'],
            $_FILES['hero_img']['type'],
            $_FILES['hero_img']['name']
        );
    }
    
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $files['profile_img'] = new CURLFile(
            $_FILES['profile_img']['tmp_name'],
            $_FILES['profile_img']['type'],
            $_FILES['profile_img']['name']
        );
    }
    
    // Make API call
    $apiResponse = callAPI('/api/v1/trainers', 'POST', $postData, $files);
    
    if ($apiResponse['success']) {
        // Insert into local database
            $response['success'] = true;
            $response['message'] = 'Trainer added successfully';
            header("Location: trainers.php");
            exit();
       
    } else {
        $response['success'] = false;
        $response['message'] = $apiResponse['message'] ?? 'Failed to add trainer';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Add Trainer | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .profile-image-preview, .hero-image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
        }
        .image-upload-container {
            margin-bottom: 20px;
        }
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

        <div class="page-content">
            <div class="page-container">
            <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="trainers.php">Trainers</a></li>
                                    <li class="breadcrumb-item active">Add New Trainer</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Add New Trainer</h4>
                        </div>
                    </div>
                </div>

                <?php if (isset($response)): ?>
                    <div class="alert alert-<?php echo $response['success'] ? 'success' : 'danger'; ?>">
                        <?php echo $response['message']; ?>
                    </div>
                <?php endif; ?>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="designation" class="form-label">Designation</label>
                                        <input type="text" class="form-control" id="designation" name="designation" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="mobile" class="form-label">Mobile Number</label>
                                        <input type="tel" class="form-control" id="mobile" name="mobile" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="passcode" class="form-label">Passcode</label>
                                        <input type="text" class="form-control" id="passcode" name="passcode" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="short_about" class="form-label">Short About</label>
                                        <textarea class="form-control" id="short_about" name="short_about" rows="3" required></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="about" class="form-label">Detailed About</label>
                                        <textarea class="form-control" id="about" name="about" rows="5" required></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="image-upload-container">
                                                <label for="profile_img" class="form-label">Profile Image</label>
                                                <input type="file" class="form-control" id="profile_img" name="profile_img" accept="image/*" required>
                                                <img src="" class="profile-image-preview d-none" alt="Profile Image Preview">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="image-upload-container">
                                                <label for="hero_img" class="form-label">Hero Image</label>
                                                <input type="file" class="form-control" id="hero_img" name="hero_img" accept="image/*" required>
                                                <img src="" class="hero-image-preview d-none" alt="Hero Image Preview">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-check me-1"></i> Add Trainer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->
        </div>
    </div>

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        // Preview images before upload
        document.getElementById('profile_img').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.profile-image-preview');
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        document.getElementById('hero_img').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.hero-image-preview');
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html> 