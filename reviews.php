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

// Check if user is admin
if ($userType !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch reviews with user and trainer information
$sql = "SELECT tr.*, 
        u.first_name as user_first_name, u.last_name as user_last_name, 
        t.first_name as trainer_first_name, t.last_name as trainer_last_name, 
        b.id as booking_id, b.status as booking_status,
        DATE_FORMAT(tr.created_at, '%M %d, %Y %h:%i %p') as formatted_date
        FROM trainer_reviews tr 
        LEFT JOIN users u ON tr.user_id = u.id 
        LEFT JOIN trainers t ON tr.trainer_id = t.id 
        LEFT JOIN bookings b ON tr.booking_id = b.id 
        ORDER BY tr.created_at DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Reviews Management | IPN Academy Admin</title>
    <?php include 'includes/head.php'; ?>
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <style>
        .star-rating {
            color: #ffc107;
            font-size: 1.2em;
        }
        .review-text {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
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
            .review-text {
                max-width: 200px;
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
                                    <li class="breadcrumb-item active">Reviews Management</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Reviews Management</h4>
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
                                <!-- Filter Controls -->
                                <div class="filter-section">
                                    <form id="filter-form" onsubmit="return false;">
                                        <div class="row">
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">Filter by Rating</label>
                                                <select data-choices data-choices-sorting-false id="rating-filter" class="form-select">
                                                    <option value="">All Ratings</option>
                                                    <option value="5">5 Stars</option>
                                                    <option value="4">4 Stars</option>
                                                    <option value="3">3 Stars</option>
                                                    <option value="2">2 Stars</option>
                                                    <option value="1">1 Star</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">Filter by Status</label>
                                                <select data-choices data-choices-sorting-false id="status-filter" class="form-select">
                                                    <option value="">All Statuses</option>
                                                    <option value="completed">Completed</option>
                                                    <option value="cancelled">Cancelled</option>
                                                    <option value="pending">Pending</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">From Date</label>
                                                <input type="text" id="date-from" class="form-control" placeholder="dd/mm/yyyy" autocomplete="on">
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <label class="form-label">To Date</label>
                                                <input type="text" id="date-to" class="form-control" placeholder="dd/mm/yyyy" autocomplete="on">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <button type="submit" id="apply-filters" class="btn btn-primary">
                                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                                </button>
                                                <button type="button" id="reset-filters" class="btn btn-secondary ms-2">
                                                    <i class="fas fa-undo me-1"></i> Reset Filters
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="table-responsive">
                                    <table id="reviews-table" class="table table-striped table-bordered dt-responsive nowrap">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Trainer</th>
                                                <th>Rating</th>
                                                <th>Review</th>
                                                <th>Booking Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($result)) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['user_first_name'] . ' ' . $row['user_last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['trainer_first_name'] . ' ' . $row['trainer_last_name']); ?></td>
                                                    <td data-rating="<?php echo $row['rating']; ?>">
                                                        <div class="star-rating">
                                                            <?php
                                                            $rating = (int)$row['rating'];
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                echo ($i <= $rating) ? '★' : '☆';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="review-text" title="<?php echo htmlspecialchars($row['review']); ?>">
                                                            <?php echo htmlspecialchars($row['review']); ?>
                                                        </div>
                                                    </td>
                                                    <td data-status="<?php echo strtolower($row['booking_status'] ?? ''); ?>">
                                                        <span class="badge <?php 
                                                            echo match($row['booking_status']) {
                                                                'completed' => 'bg-success',
                                                                'cancelled' => 'bg-danger',
                                                                'pending' => 'bg-warning',
                                                                default => 'bg-secondary'
                                                            };
                                                        ?>">
                                                            <?php echo ucfirst($row['booking_status'] ?? 'Unknown'); ?>
                                                        </span>
                                                    </td>
                                                    <td data-order="<?php echo strtotime($row['created_at']); ?>">
                                                        <?php echo $row['formatted_date']; ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1 flex-wrap">
                                                            <a href="review_edit.php?id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-sm btn-primary">Edit</a>
                                                            <button onclick="deleteReview(<?php echo $row['id']; ?>)" 
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
            </div>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Moment.js for date handling -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        $(document).ready(function() {
            // Initialize date pickers
            flatpickr("#date-from", {
                dateFormat: "d/m/Y",
                allowInput: true
            });
            
            flatpickr("#date-to", {
                dateFormat: "d/m/Y",
                allowInput: true
            });

            // Initialize DataTable
            var reviewsTable = $('#reviews-table').DataTable({
                responsive: true,
                order: [[5, "desc"]],
                columnDefs: [
                    { responsivePriority: 1, targets: [2, 6] },
                    { responsivePriority: 2, targets: [0, 1] },
                    { responsivePriority: 3, targets: 3 },
                    { responsivePriority: 4, targets: 4 },
                    { responsivePriority: 5, targets: 5 }
                ]
            });

            // Custom filtering function
            function customFilter(settings, data, dataIndex) {
                var rating = $('#rating-filter').val();
                var status = $('#status-filter').val().toLowerCase();
                var dateFrom = $('#date-from').val();
                var dateTo = $('#date-to').val();

                // Check rating
                if (rating && $(data[2]).attr('data-rating') != rating) {
                    return false;
                }

                // Check status
                var rowStatus = data[4].toLowerCase().trim();
                if (status && !rowStatus.includes(status)) {
                    return false;
                }

                // Check date range
                if (dateFrom || dateTo) {
                    var rowDate = moment(data[5], "MMMM DD, YYYY hh:mm A");
                    
                    if (dateFrom) {
                        var from = moment(dateFrom, "DD/MM/YYYY").startOf('day');
                        if (rowDate.isBefore(from)) return false;
                    }
                    
                    if (dateTo) {
                        var to = moment(dateTo, "DD/MM/YYYY").endOf('day');
                        if (rowDate.isAfter(to)) return false;
                    }
                }

                return true;
            }

            // Apply filters button click
            $('#apply-filters').on('click', function() {
                // Remove any existing custom search functions
                $.fn.dataTable.ext.search = [];
                
                // Add our custom filter function
                $.fn.dataTable.ext.search.push(customFilter);
                
                // Redraw the table
                reviewsTable.draw();
            });

            // Reset filters button click
            $('#reset-filters').on('click', function() {
                // Clear all inputs
                $('#rating-filter').val('');
                $('#status-filter').val('');
                $('#date-from').val('');
                $('#date-to').val('');

                // Remove all custom search functions
                $.fn.dataTable.ext.search = [];

                // Redraw the table
                reviewsTable.draw();
            });
        });

        function deleteReview(id) {
            if (confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
                window.location.href = 'review_delete.php?id=' + id;
            }
        }
    </script>

    <!-- App JS (load last) -->
    <script src="assets/js/app.js"></script>
</body>
</html> 