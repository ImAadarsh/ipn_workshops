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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workshop_id = (int)$_POST['workshop_id'];
    $rlink = mysqli_real_escape_string($conn, $_POST['rlink']);
    
    $sql = "UPDATE workshops SET rlink = '$rlink' WHERE id = $workshop_id";
            
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_message'] = "Recording link updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating recording link: " . mysqli_error($conn);
    }
}

// Redirect back to workshop details
header("Location: workshop-details.php?id=" . $workshop_id);
exit(); 