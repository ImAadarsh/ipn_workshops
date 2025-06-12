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
    'trainer' => isset($_GET['trainer']) ? $_GET['trainer'] : '',
    'user' => isset($_GET['user']) ? $_GET['user'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'payment_status' => isset($_GET['payment_status']) ? $_GET['payment_status'] : '',
    'amount_min' => isset($_GET['amount_min']) ? $_GET['amount_min'] : '',
    'amount_max' => isset($_GET['amount_max']) ? $_GET['amount_max'] : ''
];

// Build WHERE clause based on filters
$where_conditions = [];

if ($filters['search']) {
    $search = mysqli_real_escape_string($conn, $filters['search']);
    $where_conditions[] = "(u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR t.first_name LIKE '%$search%' OR t.last_name LIKE '%$search%')";
}

if ($filters['status']) {
    $status = mysqli_real_escape_string($conn, $filters['status']);
    $where_conditions[] = "b.status = '$status'";
}

if ($filters['trainer']) {
    $trainer = mysqli_real_escape_string($conn, $filters['trainer']);
    $where_conditions[] = "t.id = '$trainer'";
}

if ($filters['user']) {
    $user = mysqli_real_escape_string($conn, $filters['user']);
    $where_conditions[] = "u.id = '$user'";
}

if ($filters['date_from']) {
    $date_from = mysqli_real_escape_string($conn, $filters['date_from']);
    $where_conditions[] = "ta.date >= '$date_from'";
}

if ($filters['date_to']) {
    $date_to = mysqli_real_escape_string($conn, $filters['date_to']);
    $where_conditions[] = "ta.date <= '$date_to'";
}

if ($filters['payment_status']) {
    $payment_status = mysqli_real_escape_string($conn, $filters['payment_status']);
    $where_conditions[] = "p.status = '$payment_status'";
}

if ($filters['amount_min']) {
    $amount_min = mysqli_real_escape_string($conn, $filters['amount_min']);
    $where_conditions[] = "p.amount >= '$amount_min'";
}

if ($filters['amount_max']) {
    $amount_max = mysqli_real_escape_string($conn, $filters['amount_max']);
    $where_conditions[] = "p.amount <= '$amount_max'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT b.id) as total 
              FROM bookings b 
              LEFT JOIN users u ON b.user_id = u.id 
              LEFT JOIN time_slots ts ON b.time_slot_id = ts.id 
              LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
              LEFT JOIN trainers t ON ta.trainer_id = t.id 
              LEFT JOIN payments p ON b.id = p.booking_id 
              $where_clause";

$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_records / $limit);

// Get bookings with filters and pagination
$sql = "SELECT b.*, 
        u.first_name as user_first_name, u.last_name as user_last_name,
        t.first_name as trainer_first_name, t.last_name as trainer_last_name,
        ta.date, ts.start_time, ts.end_time,
        p.amount, p.status as payment_status
        FROM bookings b 
        LEFT JOIN users u ON b.user_id = u.id 
        LEFT JOIN time_slots ts ON b.time_slot_id = ts.id 
        LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
        LEFT JOIN trainers t ON ta.trainer_id = t.id 
        LEFT JOIN payments p ON b.id = p.booking_id 
        $where_clause
        ORDER BY ta.date DESC, ts.start_time ASC 
        LIMIT $offset, $limit";

$result = mysqli_query($conn, $sql);
$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

// Get distinct values for filters
$sql_trainers = "SELECT DISTINCT t.id, t.first_name, t.last_name 
                 FROM trainers t 
                 JOIN trainer_availabilities ta ON t.id = ta.trainer_id 
                 JOIN time_slots ts ON ta.id = ts.trainer_availability_id 
                 JOIN bookings b ON ts.id = b.time_slot_id 
                 ORDER BY t.first_name";

$sql_users = "SELECT DISTINCT u.id, u.first_name, u.last_name 
              FROM users u 
              JOIN bookings b ON u.id = b.user_id 
              ORDER BY u.first_name";

$trainers = mysqli_query($conn, $sql_trainers);
$users = mysqli_query($conn, $sql_users);

