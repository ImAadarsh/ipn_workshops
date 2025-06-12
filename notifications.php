<?php
session_start();
require_once 'config/config.php';

// Function to get notifications
function getNotifications($conn, $user_id, $user_type) {
    $notifications = [];
    
    if ($user_type === 'admin') {
        // For admin - get new user registrations, new bookings, and trainer reviews
        $query = "SELECT 
            'new_user' as type,
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.created_at as time,
            'New user registration' as message
            FROM users u 
            WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT 
            'new_booking' as type,
            b.id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            b.created_at as time,
            'New booking created' as message
            FROM bookings b 
            JOIN users u ON b.user_id = u.id
            WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT 
            'new_review' as type,
            tr.id,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            tr.created_at as time,
            'New trainer review' as message
            FROM trainer_reviews tr
            JOIN users u ON tr.user_id = u.id
            WHERE tr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY time DESC LIMIT 10";
    } else {
        // For regular users - get their booking status updates and trainer responses
        $query = "SELECT 
            'booking_update' as type,
            b.id,
            CONCAT(t.first_name, ' ', t.last_name) as name,
            b.updated_at as time,
            CASE 
                WHEN b.status = 'confirmed' THEN 'Booking confirmed'
                WHEN b.status = 'completed' THEN 'Session completed'
                WHEN b.status = 'cancelled' THEN 'Booking cancelled'
                ELSE 'Booking status updated'
            END as message
            FROM bookings b
            JOIN time_slots ts ON b.time_slot_id = ts.id
            JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
            JOIN trainers t ON ta.trainer_id = t.id
            WHERE b.user_id = ? AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY time DESC LIMIT 10";
    }

    $stmt = mysqli_prepare($conn, $query);
    if ($user_type !== 'admin') {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'type' => $row['type'],
            'id' => $row['id'],
            'name' => $row['name'],
            'time' => $row['time'],
            'message' => $row['message']
        ];
    }
    
    mysqli_stmt_close($stmt);
    return $notifications;
}

// Get notifications
$notifications = getNotifications($conn, $_SESSION['user_id'], $_SESSION['user_type']);

// Return JSON if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($notifications);
    exit;
}
?> 