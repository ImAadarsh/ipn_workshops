<?php
include 'config/show_errors.php';
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once 'config/config.php';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id <= 0) {
    header("Location: bookings.php");
    exit();
}

// Get booking details with related information
$sql = "SELECT 
    b.*,
    u.first_name as user_first_name,
    u.last_name as user_last_name,
    u.email as user_email,
    t.first_name as trainer_first_name,
    t.last_name as trainer_last_name,
    t.email as trainer_email,
    t.id as trainer_id,
    ts.start_time,
    ts.end_time,
    ts.duration_minutes,
    p.amount,
    p.status as payment_status,
    p.payment_method,
    p.transaction_id,
    p.created_at as payment_date,
    ta.date as booking_date
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN time_slots ts ON b.time_slot_id = ts.id
LEFT JOIN trainer_availabilities ta ON ts.trainer_availability_id = ta.id
LEFT JOIN trainers t ON ta.trainer_id = t.id
LEFT JOIN payments p ON b.id = p.booking_id
WHERE b.id = ?";

// Check if connection is valid
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Prepare the statement
$stmt = mysqli_prepare($conn, $sql);

// Check if prepare was successful
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

// Bind parameters
if (!mysqli_stmt_bind_param($stmt, "i", $booking_id)) {
    die("Binding parameters failed: " . mysqli_stmt_error($stmt));
}

// Execute the statement
if (!mysqli_stmt_execute($stmt)) {
    die("Execute failed: " . mysqli_stmt_error($stmt));
}

// Get the result
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header("Location: bookings.php");
    exit();
}

// Get user type for permission checks
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];

