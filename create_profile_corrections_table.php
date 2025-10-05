<?php
require_once 'config/config.php';

// Create profile_correction_requests table
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `profile_correction_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(50) NOT NULL,
  `institute_name` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_processed_by` (`processed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    // Execute table creation query
    if (mysqli_query($conn, $create_table_sql)) {
        echo "✅ profile_correction_requests table created successfully<br>";
    } else {
        echo "❌ Error creating profile_correction_requests table: " . mysqli_error($conn) . "<br>";
    }
    
    echo "<br><strong>Database setup completed!</strong><br>";
    echo "<a href='admin_profile_corrections.php'>Go to Profile Corrections Admin</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
