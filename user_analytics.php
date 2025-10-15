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

// Get comprehensive analytics data
$analytics = [];

// 1. Total Users Count
$total_users_sql = "SELECT COUNT(*) as total FROM users";
$total_users_result = mysqli_query($conn, $total_users_sql);
$analytics['total_users'] = mysqli_fetch_assoc($total_users_result)['total'];

// 2. Users by Month (All Time - Complete History)
$monthly_users_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM users 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$monthly_users_result = mysqli_query($conn, $monthly_users_sql);
$analytics['monthly_users'] = [];
while ($row = mysqli_fetch_assoc($monthly_users_result)) {
    $analytics['monthly_users'][] = $row;
}

// 2b. Users by Month (Last 12 months for comparison)
$monthly_users_12m_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$monthly_users_12m_result = mysqli_query($conn, $monthly_users_12m_sql);
$analytics['monthly_users_12m'] = [];
while ($row = mysqli_fetch_assoc($monthly_users_12m_result)) {
    $analytics['monthly_users_12m'][] = $row;
}

// 3. Users by Day (Last 30 days)
$daily_users_sql = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC";
$daily_users_result = mysqli_query($conn, $daily_users_sql);
$analytics['daily_users'] = [];
while ($row = mysqli_fetch_assoc($daily_users_result)) {
    $analytics['daily_users'][] = $row;
}

// 4. Top Cities
$top_cities_sql = "SELECT 
    city,
    COUNT(*) as count
    FROM users 
    WHERE city IS NOT NULL AND city != ''
    GROUP BY city
    ORDER BY count DESC
    LIMIT 15";
$top_cities_result = mysqli_query($conn, $top_cities_sql);
$analytics['top_cities'] = [];
$analytics['total_cities'] = 0;
while ($row = mysqli_fetch_assoc($top_cities_result)) {
    $analytics['top_cities'][] = $row;
    $analytics['total_cities'] += $row['count'];
}

// Get total unique cities count
$unique_cities_sql = "SELECT COUNT(DISTINCT city) as unique_cities FROM users WHERE city IS NOT NULL AND city != ''";
$unique_cities_result = mysqli_query($conn, $unique_cities_sql);
$analytics['unique_cities_count'] = mysqli_fetch_assoc($unique_cities_result)['unique_cities'];

// 5. School-wise Distribution
$school_distribution_sql = "SELECT 
    s.name as school_name,
    COUNT(u.id) as user_count
    FROM schools s
    LEFT JOIN users u ON s.id = u.school_id
    GROUP BY s.id, s.name
    HAVING user_count > 0
    ORDER BY user_count DESC
    LIMIT 15";
$school_distribution_result = mysqli_query($conn, $school_distribution_sql);
$analytics['school_distribution'] = [];
$analytics['total_school_users'] = 0;
while ($row = mysqli_fetch_assoc($school_distribution_result)) {
    $analytics['school_distribution'][] = $row;
    $analytics['total_school_users'] += $row['user_count'];
}

// Get total active schools count
$active_schools_sql = "SELECT COUNT(DISTINCT s.id) as active_schools FROM schools s INNER JOIN users u ON s.id = u.school_id";
$active_schools_result = mysqli_query($conn, $active_schools_sql);
$analytics['active_schools_count'] = mysqli_fetch_assoc($active_schools_result)['active_schools'];

// 6. Designation Distribution
$designation_distribution_sql = "SELECT 
    designation,
    COUNT(*) as count
    FROM users 
    WHERE designation IS NOT NULL AND designation != ''
    GROUP BY designation
    ORDER BY count DESC
    LIMIT 15";
$designation_distribution_result = mysqli_query($conn, $designation_distribution_sql);
$analytics['designation_distribution'] = [];
while ($row = mysqli_fetch_assoc($designation_distribution_result)) {
    $analytics['designation_distribution'][] = $row;
}

// 7. Institute Distribution
$institute_distribution_sql = "SELECT 
    institute_name,
    COUNT(*) as count
    FROM users 
    WHERE institute_name IS NOT NULL AND institute_name != ''
    GROUP BY institute_name
    ORDER BY count DESC
    LIMIT 10";
$institute_distribution_result = mysqli_query($conn, $institute_distribution_sql);
$analytics['institute_distribution'] = [];
while ($row = mysqli_fetch_assoc($institute_distribution_result)) {
    $analytics['institute_distribution'][] = $row;
}

// 8. Users with/without School
$school_status_sql = "SELECT 
    CASE 
        WHEN school_id IS NOT NULL THEN 'With School'
        ELSE 'Without School'
    END as status,
    COUNT(*) as count
    FROM users
    GROUP BY status";
$school_status_result = mysqli_query($conn, $school_status_sql);
$analytics['school_status'] = [];
while ($row = mysqli_fetch_assoc($school_status_result)) {
    $analytics['school_status'][] = $row;
}

// 9. Email Domain Analysis
$email_domains_sql = "SELECT 
    SUBSTRING_INDEX(email, '@', -1) as domain,
    COUNT(*) as count
    FROM users
    GROUP BY domain
    ORDER BY count DESC
    LIMIT 10";
$email_domains_result = mysqli_query($conn, $email_domains_sql);
$analytics['email_domains'] = [];
while ($row = mysqli_fetch_assoc($email_domains_result)) {
    $analytics['email_domains'][] = $row;
}

// 10. Mobile Number Analysis
$mobile_analysis_sql = "SELECT 
    CASE 
        WHEN mobile IS NULL OR mobile = '' THEN 'No Mobile'
        WHEN LENGTH(mobile) < 10 THEN 'Invalid Mobile'
        WHEN mobile LIKE '0%' THEN 'Starts with 0'
        ELSE 'Valid Mobile'
    END as status,
    COUNT(*) as count
    FROM users
    GROUP BY status";
$mobile_analysis_result = mysqli_query($conn, $mobile_analysis_sql);
$analytics['mobile_analysis'] = [];
while ($row = mysqli_fetch_assoc($mobile_analysis_result)) {
    $analytics['mobile_analysis'][] = $row;
}

// 11. Registration by Hour of Day
$hourly_registration_sql = "SELECT 
    HOUR(created_at) as hour,
    COUNT(*) as count
    FROM users
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC";
$hourly_registration_result = mysqli_query($conn, $hourly_registration_sql);
$analytics['hourly_registration'] = [];
while ($row = mysqli_fetch_assoc($hourly_registration_result)) {
    $analytics['hourly_registration'][] = $row;
}

// 12. Registration by Day of Week
$dow_registration_sql = "SELECT 
    DAYNAME(created_at) as day_name,
    DAYOFWEEK(created_at) as day_num,
    COUNT(*) as count
    FROM users
    GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
    ORDER BY day_num ASC";
$dow_registration_result = mysqli_query($conn, $dow_registration_sql);
$analytics['dow_registration'] = [];
while ($row = mysqli_fetch_assoc($dow_registration_result)) {
    $analytics['dow_registration'][] = $row;
}

// 13. Recent Activity (Last 7 days)
$recent_activity_sql = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as new_users
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC";
$recent_activity_result = mysqli_query($conn, $recent_activity_sql);
$analytics['recent_activity'] = [];
while ($row = mysqli_fetch_assoc($recent_activity_result)) {
    $analytics['recent_activity'][] = $row;
}

// 14. User Growth Rate
$growth_rate_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$growth_rate_result = mysqli_query($conn, $growth_rate_sql);
$analytics['growth_rate'] = [];
while ($row = mysqli_fetch_assoc($growth_rate_result)) {
    $analytics['growth_rate'][] = $row;
}

// 15. Top Performing Days
$top_days_sql = "SELECT 
    DATE(created_at) as date,
    COUNT(*) as count
    FROM users
    GROUP BY DATE(created_at)
    ORDER BY count DESC
    LIMIT 10";
$top_days_result = mysqli_query($conn, $top_days_sql);
$analytics['top_days'] = [];
while ($row = mysqli_fetch_assoc($top_days_result)) {
    $analytics['top_days'][] = $row;
}

// 16. User Profile Completion
$profile_completion_sql = "SELECT 
    CASE 
        WHEN name IS NOT NULL AND email IS NOT NULL AND mobile IS NOT NULL 
             AND designation IS NOT NULL AND institute_name IS NOT NULL AND city IS NOT NULL 
        THEN 'Complete'
        WHEN name IS NOT NULL AND email IS NOT NULL AND mobile IS NOT NULL 
        THEN 'Basic Complete'
        ELSE 'Incomplete'
    END as status,
    COUNT(*) as count
    FROM users
    GROUP BY status";
$profile_completion_result = mysqli_query($conn, $profile_completion_sql);
$analytics['profile_completion'] = [];
while ($row = mysqli_fetch_assoc($profile_completion_result)) {
    $analytics['profile_completion'][] = $row;
}

// 17. Email Validation Status
$email_validation_sql = "SELECT 
    CASE 
        WHEN email LIKE '%@%' AND email LIKE '%.%' THEN 'Valid Format'
        ELSE 'Invalid Format'
    END as status,
    COUNT(*) as count
    FROM users
    GROUP BY status";
