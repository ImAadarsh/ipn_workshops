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

// --- INITIALIZATION ---
$workshop_id = isset($_REQUEST['workshop_id']) ? intval($_REQUEST['workshop_id']) : 0;

// Use session for feedback to survive redirects
$feedback_message = $_SESSION['feedback_message'] ?? '';
$feedback_error = $_SESSION['feedback_error'] ?? '';
unset($_SESSION['feedback_message'], $_SESSION['feedback_error']);


// --- WORKSHOP VALIDATION ---
$workshop = null;
if ($workshop_id > 0) {
    $sql = "SELECT name, price, cpd FROM workshops WHERE id = $workshop_id AND is_deleted = 0";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $workshop = mysqli_fetch_assoc($result);
    } else {
        header("Location: dashboard.php"); // Workshop not found
        exit();
    }
} else {
    header("Location: dashboard.php"); // No workshop ID
    exit();
}

// --- HELPER FUNCTION FOR ENROLLMENT (Manual Selection)---
function enrollUser($conn, $user_id, $workshop_id, $amount, $cpd_hrs, &$errors) {
    $user_id = intval($user_id);
    // Check if user is already enrolled
    $check_sql = "SELECT id FROM payments WHERE user_id = $user_id AND workshop_id = $workshop_id AND payment_status = 1";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        return false; // Already enrolled
    }

    $user_school_sql = "SELECT school_id FROM users WHERE id = $user_id";
    $user_school_result = mysqli_query($conn, $user_school_sql);
    $user_school_id = mysqli_fetch_assoc($user_school_result)['school_id'] ?? 'NULL';

    $payment_id = 'B2C-ENRL-' . uniqid();
    $order_id = 'B2C-' . uniqid();
    $insert_sql = "INSERT INTO payments (user_id, workshop_id, amount, payment_status, payment_id, school_id, is_attended, mail_send, order_id, created_at, updated_at, cpd) 
                   VALUES ($user_id, $workshop_id, $amount, 1, '$payment_id', $user_school_id, 0, 0, '$order_id', NOW(), NOW(), '$cpd_hrs')";
    
    if (mysqli_query($conn, $insert_sql)) {
        return true; // Enrolled successfully
    } else {
        $errors[] = "Failed to enroll user ID $user_id: " . mysqli_error($conn);
        return false;
    }
}

// --- FORM SUBMISSION HANDLING ---

// Handle Manual Enrollment Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_users'])) {
    $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    if (!empty($user_ids)) {
        $enrolled_count = 0;
        $errors = [];
        foreach ($user_ids as $user_id) {
            if (enrollUser($conn, $user_id, $workshop_id, $workshop['price'], $workshop['cpd'], $errors)) {
                $enrolled_count++;
            }
        }
        if ($enrolled_count > 0) {
            $_SESSION['feedback_message'] = "Successfully enrolled $enrolled_count new users.";
        }
        if (!empty($errors)) {
            $_SESSION['feedback_error'] = "Some errors occurred:<br>" . implode('<br>', $errors);
        }
        if ($enrolled_count === 0 && empty($errors)) {
            $_SESSION['feedback_message'] = "No new users were enrolled (they may have been enrolled previously).";
        }
    } else {
        $_SESSION['feedback_error'] = "No users were selected for enrollment.";
    }
    // Redirect to prevent form resubmission
    header("Location: b2c_bulk_enrollment.php?workshop_id=$workshop_id");
    exit();
}

// --- DATA FETCHING FOR DISPLAY ---
$users = [];
$is_search_active = false;
$base_sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation, s.name as school_name,
             (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.workshop_id = $workshop_id AND p.payment_status = 1) as enrolled
             FROM users u
             LEFT JOIN schools s ON u.school_id = s.id";
