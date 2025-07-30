<?php
require_once 'config/config.php';
require_once 'includes/head.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_b2c2b':
                $school_id = intval($_POST['school_id']);
                $b2c2b_status = intval($_POST['b2c2b_status']);
                
                $update_query = "UPDATE schools SET b2c2b = ?, updated_at = NOW() WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ii", $b2c2b_status, $school_id);
                mysqli_stmt_execute($stmt);
                
                $_SESSION['success_message'] = "B2C2B status updated successfully!";
                header("Location: schools_management.php");
                exit();
                break;

            case 'update_password':
                $school_id = intval($_POST['school_id']);
                $new_password = $_POST['new_password'];
                
                // Hash the password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_query = "UPDATE schools SET password = ?, updated_at = NOW() WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "si", $hashed_password, $school_id);
                mysqli_stmt_execute($stmt);
                
                $_SESSION['success_message'] = "Password updated successfully!";
                header("Location: schools_management.php");
                exit();
                break;

            case 'update_school_details':
                $school_id = intval($_POST['school_id']);
                $school_name = trim($_POST['school_name']);
                $school_email = trim($_POST['school_email']);
                $school_mobile = trim($_POST['school_mobile']);
                
                // Validate email format
                if (!filter_var($school_email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['error_message'] = "Invalid email format!";
                    header("Location: schools_management.php");
                    exit();
                }
                
                // Check if email already exists for another school
                $check_query = "SELECT id FROM schools WHERE email = ? AND id != ?";
                $stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($stmt, "si", $school_email, $school_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) > 0) {
                    $_SESSION['error_message'] = "Email already exists for another school!";
                    header("Location: schools_management.php");
                    exit();
                }
                
                $update_query = "UPDATE schools SET name = ?, email = ?, mobile = ?, updated_at = NOW() WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssi", $school_name, $school_email, $school_mobile, $school_id);
                mysqli_stmt_execute($stmt);
                
                $_SESSION['success_message'] = "School details updated successfully!";
                header("Location: schools_management.php");
                exit();
                break;
        }
    }
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$b2c2b_filter = isset($_GET['b2c2b']) ? $_GET['b2c2b'] : 'all';

// Build the query with search and filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if ($status_filter !== 'all') {
    $where_conditions[] = "is_active = ?";
    $params[] = ($status_filter === 'active') ? 1 : 0;
    $param_types .= 'i';
}

if ($b2c2b_filter !== 'all') {
    $where_conditions[] = "b2c2b = ?";
    $params[] = ($b2c2b_filter === 'enabled') ? 1 : 0;
    $param_types .= 'i';
}

// Build the final query
$query = "SELECT id, name, email, mobile, is_active, b2c2b, created_at FROM schools";
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(' AND ', $where_conditions);
}
$query .= " ORDER BY name ASC";

