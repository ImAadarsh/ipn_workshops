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

// Handle grant/ungrant actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Multi-select
    if (isset($_POST['grant_ids']) && isset($_POST['grant_action'])) {
        $user_ids = array_map('intval', $_POST['grant_ids']);
        if ($user_ids) {
            $user_ids_str = implode(',', $user_ids);
            if ($_POST['grant_action'] === 'grant') {
                mysqli_query($conn, "UPDATE tlc_join_durations SET grace_grant=1 WHERE user_id IN ($user_ids_str)");
                $msg = "Grace granted for selected users.";
            } elseif ($_POST['grant_action'] === 'ungrant') {
                mysqli_query($conn, "UPDATE tlc_join_durations SET grace_grant=0 WHERE user_id IN ($user_ids_str)");
                $msg = "Grace ungranted for selected users.";
            }
        }
    }
    // Single action
    if (isset($_POST['single_action'])) {
        foreach ($_POST['single_action'] as $uid => $action) {
            $uid = (int)$uid;
            if ($action === 'grant') {
                mysqli_query($conn, "UPDATE tlc_join_durations SET grace_grant=1 WHERE user_id = $uid");
                $msg = "Grace granted for user $uid.";
            } elseif ($action === 'ungrant') {
                mysqli_query($conn, "UPDATE tlc_join_durations SET grace_grant=0 WHERE user_id = $uid");
                $msg = "Grace ungranted for user $uid.";
            }
        }
    }
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grace_filter = isset($_GET['grace_filter']) ? $_GET['grace_filter'] : '';
$min_duration = isset($_GET['min_duration']) ? (int)$_GET['min_duration'] : '';
$max_duration = isset($_GET['max_duration']) ? (int)$_GET['max_duration'] : '';
$applied_filter = isset($_GET['applied_filter']) ? $_GET['applied_filter'] : 'applied'; // default to 'applied'

$where = [];
if ($applied_filter === 'applied') {
    $where[] = "t.reason IS NOT NULL";
    $where[] = "t.reason != ''";
} elseif ($applied_filter === 'not_applied') {
    $where[] = "(t.reason IS NULL OR t.reason = '')";
}
if ($search) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where[] = "(t.name LIKE '%$search_esc%' OR t.email LIKE '%$search_esc%' OR t.user_id LIKE '%$search_esc%' OR u.mobile LIKE '%$search_esc%' OR u.institute_name LIKE '%$search_esc%')";
}
if ($grace_filter !== '' && ($grace_filter === '0' || $grace_filter === '1')) {
    $where[] = "t.grace_grant = " . (int)$grace_filter;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$having = [];
if ($min_duration !== '') {
    $having[] = "SUM(t.total_duration) >= $min_duration";
}
if ($max_duration !== '') {
    $having[] = "SUM(t.total_duration) <= $max_duration";
}
$having_sql = !empty($having) ? 'HAVING ' . implode(' AND ', $having) : '';

// Get one row per user_id, with the latest reason, and join users for mobile/institute_name
$sql = "SELECT t.user_id, t.name, t.email, MAX(t.reason) as reason, MAX(t.grace_grant) as grace_grant, u.mobile, u.institute_name, SUM(t.total_duration) as total_duration
        FROM tlc_join_durations t
        LEFT JOIN users u ON t.user_id = u.id
        $where_sql
        GROUP BY t.user_id, t.name, t.email, u.mobile, u.institute_name
        $having_sql
        ORDER BY t.user_id DESC";
$result = mysqli_query($conn, $sql);

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>TLC Grace Grant Management | IPN Academy</title>
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
            <h3 class="mb-3">TLC Grace Grant Management</h3>
            <?php if (!empty($msg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-2">
                    <select name="applied_filter" class="form-select">
                        <option value="">All</option>
                        <option value="applied" <?php if($applied_filter==='applied') echo 'selected'; ?>>Applied</option>
                        <option value="not_applied" <?php if($applied_filter==='not_applied') echo 'selected'; ?>>Not Applied</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, user id, phone, or institute..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="grace_filter" class="form-select">
                        <option value="">All</option>
                        <option value="1" <?php if(isset($_GET['grace_filter']) && $_GET['grace_filter']=='1') echo 'selected'; ?>>Granted</option>
                        <option value="0" <?php if(isset($_GET['grace_filter']) && $_GET['grace_filter']=='0') echo 'selected'; ?>>Not Granted</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="min_duration" class="form-control" placeholder="Min Duration" value="<?php echo isset($_GET['min_duration']) ? (int)$_GET['min_duration'] : '' ?>">
                </div>
                <div class="col-md-2">
                    <input type="number" name="max_duration" class="form-control" placeholder="Max Duration" value="<?php echo isset($_GET['max_duration']) ? (int)$_GET['max_duration'] : '' ?>">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
            </form>
            <form method="POST">
                <div class="mb-2 d-flex gap-2 flex-wrap">
                    <button type="submit" name="grant_action" value="grant" class="btn btn-success" onclick="return confirm('Grant grace to selected users?')">Grant Grace to Selected</button>
                    <button type="submit" name="grant_action" value="ungrant" class="btn btn-danger" onclick="return confirm('Ungrant grace for selected users?')">Ungrant Grace for Selected</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped" id="graceGrantTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Institute</th>
                                <th>Total Duration (min)</th>
                                <th>Reason</th>
                                <th>Grace Granted?</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="grant_ids[]" value="<?php echo $row['user_id']; ?>" class="row-check"></td>
                                <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($row['institute_name']); ?></td>
                                <td><?php echo (int)$row['total_duration']; ?></td>
                                <td>
                                    <?php echo ($row['reason'] !== null && $row['reason'] !== '') ? htmlspecialchars($row['reason']) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($row['grace_grant']): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$row['grace_grant']): ?>
                                        <button type="submit" name="single_action[<?php echo $row['user_id']; ?>]" value="grant" class="btn btn-sm btn-primary">Grant Grace</button>
                                    <?php else: ?>
                                        <button type="submit" name="single_action[<?php echo $row['user_id']; ?>]" value="ungrant" class="btn btn-sm btn-danger">Ungrant</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php if ($is_logged_in): ?>
        <?php include 'includes/theme_settings.php'; ?>
    <?php endif; ?>
</div>
<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
    var table = $('#graceGrantTable').DataTable({
        "order": [],
        "pageLength": 25
    });

    // Header checkbox
    $('#selectAll').on('change', function() {
        var checked = this.checked;
        $('#graceGrantTable').find('input.row-check').prop('checked', checked);
    });
});
</script>
</body>
</html>