// Booking statuses
$booking_statuses = ['pending', 'pending_reschedule', 'confirmed', 'completed', 'cancelled', 'refunded'];
$payment_statuses = ['pending', 'completed', 'failed', 'refunded'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Bookings | IPN Academy</title>
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
        .status-pending_reschedule { background-color: #fef3c7; color: #9a3412; }
        .status-confirmed { background-color: #dbeafe; color: #1e40af; }
        .status-completed { background-color: #dcfce7; color: #166534; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .status-refunded { background-color: #f3e8ff; color: #6b21a8; }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }
        .booking-time {
            display: inline-block;
            padding: 4px 8px;
            background-color: #f8fafc;
            border-radius: 4px;
            font-size: 12px;
            color: #475569;
        }
        .flatpickr-input {
            background-color: #fff !important;
        }
        .amount-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .amount-range .form-control {
            width: calc(50% - 5px);
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
                                    <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
                                </ol>
                            </div>
                            <h4 class="page-title">Bookings</h4>
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
                                                   placeholder="Search by name..." 
                                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
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
                                            <label class="form-label">User</label>
                                            <select  data-choices data-choices-sorting-false class="form-select" name="user">
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
                                            <label class="form-label">Booking Status</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="status">
                                                <option value="">All Statuses</option>
                                                <?php foreach($booking_statuses as $status): ?>
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
                                            <label class="form-label">Payment Status</label>
                                            <select  data-choices data-choices-sorting-false class="form-select" name="payment_status">
                                                <option value="">All Statuses</option>
                                                <?php foreach($payment_statuses as $status): ?>
                                                    <option value="<?php echo $status; ?>" 
                                                            <?php echo $filters['payment_status'] == $status ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst($status); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                                                <a href="bookings.php" class="btn btn-light">Reset</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bookings Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Manage Bookings</h4>
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
                                <table id="bookings-table" class="table table-centered table-nowrap mb-0">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>User</th>
                                            <th>Trainer</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Payment Status</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td>#<?php echo $booking['id']; ?></td>
                                                <td>
                                                    <?php echo $booking['user_first_name'] . ' ' . $booking['user_last_name']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $booking['trainer_first_name'] . ' ' . $booking['trainer_last_name']; ?>
                                                </td>
                                                <td>
                                                    <div class="booking-time">
                                                        <?php 
                                                        echo date('d M Y', strtotime($booking['date'])) . '<br>' .
                                                             date('h:i A', strtotime($booking['start_time'])) . ' - ' .
                                                             date('h:i A', strtotime($booking['end_time']));
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $booking['payment_status']; ?>">
                                                        <?php echo ucfirst($booking['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>₹<?php echo number_format($booking['amount'], 2); ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <a href="#" class="dropdown-toggle card-drop" 
                                                           data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="ti ti-dots-vertical font-size-18"></i>
                                                        </a>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item" href="view_booking.php?id=<?php echo $booking['id']; ?>">
                                                                <i class="ti ti-eye me-1"></i> View Details
                                                            </a>
                                                            <?php if ($booking['status'] == 'pending'): ?>
                                                                <a class="dropdown-item" href="edit_booking.php?id=<?php echo $booking['id']; ?>">
                                                                    <i class="ti ti-edit me-1"></i> Edit
                                                                </a>
                                                                <a class="dropdown-item text-success confirm-booking" 
                                                                   href="javascript:void(0);" 
                                                                   data-id="<?php echo $booking['id']; ?>">
                                                                    <i class="ti ti-check me-1"></i> Confirm
                                                                </a>
                                                                <a class="dropdown-item text-danger cancel-booking" 
                                                                   href="javascript:void(0);" 
                                                                   data-id="<?php echo $booking['id']; ?>">
                                                                    <i class="ti ti-x me-1"></i> Cancel
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

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

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
            var table = $('#bookings-table').DataTable({
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

            // Booking status updates
            $('.confirm-booking').on('click', function() {
                var bookingId = $(this).data('id');
                updateBookingStatus(bookingId, 'confirmed');
            });

            $('.cancel-booking').on('click', function() {
                var bookingId = $(this).data('id');
                updateBookingStatus(bookingId, 'cancelled');
            });

            function updateBookingStatus(bookingId, status) {
                if (confirm('Are you sure you want to ' + status + ' this booking?')) {
                    $.ajax({
                        url: 'controllers/update_booking_status.php',
                        type: 'POST',
                        data: {
                            booking_id: bookingId,
                            status: status
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Failed to update booking status');
                            }
                        },
                        error: function() {
                            alert('An error occurred while updating the booking');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html> 