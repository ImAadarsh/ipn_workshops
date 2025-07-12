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
$sql = "SELECT t.user_id, t.name, t.email, MAX(t.reason) as reason, MAX(t.grace_grant) as grace_grant, u.mobile, u.institute_name, SUM(t.total_duration) as total_duration, MAX(t.updated_at) as updated_at
        FROM tlc_join_durations t
        LEFT JOIN users u ON t.user_id = u.id
        $where_sql
        GROUP BY t.user_id, t.name, t.email, u.mobile, u.institute_name
        $having_sql
        ORDER BY updated_at DESC";
$result = mysqli_query($conn, $sql);

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// Get latest grant applications (last 10)
$latest_sql = "SELECT t.user_id, t.name, t.email, t.reason, t.grace_grant, t.updated_at, u.mobile, u.institute_name
               FROM tlc_join_durations t
               LEFT JOIN users u ON t.user_id = u.id
               WHERE t.reason IS NOT NULL AND t.reason != ''
               ORDER BY t.updated_at DESC
               LIMIT 10";
$latest_result = mysqli_query($conn, $latest_sql);
$latest_applications = [];
while ($row = mysqli_fetch_assoc($latest_result)) {
    $latest_applications[] = $row;
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
            
            <!-- Latest Grant Applications -->
            <?php if (!empty($latest_applications)): ?>
            <!-- <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-clock"></i> Latest Grant Applications
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['name']); ?></strong><br>
                                        <small class="text-muted">ID: <?php echo $app['user_id']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($app['email']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($app['mobile'] ?: 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?php echo htmlspecialchars($app['reason']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($app['grace_grant']): ?>
                                            <span class="badge bg-success">Granted</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d M Y H:i', strtotime($app['updated_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> -->
            <?php endif; ?>
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-md-2">
                    <label class="form-label">Applied Status</label>
                    <select name="applied_filter" class="form-select">
                        <option value="">All</option>
                        <option value="applied" <?php if($applied_filter==='applied') echo 'selected'; ?>>Applied</option>
                        <option value="not_applied" <?php if($applied_filter==='not_applied') echo 'selected'; ?>>Not Applied</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Grant Status</label>
                    <select name="grace_filter" class="form-select">
                        <option value="">All</option>
                        <option value="1" <?php if(isset($_GET['grace_filter']) && $_GET['grace_filter']=='1') echo 'selected'; ?>>Granted</option>
                        <option value="0" <?php if(isset($_GET['grace_filter']) && $_GET['grace_filter']=='0') echo 'selected'; ?>>Not Granted</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, user id, phone, or institute..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Duration</label>
                    <input type="number" name="min_duration" class="form-control" placeholder="Min Duration" value="<?php echo isset($_GET['min_duration']) ? (int)$_GET['min_duration'] : '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Duration</label>
                    <input type="number" name="max_duration" class="form-control" placeholder="Max Duration" value="<?php echo isset($_GET['max_duration']) ? (int)$_GET['max_duration'] : '' ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
            </form>
            <form method="POST">
                <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                    <button type="submit" name="grant_action" value="grant" class="btn btn-success" onclick="return confirm('Grant grace to selected users?')">
                        <i class="ti ti-check"></i> Grant Grace to Selected
                    </button>
                    <button type="submit" name="grant_action" value="ungrant" class="btn btn-danger" onclick="return confirm('Ungrant grace for selected users?')">
                        <i class="ti ti-x"></i> Ungrant Grace for Selected
                    </button>
                    <span class="text-muted ms-2">(<span id="selectedCount">0</span> users selected)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="graceGrantTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Institute</th>
                                <th>Total Duration (min)</th>
                                <th>Reason</th>
                                <th>Grace Granted?</th>
                                <th width="120">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($users) == 0): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="ti ti-users-off" style="font-size: 2rem;"></i>
                                    <p class="mt-2">No users found matching the current filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="grant_ids[]" value="<?php echo $row['user_id']; ?>" class="row-check form-check-input">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['user_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['mobile'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['institute_name'] ?: 'N/A'); ?></td>
                                    <td><span class="badge bg-info"><?php echo (int)$row['total_duration']; ?> min</span></td>
                                    <td>
                                        <?php if ($row['reason'] !== null && $row['reason'] !== ''): ?>
                                            <span class="text-muted small"><?php echo htmlspecialchars($row['reason']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['grace_grant']): ?>
                                            <span class="badge bg-success">Granted</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Granted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$row['grace_grant']): ?>
                                            <button type="submit" name="single_action[<?php echo $row['user_id']; ?>]" value="grant" class="btn btn-sm btn-primary">
                                                <i class="ti ti-check"></i> Grant
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="single_action[<?php echo $row['user_id']; ?>]" value="ungrant" class="btn btn-sm btn-danger">
                                                <i class="ti ti-x"></i> Ungrant
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
<script>
$(function() {
    // Multi-select functionality
    function updateSelectedCount() {
        var checkedCount = $('.row-check:checked').length;
        $('#selectedCount').text(checkedCount);
        
        // Update select all checkbox
        var totalCheckboxes = $('.row-check').length;
        if (checkedCount === 0) {
            $('#selectAll').prop('indeterminate', false).prop('checked', false);
        } else if (checkedCount === totalCheckboxes) {
            $('#selectAll').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#selectAll').prop('indeterminate', true);
        }
    }

    // Header checkbox - select all
    $('#selectAll').on('change', function() {
        var checked = this.checked;
        $('.row-check').prop('checked', checked);
        updateSelectedCount();
    });

    // Individual row checkboxes
    $(document).on('change', '.row-check', function() {
        updateSelectedCount();
    });

    // Initialize count
    updateSelectedCount();

    // Form validation - only for POST forms (grant actions)
    $('form[method="POST"]').on('submit', function(e) {
        var checkedBoxes = $('.row-check:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one user to perform the action.');
            return false;
        }
    });

    // Auto-submit on single action buttons
    $('button[name^="single_action"]').on('click', function(e) {
        if (!confirm('Are you sure you want to perform this action?')) {
            e.preventDefault();
            return false;
        }
    });

    // Add some visual feedback
    $('.row-check').on('change', function() {
        var row = $(this).closest('tr');
        if (this.checked) {
            row.addClass('table-primary');
        } else {
            row.removeClass('table-primary');
        }
    });
});
</script>
</body>
</html>