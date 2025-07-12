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

// 8. Users who attended only one day
$result = mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT user_id 
        FROM tlc_join_durations 
        GROUP BY user_id 
        HAVING COUNT(DISTINCT day) = 1
    ) as one_day
");
$stats['attended_one_day'] = mysqli_fetch_assoc($result)['count'];

// 9. Duration analysis - Total duration per user
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
$stats['under_100_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_100_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_200_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_300_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_324_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_400_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_500_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_600_min'] = ['new' => 0, 'old' => 0, 'total' => 0];
$stats['over_648_min'] = ['new' => 0, 'old' => 0, 'total' => 0];

while ($row = mysqli_fetch_assoc($result)) {
    $duration = (int)$row['total_duration'];
    $is_new = $row['is_tlc_new'] == 1;
    
    if ($duration < 100) {
        $stats['under_100_min']['total']++;
        if ($is_new) $stats['under_100_min']['new']++;
        else $stats['under_100_min']['old']++;
    }
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
    if ($duration >= 400) {
        $stats['over_400_min']['total']++;
        if ($is_new) $stats['over_400_min']['new']++;
        else $stats['over_400_min']['old']++;
    }
    if ($duration >= 500) {
        $stats['over_500_min']['total']++;
        if ($is_new) $stats['over_500_min']['new']++;
        else $stats['over_500_min']['old']++;
    }
    if ($duration >= 600) {
        $stats['over_600_min']['total']++;
        if ($is_new) $stats['over_600_min']['new']++;
        else $stats['over_600_min']['old']++;
    }
    if ($duration >= 648) {
        $stats['over_648_min']['total']++;
        if ($is_new) $stats['over_648_min']['new']++;
        else $stats['over_648_min']['old']++;
    }
}

// 10. Day-wise attendance
$result = mysqli_query($conn, "SELECT day, COUNT(DISTINCT user_id) as count FROM tlc_join_durations GROUP BY day ORDER BY day");
$stats['day_wise'] = [];
while ($row = mysqli_fetch_assoc($result)) {
    $stats['day_wise'][$row['day']] = $row['count'];
}

// 11. Grace grant statistics
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tlc_join_durations WHERE grace_grant = 1");
$stats['grace_granted'] = mysqli_fetch_assoc($result)['count'];

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tlc_join_durations WHERE grace_grant = 0");
$stats['grace_not_granted'] = mysqli_fetch_assoc($result)['count'];

// 12. Average duration by user type
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

// 13. Geographic analysis
$result = mysqli_query($conn, "
    SELECT 
        u.city,
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT t.user_id) as attended_users
    FROM users u
    LEFT JOIN tlc_join_durations t ON u.id = t.user_id
    WHERE u.tlc_2025 = 1
    GROUP BY u.city
    HAVING total_users >= 5
    ORDER BY total_users DESC
    LIMIT 10
");
$stats['top_cities'] = [];
while ($row = mysqli_fetch_assoc($result)) {
    $stats['top_cities'][] = $row;
}

// 14. Institute analysis
$result = mysqli_query($conn, "
    SELECT 
        u.institute_name,
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT t.user_id) as attended_users
    FROM users u
    LEFT JOIN tlc_join_durations t ON u.id = t.user_id
    WHERE u.tlc_2025 = 1 AND u.institute_name IS NOT NULL AND u.institute_name != ''
    GROUP BY u.institute_name
    HAVING total_users >= 3
    ORDER BY total_users DESC
    LIMIT 10
");
$stats['top_institutes'] = [];
while ($row = mysqli_fetch_assoc($result)) {
    $stats['top_institutes'][] = $row;
}

// 15. Registration vs Attendance Rate
$stats['attendance_rate'] = $stats['total_registrations'] > 0 ? round(($stats['attended_any_day'] / $stats['total_registrations']) * 100, 1) : 0;
$stats['both_days_rate'] = $stats['total_registrations'] > 0 ? round(($stats['attended_both_days'] / $stats['total_registrations']) * 100, 1) : 0;

// 16. New vs Old user engagement
$stats['new_engagement_rate'] = $stats['new_users'] > 0 ? round(($stats['new_attended_both_days'] / $stats['new_users']) * 100, 1) : 0;
$stats['old_engagement_rate'] = $stats['old_users'] > 0 ? round(($stats['old_attended_both_days'] / $stats['old_users']) * 100, 1) : 0;

// 17. Users with reasons (grace applications)
$result = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as count FROM tlc_join_durations WHERE reason IS NOT NULL AND reason != ''");
$stats['users_with_reasons'] = mysqli_fetch_assoc($result)['count'];

// 18. Users without reasons
$stats['users_without_reasons'] = $stats['attended_any_day'] - $stats['users_with_reasons'];

