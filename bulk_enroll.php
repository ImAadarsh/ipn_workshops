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
$school_id = isset($_REQUEST['school_id']) ? intval($_REQUEST['school_id']) : 0;

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

    $payment_id = 'B2B-ENRL-' . uniqid();
    $order_id = 'B2B-' . uniqid();
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

// Handle School-Specific CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_school_csv'])) {
    $csv_workshop_id = intval($_POST['workshop_id']);
    $csv_school_id = intval($_POST['school_id']);
    $file = $_FILES['csv_file']['tmp_name'];

    if ($csv_school_id > 0 && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($file)) {
        $stats = ['new' => 0, 'updated' => 0, 'errors' => 0, 'processed' => 0];
        $errors = [];
        $row_num = 1;

        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header row
            while (($csv = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_num++;
                // CSV format: Name, Email, Mobile, Designation, Institute, City
                $name = mysqli_real_escape_string($conn, trim($csv[0] ?? ''));
                $email = mysqli_real_escape_string($conn, trim($csv[1] ?? ''));
                $mobile = preg_replace('/[^0-9]/', '', trim($csv[2] ?? ''));
                $designation = mysqli_real_escape_string($conn, trim($csv[3] ?? ''));
                $institute_name = mysqli_real_escape_string($conn, trim($csv[4] ?? ''));
                $city = mysqli_real_escape_string($conn, trim($csv[5] ?? ''));

                if (empty($name) || empty($email) || empty($mobile)) {
                    $errors[] = "Row $row_num: Skipping due to missing Name, Email, or Mobile.";
                    continue;
                }
                
                // Check if user exists by email or mobile
                $user_id = null;
                $user_check_sql = "SELECT id FROM users WHERE email = '$email' OR mobile = '$mobile' limit 1";
                $user_check_result = mysqli_query($conn, $user_check_sql);
                if ($user_row = mysqli_fetch_assoc($user_check_result)) {
                    // User exists: Update them
                    $user_id = $user_row['id'];
                    $update_fields = [
                        "name='$name'",
                        "email='$email'",
                        "mobile='$mobile'",
                        "school_id=$csv_school_id",
                        "updated_at=NOW()"
                    ];
                    if (!empty($designation)) $update_fields[] = "designation='$designation'";
                    if (!empty($institute_name)) $update_fields[] = "institute_name='$institute_name'";
                    if (!empty($city)) $update_fields[] = "city='$city'";

                    $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id=$user_id";
                    if(mysqli_query($conn, $update_sql)) {
                        $stats['updated']++;
                    } else {
                        $errors[] = "Row $row_num: Failed to update existing user ($email): " . mysqli_error($conn);
                        continue;
                    }
                } else {
                    // User does not exist: Create them
                    $insert_sql = "INSERT INTO users (name, email, mobile, designation, institute_name, city, school_id, user_type, created_at, updated_at) 
                                   VALUES ('$name', '$email', '$mobile', '$designation', '$institute_name', '$city', $csv_school_id, 'user', NOW(), NOW())";
                    if(mysqli_query($conn, $insert_sql)) {
                        $user_id = mysqli_insert_id($conn);
                        $stats['new']++;
                    } else {
                        $errors[] = "Row $row_num: Failed to create new user ($email): " . mysqli_error($conn);
                        continue;
                    }
                }

                // Enroll the user if they were successfully created/updated
                if ($user_id) {
                    if(enrollUser($conn, $user_id, $csv_workshop_id, $workshop['price'], $workshop['cpd'], $errors)) {
                        $stats['processed']++;
                    }
                }
            }
            fclose($handle);

            $_SESSION['feedback_message'] = "CSV Processing Complete. Enrolled: {$stats['processed']}, New Users: {$stats['new']}, Updated Users: {$stats['updated']}.";
        } else {
            $_SESSION['feedback_error'] = "Failed to open the uploaded CSV file.";
        }
        if (!empty($errors)) {
            $_SESSION['feedback_error'] = ($_SESSION['feedback_error'] ?? '') . "<br>Errors:<br>" . implode('<br>', $errors);
        }
    } else {
        $_SESSION['feedback_error'] = "Please select a school before uploading a CSV.";
    }
    // Redirect to prevent form resubmission
    header("Location: bulk_enroll.php?workshop_id=$csv_workshop_id&school_id=$csv_school_id");
    exit();
}


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
            $_SESSION['feedback_message'] = "Successfully enrolled $enrolled_count new teachers.";
        }
        if (!empty($errors)) {
            $_SESSION['feedback_error'] = "Some errors occurred:<br>" . implode('<br>', $errors);
        }
        if ($enrolled_count === 0 && empty($errors)) {
            $_SESSION['feedback_message'] = "No new teachers were enrolled (they may have been enrolled previously).";
        }
    } else {
        $_SESSION['feedback_error'] = "No teachers were selected for enrollment.";
    }
    // Redirect to prevent form resubmission
    header("Location: bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id");
    exit();
}

// --- DATA FETCHING FOR DISPLAY ---
$users = [];
$is_search_or_filter_active = false;
$base_sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation,
             (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.workshop_id = $workshop_id AND p.payment_status = 1) as enrolled
             FROM users u";
