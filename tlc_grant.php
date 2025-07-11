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

// Handle grace grant action (single or multiple)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grant_ids'])) {
    $grant_ids = array_map('intval', $_POST['grant_ids']);
    if ($grant_ids) {
        $ids_str = implode(',', $grant_ids);
        // Get all user_ids for selected ids
        $user_ids = [];
        $result = mysqli_query($conn, "SELECT DISTINCT user_id FROM tlc_join_durations WHERE id IN ($ids_str)");
        while ($row = mysqli_fetch_assoc($result)) {
            $user_ids[] = (int)$row['user_id'];
        }
        if ($user_ids) {
            $user_ids_str = implode(',', $user_ids);
            // Set grace_grant=1 for all records of these user_ids
            mysqli_query($conn, "UPDATE tlc_join_durations SET grace_grant=1 WHERE user_id IN ($user_ids_str)");
            $msg = "Grace granted for selected users.";
        }
    }
}

// Search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = ["t.reason IS NOT NULL", "t.reason != ''"];
if ($search) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where[] = "(t.name LIKE '%$search_esc%' OR t.email LIKE '%$search_esc%' OR t.user_id LIKE '%$search_esc%' OR u.mobile LIKE '%$search_esc%' OR u.institute_name LIKE '%$search_esc%')";
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get one row per user_id, with the latest reason, and join users for mobile/institute_name
$sql = "SELECT t.user_id, t.name, t.email, MAX(t.reason) as reason, MAX(t.grace_grant) as grace_grant, u.mobile, u.institute_name, SUM(t.total_duration) as total_duration
        FROM tlc_join_durations t
        LEFT JOIN users u ON t.user_id = u.id
        $where_sql
        GROUP BY t.user_id, t.name, t.email, u.mobile, u.institute_name
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
            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width:500px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, user id, phone, or institute..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </form>
            <form method="POST">
                <div class="mb-2">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Grant grace to selected users?')">Grant Grace to Selected</button>
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
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td>
                                    <?php if ($row['grace_grant']): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$row['grace_grant']): ?>
                                        <button type="submit" name="grant_ids[]" value="<?php echo $row['user_id']; ?>" class="btn btn-sm btn-primary">Grant Grace</button>
                                    <?php else: ?>
                                        <span class="text-success">Granted</span>
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
    $('#graceGrantTable').DataTable({
        "order": [],
        "pageLength": 25
    });
    $('#selectAll').on('change', function() {
        $('.row-check').prop('checked', this.checked);
    });
});
</script>
</body>
</html>