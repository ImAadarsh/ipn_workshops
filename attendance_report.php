<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

// Get workshop list for dropdown
$workshops = [];
$workshop_sql = "SELECT id, name FROM workshops WHERE is_deleted = 0 ORDER BY start_date DESC";
$workshop_result = mysqli_query($conn, $workshop_sql);
while ($row = mysqli_fetch_assoc($workshop_result)) {
    $workshops[] = $row;
}

// Get school list for dropdown
$schools = [];
$school_sql = "SELECT id, name FROM schools ORDER BY name";
$school_result = mysqli_query($conn, $school_sql);
while ($row = mysqli_fetch_assoc($school_result)) {
    $schools[] = $row;
}

// Get filters
$workshop_id = isset($_GET['workshop_id']) ? (int)$_GET['workshop_id'] : 0;
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;

// Export as CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv' && $workshop_id) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $workshop_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Mobile', 'School/Institute', 'Attended', 'Duration (min)']);
    $where = "p.workshop_id = $workshop_id";
    if ($school_id) {
        $where .= " AND (p.school_id = $school_id OR u.school_id = $school_id)";
    }
    $sql = "SELECT u.name, u.email, u.mobile, u.school_id as user_school_id, u.institute_name, s.name as school, p.is_attended, p.attended_duration
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE $where AND p.payment_status = 1
            ORDER BY u.name";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $school_display = '';
        if ($row['user_school_id'] && $row['school']) {
            $school_display = $row['school'];
        } elseif ($row['user_school_id'] && !$row['school']) {
            $school_display = $row['institute_name'] . ' (Not registered in any school in system)';
        } elseif (!$row['user_school_id'] && $row['institute_name']) {
            $school_display = $row['institute_name'] . ' (Not registered in any school in system)';
        } else {
            $school_display = 'N/A';
        }
        fputcsv($output, [
            $row['name'],
            $row['email'],
            $row['mobile'],
            $school_display,
            $row['is_attended'] ? 'Yes' : 'No',
            $row['attended_duration']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch attendance data
$attendance = [];
if ($workshop_id) {
    $where = "p.workshop_id = $workshop_id";
    if ($school_id) {
        $where .= " AND (p.school_id = $school_id OR u.school_id = $school_id)";
    }
    $sql = "SELECT u.name, u.email, u.mobile, u.school_id as user_school_id, u.institute_name, s.name as school, p.is_attended, p.attended_duration
            FROM payments p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN schools s ON u.school_id = s.id
            WHERE $where AND p.payment_status = 1
            ORDER BY u.name";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Attendance Report | IPN Academy</title>
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
                    <?php if ($workshop_id): ?>
                    <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-outline-secondary mb-3">
                        <i class="ti ti-arrow-left me-1"></i> Back to Workshop Details
                    </a>
                    <?php endif; ?>
                    <h3 class="mb-3">Attendance Report</h3>
                    <form class="row g-3 align-items-end" method="GET">
                        <div class="col-md-4">
                            <label class="form-label">Workshop</label>
                            <select name="workshop_id" class="form-select" required>
                                <option value="">Select Workshop</option>
                                <?php foreach ($workshops as $w): ?>
                                    <option value="<?php echo $w['id']; ?>" <?php if ($workshop_id == $w['id']) echo 'selected'; ?>><?php echo htmlspecialchars($w['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">School (optional)</label>
                            <select name="school_id" class="form-select">
                                <option value="">All Schools</option>
                                <?php foreach ($schools as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php if ($school_id == $s['id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">View Report</button>
                            <?php if ($workshop_id): ?>
                                <a href="?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&export=csv" class="btn btn-success ms-2">Export CSV</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php if ($workshop_id): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Attendance List</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $has_unregistered = false;
                            foreach ($attendance as $row) {
                                if ((($row['user_school_id'] && !$row['school']) || (!$row['user_school_id'] && $row['institute_name']))) {
                                    $has_unregistered = true;
                                    break;
                                }
                            }
                            if ($has_unregistered): ?>
                                <div class="alert alert-warning mb-3">
                                    <strong>Note:</strong> Some users are <span style="color: orange; font-weight: bold;">not registered in any school in the system</span>. Their institute name is shown in orange below.
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="attendanceTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Mobile</th>
                                            <th>School/Institute</th>
                                            <th>Attended</th>
                                            <th>Duration (min)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($attendance) == 0): ?>
                                        <tr><td colspan="6" class="text-center">No records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                                <td>
                                                    <?php
                                                    if ($row['user_school_id'] && $row['school']) {
                                                        echo htmlspecialchars($row['school']);
                                                    } elseif ($row['user_school_id'] && !$row['school']) {
                                                        echo '<span style="color: orange; font-weight: bold;">' . htmlspecialchars($row['institute_name']);
                                                    } elseif (!$row['user_school_id'] && $row['institute_name']) {
                                                        echo '<span style="color: orange; font-weight: bold;">' . htmlspecialchars($row['institute_name']);
                                                    } else {
                                                        echo '<span class="text-muted">N/A</span>';
                                                    }
                                                    ?>
                                                </td>
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
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/theme_settings.php'; ?>
</div>
<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        "order": [],
        "pageLength": 25
    });
});
</script>
</body>
</html> 