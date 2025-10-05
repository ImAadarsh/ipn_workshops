<?php
session_start();
require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_correction'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $mobile = mysqli_real_escape_string($conn, trim($_POST['mobile']));
    $institute_name = mysqli_real_escape_string($conn, trim($_POST['institute_name']));
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason']));
    
    // Validation
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($mobile)) $errors[] = "Mobile is required.";
    if (empty($institute_name)) $errors[] = "Institute name is required.";
    if (empty($reason)) $errors[] = "Reason for correction is required.";
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    $mobile_clean = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($mobile_clean) !== 10) {
        $errors[] = "Please enter a valid 10-digit mobile number.";
    }
    
    // Check if there are any changes
    $has_changes = false;
    if ($name !== $user['name'] || $email !== $user['email'] || $mobile_clean !== $user['mobile'] || $institute_name !== $user['institute_name']) {
        $has_changes = true;
    }
    
    if (!$has_changes) {
        $errors[] = "No changes detected. Please make at least one change to your profile.";
    }
    
    // Check for existing pending request
    $existing_sql = "SELECT id FROM profile_correction_requests WHERE user_id = $user_id AND status = 'pending'";
    $existing_result = mysqli_query($conn, $existing_sql);
    if (mysqli_num_rows($existing_result) > 0) {
        $errors[] = "You already have a pending correction request. Please wait for it to be processed.";
    }
    
    if (empty($errors)) {
        // Insert correction request
        $insert_sql = "INSERT INTO profile_correction_requests (user_id, name, email, mobile, institute_name, reason, status, created_at) 
                       VALUES ($user_id, '$name', '$email', '$mobile_clean', '$institute_name', '$reason', 'pending', NOW())";
        
        if (mysqli_query($conn, $insert_sql)) {
            $request_id = mysqli_insert_id($conn);
            
            // Send notification to admin
            require_once 'admin_correction_emails.php';
            sendAdminNotification($conn, $request_id, $user['name'], $user['email']);
            
            $success_message = "Your profile correction request has been submitted successfully. You will be notified once it's processed.";
        } else {
            $error_message = "Failed to submit your request. Please try again.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex, nofollow">
    <title>Profile Correction Request | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .comparison-table {
            font-size: 0.9rem;
        }
        .comparison-table .changed {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .comparison-table .unchanged {
            background-color: #f8f9fa;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">   <?php include 'includes/sidenav.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    <?php include 'includes/sidenav.php'; ?>

    <div class="page-content">
        <div class="page-container">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Profile Correction Request</li>
                            </ol>
                        </div>
                        <h4 class="page-title">Profile Correction Request</h4>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                    <div class="page-title-box">
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Profile Correction Request</li>
                            </ol>
                        </div>
                        <h4 class="page-title">Profile Correction Request</h4>
                        <p class="text-muted">Request changes to your profile information</p>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Request Profile Changes</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="correctionForm">
                                <div class="form-section">
                                    <h6 class="mb-3">Current Profile Information</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Current Name</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Current Email</label>
                                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Current Mobile</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['mobile']); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Current Institute</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['institute_name']); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6 class="mb-3">Requested Changes</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">New Name *</label>
                                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">New Email *</label>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">New Mobile *</label>
                                                <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                                                <small class="text-muted">Enter 10-digit mobile number</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">New Institute Name *</label>
                                                <input type="text" name="institute_name" class="form-control" value="<?php echo htmlspecialchars($user['institute_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h6 class="mb-3">Reason for Changes</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Please explain why you need these changes *</label>
                                        <textarea name="reason" class="form-control" rows="4" placeholder="Please provide a detailed reason for the requested changes..." required></textarea>
                                        <small class="text-muted">This information helps us process your request faster.</small>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="ti ti-info-circle me-2"></i>
                                    <strong>Important:</strong> Your request will be reviewed by our admin team. You will be notified via email once it's processed. Please ensure all information is accurate.
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="submit_correction" class="btn btn-primary">
                                        <i class="ti ti-send me-1"></i>Submit Request
                                    </button>
                                    <a href="profile.php" class="btn btn-outline-secondary">
                                        <i class="ti ti-arrow-left me-1"></i>Back to Profile
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Request Status</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Check for existing requests
                            $status_sql = "SELECT * FROM profile_correction_requests WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 1";
                            $status_result = mysqli_query($conn, $status_sql);
                            
                            if (mysqli_num_rows($status_result) > 0) {
                                $latest_request = mysqli_fetch_assoc($status_result);
                                $status_class = [
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger'
                                ][$latest_request['status']] ?? 'secondary';
                            ?>
                                <div class="mb-3">
                                    <strong>Latest Request Status:</strong>
                                    <span class="badge bg-<?php echo $status_class; ?> ms-2">
                                        <?php echo ucfirst($latest_request['status']); ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Submitted:</strong><br>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($latest_request['created_at'])); ?></small>
                                </div>
                                <?php if ($latest_request['processed_at']): ?>
                                <div class="mb-3">
                                    <strong>Processed:</strong><br>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($latest_request['processed_at'])); ?></small>
                                </div>
                                <?php endif; ?>
                                <?php if ($latest_request['admin_notes']): ?>
                                <div class="mb-3">
                                    <strong>Admin Notes:</strong><br>
                                    <small class="text-muted"><?php echo nl2br(htmlspecialchars($latest_request['admin_notes'])); ?></small>
                                </div>
                                <?php endif; ?>
                            <?php } else { ?>
                                <p class="text-muted">No previous requests found.</p>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Guidelines</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="ti ti-check text-success me-2"></i>Provide accurate information</li>
                                <li><i class="ti ti-check text-success me-2"></i>Explain the reason clearly</li>
                                <li><i class="ti ti-check text-success me-2"></i>Use official documents if needed</li>
                                <li><i class="ti ti-check text-success me-2"></i>Wait for admin approval</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script>
        // Mobile number formatting
        document.querySelector('input[name="mobile"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });

        // Form validation
        document.getElementById('correctionForm').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const mobile = document.querySelector('input[name="mobile"]').value.trim();
            const institute = document.querySelector('input[name="institute_name"]').value.trim();
            const reason = document.querySelector('textarea[name="reason"]').value.trim();

            if (!name || !email || !mobile || !institute || !reason) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            if (mobile.length !== 10) {
                e.preventDefault();
                alert('Please enter a valid 10-digit mobile number.');
                return;
            }

            if (!email.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }

            if (reason.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed reason (at least 10 characters).');
                return;
            }

            // Check if there are any changes
            const currentName = '<?php echo addslashes($user['name']); ?>';
            const currentEmail = '<?php echo addslashes($user['email']); ?>';
            const currentMobile = '<?php echo addslashes($user['mobile']); ?>';
            const currentInstitute = '<?php echo addslashes($user['institute_name']); ?>';

            if (name === currentName && email === currentEmail && mobile === currentMobile && institute === currentInstitute) {
                e.preventDefault();
                alert('No changes detected. Please make at least one change to your profile.');
                return;
            }

            if (!confirm('Are you sure you want to submit this correction request?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
