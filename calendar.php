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

// Debug session info
echo "<div style='display:none;'>";
echo "Session Info:<br>";
echo "User Type: " . $userType . "<br>";
echo "User ID: " . $userId . "<br>";
echo "User Name: " . $userName . "<br>";
echo "</div>";

// Modified query to include all relevant events
$sql = "SELECT 
            b.id, 
            b.status,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            CONCAT(t.first_name, ' ', t.last_name) as trainer_name,
            ta.date,
            ts.start_time,
            ts.end_time,
            t.id as trainer_id,
            u.id as user_id
        FROM bookings b 
        JOIN users u ON b.user_id = u.id
        JOIN time_slots ts ON b.time_slot_id = ts.id
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        JOIN trainers t ON ta.trainer_id = t.id";

// Add WHERE clause based on user type
if ($userType == 'trainer') {
    $sql .= " WHERE ta.trainer_id = $userId";
} elseif ($userType == 'user') {
    $sql .= " WHERE b.user_id = $userId";
} else {
    // For admin, show all events
    $sql .= " WHERE 1=1";
}

$sql .= " ORDER BY ta.date, ts.start_time";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$events = [];
$eventCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $eventCount++;
    $start = $row['date'] . 'T' . $row['start_time'];
    $end = $row['date'] . 'T' . $row['end_time'];
    
    // Set event color based on status
    $color = '';
    switch($row['status']) {
        case 'pending':
            $color = '#ffc107'; // warning yellow
            break;
        case 'confirmed':
            $color = '#0d6efd'; // primary blue
            break;
        case 'completed':
            $color = '#198754'; // success green
            break;
        case 'cancelled':
            $color = '#dc3545'; // danger red
            break;
        case 'pending_reschedule':
            $color = '#6c757d'; // secondary gray
            break;
        default:
            $color = '#6c757d'; // default gray
    }
    
    $title = $userType == 'trainer' ? 
        "Session with " . $row['user_name'] : 
        "Session with " . $row['trainer_name'];
    
    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $start,
        'end' => $end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'status' => $row['status'],
        'extendedProps' => [
            'status' => $row['status'],
            'user_name' => $row['user_name'],
            'trainer_name' => $row['trainer_name']
        ]
    ];
}

// Debug output
echo "<div style='display:none;'>";
echo "Total events found: " . $eventCount . "<br>";
echo "SQL Query: " . $sql . "<br>";
echo "Events Array: <pre>" . print_r($events, true) . "</pre>";
echo "</div>";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Calendar | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        #calendar {
            margin: 20px auto;
            padding: 0 10px;
            max-width: 1200px;
            height: 700px;
        }
        .fc-event {
            cursor: pointer;
        }
        .fc-event-title {
            white-space: normal;
            overflow: visible;
        }
        .card-body {
            padding: 1.5rem;
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
                                    <li class="breadcrumb-item active">Calendar</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Calendar</h4>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div id="calendar"></div>
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

    <!-- App js -->
    <script src="assets/js/app.js"></script>

    <!-- Fullcalendar js -->
    <script src="assets/vendor/fullcalendar/index.global.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var events = <?php echo json_encode($events); ?>;
            
            console.log('Calendar Events:', events);
            console.log('Total Events:', events.length);
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                themeSystem: 'bootstrap',
                height: 'auto',
                contentHeight: 'auto',
                aspectRatio: 2,
                events: events,
                eventDisplay: 'block',
                displayEventTime: true,
                eventClick: function(info) {
                    window.location.href = 'view_booking.php?id=' + info.event.id;
                },
                eventDidMount: function(info) {
                    // Enhanced tooltip with more information
                    info.el.title = `
                        Status: ${info.event.extendedProps.status}
                        ${info.event.title}
                        Time: ${info.event.start.toLocaleTimeString()} - ${info.event.end.toLocaleTimeString()}
                    `;
                },
                datesSet: function(info) {
                    console.log('Calendar view changed:', info.view.type);
                }
            });
            
            calendar.render();
            
            // Additional debug check after render
            setTimeout(function() {
                if (calendarEl.children.length === 0) {
                    console.error('Calendar failed to render properly');
                } else {
                    console.log('Calendar rendered successfully');
                }
            }, 1000);
        });
    </script>
</body>
</html>