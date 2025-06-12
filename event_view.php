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

// Check if event ID is provided
if (!isset($_GET['id'])) {
    header("Location: events.php");
    exit();
}

$event_id = (int)$_GET['id'];

// Fetch event details
$sql = "SELECT * FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    header("Location: events.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>View Event | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .event-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }
        .event-details {
            margin-top: 20px;
        }
        .event-details h2 {
            margin-bottom: 20px;
        }
        .event-meta {
            margin-bottom: 20px;
        }
        .event-meta p {
            margin-bottom: 10px;
        }
        .event-actions {
            margin-top: 30px;
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
                                    <li class="breadcrumb-item"><a href="events.php">Events</a></li>
                                    <li class="breadcrumb-item active">View Event</li>
                                </ol>
                            </div>
                            <h4 class="page-title">View Event</h4>
                            <div>
                                <a href="events.php" class="btn btn-secondary me-2">Back to Events</a>
                                <a href="event_edit.php?id=<?php echo $event_id; ?>" class="btn btn-primary">Edit Event</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <img src="<?php echo $uri . htmlspecialchars($event['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($event['name']); ?>" 
                                             class="event-image">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="event-details">
                                            <h2><?php echo htmlspecialchars($event['name']); ?></h2>
                                            <div class="event-meta">
                                                <p><strong>Date & Time:</strong> 
                                                    <?php echo date('F j, Y g:i A', strtotime($event['date_time'])); ?>
                                                </p>
                                                <p><strong>Location:</strong> 
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                </p>
                                                <p><strong>Event Link:</strong> 
                                                    <a href="<?php echo htmlspecialchars($event['link']); ?>" 
                                                       target="_blank">
                                                        <?php echo htmlspecialchars($event['link']); ?>
                                                    </a>
                                                </p>
                                            </div>
                                            <div class="event-actions">
                                                <a href="<?php echo htmlspecialchars($event['link']); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-success">
                                                    Join Event
                                                </a>
                                                <button onclick="deleteEvent(<?php echo $event_id; ?>)" 
                                                        class="btn btn-danger ms-2">
                                                    Delete Event
                                                </button>
                                            </div>
                                        </div>
                                    </div>
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

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    <!-- App js -->
    <script src="assets/js/app.js"></script>

    <script>
        function deleteEvent(id) {
            if (confirm('Are you sure you want to delete this event?')) {
                window.location.href = 'event_delete.php?id=' + id;
            }
        }
    </script>
</body>
</html> 