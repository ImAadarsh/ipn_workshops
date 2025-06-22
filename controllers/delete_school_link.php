<?php
include '../config/show_errors.php';
session_start();
$conn = require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$link_id = isset($_POST['link_id']) ? intval($_POST['link_id']) : 0;
$workshop_id = isset($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;

if (!$link_id || !$workshop_id) {
    die('Missing link_id or workshop_id.');
}

$delete = mysqli_query($conn, "DELETE FROM school_links WHERE id = $link_id");
if (!$delete) {
    die('Delete failed: ' . mysqli_error($conn));
}

header('Location: ../school_links.php?workshop_id=' . $workshop_id);
exit; 