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

// Get overall revenue metrics
$revenue_sql = "SELECT 
    SUM(amount) as total_revenue,
    COUNT(*) as total_transactions,
    AVG(amount) as average_transaction,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_revenue,
    SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) as refunded_amount
FROM payments
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$revenue_result = mysqli_query($conn, $revenue_sql);
$revenue_metrics = mysqli_fetch_assoc($revenue_result);

// Get payment method distribution
$payment_method_sql = "SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM payments
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY payment_method
ORDER BY total_amount DESC";
$payment_method_result = mysqli_query($conn, $payment_method_sql);

// Get daily revenue for the last 30 days
$daily_revenue_sql = "SELECT 
    DATE(created_at) as date,
    SUM(amount) as daily_revenue,
    COUNT(*) as transaction_count
FROM payments
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date";
$daily_revenue_result = mysqli_query($conn, $daily_revenue_sql);

// Get trainer revenue
$trainer_revenue_sql = "SELECT 
    t.first_name,
    t.last_name,
    COUNT(DISTINCT b.id) as booking_count,
    COALESCE(SUM(p.amount), 0) as total_revenue,
    COALESCE(AVG(p.amount), 0) as average_booking_value
FROM trainers t
LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id
LEFT JOIN time_slots ts ON ta.id = ts.trainer_availability_id
LEFT JOIN bookings b ON ts.id = b.time_slot_id
LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
WHERE (p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR p.created_at IS NULL)
GROUP BY t.id
HAVING total_revenue > 0
ORDER BY total_revenue DESC
LIMIT 5";
$trainer_revenue_result = mysqli_query($conn, $trainer_revenue_sql);

// Get recent transactions
$transactions_sql = "SELECT 
    p.*,
    u.first_name as user_first_name,
    u.last_name as user_last_name,
    t.first_name as trainer_first_name,
    t.last_name as trainer_last_name
FROM payments p
JOIN bookings b ON p.booking_id = b.id
JOIN users u ON b.user_id = u.id
JOIN time_slots ts ON b.time_slot_id = ts.id
JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
JOIN trainers t ON ta.trainer_id = t.id
ORDER BY p.created_at DESC
LIMIT 10";
$transactions_result = mysqli_query($conn, $transactions_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Finance Dashboard | IPN Academy Admin</title>
    <?php include 'includes/head.php'; ?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            transition: all 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-refunded { background-color: #f8d7da; color: #721c24; }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <!-- Overview Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Financial Overview</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Financial Overview (Last 30 Days)</h4>
                        </div>
                    </div>
                </div>

                <!-- Revenue Metrics Section -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card metric-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Revenue</h5>
                                <h2 class="mb-0">$<?php echo number_format($revenue_metrics['total_revenue'], 2); ?></h2>
                                <p class="small mb-0"><?php echo number_format($revenue_metrics['total_transactions']); ?> transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card metric-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Completed Revenue</h5>
                                <h2 class="mb-0">$<?php echo number_format($revenue_metrics['completed_revenue'], 2); ?></h2>
                                <p class="small mb-0">Successfully processed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card metric-card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Average Transaction</h5>
                                <h2 class="mb-0">$<?php echo number_format($revenue_metrics['average_transaction'], 2); ?></h2>
                                <p class="small mb-0">Per booking</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card metric-card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Refunded Amount</h5>
                                <h2 class="mb-0">$<?php echo number_format($revenue_metrics['refunded_amount'], 2); ?></h2>
                                <p class="small mb-0">Total refunds</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row">
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Daily Revenue Trend</h5>
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Payment Methods</h5>
                                <canvas id="paymentMethodChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Earning Trainers Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Top Earning Trainers</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Trainer</th>
                                                <th>Bookings</th>
                                                <th>Total Revenue</th>
                                                <th>Average Booking Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($trainer = mysqli_fetch_assoc($trainer_revenue_result)) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></td>
                                                    <td><?php echo number_format($trainer['booking_count']); ?></td>
                                                    <td>$<?php echo number_format($trainer['total_revenue'], 2); ?></td>
                                                    <td>$<?php echo number_format($trainer['average_booking_value'], 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Transactions</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Transaction ID</th>
                                                <th>User</th>
                                                <th>Trainer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($transaction = mysqli_fetch_assoc($transactions_result)) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['user_first_name'] . ' ' . $transaction['user_last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['trainer_first_name'] . ' ' . $transaction['trainer_last_name']); ?></td>
                                                    <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo strtolower($transaction['status']); ?>">
                                                            <?php echo ucfirst($transaction['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Daily Revenue Chart
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: [<?php 
                    $dates = [];
                    $revenues = [];
                    mysqli_data_seek($daily_revenue_result, 0);
                    while ($row = mysqli_fetch_assoc($daily_revenue_result)) {
                        $dates[] = "'" . date('M d', strtotime($row['date'])) . "'";
                        $revenues[] = $row['daily_revenue'];
                    }
                    echo implode(',', $dates);
                ?>],
                datasets: [{
                    label: 'Daily Revenue',
                    data: [<?php echo implode(',', $revenues); ?>],
                    borderColor: '#3498db',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });

        // Payment Methods Chart
        new Chart(document.getElementById('paymentMethodChart'), {
            type: 'doughnut',
            data: {
                labels: [<?php 
                    $methods = [];
                    $amounts = [];
                    mysqli_data_seek($payment_method_result, 0);
                    while ($row = mysqli_fetch_assoc($payment_method_result)) {
                        $methods[] = "'" . $row['payment_method'] . "'";
                        $amounts[] = $row['total_amount'];
                    }
                    echo implode(',', $methods);
                ?>],
                datasets: [{
                    data: [<?php echo implode(',', $amounts); ?>],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 