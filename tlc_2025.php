<?php
include 'config/show_errors.php';
session_start();

$special_access_key = '5678y3uhsc76270e9yuwqdjq9q72u1ejqiw';
$is_logged_in = isset($_SESSION['user_id']);
$is_guest_access = !$is_logged_in && isset($_GET['uvx']) && $_GET['uvx'] === $special_access_key;

if (!$is_logged_in && !$is_guest_access) {
    header("Location: index.php");
    exit();
}

// Include database connection
$conn = require_once 'config/config.php';

// Get user info from session if logged in
if ($is_logged_in) {
    $userName = $_SESSION['user_name'];
    $userType = $_SESSION['user_type'];
}

// Fetch stats for TLC 2025 users
$stats_sql = "SELECT 
    COUNT(*) as total_users, 
    COUNT(DISTINCT city) as unique_cities, 
    COUNT(DISTINCT institute_name) as unique_institutes,
    SUM(is_tlc_new = 1) as new_users,
    SUM(is_tlc_new = 0) as existing_users
    FROM users WHERE tlc_2025 = 1";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch all users marked for TLC 2025
$users_sql = "SELECT id, name, email, mobile, country_code, city, institute_name, is_tlc_new, tlc_join_date FROM users WHERE tlc_2025 = 1 ORDER BY tlc_join_date DESC";
$users_result = mysqli_query($conn, $users_sql);
$users = [];
if ($users_result) {
    while($row = mysqli_fetch_assoc($users_result)) {
        $users[] = $row;
    }
}

// Function to get initials from a full name
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    if (!empty($words[0])) {
        $initials .= mb_substr($words[0], 0, 1, 'UTF-8');
    }
    if (count($words) > 1) {
        $initials .= mb_substr(end($words), 0, 1, 'UTF-8');
    }
    return strtoupper($initials);
}

