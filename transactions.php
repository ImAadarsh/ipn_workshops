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

// Initialize filters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'payment_method' => isset($_GET['payment_method']) ? $_GET['payment_method'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'amount_min' => isset($_GET['amount_min']) ? $_GET['amount_min'] : '',
    'amount_max' => isset($_GET['amount_max']) ? $_GET['amount_max'] : '',
    'user' => isset($_GET['user']) ? $_GET['user'] : '',
    'trainer' => isset($_GET['trainer']) ? $_GET['trainer'] : ''
];

// Build WHERE clause based on filters
$where_conditions = [];

if ($filters['search']) {
    $search = mysqli_real_escape_string($conn, $filters['search']);
    $where_conditions[] = "(u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR 
                          t.first_name LIKE '%$search%' OR t.last_name LIKE '%$search%' OR 
                          p.transaction_id LIKE '%$search%')";
}

if ($filters['status']) {
    $status = mysqli_real_escape_string($conn, $filters['status']);
    $where_conditions[] = "p.status = '$status'";
}

if ($filters['payment_method']) {
    $method = mysqli_real_escape_string($conn, $filters['payment_method']);
    $where_conditions[] = "p.payment_method = '$method'";
}

if ($filters['date_from']) {
    $date_from = mysqli_real_escape_string($conn, $filters['date_from']);
    $where_conditions[] = "DATE(p.created_at) >= '$date_from'";
}

if ($filters['date_to']) {
    $date_to = mysqli_real_escape_string($conn, $filters['date_to']);
    $where_conditions[] = "DATE(p.created_at) <= '$date_to'";
}

if ($filters['amount_min']) {
    $amount_min = mysqli_real_escape_string($conn, $filters['amount_min']);
    $where_conditions[] = "p.amount >= '$amount_min'";
}

if ($filters['amount_max']) {
    $amount_max = mysqli_real_escape_string($conn, $filters['amount_max']);
    $where_conditions[] = "p.amount <= '$amount_max'";
}

if ($filters['user']) {
    $user = mysqli_real_escape_string($conn, $filters['user']);
    $where_conditions[] = "b.user_id = '$user'";
}

if ($filters['trainer']) {
    $trainer = mysqli_real_escape_string($conn, $filters['trainer']);
    $where_conditions[] = "t.id = '$trainer'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT p.id) as total 
              FROM payments p 
              LEFT JOIN bookings b ON p.booking_id = b.id 
              LEFT JOIN users u ON b.user_id = u.id 
              LEFT JOIN time_slots ts ON b.time_slot_id = ts.id 
              LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
              LEFT JOIN trainers t ON ta.trainer_id = t.id 
              $where_clause";

$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_records / $limit);

// Get transactions with filters and pagination
$sql = "SELECT p.*, 
        u.first_name as user_first_name, u.last_name as user_last_name,
        t.first_name as trainer_first_name, t.last_name as trainer_last_name,
        b.id as booking_id
        FROM payments p 
        LEFT JOIN bookings b ON p.booking_id = b.id 
        LEFT JOIN users u ON b.user_id = u.id 
        LEFT JOIN time_slots ts ON b.time_slot_id = ts.id 
        LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
        LEFT JOIN trainers t ON ta.trainer_id = t.id 
        $where_clause
        ORDER BY p.created_at DESC 
        LIMIT $offset, $limit";

$result = mysqli_query($conn, $sql);
$transactions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $transactions[] = $row;
}

// Get distinct values for filters
$sql_users = "SELECT DISTINCT u.id, u.first_name, u.last_name 
              FROM users u 
              JOIN bookings b ON u.id = b.user_id 
              JOIN payments p ON b.id = p.booking_id 
              ORDER BY u.first_name";

$sql_trainers = "SELECT DISTINCT t.id, t.first_name, t.last_name 
                 FROM trainers t 
                 JOIN trainer_availabilities ta ON t.id = ta.trainer_id 
                 JOIN time_slots ts ON ta.id = ts.trainer_availability_id 
                 JOIN bookings b ON ts.id = b.time_slot_id 
                 JOIN payments p ON b.id = p.booking_id 
                 ORDER BY t.first_name";

$users = mysqli_query($conn, $sql_users);
$trainers = mysqli_query($conn, $sql_trainers);

// Payment methods and statuses
$payment_methods = ['credit_card', 'debit_card', 'upi', 'net_banking', 'wallet', 'Admin Scheduled'];
$payment_statuses = ['pending', 'completed', 'failed', 'refunded'];

