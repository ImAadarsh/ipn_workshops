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

// Check if blog ID is provided
if (!isset($_GET['id'])) {
    header("Location: blogs.php");
    exit();
}

$blog_id = (int)$_GET['id'];

// Soft delete the blog (update is_deleted flag)
$sql = "UPDATE blogs SET is_deleted = 1 WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $blog_id);

if (mysqli_stmt_execute($stmt)) {
    // Success
    header("Location: blogs.php");
    exit();
} else {
    // Error
    die("Error deleting blog: " . mysqli_error($conn));
}
?> 