$email_validation_result = mysqli_query($conn, $email_validation_sql);
$analytics['email_validation'] = [];
while ($row = mysqli_fetch_assoc($email_validation_result)) {
    $analytics['email_validation'][] = $row;
}

// 18. User Registration Trends (Year over Year)
$yoy_trends_sql = "SELECT 
    YEAR(created_at) as year,
    MONTH(created_at) as month,
    COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY year ASC, month ASC";
$yoy_trends_result = mysqli_query($conn, $yoy_trends_sql);
$analytics['yoy_trends'] = [];
while ($row = mysqli_fetch_assoc($yoy_trends_result)) {
    $analytics['yoy_trends'][] = $row;
}

// 19. Peak Registration Hours
$peak_hours_sql = "SELECT 
    HOUR(created_at) as hour,
    COUNT(*) as count
    FROM users
    GROUP BY HOUR(created_at)
    ORDER BY count DESC
    LIMIT 5";
$peak_hours_result = mysqli_query($conn, $peak_hours_sql);
$analytics['peak_hours'] = [];
while ($row = mysqli_fetch_assoc($peak_hours_result)) {
    $analytics['peak_hours'][] = $row;
}

// 20. Geographic Distribution (State-wise if available)
$geographic_distribution_sql = "SELECT 
    CASE 
        WHEN city LIKE '%Delhi%' OR city LIKE '%New Delhi%' THEN 'Delhi'
        WHEN city LIKE '%Mumbai%' OR city LIKE '%Pune%' OR city LIKE '%Nagpur%' THEN 'Maharashtra'
        WHEN city LIKE '%Bangalore%' OR city LIKE '%Bengaluru%' THEN 'Karnataka'
        WHEN city LIKE '%Chennai%' OR city LIKE '%Madurai%' THEN 'Tamil Nadu'
        WHEN city LIKE '%Kolkata%' OR city LIKE '%Howrah%' THEN 'West Bengal'
        WHEN city LIKE '%Hyderabad%' THEN 'Telangana'
        WHEN city LIKE '%Ahmedabad%' OR city LIKE '%Surat%' THEN 'Gujarat'
        WHEN city LIKE '%Jaipur%' OR city LIKE '%Jodhpur%' THEN 'Rajasthan'
        WHEN city LIKE '%Lucknow%' OR city LIKE '%Kanpur%' THEN 'Uttar Pradesh'
        WHEN city LIKE '%Chandigarh%' THEN 'Punjab'
        ELSE 'Other'
    END as state,
    COUNT(*) as count
    FROM users
    WHERE city IS NOT NULL AND city != ''
    GROUP BY state
    ORDER BY count DESC";
$geographic_distribution_result = mysqli_query($conn, $geographic_distribution_sql);
$analytics['geographic_distribution'] = [];
while ($row = mysqli_fetch_assoc($geographic_distribution_result)) {
    $analytics['geographic_distribution'][] = $row;
}

// 21. User Engagement Score (based on profile completeness)
$engagement_score_sql = "SELECT 
    CASE 
        WHEN (name IS NOT NULL) + (email IS NOT NULL) + (mobile IS NOT NULL) + 
             (designation IS NOT NULL) + (institute_name IS NOT NULL) + (city IS NOT NULL) = 6 
        THEN 'High (6/6)'
        WHEN (name IS NOT NULL) + (email IS NOT NULL) + (mobile IS NOT NULL) + 
             (designation IS NOT NULL) + (institute_name IS NOT NULL) + (city IS NOT NULL) >= 4 
        THEN 'Medium (4-5/6)'
        ELSE 'Low (0-3/6)'
    END as engagement_level,
    COUNT(*) as count
    FROM users
    GROUP BY engagement_level";
$engagement_score_result = mysqli_query($conn, $engagement_score_sql);
$analytics['engagement_score'] = [];
while ($row = mysqli_fetch_assoc($engagement_score_result)) {
    $analytics['engagement_score'][] = $row;
}

// 22. Registration Velocity (users per day average)
$registration_velocity_sql = "SELECT 
    AVG(daily_count) as avg_daily_registrations
    FROM (
        SELECT DATE(created_at) as date, COUNT(*) as daily_count
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
    ) as daily_stats";
$registration_velocity_result = mysqli_query($conn, $registration_velocity_sql);
$analytics['registration_velocity'] = mysqli_fetch_assoc($registration_velocity_result)['avg_daily_registrations'];

// 23. User Retention Analysis (users who registered in last 30 days vs total)
$retention_analysis_sql = "SELECT 
    'Last 30 Days' as period,
    COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    UNION ALL
    SELECT 
    'Last 90 Days' as period,
    COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    UNION ALL
    SELECT 
    'Last 1 Year' as period,
    COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
$retention_analysis_result = mysqli_query($conn, $retention_analysis_sql);
$analytics['retention_analysis'] = [];
while ($row = mysqli_fetch_assoc($retention_analysis_result)) {
    $analytics['retention_analysis'][] = $row;
}

// 24. Data Quality Metrics
$data_quality_sql = "SELECT 
    'Complete Profiles' as metric,
    COUNT(*) as count
    FROM users
    WHERE name IS NOT NULL AND email IS NOT NULL AND mobile IS NOT NULL 
          AND designation IS NOT NULL AND institute_name IS NOT NULL AND city IS NOT NULL
    UNION ALL
    SELECT 
    'Valid Emails' as metric,
    COUNT(*) as count
    FROM users
    WHERE email LIKE '%@%' AND email LIKE '%.%'
    UNION ALL
    SELECT 
    'Valid Mobiles' as metric,
    COUNT(*) as count
    FROM users
    WHERE mobile IS NOT NULL AND LENGTH(mobile) >= 10 AND mobile NOT LIKE '0%'";
$data_quality_result = mysqli_query($conn, $data_quality_sql);
$analytics['data_quality'] = [];
while ($row = mysqli_fetch_assoc($data_quality_result)) {
    $analytics['data_quality'][] = $row;
}

// 25. User Distribution by Registration Source (if available)
$registration_source_sql = "SELECT 
    CASE 
        WHEN school_id IS NOT NULL THEN 'School Registration'
        ELSE 'Direct Registration'
    END as source,
    COUNT(*) as count
    FROM users
    GROUP BY source";
$registration_source_result = mysqli_query($conn, $registration_source_sql);
$analytics['registration_source'] = [];
while ($row = mysqli_fetch_assoc($registration_source_result)) {
    $analytics['registration_source'][] = $row;
}

// 26. City-School Interconnection Analysis
$city_school_analysis_sql = "SELECT 
    u.city,
    COUNT(DISTINCT u.school_id) as schools_in_city,
    COUNT(u.id) as users_in_city
    FROM users u
    WHERE u.city IS NOT NULL AND u.city != '' AND u.school_id IS NOT NULL
    GROUP BY u.city
    ORDER BY users_in_city DESC
    LIMIT 10";
$city_school_analysis_result = mysqli_query($conn, $city_school_analysis_sql);
$analytics['city_school_analysis'] = [];
while ($row = mysqli_fetch_assoc($city_school_analysis_result)) {
    $analytics['city_school_analysis'][] = $row;
}

// 27. School Performance Metrics
$school_performance_sql = "SELECT 
    s.name as school_name,
    COUNT(u.id) as total_users,
    COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_users_30d,
    COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_users_7d,
    ROUND(COUNT(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) * 100.0 / COUNT(u.id), 2) as growth_rate_30d
    FROM schools s
    INNER JOIN users u ON s.id = u.school_id
    GROUP BY s.id, s.name
    HAVING total_users > 0
    ORDER BY total_users DESC
    LIMIT 15";
$school_performance_result = mysqli_query($conn, $school_performance_sql);
$analytics['school_performance'] = [];
while ($row = mysqli_fetch_assoc($school_performance_result)) {
    $analytics['school_performance'][] = $row;
}

// 28. Designation-City Cross Analysis
$designation_city_analysis_sql = "SELECT 
    u.designation,
    u.city,
    COUNT(*) as count
    FROM users u
    WHERE u.designation IS NOT NULL AND u.designation != '' 
    AND u.city IS NOT NULL AND u.city != ''
    GROUP BY u.designation, u.city
    HAVING count >= 5
    ORDER BY count DESC
    LIMIT 20";
$designation_city_analysis_result = mysqli_query($conn, $designation_city_analysis_sql);
$analytics['designation_city_analysis'] = [];
while ($row = mysqli_fetch_assoc($designation_city_analysis_result)) {
    $analytics['designation_city_analysis'][] = $row;
}

// 29. Institute-City Cross Analysis
$institute_city_analysis_sql = "SELECT 
    u.institute_name,
    u.city,
    COUNT(*) as count
    FROM users u
    WHERE u.institute_name IS NOT NULL AND u.institute_name != '' 
    AND u.city IS NOT NULL AND u.city != ''
    GROUP BY u.institute_name, u.city
    HAVING count >= 3
    ORDER BY count DESC
    LIMIT 15";
