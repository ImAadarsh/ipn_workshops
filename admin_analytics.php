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

// Get overall metrics
$overall_sql = "SELECT 
    (SELECT COUNT(*) FROM bookings) as total_bookings,
    (SELECT COUNT(*) FROM users WHERE user_type = 'user') as total_users,
    (SELECT COUNT(*) FROM trainers) as total_trainers,
    (SELECT COUNT(*) FROM trainer_reviews) as total_reviews,
    (SELECT COUNT(*) FROM blogs) as total_blogs";
$overall_result = mysqli_query($conn, $overall_sql);
$overall_metrics = mysqli_fetch_assoc($overall_result);

// Get booking metrics
$booking_sql = "SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
FROM bookings
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$booking_result = mysqli_query($conn, $booking_sql);
$booking_metrics = mysqli_fetch_assoc($booking_result);

// Get trainer performance
$trainer_sql = "SELECT 
    t.first_name,
    t.last_name,
    COALESCE(COUNT(DISTINCT b.id), 0) as booking_count,
    COALESCE(AVG(tr.rating), 0) as avg_rating,
    COALESCE(COUNT(DISTINCT tr.id), 0) as review_count,
    COALESCE(SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_sessions
FROM trainers t
LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id
LEFT JOIN time_slots ts ON ta.id = ts.trainer_availability_id
LEFT JOIN bookings b ON ts.id = b.time_slot_id
LEFT JOIN trainer_reviews tr ON t.id = tr.trainer_id
GROUP BY t.id
ORDER BY booking_count DESC, avg_rating DESC
LIMIT 5";
$trainer_result = mysqli_query($conn, $trainer_sql);

// Get popular blog categories
$blog_sql = "SELECT 
    bc.name as category_name,
    COUNT(b.id) as post_count,
    SUM(b.visit) as total_visits
FROM blog_categories bc
LEFT JOIN blogs b ON bc.id = b.category_id
GROUP BY bc.id
ORDER BY total_visits DESC
LIMIT 5";
$blog_result = mysqli_query($conn, $blog_sql);

// Get recent reviews
$reviews_sql = "SELECT 
    tr.*,
    u.first_name as user_first_name,
    u.last_name as user_last_name,
    t.first_name as trainer_first_name,
    t.last_name as trainer_last_name
FROM trainer_reviews tr
JOIN users u ON tr.user_id = u.id
JOIN trainers t ON tr.trainer_id = t.id
ORDER BY tr.created_at DESC
LIMIT 5";
$reviews_result = mysqli_query($conn, $reviews_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Analytics Dashboard | IPN Academy Admin</title>
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
        .rating-stars {
            color: #ffc107;
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
                                    <li class="breadcrumb-item active">Analytics Dashboard</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Analytics Dashboard</h4>
                        </div>
                    </div>
                </div>

                <!-- Overview Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h4 class="mb-4">Overview</h4>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card metric-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Bookings</h5>
                                <h2 class="mb-0"><?php echo number_format($overall_metrics['total_bookings']); ?></h2>
                                <p class="small mb-0">Last 30 days: <?php echo number_format($booking_metrics['total_count']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card metric-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Active Users</h5>
                                <h2 class="mb-0"><?php echo number_format($overall_metrics['total_users']); ?></h2>
                                <p class="small mb-0">Total registered users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card metric-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Reviews</h5>
                                <h2 class="mb-0"><?php echo number_format($overall_metrics['total_reviews']); ?></h2>
                                <p class="small mb-0">Across <?php echo number_format($overall_metrics['total_trainers']); ?> trainers</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Booking Status Distribution</h5>
                                <canvas id="bookingChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Top Blog Categories</h5>
                                <canvas id="blogChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Trainers Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Top Performing Trainers</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Trainer</th>
                                                <th>Bookings</th>
                                                <th>Rating</th>
                                                <th>Reviews</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($trainer = mysqli_fetch_assoc($trainer_result)) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></td>
                                                    <td><?php echo number_format($trainer['booking_count']); ?></td>
                                                    <td>
                                                        <div class="rating-stars">
                                                            <?php
                                                            $rating = round($trainer['avg_rating']);
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                echo ($i <= $rating) ? '★' : '☆';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo number_format($trainer['review_count']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reviews Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Reviews</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Trainer</th>
                                                <th>Rating</th>
                                                <th>Review</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($review = mysqli_fetch_assoc($reviews_result)) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($review['user_first_name'] . ' ' . $review['user_last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($review['trainer_first_name'] . ' ' . $review['trainer_last_name']); ?></td>
                                                    <td>
                                                        <div class="rating-stars">
                                                            <?php
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                echo ($i <= $review['rating']) ? '★' : '☆';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(substr($review['review'], 0, 100)) . '...'; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
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
        // Booking Status Chart
        new Chart(document.getElementById('bookingChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Cancelled', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $booking_metrics['completed']; ?>,
                        <?php echo $booking_metrics['cancelled']; ?>,
                        <?php echo $booking_metrics['pending']; ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107']
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

        // Blog Categories Chart
        new Chart(document.getElementById('blogChart'), {
            type: 'bar',
            data: {
                labels: [<?php 
                    $labels = [];
                    $visits = [];
                    mysqli_data_seek($blog_result, 0);
                    while ($row = mysqli_fetch_assoc($blog_result)) {
                        $labels[] = "'" . $row['category_name'] . "'";
                        $visits[] = $row['total_visits'];
                    }
                    echo implode(',', $labels);
                ?>],
                datasets: [{
                    label: 'Total Visits',
                    data: [<?php echo implode(',', $visits); ?>],
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 