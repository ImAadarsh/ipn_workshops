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
    // Get filter parameters for CSV export
    $csv_search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $csv_attendance_filter = isset($_GET['attendance_filter']) ? $_GET['attendance_filter'] : '';
    $csv_duration_filter = isset($_GET['duration_filter']) ? $_GET['duration_filter'] : '';
    
    // Build WHERE conditions for CSV export
    $csv_where_conditions = [
        "p.workshop_id = $workshop_id",
        "p.payment_status = 1",
        "(p.school_id = $school_id OR u.school_id = $school_id)"
    ];
    
    // Add search condition
    if (!empty($csv_search)) {
        $csv_search_escaped = mysqli_real_escape_string($conn, $csv_search);
        $csv_where_conditions[] = "(u.name LIKE '%$csv_search_escaped%' OR u.email LIKE '%$csv_search_escaped%' OR u.mobile LIKE '%$csv_search_escaped%')";
    }
    
    // Add attendance filter
    if ($csv_attendance_filter === 'attended') {
        $csv_where_conditions[] = "p.is_attended = 1";
    } elseif ($csv_attendance_filter === 'not_attended') {
        $csv_where_conditions[] = "p.is_attended = 0";
    }
    
    // Add duration filter
    if ($csv_duration_filter === 'less_than_60') {
        $csv_where_conditions[] = "p.attended_duration < 60 AND p.is_attended = 1";
    } elseif ($csv_duration_filter === 'more_than_60') {
        $csv_where_conditions[] = "p.attended_duration >= 60 AND p.is_attended = 1";
    } elseif ($csv_duration_filter === 'certificate_eligible') {
        $csv_where_conditions[] = "p.attended_duration >= 60 AND p.is_attended = 1";
    }
    
    $csv_where_clause = implode(' AND ', $csv_where_conditions);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="school_teachers_' . $school_id . '_workshop_' . $workshop_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Mobile', 'Attended', 'Duration (min)']);
    $sql = "SELECT u.id, u.name, u.email, u.mobile, p.is_attended, p.attended_duration
            FROM payments p
            INNER JOIN users u ON p.user_id = u.id
            WHERE $csv_where_clause
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

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$attendance_filter = isset($_GET['attendance_filter']) ? $_GET['attendance_filter'] : '';
$duration_filter = isset($_GET['duration_filter']) ? $_GET['duration_filter'] : '';

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
    // Build WHERE conditions for filtering
    $where_conditions = [
        "p.workshop_id = $workshop_id",
        "p.payment_status = 1",
        "(p.school_id = $school_id OR u.school_id = $school_id)"
    ];
    
    // Add search condition
    if (!empty($search)) {
        $search_escaped = mysqli_real_escape_string($conn, $search);
        $where_conditions[] = "(u.name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%' OR u.mobile LIKE '%$search_escaped%')";
    }
    
    // Add attendance filter
    if ($attendance_filter === 'attended') {
        $where_conditions[] = "p.is_attended = 1";
    } elseif ($attendance_filter === 'not_attended') {
        $where_conditions[] = "p.is_attended = 0";
    }
    
    // Add duration filter
    if ($duration_filter === 'less_than_60') {
        $where_conditions[] = "p.attended_duration < 60 AND p.is_attended = 1";
    } elseif ($duration_filter === 'more_than_60') {
        $where_conditions[] = "p.attended_duration >= 60 AND p.is_attended = 1";
    } elseif ($duration_filter === 'certificate_eligible') {
        $where_conditions[] = "p.attended_duration >= 60 AND p.is_attended = 1";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Teachers list (unique by user)
    $sql = "SELECT u.id, u.name, u.email, u.mobile, p.is_attended, p.attended_duration
            FROM payments p
            INNER JOIN users u ON p.user_id = u.id
            WHERE $where_clause
            GROUP BY u.id
            ORDER BY u.name";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
    // Attendance stats (respecting filters)
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
        WHERE $where_clause";
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
                    
                    <!-- Search and Filter Form -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                                
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, or mobile">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Attendance Status</label>
                                    <select class="form-select" name="attendance_filter">
                                        <option value="">All</option>
                                        <option value="attended" <?php echo $attendance_filter === 'attended' ? 'selected' : ''; ?>>Attended</option>
                                        <option value="not_attended" <?php echo $attendance_filter === 'not_attended' ? 'selected' : ''; ?>>Not Attended</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Duration Filter</label>
                                    <select class="form-select" name="duration_filter">
                                        <option value="">All</option>
                                        <option value="less_than_60" <?php echo $duration_filter === 'less_than_60' ? 'selected' : ''; ?>>Less than 60 min</option>
                                        <option value="more_than_60" <?php echo $duration_filter === 'more_than_60' ? 'selected' : ''; ?>>60+ min</option>
                                        <option value="certificate_eligible" <?php echo $duration_filter === 'certificate_eligible' ? 'selected' : ''; ?>>Certificate Eligible</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-search me-1"></i> Filter
                                        </button>
                                        <a href="?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>" class="btn btn-outline-secondary">
                                            <i class="ti ti-refresh me-1"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Export with current filters -->
                    <a href="?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&export=csv&search=<?php echo urlencode($search); ?>&attendance_filter=<?php echo urlencode($attendance_filter); ?>&duration_filter=<?php echo urlencode($duration_filter); ?>" class="btn btn-success mb-3">
                        <i class="ti ti-download me-1"></i> Export CSV
                    </a>
                    
                    <!-- Certificate Requirements Note -->
                    <div class="alert alert-warning mb-3">
                        <i class="ti ti-certificate me-1"></i>
                        <strong>Certificate Requirements:</strong> To be eligible for a certificate, teachers must have <strong>Attended = Yes</strong> AND <strong>Duration ≥ 60 minutes</strong>.
                    </div>
                    
                    <!-- Results Summary -->
                    <?php if (!empty($search) || !empty($attendance_filter) || !empty($duration_filter)): ?>
                    <div class="alert alert-info mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        <strong>Filtered Results:</strong> 
                        <?php echo count($teachers); ?> teacher(s) found
                        <?php if (!empty($search)): ?> • Search: "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                        <?php if (!empty($attendance_filter)): ?> • Attendance: <?php echo $attendance_filter === 'attended' ? 'Attended' : 'Not Attended'; ?><?php endif; ?>
                        <?php if (!empty($duration_filter)): ?> • Duration: <?php 
                            if ($duration_filter === 'less_than_60') echo 'Less than 60 min';
                            elseif ($duration_filter === 'more_than_60') echo '60+ min';
                            elseif ($duration_filter === 'certificate_eligible') echo 'Certificate Eligible';
                        ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
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
                                            <th>Certificate Eligible</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($teachers) == 0): ?>
                                        <tr><td colspan="7" class="text-center">No teachers found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($teachers as $row): ?>
                                            <?php 
                                            // Check certificate eligibility
                                            $is_eligible = ($row['is_attended'] == 1 && $row['attended_duration'] >= 60);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                                <td>
                                                    <span id="attended-badge-<?php echo $row['id']; ?>" class="badge <?php echo $row['is_attended'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $row['is_attended'] ? 'Yes' : 'No'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span id="duration-display-<?php echo $row['id']; ?>">
                                                        <?php echo htmlspecialchars($row['attended_duration']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $is_eligible ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <i class="ti ti-certificate me-1"></i>
                                                        <?php echo $is_eligible ? 'Eligible' : 'Not Eligible'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="editAttendance(<?php echo $row['id']; ?>, <?php echo $workshop_id; ?>, <?php echo $row['is_attended']; ?>, <?php echo $row['attended_duration']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                                        <i class="ti ti-edit me-1"></i> Edit
                                                    </button>
                                                </td>
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
    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAttendanceModalLabel">Edit Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAttendanceForm">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <input type="hidden" id="edit_workshop_id" name="workshop_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Teacher Name</label>
                            <input type="text" class="form-control" id="edit_teacher_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Attended</label>
                            <select class="form-select" id="edit_is_attended" name="is_attended">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="edit_attended_duration" name="attended_duration" min="0" max="120" placeholder="Enter duration in minutes">
                            <small class="text-muted">Enter duration between 0-120 minutes</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAttendance()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

<script src="assets/js/vendor.min.js"></script>
<script src="assets/js/app.min.js"></script>

<script>
function editAttendance(userId, workshopId, isAttended, attendedDuration, teacherName) {
    // Populate modal with current values
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_workshop_id').value = workshopId;
    document.getElementById('edit_teacher_name').value = teacherName;
    document.getElementById('edit_is_attended').value = isAttended;
    document.getElementById('edit_attended_duration').value = attendedDuration;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editAttendanceModal'));
    modal.show();
}

function saveAttendance() {
    const formData = new FormData(document.getElementById('editAttendanceForm'));
    
    // Show loading state
    const saveBtn = document.querySelector('#editAttendanceModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i> Saving...';
    saveBtn.disabled = true;
    
    fetch('update_attendance.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the display
            const userId = document.getElementById('edit_user_id').value;
            const isAttended = document.getElementById('edit_is_attended').value;
            const attendedDuration = document.getElementById('edit_attended_duration').value;
            
            // Update attended badge
            const attendedBadge = document.getElementById('attended-badge-' + userId);
            attendedBadge.className = 'badge ' + (isAttended == 1 ? 'bg-success' : 'bg-danger');
            attendedBadge.textContent = isAttended == 1 ? 'Yes' : 'No';
            
            // Update duration display
            const durationDisplay = document.getElementById('duration-display-' + userId);
            durationDisplay.textContent = attendedDuration;
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal'));
            modal.hide();
            
            // Show success message
            showAlert('Attendance updated successfully!', 'success');
            
            // Reload page after 1 second to update statistics
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error updating attendance. Please try again.', 'danger');
    })
    .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function showAlert(message, type) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to page
    document.body.appendChild(alertDiv);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 3000);
}
</script>
</body>
</html>
