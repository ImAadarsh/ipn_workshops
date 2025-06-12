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

// Get trainer ID from URL
$trainerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$trainerId) {
    header("Location: trainers.php");
    exit();
}

// Get trainer details
$sql = "SELECT t.*, 
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT CASE WHEN b.status = 'completed' THEN b.id END) as completed_sessions,
        COALESCE(AVG(tr.rating), 0) as avg_rating,
        COUNT(DISTINCT tr.id) as total_reviews,
        GROUP_CONCAT(DISTINCT ts.specialization) as specializations
        FROM trainers t 
        LEFT JOIN trainer_specializations ts ON t.id = ts.trainer_id 
        LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id 
        LEFT JOIN time_slots tsl ON ta.id = tsl.trainer_availability_id 
        LEFT JOIN bookings b ON tsl.id = b.time_slot_id 
        LEFT JOIN trainer_reviews tr ON t.id = tr.trainer_id 
        WHERE t.id = $trainerId
        GROUP BY t.id";

$result = mysqli_query($conn, $sql);
$trainer = mysqli_fetch_assoc($result);

if (!$trainer) {
    header("Location: trainers.php");
    exit();
}

// Get available slots
$today = date('Y-m-d');
$sql_slots = "SELECT ta.date, tsl.start_time, tsl.end_time, tsl.id as slot_id,
              CASE WHEN b.id IS NULL THEN 'available' ELSE 'booked' END as status
              FROM trainer_availabilities ta
              JOIN time_slots tsl ON ta.id = tsl.trainer_availability_id
              LEFT JOIN bookings b ON tsl.id = b.time_slot_id AND b.status != 'cancelled'
              WHERE ta.trainer_id = $trainerId 
              AND ta.date >= '$today'
              ORDER BY ta.date, tsl.start_time";

$slots_result = mysqli_query($conn, $sql_slots);
$available_slots = [];
$booked_slots = [];

while ($slot = mysqli_fetch_assoc($slots_result)) {
    if ($slot['status'] === 'available') {
        $available_slots[] = $slot;
    } else {
        $booked_slots[] = $slot;
    }
}

// Get upcoming bookings
$sql_bookings = "SELECT b.*, u.first_name, u.last_name, u.email,
                 ta.date, tsl.start_time, tsl.end_time
                 FROM bookings b
                 JOIN users u ON b.user_id = u.id
                 JOIN time_slots tsl ON b.time_slot_id = tsl.id
                 JOIN trainer_availabilities ta ON tsl.trainer_availability_id = ta.id
                 WHERE ta.trainer_id = $trainerId 
                 AND ta.date >= '$today'
                 AND b.status != 'cancelled'
                 ORDER BY ta.date, tsl.start_time
                 LIMIT 10";

$bookings_result = mysqli_query($conn, $sql_bookings);
$upcoming_bookings = [];

while ($booking = mysqli_fetch_assoc($bookings_result)) {
    $upcoming_bookings[] = $booking;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>View Trainer | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .trainer-profile {
            text-align: center;
            padding: 20px;
        }
        .trainer-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 24px;
            margin: 10px 0;
        }
        .specialization-badge {
            display: inline-block;
            padding: 6px 12px;
            margin: 4px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 14px;
            color: #495057;
        }
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
        }
        .stats-label {
            color: #6c757d;
            font-size: 14px;
        }
        .slot-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .slot-date {
            font-weight: bold;
            color: #0d6efd;
        }
        .slot-time {
            color: #6c757d;
        }
        .booking-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .booking-user {
            font-weight: bold;
        }
        .booking-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-completed {
            background-color: #cce5ff;
            color: #004085;
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
                                    <li class="breadcrumb-item"><a href="trainers.php">Trainers</a></li>
                                    <li class="breadcrumb-item active">Trainer Details</li>
                                </ol>
                            </div>
                           
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="page-title-box d-flex justify-content-between align-items-center">
                            <h4 class="page-title">Trainer Details</h4>
                            <div class="d-flex gap-2">
                                <a href="bookings.php?trainer=<?php echo $trainerId; ?>" class="btn btn-primary">
                                    <i class="ti ti-calendar me-1"></i> View All Bookings
                                </a>
                                <?php if ($userType === 'admin'): ?>
                                    <a href="edit_trainer.php?id=<?php echo $trainerId; ?>" class="btn btn-info">
                                        <i class="ti ti-edit me-1"></i> Edit Trainer
                                    </a>
                                    <button class="btn btn-danger delete-trainer" data-id="<?php echo $trainerId; ?>">
                                        <i class="ti ti-trash me-1"></i> Delete Trainer
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Trainer Profile -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="trainer-profile">
                                <img src="<?php echo $uri.$trainer['profile_img']; ?>" 
                                     alt="<?php echo $trainer['first_name']; ?>" 
                                     class="trainer-avatar">
                                <h3><?php echo $trainer['first_name'] . ' ' . $trainer['last_name']; ?></h3>
                                <p class="text-muted"><?php echo $trainer['designation']; ?></p>
                                
                                <div class="rating-stars">
                                    <?php 
                                    $rating = round($trainer['avg_rating'], 1);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '★';
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                    <span class="text-muted">(<?php echo $trainer['total_reviews']; ?> reviews)</span>
                                </div>

                                <div class="mt-3">
                                    <?php 
                                    $specializations = explode(',', $trainer['specializations']);
                                    foreach ($specializations as $spec): 
                                        if ($spec):
                                    ?>
                                        <span class="specialization-badge"><?php echo $spec; ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-6">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo $trainer['total_bookings']; ?></div>
                                            <div class="stats-label">Total Bookings</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stats-card">
                                            <div class="stats-number"><?php echo $trainer['completed_sessions']; ?></div>
                                            <div class="stats-label">Completed Sessions</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Upcoming Bookings -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Upcoming Bookings</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_bookings)): ?>
                                    <p class="text-muted">No upcoming bookings.</p>
                                <?php else: ?>
                                    <?php foreach ($upcoming_bookings as $booking): ?>
                                        <div class="booking-card">
                                            <div class="booking-header">
                                                <div class="booking-user">
                                                    <?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?>
                                                </div>
                                                <span class="booking-status status-<?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </div>
                                            <div class="booking-details">
                                                <div class="booking-date">
                                                    <?php echo date('D, M d, Y', strtotime($booking['date'])); ?>
                                                </div>
                                                <div class="booking-time">
                                                    <?php echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                                             date('h:i A', strtotime($booking['end_time'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Available Slots -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Available Slots</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($available_slots)): ?>
                                    <p class="text-muted">No available slots at the moment.</p>
                                <?php else: ?>
                                    <?php foreach ($available_slots as $slot): ?>
                                        <div class="slot-card">
                                            <div class="slot-date">
                                                <?php echo date('D, M d, Y', strtotime($slot['date'])); ?>
                                            </div>
                                            <div class="slot-time">
                                                <?php echo date('h:i A', strtotime($slot['start_time'])) . ' - ' . 
                                                         date('h:i A', strtotime($slot['end_time'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Delete trainer confirmation
            document.querySelector('.delete-trainer').addEventListener('click', function() {
                var trainerId = this.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this trainer? This action cannot be undone.')) {
                    fetch('controllers/delete_trainer.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `trainer_id=${trainerId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'trainers.php';
                        } else {
                            alert(data.message || 'Failed to delete trainer');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the trainer');
                    });
                }
            });
        });
    </script>
</body>
</html> 