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

// Get search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_issue = isset($_GET['filter_issue']) ? $_GET['filter_issue'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // Reduced from 20 to 12
$offset = ($page - 1) * $per_page;

// Build the query for bad users
$where_conditions = [];
$params = [];
$param_types = "";

// Base conditions for bad users
$base_conditions = [
    "u.mobile LIKE '0%'", // Phone starts with 0
    "u.mobile = '' OR u.mobile IS NULL", // Empty phone
    "LENGTH(u.mobile) < 10", // Phone too short
    "u.email NOT LIKE '%@%'", // Invalid email format
    "u.email = '' OR u.email IS NULL", // Empty email
    "u.name = '' OR u.name IS NULL", // Empty name
    "u.name LIKE '%test%' OR u.name LIKE '%demo%'", // Test/demo names
    "u.email LIKE '%test%' OR u.email LIKE '%demo%'", // Test/demo emails
    "u.mobile LIKE '%0000%' OR u.mobile LIKE '%1111%' OR u.mobile LIKE '%9999%'" // Suspicious patterns
];

if (!empty($filter_issue)) {
    switch ($filter_issue) {
        case 'phone_zero':
            $where_conditions[] = "u.mobile LIKE '0%'";
            break;
        case 'phone_empty':
            $where_conditions[] = "(u.mobile = '' OR u.mobile IS NULL)";
            break;
        case 'phone_short':
            $where_conditions[] = "LENGTH(u.mobile) < 10";
            break;
        case 'email_invalid':
            $where_conditions[] = "u.email NOT LIKE '%@%'";
            break;
        case 'email_empty':
            $where_conditions[] = "(u.email = '' OR u.email IS NULL)";
            break;
        case 'name_empty':
            $where_conditions[] = "(u.name = '' OR u.name IS NULL)";
            break;
        case 'test_data':
            $where_conditions[] = "(u.name LIKE '%test%' OR u.name LIKE '%demo%' OR u.email LIKE '%test%' OR u.email LIKE '%demo%')";
            break;
        case 'suspicious_patterns':
            $where_conditions[] = "(u.mobile LIKE '%0000%' OR u.mobile LIKE '%1111%' OR u.mobile LIKE '%9999%')";
            break;
    }
} else {
    // Show all bad users
    $where_conditions = ["(" . implode(" OR ", $base_conditions) . ")"];
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.institute_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
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

// Get bad users with pagination
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

// Get statistics
$stats_sql = "SELECT 
    COUNT(CASE WHEN mobile LIKE '0%' THEN 1 END) as phone_zero,
    COUNT(CASE WHEN mobile = '' OR mobile IS NULL THEN 1 END) as phone_empty,
    COUNT(CASE WHEN LENGTH(mobile) < 10 THEN 1 END) as phone_short,
    COUNT(CASE WHEN email NOT LIKE '%@%' THEN 1 END) as email_invalid,
    COUNT(CASE WHEN email = '' OR email IS NULL THEN 1 END) as email_empty,
    COUNT(CASE WHEN name = '' OR name IS NULL THEN 1 END) as name_empty,
    COUNT(CASE WHEN name LIKE '%test%' OR name LIKE '%demo%' OR email LIKE '%test%' OR email LIKE '%demo%' THEN 1 END) as test_data,
    COUNT(CASE WHEN mobile LIKE '%0000%' OR mobile LIKE '%1111%' OR mobile LIKE '%9999%' THEN 1 END) as suspicious_patterns
FROM users";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Bad Users Detection | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .issue-card {
            transition: all 0.3s ease;
            border-radius: 12px;
        }
        .issue-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        .issue-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .stat-card {
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important;
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
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Bad Users Detection</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="bulk_fix_mobile.php" class="btn btn-warning me-2">
                                    <i class="ti ti-tools me-1"></i> Bulk Fix Mobile
                                </a>
                                <a href="user_management.php" class="btn btn-primary me-2">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Users
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="ti ti-home me-1"></i> Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Issue Statistics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-danger mb-3">
                            <i class="ti ti-alert-triangle me-2"></i>Data Quality Issues Summary
                        </h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-danger-subtle border border-danger rounded-3 p-3 text-center">
                                    <div class="stat-number text-danger fw-bold fs-4"><?php echo $stats['phone_zero']; ?></div>
                                    <div class="stat-label text-muted small">Phone Starts with 0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-warning-subtle border border-warning rounded-3 p-3 text-center">
                                    <div class="stat-number text-warning fw-bold fs-4"><?php echo $stats['phone_empty']; ?></div>
                                    <div class="stat-label text-muted small">Empty Phone</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-info-subtle border border-info rounded-3 p-3 text-center">
                                    <div class="stat-number text-info fw-bold fs-4"><?php echo $stats['phone_short']; ?></div>
                                    <div class="stat-label text-muted small">Short Phone</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-secondary-subtle border border-secondary rounded-3 p-3 text-center">
                                    <div class="stat-number text-secondary fw-bold fs-4"><?php echo $stats['email_invalid']; ?></div>
                                    <div class="stat-label text-muted small">Invalid Email</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-primary-subtle border border-primary rounded-3 p-3 text-center">
                                    <div class="stat-number text-primary fw-bold fs-4"><?php echo $stats['email_empty']; ?></div>
                                    <div class="stat-label text-muted small">Empty Email</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-success-subtle border border-success rounded-3 p-3 text-center">
                                    <div class="stat-number text-success fw-bold fs-4"><?php echo $stats['name_empty']; ?></div>
                                    <div class="stat-label text-muted small">Empty Name</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-dark-subtle border border-dark rounded-3 p-3 text-center">
                                    <div class="stat-number text-dark fw-bold fs-4"><?php echo $stats['test_data']; ?></div>
                                    <div class="stat-label text-muted small">Test Data</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card bg-danger-subtle border border-danger rounded-3 p-3 text-center">
                                    <div class="stat-number text-danger fw-bold fs-4"><?php echo $stats['suspicious_patterns']; ?></div>
                                    <div class="stat-label text-muted small">Suspicious Patterns</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-gradient-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-search me-2"></i>Search & Filter Issues
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Name, Email, Mobile, Institute...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Issue Type</label>
                                <select class="form-select" name="filter_issue">
                                    <option value="">All Issues</option>
                                    <option value="phone_zero" <?php echo $filter_issue === 'phone_zero' ? 'selected' : ''; ?>>Phone Starts with 0</option>
                                    <option value="phone_empty" <?php echo $filter_issue === 'phone_empty' ? 'selected' : ''; ?>>Empty Phone</option>
                                    <option value="phone_short" <?php echo $filter_issue === 'phone_short' ? 'selected' : ''; ?>>Short Phone</option>
                                    <option value="email_invalid" <?php echo $filter_issue === 'email_invalid' ? 'selected' : ''; ?>>Invalid Email</option>
                                    <option value="email_empty" <?php echo $filter_issue === 'email_empty' ? 'selected' : ''; ?>>Empty Email</option>
                                    <option value="name_empty" <?php echo $filter_issue === 'name_empty' ? 'selected' : ''; ?>>Empty Name</option>
                                    <option value="test_data" <?php echo $filter_issue === 'test_data' ? 'selected' : ''; ?>>Test Data</option>
                                    <option value="suspicious_patterns" <?php echo $filter_issue === 'suspicious_patterns' ? 'selected' : ''; ?>>Suspicious Patterns</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="ti ti-search me-1"></i> Search
                                    </button>
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
                                <small class="text-muted">
                                    Found <?php echo number_format($total_users); ?> users with data quality issues
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bad Users Grid -->
                <div class="row g-4">
                    <?php while ($user = mysqli_fetch_assoc($result)): ?>
                        <?php
                        // Detect issues for this user
                        $issues = [];
                        if (preg_match('/^0/', $user['mobile'])) {
                            $issues[] = ['type' => 'phone_zero', 'text' => 'Phone starts with 0', 'class' => 'danger'];
                        }
                        if (empty($user['mobile'])) {
                            $issues[] = ['type' => 'phone_empty', 'text' => 'Empty phone number', 'class' => 'warning'];
                        }
                        if (strlen($user['mobile']) < 10) {
                            $issues[] = ['type' => 'phone_short', 'text' => 'Phone too short', 'class' => 'info'];
                        }
                        if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                            $issues[] = ['type' => 'email_invalid', 'text' => 'Invalid email format', 'class' => 'secondary'];
                        }
                        if (empty($user['email'])) {
                            $issues[] = ['type' => 'email_empty', 'text' => 'Empty email', 'class' => 'primary'];
                        }
                        if (empty($user['name'])) {
                            $issues[] = ['type' => 'name_empty', 'text' => 'Empty name', 'class' => 'success'];
                        }
                        if (stripos($user['name'], 'test') !== false || stripos($user['name'], 'demo') !== false || 
                            stripos($user['email'], 'test') !== false || stripos($user['email'], 'demo') !== false) {
                            $issues[] = ['type' => 'test_data', 'text' => 'Test/Demo data', 'class' => 'dark'];
                        }
                        if (preg_match('/(0000|1111|9999)/', $user['mobile'])) {
                            $issues[] = ['type' => 'suspicious_patterns', 'text' => 'Suspicious pattern', 'class' => 'danger'];
                        }
                        ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card issue-card border-0 shadow-sm h-100">
                                <div class="card-header bg-light border-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold text-danger mb-0">
                                            <?php echo htmlspecialchars($user['name'] ?: 'No Name'); ?>
                                        </h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-danger dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i class="ti ti-edit me-2"></i> Fix User
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
                                            <small class="text-muted text-truncate">
                                                <?php echo htmlspecialchars($user['email'] ?: 'No Email'); ?>
                                            </small>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="ti ti-phone text-muted me-2"></i>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($user['mobile'] ?: 'No Phone'); ?>
                                            </small>
                                        </div>
                                        <?php if ($user['institute_name']): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="ti ti-building text-muted me-2"></i>
                                                <small class="text-muted text-truncate">
                                                    <?php echo htmlspecialchars($user['institute_name']); ?>
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
                                    
                                    <!-- Issue Badges -->
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <?php foreach ($issues as $issue): ?>
                                            <span class="badge bg-<?php echo $issue['class']; ?> issue-badge">
                                                <?php echo $issue['text']; ?>
                                            </span>
                                        <?php endforeach; ?>
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
                            <nav aria-label="Bad users pagination">
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

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
        function editUser(userId) {
            window.open(`user_management.php?edit=${userId}`, '_blank');
        }

        function viewUserDetails(userId) {
            window.open(`user_details.php?id=${userId}`, '_blank');
        }

        function deleteUser(userId, userName) {
            if (confirm(`Are you sure you want to delete user: ${userName}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'user_management.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                form.appendChild(actionInput);
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html> 