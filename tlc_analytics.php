<?php
include 'config/show_errors.php';
session_start();

$special_access_key = '5678y3uhsc76270e9yuwqdjq9q72u1ejqiw';
$is_logged_in = isset($_SESSION['user_id']);
$is_guest_access = !$is_logged_in && isset($_GET['uvx']) && $_GET['uvx'] === $special_access_key;

if (!$is_logged_in && !$is_guest_access) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

// Get comprehensive TLC statistics
$stats = [];

// 1. Total TLC 2025 registrations
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE tlc_2025 = 1");
$stats['total_registrations'] = mysqli_fetch_assoc($result)['count'];

// 2. New users (is_tlc_new = 1)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE tlc_2025 = 1 AND is_tlc_new = 1");
$stats['new_users'] = mysqli_fetch_assoc($result)['count'];

// 3. Old users (is_tlc_new = 0 or null)
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE tlc_2025 = 1 AND (is_tlc_new = 0 OR is_tlc_new IS NULL)");
$stats['old_users'] = mysqli_fetch_assoc($result)['count'];

// 4. Users who attended at least one day
$result = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as count FROM tlc_join_durations");
$stats['attended_any_day'] = mysqli_fetch_assoc($result)['count'];

// 5. Users who attended both days
$result = mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT user_id 
        FROM tlc_join_durations 
        GROUP BY user_id 
        HAVING COUNT(DISTINCT day) = 2
    ) as both_days
");
$stats['attended_both_days'] = mysqli_fetch_assoc($result)['count'];

// 6. New users who attended both days
$result = mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT t.user_id 
        FROM tlc_join_durations t
        JOIN users u ON t.user_id = u.id
        WHERE u.is_tlc_new = 1
        GROUP BY t.user_id 
        HAVING COUNT(DISTINCT t.day) = 2
    ) as new_both_days
");
$stats['new_attended_both_days'] = mysqli_fetch_assoc($result)['count'];

// 7. Old users who attended both days
$result = mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT t.user_id 
        FROM tlc_join_durations t
        JOIN users u ON t.user_id = u.id
        WHERE (u.is_tlc_new = 0 OR u.is_tlc_new IS NULL)
        GROUP BY t.user_id 
        HAVING COUNT(DISTINCT t.day) = 2
    ) as old_both_days
");
$stats['old_attended_both_days'] = mysqli_fetch_assoc($result)['count'];

// 8. Duration analysis - Total duration per user
$result = mysqli_query($conn, "
    SELECT 
        t.user_id,
        u.is_tlc_new,
        SUM(t.total_duration) as total_duration
    FROM tlc_join_durations t
    JOIN users u ON t.user_id = u.id
    GROUP BY t.user_id, u.is_tlc_new
");
$duration_stats = [];
$stats['over_100_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_200_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_300_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_324_min'] = ['new' => 0, 'old' => 0, 'total' => 0];

while ($row = mysqli_fetch_assoc($result)) {
    $duration = (int)$row['total_duration'];
    $is_new = $row['is_tlc_new'] == 1;
    
    if ($duration >= 100) {
        $stats['over_100_min']['total']++;
        if ($is_new) $stats['over_100_min']['new']++;
        else $stats['over_100_min']['old']++;
    }
    if ($duration >= 200) {
        $stats['over_200_min']['total']++;
        if ($is_new) $stats['over_200_min']['new']++;
        else $stats['over_200_min']['old']++;
    }
    if ($duration >= 300) {
        $stats['over_300_min']['total']++;
        if ($is_new) $stats['over_300_min']['new']++;
        else $stats['over_300_min']['old']++;
    }
    if ($duration >= 324) {
        $stats['over_324_min']['total']++;
        if ($is_new) $stats['over_324_min']['new']++;
        else $stats['over_324_min']['old']++;
    }
}

// 9. Day-wise attendance
$result = mysqli_query($conn, "SELECT day, COUNT(DISTINCT user_id) as count FROM tlc_join_durations GROUP BY day ORDER BY day");
$stats['day_wise'] = [];
while ($row = mysqli_fetch_assoc($result)) {
    $stats['day_wise'][$row['day']] = $row['count'];
}

// 10. Grace grant statistics
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tlc_join_durations WHERE grace_grant = 1");
$stats['grace_granted'] = mysqli_fetch_assoc($result)['count'];

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tlc_join_durations WHERE grace_grant = 0");
$stats['grace_not_granted'] = mysqli_fetch_assoc($result)['count'];

// 11. Average duration by user type
$result = mysqli_query($conn, "
    SELECT 
        u.is_tlc_new,
        AVG(total_duration) as avg_duration,
        COUNT(DISTINCT t.user_id) as user_count
    FROM tlc_join_durations t
    JOIN users u ON t.user_id = u.id
    GROUP BY u.is_tlc_new
");
$stats['avg_duration'] = ['new' => 0, 'old' => 0];
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['is_tlc_new'] == 1) {
        $stats['avg_duration']['new'] = round($row['avg_duration'], 1);
    } else {
        $stats['avg_duration']['old'] = round($row['avg_duration'], 1);
    }
}

