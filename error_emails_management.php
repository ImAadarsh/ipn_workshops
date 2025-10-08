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

// Get workshop ID from URL
$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;

// Get workshop details
$workshop = null;
if ($workshop_id > 0) {
    $sql = "SELECT name FROM workshops WHERE id = $workshop_id AND is_deleted = 0";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $workshop = mysqli_fetch_assoc($result);
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_user':
            $error_id = intval($_POST['error_id']);
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
            $designation = mysqli_real_escape_string($conn, $_POST['designation']);
            $institute_name = mysqli_real_escape_string($conn, $_POST['institute_name']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            
            // Get user_id from error record
            $get_user_sql = "SELECT user_id FROM email_errors WHERE id = $error_id";
            $user_result = mysqli_query($conn, $get_user_sql);
            if ($user_result && $user_row = mysqli_fetch_assoc($user_result)) {
                $user_id = $user_row['user_id'];
                
                // Update user details
                $update_sql = "UPDATE users SET 
                               name = '$name',
                               email = '$email',
                               mobile = '$mobile',
                               designation = '$designation',
                               institute_name = '$institute_name',
                               city = '$city',
                               updated_at = NOW()
                               WHERE id = $user_id";
                
                if (mysqli_query($conn, $update_sql)) {
                    echo json_encode(['success' => true, 'message' => 'User details updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            exit();
            
        case 'mark_resolved':
            $error_id = intval($_POST['error_id']);
            $update_sql = "UPDATE email_errors SET 
                           is_resolved = 1,
                           resolved_at = NOW(),
                           updated_at = NOW()
                           WHERE id = $error_id";
            
            if (mysqli_query($conn, $update_sql)) {
                echo json_encode(['success' => true, 'message' => 'Error marked as resolved']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark as resolved: ' . mysqli_error($conn)]);
            }
            exit();
            
        case 'retry_email':
            $error_id = intval($_POST['error_id']);
            
            // Get error details
            $error_sql = "SELECT ee.*, u.name as user_name, u.email as user_email, w.name as workshop_name, w.trainer_name, w.duration, w.meeting_id, w.passcode
                         FROM email_errors ee
                         INNER JOIN users u ON ee.user_id = u.id
                         INNER JOIN workshops w ON ee.workshop_id = w.id
                         WHERE ee.id = $error_id";
            
            $error_result = mysqli_query($conn, $error_sql);
            if ($error_result && $error_data = mysqli_fetch_assoc($error_result)) {
                // Prepare data for email sending
                $user_data = [
                    'name' => $error_data['user_name'],
                    'email' => $error_data['user_email']
                ];
                
                $workshop_data = [
                    'name' => $error_data['workshop_name'],
                    'trainer_name' => $error_data['trainer_name'],
                    'duration' => $error_data['duration'],
                    'meeting_id' => $error_data['meeting_id'],
                    'passcode' => $error_data['passcode']
                ];
                
                // Generate joining link
                $joining_link = 'https://meet.ipnacademy.in/?display_name=' . $error_data['user_id'] . '_' . urlencode($error_data['user_name']) . '&mn=' . urlencode($error_data['meeting_id']) . '&pwd=' . urlencode($error_data['passcode']) . '&meeting_email=' . urlencode($error_data['user_email']);
                
                // Include email helper
                require_once 'config/email_helper.php';
                
                // Try to send email
                $email_sent = sendWorkshopReminderEmail($user_data, $workshop_data, $joining_link);
                
                if ($email_sent) {
                    // Mark as resolved
                    $resolve_sql = "UPDATE email_errors SET 
                                   is_resolved = 1,
                                   resolved_at = NOW(),
                                   updated_at = NOW()
                                   WHERE id = $error_id";
                    mysqli_query($conn, $resolve_sql);
                    
                    echo json_encode(['success' => true, 'message' => 'Email sent successfully and error marked as resolved']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error record not found']);
            }
            exit();
    }
}

// Get error emails for this workshop
$errors = [];
if ($workshop_id > 0) {
    $errors_sql = "SELECT ee.*, u.name as user_name, u.email as user_email, u.mobile, u.designation, u.institute_name, u.city,
                          w.name as workshop_name
                   FROM email_errors ee
                   INNER JOIN users u ON ee.user_id = u.id
                   INNER JOIN workshops w ON ee.workshop_id = w.id
                   WHERE ee.workshop_id = $workshop_id
                   ORDER BY ee.created_at DESC";
    
    $errors_result = mysqli_query($conn, $errors_sql);
    if ($errors_result) {
        while ($row = mysqli_fetch_assoc($errors_result)) {
            $errors[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Error Emails Management | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .error-card {
            border-left: 4px solid #dc3545;
        }
        .error-card.resolved {
            border-left-color: #28a745;
            opacity: 0.7;
        }
        .error-type-badge {
            font-size: 0.75rem;
        }
        .user-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .edit-form {
            display: none;
        }
        .edit-form.show {
            display: block;
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
                                <h4 class="fs-18 text-uppercase fw-bold m-0">
                                    <i class="ti ti-alert-circle me-2"></i>Error Emails Management
                                    <?php if ($workshop): ?>
                                        - <?php echo htmlspecialchars($workshop['name']); ?>
                                    <?php endif; ?>
                                </h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Workshop Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($errors)): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-success">
                            <i class="ti ti-check-circle me-2"></i>
                            <strong>Great!</strong> No email errors found for this workshop.
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-alert-triangle me-1"></i> Email Errors (<?php echo count($errors); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($errors as $error): ?>
                                <div class="card error-card mb-3 <?php echo $error['is_resolved'] ? 'resolved' : ''; ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="badge bg-danger error-type-badge me-2">
                                                        <?php echo strtoupper($error['error_type']); ?>
                                                    </span>
                                                    <strong><?php echo htmlspecialchars($error['user_name']); ?></strong>
                                                    <span class="text-muted ms-2">(<?php echo htmlspecialchars($error['user_email']); ?>)</span>
                                                    <?php if ($error['is_resolved']): ?>
                                                    <span class="badge bg-success ms-2">RESOLVED</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="user-details">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Mobile:</strong> <?php echo htmlspecialchars($error['mobile']); ?><br>
                                                            <strong>Designation:</strong> <?php echo htmlspecialchars($error['designation'] ?: 'N/A'); ?><br>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Institute:</strong> <?php echo htmlspecialchars($error['institute_name'] ?: 'N/A'); ?><br>
                                                            <strong>City:</strong> <?php echo htmlspecialchars($error['city'] ?: 'N/A'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <strong>Error Message:</strong>
                                                    <p class="text-danger mb-1"><?php echo htmlspecialchars($error['error_message']); ?></p>
                                                    <?php if ($error['retry_count'] > 0): ?>
                                                    <small class="text-warning">
                                                        <i class="ti ti-refresh me-1"></i>Retry Count: <?php echo $error['retry_count']; ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="text-muted small">
                                                    <strong>Created:</strong> <?php echo date('d M Y, h:i A', strtotime($error['created_at'])); ?>
                                                    <?php if ($error['resolved_at']): ?>
                                                    | <strong>Resolved:</strong> <?php echo date('d M Y, h:i A', strtotime($error['resolved_at'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 text-md-end">
                                                <div class="btn-group-vertical d-grid gap-2">
                                                    <?php if (!$error['is_resolved']): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="editUser(<?php echo $error['id']; ?>)">
                                                        <i class="ti ti-edit me-1"></i> Edit User Details
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="retryEmail(<?php echo $error['id']; ?>)">
                                                        <i class="ti ti-refresh me-1"></i> Retry Email
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="markResolved(<?php echo $error['id']; ?>)">
                                                        <i class="ti ti-check me-1"></i> Mark as Resolved
                                                    </button>
                                                    <?php else: ?>
                                                    <span class="badge bg-success">Already Resolved</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Edit Form (Hidden by default) -->
                                        <div class="edit-form mt-3" id="editForm_<?php echo $error['id']; ?>">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6 class="card-title">Edit User Details</h6>
                                                    <form id="editForm_<?php echo $error['id']; ?>_form">
                                                        <input type="hidden" name="error_id" value="<?php echo $error['id']; ?>">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Name</label>
                                                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($error['user_name']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Email</label>
                                                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($error['user_email']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mobile</label>
                                                                    <input type="text" class="form-control" name="mobile" value="<?php echo htmlspecialchars($error['mobile']); ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Designation</label>
                                                                    <input type="text" class="form-control" name="designation" value="<?php echo htmlspecialchars($error['designation'] ?: ''); ?>">
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Institute Name</label>
                                                                    <input type="text" class="form-control" name="institute_name" value="<?php echo htmlspecialchars($error['institute_name'] ?: ''); ?>">
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">City</label>
                                                                    <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($error['city'] ?: ''); ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="ti ti-device-floppy me-1"></i> Update User
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" onclick="cancelEdit(<?php echo $error['id']; ?>)">
                                                                <i class="ti ti-x me-1"></i> Cancel
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Start -->
        <?php include 'includes/footer.php'; ?>
        <!-- end Footer -->
    </div>

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        function editUser(errorId) {
            const editForm = document.getElementById('editForm_' + errorId);
            editForm.classList.add('show');
        }

        function cancelEdit(errorId) {
            const editForm = document.getElementById('editForm_' + errorId);
            editForm.classList.remove('show');
        }

        function markResolved(errorId) {
            if (!confirm('Are you sure you want to mark this error as resolved?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'mark_resolved');
            formData.append('error_id', errorId);

            fetch('error_emails_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Error marked as resolved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function retryEmail(errorId) {
            if (!confirm('Are you sure you want to retry sending the email?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'retry_email');
            formData.append('error_id', errorId);

            fetch('error_emails_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Email sent successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Handle edit form submissions
        document.addEventListener('DOMContentLoaded', function() {
            const editForms = document.querySelectorAll('[id^="editForm_"][id$="_form"]');
            
            editForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'update_user');
                    
                    fetch('error_emails_management.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('User details updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
                });
            });
        });
    </script>
</body>
</html>
