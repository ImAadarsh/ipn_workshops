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

// Get school list for dropdown
$schools = [];
$school_sql = "SELECT id, name FROM schools ORDER BY name";
$school_result = mysqli_query($conn, $school_sql);
while ($row = mysqli_fetch_assoc($school_result)) {
    $schools[] = $row;
}

// Get day list for dropdown
$days = [];
$day_sql = "SELECT DISTINCT day FROM tlc_join_durations ORDER BY day";
$day_result = mysqli_query($conn, $day_sql);
while ($row = mysqli_fetch_assoc($day_result)) {
    $days[] = $row['day'];
}

// Get filters
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$day = isset($_GET['day']) ? (int)$_GET['day'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Export as CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tlc_durations_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Mobile', 'School', 'Duration (min)', 'Day']);
    $where = [];
    if ($day) $where[] = "t.day = $day";
    if ($school_id) $where[] = "u.school_id = $school_id";
    if ($search) {
        $search_esc = mysqli_real_escape_string($conn, $search);
        $where[] = "(t.name LIKE '%$search_esc%' OR t.email LIKE '%$search_esc%' OR u.mobile LIKE '%$search_esc%' OR u.institute_name LIKE '%$search_esc%')";
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT t.*, u.mobile, u.institute_name
            FROM tlc_join_durations t
            LEFT JOIN users u ON t.user_id = u.id
            $where_sql
            ORDER BY t.total_duration DESC";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['name'],
            $row['email'],
            $row['mobile'],
            isset($row['institute_name']) ? $row['institute_name'] : 'N/A',
            $row['total_duration'],
            $row['day']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch TLC durations data
$where = [];
if ($day) $where[] = "t.day = $day";
if ($school_id) $where[] = "u.school_id = $school_id";
if ($search) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where[] = "(t.name LIKE '%$search_esc%' OR t.email LIKE '%$search_esc%' OR u.mobile LIKE '%$search_esc%' OR u.institute_name LIKE '%$search_esc%')";
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT t.*, u.mobile, u.institute_name
        FROM tlc_join_durations t
        LEFT JOIN users u ON t.user_id = u.id
        $where_sql
        ORDER BY t.total_duration DESC";
$result = mysqli_query($conn, $sql);

$durations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $durations[] = $row;
}

// Stats
$total_users = count($durations);
$total_duration = array_sum(array_column($durations, 'total_duration'));
$avg_duration = $total_users ? round($total_duration / $total_users, 1) : 0;
$max_duration = 324;
$max_duration_count = 0;
$dist = [
    '0-60' => 0,
    '61-120' => 0,
    '121-200' => 0,
    '201-323' => 0,
    '324' => 0
];
foreach ($durations as $row) {
    $d = (int)$row['total_duration'];
    if ($d == $max_duration) {
        $dist['324']++;
        $max_duration_count++;
    } elseif ($d > 200) {
        $dist['201-323']++;
    } elseif ($d > 120) {
        $dist['121-200']++;
    } elseif ($d > 60) {
        $dist['61-120']++;
    } else {
        $dist['0-60']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>TLC Join Durations | IPN Academy</title>
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
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="mb-3">TLC Join Durations Report</h3>
                    <form class="row g-3 align-items-end" method="GET">
                        <div class="col-md-3">
                            <label class="form-label">Day</label>
                            <select name="day" class="form-select">
                                <option value="">All Days</option>
                                <?php foreach ($days as $d): ?>
                                    <option value="<?php echo $d; ?>" <?php if ($day == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">School (optional)</label>
                            <select name="school_id" class="form-select">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php if ($school_id == $s['id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" id="tlcSearchBox" class="form-control" placeholder="Name, Email, Mobile, Institute..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success w-100">Export CSV</a>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Users</h6>
                            <h2 class="mb-0"><?php echo $total_users; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Duration</h6>
                            <h2 class="mb-0"><?php echo $total_duration; ?> min</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Avg. Duration</h6>
                            <h2 class="mb-0"><?php echo $avg_duration; ?> min</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Max Duration (<?php echo $max_duration; ?> min)</h6>
                            <h2 class="mb-0"><?php echo $max_duration_count; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Duration Distribution -->
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="mb-3">Duration Distribution</h6>
                    <div class="progress" style="height: 25px;">
                        <?php
                        $total_dist = array_sum($dist);
                        foreach ($dist as $label => $count) {
                            if ($total_dist > 0) {
                                $width = ($count / $total_dist) * 100;
                                $color = 'bg-secondary';
                                if ($label == '324') $color = 'bg-success';
                                elseif ($label == '201-323') $color = 'bg-info';
                                elseif ($label == '121-200') $color = 'bg-warning';
                                elseif ($label == '61-120') $color = 'bg-primary';
                                elseif ($label == '0-60') $color = 'bg-danger';
                                echo "<div class='progress-bar $color' role='progressbar' style='width: $width%' title='$label min: $count users'>" . round($width) . "%</div>";
                            }
                        }
                        ?>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-danger">0-60 min</small>
                        <small class="text-primary">61-120 min</small>
                        <small class="text-warning">121-200 min</small>
                        <small class="text-info">201-323 min</small>
                        <small class="text-success">324 min</small>
                    </div>
                </div>
            </div>
            <!-- Data Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">TLC Join Durations List</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="tlcDurationsTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Mobile</th>
                                            <th>School</th>
                                            <th>Duration (min)</th>
                                            <th>Day</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($durations) == 0): ?>
                                        <tr><td colspan="6" class="text-center">No records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($durations as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                                <td><?php echo htmlspecialchars(isset($row['institute_name']) ? $row['institute_name'] : 'N/A'); ?></td>
                                                <td><?php echo (int)$row['total_duration']; ?></td>
                                                <td><?php echo (int)$row['day']; ?></td>
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
    <?php if ($is_logged_in): ?>
        <?php include 'includes/theme_settings.php'; ?>
    <?php endif; ?>
</div>
<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
    $('#tlcDurationsTable').DataTable({
        "order": [],
        "pageLength": 25
    });
});
</script>
</body>
</html> 