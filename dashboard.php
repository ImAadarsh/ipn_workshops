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

// Get analytics data for dashboard
$analytics = [];

// Get total users
$total_users_sql = "SELECT COUNT(*) as total FROM users";
$result = mysqli_query($conn, $total_users_sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $analytics['total_users'] = $row['total'];
}

// Get monthly users for the last 12 months
$monthly_users_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$result = mysqli_query($conn, $monthly_users_sql);
$analytics['monthly_users'] = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $analytics['monthly_users'][] = $row;
    }
}

// Get today's users
$today_users_sql = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
$result = mysqli_query($conn, $today_users_sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $analytics['today_users'] = $row['count'];
}

// Get this week's users
$week_users_sql = "SELECT COUNT(*) as count FROM users WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
$result = mysqli_query($conn, $week_users_sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $analytics['week_users'] = $row['count'];
}

// Get this month's users
$month_users_sql = "SELECT COUNT(*) as count FROM users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
$result = mysqli_query($conn, $month_users_sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $analytics['month_users'] = $row['count'];
}

// Get this year's users
$year_users_sql = "SELECT COUNT(*) as count FROM users WHERE YEAR(created_at) = YEAR(CURDATE())";
$result = mysqli_query($conn, $year_users_sql);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $analytics['year_users'] = $row['count'];
}

