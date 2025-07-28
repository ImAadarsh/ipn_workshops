<?php
require_once 'config/config.php';
require_once 'includes/head.php';

// Get workshop type filter
$type = isset($_GET['type']) ? intval($_GET['type']) : 0;

// Get workshops with question counts - only showing workshops with questions
$query = "
    SELECT 
        w.id,
        w.name,
        w.trainer_name,
        w.start_date,
        w.type,
        COUNT(q.id) as question_count
    FROM workshops w
    INNER JOIN workshop_mcq_questions q ON w.id = q.workshop_id
    WHERE w.is_deleted = 0 AND w.type = ?
    GROUP BY w.id, w.name, w.trainer_name, w.start_date, w.type
    HAVING question_count > 0
    ORDER BY w.start_date DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $type);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$workshops = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get total question counts for stats
$stats_query = "
    SELECT 
        COUNT(DISTINCT w.id) as workshop_count,
        COUNT(q.id) as total_questions
    FROM workshops w
    INNER JOIN workshop_mcq_questions q ON w.id = q.workshop_id
    WHERE w.is_deleted = 0 AND w.type = ?";

$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $type);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
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
                        <li class="breadcrumb-item active">Workshop Questions</li>
                    </ol>
                </div>
                <h4 class="page-title">Workshop Questions Overview</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-book-2 text-primary" style="font-size: 24px;"></i>
                            <div class="ms-3">
                                <h4 class="mb-0"><?php echo $stats['workshop_count']; ?></h4>
                                <p class="text-muted mb-0">Workshops with Questions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="ti ti-list-check text-success" style="font-size: 24px;"></i>
                            <div class="ms-3">
                                <h4 class="mb-0"><?php echo $stats['total_questions']; ?></h4>
                                <p class="text-muted mb-0">Total Questions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Filter Buttons -->
                    <div class="mb-4">
                        <a href="?type=0" class="btn <?php echo $type == 0 ? 'btn-primary' : 'btn-light'; ?> me-2">
                            Upcoming Workshops
                        </a>
                        <a href="?type=1" class="btn <?php echo $type == 1 ? 'btn-primary' : 'btn-light'; ?>">
                            Past Workshops
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered table-striped dt-responsive nowrap w-100" id="workshops-datatable">
                            <thead>
                                <tr>
                                    <th>Workshop Name</th>
                                    <th>Trainer</th>
                                    <th>Date</th>
                                    <th>Questions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workshops as $workshop): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($workshop['name']); ?></td>
                                    <td><?php echo htmlspecialchars($workshop['trainer_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($workshop['start_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $workshop['question_count']; ?> Questions
                                        </span>
                                    </td>
                                    <td>
                                        <a href="manage_workshop_questions.php?id=<?php echo $workshop['id']; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="ti ti-edit me-1"></i>Manage Questions
                                        </a>
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
</div>

<!-- DataTables js -->
<script src="assets/vendor/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/vendor/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/vendor/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
<script src="assets/vendor/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#workshops-datatable').DataTable({
        responsive: true,
        order: [[2, 'desc']], // Sort by date by default
        pageLength: 25
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 