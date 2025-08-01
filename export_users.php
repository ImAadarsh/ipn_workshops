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

// Get export format
$format = isset($_GET['export']) ? $_GET['export'] : 'csv';

// Get search and filter parameters (same as user_management.php)
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_membership = isset($_GET['filter_membership']) ? $_GET['filter_membership'] : '';
$filter_school = isset($_GET['filter_school']) ? intval($_GET['filter_school']) : '';

// Build the query
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR u.institute_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if (!empty($filter_type)) {
    $where_conditions[] = "u.user_type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if (!empty($filter_membership)) {
    $where_conditions[] = "u.membership = ?";
    $params[] = $filter_membership;
    $param_types .= "s";
}

if (!empty($filter_school)) {
    $where_conditions[] = "u.school_id = ?";
    $params[] = $filter_school;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get users
$sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation, u.institute_name, u.city, 
               u.user_type, u.membership, u.email_verified_at, u.tlc_2025, u.created_at,
               s.name as school_name
        FROM users u 
        LEFT JOIN schools s ON u.school_id = s.id 
        WHERE $where_clause 
        ORDER BY u.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Set headers for download
$filename = "users_export_" . date('Y-m-d_H-i-s') . "." . $format;
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'ID', 'Name', 'Email', 'Mobile', 'Designation', 'Institute Name', 'City',
    'User Type', 'Membership', 'Email Verified', 'TLC 2025', 'School Name', 'Created At'
];
fputcsv($output, $headers);

// Add data rows
while ($user = mysqli_fetch_assoc($result)) {
    $row = [
        $user['id'],
        $user['name'],
        $user['email'],
        $user['mobile'],
        $user['designation'],
        $user['institute_name'],
        $user['city'],
        $user['user_type'],
        $user['membership'],
        $user['email_verified_at'] ? 'Yes' : 'No',
        $user['tlc_2025'] ? 'Yes' : 'No',
        $user['school_name'],
        $user['created_at']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?> 