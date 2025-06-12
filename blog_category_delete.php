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

// Check if category ID is provided
if (!isset($_GET['id'])) {
    header("Location: blog_categories.php");
    exit();
}

$category_id = (int)$_GET['id'];

// Check if category is being used in any blogs
$check_sql = "SELECT COUNT(*) as count FROM blogs WHERE category_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $category_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    $_SESSION['error'] = "Cannot delete category: It is being used by one or more blogs";
} else {
    // Delete the category
    $sql = "DELETE FROM blog_categories WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Category deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting category: " . mysqli_error($conn);
    }
}

header("Location: blog_categories.php");
exit();
?> 