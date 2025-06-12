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

// Check if event ID is provided
if (!isset($_GET['id'])) {
    header("Location: events.php");
    exit();
}

$event_id = (int)$_GET['id'];

// First get the event details to delete the image file
$sql = "SELECT image FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

// Delete the event
$sql = "DELETE FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);

if (mysqli_stmt_execute($stmt)) {
    // Delete the image file if it exists
    if ($event && $event['image'] && file_exists($event['image'])) {
        unlink($event['image']);
    }
    $_SESSION['success'] = "Event deleted successfully";
} else {
    $_SESSION['error'] = "Error deleting event: " . mysqli_error($conn);
}

header("Location: events.php");
exit();
?> 