<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Initialize filters
$filters = [
    'report_type' => $_GET['report_type'] ?? 'all',
    'trainer_id' => $_GET['trainer_id'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Get all trainers for filter dropdown
$trainers_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM trainers ORDER BY first_name";
$trainers_result = mysqli_query($conn, $trainers_query);
$trainers = mysqli_fetch_all($trainers_result, MYSQLI_ASSOC);

// Get all users for filter dropdown
$users_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE user_type = 'user' ORDER BY first_name";
$users_result = mysqli_query($conn, $users_query);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);

// Calculate summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_sessions,
    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_sessions,
    AVG(tr.rating) as avg_rating,
    SUM(p.amount) as total_revenue,
    COUNT(DISTINCT b.user_id) as unique_users,
    COUNT(DISTINCT ta.trainer_id) as active_trainers
FROM bookings b
LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
LEFT JOIN payments p ON b.id = p.booking_id
LEFT JOIN trainer_reviews tr ON b.id = tr.booking_id
WHERE 1=1";

// Apply the same filters to stats
if ($filters['trainer_id']) {
    $stats_query .= " AND ta.trainer_id = " . mysqli_real_escape_string($conn, $filters['trainer_id']);
}
if ($filters['user_id']) {
    $stats_query .= " AND b.user_id = " . mysqli_real_escape_string($conn, $filters['user_id']);
}
if ($filters['date_from']) {
    $stats_query .= " AND ta.date >= '" . mysqli_real_escape_string($conn, $filters['date_from']) . "'";
}
if ($filters['date_to']) {
    $stats_query .= " AND ta.date <= '" . mysqli_real_escape_string($conn, $filters['date_to']) . "'";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get monthly trends
$trends_query = "SELECT 
    DATE_FORMAT(ta.date, '%Y-%m') as month,
    COUNT(*) as total_bookings,
    SUM(p.amount) as revenue,
    AVG(tr.rating) as avg_rating
FROM bookings b
LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
LEFT JOIN payments p ON b.id = p.booking_id
LEFT JOIN trainer_reviews tr ON b.id = tr.booking_id
LEFT JOIN trainers t ON ta.trainer_id = t.id
LEFT JOIN users u ON b.user_id = u.id
WHERE ta.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";

// Apply the same filters to trends query
if ($filters['trainer_id']) {
    $trends_query .= " AND ta.trainer_id = " . mysqli_real_escape_string($conn, $filters['trainer_id']);
}
if ($filters['user_id']) {
    $trends_query .= " AND b.user_id = " . mysqli_real_escape_string($conn, $filters['user_id']);
}
if ($filters['date_from']) {
    $trends_query .= " AND ta.date >= '" . mysqli_real_escape_string($conn, $filters['date_from']) . "'";
}
if ($filters['date_to']) {
    $trends_query .= " AND ta.date <= '" . mysqli_real_escape_string($conn, $filters['date_to']) . "'";
}
if ($filters['status']) {
    $trends_query .= " AND b.status = '" . mysqli_real_escape_string($conn, $filters['status']) . "'";
}

$trends_query .= " GROUP BY DATE_FORMAT(ta.date, '%Y-%m')
ORDER BY month ASC";

$trends_result = mysqli_query($conn, $trends_query);
$trends = mysqli_fetch_all($trends_result, MYSQLI_ASSOC);

// Get top performing trainers
$top_trainers_query = "SELECT 
    t.first_name,
    t.last_name,
    COUNT(b.id) as total_sessions,
    AVG(tr.rating) as avg_rating,
    SUM(p.amount) as revenue
FROM trainers t
LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id
LEFT JOIN time_slots ts ON ta.id = ts.trainer_availability_id
LEFT JOIN bookings b ON ts.id = b.time_slot_id
LEFT JOIN trainer_reviews tr ON b.id = tr.booking_id
LEFT JOIN payments p ON b.id = p.booking_id
LEFT JOIN users u ON b.user_id = u.id
WHERE 1=1";

// Apply the same filters to top trainers query
if ($filters['trainer_id']) {
    $top_trainers_query .= " AND ta.trainer_id = " . mysqli_real_escape_string($conn, $filters['trainer_id']);
}
if ($filters['user_id']) {
    $top_trainers_query .= " AND b.user_id = " . mysqli_real_escape_string($conn, $filters['user_id']);
}
if ($filters['date_from']) {
    $top_trainers_query .= " AND ta.date >= '" . mysqli_real_escape_string($conn, $filters['date_from']) . "'";
}
if ($filters['date_to']) {
    $top_trainers_query .= " AND ta.date <= '" . mysqli_real_escape_string($conn, $filters['date_to']) . "'";
}
if ($filters['status']) {
    $top_trainers_query .= " AND b.status = '" . mysqli_real_escape_string($conn, $filters['status']) . "'";
}

$top_trainers_query .= " GROUP BY t.id
ORDER BY total_sessions DESC
LIMIT 5";

$top_trainers_result = mysqli_query($conn, $top_trainers_query);
$top_trainers = mysqli_fetch_all($top_trainers_result, MYSQLI_ASSOC);

// Build main report query
$base_query = "SELECT 
    b.id as booking_id,
    b.status as booking_status,
    b.created_at as booking_date,
    u.first_name as user_first_name,
    u.last_name as user_last_name,
    t.first_name as trainer_first_name,
    t.last_name as trainer_last_name,
    ta.date as session_date,
    ts.start_time,
    ts.end_time,
    p.amount as payment_amount,
    p.status as payment_status,
    tr.rating,
    tr.review
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
LEFT JOIN trainers t ON ta.trainer_id = t.id
LEFT JOIN payments p ON b.id = p.booking_id
LEFT JOIN trainer_reviews tr ON b.id = tr.booking_id
WHERE 1=1";

// Apply filters
if ($filters['trainer_id']) {
    $base_query .= " AND ta.trainer_id = " . mysqli_real_escape_string($conn, $filters['trainer_id']);
}
if ($filters['user_id']) {
    $base_query .= " AND b.user_id = " . mysqli_real_escape_string($conn, $filters['user_id']);
}
if ($filters['date_from']) {
    $base_query .= " AND ta.date >= '" . mysqli_real_escape_string($conn, $filters['date_from']) . "'";
}
if ($filters['date_to']) {
    $base_query .= " AND ta.date <= '" . mysqli_real_escape_string($conn, $filters['date_to']) . "'";
}
if ($filters['status']) {
    $base_query .= " AND b.status = '" . mysqli_real_escape_string($conn, $filters['status']) . "'";
}

$base_query .= " ORDER BY b.created_at DESC";

// Execute query
$result = mysqli_query($conn, $base_query);
$reports = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'Booking ID',
        'User Name',
        'Trainer Name',
        'Session Date',
        'Time',
        'Status',
        'Payment Amount',
        'Payment Status',
        'Rating',
        'Review',
        'Booking Date'
    ]);
    
    // Add data
    foreach ($reports as $row) {
        fputcsv($output, [
            $row['booking_id'],
            $row['user_first_name'] . ' ' . $row['user_last_name'],
            $row['trainer_first_name'] . ' ' . $row['trainer_last_name'],
            $row['session_date'],
            $row['start_time'] . ' - ' . $row['end_time'],
            $row['booking_status'],
            $row['payment_amount'],
            $row['payment_status'],
            $row['rating'],
            $row['review'],
            $row['booking_date']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Reports | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <!-- Include Date Range Picker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <!-- Include ApexCharts -->
    <link rel="stylesheet" href="assets/vendor/apexcharts/apexcharts.css">
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <!-- Page Title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Reports</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Reports & Analytics</h4>
                        </div>
                    </div>
                </div>
                <!-- Filters Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="header-title mb-3">Filter Reports</h4>
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col-md-2">
                                <label class="form-label">Report Type</label>
                                <select data-choices data-choices-sorting-false class="form-select" name="report_type">
                                    <option value="all" <?php echo $filters['report_type'] == 'all' ? 'selected' : ''; ?>>All Reports</option>
                                    <option value="bookings" <?php echo $filters['report_type'] == 'bookings' ? 'selected' : ''; ?>>Bookings</option>
                                    <option value="payments" <?php echo $filters['report_type'] == 'payments' ? 'selected' : ''; ?>>Payments</option>
                                    <option value="reviews" <?php echo $filters['report_type'] == 'reviews' ? 'selected' : ''; ?>>Reviews</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Trainer</label>
                                <select data-choices data-choices-sorting-false class="form-select" name="trainer_id">
                                    <option value="">All Trainers</option>
                                    <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['id']; ?>" <?php echo $filters['trainer_id'] == $trainer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($trainer['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">User</label>
                                <select data-choices data-choices-sorting-false class="form-select" name="user_id"  data-choices data-choices-sorting-false>
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <input type="text" class="form-control" id="date-range" name="date_range" 
                                       value="<?php echo $filters['date_from'] ? $filters['date_from'] . ' to ' . $filters['date_to'] : ''; ?>"
                                       placeholder="Select date range">
                                <input type="hidden" name="date_from" id="date-from" value="<?php echo $filters['date_from']; ?>">
                                <input type="hidden" name="date_to" id="date-to" value="<?php echo $filters['date_to']; ?>">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select data-choices data-choices-sorting-false class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $filters['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $filters['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $filters['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $filters['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-filter me-1"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Summary Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="fs-22 fw-bold mb-2"><?php echo number_format($stats['total_bookings']); ?></h4>
                                        <p class="text-muted mb-0">Total Bookings</p>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-primary">
                                        <i class="ti ti-calendar-event avatar-title text-white fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="fs-22 fw-bold mb-2">₹<?php echo number_format($stats['total_revenue']); ?></h4>
                                        <p class="text-muted mb-0">Total Revenue</p>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-success">
                                        <i class="ti ti-currency-rupee avatar-title text-white fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="fs-22 fw-bold mb-2"><?php echo number_format($stats['avg_rating'], 1); ?></h4>
                                        <p class="text-muted mb-0">Average Rating</p>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-warning">
                                        <i class="ti ti-star avatar-title text-white fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="fs-22 fw-bold mb-2"><?php echo number_format($stats['unique_users']); ?></h4>
                                        <p class="text-muted mb-0">Active Users</p>
                                    </div>
                                    <div class="avatar-sm rounded-circle bg-info">
                                        <i class="ti ti-users avatar-title text-white fs-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="header-title mb-4">Booking & Revenue Trends</h4>
                                <div id="trends-chart" class="apex-charts"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="header-title mb-4">Booking Status Distribution</h4>
                                <div id="status-chart" class="apex-charts"></div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Detailed Reports Card -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="header-title">Detailed Reports</h4>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                                <i class="ti ti-download me-1"></i> Export CSV
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>User</th>
                                        <th>Trainer</th>
                                        <th>Session Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Rating</th>
                                        <th>Booking Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td>#<?php echo $report['booking_id']; ?></td>
                                            <td><?php echo htmlspecialchars($report['user_first_name'] . ' ' . $report['user_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['trainer_first_name'] . ' ' . $report['trainer_last_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($report['session_date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($report['start_time'])) . ' - ' . date('h:i A', strtotime($report['end_time'])); ?></td>
                                            <td>
                                                <span class="badge <?php
                                                    switch($report['booking_status']) {
                                                        case 'completed':
                                                            echo 'bg-success';
                                                            break;
                                                        case 'pending':
                                                            echo 'bg-warning';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-danger';
                                                            break;
                                                        default:
                                                            echo 'bg-secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($report['booking_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-<?php echo $report['payment_status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                    ₹<?php echo number_format($report['payment_amount'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($report['rating']): ?>
                                                    <div class="text-warning">
                                                        <?php
                                                        for ($i = 0; $i < 5; $i++) {
                                                            echo $i < $report['rating'] ? '★' : '☆';
                                                        }
                                                        ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">No rating</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($report['booking_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reports)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No reports found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/theme_settings.php'; ?>

    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <!-- Initialize Charts -->
    <script>
        // Prepare data for charts
        const trendsData = <?php echo json_encode($trends); ?>;
        console.log('Trends data:', trendsData); // Debug log to see the data
        
        const statusData = {
            completed: <?php echo (int)$stats['completed_sessions']; ?>,
            pending: <?php echo (int)$stats['pending_sessions']; ?>,
            cancelled: <?php echo (int)$stats['cancelled_sessions']; ?>
        };

        // Trends Chart
        const trendsOptions = {
            series: [{
                name: 'Bookings',
                type: 'column',
                data: trendsData.length > 0 ? trendsData.map(item => parseInt(item.total_bookings || 0)) : [0]
            }, {
                name: 'Revenue',
                type: 'line',
                data: trendsData.length > 0 ? trendsData.map(item => parseInt(item.revenue || 0)) : [0]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true, // Enable toolbar for debugging
                    tools: {
                        download: true,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        pan: true,
                        reset: true
                    }
                }
            },
            stroke: {
                width: [0, 4]
            },
            title: {
                text: 'Monthly Trends' + (trendsData.length === 0 ? ' (No Data)' : '')
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [1]
            },
            labels: trendsData.length > 0 ? trendsData.map(item => {
                // Ensure proper date formatting by checking if month format is valid
                if (item.month && item.month.match(/^\d{4}-\d{2}$/)) {
                    return moment(item.month + '-01').format('MMM YYYY');
                } else {
                    return item.month || 'Unknown Date';
                }
            }) : ['No Data'],
            xaxis: {
                type: trendsData.length > 0 ? 'category' : 'category',
                labels: {
                    formatter: function(value) {
                        return value;
                    }
                }
            },
            yaxis: [{
                title: {
                    text: 'Bookings',
                },
                min: 0
            }, {
                opposite: true,
                title: {
                    text: 'Revenue'
                },
                min: 0
            }],
            noData: {
                text: 'No data available for the selected filters',
                align: 'center',
                verticalAlign: 'middle',
                offsetX: 0,
                offsetY: 0,
                style: {
                    color: '#6c757d',
                    fontSize: '16px',
                    fontFamily: 'Helvetica, Arial, sans-serif'
                }
            }
        };

        // Status Distribution Chart
        const statusOptions = {
            series: [
                parseInt(statusData.completed || 0), 
                parseInt(statusData.pending || 0), 
                parseInt(statusData.cancelled || 0)
            ],
            chart: {
                type: 'donut',
                height: 350
            },
            labels: ['Completed', 'Pending', 'Cancelled'],
            colors: ['#0acf97', '#ffc107', '#fa5c7c'],
            legend: {
                position: 'bottom'
            },
            noData: {
                text: 'No data available',
                align: 'center',
                verticalAlign: 'middle',
                offsetX: 0,
                offsetY: 0,
                style: {
                    color: '#6c757d',
                    fontSize: '16px',
                    fontFamily: 'Helvetica, Arial, sans-serif'
                }
            }
        };

        // Initialize charts
        const trendsChart = new ApexCharts(document.querySelector("#trends-chart"), trendsOptions);
        const statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
        
        trendsChart.render();
        statusChart.render();

        // Initialize date range picker
        $('#date-range').daterangepicker({
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear'
            }
        });

        $('#date-range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
            $('#date-from').val(picker.startDate.format('YYYY-MM-DD'));
            $('#date-to').val(picker.endDate.format('YYYY-MM-DD'));
        });

        $('#date-range').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            $('#date-from').val('');
            $('#date-to').val('');
        });
    </script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>
</body>
</html> 