<?php
include 'config/show_errors.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

$workshop_id = isset($_GET['workshop_id']) ? (int)$_GET['workshop_id'] : 0;
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;

// Get school info
$school = null;
if ($school_id) {
    $school_sql = "SELECT name FROM schools WHERE id = $school_id";
    $school_result = mysqli_query($conn, $school_sql);
    $school = mysqli_fetch_assoc($school_result);
}

// Get workshop info
$workshop = null;
if ($workshop_id) {
    $workshop_sql = "SELECT name FROM workshops WHERE id = $workshop_id";
    $workshop_result = mysqli_query($conn, $workshop_sql);
    $workshop = mysqli_fetch_assoc($workshop_result);
}

// Export as CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv' && $workshop_id && $school_id) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="school_teachers_' . $school_id . '_workshop_' . $workshop_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Mobile', 'Attended', 'Duration (min)']);
    $sql = "SELECT u.id, u.name, u.email, u.mobile, p.is_attended, p.attended_duration
            FROM payments p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.workshop_id = $workshop_id AND p.payment_status = 1
            AND (p.school_id = $school_id OR u.school_id = $school_id)
            GROUP BY u.id
            ORDER BY u.name";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['name'],
            $row['email'],
            $row['mobile'],
            $row['is_attended'] ? 'Yes' : 'No',
            $row['attended_duration']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch teachers and attendance stats
$teachers = [];
$attendance_stats = [
    'total_registered' => 0,
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
if ($workshop_id && $school_id) {
    // Teachers list (unique by user)
    $sql = "SELECT u.id, u.name, u.email, u.mobile, p.is_attended, p.attended_duration
            FROM payments p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.workshop_id = $workshop_id AND p.payment_status = 1
            AND (p.school_id = $school_id OR u.school_id = $school_id)
            GROUP BY u.id
            ORDER BY u.name";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
    // Attendance stats
    $stats_sql = "SELECT 
        COUNT(DISTINCT u.id) as total_registered,
        SUM(CASE WHEN p.is_attended = 1 THEN 1 ELSE 0 END) as total_attended,
        AVG(CASE WHEN p.is_attended = 1 THEN p.attended_duration ELSE NULL END) as avg_duration,
        SUM(CASE WHEN p.is_attended = 1 AND p.attended_duration > 0 AND p.attended_duration <= 15 THEN 1 ELSE 0 END) as duration_0_15,
        SUM(CASE WHEN p.is_attended = 1 AND p.attended_duration > 15 AND p.attended_duration <= 30 THEN 1 ELSE 0 END) as duration_15_30,
        SUM(CASE WHEN p.is_attended = 1 AND p.attended_duration > 30 AND p.attended_duration <= 60 THEN 1 ELSE 0 END) as duration_30_60,
        SUM(CASE WHEN p.is_attended = 1 AND p.attended_duration > 60 THEN 1 ELSE 0 END) as duration_60_plus
        FROM payments p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.workshop_id = $workshop_id AND p.payment_status = 1
        AND (p.school_id = $school_id OR u.school_id = $school_id)";
    $stats_result = mysqli_query($conn, $stats_sql);
    if ($stats_result) {
        $stats = mysqli_fetch_assoc($stats_result);
        $attendance_stats['total_registered'] = (int)$stats['total_registered'];
        $attendance_stats['total_attended'] = (int)$stats['total_attended'];
        $attendance_stats['avg_duration'] = $stats['avg_duration'] !== null ? round($stats['avg_duration'], 1) : 0;
        $attendance_stats['duration_stats']['0-15'] = (int)$stats['duration_0_15'];
        $attendance_stats['duration_stats']['15-30'] = (int)$stats['duration_15_30'];
        $attendance_stats['duration_stats']['30-60'] = (int)$stats['duration_30_60'];
        $attendance_stats['duration_stats']['60+'] = (int)$stats['duration_60_plus'];
        if ($attendance_stats['total_registered'] > 0) {
            $attendance_stats['completion_rate'] = round(($attendance_stats['total_attended'] / $attendance_stats['total_registered']) * 100, 1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>School Teachers | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidenav.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    <div class="page-content">
        <div class="page-container">
            <div class="row mb-4">
                <div class="col-12">
                    <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-outline-secondary mb-3">
                        <i class="ti ti-arrow-left me-1"></i> Back to Workshop Details
                    </a>
                    <h3 class="mb-3">Teachers from <span class="text-primary"><?php echo htmlspecialchars($school['name']); ?></span> in <span class="text-success"><?php echo htmlspecialchars($workshop['name']); ?></span></h3>
                    <a href="?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&export=csv" class="btn btn-success mb-3">Export CSV</a>
                </div>
            </div>
            <!-- Attendance Panel for School -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ti ti-chart-bar me-1"></i> Attendance Statistics (<?php echo htmlspecialchars($school['name']); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Total Registered</h6>
                                            <h2 class="mb-0"><?php echo $attendance_stats['total_registered']; ?></h2>
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
            <!-- End Attendance Panel for School -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Teacher List</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Mobile</th>
                                            <th>Attended</th>
                                            <th>Duration (min)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($teachers) == 0): ?>
                                        <tr><td colspan="5" class="text-center">No teachers found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($teachers as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                                <td><?php echo $row['is_attended'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>'; ?></td>
                                                <td><?php echo (int)$row['attended_duration']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/theme_settings.php'; ?>
</div>
<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
</body>
</html>
