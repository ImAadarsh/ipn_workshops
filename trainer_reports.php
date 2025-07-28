<?php
require_once 'config/config.php';
require_once 'includes/head.php';

// Handle CSV Download
if(isset($_GET['download']) && $_GET['download'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="trainer_reports_'.date('Y-m-d').'.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, array(
        'Trainer Name',
        'Designation',
        'Total Workshops',
        'Total Ratings',
        'Average Rating',
        '5 Star Ratings',
        '4 Star Ratings',
        '3 Star Ratings',
        '2 Star Ratings',
        '1 Star Ratings'
    ));

    // Get trainer data without LIMIT for CSV
    $csv_query = "
        SELECT 
            t.name,
            t.designation,
            COUNT(DISTINCT w.id) as total_workshops,
            COUNT(DISTINCT f.id) as total_ratings,
            ROUND(AVG(f.rating), 1) as avg_rating,
            COUNT(CASE WHEN f.rating = 5 THEN 1 END) as five_star,
            COUNT(CASE WHEN f.rating = 4 THEN 1 END) as four_star,
            COUNT(CASE WHEN f.rating = 3 THEN 1 END) as three_star,
            COUNT(CASE WHEN f.rating = 2 THEN 1 END) as two_star,
            COUNT(CASE WHEN f.rating = 1 THEN 1 END) as one_star
        FROM trainers t
        LEFT JOIN workshops w ON t.id = w.trainer_id AND w.is_deleted = 0
        LEFT JOIN feedback f ON t.id = f.trainer_id
        WHERE t.active = 1
        AND w.type = 1
        GROUP BY t.id, t.name, t.designation
        ORDER BY COUNT(DISTINCT w.id) DESC, COUNT(DISTINCT f.id) DESC";
    
    $csv_result = mysqli_query($conn, $csv_query);
    while($row = mysqli_fetch_assoc($csv_result)) {
        fputcsv($output, array(
            $row['name'],
            $row['designation'],
            $row['total_workshops'],
            $row['total_ratings'],
            $row['avg_rating'],
            $row['five_star'],
            $row['four_star'],
            $row['three_star'],
            $row['two_star'],
            $row['one_star']
        ));
    }
    fclose($output);
    exit();
}

// Get trainer statistics
$query = "
    SELECT 
        t.id,
        t.name,
        t.designation,
        t.image,
        t.about,
        COUNT(DISTINCT w.id) as total_workshops,
        COUNT(DISTINCT f.id) as total_ratings,
        ROUND(AVG(f.rating), 1) as avg_rating,
        COUNT(CASE WHEN f.rating = 5 THEN 1 END) as five_star,
        COUNT(CASE WHEN f.rating = 4 THEN 1 END) as four_star,
        COUNT(CASE WHEN f.rating = 3 THEN 1 END) as three_star,
        COUNT(CASE WHEN f.rating = 2 THEN 1 END) as two_star,
        COUNT(CASE WHEN f.rating = 1 THEN 1 END) as one_star
    FROM trainers t
    LEFT JOIN workshops w ON t.id = w.trainer_id AND w.is_deleted = 0
    LEFT JOIN feedback f ON t.id = f.trainer_id
    WHERE t.active = 1
    AND w.type = 1
    GROUP BY t.id, t.name, t.designation, t.image, t.about
    ORDER BY COUNT(DISTINCT w.id) DESC, COUNT(DISTINCT f.id) DESC"; // Sort by workshops first, then ratings

$result = mysqli_query($conn, $query);
$trainers = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
    <!-- Begin page -->
    <div class="wrapper">
        
            <!-- Sidenav Menu Start -->
            <?php include 'includes/sidenav.php'; ?>
            <!-- Sidenav Menu End -->

            <!-- Topbar Start -->
            <?php include 'includes/topbar.php'; ?>
            <!-- Topbar End -->
