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

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    header("Location: user_management.php");
    exit();
}

// Get user details
$sql = "SELECT u.*, s.name as school_name 
        FROM users u 
        LEFT JOIN schools s ON u.school_id = s.id 
        WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$user = mysqli_fetch_assoc($result)) {
    header("Location: user_management.php");
    exit();
}

// Get user's workshop enrollments
$enrollments_sql = "SELECT p.*, w.name as workshop_name, w.start_date, w.trainer_name
                    FROM payments p 
                    INNER JOIN workshops w ON p.workshop_id = w.id 
                    WHERE p.user_id = ? AND p.payment_status = 1 
                    ORDER BY w.start_date DESC";
$enrollments_stmt = mysqli_prepare($conn, $enrollments_sql);
mysqli_stmt_bind_param($enrollments_stmt, "i", $user_id);
mysqli_stmt_execute($enrollments_stmt);
$enrollments_result = mysqli_stmt_get_result($enrollments_stmt);

// Get user's feedback
$feedback_sql = "SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC";
$feedback_stmt = mysqli_prepare($conn, $feedback_sql);
mysqli_stmt_bind_param($feedback_stmt, "i", $user_id);
mysqli_stmt_execute($feedback_stmt);
$feedback_result = mysqli_stmt_get_result($feedback_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>User Details | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .detail-card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important;
        }
        .issue-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
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
                                <h4 class="fs-18 text-uppercase fw-bold m-0">User Details</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="user_management.php" class="btn btn-primary me-2">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Users
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="ti ti-home me-1"></i> Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-gradient-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-user me-2"></i>User Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h3 class="text-primary mb-3"><?php echo htmlspecialchars($user['name']); ?></h3>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="ti ti-mail text-muted me-2"></i>
                                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="ti ti-phone text-muted me-2"></i>
                                                    <span><?php echo htmlspecialchars($user['mobile']); ?></span>
                                                </div>
                                                <?php if ($user['designation']): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="ti ti-briefcase text-muted me-2"></i>
                                                    <span><?php echo htmlspecialchars($user['designation']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($user['institute_name']): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="ti ti-building text-muted me-2"></i>
                                                    <span><?php echo htmlspecialchars($user['institute_name']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($user['city']): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="ti ti-map-pin text-muted me-2"></i>
                                                    <span><?php echo htmlspecialchars($user['city']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($user['school_name']): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="ti ti-school text-muted me-2"></i>
                                                    <span><?php echo htmlspecialchars($user['school_name']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-end">
                                            <p class="text-muted mb-1">User ID: <?php echo $user['id']; ?></p>
                                            <p class="text-muted mb-1">Joined: <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                                            <p class="text-muted mb-0">Last Updated: <?php echo date('d M Y', strtotime($user['updated_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card bg-primary-subtle border border-primary rounded-3 p-3 text-center">
                            <div class="stat-number text-primary fw-bold fs-4">
                                <?php echo mysqli_num_rows($enrollments_result); ?>
                            </div>
                            <div class="stat-label text-muted small">Workshops Enrolled</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success-subtle border border-success rounded-3 p-3 text-center">
                            <div class="stat-number text-success fw-bold fs-4">
                                <?php echo mysqli_num_rows($feedback_result); ?>
                            </div>
                            <div class="stat-label text-muted small">Feedback Given</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info-subtle border border-info rounded-3 p-3 text-center">
                            <div class="stat-number text-info fw-bold fs-4">
                                <?php echo $user['tlc_2025'] ? 'Yes' : 'No'; ?>
                            </div>
                            <div class="stat-label text-muted small">TLC 2025</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning-subtle border border-warning rounded-3 p-3 text-center">
                            <div class="stat-number text-warning fw-bold fs-4">
                                <?php echo $user['email_verified_at'] ? 'Yes' : 'No'; ?>
                            </div>
                            <div class="stat-label text-muted small">Email Verified</div>
                        </div>
                    </div>
                </div>

                <!-- Data Quality Issues -->
                <?php
                $issues = [];
                if (preg_match('/^0/', $user['mobile'])) {
                    $issues[] = ['type' => 'phone_zero', 'text' => 'Phone starts with 0', 'class' => 'danger'];
                }
                if (empty($user['mobile'])) {
                    $issues[] = ['type' => 'phone_empty', 'text' => 'Empty phone number', 'class' => 'warning'];
                }
                if (strlen($user['mobile']) < 10) {
                    $issues[] = ['type' => 'phone_short', 'text' => 'Phone too short', 'class' => 'info'];
                }
                if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    $issues[] = ['type' => 'email_invalid', 'text' => 'Invalid email format', 'class' => 'secondary'];
                }
                if (empty($user['email'])) {
                    $issues[] = ['type' => 'email_empty', 'text' => 'Empty email', 'class' => 'primary'];
                }
                if (empty($user['name'])) {
                    $issues[] = ['type' => 'name_empty', 'text' => 'Empty name', 'class' => 'success'];
                }
                if (!empty($issues)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card detail-card border-warning">
                            <div class="card-header bg-warning text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-alert-triangle me-2"></i>Data Quality Issues
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($issues as $issue): ?>
                                        <span class="badge bg-<?php echo $issue['class']; ?> issue-badge">
                                            <?php echo $issue['text']; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Workshop Enrollments -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-gradient-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-calendar-event me-2"></i>Workshop Enrollments
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($enrollments_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Workshop</th>
                                                    <th>Trainer</th>
                                                    <th>Date</th>
                                                    <th>Payment ID</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($enrollment = mysqli_fetch_assoc($enrollments_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($enrollment['workshop_name']); ?></strong>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($enrollment['trainer_name']); ?></td>
                                                        <td><?php echo date('d M Y', strtotime($enrollment['start_date'])); ?></td>
                                                        <td><code><?php echo htmlspecialchars($enrollment['payment_id']); ?></code></td>
                                                        <td>
                                                            <?php if ($enrollment['is_attended']): ?>
                                                                <span class="badge bg-success">Attended</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Enrolled</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="ti ti-calendar-off text-muted" style="font-size: 3rem;"></i>
                                        <h6 class="text-muted mt-3">No workshop enrollments</h6>
                                        <p class="text-muted mb-0">This user hasn't enrolled in any workshops yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card detail-card">
                            <div class="card-header bg-gradient-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="ti ti-message-circle me-2"></i>Feedback
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($feedback_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Workshop</th>
                                                    <th>Rating</th>
                                                    <th>Comment</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($feedback = mysqli_fetch_assoc($feedback_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($feedback['workshop_name']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="ti ti-star<?php echo $i <= $feedback['rating'] ? '-filled text-warning' : ' text-muted'; ?>"></i>
                                                            <?php endfor; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($feedback['comment']); ?></td>
                                                        <td><?php echo date('d M Y', strtotime($feedback['created_at'])); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="ti ti-message-off text-muted" style="font-size: 3rem;"></i>
                                        <h6 class="text-muted mt-3">No feedback given</h6>
                                        <p class="text-muted mb-0">This user hasn't provided any feedback yet.</p>
                                    </div>
                                <?php endif; ?>
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

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 