// 12. Registration vs Attendance Rate
$stats['attendance_rate'] = $stats['total_registrations'] > 0 ? round(($stats['attended_any_day'] / $stats['total_registrations']) * 100, 1) : 0;
$stats['both_days_rate'] = $stats['total_registrations'] > 0 ? round(($stats['attended_both_days'] / $stats['total_registrations']) * 100, 1) : 0;

// 13. New vs Old user engagement
$stats['new_engagement_rate'] = $stats['new_users'] > 0 ? round(($stats['new_attended_both_days'] / $stats['new_users']) * 100, 1) : 0;
$stats['old_engagement_rate'] = $stats['old_users'] > 0 ? round(($stats['old_attended_both_days'] / $stats['old_users']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>TLC Analytics | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <?php if ($is_guest_access): ?>
    <style>
        .page-content { margin-left: 0 !important; padding: 20px; }
        .wrapper { padding-top: 0 !important; }
    </style>
    <?php endif; ?>
</head>
<body>
<div class="wrapper">
    <?php if ($is_logged_in): ?>
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>
    <?php endif; ?>
    <div class="page-content">
        <div class="page-container">
            <h3 class="mb-4">TLC 2025 Analytics Dashboard</h3>
            
            <!-- Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Registrations</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['total_registrations']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Attended Any Day</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['attended_any_day']); ?></h2>
                            <small><?php echo $stats['attendance_rate']; ?>% of registrations</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Attended Both Days</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['attended_both_days']); ?></h2>
                            <small><?php echo $stats['both_days_rate']; ?>% of registrations</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Grace Granted</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['grace_granted']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Type Analysis -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">User Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo number_format($stats['new_users']); ?></h4>
                                    <p class="text-muted">New Users</p>
                                    <small class="text-success"><?php echo $stats['new_engagement_rate']; ?>% attended both days</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo number_format($stats['old_users']); ?></h4>
                                    <p class="text-muted">Existing Users</p>
                                    <small class="text-success"><?php echo $stats['old_engagement_rate']; ?>% attended both days</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Both Days Attendance by User Type</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo number_format($stats['new_attended_both_days']); ?></h4>
                                    <p class="text-muted">New Users</p>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo number_format($stats['old_attended_both_days']); ?></h4>
                                    <p class="text-muted">Existing Users</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duration Analysis -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Duration Analysis (Total Duration per User)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Duration Threshold</th>
                                            <th>New Users</th>
                                            <th>Existing Users</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>≥ 100 minutes</td>
                                            <td><?php echo number_format($stats['over_100_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_100_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_100_min']['total']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 200 minutes</td>
                                            <td><?php echo number_format($stats['over_200_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_200_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_200_min']['total']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 300 minutes</td>
                                            <td><?php echo number_format($stats['over_300_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_300_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_300_min']['total']); ?></strong></td>
                                        </tr>
                                        <tr class="table-success">
                                            <td>≥ 324 minutes (Max)</td>
                                            <td><?php echo number_format($stats['over_324_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_324_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_324_min']['total']); ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day-wise Analysis -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Day-wise Attendance</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($stats['day_wise'] as $day => $count): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Day <?php echo $day; ?></span>
                                <span class="badge bg-primary"><?php echo number_format($count); ?> users</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Average Duration by User Type</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo $stats['avg_duration']['new']; ?> min</h4>
                                    <p class="text-muted">New Users</p>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo $stats['avg_duration']['old']; ?> min</h4>
                                    <p class="text-muted">Existing Users</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grace Grant Analysis -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Grace Grant Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo number_format($stats['grace_granted']); ?></h4>
                                    <p class="text-muted">Grace Granted</p>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-danger"><?php echo number_format($stats['grace_not_granted']); ?></h4>
                                    <p class="text-muted">Grace Not Granted</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Insights -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Key Business Insights</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <strong>Engagement Rate:</strong> <?php echo $stats['attendance_rate']; ?>% of registered users attended at least one day
                                </li>
                                <li class="list-group-item">
                                    <strong>Retention Rate:</strong> <?php echo $stats['both_days_rate']; ?>% of registered users attended both days
                                </li>
                                <li class="list-group-item">
                                    <strong>New User Performance:</strong> <?php echo $stats['new_engagement_rate']; ?>% of new users attended both days
                                </li>
                                <li class="list-group-item">
                                    <strong>Existing User Performance:</strong> <?php echo $stats['old_engagement_rate']; ?>% of existing users attended both days
                                </li>
                                <li class="list-group-item">
                                    <strong>High Engagement:</strong> <?php echo number_format($stats['over_324_min']['total']); ?> users achieved maximum duration (324+ minutes)
                                </li>
                                <li class="list-group-item">
                                    <strong>Support Needed:</strong> <?php echo number_format($stats['grace_granted']); ?> users required grace grants
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php if ($is_logged_in): ?>
        <?php include 'includes/theme_settings.php'; ?>
    <?php endif; ?>
</div>
<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
</body>
</html> 