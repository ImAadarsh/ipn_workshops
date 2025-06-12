<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection and controller
$conn = require_once 'config/config.php';
require_once 'controllers/UserController.php';

// Get user type and name from session
$userType = $_SESSION['user_type'];
$userName = $_SESSION['user_name'];
$userId = $_SESSION['user_id'];

// Initialize UserController
$userController = new UserController($conn);

// Get current page from URL
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// Get filters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'grade' => isset($_GET['grade']) ? $_GET['grade'] : '',
    'city' => isset($_GET['city']) ? $_GET['city'] : '',
    'school' => isset($_GET['school']) ? $_GET['school'] : ''
];

// Get users with pagination
$result = $userController->getAllUsers($page, $limit, $filters);
$users = $result['users'];
$totalPages = $result['total_pages'];
$currentPage = $result['current_page'];
$totalRecords = $result['total_records'];

// Fetch distinct values for filters
$sql_grades = "SELECT DISTINCT grade FROM users WHERE grade IS NOT NULL AND grade != '' ORDER BY grade";
$sql_cities = "SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' ORDER BY city";
$sql_schools = "SELECT DISTINCT school FROM users WHERE school IS NOT NULL AND school != '' ORDER BY school";

$grades = mysqli_query($conn, $sql_grades);
$cities = mysqli_query($conn, $sql_cities);
$schools = mysqli_query($conn, $sql_schools);

