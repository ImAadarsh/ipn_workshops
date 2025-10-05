<?php
session_start();
require_once 'config/config.php';

// Check admin authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Pagination settings
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "pcr.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR pcr.email LIKE ? OR pcr.mobile LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total 
              FROM profile_correction_requests pcr 
              LEFT JOIN users u ON pcr.user_id = u.id 
              $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, str_repeat('s', count($params)), ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_records / $per_page);

// Get requests
$sql = "SELECT pcr.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile, u.institute_name as user_institute
        FROM profile_correction_requests pcr 
        LEFT JOIN users u ON pcr.user_id = u.id 
        $where_clause 
        ORDER BY pcr.$sort_by $sort_order 
        LIMIT $per_page OFFSET $offset";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($params)), ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$requests = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_requests,
    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_requests
    FROM profile_correction_requests";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $request_ids = $_POST['request_ids'] ?? [];
    
    if (!empty($request_ids) && in_array($action, ['approve', 'reject'])) {
        $ids_str = implode(',', array_map('intval', $request_ids));
        
        if ($action === 'approve') {
            // Update status to approved
            $update_sql = "UPDATE profile_correction_requests 
                          SET status = 'approved', 
                              processed_by = $admin_id, 
                              processed_at = NOW(),
                              admin_notes = CONCAT(IFNULL(admin_notes, ''), '\nBulk approved by $admin_name on ', NOW())
                          WHERE id IN ($ids_str)";
            mysqli_query($conn, $update_sql);
            
            // Update user profiles
            $user_update_sql = "UPDATE users u 
                               INNER JOIN profile_correction_requests pcr ON u.id = pcr.user_id 
                               SET u.name = pcr.name, 
                                   u.email = pcr.email, 
                                   u.mobile = pcr.mobile, 
                                   u.institute_name = pcr.institute_name,
                                   u.updated_at = NOW()
                               WHERE pcr.id IN ($ids_str)";
            mysqli_query($conn, $user_update_sql);
            
            $_SESSION['success_message'] = "Successfully approved " . count($request_ids) . " requests.";
        } else {
            // Update status to rejected
            $update_sql = "UPDATE profile_correction_requests 
                          SET status = 'rejected', 
                              processed_by = $admin_id, 
                              processed_at = NOW(),
                              admin_notes = CONCAT(IFNULL(admin_notes, ''), '\nBulk rejected by $admin_name on ', NOW())
                          WHERE id IN ($ids_str)";
            mysqli_query($conn, $update_sql);
            
            $_SESSION['success_message'] = "Successfully rejected " . count($request_ids) . " requests.";
        }
        
        header("Location: admin_profile_corrections.php");
        exit();
    }
}

