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

// Check if review ID is provided
if (!isset($_GET['id'])) {
    header("Location: reviews.php");
    exit();
}

$review_id = (int)$_GET['id'];

// Delete review
$sql = "DELETE FROM trainer_reviews WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $review_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "Review deleted successfully";
} else {
    $_SESSION['error'] = "Error deleting review: " . mysqli_error($conn);
}

header("Location: reviews.php");
exit();
?> 