<?php
session_start();
include 'config/show_errors.php';
$conn = require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;

if ($workshop_id <= 0) {
    header('Location: workshops.php');
    exit();
}

// Get workshop details
$workshop_sql = "SELECT id, name, trainer_name FROM workshops WHERE id = ?";
$workshop_stmt = mysqli_prepare($conn, $workshop_sql);
mysqli_stmt_bind_param($workshop_stmt, "i", $workshop_id);
mysqli_stmt_execute($workshop_stmt);
$workshop_result = mysqli_stmt_get_result($workshop_stmt);
$workshop = mysqli_fetch_assoc($workshop_result);
mysqli_stmt_close($workshop_stmt);

if (!$workshop) {
    header('Location: workshops.php');
    exit();
}

$workshop_name = $workshop['name'];

// Get email delivery tracking data (limit to 1000 records for performance)
$tracking_sql = "SELECT 
                    we.id,
                    we.user_id,
                    we.payment_id,
                    we.user_email,
                    we.sending_user_email,
                    we.is_sent,
                    we.sent_at,
                    we.created_at,
                    we.updated_at,
                    u.name as user_name,
                    u.mobile,
                    u.designation,
                    u.institute_name,
                    u.city
                FROM workshops_emails we
                LEFT JOIN users u ON we.user_id = u.id
                WHERE we.workshop_id = ?
                ORDER BY we.created_at DESC
                LIMIT 1000";

