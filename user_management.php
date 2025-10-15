<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_user':
                $user_id = intval($_POST['user_id']);
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $email = mysqli_real_escape_string($conn, $_POST['email']);
                $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
                $designation = mysqli_real_escape_string($conn, $_POST['designation']);
                $institute_name = mysqli_real_escape_string($conn, $_POST['institute_name']);
                $city = mysqli_real_escape_string($conn, $_POST['city']);
                $school_id = !empty($_POST['school_id']) ? intval($_POST['school_id']) : null;
                
                $sql = "UPDATE users SET 
                        name = ?, email = ?, mobile = ?, designation = ?, 
                        institute_name = ?, city = ?, school_id = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssii", $name, $email, $mobile, $designation, $institute_name, $city, $school_id, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "User updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Error updating user: " . mysqli_error($conn);
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "User deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting user: " . mysqli_error($conn);
                }
                break;
        }
        header("Location: user_management.php");
        exit();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_school = isset($_GET['filter_school']) ? intval($_GET['filter_school']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 100; // Reduced from 20 to 12
$offset = ($page - 1) * $per_page;

// Build the query
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.institute_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if (!empty($filter_school)) {
    $where_conditions[] = "u.school_id = ?";
    $params[] = $filter_school;
    $param_types .= "i";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(u.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(u.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_result = mysqli_stmt_get_result($count_stmt);
$total_users = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_users / $per_page);

// Get users with pagination
$sql = "SELECT u.*, s.name as school_name 
        FROM users u 
        LEFT JOIN schools s ON u.school_id = s.id 
        WHERE $where_clause 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get schools for filter
$schools_sql = "SELECT id, name FROM schools ORDER BY name ASC";
$schools_result = mysqli_query($conn, $schools_sql);
$schools = [];
while ($school = mysqli_fetch_assoc($schools_result)) {
    $schools[] = $school;
}

// Get user statistics for different time periods
$stats = [];

// Today
$today_sql = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$stats['today'] = mysqli_fetch_assoc($today_result)['count'];

// This Week
$week_sql = "SELECT COUNT(*) as count FROM users WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
$week_result = mysqli_query($conn, $week_sql);
$stats['week'] = mysqli_fetch_assoc($week_result)['count'];

// This Month
$month_sql = "SELECT COUNT(*) as count FROM users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
$month_result = mysqli_query($conn, $month_sql);
$stats['month'] = mysqli_fetch_assoc($month_result)['count'];

// This Year
$year_sql = "SELECT COUNT(*) as count FROM users WHERE YEAR(created_at) = YEAR(CURDATE())";
$year_result = mysqli_query($conn, $year_sql);
$stats['year'] = mysqli_fetch_assoc($year_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>User Management | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .user-card {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        .badge-issue {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 4px;
        }
        /* Fix z-index for modals */
        .modal {
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        /* Responsive pagination */
        .pagination {
            flex-wrap: wrap;
            justify-content: center;
        }
        .pagination .page-link {
            margin: 2px;
        }
        @media (max-width: 768px) {
            .pagination .page-item {
                margin: 1px;
            }
            .pagination .page-link {
                padding: 0.375rem 0.5rem;
                font-size: 0.875rem;
            }
        }
        /* Statistics Cards */
        .stats-card {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        .stats-icon {
            transition: all 0.3s ease;
        }
        .stats-card:hover .stats-icon {
            transform: scale(1.1);
        }
        .stats-number {
            transition: all 0.3s ease;
        }
        .stats-card:hover .stats-number {
            transform: scale(1.05);
        }
        .stats-card .card-body {
            padding: 1rem 0.75rem;
        }
        .stats-card .stats-icon-container {
            margin-bottom: 0.75rem;
        }
        .stats-card .stats-icon-container .rounded-circle {
            padding: 0.5rem !important;
        }
        .stats-card .stats-icon {
            font-size: 1.25rem !important;
        }
        .stats-card .stats-number {
            font-size: 1.5rem !important;
            margin-bottom: 0.25rem !important;
        }
        .stats-card p {
            margin-bottom: 0.25rem !important;
        }
        .stats-card small {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Smart User Management</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="user_analytics.php" class="btn btn-info me-2">
                                    <i class="ti ti-chart-line me-1"></i> Analytics
                                </a>
                                <a href="bad_users.php" class="btn btn-danger me-2">
                                    <i class="ti ti-alert-triangle me-1"></i> Bad Users
                                </a>
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card stats-card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="d-flex align-items-center justify-content-center stats-icon-container">
                                    <div class="bg-primary bg-opacity-10 rounded-circle">
                                        <i class="ti ti-calendar-week text-primary stats-icon"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold text-primary stats-number"><?php echo number_format($stats['today']); ?></h3>
                                <p class="text-muted mb-0">Today</p>
                                <small class="text-muted">New registrations</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card stats-card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="d-flex align-items-center justify-content-center stats-icon-container">
                                    <div class="bg-info bg-opacity-10 rounded-circle">
                                        <i class="ti ti-calendar-week text-info stats-icon"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold text-info stats-number"><?php echo number_format($stats['week']); ?></h3>
                                <p class="text-muted mb-0">This Week</p>
                                <small class="text-muted">New registrations</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card stats-card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="d-flex align-items-center justify-content-center stats-icon-container">
                                    <div class="bg-success bg-opacity-10 rounded-circle">
                                        <i class="ti ti-calendar-month text-success stats-icon"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold text-success stats-number"><?php echo number_format($stats['month']); ?></h3>
                                <p class="text-muted mb-0">This Month</p>
                                <small class="text-muted">New registrations</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card stats-card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <div class="d-flex align-items-center justify-content-center stats-icon-container">
                                    <div class="bg-warning bg-opacity-10 rounded-circle">
                                        <i class="ti ti-calendar-month text-warning stats-icon"></i>
                                    </div>
                                </div>
                                <h3 class="fw-bold text-warning stats-number"><?php echo number_format($stats['year']); ?></h3>
                                <p class="text-muted mb-0">This Year</p>
                                <small class="text-muted">New registrations</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
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

                <!-- Auto-edit if edit parameter is present -->
                <?php if (isset($_GET['edit'])): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            editUser(<?php echo intval($_GET['edit']); ?>);
                        });
                    </script>
                <?php endif; ?>

                <!-- Search and Filters -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-search me-2"></i>Search & Filters
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Name, Email, Mobile, Institute...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">School</label>
                                <select class="form-select" name="filter_school">
                                    <option value="">All Schools</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo $school['id']; ?>" <?php echo $filter_school == $school['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($school['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-search me-1"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-filter me-1"></i> Apply Filters
                                    </button>
                                    <a href="user_management.php" class="btn btn-outline-secondary">
                                        <i class="ti ti-x me-1"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-light border-0">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <small class="text-muted">
                                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_users); ?> 
                                        of <?php echo number_format($total_users); ?> users
                                    </small>
                                    <?php if (!empty($search) || !empty($filter_school) || !empty($date_from) || !empty($date_to)): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <small class="text-muted">Active filters:</small>
                                            <?php if (!empty($search)): ?>
                                                <span class="badge bg-primary">Search: <?php echo htmlspecialchars($search); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($filter_school)): ?>
                                                <?php 
                                                $selected_school = array_filter($schools, function($s) use ($filter_school) { 
                                                    return $s['id'] == $filter_school; 
                                                });
                                                $selected_school = reset($selected_school);
                                                ?>
                                                <span class="badge bg-info">School: <?php echo htmlspecialchars($selected_school['name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($date_from)): ?>
                                                <span class="badge bg-success">From: <?php echo date('d M Y', strtotime($date_from)); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($date_to)): ?>
                                                <span class="badge bg-success">To: <?php echo date('d M Y', strtotime($date_to)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Grid -->
                <div class="row g-4">
                    <?php while ($user = mysqli_fetch_assoc($result)): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card user-card border-0 shadow-sm h-100">
                                <div class="card-header bg-light border-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold text-primary mb-0">
                                            <?php echo highlightSearch(htmlspecialchars($user['name']), $search); ?>
                                        </h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i class="ti ti-edit me-2"></i> Edit
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                                    <i class="ti ti-eye me-2"></i> View Details
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                    <i class="ti ti-trash me-2"></i> Delete
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="ti ti-mail text-muted me-2"></i>
                                            <small class="text-muted text-truncate" title="<?php echo htmlspecialchars($user['email']); ?>">
                                                <?php echo highlightSearch(htmlspecialchars($user['email']), $search); ?>
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="ti ti-phone text-muted me-2"></i>
                                            <small class="text-muted">
                                                <?php echo highlightSearch(htmlspecialchars($user['mobile']), $search); ?>
                                            </small>
                                        </div>
                                        <?php if ($user['institute_name']): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="ti ti-building text-muted me-2"></i>
                                                <small class="text-muted text-truncate" title="<?php echo htmlspecialchars($user['institute_name']); ?>">
                                                    <?php echo highlightSearch(htmlspecialchars($user['institute_name']), $search); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($user['school_name']): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="ti ti-school text-muted me-2"></i>
                                                <small class="text-muted text-truncate">
                                                    <?php echo htmlspecialchars($user['school_name']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Issue Indicators -->
                                    <div class="small">
                                        <?php
                                        $issues = [];
                                        if (preg_match('/^0/', $user['mobile'])) {
                                            $issues[] = '<span class="text-danger">Phone starts with 0</span>';
                                        }
                                        if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                                            $issues[] = '<span class="text-danger">Invalid email</span>';
                                        }
                                        if (empty($user['mobile']) || strlen($user['mobile']) < 10) {
                                            $issues[] = '<span class="text-danger">Invalid phone</span>';
                                        }
                                        if (!empty($issues)): ?>
                                            <div class="alert alert-warning alert-sm py-1 mb-0">
                                                <i class="ti ti-alert-triangle me-1"></i>
                                                <?php echo implode(', ', $issues); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-light border-0 py-2">
                                    <small class="text-muted">
                                        <i class="ti ti-calendar me-1"></i>
                                        Joined: <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <nav aria-label="User pagination">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Start -->
        <?php include 'includes/footer.php'; ?>
        <!-- end Footer -->
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" name="name" id="edit_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile *</label>
                                <input type="text" class="form-control" name="mobile" id="edit_mobile" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Designation</label>
                                <input type="text" class="form-control" name="designation" id="edit_designation">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institute Name</label>
                                <input type="text" class="form-control" name="institute_name" id="edit_institute_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" id="edit_city">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">School</label>
                                <select class="form-select" name="school_id" id="edit_school_id">
                                    <option value="">Select School</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo $school['id']; ?>">
                                            <?php echo htmlspecialchars($school['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete user: <strong id="delete_user_name"></strong>?</p>
                        <p class="text-danger">This action cannot be undone!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
        function editUser(userId) {
            // Fetch user data and populate modal
            fetch(`get_user_data.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_user_id').value = data.id;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_mobile').value = data.mobile;
                    document.getElementById('edit_designation').value = data.designation || '';
                    document.getElementById('edit_institute_name').value = data.institute_name || '';
                    document.getElementById('edit_city').value = data.city || '';
                    document.getElementById('edit_school_id').value = data.school_id || '';
                    
                    new bootstrap.Modal(document.getElementById('editUserModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user data');
                });
        }

        function deleteUser(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }

        function viewUserDetails(userId) {
            window.open(`user_details.php?id=${userId}`, '_blank');
        }
    </script>
</body>
</html>

<?php
function highlightSearch($text, $search) {
    if (empty($search)) return $text;
    return preg_replace('/(' . preg_quote($search, '/') . ')/i', '<span class="search-highlight">$1</span>', $text);
}
?> 