<?php
include 'config/show_errors.php';
$conn = require_once 'config/config.php';

// Create discarded_entries table
$sql = "CREATE TABLE IF NOT EXISTS discarded_entries (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mobile VARCHAR(255) NOT NULL,
    payment_id VARCHAR(255) NOT NULL,
    workshop_id BIGINT(20) UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    csv_data TEXT NOT NULL,
    verification_token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workshop_id) REFERENCES workshops(id)
)";

if (mysqli_query($conn, $sql)) {
    echo "Table discarded_entries created successfully";
} else {
    echo "Error creating table: " . mysqli_error($conn);
} 