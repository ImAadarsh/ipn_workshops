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
$workshop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get workshop details
$workshop = null;
$sql = "SELECT * FROM workshops WHERE id = $workshop_id AND is_deleted = 0";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    $workshop = mysqli_fetch_assoc($result);
}

// Get workshop stats
$workshopStats = [
    'b2b' => 0,
    'b2c' => 0,
    'mail_sent' => 0
];

if ($workshop) {
    $sql = "SELECT school_id, mail_send FROM payments WHERE workshop_id = $workshop_id AND payment_status = 1";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['school_id'])) {
                $workshopStats['b2b']++;
            } else {
                $workshopStats['b2c']++;
            }
            if ($row['mail_send'] == 1) {
                $workshopStats['mail_sent']++;
            }
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rlink = mysqli_real_escape_string($conn, $_POST['rlink']);
    $meeting_id = mysqli_real_escape_string($conn, $_POST['meeting_id']);
    $passcode = mysqli_real_escape_string($conn, $_POST['passcode']);
    
    $sql = "UPDATE workshops SET 
            rlink = '$rlink',
            meeting_id = '$meeting_id',
            passcode = '$passcode'
            WHERE id = $workshop_id";
            
    if (mysqli_query($conn, $sql)) {
        $success_message = "Workshop details updated successfully!";
        // Refresh workshop data
        $result = mysqli_query($conn, "SELECT * FROM workshops WHERE id = $workshop_id");
        if ($result) {
            $workshop = mysqli_fetch_assoc($result);
        }
    } else {
        $error_message = "Error updating workshop details: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Workshop Details | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
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
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Workshop Details</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($workshop): ?>
                <!-- Workshop Details Card -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-4">
                                        <?php if ($workshop['image']): ?>
                                        <img src="<?php echo $uri.$workshop['image']; ?>" class="mb-3" style="border-radius: 20px; border: solid 8px green;" width="100%" alt="Workshop">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h3 class="mb-2 text-primary"><?php echo $workshop['name']; ?></h3>
                                            <div>
                                                <?php if ($workshop['rlink']): ?>
                                                <span class="badge bg-success p-2 me-2">
                                                    <i class="ti ti-video me-1"></i> Recording Avail.
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($workshop['meeting_id'] && $workshop['passcode']): ?>
                                                <span class="badge bg-primary p-2">
                                                    <i class="ti ti-brand-zoom me-1"></i> Zoom Link Added
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="text-muted mb-3 fs-5"><?php echo $workshop['trainer_name']; ?></p>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <h5 class="mb-1">Start Date</h5>
                                                <p class="text-muted mb-0 fs-5">
                                                    <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4">
                                                <h5 class="mb-1">Duration</h5>
                                                <p class="text-muted mb-0 fs-5"><?php echo $workshop['duration']; ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <h5 class="mb-1">Price</h5>
                                                <?php if ($workshop['cut_price']): ?>
                                                <p class="mb-0 fs-5">
                                                    <span class="text-decoration-line-through text-muted">₹<?php echo number_format($workshop['cut_price'], 2); ?></span>
                                                    <span class="text-success ms-2">₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                </p>
                                                <?php else: ?>
                                                <p class="mb-0 text-success fs-5">₹<?php echo number_format($workshop['price'], 2); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Workshop Stats -->
                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <span class="fw-bold">B2B User Enrollment:</span>
                                                <span class="badge bg-primary fs-5 ms-1"><?php echo $workshopStats['b2b']; ?></span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="fw-bold">B2C User Enrollment:</span>
                                                <span class="badge bg-success fs-5 ms-1"><?php echo $workshopStats['b2c']; ?></span>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="fw-bold">Mails Sent:</span>
                                                <span class="badge bg-warning text-dark fs-5 ms-1"><?php echo $workshopStats['mail_sent']; ?></span>
                                            </div>
                                        </div>

                                        <!-- Workshop Links -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-link me-1"></i> Workshop Important Links
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="https://ipnacademy.in/feedback.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-info" target="_blank">
                                                                <i class="ti ti-message-circle me-1"></i> Feedback
                                                            </a>
                                                            <a href="https://ipnacademy.in/feedback_report.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-info" target="_blank">
                                                                <i class="ti ti-report me-1"></i> Feedback Report
                                                            </a>
                                                            <a href="https://ipnacademy.in/workshop_assessment.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-info" target="_blank">
                                                                <i class="ti ti-clipboard-check me-1"></i> Assessment
                                                            </a>
                                                            <a href="https://ipnacademy.in/workshop_assessment_report.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-info" target="_blank">
                                                                <i class="ti ti-file-report me-1"></i> Assessment Report
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Instamojo CSV Upload -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-upload me-1"></i> Instamojo CSV Upload
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="process_csv.php" enctype="multipart/form-data" onsubmit="return confirmCsvUpload()">
                                                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Select CSV File</label>
                                                                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                                                <small class="text-muted">Please upload the Instamojo CSV file containing payment details.</small>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="ti ti-upload me-1"></i> Upload and Process
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Edit Forms -->
                                        <div class="row mt-4">
                                            <!-- Vimeo Recording Form -->
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-video me-1"></i> Vimeo Recording
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="update_recording.php" onsubmit="return confirmRecordingUpdate()">
                                                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Recording Link</label>
                                                                <input type="text" class="form-control" name="rlink" value="<?php echo htmlspecialchars($workshop['rlink']); ?>" placeholder="Enter Vimeo recording ID">
                                                            </div>
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="ti ti-device-floppy me-1"></i> Update Recording
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Zoom Meeting Form -->
                                            <div class="col-md-6">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-brand-zoom me-1"></i> Zoom Meeting
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="update_zoom.php" onsubmit="return confirmZoomUpdate()">
                                                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Meeting ID</label>
                                                                <input type="text" class="form-control" name="meeting_id" value="<?php echo htmlspecialchars($workshop['meeting_id']); ?>" placeholder="Enter Zoom Meeting ID">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Passcode</label>
                                                                <input type="text" class="form-control" name="passcode" value="<?php echo htmlspecialchars($workshop['passcode']); ?>" placeholder="Enter Zoom Passcode">
                                                            </div>
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="ti ti-device-floppy me-1"></i> Update Zoom Details
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add JavaScript for confirmation dialogs -->
                                        <script>
                                            function confirmRecordingUpdate() {
                                                return confirm('Are you sure you want to update the recording link?');
                                            }

                                            function confirmZoomUpdate() {
                                                return confirm('Are you sure you want to update the Zoom meeting details?');
                                            }

                                            function confirmCsvUpload() {
                                                return confirm('Are you sure you want to process this CSV file? This will create/update user records and payments.');
                                            }
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger mt-4">
                    Workshop not found or has been deleted.
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

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 