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

// Get user type and name from session
$userType = $_SESSION['user_type'];
$userName = $_SESSION['user_name'];
$userId = $_SESSION['user_id'];

// Get workshop ID from URL
$workshop_id = isset($_GET['workshop']) ? intval($_GET['workshop']) : 0;
if (!$workshop_id) {
    header("Location: dashboard.php");
    exit();
}

// Get current page and tab from URL
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'b2b';
$limit = 10;

// Get filters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'mail_status' => isset($_GET['mail_status']) ? $_GET['mail_status'] : ''
];

// Get workshop details
$workshop_sql = "SELECT name FROM workshops WHERE id = ? AND is_deleted = 0";
$stmt = mysqli_prepare($conn, $workshop_sql);
mysqli_stmt_bind_param($stmt, "i", $workshop_id);
mysqli_stmt_execute($stmt);
$workshop_result = mysqli_stmt_get_result($stmt);
$workshop = mysqli_fetch_assoc($workshop_result);

if (!$workshop) {
    header("Location: dashboard.php");
    exit();
}

// Function to get users based on type and filters
function getUsers($conn, $type, $workshop_id, $page, $limit, $filters) {
    $offset = ($page - 1) * $limit;
    $where = [];
    $params = [];
    $types = '';
    
    // Base query
    if ($type === 'b2b') {
        $base_query = "SELECT DISTINCT u.*, p.workshop_id, p.payment_status, p.mail_send, p.instamojo_upload, w.name as workshop_name 
                      FROM users u 
                      INNER JOIN payments p ON u.id = p.user_id 
                      INNER JOIN workshops w ON p.workshop_id = w.id 
                      WHERE p.school_id IS NOT NULL AND p.workshop_id = ?";
        $params[] = $workshop_id;
        $types .= 'i';
    } elseif ($type === 'b2c') {
        $base_query = "SELECT DISTINCT u.*, p.workshop_id, p.payment_status, p.mail_send, p.instamojo_upload, w.name as workshop_name 
                      FROM users u 
                      INNER JOIN payments p ON u.id = p.user_id 
                      INNER JOIN workshops w ON p.workshop_id = w.id 
                      WHERE p.school_id IS NULL AND p.workshop_id = ?";
        $params[] = $workshop_id;
        $types .= 'i';
    } else { // discarded
        $base_query = "SELECT * FROM discarded_entries WHERE workshop_id = ?";
        $params[] = $workshop_id;
        $types .= 'i';
    }
    
    // Add filters
    if (!empty($filters['search'])) {
        if ($type === 'discarded') {
            $where[] = "(name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $types .= 'sss';
        } else {
            $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $types .= 'sss';
        }
    }
    
    if (!empty($filters['mail_status']) && $type !== 'discarded') {
        $where[] = "p.mail_send = ?";
        $params[] = $filters['mail_status'];
        $types .= 'i';
    }
    
    // Add where clause if filters exist
    if (!empty($where)) {
        $base_query .= " AND " . implode(" AND ", $where);
    }
    
    // Get total count
    if ($type === 'discarded') {
        $count_query = "SELECT COUNT(*) as total FROM discarded_entries WHERE workshop_id = ?";
        if (!empty($where)) {
            $count_query .= " AND " . implode(" AND ", $where);
        }
    } else {
        $count_query = str_replace("DISTINCT u.*, p.workshop_id, p.payment_status, p.mail_send, p.instamojo_upload, w.name as workshop_name", "COUNT(DISTINCT u.id) as total", $base_query);
    }
    
    $stmt = mysqli_prepare($conn, $count_query);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $total_result = mysqli_stmt_get_result($stmt);
        $total_row = mysqli_fetch_assoc($total_result);
        $total = $total_row['total'];
    } else {
        $total = 0;
    }
    
    // Add pagination
    $base_query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Get paginated results
    $stmt = mysqli_prepare($conn, $base_query);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
    } else {
        $users = [];
    }
    
    return [
        'users' => $users,
        'total' => $total,
        'total_pages' => ceil($total / $limit)
    ];
}