<!-- Start Content-->
<div class="page-content">
            <div class="page-container">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Trainer Reports</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    Trainer Performance Reports
                    <a href="?download=csv" class="btn btn-success btn-sm ms-3">
                        <i class="ti ti-download me-1"></i>Download CSV
                    </a>
                </h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <!-- Stats Row -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="widget-rounded-circle card-box">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="avatar-lg">
                                            <i class="ti ti-users font-22 avatar-title text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="mb-1 mt-2"><?php echo count($trainers); ?></h5>
                                        <p class="mb-2 text-muted">Total Active Trainers</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Calculate total workshops and average rating
                        $total_workshops = 0;
                        $total_ratings = 0;
                        $ratings_sum = 0;

                        foreach ($trainers as $trainer) {
                            $total_workshops += $trainer['total_workshops'];
                            $total_ratings += $trainer['total_ratings'];
                            $ratings_sum += ($trainer['avg_rating'] * $trainer['total_ratings']);
                        }

                        $overall_avg_rating = $total_ratings > 0 ? round($ratings_sum / $total_ratings, 1) : 0;
                        ?>

                        <div class="col-md-3">
                            <div class="widget-rounded-circle card-box">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="avatar-lg">
                                            <i class="ti ti-calendar-event font-22 avatar-title text-success"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="mb-1 mt-2"><?php echo $total_workshops; ?></h5>
                                        <p class="mb-2 text-muted">Total Workshops</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="widget-rounded-circle card-box">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="avatar-lg">
                                            <i class="ti ti-star font-22 avatar-title text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="mb-1 mt-2"><?php echo $total_ratings; ?></h5>
                                        <p class="mb-2 text-muted">Total Ratings</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="widget-rounded-circle card-box">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="avatar-lg">
                                            <i class="ti ti-chart-stars font-22 avatar-title text-info"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <h5 class="mb-1 mt-2"><?php echo $overall_avg_rating; ?>/5.0</h5>
                                        <p class="mb-2 text-muted">Average Rating</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trainer Cards -->
    <div class="row">
        <?php foreach ($trainers as $trainer): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start mb-3">
                        <img src="<?php echo $trainer['image'] ? $uri.$trainer['image'] : 'assets/images/users/avatar-1.jpg'; ?>" 
                             class="rounded-circle avatar-lg" alt="trainer-image">
                        <div class="ms-3">
                            <h4 class="mt-0 mb-1"><?php echo htmlspecialchars($trainer['name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars(substr($trainer['designation'], 0, 30)); ?>...</p>
                        </div>
                    </div>

                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <h4><?php echo $trainer['total_workshops']; ?></h4>
                            <p class="mb-0 text-muted text-truncate">Workshops</p>
                        </div>
                        <div class="col-4">
                            <h4><?php echo $trainer['total_ratings']; ?></h4>
                            <p class="mb-0 text-muted text-truncate">Ratings</p>
                        </div>
                        <div class="col-4">
                            <h4><?php echo $trainer['avg_rating']; ?></h4>
                            <p class="mb-0 text-muted text-truncate">Avg Rating</p>
                        </div>
                    </div>

                    <!-- Rating Breakdown -->
                    <div class="pt-3">
                        <h5 class="mb-3">Rating Distribution</h5>
                        <?php
                        $star_ratings = [
                            5 => $trainer['five_star'],
                            4 => $trainer['four_star'],
                            3 => $trainer['three_star'],
                            2 => $trainer['two_star'],
                            1 => $trainer['one_star']
                        ];

                        foreach ($star_ratings as $stars => $count):
                            $percentage = $trainer['total_ratings'] > 0 ? 
                                round(($count / $trainer['total_ratings']) * 100) : 0;
                        ?>
                        <div class="row align-items-center mb-2">
                            <div class="col-2">
                                <?php echo $stars; ?> <i class="ti ti-star text-warning"></i>
                            </div>
                            <div class="col-8">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-2">
                                <?php echo $count; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- end row -->

</div>
</div>
</div>
</div>

<!-- container -->

<?php require_once 'includes/footer.php'; ?> 