// 19. Peak performance users (648+ minutes - 2 days max)
$result = mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT user_id, SUM(total_duration) as total_duration
        FROM tlc_join_durations
        GROUP BY user_id
        HAVING total_duration >= 648
    ) as peak_users
");
$stats['peak_performance_users'] = mysqli_fetch_assoc($result)['count'];

// 20. Low engagement users (< 100 minutes total)
$result = mysqli_query($conn, "
    SELECT COUNT(*) as count FROM (
        SELECT user_id, SUM(total_duration) as total_duration
        FROM tlc_join_durations
        GROUP BY user_id
        HAVING total_duration < 100
    ) as low_engagement_users
");
$stats['low_engagement_users'] = mysqli_fetch_assoc($result)['count'];

// 21. Registration date analysis
$result = mysqli_query($conn, "
    SELECT 
        DATE(tlc_join_date) as join_date,
        COUNT(*) as registrations
    FROM users 
    WHERE tlc_2025 = 1 AND tlc_join_date IS NOT NULL
    GROUP BY DATE(tlc_join_date)
    ORDER BY join_date DESC
    LIMIT 7
");
$stats['recent_registrations'] = [];
while ($row = mysqli_fetch_assoc($result)) {
    $stats['recent_registrations'][] = $row;
}

// 22. Email sent status
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE tlc_2025 = 1 AND tlc_email_sent = 1");
$stats['email_sent'] = mysqli_fetch_assoc($result)['count'];

$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE tlc_2025 = 1 AND (tlc_email_sent = 0 OR tlc_email_sent IS NULL)");
$stats['email_not_sent'] = mysqli_fetch_assoc($result)['count'];
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
    <style>
        .stat-card {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .drill-down-btn {
            font-size: 0.8em;
            opacity: 0.7;
        }
        .stat-card:hover .drill-down-btn {
            opacity: 1;
        }
    </style>
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
                    <div class="card bg-primary text-white stat-card" onclick="showUsers('total_registrations')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Registrations</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['total_registrations']); ?></h2>
                            <small class="drill-down-btn">Click to view users</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white stat-card" onclick="showUsers('attended_any_day')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Attended Any Day</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['attended_any_day']); ?></h2>
                            <small><?php echo $stats['attendance_rate']; ?>% of registrations</small>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white stat-card" onclick="showUsers('attended_both_days')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Attended Both Days</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['attended_both_days']); ?></h2>
                            <small><?php echo $stats['both_days_rate']; ?>% of registrations</small>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white stat-card" onclick="showUsers('grace_granted')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Grace Granted</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['grace_granted']); ?></h2>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-secondary text-white stat-card" onclick="showUsers('new_attended_both_days')">
                        <div class="card-body text-center">
                            <h6 class="card-title">New Users - Both Days</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['new_attended_both_days']); ?></h2>
                            <small><?php echo $stats['new_engagement_rate']; ?>% of new users</small>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-dark text-white stat-card" onclick="showUsers('old_attended_both_days')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Existing Users - Both Days</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['old_attended_both_days']); ?></h2>
                            <small><?php echo $stats['old_engagement_rate']; ?>% of existing users</small>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white stat-card" onclick="showUsers('peak_performance')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Peak Performance</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['peak_performance_users']); ?></h2>
                            <small>648+ minutes total (2 days max)</small>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light text-dark stat-card" onclick="showUsers('low_engagement')">
                        <div class="card-body text-center">
                            <h6 class="card-title">Low Engagement</h6>
                            <h2 class="mb-0"><?php echo number_format($stats['low_engagement_users']); ?></h2>
                            <small>< 100 minutes total</small>
                            <div class="drill-down-btn">Click to view users</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Type Distribution -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">User Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo number_format($stats['new_users']); ?></h4>
                                    <p class="text-muted">New Users (Total Registrations)</p>
                                    <small class="text-success"><?php echo $stats['new_engagement_rate']; ?>% attended both days</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo number_format($stats['old_users']); ?></h4>
                                    <p class="text-muted">Existing Users (Total Registrations)</p>
                                    <small class="text-success"><?php echo $stats['old_engagement_rate']; ?>% attended both days</small>
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
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>< 100 minutes</td>
                                            <td><?php echo number_format($stats['under_100_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['under_100_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['under_100_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('under_100_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 100 minutes</td>
                                            <td><?php echo number_format($stats['over_100_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_100_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_100_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_100_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 200 minutes</td>
                                            <td><?php echo number_format($stats['over_200_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_200_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_200_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_200_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 300 minutes</td>
                                            <td><?php echo number_format($stats['over_300_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_300_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_300_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_300_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 324 minutes (Day 1 Max)</td>
                                            <td><?php echo number_format($stats['over_324_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_324_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_324_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_324_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 400 minutes</td>
                                            <td><?php echo number_format($stats['over_400_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_400_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_400_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_400_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 500 minutes</td>
                                            <td><?php echo number_format($stats['over_500_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_500_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_500_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_500_min')">View Users</button></td>
                                        </tr>
                                        <tr>
                                            <td>≥ 600 minutes</td>
                                            <td><?php echo number_format($stats['over_600_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_600_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_600_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-outline-primary" onclick="showUsers('over_600_min')">View Users</button></td>
                                        </tr>
                                        <tr class="table-success">
                                            <td>≥ 648 minutes (2 Days Max)</td>
                                            <td><?php echo number_format($stats['over_648_min']['new']); ?></td>
                                            <td><?php echo number_format($stats['over_648_min']['old']); ?></td>
                                            <td><strong><?php echo number_format($stats['over_648_min']['total']); ?></strong></td>
                                            <td><button class="btn btn-sm btn-success" onclick="showUsers('over_648_min')">View Users</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Geographic & Institute Analysis -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Cities (≥5 users)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>City</th>
                                            <th>Total Users</th>
                                            <th>Attended</th>
                                            <th>Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['top_cities'] as $city): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($city['city']); ?></td>
                                            <td><?php echo $city['total_users']; ?></td>
                                            <td><?php echo $city['attended_users']; ?></td>
                                            <td><?php echo round(($city['attended_users'] / $city['total_users']) * 100, 1); ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Top Institutes (≥3 users)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Institute</th>
                                            <th>Total Users</th>
                                            <th>Attended</th>
                                            <th>Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['top_institutes'] as $institute): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($institute['institute_name']); ?></td>
                                            <td><?php echo $institute['total_users']; ?></td>
                                            <td><?php echo $institute['attended_users']; ?></td>
                                            <td><?php echo round(($institute['attended_users'] / $institute['total_users']) * 100, 1); ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
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
                                <div class="col-4">
                                    <h4 class="text-success"><?php echo number_format($stats['grace_granted']); ?></h4>
                                    <p class="text-muted">Grace Granted</p>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-danger"><?php echo number_format($stats['grace_not_granted']); ?></h4>
                                    <p class="text-muted">Grace Not Granted</p>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-info"><?php echo number_format($stats['users_with_reasons']); ?></h4>
                                    <p class="text-muted">Applied for Grace</p>
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
                                <li class="list-group-item">
                                    <strong>One Day Only:</strong> <?php echo number_format($stats['attended_one_day']); ?> users attended only one day
                                </li>
                                <li class="list-group-item">
                                    <strong>Email Campaign:</strong> <?php echo number_format($stats['email_sent']); ?> users received TLC emails
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

<!-- User List Modal -->
<div class="modal fade" id="userListModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userListModalTitle">User List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="userListContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
<script>
function showUsers(type) {
    const modal = new bootstrap.Modal(document.getElementById('userListModal'));
    const title = document.getElementById('userListModalTitle');
    const content = document.getElementById('userListContent');
    
    // Set title based on type
    const titles = {
        'total_registrations': 'All TLC 2025 Registrations',
        'new_users': 'New Users (is_tlc_new = 1)',
        'old_users': 'Existing Users (is_tlc_new = 0)',
        'attended_any_day': 'Users Who Attended Any Day',
        'attended_both_days': 'Users Who Attended Both Days',
        'new_attended_both_days': 'New Users Who Attended Both Days',
        'old_attended_both_days': 'Existing Users Who Attended Both Days',
        'grace_granted': 'Users Granted Grace',
        'peak_performance': 'Peak Performance Users (648+ minutes)',
        'low_engagement': 'Low Engagement Users (< 100 minutes)',
        'under_100_min': 'Users with < 100 minutes total',
        'over_100_min': 'Users with ≥ 100 minutes total',
        'over_200_min': 'Users with ≥ 200 minutes total',
        'over_300_min': 'Users with ≥ 300 minutes total',
        'over_324_min': 'Users with ≥ 324 minutes total',
        'over_400_min': 'Users with ≥ 400 minutes total',
        'over_500_min': 'Users with ≥ 500 minutes total',
        'over_600_min': 'Users with ≥ 600 minutes total',
        'over_648_min': 'Users with ≥ 648 minutes total (2 days max)'
    };
    
    title.textContent = titles[type] || 'User List';
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading users...</p></div>';
    
    modal.show();
    
    // Load user data
    fetch(`tlc_user_list.php?type=${type}&uvx=<?php echo $special_access_key; ?>`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading user data.</div>';
        });
}
</script>
</body>
</html> 