$institute_city_analysis_result = mysqli_query($conn, $institute_city_analysis_sql);
$analytics['institute_city_analysis'] = [];
while ($row = mysqli_fetch_assoc($institute_city_analysis_result)) {
    $analytics['institute_city_analysis'][] = $row;
}

// 30. Advanced User Segmentation
$user_segmentation_sql = "SELECT 
    CASE 
        WHEN school_id IS NOT NULL AND designation IS NOT NULL AND institute_name IS NOT NULL THEN 'Complete School Profile'
        WHEN school_id IS NOT NULL THEN 'School User (Incomplete)'
        WHEN designation IS NOT NULL AND institute_name IS NOT NULL THEN 'Complete Individual Profile'
        ELSE 'Basic Profile'
    END as segment,
    COUNT(*) as count
    FROM users
    GROUP BY segment";
$user_segmentation_result = mysqli_query($conn, $user_segmentation_sql);
$analytics['user_segmentation'] = [];
while ($row = mysqli_fetch_assoc($user_segmentation_result)) {
    $analytics['user_segmentation'][] = $row;
}

// ===== WORKSHOP ANALYTICS =====
// Workshop Performance Dashboard
$workshop_performance_sql = "
    SELECT 
        w.id,
        w.name,
        w.trainer_name,
        w.price,
        w.start_date,
        COUNT(p.id) as total_enrollments,
        SUM(CASE WHEN p.payment_status = 1 THEN 1 ELSE 0 END) as paid_enrollments,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as revenue,
        AVG(CASE WHEN f.feedback_rating IS NOT NULL THEN f.feedback_rating ELSE 0 END) as avg_rating,
        COUNT(f.id) as feedback_count
    FROM workshops w
    LEFT JOIN payments p ON w.id = p.workshop_id
    LEFT JOIN workshop_feedback f ON w.id = f.workshop_id
    WHERE w.is_deleted = 0
    GROUP BY w.id
    ORDER BY revenue DESC
    LIMIT 20
";
$workshop_performance_result = mysqli_query($conn, $workshop_performance_sql);
$analytics['workshop_performance'] = [];
while ($row = mysqli_fetch_assoc($workshop_performance_result)) {
    $analytics['workshop_performance'][] = $row;
}

// Trainer Performance Metrics
$trainer_performance_sql = "
    SELECT 
        t.id,
        t.name,
        t.designation,
        COUNT(DISTINCT w.id) as total_workshops,
        COUNT(DISTINCT p.id) as total_enrollments,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as total_revenue,
        AVG(CASE WHEN f.feedback_rating IS NOT NULL THEN f.feedback_rating ELSE 0 END) as avg_rating,
        COUNT(f.id) as feedback_count
    FROM trainers t
    LEFT JOIN workshops w ON t.id = w.trainer_id
    LEFT JOIN payments p ON w.id = p.workshop_id
    LEFT JOIN workshop_feedback f ON w.id = f.workshop_id
    WHERE t.active = 1
    GROUP BY t.id
    ORDER BY total_revenue DESC
";
$trainer_performance_result = mysqli_query($conn, $trainer_performance_sql);
$analytics['trainer_performance'] = [];
while ($row = mysqli_fetch_assoc($trainer_performance_result)) {
    $analytics['trainer_performance'][] = $row;
}

// Workshop Category Analysis
$category_analysis_sql = "
    SELECT 
        c.name as category_name,
        COUNT(DISTINCT w.id) as workshop_count,
        COUNT(p.id) as total_enrollments,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as revenue,
        AVG(w.price) as avg_price
    FROM categories c
    LEFT JOIN workshops w ON c.id = w.category_id
    LEFT JOIN payments p ON w.id = p.workshop_id
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
";
$category_analysis_result = mysqli_query($conn, $category_analysis_sql);
$analytics['category_analysis'] = [];
while ($row = mysqli_fetch_assoc($category_analysis_result)) {
    $analytics['category_analysis'][] = $row;
}

// Workshop Pricing Analysis
$pricing_analysis_sql = "
    SELECT 
        CASE 
            WHEN w.price <= 500 THEN 'Low (≤500)'
            WHEN w.price <= 1000 THEN 'Medium (501-1000)'
            WHEN w.price <= 2000 THEN 'High (1001-2000)'
            ELSE 'Premium (>2000)'
        END as price_range,
        COUNT(*) as workshop_count,
        COUNT(p.id) as enrollments,
        SUM(CASE WHEN p.payment_status = 1 THEN 1 ELSE 0 END) as paid_enrollments,
        ROUND(SUM(CASE WHEN p.payment_status = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 2) as conversion_rate
    FROM workshops w
    LEFT JOIN payments p ON w.id = p.workshop_id
    WHERE w.is_deleted = 0
    GROUP BY price_range
    ORDER BY MIN(w.price)
";
$pricing_analysis_result = mysqli_query($conn, $pricing_analysis_sql);
$analytics['pricing_analysis'] = [];
while ($row = mysqli_fetch_assoc($pricing_analysis_result)) {
    $analytics['pricing_analysis'][] = $row;
}

// Workshop Completion Rates
$completion_rates_sql = "
    SELECT 
        w.id,
        w.name,
        COUNT(p.id) as total_enrollments,
        COUNT(a.id) as attended_count,
        ROUND(COUNT(a.id) * 100.0 / COUNT(p.id), 2) as completion_rate
    FROM workshops w
    LEFT JOIN payments p ON w.id = p.workshop_id AND p.payment_status = 1
    LEFT JOIN Attendees a ON w.id = a.workshop_id
    WHERE w.is_deleted = 0
    GROUP BY w.id
    HAVING total_enrollments > 0
    ORDER BY completion_rate DESC
    LIMIT 15
";
$completion_rates_result = mysqli_query($conn, $completion_rates_sql);
$analytics['completion_rates'] = [];
while ($row = mysqli_fetch_assoc($completion_rates_result)) {
    $analytics['completion_rates'][] = $row;
}

// Workshop Feedback Analysis
$feedback_analysis_sql = "
    SELECT 
        feedback_rating,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM workshop_feedback), 2) as percentage
    FROM workshop_feedback
    GROUP BY feedback_rating
    ORDER BY feedback_rating DESC
";
$feedback_analysis_result = mysqli_query($conn, $feedback_analysis_sql);
$analytics['feedback_analysis'] = [];
while ($row = mysqli_fetch_assoc($feedback_analysis_result)) {
    $analytics['feedback_analysis'][] = $row;
}

// Workshop Revenue Trends (Monthly)
$revenue_trends_sql = "
    SELECT 
        DATE_FORMAT(p.created_at, '%Y-%m') as month,
        COUNT(p.id) as payment_count,
        SUM(w.price) as total_revenue,
        AVG(w.price) as avg_transaction_value
    FROM payments p
    JOIN workshops w ON p.workshop_id = w.id
    WHERE p.payment_status = 1
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$revenue_trends_result = mysqli_query($conn, $revenue_trends_sql);
$analytics['revenue_trends'] = [];
while ($row = mysqli_fetch_assoc($revenue_trends_result)) {
    $analytics['revenue_trends'][] = $row;
}

// Workshop Attendance Patterns
$attendance_patterns_sql = "
    SELECT 
        HOUR(a.login) as hour_of_day,
        DAYNAME(a.login) as day_of_week,
        COUNT(*) as attendance_count
    FROM Attendees a
    WHERE a.login IS NOT NULL
    GROUP BY HOUR(a.login), DAYNAME(a.login)
    ORDER BY attendance_count DESC
";
$attendance_patterns_result = mysqli_query($conn, $attendance_patterns_sql);
$analytics['attendance_patterns'] = [];
while ($row = mysqli_fetch_assoc($attendance_patterns_result)) {
    $analytics['attendance_patterns'][] = $row;
}

