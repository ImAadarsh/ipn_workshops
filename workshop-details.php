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
$workshop_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
    <!-- jQuery must be loaded before other scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

                <!-- Registered Users Buttons -->
                <div class="row mb-3">
                    <div class="col-auto">
                        <a href="registered_users.php?tab=b2b&workshop=<?php echo $workshop_id; ?>" class="btn btn-outline-primary">
                            <i class="ti ti-users me-1"></i> View B2B Users
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="registered_users.php?tab=b2c&workshop=<?php echo $workshop_id; ?>" class="btn btn-outline-success">
                            <i class="ti ti-user me-1"></i> View B2C Users
                        </a>
                    </div>
                    <div class="col-auto">
                        <a href="registered_users.php?tab=discarded&workshop=<?php echo $workshop_id; ?>" class="btn btn-outline-danger">
                            <i class="ti ti-alert-triangle me-1"></i> View Discarded Entries
                        </a>
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

                                        <?php if ($workshop['type'] == 1): ?>
                                        <!-- Attendance Panel -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header d-flex justify-content-between align-items-center">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-chart-bar me-1"></i> Attendance Statistics
                                                        </h5>
                                                        <a href="attendance_report.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="ti ti-file-text me-1"></i> View Full Attendance Report
                                                        </a>
                                                    </div>
                                                    <div class="card-body">
                                                        <?php
                                                        // Get attendance statistics
                                                        $attendance_stats = [
                                                            'total_enrolled' => 0,
                                                            'total_attended' => 0,
                                                            'avg_duration' => 0,
                                                            'completion_rate' => 0,
                                                            'duration_stats' => [
                                                                '0-15' => 0,
                                                                '15-30' => 0,
                                                                '30-60' => 0,
                                                                '60+' => 0
                                                            ]
                                                        ];

                                                        // Get total enrolled
                                                        $enrolled_sql = "SELECT COUNT(*) as total FROM payments WHERE workshop_id = $workshop_id AND payment_status = 1";
                                                        $enrolled_result = mysqli_query($conn, $enrolled_sql);
                                                        if ($enrolled_result) {
                                                            $attendance_stats['total_enrolled'] = mysqli_fetch_assoc($enrolled_result)['total'];
                                                        }

                                                        // Get attendance stats
                                                        $attendance_sql = "SELECT 
                                                            COUNT(*) as total_attended,
                                                            AVG(attended_duration) as avg_duration,
                                                            SUM(CASE WHEN attended_duration > 0 AND attended_duration <= 15 THEN 1 ELSE 0 END) as duration_0_15,
                                                            SUM(CASE WHEN attended_duration > 15 AND attended_duration <= 30 THEN 1 ELSE 0 END) as duration_15_30,
                                                            SUM(CASE WHEN attended_duration > 30 AND attended_duration <= 60 THEN 1 ELSE 0 END) as duration_30_60,
                                                            SUM(CASE WHEN attended_duration > 60 THEN 1 ELSE 0 END) as duration_60_plus
                                                            FROM payments 
                                                            WHERE workshop_id = $workshop_id 
                                                            AND payment_status = 1 
                                                            AND is_attended = 1";
                                                        
                                                        $attendance_result = mysqli_query($conn, $attendance_sql);
                                                        if ($attendance_result) {
                                                            $stats = mysqli_fetch_assoc($attendance_result);
                                                            $attendance_stats['total_attended'] = $stats['total_attended'];
                                                            $attendance_stats['avg_duration'] = round($stats['avg_duration'], 1);
                                                            $attendance_stats['duration_stats']['0-15'] = $stats['duration_0_15'];
                                                            $attendance_stats['duration_stats']['15-30'] = $stats['duration_15_30'];
                                                            $attendance_stats['duration_stats']['30-60'] = $stats['duration_30_60'];
                                                            $attendance_stats['duration_stats']['60+'] = $stats['duration_60_plus'];
                                                            
                                                            // Calculate completion rate
                                                            if ($attendance_stats['total_enrolled'] > 0) {
                                                                $attendance_stats['completion_rate'] = round(($attendance_stats['total_attended'] / $attendance_stats['total_enrolled']) * 100, 1);
                                                            }
                                                        }
                                                        ?>

                                                        <!-- Attendance Overview -->
                                                        <div class="row mb-4">
                                                            <div class="col-md-3">
                                                                <div class="card bg-primary text-white">
                                                                    <div class="card-body text-center">
                                                                        <h6 class="card-title">Total Enrolled</h6>
                                                                        <h2 class="mb-0"><?php echo $attendance_stats['total_enrolled']; ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="card bg-success text-white">
                                                                    <div class="card-body text-center">
                                                                        <h6 class="card-title">Total Attended</h6>
                                                                        <h2 class="mb-0"><?php echo $attendance_stats['total_attended']; ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="card bg-info text-white">
                                                                    <div class="card-body text-center">
                                                                        <h6 class="card-title">Avg. Duration</h6>
                                                                        <h2 class="mb-0"><?php echo $attendance_stats['avg_duration']; ?> min</h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="card bg-warning text-white">
                                                                    <div class="card-body text-center">
                                                                        <h6 class="card-title">Completion Rate</h6>
                                                                        <h2 class="mb-0"><?php echo $attendance_stats['completion_rate']; ?>%</h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Duration Distribution -->
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <h6 class="mb-3">Duration Distribution</h6>
                                                                <div class="progress" style="height: 25px;">
                                                                    <?php
                                                                    $total = array_sum($attendance_stats['duration_stats']);
                                                                    if ($total > 0) {
                                                                        $width_0_15 = ($attendance_stats['duration_stats']['0-15'] / $total) * 100;
                                                                        $width_15_30 = ($attendance_stats['duration_stats']['15-30'] / $total) * 100;
                                                                        $width_30_60 = ($attendance_stats['duration_stats']['30-60'] / $total) * 100;
                                                                        $width_60_plus = ($attendance_stats['duration_stats']['60+'] / $total) * 100;
                                                                    ?>
                                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $width_0_15; ?>%" 
                                                                         title="0-15 min: <?php echo $attendance_stats['duration_stats']['0-15']; ?> participants">
                                                                        <?php echo round($width_0_15); ?>%
                                                                    </div>
                                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $width_15_30; ?>%" 
                                                                         title="15-30 min: <?php echo $attendance_stats['duration_stats']['15-30']; ?> participants">
                                                                        <?php echo round($width_15_30); ?>%
                                                                    </div>
                                                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $width_30_60; ?>%" 
                                                                         title="30-60 min: <?php echo $attendance_stats['duration_stats']['30-60']; ?> participants">
                                                                        <?php echo round($width_30_60); ?>%
                                                                    </div>
                                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $width_60_plus; ?>%" 
                                                                         title="60+ min: <?php echo $attendance_stats['duration_stats']['60+']; ?> participants">
                                                                        <?php echo round($width_60_plus); ?>%
                                                                    </div>
                                                                    <?php } ?>
                                                                </div>
                                                                <div class="d-flex justify-content-between mt-2">
                                                                    <small class="text-danger">0-15 min</small>
                                                                    <small class="text-warning">15-30 min</small>
                                                                    <small class="text-info">30-60 min</small>
                                                                    <small class="text-success">60+ min</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

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

                                        <!-- CSV Processing Message -->
                                        <?php if (isset($_SESSION['processing_message'])): ?>
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="alert alert-info alert-dismissible fade show" role="alert">
                                                    <h5 class="alert-heading mb-2">
                                                        <i class="ti ti-info-circle me-1"></i> CSV Processing Results
                                                    </h5>
                                                    <div class="mb-0">
                                                        <?php echo nl2br(htmlspecialchars($_SESSION['processing_message'])); ?>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php 
                                        // Clear the message after displaying
                                        unset($_SESSION['processing_message']);
                                        endif; 
                                        ?>

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

                                        <!-- Manual CSV Upload -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-file-upload me-1"></i> Manual CSV Upload
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="process_manual_csv.php" enctype="multipart/form-data" onsubmit="return confirmManualCsvUpload()">
                                                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Select CSV File</label>
                                                                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                                                <small class="text-muted">Please upload a CSV file with the following columns:<br>
                                                                • Name (required)<br>
                                                                • Email (required)<br>
                                                                • Mobile (required)<br>
                                                                • Designation<br>
                                                                • Institute Name<br>
                                                                • City</small>
                                                            </div>
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="ti ti-upload me-1"></i> Upload and Process
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Zoom Timing Upload -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-clock me-1"></i> Zoom Attendance Upload
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="process_zoom_attendance.php" enctype="multipart/form-data" onsubmit="return confirmZoomAttendanceUpload()">
                                                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Select Zoom Attendance CSV</label>
                                                                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                                                <small class="text-muted">Please upload the Zoom attendance CSV file with the following columns:<br>
                                                                • Name (original name) - Format: {user_id}_{Full Name}<br>
                                                                • Total duration (minutes)</small>
                                                            </div>
                                                            <button type="submit" class="btn btn-info">
                                                                <i class="ti ti-upload me-1"></i> Upload Attendance
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- School Bulk Enroll Section -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-user-plus me-1"></i> Bulk Enroll Teachers by School
                                                        </h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Select School</label>
                                                                <select id="bulkEnrollSchool" class="form-select">
                                                                    <option value="">-- Select School --</option>
                                                                    <?php
                                                                    $all_schools = mysqli_query($conn, "SELECT id, name FROM schools ORDER BY name");
                                                                    while ($s = mysqli_fetch_assoc($all_schools)) {
                                                                        echo '<option value="' . $s['id'] . '">' . htmlspecialchars($s['name']) . '</option>';
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div id="schoolUsersSection" style="display:none;">
                                                            <form id="bulkEnrollForm">
                                                                <div class="table-responsive mb-3">
                                                                    <table class="table table-bordered" id="schoolUsersTable">
                                                                        <thead>
                                                                            <tr>
                                                                                <th><input type="checkbox" id="selectAllSchoolUsers"></th>
                                                                                <th>Name</th>
                                                                                <th>Email</th>
                                                                                <th>Mobile</th>
                                                                                <th>Designation</th>
                                                                                <th>Already Enrolled?</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody></tbody>
                                                                    </table>
                                                                </div>
                                                                <button type="submit" class="btn btn-success" id="enrollSelectedSchoolUsers" disabled>Enroll Selected Teachers</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- End School Bulk Enroll Section -->

                                        <!-- Add JavaScript for confirmation dialogs -->
                                        <script>
                                            function confirmRecordingUpdate() {
                                                return confirm('Are you sure you want to update the recording link?');
                                            }

                                            function confirmZoomUpdate() {
                                                return confirm('Are you sure you want to update the Zoom meeting details?');
                                            }

                                            function confirmCsvUpload() {
                                                return confirm('Are you sure you want to process this Instamojo CSV file? This will create/update user records and payments.');
                                            }

                                            function confirmManualCsvUpload() {
                                                return confirm('Are you sure you want to process this manual CSV file? This will create/update user records.');
                                            }

                                            function confirmZoomAttendanceUpload() {
                                                return confirm('Are you sure you want to process this Zoom attendance CSV file? This will update attendance records for users.');
                                            }
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Search and Enrollment Section -->
                <div class="row mt-4">
                    <!-- Schools Registered Panel (moved here, full width, bigger cards) -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-building me-1"></i> Schools & Teacher Count
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                <?php
                                // Get all schools that have at least one user registered for this workshop (via payments or users table)
                                $schools_sql = "SELECT s.id, s.name, s.email, s.mobile
                                    FROM schools s
                                    WHERE (
                                        EXISTS (
                                            SELECT 1 FROM payments p WHERE p.workshop_id = $workshop_id AND p.payment_status = 1 AND p.school_id = s.id
                                        )
                                        OR EXISTS (
                                            SELECT 1 FROM payments p INNER JOIN users u ON p.user_id = u.id WHERE p.workshop_id = $workshop_id AND p.payment_status = 1 AND u.school_id = s.id
                                        )
                                    )
                                    ORDER BY s.name ASC";
                                $schools_result = mysqli_query($conn, $schools_sql);
                                $hasSchools = false;
                                while ($school = mysqli_fetch_assoc($schools_result)) {
                                    $hasSchools = true;
                                    // Count unique users for this school for this workshop
                                    $count_sql = "SELECT COUNT(DISTINCT u.id) as teacher_count
                                        FROM payments p
                                        INNER JOIN users u ON p.user_id = u.id
                                        WHERE p.workshop_id = $workshop_id AND p.payment_status = 1
                                        AND (p.school_id = {$school['id']} OR u.school_id = {$school['id']})";
                                    $count_result = mysqli_query($conn, $count_sql);
                                    $teacher_count = 0;
                                    if ($count_result) {
                                        $teacher_count = (int)mysqli_fetch_assoc($count_result)['teacher_count'];
                                    }
                                ?>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="card border shadow-lg h-100" style="min-height: 180px; font-size: 1.15rem;">
                                            <div class="card-body d-flex flex-column justify-content-center align-items-start">
                                                <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($school['name']); ?></h4>
                                                <div class="mb-2"><i class="ti ti-mail me-1"></i> <span class="text-muted"><?php echo htmlspecialchars($school['email']); ?></span></div>
                                                <div class="mb-3"><i class="ti ti-phone me-1"></i> <span class="text-muted"><?php echo htmlspecialchars($school['mobile']); ?></span></div>
                                                <div><a href="school_teachers.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school['id']; ?>" class="badge bg-primary fs-5 px-3 py-2" style="cursor:pointer; text-decoration:none;">Teachers: <?php echo $teacher_count; ?></a></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                                if (!$hasSchools): ?>
                                    <div class="col-12 text-center text-muted">No schools with registered teachers for this workshop.</div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <!-- End Schools Registered Panel -->

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Search and Enroll Users</h5>
                    </div>
                    <div class="card-body">
                        <form id="searchForm" class="mb-4" method="GET">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="searchName">Name</label>
                                        <input type="text" class="form-control" id="searchName" name="name" 
                                               value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>" 
                                               placeholder="Search by name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="searchEmail">Email</label>
                                        <input type="email" class="form-control" id="searchEmail" name="email" 
                                               value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>" 
                                               placeholder="Search by email">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="searchMobile">Mobile</label>
                                        <input type="text" class="form-control" id="searchMobile" name="mobile" 
                                               value="<?php echo isset($_GET['mobile']) ? htmlspecialchars($_GET['mobile']) : ''; ?>" 
                                               placeholder="Search by mobile">
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <button type="button" class="btn btn-secondary" onclick="clearSearch()">Clear</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Designation</th>
                                        <th>Institute</th>
                                        <th>City</th>
                                    </tr>
                                </thead>
                                <tbody id="searchResults">
                                    <!-- Search results will be populated here -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <button id="enrollSelected" class="btn btn-success" disabled>Enroll Selected Users</button>
                        </div>
                    </div>
                </div>

                <script>
                $(document).ready(function() {
                    // Initial search if parameters exist
                    if (window.location.search) {
                        performSearch();
                    }

                    // Handle search form submission
                    $('#searchForm').on('submit', function(e) {
                        e.preventDefault();
                        performSearch();
                    });

                    function performSearch() {
                        const formData = $('#searchForm').serialize();
                        
                        $.ajax({
                            url: 'search_users.php',
                            type: 'GET',
                            data: formData,
                            dataType: 'json',
                            success: function(users) {
                                let html = '';
                                
                                if (!users || users.length === 0) {
                                    html = '<tr><td colspan="7" class="text-center">No users found</td></tr>';
                                } else {
                                    users.forEach(user => {
                                        html += `
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input user-checkbox" type="checkbox" value="${user.id}">
                                                    </div>
                                                </td>
                                                <td>${user.name || '-'}</td>
                                                <td>${user.email || '-'}</td>
                                                <td>${user.mobile || '-'}</td>
                                                <td>${user.designation || '-'}</td>
                                                <td>${user.institute_name || '-'}</td>
                                                <td>${user.city || '-'}</td>
                                            </tr>
                                        `;
                                    });
                                }
                                
                                $('#searchResults').html(html);
                                updateEnrollButton();
                            },
                            error: function(xhr, status, error) {
                                console.error('Search error:', error);
                                console.log('Status:', status);
                                console.log('Response:', xhr.responseText);
                                $('#searchResults').html('<tr><td colspan="7" class="text-center text-danger">Error performing search</td></tr>');
                            }
                        });
                    }

                    function clearSearch() {
                        $('#searchName').val('');
                        $('#searchEmail').val('');
                        $('#searchMobile').val('');
                        $('#searchResults').html('');
                        updateEnrollButton();
                    }

                    // Handle select all checkbox
                    $('#selectAll').on('change', function() {
                        $('.user-checkbox').prop('checked', $(this).prop('checked'));
                        updateEnrollButton();
                    });

                    // Handle individual checkboxes
                    $(document).on('change', '.user-checkbox', function() {
                        updateEnrollButton();
                    });

                    // Update enroll button state
                    function updateEnrollButton() {
                        const checkedCount = $('.user-checkbox:checked').length;
                        $('#enrollSelected').prop('disabled', checkedCount === 0);
                    }

                    // Handle enroll button click
                    $('#enrollSelected').on('click', function() {
                        const selectedUsers = [];
                        $('.user-checkbox:checked').each(function() {
                            selectedUsers.push($(this).val());
                        });

                        if (selectedUsers.length === 0) return;

                        if (!confirm(`Are you sure you want to enroll ${selectedUsers.length} users in this workshop?`)) {
                            return;
                        }

                        $.ajax({
                            url: 'enroll_users.php',
                            type: 'POST',
                            data: {
                                workshop_id: <?php echo $workshop_id; ?>,
                                user_ids: selectedUsers
                            },
                            success: function(response) {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    alert(`Successfully enrolled ${result.enrolled} users.`);
                                    if (result.errors.length > 0) {
                                        alert('Some errors occurred:\n' + result.errors.join('\n'));
                                    }
                                    // Refresh the page to show updated statistics
                                    location.reload();
                                } else {
                                    alert('Error enrolling users: ' + result.errors.join('\n'));
                                }
                            },
                            error: function() {
                                alert('Error enrolling users');
                            }
                        });
                    });

                    // Fetch users when school is selected
                    $('#bulkEnrollSchool').on('change', function() {
                        var schoolId = $(this).val();
                        if (!schoolId) {
                            $('#schoolUsersSection').hide();
                            return;
                        }
                        $.get('fetch_school_users.php', {
                            school_id: schoolId,
                            workshop_id: <?php echo $workshop_id; ?>
                        }, function(users) {
                            var tbody = '';
                            if (users.length === 0) {
                                tbody = '<tr><td colspan="6" class="text-center">No teachers found for this school.</td></tr>';
                            } else {
                                users.forEach(function(user) {
                                    tbody += '<tr>' +
                                        '<td><input type="checkbox" class="school-user-checkbox" value="' + user.id + '"' + (user.enrolled ? ' disabled' : '') + '></td>' +
                                        '<td>' + (user.name || '-') + '</td>' +
                                        '<td>' + (user.email || '-') + '</td>' +
                                        '<td>' + (user.mobile || '-') + '</td>' +
                                        '<td>' + (user.designation || '-') + '</td>' +
                                        '<td>' + (user.enrolled ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>') + '</td>' +
                                    '</tr>';
                                });
                            }
                            $('#schoolUsersTable tbody').html(tbody);
                            console.log('Loaded users:', users);
                            $('#schoolUsersSection').show();
                            $('#schoolUsersSection').css('display', 'block'); // force show
                            $('#enrollSelectedSchoolUsers').prop('disabled', true);
                        }, 'json');
                    });

                    // Select all checkboxes
                    $(document).on('change', '#selectAllSchoolUsers', function() {
                        $('.school-user-checkbox:enabled').prop('checked', $(this).prop('checked'));
                        updateBulkEnrollButton();
                    });
                    // Individual checkbox
                    $(document).on('change', '.school-user-checkbox', function() {
                        updateBulkEnrollButton();
                    });
                    function updateBulkEnrollButton() {
                        var checkedCount = $('.school-user-checkbox:checked').length;
                        $('#enrollSelectedSchoolUsers').prop('disabled', checkedCount === 0);
                    }
                    // Handle form submit
                    $('#bulkEnrollForm').on('submit', function(e) {
                        e.preventDefault();
                        var selected = $('.school-user-checkbox:checked').map(function() { return this.value; }).get();
                        if (selected.length === 0) return;
                        if (!confirm('Are you sure you want to enroll ' + selected.length + ' teachers in this workshop?')) return;
                        $.post('enroll_school_users.php', {
                            user_ids: selected,
                            workshop_id: <?php echo $workshop_id; ?>
                        }, function(response) {
                            var result = JSON.parse(response);
                            if (result.success) {
                                alert('Successfully enrolled ' + result.enrolled + ' teachers.');
                                $('#bulkEnrollSchool').trigger('change'); // Refresh list
                            } else {
                                alert('Error: ' + (result.errors ? result.errors.join('\n') : 'Unknown error.'));
                            }
                        });
                    });
                });
                </script>
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
        <!-- Theme Settings -->


<!-- Vendor js -->
<script src="assets/js/vendor.min.js"></script>

<!-- App js -->
<script src="assets/js/app.min.js"></script>
    <!-- Core JS -->
     
    <!-- Remove jQuery from here since it's already in head -->
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 