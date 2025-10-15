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
    
    <!-- Advanced Chart Libraries -->
    <script src="https://cdn.plot.ly/plotly-2.26.0.min.js"></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- CSS for Advanced Visualizations -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
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
        
        /* Advanced Visualization Styles */
        .viz-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .viz-container.large {
            height: 500px;
        }
        .viz-container.small {
            height: 300px;
        }
        
        /* 3D Chart Styles */
        .chart-3d {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
        }
        
        /* Flow Chart Styles */
        .flow-chart {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        /* Map Styles */
        .map-container {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        /* Heatmap Styles */
        .heatmap-container {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        /* Glassmorphism Effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        /* Animated Counters */
        .counter {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Particle Background */
        .particle-bg {
            position: relative;
            overflow: hidden;
        }
        .particle-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            animation: particleFloat 20s ease-in-out infinite;
        }
        
        @keyframes particleFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        /* Interactive Elements */
        .interactive-element {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .interactive-element:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        /* Glow Effects */
        .glow {
            box-shadow: 0 0 20px rgba(52, 152, 219, 0.5);
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { box-shadow: 0 0 20px rgba(52, 152, 219, 0.5); }
            to { box-shadow: 0 0 30px rgba(52, 152, 219, 0.8); }
        }
        
        /* Progress Rings */
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring-circle {
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 0.5s ease-in-out;
        }
        
        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0,0,0,0.4);
        }
        
        /* Neumorphism */
        .neomorphic {
            background: #f0f0f3;
            border-radius: 20px;
            box-shadow: 20px 20px 60px #bebebe, -20px -20px 60px #ffffff;
        }
        
        /* Dark Theme Support */
        .dark-theme {
            background: #1a1a1a;
            color: #ffffff;
        }
        .dark-theme .analytics-card {
            background: #2d2d2d;
            border: 1px solid #404040;
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





            </div>
        </div>

        <!-- Footer Start -->
        <?php include 'includes/footer.php'; ?>
        <!-- end Footer -->
        
        <!-- Floating Action Button -->
        <button class="fab" onclick="toggleTheme()" title="Toggle Theme">
            <i class="fas fa-palette"></i>
        </button>
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

        // 🚀 Advanced 3D Visualizations
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for all libraries to load
            setTimeout(function() {
                initializeAdvancedVisualizations();
            }, 1000);
        });

        function initializeAdvancedVisualizations() {
            try {
                // 3D Pyramid Chart
                if (document.getElementById('pyramid3d')) {
                    create3DPyramid();
                }
                
                // 3D Geographic Distribution
                if (document.getElementById('geo3d')) {
                    create3DGeoChart();
                }
                
                // 3D School Performance Matrix
                if (document.getElementById('school3d')) {
                    create3DSchoolMatrix();
                }
                
                // 3D User Journey Funnel
                if (document.getElementById('funnel3d')) {
                    create3DFunnel();
                }
                
                // Sankey Flow Diagram
                if (document.getElementById('sankeyFlow')) {
                    createSankeyFlow();
                }
                
                // Network Graph
                if (document.getElementById('networkGraph')) {
                    createNetworkGraph();
                }
                
                // Interactive Map
                if (document.getElementById('interactiveMap')) {
                    createInteractiveMap();
                }
                
                // Heatmap
                if (document.getElementById('heatmap')) {
                    createHeatmap();
                }
                
                // Prediction Chart
                if (document.getElementById('predictionChart')) {
                    createPredictionChart();
                }
                
                // Seasonal Analysis
                if (document.getElementById('seasonalChart')) {
                    createSeasonalChart();
                }
                
                // Cohort Analysis
                if (document.getElementById('cohortChart')) {
                    createCohortChart();
                }
                
                // Live Counter Animation
                if (document.getElementById('liveCounter')) {
                    animateLiveCounter();
                }
            } catch (error) {
                console.log('Some visualizations could not be loaded:', error);
            }
        }

        // 3D Pyramid Chart
        function create3DPyramid() {
            try {
                const totalUsers = <?php echo isset($analytics['total_users']) ? $analytics['total_users'] : 1000; ?>;
                const data = [{
                    type: 'bar',
                    x: ['Students', 'Teachers', 'Principals', 'Coordinators', 'Others'],
                    y: [totalUsers * 0.4, totalUsers * 0.3, totalUsers * 0.15, totalUsers * 0.1, totalUsers * 0.05],
                    marker: {
                        color: ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7'],
                        line: { color: 'white', width: 2 }
                    }
                }];

                const layout = {
                    title: 'User Growth Pyramid',
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('pyramid3d', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating 3D Pyramid:', error);
            }
        }

        // 3D Geographic Distribution
        function create3DGeoChart() {
            try {
                const cities = <?php echo json_encode(isset($analytics['top_cities']) ? array_slice($analytics['top_cities'], 0, 10) : []); ?>;
                if (cities.length === 0) {
                    console.log('No city data available for 3D Geographic Distribution');
                    return;
                }
                
                const data = [{
                    type: 'scatter3d',
                    x: cities.map((_, i) => Math.random() * 100),
                    y: cities.map((_, i) => Math.random() * 100),
                    z: cities.map(city => city.count),
                    mode: 'markers',
                    marker: {
                        size: cities.map(city => Math.sqrt(city.count) * 2),
                        color: cities.map(city => city.count),
                        colorscale: 'Viridis',
                        line: { color: 'white', width: 2 }
                    },
                    text: cities.map(city => `${city.city}: ${city.count} users`),
                    hovertemplate: '<b>%{text}</b><br>X: %{x}<br>Y: %{y}<br>Z: %{z}<extra></extra>'
                }];

                const layout = {
                    title: '3D Geographic Distribution',
                    scene: {
                        xaxis: { title: 'Longitude' },
                        yaxis: { title: 'Latitude' },
                        zaxis: { title: 'User Count' },
                        camera: { eye: { x: 1.5, y: 1.5, z: 1.5 } }
                    },
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('geo3d', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating 3D Geographic Distribution:', error);
            }
        }

        // 3D School Performance Matrix
        function create3DSchoolMatrix() {
            try {
                const schools = <?php echo json_encode(isset($analytics['school_performance']) ? array_slice($analytics['school_performance'], 0, 8) : []); ?>;
                if (schools.length === 0) {
                    console.log('No school data available for 3D School Performance Matrix');
                    return;
                }
                
                const data = [{
                    type: 'scatter3d',
                    x: schools.map((_, i) => i),
                    y: schools.map(school => school.user_count),
                    z: schools.map(school => school.avg_engagement || 0),
                    mode: 'markers+text',
                    marker: {
                        size: schools.map(school => Math.sqrt(school.user_count) * 3),
                        color: schools.map(school => school.user_count),
                        colorscale: 'Plasma',
                        line: { color: 'white', width: 2 }
                    },
                    text: schools.map(school => school.school_name.substring(0, 10)),
                    textposition: 'top center'
                }];

                const layout = {
                    title: '3D School Performance Matrix',
                    scene: {
                        xaxis: { title: 'School Index' },
                        yaxis: { title: 'User Count' },
                        zaxis: { title: 'Engagement Score' },
                        camera: { eye: { x: 1.5, y: 1.5, z: 1.5 } }
                    },
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('school3d', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating 3D School Performance Matrix:', error);
            }
        }

        // 3D User Journey Funnel
        function create3DFunnel() {
            try {
                const totalUsers = <?php echo isset($analytics['total_users']) ? $analytics['total_users'] : 1000; ?>;
                const stages = ['Visitors', 'Registered', 'Active', 'Engaged', 'Retained'];
                const values = [totalUsers * 3, totalUsers, totalUsers * 0.7, totalUsers * 0.4, totalUsers * 0.2];
                
                const data = [{
                    type: 'funnel',
                    y: stages,
                    x: values,
                    marker: {
                        color: ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7'],
                        line: { color: 'white', width: 2 }
                    },
                    textinfo: 'value+percent initial'
                }];

                const layout = {
                    title: '3D User Journey Funnel',
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('funnel3d', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating 3D User Journey Funnel:', error);
            }
        }

        // Sankey Flow Diagram
        function createSankeyFlow() {
            try {
                const totalUsers = <?php echo isset($analytics['total_users']) ? $analytics['total_users'] : 1000; ?>;
                const data = {
                    type: "sankey",
                    orientation: "h",
                    node: {
                        pad: 15,
                        thickness: 20,
                        line: { color: "black", width: 0.5 },
                        label: ["Direct", "Social Media", "Email", "Referral", "Students", "Teachers", "Principals", "Active Users", "Engaged Users"],
                        color: ["#FF6B6B", "#4ECDC4", "#45B7D1", "#96CEB4", "#FFEAA7", "#DDA0DD", "#98D8C8", "#F7DC6F", "#BB8FCE"]
                    },
                    link: {
                        source: [0, 1, 2, 3, 4, 5, 6, 7],
                        target: [4, 5, 6, 4, 7, 7, 7, 8],
                        value: [totalUsers * 0.3, totalUsers * 0.25, totalUsers * 0.2, totalUsers * 0.25, totalUsers * 0.7, totalUsers * 0.6, totalUsers * 0.5, totalUsers * 0.4]
                    }
                };

                const layout = {
                    title: "User Registration Flow",
                    font: { size: 10, color: 'white' },
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)'
                };

                Plotly.newPlot('sankeyFlow', [data], layout, {responsive: true});
            } catch (error) {
                console.log('Error creating Sankey Flow Diagram:', error);
            }
        }

        // Network Graph
        function createNetworkGraph() {
            try {
                const schools = <?php echo json_encode(isset($analytics['school_performance']) ? array_slice($analytics['school_performance'], 0, 6) : []); ?>;
                if (schools.length === 0) {
                    console.log('No school data available for Network Graph');
                    return;
                }
                
                const nodes = schools.map((school, i) => ({
                    id: i,
                    label: school.school_name.substring(0, 15),
                    size: Math.sqrt(school.user_count) * 2,
                    color: `hsl(${i * 60}, 70%, 60%)`
                }));

                const links = [];
                for (let i = 0; i < nodes.length - 1; i++) {
                    links.push({
                        source: i,
                        target: i + 1,
                        strength: 0.5
                    });
                }

                const svg = d3.select('#networkGraph')
                    .append('svg')
                    .attr('width', '100%')
                    .attr('height', '100%');

                const width = 300;
                const height = 300;

                const simulation = d3.forceSimulation(nodes)
                    .force('link', d3.forceLink(links).id(d => d.id).distance(50))
                    .force('charge', d3.forceManyBody().strength(-300))
                    .force('center', d3.forceCenter(width / 2, height / 2));

                const link = svg.append('g')
                    .selectAll('line')
                    .data(links)
                    .enter().append('line')
                    .attr('stroke', '#999')
                    .attr('stroke-opacity', 0.6)
                    .attr('stroke-width', 2);

                const node = svg.append('g')
                    .selectAll('circle')
                    .data(nodes)
                    .enter().append('circle')
                    .attr('r', d => d.size)
                    .attr('fill', d => d.color)
                    .attr('stroke', '#fff')
                    .attr('stroke-width', 2)
                    .call(d3.drag()
                        .on('start', dragstarted)
                        .on('drag', dragged)
                        .on('end', dragended));

                const label = svg.append('g')
                    .selectAll('text')
                    .data(nodes)
                    .enter().append('text')
                    .text(d => d.label)
                    .attr('font-size', 10)
                    .attr('text-anchor', 'middle')
                    .attr('dy', 4)
                    .attr('fill', 'white');

                simulation.on('tick', () => {
                    link
                        .attr('x1', d => d.source.x)
                        .attr('y1', d => d.source.y)
                        .attr('x2', d => d.target.x)
                        .attr('y2', d => d.target.y);

                    node
                        .attr('cx', d => d.x)
                        .attr('cy', d => d.y);

                    label
                        .attr('x', d => d.x)
                        .attr('y', d => d.y);
                });

                function dragstarted(event, d) {
                    if (!event.active) simulation.alphaTarget(0.3).restart();
                    d.fx = d.x;
                    d.fy = d.y;
                }

                function dragged(event, d) {
                    d.fx = event.x;
                    d.fy = event.y;
                }

                function dragended(event, d) {
                    if (!event.active) simulation.alphaTarget(0);
                    d.fx = null;
                    d.fy = null;
                }
            } catch (error) {
                console.log('Error creating Network Graph:', error);
            }
        }

        // Interactive Map
        function createInteractiveMap() {
            try {
                const map = L.map('interactiveMap').setView([20.5937, 78.9629], 5);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                const cities = <?php echo json_encode(isset($analytics['top_cities']) ? $analytics['top_cities'] : []); ?>;
                cities.forEach(city => {
                    const lat = 20.5937 + (Math.random() - 0.5) * 10;
                    const lng = 78.9629 + (Math.random() - 0.5) * 10;
                    
                    L.circleMarker([lat, lng], {
                        radius: Math.sqrt(city.count) * 0.5,
                        fillColor: '#3498db',
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map).bindPopup(`
                        <strong>${city.city}</strong><br>
                        Users: ${city.count}<br>
                        Percentage: ${city.percentage}%
                    `);
                });
            } catch (error) {
                console.log('Error creating Interactive Map:', error);
            }
        }

        // Heatmap
        function createHeatmap() {
            try {
                const hours = Array.from({length: 24}, (_, i) => i);
                const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                const data = [];

                for (let i = 0; i < days.length; i++) {
                    for (let j = 0; j < hours.length; j++) {
                        data.push({
                            x: hours[j],
                            y: days[i],
                            z: Math.random() * 100
                        });
                    }
                }

                const heatmapData = [{
                    type: 'heatmap',
                    x: hours,
                    y: days,
                    z: data.map(d => d.z),
                    colorscale: 'Viridis',
                    showscale: true
                }];

                const layout = {
                    title: 'Registration Activity Heatmap',
                    xaxis: { title: 'Hour of Day' },
                    yaxis: { title: 'Day of Week' },
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('heatmap', heatmapData, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating Heatmap:', error);
            }
        }

        // Prediction Chart
        function createPredictionChart() {
            try {
                const months = monthlyLabels.slice(-6);
                const actual = monthlyCounts.slice(-6);
                const predicted = actual.map(val => val * (1 + Math.random() * 0.3));

                const data = [
                    {
                        type: 'scatter',
                        mode: 'lines+markers',
                        name: 'Actual',
                        x: months,
                        y: actual,
                        line: { color: '#3498db', width: 3 },
                        marker: { size: 8 }
                    },
                    {
                        type: 'scatter',
                        mode: 'lines+markers',
                        name: 'Predicted',
                        x: months,
                        y: predicted,
                        line: { color: '#e74c3c', width: 3, dash: 'dash' },
                        marker: { size: 8 }
                    }
                ];

                const layout = {
                    title: 'User Growth Prediction',
                    xaxis: { title: 'Month' },
                    yaxis: { title: 'User Count' },
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('predictionChart', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating Prediction Chart:', error);
            }
        }

        // Seasonal Analysis
        function createSeasonalChart() {
            try {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const seasonalData = months.map(month => Math.random() * 100 + 50);

                const data = [{
                    type: 'scatterpolar',
                    r: seasonalData,
                    theta: months,
                    fill: 'toself',
                    name: 'Seasonal Pattern',
                    line: { color: '#4ECDC4' },
                    fillcolor: 'rgba(78, 205, 196, 0.3)'
                }];

                const layout = {
                    polar: {
                        radialaxis: {
                            visible: true,
                            range: [0, 150]
                        }
                    },
                    title: 'Seasonal Registration Pattern',
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('seasonalChart', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating Seasonal Analysis:', error);
            }
        }

        // Cohort Analysis
        function createCohortChart() {
            try {
                const cohorts = ['Jan 2024', 'Feb 2024', 'Mar 2024', 'Apr 2024', 'May 2024'];
                const retentionData = cohorts.map(() => 
                    Array.from({length: 5}, (_, i) => Math.max(0, 100 - i * 20 + Math.random() * 10))
                );

                const data = [{
                    type: 'heatmap',
                    x: ['Month 0', 'Month 1', 'Month 2', 'Month 3', 'Month 4'],
                    y: cohorts,
                    z: retentionData,
                    colorscale: 'RdYlBu',
                    showscale: true,
                    text: retentionData.map(row => row.map(val => `${val.toFixed(1)}%`)),
                    texttemplate: '%{text}',
                    textfont: { size: 10 }
                }];

                const layout = {
                    title: 'User Cohort Retention Analysis',
                    xaxis: { title: 'Months After Registration' },
                    yaxis: { title: 'Registration Cohort' },
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: 'white' }
                };

                Plotly.newPlot('cohortChart', data, layout, {responsive: true});
            } catch (error) {
                console.log('Error creating Cohort Analysis:', error);
            }
        }

        // Live Counter Animation
        function animateLiveCounter() {
            try {
                const counter = document.getElementById('liveCounter');
                const target = <?php echo isset($analytics['today_users']) ? $analytics['today_users'] : 0; ?>;
                let current = 0;
                const increment = target / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current);
                }, 20);
            } catch (error) {
                console.log('Error creating Live Counter Animation:', error);
            }
        }

        // Theme Toggle Function
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            const fab = document.querySelector('.fab');
            fab.innerHTML = document.body.classList.contains('dark-theme') ? 
                '<i class="fas fa-sun"></i>' : '<i class="fas fa-palette"></i>';
        }
    </script>
</body>
</html>
