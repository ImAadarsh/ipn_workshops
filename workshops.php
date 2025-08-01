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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'upcoming'; // Default to upcoming
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$trainer_filter = isset($_GET['trainer']) ? $_GET['trainer'] : '';

// Build query
$where_conditions = ["is_deleted = 0"];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR trainer_name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($type_filter)) {
    if ($type_filter === 'upcoming') {
        $where_conditions[] = "start_date > NOW()";
    } elseif ($type_filter === 'completed') {
        $where_conditions[] = "start_date < NOW()";
    }
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 'i';
}

if (!empty($trainer_filter)) {
    $where_conditions[] = "trainer_name LIKE ?";
    $params[] = "%$trainer_filter%";
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM workshops $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_count = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
    $total_count = mysqli_fetch_assoc($count_result)['total'];
}

$total_pages = ceil($total_count / $per_page);

// Fetch workshops
$workshops_sql = "SELECT * FROM workshops 
                  $where_clause 
                  ORDER BY start_date ASC 
                  LIMIT ? OFFSET ?";

$workshops_stmt = mysqli_prepare($conn, $workshops_sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    mysqli_stmt_bind_param($workshops_stmt, $param_types, ...$params);
} else {
    mysqli_stmt_bind_param($workshops_stmt, 'ii', $per_page, $offset);
}

mysqli_stmt_execute($workshops_stmt);
$workshops_result = mysqli_stmt_get_result($workshops_stmt);
$workshops = [];
while ($row = mysqli_fetch_assoc($workshops_result)) {
    $workshops[] = $row;
}
mysqli_stmt_close($workshops_stmt);

// Get unique trainers for filter
$trainers_sql = "SELECT DISTINCT trainer_name FROM workshops WHERE is_deleted = 0 AND trainer_name IS NOT NULL AND trainer_name != '' ORDER BY trainer_name";
$trainers_result = mysqli_query($conn, $trainers_sql);
$trainers = [];
while ($row = mysqli_fetch_assoc($trainers_result)) {
    $trainers[] = $row['trainer_name'];
}