$where_clauses = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['search'])) {
    $is_search_active = true;
    if (!empty($_GET['name'])) $where_clauses[] = "u.name LIKE '%" . mysqli_real_escape_string($conn, $_GET['name']) . "%'";
    if (!empty($_GET['email'])) $where_clauses[] = "u.email LIKE '%" . mysqli_real_escape_string($conn, $_GET['email']) . "%'";
    if (!empty($_GET['mobile'])) $where_clauses[] = "u.mobile LIKE '%" . mysqli_real_escape_string($conn, $_GET['mobile']) . "%'";
}

if ($is_search_active) {
    $sql = $base_sql;
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    $sql .= " ORDER BY u.name";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>B2C Bulk Enrollment | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidenav.php'; ?>
        <?php include 'includes/topbar.php'; ?>

        <div class="page-content">
            <div class="page-container">
                <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column">
                    <div class="flex-grow-1">
                        <h4 class="fs-18 text-uppercase fw-bold m-0">B2C Bulk Enrollment for "<?php echo htmlspecialchars($workshop['name']); ?>"</h4>
                    </div>
                    <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-primary mt-3 mt-sm-0"><i class="ti ti-arrow-left me-1"></i> Back to Workshop</a>
                </div>

                <?php if ($feedback_message): ?><div class="alert alert-success mt-3"><?php echo $feedback_message; ?></div><?php endif; ?>
                <?php if ($feedback_error): ?><div class="alert alert-danger mt-3"><?php echo $feedback_error; ?></div><?php endif; ?>

                <!-- Search Form -->
                <div class="card mt-4">
                    <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-search me-1"></i> Search for Users</h5></div>
                    <div class="card-body">
                        <p class="text-muted">Search for existing users by name, email, or mobile to enroll them.</p>
                        <form method="GET" action="b2c_bulk_enrollment.php">
                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                            <input type="hidden" name="search" value="1">
                            <div class="row g-3">
                                <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="By Name" value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>"></div>
                                <div class="col-md-4"><input type="email" name="email" class="form-control" placeholder="By Email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"></div>
                                <div class="col-md-4"><input type="text" name="mobile" class="form-control" placeholder="By Mobile" value="<?php echo isset($_GET['mobile']) ? htmlspecialchars($_GET['mobile']) : ''; ?>"></div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Search Users</button>
                                    <a href="?workshop_id=<?php echo $workshop_id; ?>" class="btn btn-secondary">Clear Search</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Results and Enrollment Form -->
                <?php if ($is_search_active): ?>
                <div class="card mt-4">
                    <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-users me-1"></i> Search Results</h5></div>
                    <div class="card-body">
                        <form method="POST" action="b2c_bulk_enrollment.php">
                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                            <input type="hidden" name="enroll_users" value="1">
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="selectAllUsers"></th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Mobile</th>
                                            <th>School</th>
                                            <th>Enrolled?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="6" class="text-center p-4">
                                            <p class="mb-0">No users found matching your search criteria.</p>
                                            <small class="text-muted">Try a different name, email, or mobile number.</small>
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><input type="checkbox" class="user-checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" <?php echo $user['enrolled'] ? 'disabled' : ''; ?>></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                            <td><?php echo htmlspecialchars($user['school_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $user['enrolled'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-success" <?php if (empty($users) || !in_array(0, array_column($users, 'enrolled'))) echo 'disabled'; ?>>
                                <i class="ti ti-plus me-1"></i> Enroll Selected Users
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center p-5 bg-light rounded mt-4">
                    <i class="ti ti-search fs-1 text-primary"></i>
                    <h5 class="mt-2">Ready to Enroll Users?</h5>
                    <p class="text-muted">Use the search form above to find users and add them to this workshop.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include 'includes/footer.php'; ?>
    </div>
    <?php include 'includes/theme_settings.php'; ?>
    <script src="assets/js/vendor.min.js"></script>
    <script src="assets/js/app.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAllUsers');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.user-checkbox:enabled').forEach(c => { c.checked = this.checked; });
            });
        }
    });
    </script>
</body>
</html> 