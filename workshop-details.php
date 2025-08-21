<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection and encoder functions
$conn = require_once 'config/config.php';
require_once 'config/encoder.php';

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
    'b2c2b' => 0,
    'platform_enrolled' => 0,
    'instamojo_payments' => 0,
    'total_users' => 0,
    'mail_sent' => 0
];

if ($workshop) {
    // Get all payments for the workshop
    $sql = "SELECT p.school_id, p.mail_send, s.b2c2b 
            FROM payments p 
            LEFT JOIN schools s ON p.school_id = s.id 
            WHERE p.workshop_id = $workshop_id AND p.payment_status = 1";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Original B2B/B2C logic
            if (!empty($row['school_id'])) {
                $workshopStats['b2b']++;
                // B2C2B logic: count B2B users whose school has b2c2b = 1
                if ($row['b2c2b'] == 1) {
                    $workshopStats['b2c2b']++;
                }
            } else {
                $workshopStats['b2c']++;
            }
            if ($row['mail_send'] == 1) {
                $workshopStats['mail_sent']++;
            }
        }
    }
    
    // Get B2C users with valid platform enrollments (excluding certain payment types and Instamojo)
    $sql = "SELECT COUNT(DISTINCT p.user_id) as platform_count 
            FROM payments p 
            WHERE p.workshop_id = $workshop_id 
            AND p.payment_status = 1 
            AND p.school_id IS NULL 
            AND p.payment_id NOT LIKE '%Membership Redeem%' 
            AND p.payment_id NOT LIKE '%499%' 
            AND p.payment_id NOT LIKE '%Google-Form-Paid%'
            AND p.payment_id NOT LIKE '%G-FORM%' 
            AND p.payment_id NOT LIKE '%G-FORM-PAID%' 
            AND p.payment_id NOT LIKE '%G-Form-Paid%' 
            AND p.payment_id NOT LIKE '%B2B-ENRL%'
            AND p.payment_id IS NOT NULL 
            AND p.payment_id != ''
            AND p.instamojo_upload != 1";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $workshopStats['platform_enrolled'] = $row['platform_count'];
    }
    
    // Get Instamojo payments count
    $sql = "SELECT COUNT(DISTINCT p.user_id) as instamojo_count 
            FROM payments p 
            WHERE p.workshop_id = $workshop_id 
            AND p.payment_status = 1 
            AND p.school_id IS NULL 
            AND p.instamojo_upload = 1";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $workshopStats['instamojo_payments'] = $row['instamojo_count'];
    }
    
    // Calculate total users (B2B + B2C)
    $workshopStats['total_users'] = $workshopStats['b2b'] + $workshopStats['b2c'];
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
    
    <!-- Custom CSS for enhanced design -->
    <style>
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .hover-shadow:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        .card {
            border-radius: 12px;
        }
        .card-header {
            border-radius: 12px 12px 0 0 !important;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        .badge {
            border-radius: 6px;
        }
        .text-truncate {
            max-width: 200px;
        }
        .stat-card {
            transition: all 0.3s ease;
            min-height: 80px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important;
        }
        .stat-card[style*="cursor: pointer"]:hover {
            background-color: rgba(0,0,0,0.05) !important;
        }
        .stat-number {
            line-height: 1.2;
        }
        .stat-label {
            margin-top: 4px;
        }
        @media (max-width: 768px) {
            .text-truncate {
                max-width: 150px;
            }
            .stat-card {
                min-height: 70px;
            }
            .stat-number {
                font-size: 1.5rem !important;
            }
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
                                    <!-- <div class="col-md-4">
                                        <?php if ($workshop['image']): ?>
                                        <img src="<?php echo $uri.$workshop['image']; ?>" class="mb-3" style="border-radius: 20px; border: solid 8px green;" width="100%" alt="Workshop">
                                        <?php endif; ?>
                                    </div> -->
                                    <div class="col-md-12">
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
                                        <div class="mt-4">
                                            <h6 class="text-primary mb-3">
                                                <i class="ti ti-chart-pie me-2"></i>Enrollment Statistics
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-primary-subtle border border-primary rounded-3 p-3 text-center" style="cursor: pointer;" onclick="showUserList('b2b', <?php echo $workshopStats['b2b'] - $workshopStats['b2c2b']; ?>)">
                                                        <div class="stat-number text-primary fw-bold fs-4"><?php echo $workshopStats['b2b'] - $workshopStats['b2c2b']; ?></div>
                                                        <div class="stat-label text-muted small">B2B Users</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-success-subtle border border-success rounded-3 p-3 text-center" style="cursor: pointer;" onclick="showUserList('b2c', <?php echo $workshopStats['b2c']; ?>)">
                                                        <div class="stat-number text-success fw-bold fs-4"><?php echo $workshopStats['b2c']; ?></div>
                                                        <div class="stat-label text-muted small">B2C Users</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-info-subtle border border-info rounded-3 p-3 text-center" style="cursor: pointer;" onclick="showUserList('b2c2b', <?php echo $workshopStats['b2c2b']; ?>)">
                                                        <div class="stat-number text-info fw-bold fs-4"><?php echo $workshopStats['b2c2b']; ?></div>
                                                        <div class="stat-label text-muted small">B2C2B Users</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-warning-subtle border border-warning rounded-3 p-3 text-center" style="cursor: pointer;" onclick="showUserList('platform', <?php echo $workshopStats['platform_enrolled']; ?>)">
                                                        <div class="stat-number text-warning fw-bold fs-4"><?php echo $workshopStats['platform_enrolled']; ?></div>
                                                        <div class="stat-label text-muted small">Platform</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-danger-subtle border border-danger rounded-3 p-3 text-center" style="cursor: pointer;" onclick="showUserList('instamojo', <?php echo $workshopStats['instamojo_payments']; ?>)">
                                                        <div class="stat-number text-danger fw-bold fs-4"><?php echo $workshopStats['instamojo_payments']; ?></div>
                                                        <div class="stat-label text-muted small">Instamojo</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-dark-subtle border border-dark rounded-3 p-3 text-center">
                                                        <div class="stat-number text-dark fw-bold fs-4"><?php echo $workshopStats['total_users']; ?></div>
                                                        <div class="stat-label text-muted small">Total Users</div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <div class="stat-card bg-secondary-subtle border border-secondary rounded-3 p-3 text-center">
                                                        <div class="stat-number text-secondary fw-bold fs-4"><?php echo $workshopStats['mail_sent']; ?></div>
                                                        <div class="stat-label text-muted small">Mails Sent</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="alert alert-info border-0 bg-info-subtle">
                                                <div class="d-flex align-items-start">
                                                    <i class="ti ti-info-circle text-info me-2 mt-1"></i>
                                                    <div class="small">
                                                        <strong>Note:</strong> B2C Users (<?php echo $workshopStats['b2c']; ?>) includes Platform Enrolled (<?php echo $workshopStats['platform_enrolled']; ?>) + Instamojo (<?php echo $workshopStats['instamojo_payments']; ?>). 
                                                        <br>Total calculation: B2B (<?php echo $workshopStats['b2b'] - $workshopStats['b2c2b']; ?>) + B2C (<?php echo $workshopStats['b2c']; ?>) + B2C2B (<?php echo $workshopStats['b2c2b']; ?>) = <?php echo $workshopStats['total_users']; ?> users.
                                                    </div>
                                                </div>
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
                                                            <a href="https://ipnacademy.in/workshop_mcq.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-info" target="_blank">
                                                                <i class="ti ti-clipboard-check me-1"></i> MCQ
                                                            </a>
                                                            <a href="https://ipnacademy.in/workshop_mcq_report.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-info" target="_blank">
                                                                <i class="ti ti-file-report me-1"></i> MCQ Report
                                                            </a>
                                                            <a href="public_workshop_links.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-warning" target="_blank" title="View Public School Enrollment Links">
                                                                <i class="ti ti-link me-1"></i> Public School Links
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
                                                                <input type="text" class="form-control" name="meeting_id" value="<?php
                                                                if (isset($workshop['meeting_id']) && !empty($workshop['meeting_id'])&& $workshop['meeting_id']!='#') {
                                                                    echo htmlspecialchars(decodeMeetingId($workshop['meeting_id']));
                                                                }
                                                                ?>" placeholder="Enter Zoom Meeting ID">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Passcode</label>
                                                                <input type="text" class="form-control" name="passcode" value="<?php 
                                                                if (isset($workshop['passcode']) && !empty($workshop['passcode'])&& $workshop['passcode']!='#') {
                                                                    echo htmlspecialchars(decodePasscode($workshop['passcode']));
                                                                }
                                                                ?>" placeholder="Enter Zoom Passcode">
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
                                                    <div class="card-header d-flex justify-content-between align-items-center">
                                                        <h5 class="card-title mb-0">
                                                            <i class="ti ti-user-plus me-1"></i> Bulk Enroll Teachers by School
                                                        </h5>
                                                        <a href="school_links.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="ti ti-link me-1"></i> Generate School Enrollment Link
                                                        </a>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        <p>Enroll multiple teachers from a single school into this workshop.</p>
                                                        <a href="bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-primary">
                                                            <i class="ti ti-user-plus me-1"></i> Go to Bulk Enrollment Page
                                                        </a>
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
                    <!-- Schools Registered Panel -->
                    <div class="col-12 mb-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-gradient-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-building me-2"></i> Registered Schools
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                <?php
                                // Get all schools that have at least one user registered for this workshop OR have a school link generated
                                $schools_sql = "SELECT DISTINCT s.id, s.name, s.email, s.mobile, s.b2c2b
                                    FROM schools s
                                    WHERE (
                                        EXISTS (
                                            SELECT 1 FROM payments p WHERE p.workshop_id = $workshop_id AND p.payment_status = 1 AND p.school_id = s.id
                                        )
                                        OR EXISTS (
                                            SELECT 1 FROM school_links sl WHERE sl.workshop_id = $workshop_id AND sl.school_id = s.id
                                        )
                                    )
                                    ORDER BY s.name ASC";
                                $schools_result = mysqli_query($conn, $schools_sql);
                                $hasSchools = false;
                                while ($school = mysqli_fetch_assoc($schools_result)) {
                                    $hasSchools = true;
                                    
                                    // Check if school has enrollments
                                    $enrollment_sql = "SELECT COUNT(DISTINCT u.id) as teacher_count
                                        FROM payments p
                                        INNER JOIN users u ON p.user_id = u.id
                                        WHERE p.workshop_id = $workshop_id AND p.payment_status = 1
                                        AND (p.school_id = {$school['id']} OR u.school_id = {$school['id']})";
                                    $enrollment_result = mysqli_query($conn, $enrollment_sql);
                                    $teacher_count = 0;
                                    $has_enrollments = false;
                                    if ($enrollment_result) {
                                        $teacher_count = (int)mysqli_fetch_assoc($enrollment_result)['teacher_count'];
                                        $has_enrollments = ($teacher_count > 0);
                                    }
                                    
                                    // Check if school has a link generated
                                    $link_sql = "SELECT id, link FROM school_links WHERE workshop_id = $workshop_id AND school_id = {$school['id']}";
                                    $link_result = mysqli_query($conn, $link_sql);
                                    $has_link = false;
                                    $school_link = '';
                                    if ($link_result && mysqli_num_rows($link_result) > 0) {
                                        $has_link = true;
                                        $link_data = mysqli_fetch_assoc($link_result);
                                        $school_link = $link_data['link'];
                                    }
                                ?>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="card h-100 border-0 shadow-sm hover-shadow <?php echo !$has_enrollments ? 'border-warning' : ''; ?>" 
                                             style="transition: all 0.3s ease; min-height: 200px; <?php echo !$has_enrollments ? 'border-left: 4px solid #ffc107;' : ''; ?>">
                                            <div class="card-header <?php echo !$has_enrollments ? 'bg-warning-subtle' : 'bg-light'; ?> border-0 py-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="fw-bold text-primary mb-0 text-truncate" title="<?php echo htmlspecialchars($school['name']); ?>">
                                                        <?php echo htmlspecialchars($school['name']); ?>
                                                        <?php if (!$has_enrollments && $has_link): ?>
                                                            <i class="ti ti-clock text-warning ms-1" title="Link generated, waiting for enrollments"></i>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <div class="d-flex gap-1">
                                                        <?php if ($school['b2c2b'] == 1): ?>
                                                            <span class="badge bg-info-subtle text-info border border-info">B2C2B</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary">B2B</span>
                                                        <?php endif; ?>
                                                        <?php if (!$has_enrollments && $has_link): ?>
                                                            <span class="badge bg-warning-subtle text-warning border border-warning">Link Ready</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body d-flex flex-column justify-content-between">
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="ti ti-mail text-muted me-2"></i>
                                                        <small class="text-muted text-truncate" title="<?php echo htmlspecialchars($school['email']); ?>">
                                                            <?php echo htmlspecialchars($school['email']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="ti ti-phone text-muted me-2"></i>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($school['mobile']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <i class="ti ti-users text-muted me-2"></i>
                                                        <small class="text-muted">
                                                            <?php if ($has_enrollments): ?>
                                                                <strong><?php echo $teacher_count; ?></strong> teachers enrolled
                                                            <?php else: ?>
                                                                <span class="text-warning">No enrollments yet</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($has_link): ?>
                                                    <div class="d-flex align-items-center mt-1">
                                                        <i class="ti ti-link text-muted me-2"></i>
                                                        <small class="text-success">
                                                            <strong>Link Generated</strong>
                                                        </small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <?php if ($has_enrollments): ?>
                                                    <a href="school_teachers.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm flex-fill" 
                                                       title="View attendance sheet">
                                                        <i class="ti ti-clipboard-check me-1"></i>
                                                        Attendance
                                                    </a>
                                                    <a href="schoolWiseJL.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school['id']; ?>&email=<?php echo $school['email']; ?>" 
                                                       class="btn btn-outline-warning btn-sm flex-fill"
                                                       title="View joining links for all enrolled teachers"
                                                       target="_blank">
                                                        <i class="ti ti-link me-1"></i>
                                                        School Wise JL
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school['id']; ?>&email=<?php echo $school['email']; ?>" 
                                                       class="btn btn-outline-success btn-sm flex-fill"
                                                       title="Enroll new teachers">
                                                        <i class="ti ti-user-plus me-1"></i>
                                                        Enroll
                                                    </a>
                                                    <?php if ($has_link): ?>
                                                    <a href="<?php echo $school_link; ?>" 
                                                       class="btn btn-outline-info btn-sm flex-fill"
                                                       target="_blank"
                                                       title="View enrollment link">
                                                        <i class="ti ti-external-link me-1"></i>
                                                        View Link
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                                if (!$hasSchools): ?>
                                    <div class="col-12">
                                        <div class="text-center py-5">
                                            <i class="ti ti-building-off text-muted" style="font-size: 3rem;"></i>
                                            <h6 class="text-muted mt-3">No schools registered for this workshop</h6>
                                            <p class="text-muted mb-0">Schools will appear here once teachers are enrolled.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <!-- End Schools Registered Panel -->

                <!-- B2C Bulk Enrollment Section -->
                <div class="col-12 mb-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="ti ti-user-plus me-2"></i> Individual User Enrollment
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="text-primary mb-2">B2C Bulk Enrollment</h6>
                                    <p class="text-muted mb-0">Enroll individual users (not associated with a specific school) into this workshop by searching for them in the system.</p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="b2c_bulk_enrollment.php?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-success btn-lg">
                                        <i class="ti ti-user-plus me-2"></i>
                                        Go to B2C Enrollment
                                    </a>
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
        <!-- Theme Settings -->


<!-- Vendor js -->
<script src="assets/js/vendor.min.js"></script>

<!-- App js -->
<script src="assets/js/app.min.js"></script>
    <!-- Core JS -->
     
    <!-- Remove jQuery from here since it's already in head -->
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

    <!-- User List Modals -->
    <!-- B2B Users Modal -->
    <div class="modal fade" id="b2bUsersModal" tabindex="-1" aria-labelledby="b2bUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="b2bUsersModalLabel">B2B Users (Non-B2C2B)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="b2bUsersList" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Institute Name</th>
                                    <th>Payment ID</th>
                                </tr>
                            </thead>
                            <tbody id="b2bUsersTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- B2C Users Modal -->
    <div class="modal fade" id="b2cUsersModal" tabindex="-1" aria-labelledby="b2cUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="b2cUsersModalLabel">B2C Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="b2cUsersList" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Institute Name</th>
                                    <th>Payment ID</th>
                                </tr>
                            </thead>
                            <tbody id="b2cUsersTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- B2C2B Users Modal -->
    <div class="modal fade" id="b2c2bUsersModal" tabindex="-1" aria-labelledby="b2c2bUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="b2c2bUsersModalLabel">B2C2B Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="b2c2bUsersList" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Institute Name</th>
                                    <th>Payment ID</th>
                                </tr>
                            </thead>
                            <tbody id="b2c2bUsersTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Enrolled Users Modal -->
    <div class="modal fade" id="platformUsersModal" tabindex="-1" aria-labelledby="platformUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="platformUsersModalLabel">Platform Enrolled Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="platformUsersList" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Institute Name</th>
                                    <th>Payment ID</th>
                                </tr>
                            </thead>
                            <tbody id="platformUsersTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instamojo Users Modal -->
    <div class="modal fade" id="instamojoUsersModal" tabindex="-1" aria-labelledby="instamojoUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="instamojoUsersModalLabel">Instamojo Payment Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="instamojoUsersList" class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Institute Name</th>
                                    <th>Payment ID</th>
                                </tr>
                            </thead>
                            <tbody id="instamojoUsersTableBody">
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showUserList(type, count) {
            if (count === 0) {
                alert('No users found in this category.');
                return;
            }

            // Show loading
            const modalId = type + 'UsersModal';
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();

            // Load data via AJAX
            fetch('get_workshop_users.php?workshop_id=<?php echo $workshop_id; ?>&type=' + type)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById(type + 'UsersTableBody');
                    tableBody.innerHTML = '';

                    if (data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No users found</td></tr>';
                        return;
                    }

                    data.forEach(user => {
                        const row = `
                            <tr>
                                <td>${user.id}</td>
                                <td>${user.name}</td>
                                <td>${user.email}</td>
                                <td>${user.mobile}</td>
                                <td>${user.institute_name || '-'}</td>
                                <td>${user.payment_id || '-'}</td>
                            </tr>
                        `;
                        tableBody.innerHTML += row;
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user data. Please try again.');
                });
        }
    </script>
</body>
</html> 