$page_title = "Workshops";
include 'includes/head.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Workshops | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
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

        <!-- ============================================================== -->
        <!-- Start Page Content here -->
        <!-- ============================================================== -->
        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Workshops</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Workshops</h4>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Search workshops, trainers...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Type</label>
                                        <select class="form-select" name="type">
                                            <option value="upcoming" <?php echo $type_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                            <option value="completed" <?php echo $type_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="" <?php echo $type_filter === '' ? 'selected' : ''; ?>>All Types</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="">All Status</option>
                                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Trainer</label>
                                        <select class="form-select" name="trainer">
                                            <option value="">All Trainers</option>
                                            <?php foreach ($trainers as $trainer): ?>
                                                <option value="<?php echo htmlspecialchars($trainer); ?>" 
                                                        <?php echo $trainer_filter === $trainer ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($trainer); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ti ti-search me-1"></i> Filter
                                            </button>
                                            <a href="workshops.php" class="btn btn-outline-secondary">
                                                <i class="ti ti-refresh me-1"></i> Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Summary -->
                <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter) || !empty($trainer_filter)): ?>
                <div class="alert alert-info mb-3">
                    <i class="ti ti-info-circle me-1"></i>
                    <strong>Filtered Results:</strong> 
                    <?php echo $total_count; ?> workshop(s) found
                    <?php if (!empty($search)): ?> • Search: "<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                    <?php if (!empty($type_filter)): ?> • Type: <?php echo ucfirst($type_filter); ?><?php endif; ?>
                    <?php if (!empty($status_filter)): ?> • Status: <?php echo $status_filter === '1' ? 'Active' : 'Inactive'; ?><?php endif; ?>
                    <?php if (!empty($trainer_filter)): ?> • Trainer: <?php echo htmlspecialchars($trainer_filter); ?><?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Workshops Grid -->
                <div class="row">
                    <?php if (empty($workshops)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="ti ti-book-off fs-1 text-muted mb-3"></i>
                                    <h5 class="text-muted">No workshops found</h5>
                                    <p class="text-muted">Try adjusting your search criteria or filters.</p>
                                    <a href="workshops.php" class="btn btn-primary">
                                        <i class="ti ti-refresh me-1"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($workshops as $workshop): ?>
                            <div class="col-xl-6 col-lg-6 col-md-6 mb-4">
                                <div class="card workshop-card h-100">
                                    <div class="card-body">
                                        <div class="workshop-header mb-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h5 class="workshop-title mb-1"><?php echo htmlspecialchars($workshop['name']); ?></h5>
                                                <div class="workshop-badges">
                                                    <?php if ($workshop['start_date'] > date('Y-m-d H:i:s')): ?>
                                                        <span class="badge bg-success">Upcoming</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Completed</span>
                                                    <?php endif; ?>
                                                    <?php if ($workshop['status'] == 1): ?>
                                                        <span class="badge bg-primary">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <p class="text-muted mb-0">
                                                <i class="ti ti-user me-1"></i>
                                                <?php echo htmlspecialchars($workshop['trainer_name']); ?>
                                            </p>
                                        </div>

                                        <?php if ($workshop['image']): ?>
                                            <div class="workshop-image mb-3">
                                                <img src="<?php echo $URL.$workshop['image']; ?>" 
                                                     class="img-fluid rounded" 
                                                     alt="Workshop Image"
                                                     style="max-height: 200px; width: 100%; object-fit: cover;">
                                            </div>
                                        <?php endif; ?>

                                        <div class="workshop-details mb-3">
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="detail-item">
                                                        <i class="ti ti-calendar text-primary"></i>
                                                        <div>
                                                            <small class="text-muted">Date & Time</small>
                                                            <div class="fw-bold">
                                                                <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="detail-item">
                                                        <i class="ti ti-clock text-success"></i>
                                                        <div>
                                                            <small class="text-muted">Duration</small>
                                                            <div class="fw-bold"><?php echo $workshop['duration']; ?> mins</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="detail-item">
                                                        <i class="ti ti-currency-rupee text-warning"></i>
                                                        <div>
                                                            <small class="text-muted">Price</small>
                                                            <div class="fw-bold">
                                                                <?php if ($workshop['cut_price']): ?>
                                                                    <span class="text-decoration-line-through text-muted">₹<?php echo number_format($workshop['cut_price'], 2); ?></span>
                                                                    <span class="text-success">₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-success">₹<?php echo number_format($workshop['price'], 2); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="detail-item">
                                                        <i class="ti ti-users text-info"></i>
                                                        <div>
                                                            <small class="text-muted">CPD Points</small>
                                                            <div class="fw-bold"><?php echo $workshop['cpd'] ?? 'N/A'; ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- <?php if (!empty($workshop['description'])): ?>
                                            <div class="workshop-description mb-3">
                                                <small class="text-muted">Description:</small>
                                                <p class="mb-0"><?php echo htmlspecialchars(substr($workshop['description'], 0, 100)); ?>
                                                    <?php if (strlen($workshop['description']) > 100): ?>...<?php endif; ?>
                                                </p>
                                            </div>
                                        <?php endif; ?> -->

                                        <div class="workshop-features mb-3">
                                            <?php if ($workshop['rlink']): ?>
                                                <span class="badge bg-success me-1">
                                                    <i class="ti ti-video me-1"></i> Recording
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($workshop['meeting_id'] && $workshop['passcode']): ?>
                                                <span class="badge bg-primary me-1">
                                                    <i class="ti ti-brand-zoom me-1"></i> Zoom
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($workshop['type'] == 0): ?>
                                                <span class="badge bg-info me-1">
                                                    <i class="ti ti-book me-1"></i> Workshop
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning me-1">
                                                    <i class="ti ti-calendar me-1"></i> Event
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="workshop-actions">
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="workshop-details.php?id=<?php echo $workshop['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="ti ti-eye me-1"></i> View Details
                                                </a>
                                                <a href="public_workshop_links.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                   class="btn btn-warning btn-sm" 
                                                   target="_blank" 
                                                   title="View Public School Enrollment Links">
                                                    <i class="ti ti-link"></i>
                                                </a>
                                                <?php if ($workshop['rlink']): ?>
                                                    <a href="<?php echo $workshop['rlink']; ?>" 
                                                       class="btn btn-success btn-sm" 
                                                       target="_blank">
                                                        <i class="ti ti-video"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <div class="d-flex flex-wrap gap-1">
                                                    <a href="https://ipnacademy.in/feedback.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       target="_blank" 
                                                       title="Feedback">
                                                        <i class="ti ti-message-circle"></i>
                                                    </a>
                                                    <a href="https://ipnacademy.in/feedback_report.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       target="_blank" 
                                                       title="Feedback Report">
                                                        <i class="ti ti-report"></i>
                                                    </a>
                                                    <a href="https://ipnacademy.in/workshop_assessment.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       target="_blank" 
                                                       title="Assessment">
                                                        <i class="ti ti-clipboard-check"></i>
                                                    </a>
                                                    <a href="https://ipnacademy.in/workshop_assessment_report.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       target="_blank" 
                                                       title="Assessment Report">
                                                        <i class="ti ti-file-report"></i>
                                                    </a>
                                                    <a href="https://ipnacademy.in/workshop_mcq.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       target="_blank" 
                                                       title="MCQ">
                                                        <i class="ti ti-clipboard-check"></i>
                                                    </a>
                                                    <a href="https://ipnacademy.in/workshop_mcq_report.php?workshop_id=<?php echo $workshop['id']; ?>" 
                                                       class="btn btn-outline-info btn-sm" 
                                                       target="_blank" 
                                                       title="MCQ Report">
                                                        <i class="ti ti-file-report"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-center">
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&trainer=<?php echo urlencode($trainer_filter); ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&trainer=<?php echo urlencode($trainer_filter); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&trainer=<?php echo urlencode($trainer_filter); ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->
        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core JS -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>

</body>

</html>

<style>
.workshop-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e9ecef;
}

.workshop-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.workshop-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
    line-height: 1.3;
}

.workshop-badges {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.detail-item i {
    margin-top: 0.25rem;
    width: 16px;
}

.workshop-description {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.5rem;
    border-left: 3px solid #007bff;
}

.workshop-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.workshop-actions {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
}

.workshop-image {
    position: relative;
    overflow: hidden;
    border-radius: 0.5rem;
}

.workshop-image img {
    transition: transform 0.3s ease;
}

.workshop-card:hover .workshop-image img {
    transform: scale(1.05);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .workshop-card {
        margin-bottom: 1rem;
    }
    
    .detail-item {
        margin-bottom: 0.5rem;
    }
    
    .workshop-actions .btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
}

/* Animation for cards */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.workshop-card {
    animation: fadeInUp 0.6s ease-out;
}

.workshop-card:nth-child(2) {
    animation-delay: 0.1s;
}

.workshop-card:nth-child(3) {
    animation-delay: 0.2s;
}

.workshop-card:nth-child(4) {
    animation-delay: 0.3s;
}
</style>
 