// Check if user has permission to view this booking
if ($user_type !== 'admin' && $booking['user_id'] !== $user_id && $booking['trainer_id'] !== $user_id) {
    header("Location: bookings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>View Booking | IPN Academy</title>
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
                                    <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
                                    <li class="breadcrumb-item active">Booking Details</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Booking Details</h4>
                        </div>
                            <div class="mt-3 mt-sm-0">
                                <a href="bookings.php" class="btn btn-outline-primary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to Bookings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-xxl-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">Booking Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                                            <p class="mb-1"><strong>Status:</strong> 
                                                <span class="badge <?php 
                                                    switch($booking['status']) {
                                                        case 'completed':
                                                            echo 'bg-success';
                                                            break;
                                                        case 'pending':
                                                            echo 'bg-warning';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-danger';
                                                            break;
                                                        default:
                                                            echo 'bg-secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </p>
                                            <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($booking['booking_date'])); ?></p>
                                            <p class="mb-1"><strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['start_time'])); ?> - <?php echo date('h:i A', strtotime($booking['end_time'])); ?></p>
                                            <p class="mb-1"><strong>Duration:</strong> <?php echo $booking['duration_minutes']; ?> minutes</p>
                                            
                                            <!-- Meeting Link Section -->
                                            <div class="mt-3">
                                                <strong>Meeting Link:</strong>
                                                <?php if ($booking['meeting_link']): ?>
                                                    <div class="d-flex align-items-center mt-1">
                                                        <a href="<?php echo $booking['meeting_link']; ?>" target="_blank" class="btn btn-sm btn-primary me-2">
                                                            <i class="ti ti-video me-1"></i> Join Meeting
                                                        </a>
                                                        <?php if ($user_type === 'admin' || $user_id === $booking['trainer_id']): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editMeetingLink()">
                                                                <i class="ti ti-pencil"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if ($user_type === 'admin' || $user_id === $booking['trainer_id']): ?>
                                                        <button class="btn btn-sm btn-primary mt-1" onclick="editMeetingLink()">
                                                            <i class="ti ti-plus me-1"></i> Add Meeting Link
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not yet provided</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Booking Notes Section -->
                                            <div class="mt-3">
                                                <strong>Booking Notes:</strong>
                                                <div class="mt-1">
                                                    <?php if ($booking['booking_notes']): ?>
                                                        <p class="mb-1"><?php echo nl2br($booking['booking_notes']); ?></p>
                                                        <?php if ($user_type === 'admin' || $user_id === $booking['trainer_id']): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editNotes()">
                                                                <i class="ti ti-pencil me-1"></i> Edit Notes
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($user_type === 'admin' || $user_id === $booking['trainer_id']): ?>
                                                            <button class="btn btn-sm btn-primary" onclick="editNotes()">
                                                                <i class="ti ti-plus me-1"></i> Add Notes
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">No notes available</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">Payment Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Amount:</strong> ₹<?php echo number_format($booking['amount'], 2); ?></p>
                                            <p class="mb-1"><strong>Payment Status:</strong> 
                                                <span class="badge <?php 
                                                    switch($booking['payment_status']) {
                                                        case 'completed':
                                                            echo 'bg-success';
                                                            break;
                                                        case 'pending':
                                                            echo 'bg-warning';
                                                            break;
                                                        case 'refunded':
                                                            echo 'bg-danger';
                                                            break;
                                                        default:
                                                            echo 'bg-secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($booking['payment_status'] ?? 'Not Paid'); ?>
                                                </span>
                                            </p>
                                            <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($booking['payment_method'] ?? 'N/A'); ?></p>
                                            <p class="mb-1"><strong>Transaction ID:</strong> <?php echo $booking['transaction_id'] ?? 'N/A'; ?></p>
                                            <p class="mb-1"><strong>Payment Date:</strong> <?php echo $booking['payment_date'] ? date('d M Y, h:i A', strtotime($booking['payment_date'])) : 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">User Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Name:</strong> <?php echo $booking['user_first_name'] . ' ' . $booking['user_last_name']; ?></p>
                                            <p class="mb-1"><strong>Email:</strong> <?php echo $booking['user_email']; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="text-muted fs-13 text-uppercase">Trainer Information</h5>
                                        <div class="mt-3">
                                            <p class="mb-1"><strong>Name:</strong> <?php echo $booking['trainer_first_name'] . ' ' . $booking['trainer_last_name']; ?></p>
                                            <p class="mb-1"><strong>Email:</strong> <?php echo $booking['trainer_email']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-muted fs-13 text-uppercase">Actions</h5>
                                <div class="mt-3">
                                    <?php if ($user_type === 'admin' || $user_id === $booking['trainer_id']): ?>
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <button class="btn btn-success w-100 mb-2" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'confirmed')">
                                                <i class="ti ti-check me-1"></i> Confirm Booking Payment
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($booking['status'] === 'confirmed'): ?>
                                            <button class="btn btn-primary w-100 mb-2" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'completed')">
                                                <i class="ti ti-check me-1"></i> Mark as Completed
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                            <button class="btn btn-danger w-100 mb-2" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'cancelled')">
                                                <i class="ti ti-x me-1"></i> Cancel Booking
                                            </button>
                                            
                                            <!-- Add Reschedule Button -->
                                            <button class="btn btn-warning w-100 mb-2" onclick="showRescheduleModal()">
                                                <i class="ti ti-calendar-time me-1"></i> Reschedule Booking
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($user_type === 'admin'): ?>
                                        <button class="btn btn-outline-danger w-100" onclick="deleteBooking(<?php echo $booking['id']; ?>)">
                                            <i class="ti ti-trash me-1"></i> Delete Booking
                                        </button>
                                    <?php endif; ?>

                                    <!-- Show Reschedule Request Status if exists -->
                                    <?php
                                    $reschedule_sql = "SELECT * FROM reschedule_requests WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1";
                                    $reschedule_stmt = mysqli_prepare($conn, $reschedule_sql);
                                    mysqli_stmt_bind_param($reschedule_stmt, "i", $booking_id);
                                    mysqli_stmt_execute($reschedule_stmt);
                                    $reschedule_request = mysqli_stmt_get_result($reschedule_stmt)->fetch_assoc();
                                    
                                    if ($reschedule_request): ?>
                                        <div class="alert alert-info mt-3 mb-0">
                                            <h6 class="mb-1">Reschedule Request</h6>
                                            <p class="mb-1">Status: <strong><?php echo ucfirst($reschedule_request['status']); ?></strong></p>
                                            <p class="mb-1">Requested Date: <?php echo date('d M Y', strtotime($reschedule_request['requested_date'])); ?></p>
                                            <p class="mb-1">Requested Time: <?php echo date('h:i A', strtotime($reschedule_request['requested_start_time'])) . ' - ' . date('h:i A', strtotime($reschedule_request['requested_end_time'])); ?></p>
                                            <p class="mb-0">Reason: <?php echo $reschedule_request['reason']; ?></p>
                                            <?php if ($reschedule_request['status'] === 'pending'): ?>
                                                <?php if (($user_type === 'admin' || $user_id === $booking['trainer_id']) || $reschedule_request['requested_by'] === 'user'): ?>
                                                    <div class="mt-2">
                                                        <button class="btn btn-sm btn-success" onclick="approveReschedule(<?php echo $reschedule_request['id']; ?>)">
                                                            <i class="ti ti-check me-1"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectReschedule(<?php echo $reschedule_request['id']; ?>)">
                                                            <i class="ti ti-x me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php elseif ($user_id === $booking['user_id'] && $reschedule_request['requested_by'] === 'trainer'): ?>
                                                    <div class="mt-2">
                                                        <button class="btn btn-sm btn-success" onclick="approveReschedule(<?php echo $reschedule_request['id']; ?>)">
                                                            <i class="ti ti-check me-1"></i> Accept New Time
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="rejectReschedule(<?php echo $reschedule_request['id']; ?>)">
                                                            <i class="ti ti-x me-1"></i> Decline
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <h5 class="text-muted fs-13 text-uppercase">Timeline</h5>
                                <div class="mt-3">
                                    <div class="timeline-alt pb-0">
                                        <div class="timeline-item">
                                            <i class="ti ti-calendar bg-info-subtle text-info timeline-icon"></i>
                                            <div class="timeline-item-info">
                                                <a href="javascript:void(0);" class="text-body fw-semibold mb-1 d-block">Booking Created</a>
                                                <p class="mb-0 pb-2">
                                                    <small class="text-muted"><?php echo date('j M Y, g:i a', strtotime($booking['created_at'])); ?></small>
                                                </p>
                                            </div>
                                        </div>

                                        <?php if ($booking['payment_date']): ?>
                                        <div class="timeline-item">
                                            <i class="ti ti-credit-card bg-success-subtle text-success timeline-icon"></i>
                                            <div class="timeline-item-info">
                                                <a href="javascript:void(0);" class="text-body fw-semibold mb-1 d-block">Payment <?php echo ucfirst($booking['payment_status']); ?></a>
                                                <p class="mb-0 pb-2">
                                                    <small class="text-muted"><?php echo date('j M Y, g:i a', strtotime($booking['payment_date'])); ?></small>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->
    </div>
    <!-- END wrapper -->

    <!-- Theme Settings -->
    <?php include 'includes/theme_settings.php'; ?>

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>

    <!-- App js -->
    <script src="assets/js/app.min.js"></script>

    <!-- Meeting Link Modal -->
    <div class="modal fade" id="meetingLinkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $booking['meeting_link'] ? 'Edit' : 'Add'; ?> Meeting Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="meetingLinkForm">
                        <div class="mb-3">
                            <label for="meetingLink" class="form-label">Meeting Link</label>
                            <input type="url" class="form-control" id="meetingLink" value="<?php echo $booking['meeting_link']; ?>" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveMeetingLink()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $booking['booking_notes'] ? 'Edit' : 'Add'; ?> Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="notesForm">
                        <div class="mb-3">
                            <label for="bookingNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="bookingNotes" rows="4"><?php echo $booking['booking_notes']; ?></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveNotes()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Reschedule Modal -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reschedule Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rescheduleForm">
                        <div class="mb-3">
                            <label for="rescheduleDate" class="form-label">New Date</label>
                            <input type="date" class="form-control" id="rescheduleDate" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="rescheduleStartTime" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="rescheduleStartTime" required>
                        </div>
                        <div class="mb-3">
                            <label for="rescheduleEndTime" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="rescheduleEndTime" required>
                        </div>
                        <div class="mb-3">
                            <label for="rescheduleReason" class="form-label">Reason for Rescheduling</label>
                            <textarea class="form-control" id="rescheduleReason" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitReschedule()">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateBookingStatus(bookingId, status) {
            if (!confirm('Are you sure you want to update the booking status?')) {
                return;
            }

            fetch('controllers/update_booking_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update booking status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the booking status');
            });
        }

        function deleteBooking(bookingId) {
            if (!confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
                return;
            }

            fetch('controllers/delete_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=${bookingId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'bookings.php';
                } else {
                    alert(data.message || 'Failed to delete booking');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the booking');
            });
        }

        function editMeetingLink() {
            var modal = new bootstrap.Modal(document.getElementById('meetingLinkModal'));
            modal.show();
        }

        function saveMeetingLink() {
            const meetingLink = document.getElementById('meetingLink').value;
            
            fetch('controllers/update_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=<?php echo $booking['id']; ?>&meeting_link=${encodeURIComponent(meetingLink)}&action=update_meeting_link`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update meeting link');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the meeting link');
            });
        }

        function editNotes() {
            var modal = new bootstrap.Modal(document.getElementById('notesModal'));
            modal.show();
        }

        function saveNotes() {
            const notes = document.getElementById('bookingNotes').value;
            
            fetch('controllers/update_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=<?php echo $booking['id']; ?>&booking_notes=${encodeURIComponent(notes)}&action=update_notes`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update notes');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the notes');
            });
        }

        function showRescheduleModal() {
            var modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
            modal.show();
        }

        function submitReschedule() {
            const date = document.getElementById('rescheduleDate').value;
            const startTime = document.getElementById('rescheduleStartTime').value;
            const endTime = document.getElementById('rescheduleEndTime').value;
            const reason = document.getElementById('rescheduleReason').value;

            if (!date || !startTime || !endTime || !reason) {
                alert('Please fill in all fields');
                return;
            }

            fetch('controllers/reschedule_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `booking_id=<?php echo $booking['id']; ?>&date=${date}&start_time=${startTime}&end_time=${endTime}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to submit reschedule request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the reschedule request');
            });
        }

        function approveReschedule(requestId) {
            if (!confirm('Are you sure you want to approve this reschedule request?')) {
                return;
            }

            fetch('controllers/handle_reschedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `request_id=${requestId}&action=approve`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to approve reschedule request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while approving the reschedule request');
            });
        }

        function rejectReschedule(requestId) {
            if (!confirm('Are you sure you want to reject this reschedule request?')) {
                return;
            }

            fetch('controllers/handle_reschedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `request_id=${requestId}&action=reject`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to reject reschedule request');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rejecting the reschedule request');
            });
        }
    </script>
</body>
</html> 