$tracking_stmt = mysqli_prepare($conn, $tracking_sql);
mysqli_stmt_bind_param($tracking_stmt, "i", $workshop_id);
mysqli_stmt_execute($tracking_stmt);
$tracking_result = mysqli_stmt_get_result($tracking_stmt);
$email_tracking = mysqli_fetch_all($tracking_result, MYSQLI_ASSOC);
mysqli_stmt_close($tracking_stmt);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN is_sent = 1 THEN 1 ELSE 0 END) as sent_emails,
                SUM(CASE WHEN is_sent = 0 THEN 1 ELSE 0 END) as pending_emails,
                COUNT(DISTINCT sending_user_email) as unique_sending_emails
              FROM workshops_emails 
              WHERE workshop_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "i", $workshop_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Email Delivery Tracking | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .tracking-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .tracking-card.sent {
            border-left: 4px solid #28a745;
        }
        .tracking-card.pending {
            border-left: 4px solid #ffc107;
        }
        .tracking-card.failed {
            border-left: 4px solid #dc3545;
        }
        .email-status {
            font-size: 0.9rem;
        }
        .user-details strong {
            color: #000;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card .card-body {
            padding: 1.5rem;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .sending-email-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .search-box .input-group {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .search-box .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        #searchResults {
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .bulk-actions {
            transition: all 0.3s ease;
        }
        .bulk-actions .btn {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .row-checkbox {
            cursor: pointer;
        }
        .tracking-row:hover {
            background-color: #f8f9fa;
        }
        .tracking-row.selected {
            background-color: #e3f2fd;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 text-uppercase fw-bold m-0">Email Delivery Tracking</h4>
                                <p class="text-muted mb-0">Workshop: <?php echo htmlspecialchars($workshop_name); ?></p>
                            </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Workshop
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-number"><?php echo $stats['total_emails']; ?></div>
                                <div>Total Emails</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-number"><?php echo $stats['sent_emails']; ?></div>
                                <div>Sent Emails</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-number"><?php echo $stats['pending_emails']; ?></div>
                                <div>Pending Emails</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-number"><?php echo $stats['unique_sending_emails']; ?></div>
                                <div>Email Accounts Used</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Tracking Table -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="ti ti-mail me-1"></i> Email Delivery Details
                                        <small class="text-muted ms-2">(Showing latest 1000 records)</small>
                                    </h5>
                                    <div class="d-flex" style="gap: 10px;">
                                        <div class="search-box">
                                            <div class="input-group" style="width: 300px;">
                                                <input type="text" class="form-control" id="searchInput" placeholder="Search by name, email, or mobile...">
                                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                                    <i class="ti ti-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="bulk-actions" id="bulkActions" style="display: none;">
                                            <button class="btn btn-danger" id="bulkRemoveBtn" disabled>
                                                <i class="ti ti-trash me-1"></i> Remove Selected (<span id="selectedCount">0</span>)
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="searchResults" class="mb-3" style="display: none;">
                                    <small class="text-muted">
                                        <i class="ti ti-search me-1"></i>
                                        <span id="searchCount">0</span> results found
                                    </small>
                                </div>
                                <?php if (empty($email_tracking)): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="ti ti-info-circle me-1"></i> No email records found for this workshop.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="emailTrackingTable">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                                    </th>
                                                    <th>ID</th>
                                                    <th>User Details</th>
                                                    <th>Email Status</th>
                                                    <th>Sent From</th>
                                                    <th>Sent Time</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($email_tracking as $track): ?>
                                                    <tr class="tracking-row" data-track-id="<?php echo $track['id']; ?>">
                                                        <td>
                                                            <input type="checkbox" class="form-check-input row-checkbox" value="<?php echo $track['id']; ?>" data-user-name="<?php echo htmlspecialchars($track['user_name']); ?>">
                                                        </td>
                                                        <td><?php echo $track['id']; ?></td>
                                                        <td>
                                                            <div class="user-details">
                                                                <strong><?php echo htmlspecialchars($track['user_name'] ?: 'N/A'); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($track['user_email']); ?></small><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($track['mobile'] ?: 'N/A'); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($track['is_sent'] == 1): ?>
                                                                <span class="badge bg-success">Sent</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Pending</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($track['sending_user_email']): ?>
                                                                <span class="sending-email-badge"><?php echo htmlspecialchars($track['sending_user_email']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not sent yet</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($track['sent_at']): ?>
                                                                <?php echo date('d M Y, h:i A', strtotime($track['sent_at'])); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not sent yet</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-danger remove-user-btn" data-track-id="<?php echo $track['id']; ?>" data-user-name="<?php echo htmlspecialchars($track['user_name']); ?>">
                                                                <i class="ti ti-trash"></i> Remove
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>


    <script>
        $(document).ready(function() {
            // Bulk selection functionality
            $('#selectAll').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.row-checkbox').prop('checked', isChecked);
                $('.tracking-row').toggleClass('selected', isChecked);
                updateBulkActions();
            });
            
            $('.row-checkbox').on('change', function() {
                const row = $(this).closest('tr');
                if ($(this).is(':checked')) {
                    row.addClass('selected');
                } else {
                    row.removeClass('selected');
                }
                updateBulkActions();
                updateSelectAllState();
            });
            
            function updateBulkActions() {
                const selectedCount = $('.row-checkbox:checked').length;
                const selectedCountSpan = $('#selectedCount');
                const bulkActions = $('#bulkActions');
                const bulkRemoveBtn = $('#bulkRemoveBtn');
                
                selectedCountSpan.text(selectedCount);
                
                if (selectedCount > 0) {
                    bulkActions.show();
                    bulkRemoveBtn.prop('disabled', false);
                } else {
                    bulkActions.hide();
                    bulkRemoveBtn.prop('disabled', true);
                }
            }
            
            function updateSelectAllState() {
                const totalCheckboxes = $('.row-checkbox').length;
                const checkedCheckboxes = $('.row-checkbox:checked').length;
                const selectAllCheckbox = $('#selectAll');
                
                if (checkedCheckboxes === 0) {
                    selectAllCheckbox.prop('indeterminate', false);
                    selectAllCheckbox.prop('checked', false);
                } else if (checkedCheckboxes === totalCheckboxes) {
                    selectAllCheckbox.prop('indeterminate', false);
                    selectAllCheckbox.prop('checked', true);
                } else {
                    selectAllCheckbox.prop('indeterminate', true);
                }
            }
            
            // Bulk remove functionality
            $('#bulkRemoveBtn').on('click', function() {
                const selectedIds = $('.row-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                const selectedNames = $('.row-checkbox:checked').map(function() {
                    return $(this).data('user-name');
                }).get();
                
                if (selectedIds.length === 0) {
                    alert('Please select at least one user to remove.');
                    return;
                }
                
                const confirmMessage = `Are you sure you want to remove ${selectedIds.length} user(s) from this workshop's email list?\n\nUsers to be removed:\n${selectedNames.join('\n')}\n\nThis will delete their email records and they will not receive any future emails for this workshop.`;
                
                if (!confirm(confirmMessage)) {
                    return;
                }
                
                // Disable button and show loading
                $(this).prop('disabled', true).html('<i class="ti ti-loader me-1"></i> Removing...');
                
                $.ajax({
                    url: 'bulk_remove_users_from_workshop.php',
                    method: 'POST',
                    data: { 
                        track_ids: selectedIds,
                        workshop_id: <?php echo $workshop_id; ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(`Successfully removed ${response.removed_count} user(s) from the workshop email list.`);
                            location.reload();
                        } else {
                            alert('Error removing users: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('An error occurred while removing users.');
                    },
                    complete: function() {
                        // Re-enable button
                        $('#bulkRemoveBtn').prop('disabled', false).html('<i class="ti ti-trash me-1"></i> Remove Selected (<span id="selectedCount">0</span>)');
                    }
                });
            });
            
            // Search functionality
            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                const table = $('#emailTrackingTable');
                const rows = table.find('tbody tr');
                let visibleCount = 0;
                
                rows.each(function() {
                    const row = $(this);
                    const userDetails = row.find('td:eq(2)').text().toLowerCase();
                    const email = row.find('td:eq(2) small:first').text().toLowerCase();
                    const mobile = row.find('td:eq(2) small:last').text().toLowerCase();
                    
                    if (userDetails.includes(searchTerm) || email.includes(searchTerm) || mobile.includes(searchTerm)) {
                        row.show();
                        visibleCount++;
                    } else {
                        row.hide();
                    }
                });
                
                // Update search results counter
                if (searchTerm.length > 0) {
                    $('#searchResults').show();
                    $('#searchCount').text(visibleCount);
                } else {
                    $('#searchResults').hide();
                }
            });
            
            // Clear search
            $('#clearSearch').on('click', function() {
                $('#searchInput').val('');
                $('#emailTrackingTable tbody tr').show();
                $('#searchResults').hide();
            });
            
            $('.remove-user-btn').on('click', function() {
                const trackId = $(this).data('track-id');
                const userName = $(this).data('user-name');
                
                if (!confirm(`Are you sure you want to remove "${userName}" from this workshop's email list?\n\nThis will delete their email record and they will not receive any future emails for this workshop.`)) {
                    return;
                }
                
                $.ajax({
                    url: 'remove_user_from_workshop.php',
                    method: 'POST',
                    data: { 
                        track_id: trackId,
                        workshop_id: <?php echo $workshop_id; ?>
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('User removed successfully!');
                            location.reload();
                        } else {
                            alert('Error removing user: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('An error occurred while removing the user.');
                    }
                });
            });
        });
    </script>
    <?php include 'includes/theme_settings.php'; ?>
    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script src="https://unpkg.com/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
