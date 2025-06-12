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
    $meeting_id = mysqli_real_escape_string($conn, $_POST['meeting_id']);
    $passcode = mysqli_real_escape_string($conn, $_POST['passcode']);
    
    $sql = "UPDATE workshops SET 
            meeting_id = '$meeting_id',
            passcode = '$passcode'
            WHERE id = $workshop_id";
            
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_message'] = "Zoom meeting details updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating Zoom meeting details: " . mysqli_error($conn);
    }
}

// Redirect back to workshop details
header("Location: workshop-details.php?id=" . $workshop_id);
exit(); 