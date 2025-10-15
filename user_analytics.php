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

// 2. Users by Month (Last 12 months)
$monthly_users_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC";
$monthly_users_result = mysqli_query($conn, $monthly_users_sql);
$analytics['monthly_users'] = [];
while ($row = mysqli_fetch_assoc($monthly_users_result)) {
    $analytics['monthly_users'][] = $row;
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
    LIMIT 10";
$top_cities_result = mysqli_query($conn, $top_cities_sql);
$analytics['top_cities'] = [];
while ($row = mysqli_fetch_assoc($top_cities_result)) {
    $analytics['top_cities'][] = $row;
}

// 5. School-wise Distribution
$school_distribution_sql = "SELECT 
    s.name as school_name,
    COUNT(u.id) as user_count
    FROM schools s
    LEFT JOIN users u ON s.id = u.school_id
    GROUP BY s.id, s.name
    HAVING user_count > 0
    ORDER BY user_count DESC
    LIMIT 10";
$school_distribution_result = mysqli_query($conn, $school_distribution_sql);
$analytics['school_distribution'] = [];
while ($row = mysqli_fetch_assoc($school_distribution_result)) {
    $analytics['school_distribution'][] = $row;
}

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
                                <h3 class="fw-bold mb-1"><?php echo count($analytics['top_cities']); ?></h3>
                                <p class="mb-0">Active Cities</p>
                            </div>
                            <i class="ti ti-map-pin fs-1 opacity-75"></i>
                        </div>
                    </div>
                    
                    <div class="metric-card danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-1"><?php echo count($analytics['school_distribution']); ?></h3>
                                <p class="mb-0">Active Schools</p>
                            </div>
                            <i class="ti ti-school fs-1 opacity-75"></i>
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
                                <h5 class="card-title mb-0">Registration by Hour of Day</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                <h5 class="card-title mb-0">Top Institutes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Institute</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_institutes = array_sum(array_column($analytics['institute_distribution'], 'count'));
                                            foreach ($analytics['institute_distribution'] as $institute): 
                                                $percentage = ($institute['count'] / $total_institutes) * 100;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($institute['institute_name']); ?></td>
                                                <td><span class="badge bg-success"><?php echo number_format($institute['count']); ?></span></td>
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

                <!-- School Distribution & Engagement Analysis -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">School-wise User Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="schoolDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Engagement Levels</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container small">
                                    <canvas id="engagementChart"></canvas>
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

        // Monthly Trends Chart
        const monthlyData = <?php echo json_encode($analytics['monthly_users']); ?>;
        const monthlyLabels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const monthlyCounts = monthlyData.map(item => item.count);

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
    </script>
</body>
</html>