// Handle individual actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($action === 'approve') {
        // Get request details
        $req_sql = "SELECT * FROM profile_correction_requests WHERE id = $request_id";
        $req_result = mysqli_query($conn, $req_sql);
        $request = mysqli_fetch_assoc($req_result);
        
        if ($request) {
            // Update user profile
            $user_update_sql = "UPDATE users SET 
                               name = '" . mysqli_real_escape_string($conn, $request['name']) . "',
                               email = '" . mysqli_real_escape_string($conn, $request['email']) . "',
                               mobile = '" . mysqli_real_escape_string($conn, $request['mobile']) . "',
                               institute_name = '" . mysqli_real_escape_string($conn, $request['institute_name']) . "',
                               updated_at = NOW()
                               WHERE id = " . $request['user_id'];
            mysqli_query($conn, $user_update_sql);
            
            // Update request status
            $update_sql = "UPDATE profile_correction_requests SET 
                          status = 'approved', 
                          processed_by = $admin_id, 
                          processed_at = NOW(),
                          admin_notes = '" . mysqli_real_escape_string($conn, $admin_notes) . "'
                          WHERE id = $request_id";
            mysqli_query($conn, $update_sql);
            
            $_SESSION['success_message'] = "Request approved successfully.";
        }
    } elseif ($action === 'reject') {
        $update_sql = "UPDATE profile_correction_requests SET 
                      status = 'rejected', 
                      processed_by = $admin_id, 
                      processed_at = NOW(),
                      admin_notes = '" . mysqli_real_escape_string($conn, $admin_notes) . "'
                      WHERE id = $request_id";
        mysqli_query($conn, $update_sql);
        
        $_SESSION['success_message'] = "Request rejected successfully.";
    }
    
    header("Location: admin_profile_corrections.php");
    exit();
}

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex, nofollow">
    <title>Profile Correction Requests | IPN Academy Admin</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .comparison-table {
            font-size: 0.9rem;
        }
        .comparison-table .changed {
            background-color: #fff3cd;
            font-weight: bold;
        }
        .comparison-table .unchanged {
            background-color: #f8f9fa;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .request-row {
            cursor: pointer;
        }
        .request-row:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'includes/topbar.php'; ?>
    <?php include 'includes/sidenav.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <h4 class="page-title">Profile Correction Requests</h4>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1">Total Requests</p>
                                    <h4 class="mb-0"><?php echo $stats['total_requests']; ?></h4>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="ti ti-file-text text-primary" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1">Pending</p>
                                    <h4 class="mb-0 text-warning"><?php echo $stats['pending_requests']; ?></h4>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="ti ti-clock text-warning" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1">Approved</p>
                                    <h4 class="mb-0 text-success"><?php echo $stats['approved_requests']; ?></h4>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="ti ti-check text-success" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1">Rejected</p>
                                    <h4 class="mb-0 text-danger"><?php echo $stats['rejected_requests']; ?></h4>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="ti ti-x text-danger" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1">Today</p>
                                    <h4 class="mb-0 text-info"><?php echo $stats['today_requests']; ?></h4>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="ti ti-calendar text-info" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="text-muted mb-1">This Week</p>
                                    <h4 class="mb-0 text-secondary"><?php echo $stats['week_requests']; ?></h4>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="ti ti-calendar-week text-secondary" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, email, or mobile" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date</option>
                                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order</label>
                            <select name="order" class="form-select">
                                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="admin_profile_corrections.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Correction Requests</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" onclick="bulkAction('approve')">
                            <i class="ti ti-check me-1"></i>Bulk Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="bulkAction('reject')">
                            <i class="ti ti-x me-1"></i>Bulk Reject
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="bulkForm" method="POST">
                        <input type="hidden" name="bulk_action" id="bulkAction">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Requested Changes</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($requests)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="ti ti-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                                <p class="text-muted mt-2">No correction requests found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($requests as $request): ?>
                                            <tr class="request-row" data-request-id="<?php echo $request['id']; ?>">
                                                <td>
                                                    <input type="checkbox" name="request_ids[]" value="<?php echo $request['id']; ?>" class="form-check-input request-checkbox">
                                                </td>
                                                <td><?php echo $request['id']; ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($request['user_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($request['user_email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showChanges(<?php echo $request['id']; ?>)">
                                                        <i class="ti ti-eye me-1"></i>View Changes
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'pending' => 'warning',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger'
                                                    ][$request['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?> status-badge">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" onclick="viewRequest(<?php echo $request['id']; ?>)">
                                                            <i class="ti ti-eye"></i>
                                                        </button>
                                                        <?php if ($request['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-outline-success" onclick="approveRequest(<?php echo $request['id']; ?>)">
                                                                <i class="ti ti-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger" onclick="rejectRequest(<?php echo $request['id']; ?>)">
                                                                <i class="ti ti-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Changes Comparison Modal -->
    <div class="modal fade" id="changesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Requested Changes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="changesModalBody">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Approve/Reject Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="actionForm">
                    <input type="hidden" name="action" id="actionType">
                    <input type="hidden" name="request_id" id="actionRequestId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add notes about this action..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="actionSubmitBtn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.request-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk actions
        function bulkAction(action) {
            const checkboxes = document.querySelectorAll('.request-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one request.');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${checkboxes.length} request(s)?`)) {
                document.getElementById('bulkAction').value = action;
                document.getElementById('bulkForm').submit();
            }
        }

        // View request details
        function viewRequest(requestId) {
            fetch(`admin_profile_correction_details.php?id=${requestId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('requestModalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('requestModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading request details.');
                });
        }

        // Show changes comparison
        function showChanges(requestId) {
            fetch(`admin_profile_correction_details.php?id=${requestId}&action=changes`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('changesModalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('changesModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading changes.');
                });
        }

        // Approve request
        function approveRequest(requestId) {
            document.getElementById('actionModalTitle').textContent = 'Approve Request';
            document.getElementById('actionType').value = 'approve';
            document.getElementById('actionRequestId').value = requestId;
            document.getElementById('actionSubmitBtn').textContent = 'Approve';
            document.getElementById('actionSubmitBtn').className = 'btn btn-success';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }

        // Reject request
        function rejectRequest(requestId) {
            document.getElementById('actionModalTitle').textContent = 'Reject Request';
            document.getElementById('actionType').value = 'reject';
            document.getElementById('actionRequestId').value = requestId;
            document.getElementById('actionSubmitBtn').textContent = 'Reject';
            document.getElementById('actionSubmitBtn').className = 'btn btn-danger';
            new bootstrap.Modal(document.getElementById('actionModal')).show();
        }
    </script>
</body>
</html>