// Execute the query
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$schools = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Schools Management | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <!-- Topbar Start -->
        <?php include 'includes/topbar.php'; ?>
        <!-- Topbar End -->

        <div class="page-content">
            <div class="page-container">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Schools Management</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    Schools Management
                    <a href="https://schools.ipnacademy.in/" target="_blank" class="btn btn-info btn-sm ms-3">
                        <i class="ti ti-external-link me-1"></i>School Portal
                    </a>
                </h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-building text-primary" style="font-size: 24px;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count($schools); ?></h4>
                            <p class="text-muted mb-0">Total Schools</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-check text-success" style="font-size: 24px;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count(array_filter($schools, function($s) { return $s['is_active'] == 1; })); ?></h4>
                            <p class="text-muted mb-0">Active Schools</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-toggle-right text-warning" style="font-size: 24px;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count(array_filter($schools, function($s) { return $s['b2c2b'] == 1; })); ?></h4>
                            <p class="text-muted mb-0">B2C2B Enabled</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="ti ti-toggle-left text-secondary" style="font-size: 24px;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count(array_filter($schools, function($s) { return $s['b2c2b'] == 0; })); ?></h4>
                            <p class="text-muted mb-0">B2B</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schools Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Search and Filter Form -->
                    <form method="GET" class="mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search by school name, email, or mobile..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="b2c2b" class="form-control">
                                    <option value="all" <?php echo $b2c2b_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="enabled" <?php echo $b2c2b_filter === 'enabled' ? 'selected' : ''; ?>>B2C2B Enabled</option>
                                    <option value="disabled" <?php echo $b2c2b_filter === 'disabled' ? 'selected' : ''; ?>>B2B</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti ti-search me-1"></i>Search
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="schools_management.php" class="btn btn-secondary w-100">
                                    <i class="ti ti-refresh me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Results Info -->
                    <?php if (!empty($search) || $status_filter !== 'all' || $b2c2b_filter !== 'all'): ?>
                        <div class="alert alert-info">
                            <strong>Search Results:</strong> 
                            Found <?php echo count($schools); ?> schools
                            <?php if (!empty($search)): ?>
                                matching "<?php echo htmlspecialchars($search); ?>"
                            <?php endif; ?>
                            <a href="schools_management.php" class="float-end">Clear all filters</a>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-centered table-striped dt-responsive nowrap w-100" id="schools-datatable">
                            <thead>
                                <tr>
                                    <th>School Name</th>
                                    <th>Email (Username)</th>
                                    <th>Mobile</th>
                                    <th>Status</th>
                                    <th>B2C2B Status</th>
                                    <th>Default Password</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($schools)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="ti ti-search" style="font-size: 48px; opacity: 0.3;"></i>
                                        <br><br>
                                        No schools found matching your search criteria.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($schools as $school): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($school['name']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: <?php echo $school['id']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($school['email']); ?>
                                            <br>
                                            <small class="text-muted">Username for login</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($school['mobile']); ?></td>
                                        <td>
                                            <?php if ($school['is_active'] == 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($school['b2c2b'] == 1): ?>
                                                <span class="badge bg-warning">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code>1@IPNACADEMY</code>
                                            <br>
                                            <small class="text-muted">Default password</small>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm" 
                                                    onclick="editB2C2B(<?php echo $school['id']; ?>, <?php echo $school['b2c2b']; ?>, '<?php echo htmlspecialchars($school['name']); ?>')">
                                                <i class="ti ti-toggle-right me-1"></i>B2C2B
                                            </button>
                                            <button type="button" class="btn btn-info btn-sm" 
                                                    onclick="editPassword(<?php echo $school['id']; ?>, '<?php echo htmlspecialchars($school['name']); ?>')">
                                                <i class="ti ti-key me-1"></i>Password
                                            </button>
                                            <button type="button" class="btn btn-primary btn-sm" 
                                                    onclick="editSchoolDetails(<?php echo $school['id']; ?>, '<?php echo htmlspecialchars($school['name']); ?>', '<?php echo htmlspecialchars($school['email']); ?>', '<?php echo htmlspecialchars($school['mobile']); ?>')">
                                                <i class="ti ti-edit me-1"></i>Edit
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

<!-- Edit B2C2B Status Modal -->
<div class="modal fade" id="editB2C2BModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_b2c2b">
                <input type="hidden" name="school_id" id="b2c2b_school_id">
                <div class="modal-header">
                    <h5 class="modal-title">Update B2C2B Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Update B2C2B status for: <strong id="b2c2b_school_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">B2C2B Status</label>
                        <select name="b2c2b_status" class="form-control" required>
                            <option value="0">No (Disabled)</option>
                            <option value="1">Yes (Enabled)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Password Modal -->
<div class="modal fade" id="editPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="school_id" id="password_school_id">
                <div class="modal-header">
                    <h5 class="modal-title">Update Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Update password for: <strong id="password_school_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="text" name="new_password" class="form-control" 
                               value="1@IPNACADEMY" required>
                        <small class="text-muted">Default password is: 1@IPNACADEMY</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit School Details Modal -->
<div class="modal fade" id="editSchoolDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_school_details">
                <input type="hidden" name="school_id" id="edit_school_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit School Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Update details for: <strong id="edit_school_name_display"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">School Name</label>
                        <input type="text" name="school_name" id="edit_school_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="school_email" id="edit_school_email" class="form-control" required>
                        <small class="text-muted">This will be used as the username for login</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="school_mobile" id="edit_school_mobile" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<!-- DataTables js -->
<script src="assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
<script src="assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#schools-datatable').DataTable({
        responsive: true,
        order: [[0, 'asc']], // Sort by school name by default
        pageLength: 25,
        searching: false // Disable DataTable's built-in search since we're using PHP search
    });
});

// Function to handle school details edit
function editSchoolDetails(schoolId, schoolName, schoolEmail, schoolMobile) {
    $('#edit_school_id').val(schoolId);
    $('#edit_school_name_display').text(schoolName);
    $('#edit_school_name').val(schoolName);
    $('#edit_school_email').val(schoolEmail);
    $('#edit_school_mobile').val(schoolMobile);
    $('#editSchoolDetailsModal').modal('show');
}

// Function to handle B2C2B status edit
function editB2C2B(schoolId, currentStatus, schoolName) {
    $('#b2c2b_school_id').val(schoolId);
    $('#b2c2b_school_name').text(schoolName);
    $('select[name="b2c2b_status"]').val(currentStatus);
    $('#editB2C2BModal').modal('show');
}

// Function to handle password edit
function editPassword(schoolId, schoolName) {
    $('#password_school_id').val(schoolId);
    $('#password_school_name').text(schoolName);
    $('#editPasswordModal').modal('show');
}
</script>

<?php require_once 'includes/footer.php'; ?> 