// Featured upcoming workshop stats
$featuredStats = [
    'b2b' => 0,
    'b2c' => 0,
    'b2c2b' => 0,
    'platform_enrolled' => 0,
    'instamojo_payments' => 0,
    'total_users' => 0,
    'mail_sent' => 0
];
if (!empty($upcomingWorkshops)) {
    $wid = $upcomingWorkshops[0]['id'];
    
    // Get all payments for the workshop
    $sql = "SELECT p.school_id, p.mail_send, s.b2c2b 
            FROM payments p 
            LEFT JOIN schools s ON p.school_id = s.id 
            WHERE p.workshop_id = $wid AND p.payment_status = 1";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Original B2B/B2C logic
            if (!empty($row['school_id'])) {
                $featuredStats['b2b']++;
                // B2C2B logic: count B2B users whose school has b2c2b = 1
                if ($row['b2c2b'] == 1) {
                    $featuredStats['b2c2b']++;
                }
            } else {
                $featuredStats['b2c']++;
            }
            if ($row['mail_send'] == 1) {
                $featuredStats['mail_sent']++;
            }
        }
    }
    
    // Get B2C users with valid platform enrollments (excluding certain payment types and Instamojo)
    $sql = "SELECT COUNT(DISTINCT p.user_id) as platform_count 
            FROM payments p 
            WHERE p.workshop_id = $wid 
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
        $featuredStats['platform_enrolled'] = $row['platform_count'];
    }
    
    // Get Instamojo payments count
    $sql = "SELECT COUNT(DISTINCT p.user_id) as instamojo_count 
            FROM payments p 
            WHERE p.workshop_id = $wid 
            AND p.payment_status = 1 
            AND p.school_id IS NULL 
            AND p.instamojo_upload = 1";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $featuredStats['instamojo_payments'] = $row['instamojo_count'];
    }
    
    // Calculate total users (B2B + B2C)
    $featuredStats['total_users'] = $featuredStats['b2b'] + $featuredStats['b2c'];
    
    // Calculate B2B Enrollment (excluding B2C2B users)
    $featuredStats['b2b_enrollment'] = $featuredStats['b2b'] - $featuredStats['b2c2b'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Custom CSS for enhanced statistics design -->
    <style>
        .stat-card {
            transition: all 0.3s ease;
            min-height: 80px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important;
        }
        .stat-number {
            line-height: 1.2;
        }
        .stat-label {
            margin-top: 4px;
        }
        @media (max-width: 768px) {
            .stat-card {
                min-height: 70px;
            }
            .stat-number {
                font-size: 1.5rem !important;
            }
        }
        
        /* Gradient Button */
        .btn-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-gradient-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            color: white;
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

                <!-- User Analytics Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">📊 User Analytics Overview</h4>
                                <a href="user_analytics.php" class="btn btn-info">
                                    <i class="ti ti-chart-line me-1"></i>View Full Analytics
                                </a>
                            </div>
                            <div class="card-body">
                                <!-- Quick Stats Cards -->
                                <div class="row g-3 mb-4">
                                    <div class="col-6 col-md-3">
                                        <div class="stat-card bg-primary-subtle border border-primary rounded-3 p-3 text-center">
                                            <div class="stat-number text-primary fw-bold fs-4"><?php echo number_format($analytics['today_users']); ?></div>
                                            <div class="stat-label text-muted small">Today</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="stat-card bg-success-subtle border border-success rounded-3 p-3 text-center">
                                            <div class="stat-number text-success fw-bold fs-4"><?php echo number_format($analytics['week_users']); ?></div>
                                            <div class="stat-label text-muted small">This Week</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="stat-card bg-info-subtle border border-info rounded-3 p-3 text-center">
                                            <div class="stat-number text-info fw-bold fs-4"><?php echo number_format($analytics['month_users']); ?></div>
                                            <div class="stat-label text-muted small">This Month</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="stat-card bg-warning-subtle border border-warning rounded-3 p-3 text-center">
                                            <div class="stat-number text-warning fw-bold fs-4"><?php echo number_format($analytics['total_users']); ?></div>
                                            <div class="stat-label text-muted small">Total Users</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- User Registration Trends Chart -->
                                <div class="row">
                                    <div class="col-12">
                                        <h5 class="mb-3">📈 User Registration Trends (Last 12 Months)</h5>
                                        <div style="height: 400px; position: relative;">
                                            <canvas id="userTrendsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Navigation Button -->
                                <div class="row mt-4">
                                    <div class="col-12 text-center">
                                        <a href="user_analytics.php" class="btn btn-lg btn-gradient-primary">
                                            <i class="ti ti-chart-dots me-2"></i>
                                            Need More Smart Analytics? Visit Full Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Workshops Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Upcoming Workshops</h4>
                                <a href="workshops.php" class="btn btn-primary">
                                    <i class="ti ti-arrow-right me-1"></i>View All
                                </a>
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
                                                                            <div class="stat-card bg-primary-subtle border border-primary rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-primary fw-bold fs-4"><?php echo $featuredStats['b2b_enrollment']; ?></div>
                                                                                <div class="stat-label text-muted small">B2B Users</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3">
                                                                            <div class="stat-card bg-success-subtle border border-success rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-success fw-bold fs-4"><?php echo $featuredStats['b2c']; ?></div>
                                                                                <div class="stat-label text-muted small">B2C Users</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3">
                                                                            <div class="stat-card bg-info-subtle border border-info rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-info fw-bold fs-4"><?php echo $featuredStats['b2c2b']; ?></div>
                                                                                <div class="stat-label text-muted small">B2C2B Users</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3">
                                                                            <div class="stat-card bg-warning-subtle border border-warning rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-warning fw-bold fs-4"><?php echo $featuredStats['platform_enrolled']; ?></div>
                                                                                <div class="stat-label text-muted small">Platform</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3">
                                                                            <div class="stat-card bg-danger-subtle border border-danger rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-danger fw-bold fs-4"><?php echo $featuredStats['instamojo_payments']; ?></div>
                                                                                <div class="stat-label text-muted small">Instamojo</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3">
                                                                            <div class="stat-card bg-dark-subtle border border-dark rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-dark fw-bold fs-4"><?php echo $featuredStats['total_users']; ?></div>
                                                                                <div class="stat-label text-muted small">Total Users</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6 col-md-3">
                                                                            <div class="stat-card bg-secondary-subtle border border-secondary rounded-3 p-3 text-center">
                                                                                <div class="stat-number text-secondary fw-bold fs-4"><?php echo $featuredStats['mail_sent']; ?></div>
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
                                                                                <strong>Note:</strong> B2C Users (<?php echo $featuredStats['b2c']; ?>) includes Platform Enrolled (<?php echo $featuredStats['platform_enrolled']; ?>) + Instamojo (<?php echo $featuredStats['instamojo_payments']; ?>). 
                                                                                <br>Total calculation: B2B (<?php echo $featuredStats['b2b'] - $featuredStats['b2c2b']; ?>) + B2C (<?php echo $featuredStats['b2c']; ?>) + B2C2B (<?php echo $featuredStats['b2c2b']; ?>) = <?php echo $featuredStats['total_users']; ?> users.
                                                                            </div>
                                                                        </div>
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
                                <a href="workshops.php?type=completed" class="btn btn-primary">
                                    <i class="ti ti-arrow-right me-1"></i>View All
                                </a>
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
                                                            <div class="col-md-12">
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

    <!-- Chart.js Script -->
    <script>
        // User Registration Trends Chart
        const monthlyData = <?php echo json_encode($analytics['monthly_users']); ?>;
        const monthlyLabels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const monthlyCounts = monthlyData.map(item => parseInt(item.count));

        // Calculate cumulative totals
        let cumulativeTotal = 0;
        const cumulativeData = monthlyCounts.map(count => {
            cumulativeTotal += count;
            return cumulativeTotal;
        });

        // Calculate moving averages (3-month)
        const movingAverageData = [];
        for (let i = 0; i < monthlyCounts.length; i++) {
            if (i < 2) {
                movingAverageData.push(null);
            } else {
                const sum = monthlyCounts[i-2] + monthlyCounts[i-1] + monthlyCounts[i];
                const avg = sum / 3;
                movingAverageData.push(Math.round(avg * 100) / 100);
            }
        }

        // Calculate growth rate (month-over-month percentage)
        const growthRateData = [];
        for (let i = 0; i < monthlyCounts.length; i++) {
            if (i === 0) {
                growthRateData.push(null);
            } else {
                const prevCount = monthlyCounts[i-1];
                const currentCount = monthlyCounts[i];
                if (prevCount > 0) {
                    const growthRate = ((currentCount - prevCount) / prevCount) * 100;
                    growthRateData.push(Math.round(growthRate * 100) / 100);
                } else {
                    growthRateData.push(null);
                }
            }
        }

        new Chart(document.getElementById('userTrendsChart'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Monthly Registrations',
                        data: monthlyCounts,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Cumulative Total Users',
                        data: cumulativeData,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: '3-Month Moving Average',
                        data: movingAverageData,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        borderDash: [5, 5],
                        yAxisID: 'y',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: 'Growth Rate (%)',
                        data: growthRateData,
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        borderDash: [3, 3],
                        yAxisID: 'y2',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#3498db',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Monthly Registrations',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cumulative Total Users',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        beginAtZero: true
                    },
                    y2: {
                        type: 'linear',
                        display: false, // Initially hidden, can be toggled
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Growth Rate (%)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    </script>

</body>

</html>