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

// Get trainer ID from URL
$trainerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$trainerId) {
    header("Location: trainers.php");
    exit();
}

// Get trainer details
$sql = "SELECT * FROM trainers WHERE id = $trainerId";
$result = mysqli_query($conn, $sql);
$trainer = mysqli_fetch_assoc($result);

if (!$trainer) {
    header("Location: trainers.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];
    
    // Update trainer details
    $updateData = [
        'trainer_id' => $trainerId,
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'short_about' => $_POST['short_about'],
        'designation' => $_POST['designation'],
        'mobile' => $_POST['mobile']
    ];
    
    $apiResponse = callAPI('/api/v1/trainer/settings/update', 'POST', $updateData);
    
    if ($apiResponse['success']) {
        // Handle profile image upload if provided
        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $profileImg = new CURLFile(
                $_FILES['profile_img']['tmp_name'],
                $_FILES['profile_img']['type'],
                $_FILES['profile_img']['name']
            );
            
            $profileResponse = callAPI('/api/v1/trainer/profile-image/upload', 'POST', 
                                     ['trainer_id' => $trainerId], 
                                     ['profile_img' => $profileImg]);
            
            if ($profileResponse['success'] && isset($profileResponse['data']['profile_img'])) {
                $updateSql = "UPDATE trainers SET profile_img = '" . 
                            mysqli_real_escape_string($conn, $profileResponse['data']['profile_img']) . 
                            "' WHERE id = $trainerId";
                mysqli_query($conn, $updateSql);
            }
        }
        
        // Handle hero image upload if provided
        if (isset($_FILES['hero_img']) && $_FILES['hero_img']['error'] === UPLOAD_ERR_OK) {
            $heroImg = new CURLFile(
                $_FILES['hero_img']['tmp_name'],
                $_FILES['hero_img']['type'],
                $_FILES['hero_img']['name']
            );
            
            $heroResponse = callAPI('/api/v1/trainer/hero-image/upload', 'POST', 
                                  ['trainer_id' => $trainerId], 
                                  ['hero_img' => $heroImg]);
            
            if ($heroResponse['success'] && isset($heroResponse['data']['hero_img'])) {
                $updateSql = "UPDATE trainers SET hero_img = '" . 
                            mysqli_real_escape_string($conn, $heroResponse['data']['hero_img']) . 
                            "' WHERE id = $trainerId";
                mysqli_query($conn, $updateSql);
            }
        }
        
        $response['success'] = true;
        $response['message'] = $apiResponse['message'] ?? 'Trainer details updated successfully';
        
        // Update other trainer details in local database
        $updateSql = "UPDATE trainers SET 
                     first_name = '" . mysqli_real_escape_string($conn, $_POST['first_name']) . "',
                     last_name = '" . mysqli_real_escape_string($conn, $_POST['last_name']) . "',
                     designation = '" . mysqli_real_escape_string($conn, $_POST['designation']) . "',
                     short_about = '" . mysqli_real_escape_string($conn, $_POST['short_about']) . "',
                     about = '" . mysqli_real_escape_string($conn, $_POST['full_about']) . "',
                     mobile = '" . mysqli_real_escape_string($conn, $_POST['mobile']) . "'
                     WHERE id = $trainerId";
        
        mysqli_query($conn, $updateSql);
        
        // Refresh trainer data
        $sql = "SELECT * FROM trainers WHERE id = $trainerId";
        $result = mysqli_query($conn, $sql);
        $trainer = mysqli_fetch_assoc($result);
    } else {
        $response['success'] = false;
        $response['message'] = $apiResponse['message'] ?? 'Failed to update trainer details';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Edit Trainer | IPN Academy</title>
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
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="trainers.php">Trainers</a></li>
                                    <li class="breadcrumb-item active">Edit Trainer</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Edit Trainer</h4>
                            <a href="view_trainer.php?id=<?php echo $trainerId; ?>" class="btn btn-secondary">
                                <i class="ti ti-arrow-left me-1"></i> Back to Trainer
                            </a>
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
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo $trainer['first_name'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo $trainer['last_name'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="designation" class="form-label">Designation</label>
                                        <input type="text" class="form-control" id="designation" name="designation" 
                                               value="<?php echo $trainer['designation'] ?? ''; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="short_about" class="form-label">Short About</label>
                                        <textarea class="form-control" id="short_about" name="short_about" rows="3"><?php echo $trainer['short_about'] ?? ''; ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="full_about" class="form-label">Full About</label>
                                        <textarea class="form-control" id="full_about" name="full_about" rows="3"><?php echo $trainer['about'] ?? ''; ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="mobile" class="form-label">Mobile Number</label>
                                        <input type="tel" class="form-control" id="mobile" name="mobile" 
                                               value="<?php echo $trainer['mobile'] ?? ''; ?>" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="image-upload-container">
                                                <label for="profile_img" class="form-label">Profile Image</label>
                                                <input type="file" class="form-control" id="profile_img" name="profile_img" 
                                                       accept="image/*">
                                                <?php if (isset($trainer['profile_img']) && $trainer['profile_img']): ?>
                                                    <img src="<?php echo $uri . ($trainer['profile_img'] ?? ''); ?>" 
                                                         class="profile-image-preview" alt="Current Profile Image">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="image-upload-container">
                                                <label for="hero_img" class="form-label">Hero Image</label>
                                                <input type="file" class="form-control" id="hero_img" name="hero_img" 
                                                       accept="image/*">
                                                <?php if (isset($trainer['hero_img']) && $trainer['hero_img']): ?>
                                                    <img src="<?php echo $uri . ($trainer['hero_img'] ?? ''); ?>" 
                                                         class="hero-image-preview" alt="Current Hero Image">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-check me-1"></i> Update Trainer
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
                    if (preview) {
                        preview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'profile-image-preview';
                        e.target.parentNode.appendChild(img);
                    }
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        document.getElementById('hero_img').addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.hero-image-preview');
                    if (preview) {
                        preview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'hero-image-preview';
                        e.target.parentNode.appendChild(img);
                    }
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html> 