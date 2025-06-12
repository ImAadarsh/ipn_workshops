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

// Initialize variables for dashboard stats
$totalUsers = 0;
$totalTrainers = 0;
$totalBookings = 0;
$todaysSessions = 0;
$totalStudents = 0;
$myBookings = 0;
$completedSessions = 0;
$pendingSessions = 0;
$cancelledSessions = 0;
$totalRevenue = 0;

// Add new statistics variables
$monthlyStats = [];
$trainerRatings = [];
$upcomingSessions = [];
$recentReviews = [];
$monthlyRevenue = [];

// Initialize default values for session statistics
$completedSessions = 0;
$pendingSessions = 0;
$cancelledSessions = 0;

// Get statistics based on user type
if ($userType === 'admin') {
    // Get total users
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE user_type = 'user'");
    $totalUsers = mysqli_fetch_assoc($result)['count'];

    // Get total trainers
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM trainers");
    $totalTrainers = mysqli_fetch_assoc($result)['count'];

    // Get total bookings
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings");
    $totalBookings = mysqli_fetch_assoc($result)['count'];

    // Get total revenue
    $result = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
    $totalRevenue = mysqli_fetch_assoc($result)['total'] ?? 0;

    // Get booking statistics
    $result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            switch ($row['status']) {
                case 'completed':
                    $completedSessions = $row['count'];
                    break;
                case 'pending':
                    $pendingSessions = $row['count'];
                    break;
                case 'cancelled':
                    $cancelledSessions = $row['count'];
                    break;
            }
        }
    }

} elseif ($userType === 'trainer') {
    // Get today's sessions for trainer
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as count FROM bookings b 
            JOIN time_slots ts ON b.time_slot_id = ts.id 
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
            WHERE ta.trainer_id = ? AND ta.date = ? AND b.status IN ('confirmed', 'pending')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $userId, $today);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $todaysSessions = mysqli_fetch_assoc($result)['count'];

    // Get total students for trainer
    $sql = "SELECT COUNT(DISTINCT b.user_id) as count FROM bookings b 
            JOIN time_slots ts ON b.time_slot_id = ts.id 
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
            WHERE ta.trainer_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $totalStudents = mysqli_fetch_assoc($result)['count'];

    // Get trainer's booking statistics
    $sql = "SELECT b.status, COUNT(*) as count FROM bookings b 
            JOIN time_slots ts ON b.time_slot_id = ts.id 
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
            WHERE ta.trainer_id = ? GROUP BY b.status";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            switch ($row['status']) {
                case 'completed':
                    $completedSessions = $row['count'];
                    break;
                case 'pending':
                    $pendingSessions = $row['count'];
                    break;
                case 'cancelled':
                    $cancelledSessions = $row['count'];
                    break;
            }
        }
    }
    mysqli_stmt_close($stmt);

} else {
    // Get total bookings for user
    $sql = "SELECT status, COUNT(*) as count FROM bookings WHERE user_id = ? GROUP BY status";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                switch ($row['status']) {
                    case 'completed':
                        $completedSessions = $row['count'];
                        break;
                    case 'pending':
                        $pendingSessions = $row['count'];
                        break;
                    case 'cancelled':
                        $cancelledSessions = $row['count'];
                        break;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Get total bookings for user
    $sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $myBookings = mysqli_fetch_assoc($result)['count'];
}

// Get recent bookings
$recentBookingsQuery = "";
if ($userType === 'admin') {
    $recentBookingsQuery = "SELECT b.*, u.first_name, u.last_name, t.first_name as trainer_fname, t.last_name as trainer_lname 
                           FROM bookings b 
                           JOIN users u ON b.user_id = u.id 
                           JOIN time_slots ts ON b.time_slot_id = ts.id 
                           JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
                           JOIN trainers t ON ta.trainer_id = t.id 
                           ORDER BY b.created_at DESC LIMIT 6";
} elseif ($userType === 'trainer') {
    $recentBookingsQuery = "SELECT b.*, u.first_name, u.last_name 
                           FROM bookings b 
                           JOIN users u ON b.user_id = u.id 
                           JOIN time_slots ts ON b.time_slot_id = ts.id 
                           JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
                           WHERE ta.trainer_id = ? 
                           ORDER BY b.created_at DESC LIMIT 5";
} else {
    $recentBookingsQuery = "SELECT b.*, t.first_name as trainer_fname, t.last_name as trainer_lname 
                           FROM bookings b 
                           JOIN time_slots ts ON b.time_slot_id = ts.id 
                           JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
                           JOIN trainers t ON ta.trainer_id = t.id 
                           WHERE b.user_id = ? 
                           ORDER BY b.created_at DESC LIMIT 5";
}

$recentBookings = [];
if ($userType === 'admin') {
    $result = mysqli_query($conn, $recentBookingsQuery);
    while ($row = mysqli_fetch_assoc($result)) {
        $recentBookings[] = $row;
    }
} else {
    $stmt = mysqli_prepare($conn, $recentBookingsQuery);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $recentBookings[] = $row;
    }
}

