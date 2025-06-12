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

// Fetch all users
$users_sql = "SELECT id, first_name, last_name FROM users ORDER BY first_name ASC";
$users_result = mysqli_query($conn, $users_sql);

// Fetch all trainers
$trainers_sql = "SELECT id, first_name, last_name FROM trainers ORDER BY first_name ASC";
$trainers_result = mysqli_query($conn, $trainers_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $trainer_id = mysqli_real_escape_string($conn, $_POST['trainer_id']);
    $time_slot_id = mysqli_real_escape_string($conn, $_POST['time_slot_id']);
    $booking_notes = mysqli_real_escape_string($conn, $_POST['booking_notes']);
    $amount = 500.00; // Default amount, you can make this dynamic if needed

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Insert booking
        $booking_sql = "INSERT INTO bookings (user_id, time_slot_id, status, booking_notes, created_at, updated_at) 
                       VALUES (?, ?, 'confirmed', ?, NOW(), NOW())";
        $booking_stmt = mysqli_prepare($conn, $booking_sql);
        mysqli_stmt_bind_param($booking_stmt, "iis", $user_id, $time_slot_id, $booking_notes);
        mysqli_stmt_execute($booking_stmt);
        $booking_id = mysqli_insert_id($conn);

        // Generate unique transaction ID
        $transaction_id = 'Admin_' . uniqid();

        // Insert payment
        $payment_sql = "INSERT INTO payments (booking_id, amount, payment_method, transaction_id, status, payment_date, created_at, updated_at) 
                       VALUES (?, ?, 'Admin Scheduled', ?, 'completed', NOW(), NOW(), NOW())";
        $payment_stmt = mysqli_prepare($conn, $payment_sql);
        mysqli_stmt_bind_param($payment_stmt, "ids", $booking_id, $amount, $transaction_id);
        mysqli_stmt_execute($payment_stmt);

        // Update time slot status
        $update_slot_sql = "UPDATE time_slots SET status = 'booked' WHERE id = ?";
        $update_slot_stmt = mysqli_prepare($conn, $update_slot_sql);
        mysqli_stmt_bind_param($update_slot_stmt, "i", $time_slot_id);
        mysqli_stmt_execute($update_slot_stmt);

        // Commit transaction
        mysqli_commit($conn);
        $_SESSION['success'] = "Booking scheduled successfully. Transaction ID: " . $transaction_id;
        header("Location: admin_booking.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error creating booking: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Schedule Booking | IPN Academy Admin</title>
    <?php include 'includes/head.php'; ?>
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        .time-slot-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .time-slot-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .time-slot-card.selected {
            border: 2px solid #3498db;
            background-color: #f8f9fa;
        }
        .trainer-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .trainer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }
        .trainer-card.selected {
            border: 2px solid #3498db;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="bookings.php">Bookings</a></li>
                                    <li class="breadcrumb-item active">Schedule New Booking</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Schedule New Booking</h4>
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

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" id="booking-form">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="user_id" class="form-label">Select User</label>
                                                <select data-choices data-choices-sorting-false class="form-select" id="user_id" name="user_id" required>
                                                    <option value="">Choose a user...</option>
                                                    <?php while ($user = mysqli_fetch_assoc($users_result)) : ?>
                                                        <option value="<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="trainer_id" class="form-label">Select Trainer</label>
                                                <select data-choices data-choices-sorting-false class="form-select" id="trainer_id" name="trainer_id" required>
                                                    <option value="">Choose a trainer...</option>
                                                    <?php while ($trainer = mysqli_fetch_assoc($trainers_result)) : ?>
                                                        <option value="<?php echo $trainer['id']; ?>">
                                                            <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Available Time Slots</label>
                                        <div id="time-slots-container" class="row g-3">
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    Please select a trainer to view available time slots.
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="time_slot_id" id="time_slot_id" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="booking_notes" class="form-label">Booking Notes</label>
                                        <textarea class="form-control" id="booking_notes" name="booking_notes" rows="3" 
                                                  placeholder="Enter any special notes or requirements"></textarea>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary" id="schedule-btn" disabled>
                                            Schedule Booking
                                        </button>
                                    </div>
                                </form>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        $(document).ready(function() {
            // Handle trainer selection
            $('#trainer_id').change(function() {
                const trainerId = $(this).val();
                if (trainerId) {
                    fetchAvailableTimeSlots(trainerId);
                } else {
                    $('#time-slots-container').html('<div class="col-12"><div class="alert alert-info">Please select a trainer to view available time slots.</div></div>');
                    $('#schedule-btn').prop('disabled', true);
                }
            });

            // Function to fetch available time slots
            function fetchAvailableTimeSlots(trainerId) {
                $.ajax({
                    url: 'get_available_slots.php',
                    method: 'POST',
                    data: { trainer_id: trainerId },
                    success: function(response) {
                        const slots = JSON.parse(response);
                        displayTimeSlots(slots);
                    },
                    error: function() {
                        $('#time-slots-container').html('<div class="col-12"><div class="alert alert-danger">Error fetching time slots. Please try again.</div></div>');
                    }
                });
            }

            // Function to display time slots
            function displayTimeSlots(slots) {
                if (slots.length === 0) {
                    $('#time-slots-container').html('<div class="col-12"><div class="alert alert-warning">No available time slots found.</div></div>');
                    return;
                }

                let html = '';
                slots.forEach(slot => {
                    html += `
                        <div class="col-md-4">
                            <div class="card time-slot-card" data-slot-id="${slot.id}">
                                <div class="card-body">
                                    <h5 class="card-title">${slot.date}</h5>
                                    <p class="card-text">
                                        Time: ${slot.start_time} - ${slot.end_time}<br>
                                        Duration: ${slot.duration_minutes} minutes<br>
                                        Price: $${slot.price}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                });

                $('#time-slots-container').html(html);

                // Handle time slot selection
                $('.time-slot-card').click(function() {
                    $('.time-slot-card').removeClass('selected');
                    $(this).addClass('selected');
                    $('#time_slot_id').val($(this).data('slot-id'));
                    validateForm();
                });
            }

            // Form validation
            function validateForm() {
                const isValid = $('#user_id').val() && 
                              $('#trainer_id').val() && 
                              $('#time_slot_id').val();
                $('#schedule-btn').prop('disabled', !isValid);
            }

            // Handle user selection
            $('#user_id').change(validateForm);
        });
    </script>
</body>
</html> 