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

// Fetch stats for TLC Feedback Day 02
$stats_sql = "SELECT 
    COUNT(*) as total_feedbacks, 
    AVG(overall_rating) as avg_overall_rating,
    AVG(keynote_rating) as avg_keynote_rating,
    AVG(mc3_rating) as avg_mc3_rating,
    AVG(mc4_rating) as avg_mc4_rating,
    AVG(mc5_rating) as avg_mc5_rating,
    AVG(panel_rating) as avg_panel_rating
    FROM tlc_25_day_2";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch all feedback entries
$feedback_sql = "SELECT * FROM tlc_25_day_2 ORDER BY submitted_at DESC";
$feedback_result = mysqli_query($conn, $feedback_sql);
$feedbacks = [];
if ($feedback_result) {
    while($row = mysqli_fetch_assoc($feedback_result)) {
        $feedbacks[] = $row;
    }
}

// Function to get initials from a full name
function getInitials($name) {
    $words = explode(' ', trim($name ?? ''));
    $initials = '';
    if (!empty($words[0])) {
        $initials .= mb_substr($words[0], 0, 1, 'UTF-8');
    }
    if (count($words) > 1) {
        $initials .= mb_substr(end($words), 0, 1, 'UTF-8');
    }
    return strtoupper($initials);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>TLC Feedback Day 02 | IPN Academy</title>
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
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>
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
                                    <li class="breadcrumb-item active">TLC Feedback Day 02</li>
                                </ol>
                            </div>
                            <?php endif; ?>
                            <h4 class="page-title">TLC Feedback Day 02</h4>
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
                                <h5 class="text-muted fw-normal mt-0" title="Total Feedbacks">Total Feedbacks</h5>
                                <h3 class="mt-3 mb-3"><?php echo $stats['total_feedbacks'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-star widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Avg Overall Rating">Avg Overall Rating</h5>
                                <h3 class="mt-3 mb-3"><?php echo number_format($stats['avg_overall_rating'], 2) ?? '-'; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-microphone widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Avg Keynote Rating">Avg Keynote Rating</h5>
                                <h3 class="mt-3 mb-3"><?php echo number_format($stats['avg_keynote_rating'], 2) ?? '-'; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="card widget-flat">
                            <div class="card-body">
                                <div class="float-end">
                                    <i class="ti ti-users widget-icon"></i>
                                </div>
                                <h5 class="text-muted fw-normal mt-0" title="Avg Panel Rating">Avg Panel Rating</h5>
                                <h3 class="mt-3 mb-3"><?php echo number_format($stats['avg_panel_rating'], 2) ?? '-'; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Feedback Table -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h4 class="header-title mb-0">TLC Feedback Day 02 List</h4>
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
                                    <table id="tlc-feedback-table" class="table table-centered table-striped dt-responsive nowrap w-100">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Mobile</th>
                                                <th>Overall Rating</th>
                                                <th>Timing</th>
                                                <th>Keynote Feedback</th>
                                                <th>Keynote Rating</th>
                                                <th>MC3 Feedback</th>
                                                <th>MC3 Rating</th>
                                                <th>MC4 Feedback</th>
                                                <th>MC4 Rating</th>
                                                <th>MC5 Feedback</th>
                                                <th>MC5 Rating</th>
                                                <th>Panel Feedback</th>
                                                <th>Panel Rating</th>
                                                <th>Submitted At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($feedbacks as $fb): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($fb['name']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['email']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mobile']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['overall_rating']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['timing']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['keynote_feedback']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['keynote_rating']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mc3_feedback']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mc3_rating']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mc4_feedback']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mc4_rating']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mc5_feedback']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['mc5_rating']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['panel_feedback']); ?></td>
                                                <td><?php echo htmlspecialchars($fb['panel_rating']); ?></td>
                                                <td><?php 
                                                    if ($fb['submitted_at']) {
                                                        $dt = new DateTime($fb['submitted_at'], new DateTimeZone('UTC'));
                                                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                        echo $dt->format('d M Y, h:i A');
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?></td>
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
            <?php if ($is_logged_in): ?>
            <?php include 'includes/footer.php'; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($is_logged_in): ?>
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
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/app.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#tlc-feedback-table').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: 'Export CSV',
                        className: 'd-none',
                        filename: 'tlc_feedback_day_02_<?php echo date("Y-m-d"); ?>',
                        exportOptions: {
                            columns: ':visible',
                            format: {
                                body: function (data, row, column, node) {
                                    return $(node).text().trim();
                                }
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: 'Export Excel',
                        className: 'd-none',
                        filename: 'tlc_feedback_day_02_<?php echo date("Y-m-d"); ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                order: [[15, 'desc']]
            });
            $('#exportCSV').on('click', function() {
                table.button('.buttons-csv').trigger();
            });
            $('#exportExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });
        });
    </script>
</body>
</html> 