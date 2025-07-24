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

// Get upcoming workshops
$upcomingWorkshops = [];
$sql = "SELECT * FROM workshops WHERE start_date > NOW() AND status = 1 AND is_deleted = 0 ORDER BY start_date ASC LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $upcomingWorkshops[] = $row;
    }
}

// Get completed workshops
$completedWorkshops = [];
$sql = "SELECT * FROM workshops WHERE start_date < NOW() AND status = 1 AND is_deleted = 0 ORDER BY start_date DESC LIMIT 5";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $completedWorkshops[] = $row;
    }
}

// Featured upcoming workshop stats
$featuredStats = [
    'b2b' => 0,
    'b2c' => 0,
    'mail_sent' => 0
];
if (!empty($upcomingWorkshops)) {
    $wid = $upcomingWorkshops[0]['id'];
    $sql = "SELECT school_id, mail_send FROM payments WHERE workshop_id = $wid AND payment_status = 1";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['school_id'])) {
                $featuredStats['b2b']++;
            } else {
                $featuredStats['b2c']++;
            }
            if ($row['mail_send'] == 1) {
                $featuredStats['mail_sent']++;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
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

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->
        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                </ol>
                            </div>
                            <h4 class="page-title">Dashboard</h4>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <!-- <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="header-title mb-3">Quick Actions</h4>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <a href="registered_users.php" class="btn btn-primary w-100">
                                            <i class="ti ti-users me-1"></i> View Registered Users
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Upcoming Workshops Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Upcoming Workshops</h4>
                                <a href="workshops.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $isFirst = true;
                                    foreach ($upcomingWorkshops as $workshop):
                                    ?>
                                        <div class="<?php echo $isFirst ? 'col-12 mb-4' : 'col-xl-6 col-md-6'; ?>">
                                            <div class="card <?php echo $isFirst ? 'border-success' : ''; ?>">
                                                <div class="card-body <?php echo $isFirst ? 'bg-success bg-opacity-10' : ''; ?>">
                                                    <?php if ($isFirst): ?>
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <?php if ($workshop['image']): ?>
                                                                    <img src="<?php echo $uri . $workshop['image']; ?>" class="mb-3" style="border-radius: 20px; border: solid 8px green;" width="300" alt="Trainer">
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
                                                                <div class="row mt-3">
                                                                    <div class="col-md-4">
                                                                        <span class="fw-bold">B2B User Enrollment:</span>
                                                                        <span class="badge bg-primary fs-5 ms-1"><?php echo $featuredStats['b2b']; ?></span>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <span class="fw-bold">B2C User Enrollment:</span>
                                                                        <span class="badge bg-success fs-5 ms-1"><?php echo $featuredStats['b2c']; ?></span>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <span class="fw-bold">Mails Sent:</span>
                                                                        <span class="badge bg-warning text-dark fs-5 ms-1"><?php echo $featuredStats['mail_sent']; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-4">
                                                                    <a href="workshop-details.php?id=<?php echo $workshop['id']; ?>" class="btn btn-primary btn-lg">View Details</a>
                                                                    <a href="public_workshop_links.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-warning btn-lg ms-2" target="_blank" title="View Public School Enrollment Links">
                                                                        <i class="ti ti-link"></i>
                                                                    </a>
                                                                    <?php if ($workshop['rlink']): ?>
                                                                        <a href="<?php echo $workshop['rlink']; ?>" class="btn btn-success btn-lg ms-2" target="_blank">
                                                                            <i class="ti ti-video me-1"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <div class="mt-3">
                                                                        <a href="https://ipnacademy.in/feedback.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-message-circle me-1"></i> Feedback
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/feedback_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-report me-1"></i> Feedback Report
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-clipboard-check me-1"></i> Assessment
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                                                            <i class="ti ti-file-report me-1"></i> Assessment Report
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-clipboard-check me-1"></i>MCQ
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                                                            <i class="ti ti-file-report me-1"></i> MCQ Report
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center mb-3">
                                                            <?php if ($workshop['image']): ?>
                                                                <img src="<?php echo $uri . $workshop['image']; ?>" class="rounded-circle me-3" width="50" height="50" alt="Trainer">
                                                            <?php endif; ?>
                                                            <div class="flex-grow-1">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <h5 class="mb-1"><?php echo $workshop['name']; ?></h5>
                                                                    <div>
                                                                        <?php if ($workshop['rlink']): ?>
                                                                            <span class="badge bg-success me-1">
                                                                                <i class="ti ti-video me-1"></i> Recording
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        <?php if ($workshop['meeting_id'] && $workshop['passcode']): ?>
                                                                            <span class="badge bg-primary">
                                                                                <i class="ti ti-brand-zoom me-1"></i> Zoom
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <p class="text-muted mb-0"><?php echo $workshop['trainer_name']; ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <h6 class="mb-1">Start Date</h6>
                                                                <p class="text-muted mb-0">
                                                                    <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1">Duration</h6>
                                                                <p class="text-muted mb-0"><?php echo $workshop['duration']; ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <h6 class="mb-1">Price</h6>
                                                                    <?php if ($workshop['cut_price']): ?>
                                                                        <p class="mb-0">
                                                                            <span class="text-decoration-line-through text-muted">₹<?php echo number_format($workshop['cut_price'], 2); ?></span>
                                                                            <span class="text-primary ms-2">₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                                        </p>
                                                                    <?php else: ?>
                                                                        <p class="mb-0 text-primary">₹<?php echo number_format($workshop['price'], 2); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <a href="workshop-details.php?id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                                                    <a href="public_workshop_links.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-warning btn-sm ms-1" target="_blank" title="View Public School Enrollment Links">
                                                                        <i class="ti ti-link"></i>
                                                                    </a>
                                                                    <?php if ($workshop['rlink']): ?>
                                                                        <a href="<?php echo $workshop['rlink']; ?>" class="btn btn-sm btn-success ms-1" target="_blank">
                                                                            <i class="ti ti-video"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <div class="mt-2">
                                                                        <a href="https://ipnacademy.in/feedback.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Feedback">
                                                                            <i class="ti ti-message-circle"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/feedback_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Feedback Report">
                                                                            <i class="ti ti-report"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Assessment">
                                                                            <i class="ti ti-clipboard-check"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Assessment Report">
                                                                            <i class="ti ti-file-report"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="MCQ">
                                                                            <i class="ti ti-clipboard-check"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="MCQ Report">
                                                                            <i class="ti ti-file-report"></i>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                        $isFirst = false;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Completed Workshops Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Completed Workshops</h4>
                                <a href="workshops.php?type=completed" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $isFirst = true;
                                    foreach ($completedWorkshops as $workshop):
                                    ?>
                                        <div class="<?php echo $isFirst ? 'col-12 mb-4' : 'col-xl-6 col-md-6'; ?>">
                                            <div class="card <?php echo $isFirst ? 'border-primary' : ''; ?>">
                                                <div class="card-body <?php echo $isFirst ? 'bg-primary bg-opacity-10' : ''; ?>">
                                                    <?php if ($isFirst): ?>
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <?php if ($workshop['image']): ?>
                                                                    <img src="<?php echo $uri . $workshop['image']; ?>" class="mb-3" style="border-radius: 20px; border: solid 8px rgb(253, 13, 13);" width="300" alt="Trainer">
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <h3 class="mb-2 text-primary"><?php echo $workshop['name']; ?></h3>
                                                                    <div>
                                                                        <?php if ($workshop['rlink']): ?>
                                                                            <span class="badge bg-success p-2">
                                                                                <i class="ti ti-video me-1"></i> Recording Avail.
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
                                                                        <?php if (!$workshop['cut_price']): ?>
                                                                            <p class="mb-0 fs-5">
                                                                                <span class="text-decoration-line-through text-muted">₹<?php echo number_format($workshop['cut_price'], 2); ?></span>
                                                                                <span class="text-primary ms-2">₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                                            </p>
                                                                        <?php else: ?>
                                                                            <p class="mb-0 text-primary fs-5">₹<?php echo number_format($workshop['price'], 2); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="mt-4">
                                                                    <a href="workshop-details.php?id=<?php echo $workshop['id']; ?>" class="btn btn-primary btn-lg">View Details</a>
                                                                    <a href="public_workshop_links.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-warning btn-lg ms-2" target="_blank" title="View Public School Enrollment Links">
                                                                        <i class="ti ti-link"></i>
                                                                    </a>
                                                                    <?php if ($workshop['rlink']): ?>
                                                                        <a href="<?php echo $workshop['rlink']; ?>" class="btn btn-success btn-lg ms-2" target="_blank">
                                                                            <i class="ti ti-video me-1"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <div class="mt-3">
                                                                        <a href="https://ipnacademy.in/feedback.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-message-circle me-1"></i> Feedback
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/feedback_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-report me-1"></i> Feedback Report
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-clipboard-check me-1"></i> Assessment
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                                                            <i class="ti ti-file-report me-1"></i> Assessment Report
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm me-2" target="_blank">
                                                                            <i class="ti ti-clipboard-check me-1"></i> MCQ
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                                                            <i class="ti ti-file-report me-1"></i> MCQ Report
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center mb-3">
                                                            <?php if ($workshop['image']): ?>
                                                                <img src="<?php echo $uri . $workshop['image']; ?>" class="rounded-circle me-3" width="50" height="50" alt="Trainer">
                                                            <?php endif; ?>
                                                            <div class="flex-grow-1">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <h5 class="mb-1"><?php echo $workshop['name']; ?></h5>
                                                                    <div>
                                                                        <?php if ($workshop['rlink']): ?>
                                                                            <span class="badge bg-success">
                                                                                <i class="ti ti-video me-1"></i> Recording
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <p class="text-muted mb-0"><?php echo $workshop['trainer_name']; ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <h6 class="mb-1">Start Date</h6>
                                                                <p class="text-muted mb-0">
                                                                    <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1">Duration</h6>
                                                                <p class="text-muted mb-0"><?php echo $workshop['duration']; ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <h6 class="mb-1">Price</h6>
                                                                    <?php if (!$workshop['cut_price']): ?>
                                                                        <p class="mb-0">
                                                                            <span class="text-decoration-line-through text-muted">₹<?php echo number_format($workshop['cut_price'], 2); ?></span>
                                                                            <span class="text-primary ms-2">₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                                        </p>
                                                                    <?php else: ?>
                                                                        <p class="mb-0 text-primary">₹<?php echo number_format($workshop['price'], 2); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <a href="workshop-details.php?id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                                                    <a href="public_workshop_links.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-warning btn-sm ms-1" target="_blank" title="View Public School Enrollment Links">
                                                                        <i class="ti ti-link"></i>
                                                                    </a>
                                                                    <?php if ($workshop['rlink']): ?>
                                                                        <a href="<?php echo $workshop['rlink']; ?>" class="btn btn-sm btn-success ms-1" target="_blank">
                                                                            <i class="ti ti-video"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <div class="mt-2">
                                                                        <a href="https://ipnacademy.in/feedback.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Feedback">
                                                                            <i class="ti ti-message-circle"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/feedback_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Feedback Report">
                                                                            <i class="ti ti-report"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="Assessment">
                                                                            <i class="ti ti-clipboard-check"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_assessment_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Assessment Report">
                                                                            <i class="ti ti-file-report"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info me-1" target="_blank" title="MCQ">
                                                                            <i class="ti ti-clipboard-check"></i>
                                                                        </a>
                                                                        <a href="https://ipnacademy.in/workshop_mcq_report.php?workshop_id=<?php echo $workshop['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="MCQ Report">
                                                                            <i class="ti ti-file-report"></i>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                        $isFirst = false;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->
        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

</body>

</html>