// Function to get initials from name
function getInitials($firstName, $lastName) {
    $firstInitial = mb_substr($firstName, 0, 1, 'UTF-8');
    $lastInitial = mb_substr($lastName, 0, 1, 'UTF-8');
    return strtoupper($firstInitial . $lastInitial);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Users | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet" type="text/css" />

    <!-- Custom CSS for table -->
    <style>
        .avatar-sm {
            height: 2.5rem;
            width: 2.5rem;
        }
        .avatar-title {
            align-items: center;
            display: flex;
            font-weight: 500;
            height: 100%;
            justify-content: center;
            width: 100%;
        }
        .pagination-rounded .page-link {
            border-radius: 30px !important;
            margin: 0 3px !important;
            border: none;
        }
        .dropdown-toggle::after {
            display: none;
        }
        .dropdown-menu {
            min-width: 120px;
            margin-top: 0.125rem;
            position: absolute;
        }
        .dropdown-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .btn-group > .btn {
            margin-right: 1.25rem;
        }
        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem;
            white-space: nowrap;
        }
        .table {
            width: 100% !important;
            margin-bottom: 0;
        }
        .card-body {
            padding: 0;
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0;
        }
        .progress {
            width: 100px;
            display: inline-block;
            margin-right: 10px;
            vertical-align: middle;
        }
        .table td {
            vertical-align: middle;
        }
        .table th {
            padding: 1rem 0.75rem;
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .dropdown {
            display: inline-block;
        }
        .dropdown-menu {
            transform: none !important;
            top: 100% !important;
            right: 0 !important;
            left: auto !important;
        }
        .card-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e9f2;
        }
        .table-centered {
            margin: 0 !important;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-label {
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: #6c757d;
        }
        .progress {
            background-color: #e9ecef;
            border-radius: 0.25rem;
            overflow: hidden;
        }
        .progress-bar {
            transition: width 0.6s ease;
            height: 100%;
        }
        .table td {
            padding: 1rem 0.75rem;
        }
        .d-flex.align-items-center {
            gap: 0.5rem;
        }
        .form-group {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .col-md-1.d-flex {
            margin-top: 0 !important;
        }
        .col-md-1.d-flex .btn {
            height: 38px;
        }
        .card.shadow-none {
            box-shadow: none !important;
        }
        .card.shadow-none.border {
            border: 1px solid #e5e9f2 !important;
        }
        .row.align-items-end {
            align-items: center !important;
        }
        .form-group {
            margin-bottom: 0;
        }
        .col-md-1 {
            display: flex;
            align-items: center;
        }
        .col-md-1 .btn {
            height: 38px;
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
                                    <li class="breadcrumb-item"><a href="users.php">All Users</a></li>
                                </ol>
                            </div>
                            <h4 class="page-title">All Users</h4>
                        </div>
                    </div>
                </div>
    

                <!-- Filters Section -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div  class="card  shadow-none border">
                            <div class="card-body">
                                <form method="GET" class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <select data-choices data-choices-sorting-false class="form-select" name="grade">
                                                <option value="">All Grades</option>
                                                <?php while($grade_row = mysqli_fetch_assoc($grades)): ?>
                                                    <option value="<?php echo $grade_row['grade']; ?>" <?php echo $filters['grade'] == $grade_row['grade'] ? 'selected' : ''; ?>>
                                                        Grade <?php echo $grade_row['grade']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                            </div>
                        </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <select data-choices data-choices-sorting-false class="form-select" name="city">
                                                <option value="">All Cities</option>
                                                <?php while($city_row = mysqli_fetch_assoc($cities)): ?>
                                                    <option value="<?php echo $city_row['city']; ?>" <?php echo $filters['city'] == $city_row['city'] ? 'selected' : ''; ?>>
                                                        <?php echo $city_row['city']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                    </div>
                                        </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <select data-choices data-choices-sorting-false class="form-select" name="school">
                                                <option value="">All Schools</option>
                                                <?php while($school_row = mysqli_fetch_assoc($schools)): ?>
                                                    <option value="<?php echo $school_row['school']; ?>" <?php echo $filters['school'] == $school_row['school'] ? 'selected' : ''; ?>>
                                                        <?php echo $school_row['school']; ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                                </div>
                                            </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                                        </div>
                                </form>
                                    </div>
                                </div>
                                    </div>
                                    </div>

                <!-- Users Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h4 class="header-title">Manage Users</h4>
                                <div>
                                    <button class="btn btn-primary me-2" id="exportCSV">
                                        <i class="ti ti-file-export me-1"></i> Export CSV
                                                </button>
                                    <button class="btn btn-success" id="exportExcel">
                                        <i class="ti ti-file-spreadsheet me-1"></i> Export Excel
                                                </button>
                                    </div>
                            </div>

                            <div class="table-responsive">
                                <table id="users-table" class="table table-centered table-nowrap mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th class="ps-3" style="width: 50px;">
                                                <input type="checkbox" class="form-check-input" id="customCheck1">
                                            </th>
                                            <th>User</th>
                                            <th>Contact Info</th>
                                            <th>School Details</th>
                                            <th>Total Bookings</th>
                                            <th>Total Amount</th>
                                            <th>Amount Due</th>
                                            <th>Completion Rate</th>
                                            <th>Joined Date</th>
                                            <th class="text-center pe-3" style="width: 120px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <input type="checkbox" class="form-check-input" id="user-<?php echo $user['id']; ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($user['icon']): ?>
                                                        <img src="<?php echo $user['icon']; ?>" 
                                                             alt="" class="rounded-circle avatar-sm me-2">
                                                    <?php else: ?>
                                                        <div class="avatar-sm me-2">
                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-14">
                                                                <?php echo getInitials($user['first_name'], $user['last_name']); ?>
                                                            </span>
                                                </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h5 class="mb-0 fs-14">
                                                            <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>
                                                </h5>
                                                        <p class="mb-0 text-muted fs-12">ID: #<?php echo $user['id']; ?></p>
                                                </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="mb-0"><i class="ti ti-mail me-1"></i><?php echo $user['email']; ?></p>
                                                <p class="mb-0"><i class="ti ti-phone me-1"></i><?php echo $user['country_code'] . ' ' . $user['mobile']; ?></p>
                                            </td>
                                            <td>
                                                <p class="mb-0"><strong>School:</strong> <?php echo $user['school']; ?></p>
                                                <p class="mb-0"><strong>Grade:</strong> <?php echo $user['grade']; ?></p>
                                                <p class="mb-0"><strong>City:</strong> <?php echo $user['city']; ?></p>
                                            </td>
                                            <td><?php echo $user['total_bookings'] ?? 0; ?></td>
                                            <td>₹<?php echo number_format($user['total_amount'] ?? 0, 2); ?></td>
                                            <td>₹<?php echo number_format($user['amount_due'] ?? 0, 2); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="height: 5px; width: 100px;">
                                                        <?php 
                                                        $rate = $user['completion_rate'] ?? 0;
                                                        $colorClass = $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger');
                                                        ?>
                                                        <div class="progress-bar bg-<?php echo $colorClass; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $rate; ?>%"
                                                             aria-valuenow="<?php echo $rate; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                </div>
                                                </div>
                                                    <span class="ms-1"><?php echo number_format($rate, 1); ?>%</span>
                                                </div>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <a href="#" class="dropdown-toggle card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="ti ti-dots-vertical font-size-18"></i>
                                                    </a>
                                                    <div class="dropdown-menu dropdown-menu-end">
                                                        <a class="dropdown-item" href="edit_user.php?id=<?php echo $user['id']; ?>">
                                                            <i class="ti ti-edit me-1"></i> Edit
                                                        </a>
                                                        <a class="dropdown-item text-danger delete-user" href="javascript:void(0);" 
                                                           data-id="<?php echo $user['id']; ?>" 
                                                           data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                                            <i class="ti ti-trash me-1"></i> Delete
                                                        </a>
                                                </div>
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
                                        Showing <?php echo ($currentPage - 1) * $limit + 1; ?> to 
                                        <?php echo min($currentPage * $limit, $totalRecords); ?> of 
                                        <?php echo $totalRecords; ?> entries
                                    </div>
                                    <ul class="pagination pagination-rounded mb-0">
                                        <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&<?php echo http_build_query($filters); ?>">
                                                <i class="ti ti-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $currentPage == $i ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                        </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&<?php echo http_build_query($filters); ?>">
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
                <div class="modal-body">
                    Are you sure you want to delete <span id="deleteUserName"></span>?
                        </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                    </div>
                    </div>
                </div>
            </div>

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Core JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables Core and Extensions -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Export Plugins -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#users-table').DataTable({
                searching: false,
                ordering: true,
                paging: false,
                info: false,
                scrollX: true,
                autoWidth: false,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: 'Export CSV',
                        className: 'btn btn-primary d-none',
                        exportOptions: {
                            columns: ':not(:first-child):not(:last-child)',
                            format: {
                                body: function (data, row, column, node) {
                                    return data.replace(/<[^>]*>/g, '')
                                        .replace(/&nbsp;/g, ' ')
                                        .replace(/\n/g, ' ')
                                        .trim();
                                }
                            }
                        },
                        filename: 'users_export_<?php echo date("Y-m-d"); ?>'
                    },
                    {
                        extend: 'excel',
                        text: 'Export Excel',
                        className: 'btn btn-success d-none',
                        exportOptions: {
                            columns: ':not(:first-child):not(:last-child)'
                        },
                        filename: 'users_export_<?php echo date("Y-m-d"); ?>'
                    }
                ],
                columnDefs: [
                    { width: '50px', targets: 0 },
                    { width: '200px', targets: 1 },
                    { width: '200px', targets: 2 },
                    { width: '250px', targets: 3 },
                    { width: '100px', targets: 4 },
                    { width: '120px', targets: 5 },
                    { width: '120px', targets: 6 },
                    { width: '150px', targets: 7 },
                    { width: '120px', targets: 8 },
                    { width: '100px', targets: 9 }
                ]
            });

            // Adjust table columns on window resize
            $(window).on('resize', function() {
                table.columns.adjust();
            });

            // Custom export buttons
            $('#exportCSV').on('click', function() {
                table.button('.buttons-csv').trigger();
            });

            $('#exportExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            // Initialize dropdowns manually
            document.querySelectorAll('.dropdown-toggle').forEach(function(element) {
                new bootstrap.Dropdown(element, {
                    boundary: 'window'
                });
            });

            // Delete user functionality
            var deleteUserId = null;
            $(document).on('click', '.delete-user', function(e) {
                e.preventDefault();
                e.stopPropagation();
                deleteUserId = $(this).data('id');
                var userName = $(this).data('name');
                $('#deleteUserName').text(userName);
                $('#deleteModal').modal('show');
            });

            $('#confirmDelete').on('click', function() {
                if (deleteUserId) {
                    $.ajax({
                        url: 'controllers/delete_user.php',
                        type: 'POST',
                        data: { user_id: deleteUserId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Failed to delete user: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while deleting the user');
                        }
                    });
                }
                $('#deleteModal').modal('hide');
            });

            // Initialize tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>