$where_clauses = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($school_id > 0) {
        $where_clauses[] = "u.school_id = $school_id";
        $is_search_or_filter_active = true;
    }
    if (!empty($_GET['show_all'])) {
        $is_search_or_filter_active = true; // Show all, no WHERE clause needed
    }
    if (!empty($_GET['search'])) {
        $is_search_or_filter_active = true;
        if (!empty($_GET['name'])) $where_clauses[] = "u.name LIKE '%" . mysqli_real_escape_string($conn, $_GET['name']) . "%'";
        if (!empty($_GET['email'])) $where_clauses[] = "u.email LIKE '%" . mysqli_real_escape_string($conn, $_GET['email']) . "%'";
        if (!empty($_GET['mobile'])) $where_clauses[] = "u.mobile LIKE '%" . mysqli_real_escape_string($conn, $_GET['mobile']) . "%'";
        // Preserve school_id during search
        if ($school_id > 0) {
             $where_clauses[] = "u.school_id = $school_id";
        }
    }
}

if ($is_search_or_filter_active) {
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
    <title>Bulk Enroll Teachers | IPN Academy</title>
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
                        <h4 class="fs-18 text-uppercase fw-bold m-0">Bulk Enroll Teachers for "<?php echo htmlspecialchars($workshop['name']); ?>"</h4>
                    </div>
                    <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-primary mt-3 mt-sm-0"><i class="ti ti-arrow-left me-1"></i> Back to Workshop</a>
                </div>

                <?php if ($feedback_message): ?><div class="alert alert-success mt-3"><?php echo $feedback_message; ?></div><?php endif; ?>
                <?php if ($feedback_error): ?><div class="alert alert-danger mt-3"><?php echo $feedback_error; ?></div><?php endif; ?>

                <div class="row">
                    <!-- School Selector -->
                    <div class="col-lg-6">
                        <div class="card mt-4 h-100">
                            <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-school me-1"></i> Find by School</h5></div>
                            <div class="card-body">
                                <form method="GET" action="bulk_enroll.php">
                                    <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">School</label>
                                        <select name="school_id" class="form-select">
                                            <option value="">-- Select School --</option>
                                            <?php
                                            $all_schools = mysqli_query($conn, "SELECT id, name FROM schools ORDER BY name");
                                            while ($s = mysqli_fetch_assoc($all_schools)) {
                                                $selected = ($s['id'] == $school_id) ? 'selected' : '';
                                                echo "<option value='{$s['id']}' $selected>" . htmlspecialchars($s['name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Show Teachers</button>
                                    <a href="?workshop_id=<?php echo $workshop_id; ?>&show_all=1" class="btn btn-secondary">Show All Teachers</a>
                                </form>
                            </div>
                        </div>
                    </div>
                     <!-- CSV Uploader -->
                    <div class="col-lg-6">
                        <div class="card mt-4 h-100 <?php echo $school_id > 0 ? '' : 'bg-light'; ?>">
                            <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-file-upload me-1"></i> Enroll to School via CSV</h5></div>
                            <div class="card-body">
                                <?php if ($school_id > 0): ?>
                                <form method="POST" action="bulk_enroll.php" enctype="multipart/form-data">
                                    <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                    <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Upload CSV for <strong><?php echo htmlspecialchars(mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM schools WHERE id=$school_id"))['name']); ?></strong></label>
                                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                        <small class="text-muted">Required: Name, Email, Mobile. Optional: Designation, Institute Name, City.</small>
                                    </div>
                                    <button type="submit" name="upload_school_csv" value="1" class="btn btn-success">Upload and Enroll</button>
                                </form>
                                <?php else: ?>
                                <p class="text-muted">Please select a school from the "Find by School" panel first to enable CSV uploads for that school.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Form -->
                <div class="card mt-4">
                    <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-search me-1"></i> Search for Teachers</h5></div>
                    <div class="card-body">
                        <form method="GET" action="bulk_enroll.php">
                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                            <input type="hidden" name="search" value="1">
                            <?php if ($school_id > 0): ?>
                            <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="By Name" value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>"></div>
                                <div class="col-md-4"><input type="email" name="email" class="form-control" placeholder="By Email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"></div>
                                <div class="col-md-4"><input type="text" name="mobile" class="form-control" placeholder="By Mobile" value="<?php echo isset($_GET['mobile']) ? htmlspecialchars($_GET['mobile']) : ''; ?>"></div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12"><button type="submit" class="btn btn-primary">Search</button></div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Results and Enrollment Form -->
                <?php if ($is_search_or_filter_active): ?>
                <div class="card mt-4">
                    <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-users me-1"></i> Teacher List</h5></div>
                    <div class="card-body">
                        <form method="POST" action="bulk_enroll.php">
                            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                            <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                            <input type="hidden" name="enroll_users" value="1">
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAllUsers"></th>
                                            <th>Name</th><th>Email</th><th>Mobile</th><th>Designation</th><th>Enrolled?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="6" class="text-center">No teachers found matching your criteria.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><input type="checkbox" class="user-checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" <?php echo $user['enrolled'] ? 'disabled' : ''; ?>></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                            <td><?php echo htmlspecialchars($user['designation']); ?></td>
                                            <td><?php echo $user['enrolled'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-success" <?php if (empty($users)) echo 'disabled'; ?>>Enroll Selected Teachers</button>
                        </form>
                    </div>
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