// Get monthly statistics for the past 6 months
$months = [];
$monthlyBookings = [];
$monthlyRevenue = [];

// Initialize arrays with zeros for all months
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('M Y', strtotime("-$i months"));
    $monthlyBookings[] = 0;
    if ($userType === 'admin') {
        $monthlyRevenue[] = 0;
    }
}

// Fetch actual data for each month
for ($i = 5; $i >= 0; $i--) {
    $startDate = date('Y-m-01 00:00:00', strtotime("-$i months"));
    $endDate = date('Y-m-t 23:59:59', strtotime("-$i months"));
    
    if ($userType === 'admin') {
        // Get bookings count
        $sql = "SELECT COUNT(*) as count FROM bookings WHERE created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result !== false) {
                $row = mysqli_fetch_assoc($result);
                if ($row && isset($row['count'])) {
                    $monthlyBookings[5 - $i] = (int)$row['count'];
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        // Get revenue
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result !== false) {
                $row = mysqli_fetch_assoc($result);
                if ($row && isset($row['total'])) {
                    $monthlyRevenue[5 - $i] = (float)$row['total'];
                }
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($userType === 'trainer') {
        $sql = "SELECT COUNT(*) as count FROM bookings b 
                JOIN time_slots ts ON b.time_slot_id = ts.id 
                JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
                WHERE ta.trainer_id = ? AND b.created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $userId, $startDate, $endDate);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result !== false) {
                $row = mysqli_fetch_assoc($result);
                if ($row && isset($row['count'])) {
                    $monthlyBookings[5 - $i] = (int)$row['count'];
                }
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $sql = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $userId, $startDate, $endDate);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result !== false) {
                $row = mysqli_fetch_assoc($result);
                if ($row && isset($row['count'])) {
                    $monthlyBookings[5 - $i] = (int)$row['count'];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get trainer ratings
if ($userType === 'admin') {
    $sql = "SELECT t.first_name, t.last_name, AVG(tr.rating) as avg_rating, COUNT(tr.id) as review_count 
            FROM trainers t 
            LEFT JOIN trainer_reviews tr ON t.id = tr.trainer_id 
            GROUP BY t.id 
            ORDER BY avg_rating DESC 
            LIMIT 5";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $trainerRatings[] = $row;
    }
}

// Get upcoming sessions
$today = date('Y-m-d');
if ($userType === 'admin') {
    $sql = "SELECT b.*, u.first_name, u.last_name, t.first_name as trainer_fname, t.last_name as trainer_lname,
            ta.date, ts.start_time, ts.end_time
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN time_slots ts ON b.time_slot_id = ts.id 
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
            JOIN trainers t ON ta.trainer_id = t.id 
            WHERE ta.date >= ? AND b.status IN ('confirmed', 'pending')
            ORDER BY ta.date, ts.start_time 
            LIMIT 5";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $today);
} elseif ($userType === 'trainer') {
    $sql = "SELECT b.*, u.first_name, u.last_name, ta.date, ts.start_time, ts.end_time
            FROM bookings b 
            JOIN users u ON b.user_id = u.id 
            JOIN time_slots ts ON b.time_slot_id = ts.id 
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
            WHERE ta.trainer_id = ? AND ta.date >= ? AND b.status IN ('confirmed', 'pending')
            ORDER BY ta.date, ts.start_time 
            LIMIT 5";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $userId, $today);
} else {
    $sql = "SELECT b.*, t.first_name as trainer_fname, t.last_name as trainer_lname,
            ta.date, ts.start_time, ts.end_time
            FROM bookings b 
            JOIN time_slots ts ON b.time_slot_id = ts.id 
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id 
            JOIN trainers t ON ta.trainer_id = t.id 
            WHERE b.user_id = ? AND ta.date >= ? AND b.status IN ('confirmed', 'pending')
            ORDER BY ta.date, ts.start_time 
            LIMIT 5";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $userId, $today);
}
mysqli_stmt_execute($stmt);
$upcomingSessions = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

// Get recent reviews
$sql = "SELECT tr.*, u.first_name, u.last_name, t.first_name as trainer_fname, t.last_name as trainer_lname 
        FROM trainer_reviews tr 
        JOIN users u ON tr.user_id = u.id 
        JOIN trainers t ON tr.trainer_id = t.id 
        ORDER BY tr.created_at DESC 
        LIMIT 5";
$result = mysqli_query($conn, $sql);
$recentReviews = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get revenue overview for admin
if ($userType === 'admin') {
    $currentMonth = date('Y-m');
    $previousMonth = date('Y-m', strtotime('-1 month'));
    
    // Get current month revenue - using direct query instead of prepared statement
    $currentMonthStart = date('Y-m-01 00:00:00');
    $currentMonthEnd = date('Y-m-t 23:59:59');
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as revenue FROM payments 
            WHERE status = 'completed' 
            AND created_at >= '$currentMonthStart' 
            AND created_at <= '$currentMonthEnd'";
    
    $result = mysqli_query($conn, $sql);
    if ($result !== false) {
        $row = mysqli_fetch_assoc($result);
        $currentMonthRevenue = $row['revenue'] ?? 0;
    } else {
        $currentMonthRevenue = 0;
    }
    
    // Get previous month revenue
    $previousMonthStart = date('Y-m-01 00:00:00', strtotime('-1 month'));
    $previousMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as revenue FROM payments 
            WHERE status = 'completed' 
            AND created_at >= '$previousMonthStart' 
            AND created_at <= '$previousMonthEnd'";
    
    $result = mysqli_query($conn, $sql);
    if ($result !== false) {
        $row = mysqli_fetch_assoc($result);
        $previousMonthRevenue = $row['revenue'] ?? 0;
    } else {
        $previousMonthRevenue = 0;
    }

    // Calculate revenue growth
    $revenueGrowth = $previousMonthRevenue > 0 ? 
        (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100 : 0;

    // Get top performing trainers
    $sql = "SELECT 
        t.*,
        COALESCE(COUNT(DISTINCT b.id), 0) as total_bookings,
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_sessions,
        COALESCE(AVG(tr.rating), 0) as avg_rating,
        COALESCE(COUNT(DISTINCT tr.id), 0) as total_reviews
    FROM trainers t
    LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id
    LEFT JOIN time_slots ts ON ta.id = ts.trainer_availability_id
    LEFT JOIN bookings b ON ts.id = b.time_slot_id
    LEFT JOIN trainer_reviews tr ON t.id = tr.trainer_id
    GROUP BY t.id
    ORDER BY total_bookings DESC, avg_rating DESC
    LIMIT 5";
    $result = mysqli_query($conn, $sql);
    $topTrainers = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Get traffic sources (admin only)
    $sql = "SELECT 
            COALESCE(SUM(CASE WHEN user_type = 'user' THEN 1 ELSE 0 END), 0) as direct_users,
            COALESCE(SUM(CASE WHEN user_type = 'trainer' THEN 1 ELSE 0 END), 0) as trainer_users
            FROM users";
    $result = mysqli_query($conn, $sql);
    if ($result !== false) {
        $trafficSources = mysqli_fetch_assoc($result);
        if (!$trafficSources) {
            $trafficSources = ['direct_users' => 0, 'trainer_users' => 0];
        }
        // Ensure values are integers
        $trafficSources['direct_users'] = (int)$trafficSources['direct_users'];
        $trafficSources['trainer_users'] = (int)$trafficSources['trainer_users'];
    } else {
        $trafficSources = ['direct_users' => 0, 'trainer_users' => 0];
    }
}

// Get activity timeline
$timeline = [];

if ($userType === 'admin') {
    // Get bookings for timeline
    $sql = "SELECT 
        'booking' as type,
        b.created_at as date,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        CONCAT(t.first_name, ' ', t.last_name) as trainer_name,
        b.status
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN time_slots ts ON b.time_slot_id = ts.id
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        JOIN trainers t ON ta.trainer_id = t.id
        ORDER BY b.created_at DESC
        LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $bookingTimeline = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeline = array_merge($timeline, $bookingTimeline);
    }
    
    // Get reviews for timeline 
    $sql = "SELECT 
        'review' as type,
        tr.created_at as date,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        CONCAT(t.first_name, ' ', t.last_name) as trainer_name,
        tr.rating as status
        FROM trainer_reviews tr
        JOIN users u ON tr.user_id = u.id
        JOIN trainers t ON tr.trainer_id = t.id
        ORDER BY tr.created_at DESC
        LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $reviewTimeline = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeline = array_merge($timeline, $reviewTimeline);
    }
    
    // Sort the combined results by date
    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Limit to 10 items
    $timeline = array_slice($timeline, 0, 10);
    
} elseif ($userType === 'trainer') {
    // Get bookings for trainer timeline
    $sql = "SELECT 
        'booking' as type,
        b.created_at as date,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        '' as trainer_name,
        b.status
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN time_slots ts ON b.time_slot_id = ts.id
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        WHERE ta.trainer_id = $userId
        ORDER BY b.created_at DESC
        LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $bookingTimeline = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeline = array_merge($timeline, $bookingTimeline);
    }
    
    // Get reviews for trainer timeline
    $sql = "SELECT 
        'review' as type,
        tr.created_at as date,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        '' as trainer_name,
        tr.rating as status
        FROM trainer_reviews tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.trainer_id = $userId
        ORDER BY tr.created_at DESC
        LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $reviewTimeline = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeline = array_merge($timeline, $reviewTimeline);
    }
    
    // Sort the combined results by date
    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Limit to 10 items
    $timeline = array_slice($timeline, 0, 10);
    
} else {
    // Get bookings for user timeline
    $sql = "SELECT 
        'booking' as type,
        b.created_at as date,
        '' as user_name,
        CONCAT(t.first_name, ' ', t.last_name) as trainer_name,
        b.status
        FROM bookings b
        JOIN time_slots ts ON b.time_slot_id = ts.id
        JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
        JOIN trainers t ON ta.trainer_id = t.id
        WHERE b.user_id = $userId
        ORDER BY b.created_at DESC
        LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $bookingTimeline = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeline = array_merge($timeline, $bookingTimeline);
    }
    
    // Get reviews for user timeline
    $sql = "SELECT 
        'review' as type,
        tr.created_at as date,
        '' as user_name,
        CONCAT(t.first_name, ' ', t.last_name) as trainer_name,
        tr.rating as status
        FROM trainer_reviews tr
        JOIN trainers t ON tr.trainer_id = t.id
        WHERE tr.user_id = $userId
        ORDER BY tr.created_at DESC
        LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $reviewTimeline = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $timeline = array_merge($timeline, $reviewTimeline);
    }
    
    // Sort the combined results by date
    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Limit to 10 items
    $timeline = array_slice($timeline, 0, 10);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Dashboard | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
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

        <!-- Search Modal -->
        <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-transparent">
                    <div class="card mb-0 shadow-none">
                        <div class="px-3 py-2 d-flex flex-row align-items-center" id="top-search">
                            <i class="ti ti-search fs-22"></i>
                            <input type="search" class="form-control border-0" id="search-modal-input" placeholder="Search for actions, people,">
                            <button type="button" class="btn p-0" data-bs-dismiss="modal" aria-label="Close">[esc]</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->
        <div class="page-content">
            <div class="page-container">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Dashboard</h4>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <form action="javascript:void(0);">
                                    <div class="row g-2 mb-0 align-items-center">
                                        <div class="col-auto">
                                            <a href="javascript: void(0);" class="btn btn-outline-primary">
                                                <i class="ti ti-sort-ascending me-1"></i> Sort By
                                            </a>
                                        </div>
                                        <!--end col-->
                                        <div class="col-sm-auto">
                                            <div class="input-group">
                                                <input type="text" class="form-control" data-provider="flatpickr" data-deafult-date="01 May to 31 May" data-date-format="d M" data-range-date="true">
                                                <span class="input-group-text bg-primary border-primary text-white">
                                                    <i class="ti ti-calendar fs-15"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <!--end col-->
                                    </div>
                                    <!--end row-->
                                </form>
                            </div>
                        </div><!-- end card header -->
                    </div>
                    <!--end col-->
                </div> <!-- end row-->

                <div class="row">
                    <div class="col">
                        <div class="row row-cols-xxl-4 row-cols-md-2 row-cols-1 text-center">
                            <?php if ($userType === 'admin'): ?>
                            <!-- Admin Stats -->
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Total Users</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-users"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $totalUsers; ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Total Trainers</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-user-check"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $totalTrainers; ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Total Bookings</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-calendar-event"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $totalBookings; ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Total Revenue</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-currency-rupee"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold">₹<?php echo number_format($totalRevenue, 2); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php elseif ($userType === 'trainer'): ?>
                            <!-- Trainer Stats -->
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Today's Sessions</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-calendar-time"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $todaysSessions; ?></h3>
                                        </div>
                                    </div>
                                                    </div>
                                                </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Total Students</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-users"></i>
                                                </span>
                                                    </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $totalStudents; ?></h3>
                                                </div>
                                            </div>
                                                    </div>
                                                </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Completed Sessions</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-success rounded-circle fs-22">
                                                    <i class="ti ti-check"></i>
                                                </span>
                                                    </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $completedSessions; ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Pending Sessions</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-warning rounded-circle fs-22">
                                                    <i class="ti ti-clock"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $pendingSessions; ?></h3>
                                        </div>
                                    </div>
                                            </div>
                                            </div>

                            <?php else: ?>
                            <!-- User Stats -->
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">My Bookings</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                                    <i class="ti ti-calendar-event"></i>
                                                </span>
                                            </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $myBookings; ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                        </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Completed Sessions</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-success rounded-circle fs-22">
                                                    <i class="ti ti-check"></i>
                                                                    </span>
                                                                </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $completedSessions; ?></h3>
                                                                </div>
                                                            </div>
                                                                </div>
                                                            </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Pending Sessions</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-warning rounded-circle fs-22">
                                                    <i class="ti ti-clock"></i>
                                                                    </span>
                                                                </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $pendingSessions; ?></h3>
                                                                </div>
                                                            </div>
                                                                </div>
                                                            </div>

                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-muted fs-13 text-uppercase">Cancelled Sessions</h5>
                                        <div class="d-flex align-items-center justify-content-center gap-2 my-2 py-1">
                                            <div class="user-img fs-42 flex-shrink-0">
                                                <span class="avatar-title text-bg-danger rounded-circle fs-22">
                                                    <i class="ti ti-x"></i>
                                                                    </span>
                                                                </div>
                                            <h3 class="mb-0 fw-bold"><?php echo $cancelledSessions; ?></h3>
                                                                </div>
                                                            </div>
                                                                </div>
                                                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-xxl-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h4 class="header-title">Booking Statistics</h4>
                                                                </div>

                                    <div class="card-body">
                                        <div id="booking-stats-chart" class="apex-charts"></div>
                                                                </div>
                                                            </div>
                                                                </div>

                            <div class="col-xxl-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h4 class="header-title">Recent Bookings</h4>
                                                            </div>

                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-centered table-nowrap mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <?php if ($userType === 'admin' || $userType === 'trainer'): ?>
                                                        <th>User</th>
                                                        <?php endif; ?>
                                                        <?php if ($userType === 'admin' || $userType === 'user'): ?>
                                                        <th>Trainer</th>
                                                        <?php endif; ?>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentBookings as $booking): ?>
                                                    <tr>
                                                        <td>#<?php echo $booking['id']; ?></td>
                                                        <?php if ($userType === 'admin' || $userType === 'trainer'): ?>
                                                        <td><?php echo $booking['first_name'] . ' ' . $booking['last_name']; ?></td>
                                                        <?php endif; ?>
                                                        <?php if ($userType === 'admin' || $userType === 'user'): ?>
                                                        <td><?php echo $booking['trainer_fname'] . ' ' . $booking['trainer_lname']; ?></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <span class="badge <?php
                                                                switch($booking['status']) {
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
                                                                <?php echo ucfirst($booking['status']); ?>
                                                                    </span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                                </div>
                                            </div>
                                            </div>
                            </div>
                        </div>
                    </div>
                                    </div>

                <!-- Add Monthly Trends Chart -->
                <div class="row mt-4">
                    <div class="col-xxl-8">
                                <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Monthly Trends</h4>
                            </div>
                            <div class="card-body">
                                <div id="monthly-trends-chart" class="apex-charts"></div>
                            </div>
                                        </div>
                                    </div>

                    <div class="col-xxl-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Upcoming Sessions</h4>
                            </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                    <table class="table table-centered table-hover mb-0">
                                                <tbody>
                                            <?php foreach ($upcomingSessions as $session): ?>
                                                    <tr>
                                                        <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="fs-14 mb-1">
                                                                <?php 
                                                                if ($userType === 'trainer') {
                                                                    echo $session['first_name'] . ' ' . $session['last_name'];
                                                                } else {
                                                                    echo $session['trainer_fname'] . ' ' . $session['trainer_lname'];
                                                                }
                                                                ?>
                                                            </h5>
                                                            <p class="text-muted mb-0">
                                                                <?php 
                                                                echo date('d M Y', strtotime($session['date'])) . ', ' . 
                                                                     date('h:i A', strtotime($session['start_time'])) . ' - ' . 
                                                                     date('h:i A', strtotime($session['end_time'])); 
                                                                ?>
                                                            </p>
                                                        </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                    <span class="badge <?php echo $session['status'] === 'confirmed' ? 'bg-success' : 'bg-warning'; ?>">
                                                        <?php echo ucfirst($session['status']); ?>
                                                    </span>
                                                        </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                                                </div>
                                                            </div>
                                                            </div>
                                                                </div>
                                                            </div>

                <!-- Add Recent Reviews Section -->
                <div class="row mt-4">
                    <div class="col-xxl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Recent Reviews</h4>
                                                            </div>
                            <div class="card-body">
                                <?php foreach ($recentReviews as $review): ?>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-grow-1">
                                        <h5 class="fs-14 mt-0 mb-1">
                                            <?php echo $review['first_name'] . ' ' . $review['last_name']; ?>
                                            <small class="text-muted"> - reviewed <?php echo $review['trainer_fname'] . ' ' . $review['trainer_lname']; ?></small>
                                        </h5>
                                        <div class="text-warning mb-1">
                                            <?php 
                                            for ($i = 0; $i < 5; $i++) {
                                                echo $i < $review['rating'] ? '★' : '☆';
                                            }
                                            ?>
                                                                </div>
                                        <p class="text-muted"><?php echo $review['review']; ?></p>
                                                            </div>
                                                            </div>
                                <?php endforeach; ?>
                                                                </div>
                                                            </div>
                    </div>

                    <?php if ($userType === 'admin'): ?>
                    <div class="col-xxl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Top Rated Trainers</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead>
                                            <tr>
                                                <th>Trainer</th>
                                                <th>Rating</th>
                                                <th>Reviews</th>
                                                    </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trainerRatings as $trainer): ?>
                                            <tr>
                                                <td><?php echo $trainer['first_name'] . ' ' . $trainer['last_name']; ?></td>
                                                <td>
                                                    <div class="text-warning">
                                                        <?php 
                                                        $rating = round($trainer['avg_rating']);
                                                        for ($i = 0; $i < 5; $i++) {
                                                            echo $i < $rating ? '★' : '☆';
                                                        }
                                                        ?>
                                                            </div>
                                                        </td>
                                                <td><?php echo $trainer['review_count']; ?></td>
                                                    </tr>
                                            <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                                </div>
                                            </div>
                                            </div>
                                    </div>
                    <?php endif; ?>
                </div>

                <!-- Revenue Overview Section (Admin Only) -->
                <?php if ($userType === 'admin'): ?>
                <div class="row mt-4">
                    <div class="col-xxl-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Revenue Overview</h4>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="mt-3">
                                            <h4>₹<?php echo number_format($currentMonthRevenue, 2); ?></h4>
                                            <p class="text-muted">Current Month Revenue</p>
                                            <span class="badge <?php echo $revenueGrowth >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $revenueGrowth >= 0 ? '+' : ''; ?><?php echo number_format($revenueGrowth, 1); ?>%
                                            </span>
                                    </div>
                                </div>
                                    <div class="col-md-4">
                                        <div class="mt-3">
                                            <h4>₹<?php echo number_format($totalRevenue/12, 2); ?></h4>
                                            <p class="text-muted">Average Monthly Revenue</p>
                                    </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mt-3">
                                            <h4>₹<?php echo number_format($previousMonthRevenue, 2); ?></h4>
                                            <p class="text-muted">Previous Month Revenue</p>
                                    </div>
                                </div>
                                    </div>
                                    </div>
                                    </div>
                                </div>

                    <div class="col-xxl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Traffic Sources</h4>
                                    </div>
                            <div class="card-body">
                                <div id="traffic-sources-chart" class="apex-charts"></div>
                                    </div>
                                    </div>
                                </div>
                </div>
                <?php endif; ?>

                <!-- Top Performing Trainers -->
                <?php if ($userType === 'admin'): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Top Performing Trainers</h4>
                                <a href="trainers.php" class="btn btn-sm btn-primary">View All</a>
                                    </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead>
                                            <tr>
                                                <th>Trainer</th>
                                                <th>Total Bookings</th>
                                                <th>Completed Sessions</th>
                                                <th>Rating</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topTrainers as $trainer): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0">
                                                            <img src="<?php echo $uri.$trainer['profile_img']; ?>" class="rounded-circle" width="40">
                                    </div>
                                                        <div class="flex-grow-1 ms-2">
                                                            <h5 class="mb-0"><?php echo $trainer['first_name'] . ' ' . $trainer['last_name']; ?></h5>
                                                            <p class="mb-0 text-muted"><?php echo $trainer['designation']; ?></p>
                                    </div>
                                </div>
                                                </td>
                                                <td><?php echo $trainer['total_bookings']; ?></td>
                                                <td><?php echo $trainer['completed_sessions']; ?></td>
                                                <td>
                                                    <div class="text-warning">
                                                        <?php 
                                                        $rating = round($trainer['avg_rating']);
                                                        for ($i = 0; $i < 5; $i++) {
                                                            echo $i < $rating ? '★' : '☆';
                                                        }
                                                        ?>
                                                            </div>
                                                        </td>
                                                <td>
                                                    <?php 
                                                    $performance = $trainer['total_bookings'] > 0 ? 
                                                        ($trainer['completed_sessions'] / $trainer['total_bookings']) * 100 : 0;
                                                    $performanceClass = $performance >= 80 ? 'success' : ($performance >= 60 ? 'warning' : 'danger');
                                                    ?>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar bg-<?php echo $performanceClass; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $performance; ?>%">
                                    </div>
                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                            </div>
                                            </div>
                                        </div>
                <?php endif; ?>

                <!-- Activity Timeline -->
                <div class="row mt-4">
                    <div class="col-xxl-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="header-title">Recent Activity</h4>
                                            </div>
                            <div class="card-body">
                                <div class="timeline-alt pb-0">
                                    <?php foreach ($timeline as $activity): ?>
                                        <div class="timeline-item">
                                        <i class="<?php echo $activity['type'] === 'booking' ? 'ti ti-calendar' : 'ti ti-star'; ?> 
                                                  bg-<?php echo $activity['type'] === 'booking' ? 'info' : 'warning'; ?>-subtle 
                                                  text-<?php echo $activity['type'] === 'booking' ? 'info' : 'warning'; ?> timeline-icon"></i>
                                            <div class="timeline-item-info">
                                            <a href="javascript:void(0);" class="text-body fw-semibold mb-1 d-block">
                                                <?php 
                                                if ($activity['type'] === 'booking') {
                                                    echo $activity['user_name'] . ' booked a session with ' . $activity['trainer_name'];
                                                } else {
                                                    echo $activity['user_name'] . ' reviewed ' . $activity['trainer_name'];
                                                }
                                                ?>
                                            </a>
                                            <p class="mb-0 pb-2">
                                                <small class="text-muted"><?php echo date('j M Y, g:i a', strtotime($activity['date'])); ?></small>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                            </div>
                                        </div>
                                            </div>
                                        </div>

                    <div class="col-xxl-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Detailed Analytics</h4>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        This Month
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="#" class="dropdown-item">Last Month</a>
                                        <a href="#" class="dropdown-item">This Year</a>
                                        <a href="#" class="dropdown-item">Last Year</a>
                                            </div>
                                        </div>
                                            </div>
                            <div class="card-body">
                                <div id="detailed-analytics-chart" class="apex-charts"></div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>

            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->
        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core JS -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>


    <!-- Apex Charts js -->
    <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>



    <!-- Initialize Bootstrap Components -->
    <script>
        // Initialize all popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                container: 'body'
            })
        });

        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                container: 'body'
            })
        });

        // Initialize all dropdowns
        var dropdownTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'))
        var dropdownList = dropdownTriggerList.map(function (dropdownTriggerEl) {
            return new bootstrap.Dropdown(dropdownTriggerEl, {
                popperConfig: {
                    strategy: 'fixed'
                }
            })
        });

        // Booking Statistics Chart
        var options = {
            series: [{
                name: 'Completed',
                data: [<?php echo $completedSessions; ?>]
            }, {
                name: 'Pending',
                data: [<?php echo $pendingSessions; ?>]
            }, {
                name: 'Cancelled',
                data: [<?php echo $cancelledSessions; ?>]
            }],
            chart: {
                type: 'bar',
                height: 350,
                stacked: true,
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                }
            },
            stroke: {
                width: 1,
                colors: ['#fff']
            },
            xaxis: {
                categories: ['Sessions'],
            },
            yaxis: {
                title: {
                    text: 'Number of Sessions'
                }
            },
            fill: {
                opacity: 1
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                offsetX: 40
            },
            colors: ['#28a745', '#ffc107', '#dc3545']
        };

        var chart = new ApexCharts(document.querySelector("#booking-stats-chart"), options);
        chart.render();

        // Monthly Trends Chart
        var monthlyTrendsOptions = {
            series: [{
                name: 'Bookings',
                type: 'column',
                data: <?php echo json_encode($monthlyBookings); ?>
            }<?php if ($userType === 'admin'): ?>, {
                name: 'Revenue',
                type: 'line',
                data: <?php echo json_encode($monthlyRevenue); ?>
            }<?php endif; ?>],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [0, 4]
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [1]
            },
            labels: <?php echo json_encode($months); ?>,
            xaxis: {
                type: 'category'
            },
            yaxis: [{
                title: {
                    text: 'Number of Bookings'
                }
            }<?php if ($userType === 'admin'): ?>, {
                opposite: true,
                title: {
                    text: 'Revenue (₹)'
                }
            }<?php endif; ?>],
            colors: ['#6c757d', '#0acf97']
        };

        var monthlyTrendsChart = new ApexCharts(
            document.querySelector("#monthly-trends-chart"), 
            monthlyTrendsOptions
        );
        monthlyTrendsChart.render();

        // Traffic Sources Chart (Admin Only)
        <?php if ($userType === 'admin'): ?>
        var trafficOptions = {
            series: [<?php echo $trafficSources['direct_users']; ?>, <?php echo $trafficSources['trainer_users']; ?>],
            chart: {
                type: 'donut',
                height: 300
            },
            labels: ['Direct Users', 'Trainer Users'],
            colors: ['#6c757d', '#0acf97'],
            legend: {
                position: 'bottom'
            }
        };

        var trafficChart = new ApexCharts(
            document.querySelector("#traffic-sources-chart"), 
            trafficOptions
        );
        trafficChart.render();
        <?php endif; ?>

        // Detailed Analytics Chart
        var detailedAnalyticsOptions = {
            series: [{
                name: 'Sessions',
                data: <?php echo json_encode($monthlyBookings); ?>
            }],
            chart: {
                type: 'area',
                height: 350,
                toolbar: {
                    show: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            xaxis: {
                categories: <?php echo json_encode($months); ?>
            },
            colors: ['#0acf97']
        };

        var detailedAnalyticsChart = new ApexCharts(
            document.querySelector("#detailed-analytics-chart"), 
            detailedAnalyticsOptions
        );
        detailedAnalyticsChart.render();
    </script>
</body>
</html>