// Get users based on current tab
$result = getUsers($conn, $tab, $workshop_id, $page, $limit, $filters);
$users = $result['users'];
$totalPages = $result['total_pages'];
$totalRecords = $result['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Registered Users | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #0ab39c;
            font-weight: 600;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.75em;
        }
        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="workshop-details.php?id=<?php echo $workshop_id; ?>">Workshop Details</a></li>
                                    <li class="breadcrumb-item active">Registered Users</li>
                                </ol>
                            </div>
                            <h4 class="page-title"><?php echo htmlspecialchars($workshop['name']); ?> - Registered Users</h4>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-tabs nav-bordered">
                                    <li class="nav-item">
                                        <a href="?tab=b2b&workshop=<?php echo $workshop_id; ?>" class="nav-link <?php echo $tab === 'b2b' ? 'active' : ''; ?>">
                                            B2B Users
                                            <span class="badge bg-primary ms-1"><?php echo $totalRecords; ?></span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="?tab=b2c&workshop=<?php echo $workshop_id; ?>" class="nav-link <?php echo $tab === 'b2c' ? 'active' : ''; ?>">
                                            B2C Users
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="?tab=discarded&workshop=<?php echo $workshop_id; ?>" class="nav-link <?php echo $tab === 'discarded' ? 'active' : ''; ?>">
                                            Discarded Entries
                                        </a>
                                    </li>
                                </ul>

                                <!-- Filters -->
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <form method="GET" class="row g-3">
                                            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                                            <input type="hidden" name="workshop" value="<?php echo $workshop_id; ?>">
                                            <div class="col-md-4">
                                                <input type="text" class="form-control" name="search" 
                                                       placeholder="Search by name, email or mobile" 
                                                       value="<?php echo htmlspecialchars($filters['search']); ?>">
                                            </div>
                                            <?php if ($tab !== 'discarded'): ?>
                                            <div class="col-md-3">
                                                <select class="form-select" name="mail_status">
                                                    <option value="">All Mail Status</option>
                                                    <option value="1" <?php echo $filters['mail_status'] === '1' ? 'selected' : ''; ?>>Mail Sent</option>
                                                    <option value="0" <?php echo $filters['mail_status'] === '0' ? 'selected' : ''; ?>>Mail Not Sent</option>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Users Table -->
                                <div class="table-responsive mt-3">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>User Details</th>
                                                <?php if ($tab !== 'discarded'): ?>
                                                <th>Payment Status</th>
                                                <th>Mail Status</th>
                                                <th>Instamojo Upload</th>
                                                <?php else: ?>
                                                <th>Reason</th>
                                                <th>Original Data</th>
                                                <?php endif; ?>
                                                <th>Registration Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                <?php 
                                                                $name = $tab === 'discarded' ? $user['name'] : $user['name'];
                                                                $initials = strtoupper(substr($name, 0, 2));
                                                                echo $initials;
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-1"><?php echo $name; ?></h5>
                                                            <p class="mb-0 text-muted">
                                                                <?php if ($tab === 'discarded'): ?>
                                                                    <?php echo $user['email']; ?><br>
                                                                    <?php echo $user['mobile']; ?>
                                                                <?php else: ?>
                                                                    <?php echo $user['email']; ?><br>
                                                                    <?php echo $user['mobile']; ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php if ($tab !== 'discarded'): ?>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['payment_status'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $user['payment_status'] ? 'Paid' : 'Pending'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['mail_send'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $user['mail_send'] ? 'Sent' : 'Not Sent'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['instamojo_upload'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $user['instamojo_upload'] ? 'Uploaded' : 'Not Uploaded'; ?>
                                                    </span>
                                                </td>
                                                <?php else: ?>
                                                <td><?php echo $user['reason']; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#dataModal" 
                                                            data-csv='<?php echo htmlspecialchars($user['csv_data']); ?>'>
                                                        View Data
                                                    </button>
                                                </td>
                                                <?php endif; ?>
                                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($tab !== 'discarded'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary edit-user" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editUserModal"
                                                            data-user='<?php echo htmlspecialchars(json_encode($user)); ?>'>
                                                        <i class="ti ti-edit"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <nav>
                                            <ul class="pagination justify-content-center">
                                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&tab=<?php echo $tab; ?>&workshop=<?php echo $workshop_id; ?>&<?php echo http_build_query($filters); ?>">
                                                        Previous
                                                    </a>
                                                </li>
                                                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&tab=<?php echo $tab; ?>&workshop=<?php echo $workshop_id; ?>&<?php echo http_build_query($filters); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                                <?php endfor; ?>
                                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&tab=<?php echo $tab; ?>&workshop=<?php echo $workshop_id; ?>&<?php echo http_build_query($filters); ?>">
                                                        Next
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Modal -->
        <div class="modal fade" id="dataModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">CSV Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <pre id="csvData" class="bg-light p-3"></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" id="editUserId" name="user_id">
                            <input type="hidden" id="editWorkshopId" name="workshop_id" value="<?php echo $workshop_id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Mobile</label>
                                <input type="text" class="form-control" id="editMobile" name="mobile" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Status</label>
                                <select class="form-select" id="editPaymentStatus" name="payment_status">
                                    <option value="0">Pending</option>
                                    <option value="1">Paid</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Mail Status</label>
                                <select class="form-select" id="editMailStatus" name="mail_send">
                                    <option value="0">Not Sent</option>
                                    <option value="1">Sent</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveUserChanges">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
        // Handle CSV data modal
        $('#dataModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var csvData = button.data('csv');
            var modal = $(this);
            modal.find('#csvData').text(JSON.stringify(JSON.parse(csvData), null, 2));
        });

        // Handle edit user modal
        $('.edit-user').on('click', function() {
            var userData = $(this).data('user');
            $('#editUserId').val(userData.id);
            $('#editName').val(userData.name);
            $('#editEmail').val(userData.email);
            $('#editMobile').val(userData.mobile);
            $('#editPaymentStatus').val(userData.payment_status);
            $('#editMailStatus').val(userData.mail_send);
        });

        // Handle save changes
        $('#saveUserChanges').on('click', function() {
            var formData = $('#editUserForm').serialize();
            
            $.ajax({
                url: 'controllers/update_user.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error updating user: ' + response.message);
                    }
                },
                error: function() {
                    alert('An error occurred while updating the user');
                }
            });
        });
    </script>
</body>
</html> 