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

// Check if review ID is provided
if (!isset($_GET['id'])) {
    header("Location: reviews.php");
    exit();
}

$review_id = (int)$_GET['id'];

// Fetch review details
$sql = "SELECT tr.*, b.id as booking_id 
        FROM trainer_reviews tr 
        LEFT JOIN bookings b ON tr.booking_id = b.id 
        WHERE tr.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $review_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$review = mysqli_fetch_assoc($result);

if (!$review) {
    header("Location: reviews.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = mysqli_real_escape_string($conn, $_POST['rating']);
    $review_text = mysqli_real_escape_string($conn, $_POST['review']);

    $sql = "UPDATE trainer_reviews SET 
            rating = ?, 
            review = ?, 
            updated_at = NOW() 
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isi", $rating, $review_text, $review_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Review updated successfully";
        header("Location: reviews.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating review: " . mysqli_error($conn);
    }
}

// Fetch user and trainer details
$sql = "SELECT u.first_name as user_first_name, u.last_name as user_last_name, 
        t.first_name as trainer_first_name, t.last_name as trainer_last_name 
        FROM trainer_reviews tr 
        LEFT JOIN users u ON tr.user_id = u.id 
        LEFT JOIN trainers t ON tr.trainer_id = t.id 
        WHERE tr.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $review_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$names = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Edit Review | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating > input {
            display: none;
        }
        .rating > label {
            position: relative;
            width: 1.1em;
            font-size: 2em;
            color: #ffc107;
            cursor: pointer;
        }
        .rating > label::before {
            content: "☆";
            position: absolute;
            opacity: 1;
        }
        .rating > label:hover:before,
        .rating > label:hover ~ label:before {
            content: "★";
        }
        .rating > input:checked ~ label:before {
            content: "★";
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
                            <h4 class="page-title">Edit Review</h4>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">User</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($names['user_first_name'] . ' ' . $names['user_last_name']); ?>" disabled>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Trainer</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($names['trainer_first_name'] . ' ' . $names['trainer_last_name']); ?>" disabled>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label d-block">Rating</label>
                                        <div class="rating">
                                            <?php for ($i = 5; $i >= 1; $i--) : ?>
                                                <input type="radio" id="star<?php echo $i; ?>" 
                                                       name="rating" value="<?php echo $i; ?>" 
                                                       <?php echo ($i == $review['rating']) ? 'checked' : ''; ?> 
                                                       required />
                                                <label for="star<?php echo $i; ?>"></label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div class="mb-3 mt-5">
                                        <label for="review" class="form-label">Review</label>
                                        <textarea class="form-control" id="review" name="review" rows="4" required><?php 
                                            echo htmlspecialchars($review['review']); 
                                        ?></textarea>
                                    </div>

                                    <div class="text-end">
                                        <a href="reviews.php" class="btn btn-secondary me-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Update Review</button>
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

    <!-- Vendor js -->
    <script src="assets/js/vendor.min.js"></script>
    <!-- App js -->
    <script src="assets/js/app.js"></script>
</body>
</html> 