// Fuzzy grouping function
function fuzzy_group($items, $threshold = 80) {
    $groups = [];
    foreach ($items as $item) {
        $found = false;
        foreach ($groups as $key => $group) {
            similar_text(strtolower($item), strtolower($key), $percent);
            if ($percent >= $threshold) {
                $groups[$key][] = $item;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $groups[$item] = [$item];
        }
    }
    return $groups;
}

// Build fuzzy groups and counts for cities and institutes
$city_participants = [];
$institute_participants = [];
foreach ($users as $user) {
    $city = trim($user['city']);
    $institute = trim($user['institute_name']);
    if ($city !== '') {
        $city_participants[] = $city;
    }
    if ($institute !== '') {
        $institute_participants[] = $institute;
    }
}
$city_groups = fuzzy_group($city_participants, 80);
$institute_groups = fuzzy_group($institute_participants, 80);

// Count participants per fuzzy group
function group_counts($items, $groups) {
    $counts = [];
    foreach ($groups as $group_key => $group_items) {
        $counts[$group_key] = 0;
        foreach ($items as $item) {
            foreach ($group_items as $gitem) {
                similar_text(strtolower($item), strtolower($gitem), $percent);
                if ($percent >= 80) {
                    $counts[$group_key]++;
                    break;
                }
            }
        }
    }
    return $counts;
}
$city_counts = group_counts($city_participants, $city_groups);
$institute_counts = group_counts($institute_participants, $institute_groups);

// Sort city and institute counts by participant count descending
arsort($city_counts);
arsort($institute_counts);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>TLC 2025 Users | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <style>
        .widget-icon {
            font-size: 24px;
            color: #6c757d;
        }
    </style>
    <?php if ($is_guest_access): ?>
    <style>
        .page-content {
            margin-left: 0 !important;
            padding: 20px;
        }
        .wrapper {
            padding-top: 0 !important;
        }
    </style>
    <?php endif; ?>
</head>

<body>
    <!-- Begin page -->
    <div class="wrapper">
        
        <?php if ($is_logged_in): ?>
        <!-- Sidenav Menu Start -->
        <?php include 'includes/sidenav.php'; ?>
        <!-- Sidenav Menu End -->

        <!-- Topbar Start -->
        <?php include 'includes/topbar.php'; ?>
        <!-- Topbar End -->
        <?php endif; ?>

        <div class="page-content">
            <div class="page-container">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <?php if ($is_logged_in): ?>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">TLC 2025 Users</li>
                                </ol>
                            </div>
                            <?php endif; ?>
                            <h4 class="page-title">TLC 2025 Users</h4>
                        </div>
                    </div>
                </div>
    
                <!-- Stats Section -->
                <div class="row">
                    <div class="col-xl-3 col-lg-6">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-users widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Total Users">Total Users</h5>
                                <h3 class="mt-3 mb-3"><?php echo $stats['total_users'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-user-plus widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="New Users">New Users</h5>
                                <h3 class="mt-3 mb-3"><?php echo $stats['new_users'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-user widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Existing Users">Existing Users</h5>
                                <h3 class="mt-3 mb-3"><?php echo $stats['existing_users'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <a href="#unique-cities-table" class="text-decoration-none card-link-scroll">
                        <div class="card widget-flat" style="cursor:pointer;">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-building-community widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Unique Cities">Unique Cities</h5>
                                <h3 class="mt-3 mb-3"><?php echo count($city_counts); ?></h3>
                            </div>
                        </div>
                        </a>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <a href="#unique-institutes-table" class="text-decoration-none card-link-scroll">
                        <div class="card widget-flat" style="cursor:pointer;">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-school widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Unique Institutes">Unique Institutes</h5>
                                <h3 class="mt-3 mb-3"><?php echo count($institute_counts); ?></h3>
                            </div>
                        </div>
                        </a>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h4 class="header-title mb-0">TLC 2025 User List</h4>
                                <div>
                                    <button class="btn btn-primary me-2" id="exportCSV">
                                        <i class="ti ti-file-export me-1"></i> Export CSV
                                    </button>
                                    <button class="btn btn-success" id="exportExcel">
                                        <i class="ti ti-file-spreadsheet me-1"></i> Export Excel
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tlc-users-table" class="table table-centered table-striped dt-responsive nowrap w-100">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Contact Info</th>
                                                <th>Institute</th>
                                                <th>City</th>
                                                <th>TLC Join Date</th>
                                                <th>User Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2 flex-shrink-0">
                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-14">
                                                                <?php echo getInitials($user['name']); ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-0 fs-14"><?php echo htmlspecialchars($user['name']); ?></h5>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="mb-0"><i class="ti ti-mail me-1"></i><?php echo htmlspecialchars($user['email']); ?></p>
                                                    <p class="mb-0"><i class="ti ti-phone me-1"></i><?php echo htmlspecialchars($user['country_code'] . ' ' . $user['mobile']); ?></p>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['institute_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['city']); ?></td>
                                                <td<?php if ($user['tlc_join_date']) { echo ' data-order=\"' . strtotime($user['tlc_join_date']) . '\"'; } ?>><?php
                                                    if ($user['tlc_join_date']) {
                                                        $dt = new DateTime($user['tlc_join_date'], new DateTimeZone('UTC'));
                                                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                        echo $dt->format('d M Y, h:i A');
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?></td>
                                                <td><?php echo $user['is_tlc_new'] == 1 ? '<span class="badge bg-danger">New</span>' : '<span class="badge bg-secondary">Existing</span>'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fuzzy Unique Cities Table -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card" id="unique-cities-table">
                            <div class="card-header"><strong>Unique Cities (Fuzzy Grouped)</strong></div>
                            <div class="card-body p-2">
                                <input type="text" class="form-control mb-2" id="city-search" placeholder="Search city...">
                                <table class="table table-sm table-bordered mb-0" id="city-table">
                                    <thead><tr><th>#</th><th>City</th><th>Participants</th></tr></thead>
                                    <tbody>
                                    <?php $i=1; foreach ($city_counts as $city => $count): ?>
                                        <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($city); ?></td><td><?php echo $count; ?></td></tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card" id="unique-institutes-table">
                            <div class="card-header"><strong>Unique Institutes (Fuzzy Grouped)</strong></div>
                            <div class="card-body p-2">
                                <input type="text" class="form-control mb-2" id="institute-search" placeholder="Search institute...">
                                <table class="table table-sm table-bordered mb-0" id="institute-table">
                                    <thead><tr><th>#</th><th>Institute</th><th>Participants</th></tr></thead>
                                    <tbody>
                                    <?php $i=1; foreach ($institute_counts as $inst => $count): ?>
                                        <tr><td><?php echo $i++; ?></td><td><?php echo htmlspecialchars($inst); ?></td><td><?php echo $count; ?></td></tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php if ($is_logged_in): ?>
            <!-- Footer Start -->
            <?php include 'includes/footer.php'; ?>
            <!-- end Footer -->
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_logged_in): ?>
    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>
    <?php endif; ?>

    <!-- Core JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables Core and Extensions -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#tlc-users-table').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: 'Export CSV',
                        className: 'd-none',
                        filename: 'tlc_2025_users_<?php echo date("Y-m-d"); ?>',
                        exportOptions: {
                            columns: ':visible',
                             format: {
                                body: function (data, row, column, node) {
                                    // Strip HTML tags and extra whitespace
                                    return $(node).text().trim();
                                }
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: 'Export Excel',
                        className: 'd-none',
                        filename: 'tlc_2025_users_<?php echo date("Y-m-d"); ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                columnDefs: [
                    {
                        targets: 4, // TLC Join Date column (0-based index)
                        type: 'num',
                        orderDataType: 'dom-data-order'
                    }
                ],
                order: [[4, 'desc']]
            });

            $('#exportCSV').on('click', function() {
                table.button('.buttons-csv').trigger();
            });

            $('#exportExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            // Add search functionality for city and institute tables
            $('#city-search').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#city-table tbody tr').filter(function() {
                    $(this).toggle($(this).find('td:first').text().toLowerCase().indexOf(value) > -1)
                });
            });
            $('#institute-search').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#institute-table tbody tr').filter(function() {
                    $(this).toggle($(this).find('td:first').text().toLowerCase().indexOf(value) > -1)
                });
            });

            // Smooth scroll for card links
            $('.card-link-scroll').on('click', function(e) {
                var target = $(this).attr('href');
                if (target && $(target).length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: $(target).offset().top - 60 // adjust offset for header
                    }, 500);
                }
            });
        });
    </script>
</body>
</html>