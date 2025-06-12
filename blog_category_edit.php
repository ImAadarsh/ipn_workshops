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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id']) && !empty($_POST['name'])) {
    $id = (int)$_POST['id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    
    // Check if category already exists with this name (excluding current category)
    $check_sql = "SELECT id FROM blog_categories WHERE name = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "si", $name, $id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $_SESSION['error'] = "Category with this name already exists";
    } else {
        // Update category
        $sql = "UPDATE blog_categories SET name = ?, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $name, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Category updated successfully";
        } else {
            $_SESSION['error'] = "Error updating category: " . mysqli_error($conn);
        }
    }
}

header("Location: blog_categories.php");
exit();
?> 