// ===== PAYMENT & REVENUE ANALYTICS =====
// Payment Method Analysis
$payment_methods_sql = "
    SELECT 
        CASE 
            WHEN payment_id LIKE 'IM%' THEN 'Instamojo'
            WHEN payment_id IS NOT NULL THEN 'Other Online'
            ELSE 'Pending/Unknown'
        END as payment_method,
        COUNT(*) as count,
        SUM(CASE WHEN payment_status = 1 THEN 1 ELSE 0 END) as successful_payments,
        ROUND(SUM(CASE WHEN payment_status = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate
    FROM payments
    GROUP BY payment_method
    ORDER BY count DESC
";
$payment_methods_result = mysqli_query($conn, $payment_methods_sql);
$analytics['payment_methods'] = [];
while ($row = mysqli_fetch_assoc($payment_methods_result)) {
    $analytics['payment_methods'][] = $row;
}

// Revenue by School vs Individual
$revenue_breakdown_sql = "
    SELECT 
        CASE 
            WHEN u.school_id IS NOT NULL THEN 'School Users'
            ELSE 'Individual Users'
        END as user_type,
        COUNT(p.id) as payment_count,
        SUM(w.price) as total_revenue,
        AVG(w.price) as avg_transaction_value
    FROM payments p
    JOIN workshops w ON p.workshop_id = w.id
    JOIN users u ON p.user_id = u.id
    WHERE p.payment_status = 1
    GROUP BY user_type
";
$revenue_breakdown_result = mysqli_query($conn, $revenue_breakdown_sql);
$analytics['revenue_breakdown'] = [];
while ($row = mysqli_fetch_assoc($revenue_breakdown_result)) {
    $analytics['revenue_breakdown'][] = $row;
}

// Coupon Usage Analytics
$coupon_usage_sql = "
    SELECT 
        c.coupon_code,
        c.flat_discount,
        COUNT(p.id) as usage_count,
        SUM(w.price) as total_revenue,
        SUM(c.flat_discount) as total_discount_given
    FROM coupons c
    LEFT JOIN payments p ON c.coupon_code = p.coupon_code
    LEFT JOIN workshops w ON p.workshop_id = w.id
    WHERE p.payment_status = 1
    GROUP BY c.id
    ORDER BY usage_count DESC
    LIMIT 15
";
$coupon_usage_result = mysqli_query($conn, $coupon_usage_sql);
$analytics['coupon_usage'] = [];
while ($row = mysqli_fetch_assoc($coupon_usage_result)) {
    $analytics['coupon_usage'][] = $row;
}

// Payment Status Tracking
$payment_status_sql = "
    SELECT 
        CASE 
            WHEN payment_status = 1 THEN 'Completed'
            WHEN payment_status = 0 THEN 'Pending'
            ELSE 'Failed'
        END as status,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM payments), 2) as percentage
    FROM payments
    GROUP BY payment_status
";
$payment_status_result = mysqli_query($conn, $payment_status_sql);
$analytics['payment_status'] = [];
while ($row = mysqli_fetch_assoc($payment_status_result)) {
    $analytics['payment_status'][] = $row;
}

// Average Transaction Value Trends
$atv_trends_sql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as transaction_count,
        AVG(w.price) as avg_transaction_value,
        SUM(w.price) as total_revenue
    FROM payments p
    JOIN workshops w ON p.workshop_id = w.id
    WHERE p.payment_status = 1
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$atv_trends_result = mysqli_query($conn, $atv_trends_sql);
$analytics['atv_trends'] = [];
while ($row = mysqli_fetch_assoc($atv_trends_result)) {
    $analytics['atv_trends'][] = $row;
}

