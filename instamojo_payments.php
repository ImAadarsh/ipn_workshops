<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(ip.buyer_name LIKE ? OR ip.buyer_email LIKE ? OR ip.buyer_phone LIKE ? OR ip.payment_id LIKE ? OR ip.link_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sssss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "ip.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(CONVERT_TZ(ip.created_at, '+00:00', '+05:30')) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM instamojo_payments ip $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_count = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
    $total_count = mysqli_fetch_assoc($count_result)['total'];
}

$total_pages = ceil($total_count / $per_page);

// Fetch payments
$payments_sql = "SELECT ip.*, il.link_name, il.workshop_ids,
                 CONVERT_TZ(ip.created_at, '+00:00', '+05:30') as created_at_ist,
                 CONVERT_TZ(ip.updated_at, '+00:00', '+05:30') as updated_at_ist
                 FROM instamojo_payments ip 
                 LEFT JOIN instamojo_links il ON ip.link_id = il.id 
                 $where_clause
                 ORDER BY ip.created_at DESC 
                 LIMIT ? OFFSET ?";

$payments_stmt = mysqli_prepare($conn, $payments_sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    mysqli_stmt_bind_param($payments_stmt, $param_types, ...$params);
} else {
    mysqli_stmt_bind_param($payments_stmt, 'ii', $per_page, $offset);
}

mysqli_stmt_execute($payments_stmt);
$payments_result = mysqli_stmt_get_result($payments_stmt);
$payments = [];
while ($row = mysqli_fetch_assoc($payments_result)) {
    $payments[] = $row;
}
mysqli_stmt_close($payments_stmt);

$page_title = "Payment History";
include 'includes/head.php';
?>

<div class="page-wrapper">
    <?php include 'includes/sidenav.php'; ?>
    
    <div class="page-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="container-fluid">
            <div class="row mt-2 mb-4">
                <div class="col-12">
                    <div class="page-title-box d-flex align-items-center justify-content-between">
                        <h4 class="mb-0">Payment History</h4>
                        <div>
                            <a href="instamojo_dashboard.php" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i> Create New Link
                            </a>
                            <a href="instamojo_links.php" class="btn btn-outline-primary">
                                <i class="ti ti-link me-1"></i> Payment Links
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search by name, email, phone, payment ID, or link name">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date (IST)</label>
                                    <input type="date" class="form-control" name="date" 
                                           value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-search me-1"></i> Filter
                                        </button>
                                        <a href="instamojo_payments.php" class="btn btn-outline-secondary">
                                            <i class="ti ti-refresh me-1"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Summary -->
            <?php if (!empty($search) || !empty($status_filter) || !empty($date_filter)): ?>
            <div class="alert alert-info mb-3">
                <i class="ti ti-info-circle me-1"></i>
                <strong>Filtered Results:</strong> 
                <?php echo $total_count; ?> payment(s) found
                <?php if (!empty($search)): ?> • Search: "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                <?php if (!empty($status_filter)): ?> • Status: <?php echo ucfirst($status_filter); ?><?php endif; ?>
                <?php if (!empty($date_filter)): ?> • Date: <?php echo $date_filter; ?> (IST)<?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Payments Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Payments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payments)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="ti ti-credit-card-off fs-1"></i>
                                    <p class="mt-2">No payments found.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="paymentsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Payment ID</th>
                                                <th>Buyer Information</th>
                                                <th>Link Name</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date (IST)</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <?php 
                                                $workshop_names = !empty($payment['workshop_names']) ? explode('|', $payment['workshop_names']) : [];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <code class="small"><?php echo htmlspecialchars($payment['payment_id']); ?></code>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($payment['buyer_name']); ?></strong><br>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($payment['buyer_email']); ?><br>
                                                                <?php echo htmlspecialchars($payment['buyer_phone']); ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <a href="#" class="text-primary fw-bold" 
                                                               onclick="viewLinkWorkshops(<?php echo $payment['link_id']; ?>, '<?php echo htmlspecialchars($payment['workshop_ids']); ?>')"
                                                               title="View Workshops">
                                                                <?php echo htmlspecialchars($payment['link_name']); ?>
                                                                <i class="ti ti-external-link ms-1"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold fs-5">₹<?php echo number_format($payment['amount'], 2); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php 
                                                            echo $payment['status'] === 'completed' ? 'bg-success' : 
                                                                ($payment['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); 
                                                        ?> fs-6">
                                                            <?php echo ucfirst($payment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <div><?php echo date('d M Y', strtotime($payment['created_at_ist'])); ?></div>
                                                            <small class="text-muted"><?php echo date('h:i A', strtotime($payment['created_at_ist'])); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                                    onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)"
                                                                    title="View Details">
                                                                <i class="ti ti-eye"></i>
                                                            </button>
                                                            <?php if ($payment['status'] === 'completed'): ?>
                                                                <span class="badge bg-success ms-1">
                                                                    <i class="ti ti-check me-1"></i>Enrolled
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center mt-4">
                                    <nav>
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                                                        Previous
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>">
                                                        Next
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Link Workshops Modal -->
<div class="modal fade" id="linkWorkshopsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Workshops in Link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="linkWorkshopsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" type="text/css" href="assets/vendor/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="assets/vendor/datatables.net/js/dataTables.min.js"></script>
<script type="text/javascript" src="assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#paymentsTable').DataTable({
        "pageLength": 20,
        "order": [[5, "desc"]], // Sort by date column (index 5) in descending order
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "responsive": true
    });
});

function viewPaymentDetails(paymentId) {
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    const content = document.getElementById('paymentDetailsContent');
    
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading payment details...</p></div>';
    modal.show();
    
    // Load payment details via AJAX
    fetch(`get_payment_details.php?payment_id=${paymentId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading payment details.</div>';
        });
}

function viewLinkWorkshops(linkId, workshopIds) {
    const modal = new bootstrap.Modal(document.getElementById('linkWorkshopsModal'));
    const content = document.getElementById('linkWorkshopsContent');
    
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading workshops...</p></div>';
    modal.show();
    
    // Load workshop details via AJAX
    fetch(`get_link_workshops.php?workshop_ids=${encodeURIComponent(workshopIds)}&link_id=${linkId}`)
        .then(response => response.text())
        .then(data => {
            content.innerHTML = data;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading workshop details.</div>';
        });
}
</script>

<style>
.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75rem;
}

/* DataTables customization */
.dataTables_wrapper .dataTables_length select {
    min-width: 80px;
}

.dataTables_wrapper .dataTables_filter input {
    min-width: 200px;
}

.dataTables_wrapper .dataTables_info {
    padding-top: 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 5px 10px;
    margin: 0 2px;
    border: 1px solid #dee2e6;
    background-color: #fff;
    color: #007bff;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background-color: #e9ecef;
    border-color: #007bff;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background-color: #007bff;
    border-color: #007bff;
    color: #fff;
}
</style>

</body>
</html> 