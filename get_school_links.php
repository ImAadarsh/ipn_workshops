<?php
// get_school_links.php - AJAX endpoint to fetch school links for a specific school
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = require_once 'config/config.php';

// Get school_id from request
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID is required']);
    exit();
}

// Fetch links for the specific school
$links = [];
$links_result = mysqli_query($conn, "SELECT sl.*, w.name as workshop_name, w.start_date FROM school_links sl JOIN workshops w ON sl.workshop_id = w.id WHERE sl.school_id = $school_id ORDER BY sl.created_at DESC");

if (!$links_result) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}

while ($row = mysqli_fetch_assoc($links_result)) {
    // Format the start date
    $start_date = new DateTime($row['start_date']);
    $row['start_date'] = $start_date->format('d M Y');
    
    // Format the created_at date
    $created_at = new DateTime($row['created_at']);
    $row['created_at'] = $created_at->format('d M Y, h:i A');
    
    $links[] = $row;
}

echo json_encode(['success' => true, 'links' => $links]);
?> 