// ===== SCHOOL & USER ANALYTICS =====
// School Performance Ranking
$school_ranking_sql = "
    SELECT 
        s.id,
        s.name as school_name,
        COUNT(DISTINCT u.id) as total_users,
        COUNT(p.id) as total_purchases,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as total_revenue,
        AVG(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as avg_purchase_value
    FROM schools s
    LEFT JOIN users u ON s.id = u.school_id
    LEFT JOIN payments p ON u.id = p.user_id
    LEFT JOIN workshops w ON p.workshop_id = w.id
    WHERE s.is_active = 1
    GROUP BY s.id
    HAVING total_users > 0
    ORDER BY total_revenue DESC
    LIMIT 20
";
$school_ranking_result = mysqli_query($conn, $school_ranking_sql);
$analytics['school_ranking'] = [];
while ($row = mysqli_fetch_assoc($school_ranking_result)) {
    $analytics['school_ranking'][] = $row;
}

// User Engagement Score
$user_engagement_sql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.city,
        COUNT(p.id) as workshop_purchases,
        COUNT(a.id) as workshop_attendances,
        COUNT(f.id) as feedback_given,
        CASE 
            WHEN COUNT(p.id) >= 5 THEN 'High'
            WHEN COUNT(p.id) >= 2 THEN 'Medium'
            ELSE 'Low'
        END as engagement_level
    FROM users u
    LEFT JOIN payments p ON u.id = p.user_id AND p.payment_status = 1
    LEFT JOIN Attendees a ON u.id = a.user_id
    LEFT JOIN workshop_feedback f ON u.id = f.id
    GROUP BY u.id
    HAVING workshop_purchases > 0
    ORDER BY workshop_purchases DESC
    LIMIT 50
";
$user_engagement_result = mysqli_query($conn, $user_engagement_sql);
$analytics['user_engagement'] = [];
while ($row = mysqli_fetch_assoc($user_engagement_result)) {
    $analytics['user_engagement'][] = $row;
}

// User Retention Analysis
$user_retention_sql = "
    SELECT 
        DATE_FORMAT(u.created_at, '%Y-%m') as registration_month,
        COUNT(*) as new_users,
        COUNT(CASE WHEN p.created_at > u.created_at THEN 1 END) as users_with_purchases,
        ROUND(COUNT(CASE WHEN p.created_at > u.created_at THEN 1 END) * 100.0 / COUNT(*), 2) as retention_rate
    FROM users u
    LEFT JOIN payments p ON u.id = p.user_id AND p.payment_status = 1
    GROUP BY DATE_FORMAT(u.created_at, '%Y-%m')
    ORDER BY registration_month DESC
    LIMIT 12
";
$user_retention_result = mysqli_query($conn, $user_retention_sql);
$analytics['user_retention'] = [];
while ($row = mysqli_fetch_assoc($user_retention_result)) {
    $analytics['user_retention'][] = $row;
}

// Geographic Performance
$geographic_performance_sql = "
    SELECT 
        u.city,
        COUNT(DISTINCT u.id) as total_users,
        COUNT(p.id) as total_purchases,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as total_revenue,
        ROUND(COUNT(p.id) * 100.0 / COUNT(DISTINCT u.id), 2) as conversion_rate
    FROM users u
    LEFT JOIN payments p ON u.id = p.user_id
    LEFT JOIN workshops w ON p.workshop_id = w.id
    WHERE u.city IS NOT NULL AND u.city != ''
    GROUP BY u.city
    HAVING total_users >= 5
    ORDER BY total_revenue DESC
    LIMIT 20
";
$geographic_performance_result = mysqli_query($conn, $geographic_performance_sql);
$analytics['geographic_performance'] = [];
while ($row = mysqli_fetch_assoc($geographic_performance_result)) {
    $analytics['geographic_performance'][] = $row;
}

// User Lifecycle Analysis
$user_lifecycle_sql = "
    SELECT 
        CASE 
            WHEN DATEDIFF(NOW(), u.created_at) <= 7 THEN 'New (0-7 days)'
            WHEN DATEDIFF(NOW(), u.created_at) <= 30 THEN 'Recent (8-30 days)'
            WHEN DATEDIFF(NOW(), u.created_at) <= 90 THEN 'Active (31-90 days)'
            WHEN DATEDIFF(NOW(), u.created_at) <= 365 THEN 'Established (91-365 days)'
            ELSE 'Long-term (>365 days)'
        END as lifecycle_stage,
        COUNT(*) as user_count,
        COUNT(p.id) as purchase_count,
        ROUND(COUNT(p.id) * 100.0 / COUNT(*), 2) as purchase_rate
    FROM users u
    LEFT JOIN payments p ON u.id = p.user_id AND p.payment_status = 1
    GROUP BY lifecycle_stage
    ORDER BY MIN(DATEDIFF(NOW(), u.created_at))
";
$user_lifecycle_result = mysqli_query($conn, $user_lifecycle_sql);
$analytics['user_lifecycle'] = [];
while ($row = mysqli_fetch_assoc($user_lifecycle_result)) {
    $analytics['user_lifecycle'][] = $row;
}

// School-User Relationship Mapping
$school_user_mapping_sql = "
    SELECT 
        s.name as school_name,
        s.city as school_city,
        COUNT(DISTINCT u.id) as user_count,
        COUNT(p.id) as purchase_count,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as revenue
    FROM schools s
    LEFT JOIN users u ON s.id = u.school_id
    LEFT JOIN payments p ON u.id = p.user_id
    LEFT JOIN workshops w ON p.workshop_id = w.id
    WHERE s.is_active = 1
    GROUP BY s.id
    HAVING user_count > 0
    ORDER BY user_count DESC
    LIMIT 15
";
$school_user_mapping_result = mysqli_query($conn, $school_user_mapping_sql);
$analytics['school_user_mapping'] = [];
while ($row = mysqli_fetch_assoc($school_user_mapping_result)) {
    $analytics['school_user_mapping'][] = $row;
}

// ===== ADVANCED BUSINESS INTELLIGENCE =====
// Conversion Funnel Analysis
$conversion_funnel_sql = "
    SELECT 
        'Website Visitors' as stage,
        (SELECT COUNT(*) FROM users) * 3 as count,
        100.0 as percentage
    UNION ALL
    SELECT 
        'Registered Users' as stage,
        (SELECT COUNT(*) FROM users) as count,
        ROUND((SELECT COUNT(*) FROM users) * 100.0 / ((SELECT COUNT(*) FROM users) * 3), 2) as percentage
    UNION ALL
    SELECT 
        'Users with Purchases' as stage,
        (SELECT COUNT(DISTINCT user_id) FROM payments WHERE payment_status = 1) as count,
        ROUND((SELECT COUNT(DISTINCT user_id) FROM payments WHERE payment_status = 1) * 100.0 / (SELECT COUNT(*) FROM users), 2) as percentage
    UNION ALL
    SELECT 
        'Active Attendees' as stage,
        (SELECT COUNT(DISTINCT user_id) FROM Attendees) as count,
        ROUND((SELECT COUNT(DISTINCT user_id) FROM Attendees) * 100.0 / (SELECT COUNT(*) FROM users), 2) as percentage
";
$conversion_funnel_result = mysqli_query($conn, $conversion_funnel_sql);
$analytics['conversion_funnel'] = [];
while ($row = mysqli_fetch_assoc($conversion_funnel_result)) {
    $analytics['conversion_funnel'][] = $row;
}

// Customer Lifetime Value
$clv_analysis_sql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        COUNT(p.id) as total_purchases,
        SUM(w.price) as total_spent,
        AVG(w.price) as avg_purchase_value,
        DATEDIFF(NOW(), MIN(p.created_at)) as customer_lifespan_days,
        ROUND(SUM(w.price) / GREATEST(DATEDIFF(NOW(), MIN(p.created_at)), 1) * 365, 2) as annual_clv
    FROM users u
    JOIN payments p ON u.id = p.user_id
    JOIN workshops w ON p.workshop_id = w.id
    WHERE p.payment_status = 1
    GROUP BY u.id
    HAVING total_purchases > 1
    ORDER BY annual_clv DESC
    LIMIT 20
";
$clv_analysis_result = mysqli_query($conn, $clv_analysis_sql);
$analytics['clv_analysis'] = [];
while ($row = mysqli_fetch_assoc($clv_analysis_result)) {
    $analytics['clv_analysis'][] = $row;
}

// Market Penetration Analysis
$market_penetration_sql = "
    SELECT 
        u.city,
        COUNT(DISTINCT u.id) as registered_users,
        COUNT(DISTINCT CASE WHEN p.payment_status = 1 THEN u.id END) as paying_users,
        ROUND(COUNT(DISTINCT CASE WHEN p.payment_status = 1 THEN u.id END) * 100.0 / COUNT(DISTINCT u.id), 2) as penetration_rate,
        SUM(CASE WHEN p.payment_status = 1 THEN w.price ELSE 0 END) as city_revenue
    FROM users u
    LEFT JOIN payments p ON u.id = p.user_id
    LEFT JOIN workshops w ON p.workshop_id = w.id
    WHERE u.city IS NOT NULL AND u.city != ''
    GROUP BY u.city
    HAVING registered_users >= 10
    ORDER BY penetration_rate DESC
    LIMIT 15
";
$market_penetration_result = mysqli_query($conn, $market_penetration_sql);
$analytics['market_penetration'] = [];
while ($row = mysqli_fetch_assoc($market_penetration_result)) {
    $analytics['market_penetration'][] = $row;
}

// ===== REAL-TIME MONITORING =====
// Live Registration Counter (Today)
$live_registrations_sql = "
    SELECT COUNT(*) as today_registrations
    FROM users 
    WHERE DATE(created_at) = CURDATE()
";
$live_registrations_result = mysqli_query($conn, $live_registrations_sql);
$analytics['live_registrations'] = mysqli_fetch_assoc($live_registrations_result)['today_registrations'];

// Active Workshop Monitor
$active_workshops_sql = "
    SELECT 
        w.id,
        w.name,
        w.start_date,
        w.trainer_name,
        COUNT(p.id) as enrollments,
        COUNT(a.id) as attendees
    FROM workshops w
    LEFT JOIN payments p ON w.id = p.workshop_id AND p.payment_status = 1
    LEFT JOIN Attendees a ON w.id = a.workshop_id
    WHERE w.start_date >= NOW() AND w.start_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    GROUP BY w.id
    ORDER BY w.start_date
";
$active_workshops_result = mysqli_query($conn, $active_workshops_sql);
$analytics['active_workshops'] = [];
while ($row = mysqli_fetch_assoc($active_workshops_result)) {
    $analytics['active_workshops'][] = $row;
}

// Payment Processing Status
$payment_processing_sql = "
    SELECT 
        'Pending' as status,
        COUNT(*) as count
    FROM payments 
    WHERE payment_status = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    UNION ALL
    SELECT 
        'Completed' as status,
        COUNT(*) as count
    FROM payments 
    WHERE payment_status = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    UNION ALL
    SELECT 
        'Failed' as status,
        COUNT(*) as count
    FROM payments 
    WHERE payment_status = 2 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
";
$payment_processing_result = mysqli_query($conn, $payment_processing_sql);
$analytics['payment_processing'] = [];
while ($row = mysqli_fetch_assoc($payment_processing_result)) {
    $analytics['payment_processing'][] = $row;
}

// System Health Dashboard
$system_health_sql = "
    SELECT 
        'Total Users' as metric,
        COUNT(*) as value
    FROM users
    UNION ALL
    SELECT 
        'Active Schools' as metric,
        COUNT(*) as value
    FROM schools WHERE is_active = 1
    UNION ALL
    SELECT 
        'Total Workshops' as metric,
        COUNT(*) as value
    FROM workshops WHERE is_deleted = 0
    UNION ALL
    SELECT 
        'Pending Payments' as metric,
        COUNT(*) as value
    FROM payments WHERE payment_status = 0
";
$system_health_result = mysqli_query($conn, $system_health_sql);
$analytics['system_health'] = [];
while ($row = mysqli_fetch_assoc($system_health_result)) {
    $analytics['system_health'][] = $row;
}

// ===== OPERATIONAL ANALYTICS =====
// Email Campaign Performance
$email_performance_sql = "
    SELECT 
        'Email Sent' as metric,
        COUNT(*) as count
    FROM payments 
    WHERE mail_send = 1
    UNION ALL
    SELECT 
        'Email Pending' as metric,
        COUNT(*) as count
    FROM payments 
    WHERE mail_send = 0 AND payment_status = 1
";
$email_performance_result = mysqli_query($conn, $email_performance_sql);
$analytics['email_performance'] = [];
while ($row = mysqli_fetch_assoc($email_performance_result)) {
    $analytics['email_performance'][] = $row;
}

// Certificate Generation Tracking
$certificate_tracking_sql = "
    SELECT 
        'Certificates Generated' as metric,
        COUNT(*) as count
    FROM payments 
    WHERE cpd = 1 AND payment_status = 1
    UNION ALL
    SELECT 
        'Certificates Pending' as metric,
        COUNT(*) as count
    FROM payments 
    WHERE cpd = 0 AND payment_status = 1
";
$certificate_tracking_result = mysqli_query($conn, $certificate_tracking_sql);
$analytics['certificate_tracking'] = [];
while ($row = mysqli_fetch_assoc($certificate_tracking_result)) {
    $analytics['certificate_tracking'][] = $row;
}

// Workshop Resource Utilization
$resource_utilization_sql = "
    SELECT 
        w.id,
        w.name,
        w.meeting_id,
        COUNT(a.id) as total_attendees,
        AVG(a.duration_attend) as avg_attendance_duration,
        COUNT(CASE WHEN a.duration_attend > 0 THEN 1 END) as active_attendees
    FROM workshops w
    LEFT JOIN Attendees a ON w.id = a.workshop_id
    WHERE w.meeting_id IS NOT NULL
    GROUP BY w.id
    ORDER BY total_attendees DESC
    LIMIT 15
";
$resource_utilization_result = mysqli_query($conn, $resource_utilization_sql);
$analytics['resource_utilization'] = [];
while ($row = mysqli_fetch_assoc($resource_utilization_result)) {
    $analytics['resource_utilization'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>User Analytics Dashboard | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    
    <!-- Custom CSS -->
    <style>
        .analytics-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .metric-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .metric-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .metric-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .metric-card.danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        .chart-container.large {
            height: 400px;
        }
        .chart-container.small {
            height: 250px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .section-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .badge-custom {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 text-uppercase fw-bold m-0">User Analytics Dashboard</h4>
                                <p class="text-muted mb-0">Comprehensive user insights and statistics</p>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="user_management.php" class="btn btn-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to User Management
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Metrics Cards -->
                <div class="stats-grid">
                    <div class="metric-card success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-1"><?php echo number_format($analytics['total_users']); ?></h3>
                                <p class="mb-0">Total Users</p>
                            </div>
                            <i class="ti ti-users fs-1 opacity-75"></i>
                        </div>
                    </div>
                    
                    <div class="metric-card info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-1"><?php echo number_format($analytics['registration_velocity'], 1); ?></h3>
                                <p class="mb-0">Avg Daily Registrations</p>
                            </div>
                            <i class="ti ti-trending-up fs-1 opacity-75"></i>
                        </div>
                    </div>
                    
                    <div class="metric-card warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-1"><?php echo number_format($analytics['unique_cities_count']); ?></h3>
                                <p class="mb-0">Active Cities</p>
                                <small class="opacity-75"><?php echo number_format($analytics['total_cities']); ?> users across cities</small>
                            </div>
                            <i class="ti ti-map-pin fs-1 opacity-75"></i>
                        </div>
                    </div>
                    
                    <div class="metric-card danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-1"><?php echo number_format($analytics['active_schools_count']); ?></h3>
                                <p class="mb-0">Active Schools</p>
                                <small class="opacity-75"><?php echo number_format($analytics['total_school_users']); ?> school users</small>
                            </div>
                            <i class="ti ti-school fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>

                <!-- Overall User Trends - Full Width -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">📈 Overall User Registration Trends (Complete History)</h5>
                                <p class="text-muted mb-0">Complete historical view of user growth patterns from start to end</p>
                            </div>
                            <div class="card-body">
                                <div class="chart-container large">
                                    <canvas id="overallTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Registration Trends (Last 12 Months)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyTrendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">School Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container small">
                                    <canvas id="schoolStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 10 Cities</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="topCitiesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Registration by Day of Week</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="dowChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 3 -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Profile Completion Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="profileCompletionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Engagement Levels</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="engagementChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Row -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top Designations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Designation</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_designations = array_sum(array_column($analytics['designation_distribution'], 'count'));
                                            foreach ($analytics['designation_distribution'] as $designation): 
                                                $percentage = ($designation['count'] / $total_designations) * 100;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($designation['designation']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo number_format($designation['count']); ?></span></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Registration by Hour of Day</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Analytics Row -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Email Domain Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container small">
                                    <canvas id="emailDomainsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Mobile Number Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container small">
                                    <canvas id="mobileAnalysisChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Geographic Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container small">
                                    <canvas id="geographicChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Quality & Performance Metrics -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Data Quality Metrics</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Metric</th>
                                                <th>Count</th>
                                                <th>Quality Score</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['data_quality'] as $metric): 
                                                $quality_score = ($metric['count'] / $analytics['total_users']) * 100;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($metric['metric']); ?></td>
                                                <td><span class="badge bg-info"><?php echo number_format($metric['count']); ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $quality_score; ?>%">
                                                            <?php echo number_format($quality_score, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Retention Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Period</th>
                                                <th>Users</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['retention_analysis'] as $retention): 
                                                $percentage = ($retention['count'] / $analytics['total_users']) * 100;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($retention['period']); ?></td>
                                                <td><span class="badge bg-warning"><?php echo number_format($retention['count']); ?></span></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Days & Peak Hours -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 10 Registration Days</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Registrations</th>
                                                <th>Day</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['top_days'] as $day): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($day['date'])); ?></td>
                                                <td><span class="badge bg-danger"><?php echo number_format($day['count']); ?></span></td>
                                                <td><?php echo date('l', strtotime($day['date'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Peak Registration Hours</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Hour</th>
                                                <th>Registrations</th>
                                                <th>Time Period</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['peak_hours'] as $hour): ?>
                                            <tr>
                                                <td><?php echo $hour['hour']; ?>:00</td>
                                                <td><span class="badge bg-success"><?php echo number_format($hour['count']); ?></span></td>
                                                <td>
                                                    <?php 
                                                    if ($hour['hour'] >= 6 && $hour['hour'] < 12) echo 'Morning';
                                                    elseif ($hour['hour'] >= 12 && $hour['hour'] < 18) echo 'Afternoon';
                                                    elseif ($hour['hour'] >= 18 && $hour['hour'] < 22) echo 'Evening';
                                                    else echo 'Night';
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- School Distribution -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 15 Schools - User Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="schoolDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Interconnected Analytics -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">🔗 Interconnected Analytics</h4>
                    </div>
                </div>

                <!-- School Performance Metrics -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 15 Schools - Performance Metrics</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>School Name</th>
                                                <th>Total Users</th>
                                                <th>Recent (30d)</th>
                                                <th>Recent (7d)</th>
                                                <th>Growth Rate</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['school_performance'] as $school): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo number_format($school['total_users']); ?></span></td>
                                                <td><span class="badge bg-info"><?php echo number_format($school['recent_users_30d']); ?></span></td>
                                                <td><span class="badge bg-success"><?php echo number_format($school['recent_users_7d']); ?></span></td>
                                                <td>
                                                    <?php if ($school['growth_rate_30d'] > 20): ?>
                                                        <span class="badge bg-success"><?php echo $school['growth_rate_30d']; ?>%</span>
                                                    <?php elseif ($school['growth_rate_30d'] > 10): ?>
                                                        <span class="badge bg-warning"><?php echo $school['growth_rate_30d']; ?>%</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><?php echo $school['growth_rate_30d']; ?>%</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($school['total_users'] > 100): ?>
                                                        <span class="badge bg-success">High</span>
                                                    <?php elseif ($school['total_users'] > 50): ?>
                                                        <span class="badge bg-warning">Medium</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Low</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- City-School Interconnection -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">City-School Interconnection</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>City</th>
                                                <th>Schools</th>
                                                <th>Users</th>
                                                <th>Avg/ School</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['city_school_analysis'] as $city): 
                                                $avg_per_school = $city['schools_in_city'] > 0 ? round($city['users_in_city'] / $city['schools_in_city'], 1) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($city['city']); ?></td>
                                                <td><span class="badge bg-info"><?php echo $city['schools_in_city']; ?></span></td>
                                                <td><span class="badge bg-primary"><?php echo number_format($city['users_in_city']); ?></span></td>
                                                <td><span class="badge bg-success"><?php echo $avg_per_school; ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Segmentation Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container small">
                                    <canvas id="userSegmentationChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cross-Analysis Tables -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Designation-City Cross Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Designation</th>
                                                <th>City</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['designation_city_analysis'] as $analysis): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($analysis['designation']); ?></td>
                                                <td><?php echo htmlspecialchars($analysis['city']); ?></td>
                                                <td><span class="badge bg-warning"><?php echo number_format($analysis['count']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Institute-City Cross Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Institute</th>
                                                <th>City</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['institute_city_analysis'] as $analysis): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($analysis['institute_name']); ?></td>
                                                <td><?php echo htmlspecialchars($analysis['city']); ?></td>
                                                <td><span class="badge bg-info"><?php echo number_format($analysis['count']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== WORKSHOP ANALYTICS SECTION ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">🎓 Workshop Analytics</h4>
                    </div>
                </div>

                <!-- Workshop Performance Dashboard -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">📊 Workshop Performance Dashboard</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Workshop</th>
                                                <th>Trainer</th>
                                                <th>Price</th>
                                                <th>Enrollments</th>
                                                <th>Revenue</th>
                                                <th>Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(isset($analytics['workshop_performance'])): foreach (array_slice($analytics['workshop_performance'], 0, 10) as $workshop): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($workshop['name']); ?></td>
                                                <td><?php echo htmlspecialchars($workshop['trainer_name']); ?></td>
                                                <td>₹<?php echo number_format($workshop['price']); ?></td>
                                                <td><?php echo $workshop['total_enrollments']; ?></td>
                                                <td>₹<?php echo number_format($workshop['revenue']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $workshop['avg_rating'] >= 4 ? 'success' : ($workshop['avg_rating'] >= 3 ? 'warning' : 'danger'); ?>">
                                                        <?php echo number_format($workshop['avg_rating'], 1); ?>/5
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trainer Performance & Category Analysis -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">👨‍🏫 Top Trainers by Revenue</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="trainerPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">📚 Category Performance</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryAnalysisChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== PAYMENT & REVENUE ANALYTICS SECTION ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">💳 Payment & Revenue Analytics</h4>
                    </div>
                </div>

                <!-- Payment Methods & Revenue Breakdown -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">💳 Payment Methods</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentMethodsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🏫 Revenue: School vs Individual</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueBreakdownChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coupon Usage Analytics -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🎫 Top Coupons by Usage</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Coupon Code</th>
                                                <th>Discount</th>
                                                <th>Usage Count</th>
                                                <th>Total Revenue</th>
                                                <th>Discount Given</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(isset($analytics['coupon_usage'])): foreach (array_slice($analytics['coupon_usage'], 0, 10) as $coupon): ?>
                                            <tr>
                                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($coupon['coupon_code']); ?></span></td>
                                                <td>₹<?php echo number_format($coupon['flat_discount']); ?></td>
                                                <td><?php echo $coupon['usage_count']; ?></td>
                                                <td>₹<?php echo number_format($coupon['total_revenue']); ?></td>
                                                <td>₹<?php echo number_format($coupon['total_discount_given']); ?></td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== SCHOOL & USER ANALYTICS SECTION ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">🏫 School & User Advanced Analytics</h4>
                    </div>
                </div>

                <!-- School Performance Ranking -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🏆 School Performance Ranking</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>School Name</th>
                                                <th>Total Users</th>
                                                <th>Purchases</th>
                                                <th>Revenue</th>
                                                <th>Avg Purchase Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(isset($analytics['school_ranking'])): foreach (array_slice($analytics['school_ranking'], 0, 10) as $index => $school): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($school['school_name']); ?></td>
                                                <td><?php echo $school['total_users']; ?></td>
                                                <td><?php echo $school['total_purchases']; ?></td>
                                                <td>₹<?php echo number_format($school['total_revenue']); ?></td>
                                                <td>₹<?php echo number_format($school['avg_purchase_value']); ?></td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Engagement & Retention -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">👥 User Engagement Levels</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="userEngagementChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🔄 User Retention Analysis</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="userRetentionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Geographic Performance & User Lifecycle -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🌍 Geographic Performance</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="geographicPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🔄 User Lifecycle Analysis</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="userLifecycleChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== ADVANCED BUSINESS INTELLIGENCE SECTION ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">🧠 Advanced Business Intelligence</h4>
                    </div>
                </div>

                <!-- Conversion Funnel & CLV Analysis -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🎯 Conversion Funnel Analysis</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="conversionFunnelChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">💎 Customer Lifetime Value (Top 8)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Purchases</th>
                                                <th>Total Spent</th>
                                                <th>Annual CLV</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(isset($analytics['clv_analysis'])): foreach (array_slice($analytics['clv_analysis'], 0, 8) as $customer): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($customer['name'], 0, 20)); ?></td>
                                                <td><?php echo $customer['total_purchases']; ?></td>
                                                <td>₹<?php echo number_format($customer['total_spent']); ?></td>
                                                <td>₹<?php echo number_format($customer['annual_clv']); ?></td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Market Penetration Analysis -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🎯 Market Penetration Analysis</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="marketPenetrationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== REAL-TIME MONITORING SECTION ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">⚡ Real-Time Monitoring</h4>
                    </div>
                </div>

                <!-- Live Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-3">
                        <div class="card analytics-card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo isset($analytics['live_registrations']) ? $analytics['live_registrations'] : 0; ?></h3>
                                <p class="text-muted mb-0">Today's Registrations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card analytics-card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo isset($analytics['active_workshops']) ? count($analytics['active_workshops']) : 0; ?></h3>
                                <p class="text-muted mb-0">Active Workshops (7 Days)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card analytics-card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo isset($analytics['payment_processing']) ? array_sum(array_column($analytics['payment_processing'], 'count')) : 0; ?></h3>
                                <p class="text-muted mb-0">Payments (24h)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card analytics-card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo isset($analytics['system_health'][0]) ? number_format($analytics['system_health'][0]['value']) : 0; ?></h3>
                                <p class="text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Workshops & Payment Processing -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">📅 Upcoming Workshops (Next 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Workshop</th>
                                                <th>Trainer</th>
                                                <th>Date</th>
                                                <th>Enrollments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(isset($analytics['active_workshops'])): foreach (array_slice($analytics['active_workshops'], 0, 8) as $workshop): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($workshop['name']); ?></td>
                                                <td><?php echo htmlspecialchars($workshop['trainer_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($workshop['start_date'])); ?></td>
                                                <td><?php echo $workshop['enrollments']; ?></td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">💳 Payment Processing (24h)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentProcessingChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== OPERATIONAL ANALYTICS SECTION ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <h4 class="section-title">⚙️ Operational Analytics</h4>
                    </div>
                </div>

                <!-- Email Performance & Certificate Tracking -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">📧 Email Campaign Performance</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="emailPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">📜 Certificate Generation Tracking</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="certificateTrackingChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Workshop Resource Utilization -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">🎥 Workshop Resource Utilization</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Workshop</th>
                                                <th>Meeting ID</th>
                                                <th>Total Attendees</th>
                                                <th>Active Attendees</th>
                                                <th>Avg Duration</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(isset($analytics['resource_utilization'])): foreach (array_slice($analytics['resource_utilization'], 0, 10) as $resource): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($resource['name']); ?></td>
                                                <td><code><?php echo htmlspecialchars($resource['meeting_id']); ?></code></td>
                                                <td><?php echo $resource['total_attendees']; ?></td>
                                                <td><?php echo $resource['active_attendees']; ?></td>
                                                <td><?php echo number_format($resource['avg_attendance_duration'], 1); ?> min</td>
                                            </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>






            </div>
        </div>

        <!-- Footer Start -->
        <?php include 'includes/footer.php'; ?>
        <!-- end Footer -->
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
        // Chart.js Configuration
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#6c757d';

        // Overall Trends Chart - Full Width (Complete History)
        const monthlyData = <?php echo json_encode($analytics['monthly_users']); ?>;
        const monthlyLabels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const monthlyCounts = monthlyData.map(item => parseInt(item.count));

        // Calculate cumulative totals for overall trends (perfect calculation)
        let cumulativeTotal = 0;
        const cumulativeData = monthlyCounts.map(count => {
            cumulativeTotal += count;
            return cumulativeTotal;
        });

        // Calculate moving averages (3-month) with proper handling
        const movingAverageData = [];
        for (let i = 0; i < monthlyCounts.length; i++) {
            if (i < 2) {
                movingAverageData.push(null);
            } else {
                const sum = monthlyCounts[i-2] + monthlyCounts[i-1] + monthlyCounts[i];
                const avg = sum / 3;
                movingAverageData.push(Math.round(avg * 100) / 100); // Round to 2 decimal places
            }
        }

        // Calculate growth rate (month-over-month percentage)
        const growthRateData = [];
        for (let i = 0; i < monthlyCounts.length; i++) {
            if (i === 0) {
                growthRateData.push(null);
            } else {
                const prevCount = monthlyCounts[i-1];
                const currentCount = monthlyCounts[i];
                if (prevCount > 0) {
                    const growthRate = ((currentCount - prevCount) / prevCount) * 100;
                    growthRateData.push(Math.round(growthRate * 100) / 100);
                } else {
                    growthRateData.push(null);
                }
            }
        }

        new Chart(document.getElementById('overallTrendsChart'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Monthly Registrations',
                        data: monthlyCounts,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Cumulative Total Users',
                        data: cumulativeData,
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: '3-Month Moving Average',
                        data: movingAverageData,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        borderDash: [5, 5],
                        yAxisID: 'y',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: 'Growth Rate (%)',
                        data: growthRateData,
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        borderDash: [3, 3],
                        yAxisID: 'y2',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#3498db',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Monthly Registrations',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cumulative Total Users',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        beginAtZero: true
                    },
                    y2: {
                        type: 'linear',
                        display: false,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Growth Rate (%)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });

        // Monthly Trends Chart (Simplified version)

        new Chart(document.getElementById('monthlyTrendsChart'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'New Registrations',
                    data: monthlyCounts,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // School Status Chart
        const schoolStatusData = <?php echo json_encode($analytics['school_status']); ?>;
        new Chart(document.getElementById('schoolStatusChart'), {
            type: 'doughnut',
            data: {
                labels: schoolStatusData.map(item => item.status),
                datasets: [{
                    data: schoolStatusData.map(item => item.count),
                    backgroundColor: ['#e74c3c', '#2ecc71'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Top Cities Chart
        const topCitiesData = <?php echo json_encode($analytics['top_cities']); ?>;
        new Chart(document.getElementById('topCitiesChart'), {
            type: 'bar',
            data: {
                labels: topCitiesData.map(item => item.city),
                datasets: [{
                    label: 'Users',
                    data: topCitiesData.map(item => item.count),
                    backgroundColor: '#9b59b6',
                    borderColor: '#8e44ad',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Day of Week Chart
        const dowData = <?php echo json_encode($analytics['dow_registration']); ?>;
        new Chart(document.getElementById('dowChart'), {
            type: 'bar',
            data: {
                labels: dowData.map(item => item.day_name),
                datasets: [{
                    label: 'Registrations',
                    data: dowData.map(item => item.count),
                    backgroundColor: '#f39c12',
                    borderColor: '#e67e22',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Hourly Chart
        const hourlyData = <?php echo json_encode($analytics['hourly_registration']); ?>;
        new Chart(document.getElementById('hourlyChart'), {
            type: 'line',
            data: {
                labels: hourlyData.map(item => item.hour + ':00'),
                datasets: [{
                    label: 'Registrations',
                    data: hourlyData.map(item => item.count),
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Profile Completion Chart
        const profileData = <?php echo json_encode($analytics['profile_completion']); ?>;
        new Chart(document.getElementById('profileCompletionChart'), {
            type: 'pie',
            data: {
                labels: profileData.map(item => item.status),
                datasets: [{
                    data: profileData.map(item => item.count),
                    backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Email Domains Chart
        const emailDomainsData = <?php echo json_encode($analytics['email_domains']); ?>;
        new Chart(document.getElementById('emailDomainsChart'), {
            type: 'doughnut',
            data: {
                labels: emailDomainsData.map(item => item.domain),
                datasets: [{
                    data: emailDomainsData.map(item => item.count),
                    backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#f1c40f'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        // Mobile Analysis Chart
        const mobileData = <?php echo json_encode($analytics['mobile_analysis']); ?>;
        new Chart(document.getElementById('mobileAnalysisChart'), {
            type: 'pie',
            data: {
                labels: mobileData.map(item => item.status),
                datasets: [{
                    data: mobileData.map(item => item.count),
                    backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c', '#95a5a6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Geographic Chart
        const geoData = <?php echo json_encode($analytics['geographic_distribution']); ?>;
        new Chart(document.getElementById('geographicChart'), {
            type: 'doughnut',
            data: {
                labels: geoData.map(item => item.state),
                datasets: [{
                    data: geoData.map(item => item.count),
                    backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#f1c40f'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        // School Distribution Chart
        const schoolData = <?php echo json_encode($analytics['school_distribution']); ?>;
        new Chart(document.getElementById('schoolDistributionChart'), {
            type: 'bar',
            data: {
                labels: schoolData.map(item => item.school_name.length > 20 ? item.school_name.substring(0, 20) + '...' : item.school_name),
                datasets: [{
                    label: 'Users',
                    data: schoolData.map(item => item.user_count),
                    backgroundColor: '#1abc9c',
                    borderColor: '#16a085',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Engagement Chart
        const engagementData = <?php echo json_encode($analytics['engagement_score']); ?>;
        new Chart(document.getElementById('engagementChart'), {
            type: 'doughnut',
            data: {
                labels: engagementData.map(item => item.engagement_level),
                datasets: [{
                    data: engagementData.map(item => item.count),
                    backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // User Segmentation Chart
        const userSegmentationData = <?php echo json_encode($analytics['user_segmentation']); ?>;
        new Chart(document.getElementById('userSegmentationChart'), {
            type: 'pie',
            data: {
                labels: userSegmentationData.map(item => item.segment),
                datasets: [{
                    data: userSegmentationData.map(item => item.count),
                    backgroundColor: ['#2ecc71', '#3498db', '#f39c12', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });



    </script>

        // ===== WORKSHOP ANALYTICS CHARTS =====
        
        // Trainer Performance Chart
        if (document.getElementById('trainerPerformanceChart')) {
            const trainerData = <?php echo isset($analytics['trainer_performance']) ? json_encode(array_slice($analytics['trainer_performance'], 0, 8)) : '[]'; ?>;
            new Chart(document.getElementById('trainerPerformanceChart'), {
                type: 'bar',
                data: {
                    labels: trainerData.map(t => t.name),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: trainerData.map(t => parseFloat(t.total_revenue)),
                        backgroundColor: '#3498db',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Category Analysis Chart
        if (document.getElementById('categoryAnalysisChart')) {
            const categoryData = <?php echo isset($analytics['category_analysis']) ? json_encode($analytics['category_analysis']) : '[]'; ?>;
            new Chart(document.getElementById('categoryAnalysisChart'), {
                type: 'doughnut',
                data: {
                    labels: categoryData.map(c => c.category_name),
                    datasets: [{
                        data: categoryData.map(c => parseFloat(c.revenue)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // ===== PAYMENT & REVENUE ANALYTICS CHARTS =====
        
        // Payment Methods Chart
        if (document.getElementById('paymentMethodsChart')) {
            const paymentMethodsData = <?php echo isset($analytics['payment_methods']) ? json_encode($analytics['payment_methods']) : '[]'; ?>;
            new Chart(document.getElementById('paymentMethodsChart'), {
                type: 'pie',
                data: {
                    labels: paymentMethodsData.map(p => p.payment_method),
                    datasets: [{
                        data: paymentMethodsData.map(p => parseInt(p.count)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // Revenue Breakdown Chart
        if (document.getElementById('revenueBreakdownChart')) {
            const revenueBreakdownData = <?php echo isset($analytics['revenue_breakdown']) ? json_encode($analytics['revenue_breakdown']) : '[]'; ?>;
            new Chart(document.getElementById('revenueBreakdownChart'), {
                type: 'bar',
                data: {
                    labels: revenueBreakdownData.map(r => r.user_type),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenueBreakdownData.map(r => parseFloat(r.total_revenue)),
                        backgroundColor: ['#3498db', '#2ecc71'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // ===== SCHOOL & USER ANALYTICS CHARTS =====
        
        // User Engagement Chart
        if (document.getElementById('userEngagementChart')) {
            const engagementData = <?php echo isset($analytics['user_engagement']) ? json_encode($analytics['user_engagement']) : '[]'; ?>;
            const engagementLevels = {
                'High': engagementData.filter(u => u.engagement_level === 'High').length,
                'Medium': engagementData.filter(u => u.engagement_level === 'Medium').length,
                'Low': engagementData.filter(u => u.engagement_level === 'Low').length
            };
            new Chart(document.getElementById('userEngagementChart'), {
                type: 'doughnut',
                data: {
                    labels: ['High', 'Medium', 'Low'],
                    datasets: [{
                        data: [engagementLevels.High, engagementLevels.Medium, engagementLevels.Low],
                        backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // User Retention Chart
        if (document.getElementById('userRetentionChart')) {
            const retentionData = <?php echo isset($analytics['user_retention']) ? json_encode(array_slice($analytics['user_retention'], 0, 6)) : '[]'; ?>;
            new Chart(document.getElementById('userRetentionChart'), {
                type: 'line',
                data: {
                    labels: retentionData.map(r => r.registration_month),
                    datasets: [{
                        label: 'Retention Rate (%)',
                        data: retentionData.map(r => parseFloat(r.retention_rate)),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100 }
                    }
                }
            });
        }

        // Geographic Performance Chart
        if (document.getElementById('geographicPerformanceChart')) {
            const geoData = <?php echo isset($analytics['geographic_performance']) ? json_encode(array_slice($analytics['geographic_performance'], 0, 10)) : '[]'; ?>;
            new Chart(document.getElementById('geographicPerformanceChart'), {
                type: 'bar',
                data: {
                    labels: geoData.map(g => g.city),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: geoData.map(g => parseFloat(g.total_revenue)),
                        backgroundColor: '#3498db',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }

        // User Lifecycle Chart
        if (document.getElementById('userLifecycleChart')) {
            const lifecycleData = <?php echo isset($analytics['user_lifecycle']) ? json_encode($analytics['user_lifecycle']) : '[]'; ?>;
            new Chart(document.getElementById('userLifecycleChart'), {
                type: 'pie',
                data: {
                    labels: lifecycleData.map(l => l.lifecycle_stage),
                    datasets: [{
                        data: lifecycleData.map(l => parseInt(l.user_count)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 9 } } }
                    }
                }
            });
        }

        // ===== ADVANCED BUSINESS INTELLIGENCE CHARTS =====
        
        // Conversion Funnel Chart
        if (document.getElementById('conversionFunnelChart')) {
            const funnelData = <?php echo isset($analytics['conversion_funnel']) ? json_encode($analytics['conversion_funnel']) : '[]'; ?>;
            new Chart(document.getElementById('conversionFunnelChart'), {
                type: 'bar',
                data: {
                    labels: funnelData.map(f => f.stage),
                    datasets: [{
                        label: 'Count',
                        data: funnelData.map(f => parseInt(f.count)),
                        backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#e74c3c'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Market Penetration Chart
        if (document.getElementById('marketPenetrationChart')) {
            const penetrationData = <?php echo isset($analytics['market_penetration']) ? json_encode(array_slice($analytics['market_penetration'], 0, 10)) : '[]'; ?>;
            new Chart(document.getElementById('marketPenetrationChart'), {
                type: 'bar',
                data: {
                    labels: penetrationData.map(p => p.city),
                    datasets: [
                        {
                            label: 'Registered Users',
                            data: penetrationData.map(p => parseInt(p.registered_users)),
                            backgroundColor: '#3498db',
                            borderRadius: 8
                        },
                        {
                            label: 'Paying Users',
                            data: penetrationData.map(p => parseInt(p.paying_users)),
                            backgroundColor: '#2ecc71',
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // ===== REAL-TIME MONITORING CHARTS =====
        
        // Payment Processing Chart
        if (document.getElementById('paymentProcessingChart')) {
            const processingData = <?php echo isset($analytics['payment_processing']) ? json_encode($analytics['payment_processing']) : '[]'; ?>;
            new Chart(document.getElementById('paymentProcessingChart'), {
                type: 'doughnut',
                data: {
                    labels: processingData.map(p => p.status),
                    datasets: [{
                        data: processingData.map(p => parseInt(p.count)),
                        backgroundColor: ['#f39c12', '#2ecc71', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 9 } } }
                    }
                }
            });
        }

        // ===== OPERATIONAL ANALYTICS CHARTS =====
        
        // Email Performance Chart
        if (document.getElementById('emailPerformanceChart')) {
            const emailData = <?php echo isset($analytics['email_performance']) ? json_encode($analytics['email_performance']) : '[]'; ?>;
            new Chart(document.getElementById('emailPerformanceChart'), {
                type: 'bar',
                data: {
                    labels: emailData.map(e => e.metric),
                    datasets: [{
                        label: 'Count',
                        data: emailData.map(e => parseInt(e.count)),
                        backgroundColor: ['#2ecc71', '#f39c12'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Certificate Tracking Chart
        if (document.getElementById('certificateTrackingChart')) {
            const certData = <?php echo isset($analytics['certificate_tracking']) ? json_encode($analytics['certificate_tracking']) : '[]'; ?>;
            new Chart(document.getElementById('certificateTrackingChart'), {
                type: 'pie',
                data: {
                    labels: certData.map(c => c.metric),
                    datasets: [{
                        data: certData.map(c => parseInt(c.count)),
                        backgroundColor: ['#2ecc71', '#f39c12']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 9 } } }
                    }
                }
            });
        }

</body>
</html>