// Calculate summary statistics
$stats_sql = "SELECT 
    COUNT(*) as total_transactions,
    COALESCE(SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) as pending_amount,
    COALESCE(SUM(CASE WHEN p.status = 'refunded' THEN p.amount ELSE 0 END), 0) as refunded_amount,
    COALESCE(AVG(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END), 0) as avg_transaction
    FROM payments p 
    LEFT JOIN bookings b ON p.booking_id = b.id 
    LEFT JOIN users u ON b.user_id = u.id 
    LEFT JOIN time_slots ts ON b.time_slot_id = ts.id 
    LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
    LEFT JOIN trainers t ON ta.trainer_id = t.id 
    $where_clause";

$stats_result = mysqli_query($conn, $stats_sql);

if (!$stats_result) {
    // Log the error and set default values
    error_log("SQL Error: " . mysqli_error($conn));
    $stats = [
        'total_transactions' => 0,
        'total_revenue' => 0,
        'pending_amount' => 0,
        'refunded_amount' => 0,
        'avg_transaction' => 0
    ];
} else {
    $stats = mysqli_fetch_assoc($stats_result);
    if (!$stats) {
        $stats = [
            'total_transactions' => 0,
            'total_revenue' => 0,
            'pending_amount' => 0,
            'refunded_amount' => 0,
            'avg_transaction' => 0
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Transactions | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        .card.shadow-none {
            box-shadow: none !important;
        }
        .card.shadow-none.border {
            border: 1px solid #e5e9f2 !important;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-label {
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: #6c757d;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-failed { background-color: #fee2e2; color: #991b1b; }
        .status-refunded { background-color: #f3e8ff; color: #6b21a8; }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        .transaction-id {
            font-family: monospace;
            font-size: 13px;
            color: #475569;
        }
        .amount-badge {
            font-weight: 600;
            color: #0f172a;
        }
        .amount-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .amount-range .form-control {
            width: calc(50% - 5px);
        }
        .stats-card {
            background: linear-gradient(to right, #4b38b3, #2c1a7e);
            color: white;
        }
        .stats-card .stats-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .stats-card .stats-label {
            font-size: 13px;
            opacity: 0.9;
        }
    </style>
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
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Transactions</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Transactions</h4>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stats-value">
                                    <?php echo $stats['total_transactions']; ?>
                                </div>
                                <div class="stats-label">Total Transactions</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stats-value">
                                    ₹<?php echo number_format($stats['total_revenue'], 2); ?>
                                </div>
                                <div class="stats-label">Total Revenue</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stats-value">
                                    ₹<?php echo number_format($stats['pending_amount'], 2); ?>
                                </div>
                                <div class="stats-label">Pending Amount</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stats-value">
                                    ₹<?php echo number_format($stats['avg_transaction'], 2); ?>
                                </div>
                                <div class="stats-label">Average Transaction</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card shadow-none border">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Search</label>
                                            <input type="text" class="form-control" name="search" 
                                                   placeholder="Search by ID or name..." 
                                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Status</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="status">
                                                <option value="">All Statuses</option>
                                                <?php foreach($payment_statuses as $status): ?>
                                                    <option value="<?php echo $status; ?>" 
                                                            <?php echo $filters['status'] == $status ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst($status); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Payment Method</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="payment_method">
                                                <option value="">All Methods</option>
                                                <?php foreach($payment_methods as $method): ?>
                                                    <option value="<?php echo $method; ?>" 
                                                            <?php echo $filters['payment_method'] == $method ? 'selected' : ''; ?>>
                                                        <?php echo ucwords(str_replace('_', ' ', $method)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Date Range</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control flatpickr-date" 
                                                       name="date_from" placeholder="From" 
                                                       value="<?php echo $filters['date_from']; ?>">
                                                <input type="text" class="form-control flatpickr-date" 
                                                       name="date_to" placeholder="To" 
                                                       value="<?php echo $filters['date_to']; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Amount Range</label>
                                            <div class="amount-range">
                                                <input type="number" class="form-control" name="amount_min" 
                                                       placeholder="Min" value="<?php echo $filters['amount_min']; ?>">
                                                <input type="number" class="form-control" name="amount_max" 
                                                       placeholder="Max" value="<?php echo $filters['amount_max']; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">User</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="user">
                                                <option value="">All Users</option>
                                                <?php while($user = mysqli_fetch_assoc($users)): ?>
                                                    <option value="<?php echo $user['id']; ?>" 
                                                            <?php echo $filters['user'] == $user['id'] ? 'selected' : ''; ?>>
                                                        <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Trainer</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="trainer">
                                                <option value="">All Trainers</option>
                                                <?php while($trainer = mysqli_fetch_assoc($trainers)): ?>
                                                    <option value="<?php echo $trainer['id']; ?>" 
                                                            <?php echo $filters['trainer'] == $trainer['id'] ? 'selected' : ''; ?>>
                                                        <?php echo $trainer['first_name'] . ' ' . $trainer['last_name']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                                                <a href="transactions.php" class="btn btn-light">Reset</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Transaction History</h4>
                                <div>
                                    <button class="btn btn-primary me-2" id="exportCSV">
                                        <i class="ti ti-file-export me-1"></i> Export CSV
                                    </button>
                                    <button class="btn btn-success" id="exportExcel">
                                        <i class="ti ti-file-spreadsheet me-1"></i> Export Excel
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="transactions-table" class="table table-centered table-nowrap mb-0">
                                    <thead>
                                        <tr>
                                            <th>Transaction ID</th>
                                            <th>User</th>
                                            <th>Trainer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment Method</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <span class="transaction-id">
                                                        <?php echo $transaction['transaction_id']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $transaction['user_first_name'] . ' ' . $transaction['user_last_name']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $transaction['trainer_first_name'] . ' ' . $transaction['trainer_last_name']; ?>
                                                </td>
                                                <td>
                                                    <span class="amount-badge">
                                                        ₹<?php echo number_format($transaction['amount'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                                        <?php echo ucfirst($transaction['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo ucwords(str_replace('_', ' ', $transaction['payment_method'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y, h:i A', strtotime($transaction['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <a href="#" class="dropdown-toggle card-drop" 
                                                           data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="ti ti-dots-vertical font-size-18"></i>
                                                        </a>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item" href="view_transaction.php?id=<?php echo $transaction['id']; ?>">
                                                                <i class="ti ti-eye me-1"></i> View Details
                                                            </a>
                                                            <?php if ($transaction['status'] == 'pending'): ?>
                                                                <a class="dropdown-item text-success update-status" 
                                                                   href="javascript:void(0);" 
                                                                   data-id="<?php echo $transaction['id']; ?>"
                                                                   data-status="completed">
                                                                    <i class="ti ti-check me-1"></i> Mark as Completed
                                                                </a>
                                                                <a class="dropdown-item text-danger update-status" 
                                                                   href="javascript:void(0);" 
                                                                   data-id="<?php echo $transaction['id']; ?>"
                                                                   data-status="failed">
                                                                    <i class="ti ti-x me-1"></i> Mark as Failed
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($transaction['status'] == 'completed'): ?>
                                                                <a class="dropdown-item text-warning update-status" 
                                                                   href="javascript:void(0);" 
                                                                   data-id="<?php echo $transaction['id']; ?>"
                                                                   data-status="refunded">
                                                                    <i class="ti ti-arrow-back-up me-1"></i> Initiate Refund
                                                                </a>
                                                            <?php endif; ?>
                                                           
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        Showing <?php echo ($page - 1) * $limit + 1; ?> to 
                                        <?php echo min($page * $limit, $total_records); ?> of 
                                        <?php echo $total_records; ?> entries
                                    </div>
                                    <ul class="pagination pagination-rounded mb-0">
                                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">
                                                <i class="ti ti-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">
                                                <i class="ti ti-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->
        </div>
    </div>

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>



    <script>
        $(document).ready(function() {
            // Initialize Flatpickr with proper configuration
            $(".flatpickr-date").flatpickr({
                dateFormat: "Y-m-d",
                allowInput: true,
                altInput: true,
                altFormat: "F j, Y",
                defaultDate: null,
                minDate: null,
                maxDate: null
            });

            // Initialize DataTable
            var table = $('#transactions-table').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: 'Export CSV',
                        className: 'btn btn-primary d-none',
                        exportOptions: {
                            columns: ':not(:last-child)'
                        }
                    },
                    {
                        extend: 'excel',
                        text: 'Export Excel',
                        className: 'btn btn-success d-none',
                        exportOptions: {
                            columns: ':not(:last-child)'
                        }
                    }
                ],
                searching: false,
                ordering: true,
                paging: false,
                info: false
            });

            // Custom export buttons
            $('#exportCSV').on('click', function() {
                table.button('.buttons-csv').trigger();
            });

            $('#exportExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            // Transaction status updates
            $('.update-status').on('click', function() {
                var transactionId = $(this).data('id');
                var newStatus = $(this).data('status');
                updateTransactionStatus(transactionId, newStatus);
            });

            function updateTransactionStatus(transactionId, status) {
                if (confirm('Are you sure you want to mark this transaction as ' + status + '?')) {
                    $.ajax({
                        url: 'controllers/update_transaction_status.php',
                        type: 'POST',
                        data: {
                            transaction_id: transactionId,
                            status: status
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Failed to update transaction status');
                            }
                        },
                        error: function() {
                            alert('An error occurred while updating the transaction');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html> 