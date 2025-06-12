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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    
    // Check if category already exists
    $check_sql = "SELECT id FROM blog_categories WHERE name = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $name);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $_SESSION['error'] = "Category already exists";
    } else {
        // Insert new category
        $sql = "INSERT INTO blog_categories (name, created_at, updated_at) VALUES (?, NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $name);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Category added successfully";
        } else {
            $_SESSION['error'] = "Error adding category: " . mysqli_error($conn);
        }
    }
}

header("Location: blog_categories.php");
exit();
?> 