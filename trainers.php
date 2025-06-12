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

// Get user type and name from session
$userType = $_SESSION['user_type'];
$userName = $_SESSION['user_name'];
$userId = $_SESSION['user_id'];

// Initialize filters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'designation' => isset($_GET['designation']) ? $_GET['designation'] : '',
    'specialization' => isset($_GET['specialization']) ? $_GET['specialization'] : '',
    'rating_min' => isset($_GET['rating_min']) ? $_GET['rating_min'] : '',
    'rating_max' => isset($_GET['rating_max']) ? $_GET['rating_max'] : '',
    'bookings_min' => isset($_GET['bookings_min']) ? $_GET['bookings_min'] : '',
    'bookings_max' => isset($_GET['bookings_max']) ? $_GET['bookings_max'] : ''
];

// Build WHERE clause based on filters
$where_conditions = [];

if ($filters['search']) {
    $search = mysqli_real_escape_string($conn, $filters['search']);
    $where_conditions[] = "(t.first_name LIKE '%$search%' OR t.last_name LIKE '%$search%' OR t.email LIKE '%$search%')";
}

if ($filters['designation']) {
    $designation = mysqli_real_escape_string($conn, $filters['designation']);
    $where_conditions[] = "t.designation = '$designation'";
}

if ($filters['specialization']) {
    $specialization = mysqli_real_escape_string($conn, $filters['specialization']);
    $where_conditions[] = "ts.specialization = '$specialization'";
}

if ($filters['rating_min']) {
    $rating_min = mysqli_real_escape_string($conn, $filters['rating_min']);
    $where_conditions[] = "AVG(tr.rating) >= '$rating_min'";
}

if ($filters['rating_max']) {
    $rating_max = mysqli_real_escape_string($conn, $filters['rating_max']);
    $where_conditions[] = "AVG(tr.rating) <= '$rating_max'";
}

if ($filters['bookings_min']) {
    $bookings_min = mysqli_real_escape_string($conn, $filters['bookings_min']);
    $where_conditions[] = "COUNT(DISTINCT b.id) >= '$bookings_min'";
}

if ($filters['bookings_max']) {
    $bookings_max = mysqli_real_escape_string($conn, $filters['bookings_max']);
    $where_conditions[] = "COUNT(DISTINCT b.id) <= '$bookings_max'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT t.id) as total 
              FROM trainers t 
              LEFT JOIN trainer_specializations ts ON t.id = ts.trainer_id 
              LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id 
              LEFT JOIN time_slots tsl ON ta.id = tsl.trainer_availability_id 
              LEFT JOIN bookings b ON tsl.id = b.time_slot_id 
              LEFT JOIN trainer_reviews tr ON t.id = tr.trainer_id 
              $where_clause";

$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_records / $limit);

// Get trainers with filters and pagination
$sql = "SELECT t.*, 
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT CASE WHEN b.status = 'completed' THEN b.id END) as completed_sessions,
        COALESCE(AVG(tr.rating), 0) as avg_rating,
        COUNT(DISTINCT tr.id) as total_reviews,
        GROUP_CONCAT(DISTINCT ts.specialization) as specializations
        FROM trainers t 
        LEFT JOIN trainer_specializations ts ON t.id = ts.trainer_id 
        LEFT JOIN trainer_availabilities ta ON t.id = ta.trainer_id 
        LEFT JOIN time_slots tsl ON ta.id = tsl.trainer_availability_id 
        LEFT JOIN bookings b ON tsl.id = b.time_slot_id 
        LEFT JOIN trainer_reviews tr ON t.id = tr.trainer_id 
        $where_clause
        GROUP BY t.id 
        ORDER BY total_bookings DESC 
        LIMIT $offset, $limit";

$result = mysqli_query($conn, $sql);
$trainers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $trainers[] = $row;
}

// Get distinct values for filters
$sql_designations = "SELECT DISTINCT designation FROM trainers WHERE designation IS NOT NULL ORDER BY designation";
$sql_specializations = "SELECT DISTINCT specialization FROM trainer_specializations ORDER BY specialization";

