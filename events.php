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


// Fetch events
$sql = "SELECT * FROM events ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Events | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <style>
        .event-image {
            max-width: 100px;
            height: auto;
        }
        @media (max-width: 768px) {
            .event-image {
                max-width: 60px;
            }
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
                margin: 2px 0;
                display: inline-block;
            }
            .dtr-details {
                width: 100%;
            }
            .dtr-details li {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .dtr-details li:last-child {
                border-bottom: none;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Events</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Events</h4>
                            <a href="event_add.php" class="btn btn-primary">Add New Event</a>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])) : ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])) : ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <table id="events-table" class="table table-striped table-bordered dt-responsive nowrap">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Location</th>
                                            <th>Date & Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo $uri . htmlspecialchars($row['image']); ?>" 
                                                         alt="Event Image" 
                                                         class="event-image">
                                                </td>
                                                <td data-order="<?php echo htmlspecialchars($row['name']); ?>">
                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                                <td data-order="<?php echo strtotime($row['event_start']); ?>">
                                                    <?php echo date('M d, Y H:i', strtotime($row['event_start'])); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-wrap">
                                                        <a href="event_view.php?id=<?php echo $row['id']; ?>" 
                                                           class="btn btn-sm btn-info">View</a>
                                                        <a href="event_edit.php?id=<?php echo $row['id']; ?>" 
                                                           class="btn btn-sm btn-primary">Edit</a>
                                                        <button onclick="deleteEvent(<?php echo $row['id']; ?>)" 
                                                                class="btn btn-sm btn-danger">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/theme_settings.php'; ?>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    <!-- App js -->
    <script src="assets/js/app.js"></script>
    <!-- DataTables js -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#events-table').DataTable({
                responsive: true,
                order: [[3, "desc"]],
                columnDefs: [
                    { responsivePriority: 1, targets: [1, 4] }, // Name and Actions columns
                    { responsivePriority: 2, targets: 0 }, // Image column
                    { responsivePriority: 3, targets: 3 }, // Date column
                    { responsivePriority: 4, targets: 2 }  // Location column
                ]
            });
        });

        function deleteEvent(id) {
            if (confirm('Are you sure you want to delete this event?')) {
                window.location.href = 'event_delete.php?id=' + id;
            }
        }
    </script>
</body>
</html> 