$designations = mysqli_query($conn, $sql_designations);
$specializations = mysqli_query($conn, $sql_specializations);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Trainers | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    
    <!-- Custom CSS -->
    <style>
        .card.shadow-none {
            box-shadow: none !important;
        }
        .card.shadow-none.border {
            border: 1px solid #e5e9f2 !important;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-label {
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: #6c757d;
        }
        .trainer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 14px;
        }
        .rating-count {
            color: #6c757d;
            font-size: 12px;
        }
        .specialization-badge {
            display: inline-block;
            padding: 4px 8px;
            margin: 2px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 12px;
            color: #495057;
        }
        .range-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .range-input .form-control {
            width: calc(50% - 5px);
        }
    </style>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">
        
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <!-- Topbar Start -->
        <?php include 'includes/topbar.php'; ?>
        <!-- Topbar End -->

        <div class="page-content">
            <div class="page-container">
            <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="users.php">Trainers</a></li>
                                </ol>
                            </div>
                            <h4 class="page-title">All Trainers</h4>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card shadow-none border">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Search</label>
                                            <input type="text" class="form-control" name="search" 
                                                   placeholder="Search by name or email..." 
                                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Designation</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="designation">
                                                <option value="">All Designations</option>
                                                <?php while($designation = mysqli_fetch_assoc($designations)): ?>
                                                    <option value="<?php echo $designation['designation']; ?>" 
                                                            <?php echo $filters['designation'] == $designation['designation'] ? 'selected' : ''; ?>>
                                                        <?php echo $designation['designation']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Specialization</label>
                                            <select data-choices data-choices-sorting-false class="form-select" name="specialization">
                                                <option value="">All Specializations</option>
                                                <?php while($specialization = mysqli_fetch_assoc($specializations)): ?>
                                                    <option value="<?php echo $specialization['specialization']; ?>" 
                                                            <?php echo $filters['specialization'] == $specialization['specialization'] ? 'selected' : ''; ?>>
                                                        <?php echo $specialization['specialization']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Rating Range</label>
                                            <div class="range-input">
                                                <input type="number" class="form-control" name="rating_min" 
                                                       placeholder="Min" min="0" max="5" step="0.1"
                                                       value="<?php echo $filters['rating_min']; ?>">
                                                <input type="number" class="form-control" name="rating_max" 
                                                       placeholder="Max" min="0" max="5" step="0.1"
                                                       value="<?php echo $filters['rating_max']; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">Bookings Range</label>
                                            <div class="range-input">
                                                <input type="number" class="form-control" name="bookings_min" 
                                                       placeholder="Min" min="0"
                                                       value="<?php echo $filters['bookings_min']; ?>">
                                                <input type="number" class="form-control" name="bookings_max" 
                                                       placeholder="Max" min="0"
                                                       value="<?php echo $filters['bookings_max']; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                                                <a href="trainers.php" class="btn btn-light">Reset</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trainers Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="header-title">Manage Trainers</h4>
                                <div>
                                    <?php if ($userType === 'admin'): ?>
                                    <a href="add_trainer.php" class="btn btn-primary">
                                        <i class="ti ti-plus me-1"></i> Add New Trainer
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-centered table-nowrap mb-0">
                                    <thead>
                                        <tr>
                                            <th>Trainer</th>
                                            <th>Specializations</th>
                                            <th>Total Bookings</th>
                                            <th>Completed Sessions</th>
                                            <th>Rating</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trainers as $trainer): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo $uri.$trainer['profile_img']; ?>" 
                                                             alt="<?php echo $trainer['first_name']; ?>" 
                                                             class="trainer-avatar me-2">
                                                        <div>
                                                            <h5 class="mb-0"><?php echo $trainer['first_name'] . ' ' . $trainer['last_name']; ?></h5>
                                                            <small class="text-muted"><?php echo $trainer['designation']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $specializations = explode(',', $trainer['specializations']);
                                                    foreach ($specializations as $spec): 
                                                        if ($spec):
                                                    ?>
                                                        <span class="specialization-badge"><?php echo $spec; ?></span>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </td>
                                                <td><?php echo $trainer['total_bookings']; ?></td>
                                                <td><?php echo $trainer['completed_sessions']; ?></td>
                                                <td>
                                                    <div class="rating-stars">
                                                        <?php 
                                                        $rating = round($trainer['avg_rating'], 1);
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            if ($i <= $rating) {
                                                                echo '★';
                                                            } else {
                                                                echo '☆';
                                                            }
                                                        }
                                                        ?>
                                                        <span class="rating-count">(<?php echo $trainer['total_reviews']; ?>)</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-link dropdown-toggle" 
                                                                type="button" 
                                                                id="dropdownMenuButton<?php echo $trainer['id']; ?>" 
                                                                data-bs-toggle="dropdown" 
                                                                aria-expanded="false">
                                                            <i class="ti ti-dots-vertical font-size-18"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end" 
                                                            aria-labelledby="dropdownMenuButton<?php echo $trainer['id']; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="view_trainer.php?id=<?php echo $trainer['id']; ?>">
                                                                    <i class="ti ti-eye me-1"></i> View Details
                                                                </a>
                                                            </li>
                                                            <?php if ($userType === 'admin'): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="edit_trainer.php?id=<?php echo $trainer['id']; ?>">
                                                                        <i class="ti ti-edit me-1"></i> Edit
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger delete-trainer" 
                                                                       href="javascript:void(0);" 
                                                                       data-id="<?php echo $trainer['id']; ?>">
                                                                        <i class="ti ti-trash me-1"></i> Delete
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted">
                                        Showing <?php echo ($page - 1) * $limit + 1; ?> to 
                                        <?php echo min($page * $limit, $total_records); ?> of 
                                        <?php echo $total_records; ?> entries
                                    </div>
                                    <ul class="pagination pagination-rounded mb-0">
                                        <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">
                                                <i class="ti ti-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">
                                                <i class="ti ti-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
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
    </div>

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });

            // Delete trainer confirmation
            document.querySelectorAll('.delete-trainer').forEach(function(element) {
                element.addEventListener('click', function() {
                    var trainerId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this trainer? This action cannot be undone.')) {
                        fetch('controllers/delete_trainer.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `trainer_id=${trainerId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message || 'Failed to delete trainer');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting the trainer');
                        });
                    }
                });
            });
        });
    </script>
</body>
</html> 