<?php
session_start();
// Standalone school bulk enroll page
// No session or admin login required



$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// If email is missing, prompt for it
if ($workshop_id > 0 && $school_id > 0 && empty($email)) {
    echo '<!DOCTYPE html><html><head><title>School Bulk Enroll - Email Required</title>';
    echo '<link rel="stylesheet" href="assets/css/app.min.css">';
    echo '</head><body>';
    echo '<div class="container py-5" style="max-width: 400px;">';
    echo '<div class="card p-4">';
    echo '<h4 class="mb-3">Enter School Email</h4>';
    echo '<form method="get" action="school_bulk_enroll.php">';
    echo '<input type="hidden" name="workshop_id" value="' . htmlspecialchars($workshop_id) . '">';
    echo '<input type="hidden" name="school_id" value="' . htmlspecialchars($school_id) . '">';
    echo '<div class="mb-3"><input type="email" name="email" class="form-control" placeholder="School Email" required autofocus></div>';
    echo '<button type="submit" class="btn btn-primary w-100">Continue</button>';
    echo '</form>';
    echo '</div></div>';
    echo '</body></html>';
    exit();
}

// --- ACCESS CONTROL ---
$access_granted = false;
$school = null;
$workshop = null;

if ($workshop_id > 0 && $school_id > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $conn = require_once 'config/config.php';
    $school_result = mysqli_query($conn, "SELECT * FROM schools WHERE id = $school_id");
    if ($school_result && ($school = mysqli_fetch_assoc($school_result))) {
        if (strcasecmp($school['email'], $email) === 0) {
            $access_granted = true;
        }
    }
    $workshop_result = mysqli_query($conn, "SELECT * FROM workshops WHERE id = $workshop_id AND is_deleted = 0");
    if ($workshop_result && ($workshop = mysqli_fetch_assoc($workshop_result))) {
        // ok
    } else {
        $access_granted = false;
    }
}

if (!$access_granted) {
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body>';
    echo '<div style="max-width:500px;margin:100px auto;padding:2em;border:1px solid #ccc;text-align:center;">';
    echo '<h2>Access Denied</h2><p>This page is only accessible to the registered school via the correct link.</p>';
    echo '</div></body></html>';
    exit();
}

// Check if editing is locked
$is_locked = false;
$show_timer = false;
$lock_time_ist = null;

if ($workshop) {
    $now_ist = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $start_dt = new DateTime($workshop['start_date'] . ' ' . $workshop['start_time'], new DateTimeZone('Asia/Kolkata'));
    
    if ($workshop['lock_before_hours'] > 0) {
        $lock_dt = clone $start_dt;
        $lock_dt->modify('-' . $workshop['lock_before_hours'] . ' hours');
        $lock_time_ist = $lock_dt;
        $show_timer = true;
        
        if ($now_ist >= $lock_dt) {
            $is_locked = true;
        }
    } else {
        $lock_dt = clone $start_dt;
        $lock_dt->modify('-3 hours');
        $lock_time_ist = $lock_dt;
        $show_timer = true;
        
        if ($now_ist >= $lock_dt) {
            $is_locked = true;
        }
    }
}



// Block any form submissions when editing is locked (additional security)
if ($is_locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['feedback_error'] = 'Editing is locked for this workshop. You cannot make changes at this time.';
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- FEEDBACK ---
$feedback_message = $_SESSION['feedback_message'] ?? '';
$feedback_error = $_SESSION['feedback_error'] ?? '';
unset($_SESSION['feedback_message'], $_SESSION['feedback_error']);

// --- Clear session data if requested ---
if (isset($_GET['clear_session']) && $_GET['clear_session'] == '1') {
    unset($_SESSION['existing_users_data'], $_SESSION['show_user_selection']);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // AJAX request - return empty response
        exit();
    }
}

// --- ENROLLMENT LOGIC (same as bulk_enroll.php, but only for this school) ---
function enrollUser($conn, $user_id, $workshop_id, $amount, $cpd_hrs, $school_id, &$errors) {
    $user_id = intval($user_id);
    $check_sql = "SELECT id FROM payments WHERE user_id = $user_id AND workshop_id = $workshop_id AND payment_status = 1";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        return false;
    }
    $payment_id = 'B2B-ENRL-' . uniqid();
    $order_id = 'B2B-' . uniqid();
    $insert_sql = "INSERT INTO payments (user_id, workshop_id, amount, payment_status, payment_id, school_id, is_attended, mail_send, order_id, created_at, updated_at, cpd) 
                   VALUES ($user_id, $workshop_id, $amount, 1, '$payment_id', $school_id, 0, 0, '$order_id', NOW(), NOW(), '$cpd_hrs')";
    if (mysqli_query($conn, $insert_sql)) {
        return true;
    } else {
        $errors[] = "Failed to enroll user ID $user_id: " . mysqli_error($conn);
        return false;
    }
}

// --- CSV Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_school_csv']) && !$is_locked) {
    $file = $_FILES['csv_file']['tmp_name'];
    if ($_FILES['csv_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($file)) {
        $stats = ['new' => 0, 'updated' => 0, 'errors' => 0, 'processed' => 0];
        $errors = [];
        $row_num = 1;
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header row
            while (($csv = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_num++;
                $name = mysqli_real_escape_string($conn, trim($csv[0] ?? ''));
                $email_csv = mysqli_real_escape_string($conn, trim($csv[1] ?? ''));
                $mobile = preg_replace('/[^0-9]/', '', trim($csv[2] ?? ''));
                $designation = mysqli_real_escape_string($conn, trim($csv[3] ?? ''));
                $institute_name = mysqli_real_escape_string($conn, trim($csv[4] ?? ''));
                $city = mysqli_real_escape_string($conn, trim($csv[5] ?? ''));
                if (empty($name) || empty($email_csv) || empty($mobile)) {
                    $errors[] = "Row $row_num: Skipping due to missing Name, Email, or Mobile.";
                    continue;
                }
                $user_id = null;
                $user_check_sql = "SELECT id FROM users WHERE email = '$email_csv' OR mobile = '$mobile' limit 1";
                $user_check_result = mysqli_query($conn, $user_check_sql);
                if ($user_row = mysqli_fetch_assoc($user_check_result)) {
                    $user_id = $user_row['id'];
                    $update_fields = [
                        "name='$name'",
                        "email='$email_csv'",
                        "mobile='$mobile'",
                        "school_id=$school_id",
                        "updated_at=NOW()"
                    ];
                    if (!empty($designation)) $update_fields[] = "designation='$designation'";
                    if (!empty($institute_name)) $update_fields[] = "institute_name='$institute_name'";
                    if (!empty($city)) $update_fields[] = "city='$city'";
                    $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id=$user_id";
                    if(mysqli_query($conn, $update_sql)) {
                        $stats['updated']++;
                    } else {
                        $errors[] = "Row $row_num: Failed to update existing user ($email_csv): " . mysqli_error($conn);
                        continue;
                    }
                } else {
                    $insert_sql = "INSERT INTO users (name, email, mobile, designation, institute_name, city, school_id, user_type, created_at, updated_at) 
                                   VALUES ('$name', '$email_csv', '$mobile', '$designation', '$institute_name', '$city', $school_id, 'user', NOW(), NOW())";
                    if(mysqli_query($conn, $insert_sql)) {
                        $user_id = mysqli_insert_id($conn);
                        $stats['new']++;
                    } else {
                        $errors[] = "Row $row_num: Failed to create new user ($email_csv): " . mysqli_error($conn);
                        continue;
                    }
                }
                if ($user_id) {
                    if(enrollUser($conn, $user_id, $workshop_id, $workshop['price'], $workshop['cpd'], $school_id, $errors)) {
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
        $_SESSION['feedback_error'] = "Please upload a valid CSV file.";
    }
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- Manual Enrollment (search + select) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_users']) && !$is_locked) {
    $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    if (!empty($user_ids)) {
        $enrolled_count = 0;
        $errors = [];
        foreach ($user_ids as $user_id) {
            if (enrollUser($conn, $user_id, $workshop_id, $workshop['price'], $workshop['cpd'], $school_id, $errors)) {
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
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- Unenroll logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_unenroll_users']) && !$is_locked) {
    $unenroll_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    $unenrolled_count = 0;
    $errors = [];
    foreach ($unenroll_ids as $uid) {
        $uid = intval($uid);
        $del_sql = "DELETE FROM payments WHERE user_id=$uid AND workshop_id=$workshop_id AND school_id=$school_id AND payment_status=1";
        if (mysqli_query($conn, $del_sql) && mysqli_affected_rows($conn) > 0) {
            $unenrolled_count++;
        } else {
            $errors[] = "Failed to unenroll user ID $uid.";
        }
    }
    if ($unenrolled_count > 0) {
        $_SESSION['feedback_message'] = "Successfully unenrolled $unenrolled_count users.";
    }
    if (!empty($errors)) {
        $_SESSION['feedback_error'] = implode('<br>', $errors);
    }
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- Manual Add User logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_add_user']) && !$is_locked) {
    $name = mysqli_real_escape_string($conn, trim($_POST['new_name'] ?? ''));
    $email_new = mysqli_real_escape_string($conn, trim($_POST['new_email'] ?? ''));
    $mobile = preg_replace('/[^0-9]/', '', trim($_POST['new_mobile'] ?? ''));
    $designation = mysqli_real_escape_string($conn, trim($_POST['new_designation'] ?? ''));
    $institute_name = mysqli_real_escape_string($conn, trim($_POST['new_institute_name'] ?? ''));
    $city = mysqli_real_escape_string($conn, trim($_POST['new_city'] ?? ''));
    $errors = [];
    
    if (empty($name) || empty($email_new) || empty($mobile)) {
        $_SESSION['feedback_error'] = "Name, Email, and Mobile are required.";
    } else {
        // Check for existing users by email and/or mobile
        $existing_users_sql = "SELECT id, name, email, mobile, designation, institute_name, city, school_id FROM users WHERE email = '$email_new' OR mobile = '$mobile' ORDER BY name";
        $existing_users_result = mysqli_query($conn, $existing_users_sql);
        $existing_users = [];
        while ($row = mysqli_fetch_assoc($existing_users_result)) {
            $existing_users[] = $row;
        }
        
        if (empty($existing_users)) {
            // No existing user found, create new user
            $insert_sql = "INSERT INTO users (name, email, mobile, designation, institute_name, city, school_id, user_type, created_at, updated_at) 
                           VALUES ('$name', '$email_new', '$mobile', '$designation', '$institute_name', '$city', $school_id, 'user', NOW(), NOW())";
            if(mysqli_query($conn, $insert_sql)) {
                $user_id = mysqli_insert_id($conn);
                // Enroll in workshop
                if (enrollUser($conn, $user_id, $workshop_id, $workshop['price'], $workshop['cpd'], $school_id, $errors)) {
                    $_SESSION['feedback_message'] = "User added and enrolled successfully.";
                } else {
                    $_SESSION['feedback_message'] = "User added, but failed to enroll.";
                }
            } else {
                $_SESSION['feedback_error'] = "Failed to add user: " . mysqli_error($conn);
            }
        } else {
            // Store existing users data in session for popup selection
            $_SESSION['existing_users_data'] = [
                'users' => $existing_users,
                'new_user_data' => [
                    'name' => $name,
                    'email' => $email_new,
                    'mobile' => $mobile,
                    'designation' => $designation,
                    'institute_name' => $institute_name,
                    'city' => $city
                ],
                'workshop_id' => $workshop_id,
                'school_id' => $school_id
            ];
            $_SESSION['show_user_selection'] = true;
        }
    }
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- Handle user selection from popup ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_existing_user'])) {
    $selected_user_id = intval($_POST['selected_user_id']);
    $action = $_POST['action'] ?? 'enroll'; // 'enroll' or 'create_new'
    
    if ($action === 'enroll' && $selected_user_id > 0) {
        // Enroll existing user and update school_id
        $errors = [];
        
        // First, update the user's school_id
        $update_sql = "UPDATE users SET school_id = $school_id, updated_at = NOW() WHERE id = $selected_user_id";
        if (!mysqli_query($conn, $update_sql)) {
            $errors[] = "Failed to update user's school association: " . mysqli_error($conn);
        }
        
        // Then enroll in workshop
        if (enrollUser($conn, $selected_user_id, $workshop_id, $workshop['price'], $workshop['cpd'], $school_id, $errors)) {
            $_SESSION['feedback_message'] = "Existing user enrolled successfully and assigned to school.";
        } else {
            $_SESSION['feedback_error'] = "Failed to enroll user: " . implode(', ', $errors);
        }
    } elseif ($action === 'create_new') {
        // Create new user with the data from session
        $user_data = $_SESSION['existing_users_data']['new_user_data'] ?? null;
        if ($user_data) {
            $insert_sql = "INSERT INTO users (name, email, mobile, designation, institute_name, city, school_id, user_type, created_at, updated_at) 
                           VALUES ('" . mysqli_real_escape_string($conn, $user_data['name']) . "', 
                                   '" . mysqli_real_escape_string($conn, $user_data['email']) . "', 
                                   '" . mysqli_real_escape_string($conn, $user_data['mobile']) . "', 
                                   '" . mysqli_real_escape_string($conn, $user_data['designation']) . "', 
                                   '" . mysqli_real_escape_string($conn, $user_data['institute_name']) . "', 
                                   '" . mysqli_real_escape_string($conn, $user_data['city']) . "', 
                                   $school_id, 'user', NOW(), NOW())";
            if(mysqli_query($conn, $insert_sql)) {
                $user_id = mysqli_insert_id($conn);
                $errors = [];
                if (enrollUser($conn, $user_id, $workshop_id, $workshop['price'], $workshop['cpd'], $school_id, $errors)) {
                    $_SESSION['feedback_message'] = "New user created and enrolled successfully.";
                } else {
                    $_SESSION['feedback_message'] = "New user created, but failed to enroll.";
                }
            } else {
                $_SESSION['feedback_error'] = "Failed to create new user: " . mysqli_error($conn);
            }
        }
    }
    
    // Clear session data
    unset($_SESSION['existing_users_data'], $_SESSION['show_user_selection']);
    
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- Manual Edit User logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user']) && !$is_locked) {
    $edit_user_id = intval($_POST['edit_user_id']);
    $name = mysqli_real_escape_string($conn, trim($_POST['edit_name'] ?? ''));
    $email_edit = mysqli_real_escape_string($conn, trim($_POST['edit_email'] ?? ''));
    $mobile = preg_replace('/[^0-9]/', '', trim($_POST['edit_mobile'] ?? ''));
    $designation = mysqli_real_escape_string($conn, trim($_POST['edit_designation'] ?? ''));
    $institute_name = mysqli_real_escape_string($conn, trim($_POST['edit_institute_name'] ?? ''));
    $city = mysqli_real_escape_string($conn, trim($_POST['edit_city'] ?? ''));
    if (empty($name) || empty($email_edit) || empty($mobile)) {
        $_SESSION['feedback_error'] = "Name, Email, and Mobile are required.";
    } else {
        // Check for duplicate email/mobile (other than this user)
        $dup_sql = "SELECT id FROM users WHERE (email = '$email_edit' OR mobile = '$mobile') AND id != $edit_user_id LIMIT 1";
        $dup_result = mysqli_query($conn, $dup_sql);
        if (mysqli_fetch_assoc($dup_result)) {
            $_SESSION['feedback_error'] = "Another user with this email or mobile already exists.";
        } else {
            $update_sql = "UPDATE users SET name='$name', email='$email_edit', mobile='$mobile', designation='$designation', institute_name='$institute_name', city='$city', updated_at=NOW() WHERE id=$edit_user_id AND school_id=$school_id";
            if (mysqli_query($conn, $update_sql)) {
                $_SESSION['feedback_message'] = "User updated successfully.";
            } else {
                $_SESSION['feedback_error'] = "Failed to update user: " . mysqli_error($conn);
            }
        }
    }
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- DATA FETCHING FOR DISPLAY ---
$users = [];
$is_search_or_filter_active = false;
$base_sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation,
             (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.workshop_id = $workshop_id AND p.payment_status = 1) as enrolled
             FROM users u";
$where_clauses = ["u.school_id = $school_id"];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $is_search_or_filter_active = true;
    if (!empty($_GET['search_name'])) $where_clauses[] = "u.name LIKE '%" . mysqli_real_escape_string($conn, $_GET['search_name']) . "%'";
    if (!empty($_GET['search_email'])) $where_clauses[] = "u.email LIKE '%" . mysqli_real_escape_string($conn, $_GET['search_email']) . "%'";
    if (!empty($_GET['search_mobile'])) $where_clauses[] = "u.mobile LIKE '%" . mysqli_real_escape_string($conn, $_GET['search_mobile']) . "%'";
}

$sql = $base_sql . " WHERE " . implode(' AND ', $where_clauses) . " ORDER BY u.name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// --- SMART ENROLLMENT STATS ---
$stats = [
    'total_registered' => 0,
    'total_enrolled' => 0,
    'total_unenrolled' => 0,
    'invested_amount' => 0,
    'cpd_hours' => 0
];
$stats_sql = "SELECT 
    COUNT(DISTINCT u.id) as total_registered,
    SUM(CASE WHEN p.payment_status=1 THEN 1 ELSE 0 END) as total_enrolled,
    SUM(CASE WHEN p.payment_status=0 THEN 1 ELSE 0 END) as total_unenrolled,
    SUM(CASE WHEN p.payment_status=1 THEN p.cpd ELSE 0 END) as cpd_hours
FROM users u
LEFT JOIN payments p ON p.user_id = u.id AND p.workshop_id = $workshop_id AND p.school_id = $school_id
WHERE u.school_id = $school_id";
$stats_result = mysqli_query($conn, $stats_sql);
if ($stats_result && ($row = mysqli_fetch_assoc($stats_result))) {
    $stats = array_merge($stats, $row);
}
$stats['invested_amount'] = $workshop['price'] * $stats['total_enrolled'];

// --- ATTENDANCE STATS ---
$attendance_stats = [
    'total_enrolled' => 0,
    'total_attended' => 0,
    'avg_duration' => 0,
    'completion_rate' => 0,
    'duration_stats' => [
        '0-15' => 0,
        '15-30' => 0,
        '30-60' => 0,
        '60+' => 0
    ]
];
$enrolled_sql = "SELECT COUNT(*) as total FROM payments WHERE workshop_id = $workshop_id AND school_id = $school_id AND payment_status = 1";
$enrolled_result = mysqli_query($conn, $enrolled_sql);
if ($enrolled_result) {
    $attendance_stats['total_enrolled'] = mysqli_fetch_assoc($enrolled_result)['total'];
}
$attendance_sql = "SELECT 
    COUNT(*) as total_attended,
    AVG(attended_duration) as avg_duration,
    SUM(CASE WHEN attended_duration > 0 AND attended_duration <= 15 THEN 1 ELSE 0 END) as duration_0_15,
    SUM(CASE WHEN attended_duration > 15 AND attended_duration <= 30 THEN 1 ELSE 0 END) as duration_15_30,
    SUM(CASE WHEN attended_duration > 30 AND attended_duration <= 60 THEN 1 ELSE 0 END) as duration_30_60,
    SUM(CASE WHEN attended_duration > 60 THEN 1 ELSE 0 END) as duration_60_plus
    FROM payments 
    WHERE workshop_id = $workshop_id 
    AND school_id = $school_id
    AND payment_status = 1 
    AND is_attended = 1";
$attendance_result = mysqli_query($conn, $attendance_sql);
if ($attendance_result) {
    $statsA = mysqli_fetch_assoc($attendance_result);
    $attendance_stats['total_attended'] = $statsA['total_attended'];
    $attendance_stats['avg_duration'] = round($statsA['avg_duration'], 1);
    $attendance_stats['duration_stats']['0-15'] = $statsA['duration_0_15'];
    $attendance_stats['duration_stats']['15-30'] = $statsA['duration_15_30'];
    $attendance_stats['duration_stats']['30-60'] = $statsA['duration_30_60'];
    $attendance_stats['duration_stats']['60+'] = $statsA['duration_60_plus'];
    if ($attendance_stats['total_enrolled'] > 0) {
        $attendance_stats['completion_rate'] = round(($attendance_stats['total_attended'] / $attendance_stats['total_enrolled']) * 100, 1);
    }
}

// --- Attendance Details Export as CSV ---
if (isset($_GET['export_attendance_csv']) && $_GET['export_attendance_csv'] == '1') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_details.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Mobile', 'Attended?', 'Attended Duration (min)', 'CPD', 'Payment Date']);
    $att_sql = "SELECT u.name, u.email, u.mobile, p.is_attended, p.attended_duration, p.cpd, p.created_at FROM users u INNER JOIN payments p ON p.user_id = u.id WHERE p.workshop_id = $workshop_id AND p.school_id = $school_id AND p.payment_status = 1 ORDER BY u.name";
    $att_result = mysqli_query($conn, $att_sql);
    while ($row = mysqli_fetch_assoc($att_result)) {
        fputcsv($out, [
            $row['name'],
            $row['email'],
            $row['mobile'],
            $row['is_attended'] ? 'Yes' : 'No',
            htmlspecialchars(min($row['attended_duration'], 120)),
            $row['cpd'],
            $row['created_at']
        ]);
    }
    fclose($out);
    exit();
}

// --- Bulk Remove from School logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_remove_school']) && !$is_locked) {
    $remove_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    $removed_count = 0;
    $errors = [];
    foreach ($remove_ids as $uid) {
        $uid = intval($uid);
        $update_sql = "UPDATE users SET school_id=NULL WHERE id=$uid AND school_id=$school_id";
        $del_sql = "DELETE FROM payments WHERE user_id=$uid AND workshop_id=$workshop_id AND school_id=$school_id";
        $ok1 = mysqli_query($conn, $update_sql);
        $ok2 = mysqli_query($conn, $del_sql);
        if ($ok1) {
            $removed_count++;
        } else {
            $errors[] = "Failed to remove user ID $uid from school.";
        }
    }
    if ($removed_count > 0) {
        $_SESSION['feedback_message'] = "Removed $removed_count users from school and unenrolled them.";
    }
    if (!empty($errors)) {
        $_SESSION['feedback_error'] = implode('<br>', $errors);
    }
    header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    exit();
}

// --- Add Existing User backend logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_existing_user'])) {
    $existing_user_id = intval($_POST['existing_user_id']);
    $errors = [];
    // Assign user to this school (allow moving from any school)
    $update_sql = "UPDATE users SET school_id=$school_id WHERE id=$existing_user_id";
    if (!mysqli_query($conn, $update_sql)) {
        $errors[] = "Failed to assign user to school: " . mysqli_error($conn);
    }
    // Enroll in workshop if not already
    $check_sql = "SELECT id FROM payments WHERE user_id=$existing_user_id AND workshop_id=$workshop_id AND school_id=$school_id";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_fetch_assoc($check_result)) {
        $errors[] = "User is already enrolled in this workshop for this school.";
    } else if (empty($errors)) {
        $amount = $workshop['price'];
        $cpd_hrs = $workshop['cpd'];
        $insert_sql = "INSERT INTO payments (user_id, workshop_id, school_id, payment_status, amount, cpd, created_at) VALUES ($existing_user_id, $workshop_id, $school_id, 1, $amount, $cpd_hrs, NOW())";
        if (!mysqli_query($conn, $insert_sql)) {
            $errors[] = "Failed to enroll user: " . mysqli_error($conn);
        }
    }
    if (empty($errors)) {
        $_SESSION['feedback_message'] = "User added to school and enrolled successfully.";
        // Redirect to page without search_existing=1
        header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email));
    } else {
        $_SESSION['feedback_error'] = implode('<br>', $errors);
        // Stay on modal for error
        header("Location: school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($email) . "&search_existing=1");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Enroll Teachers | <?php echo htmlspecialchars($school['name']); ?> | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .header-bar {
            width: 100%;
            background: #fff;
            border-bottom: 1px solid #e1e4e8;
            padding: 8px 0 8px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 52px;
        }
        .header-bar .logo {
            height: 32px;
            margin-left: 16px;
        }
        .header-bar .workshop-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.18rem;
            text-align: center;
            flex: 1 1 auto;
        }
        .header-bar .school-name {
            font-weight: 400;
            color: #34495e;
            font-size: 1.05rem;
            margin-right: 16px;
            text-align: right;
            white-space: nowrap;
        }
        .header-bar .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-bar .icon-btn {
            background: none;
            border: none;
            padding: 0 6px;
            font-size: 1.3rem;
            color: #2c3e50;
            cursor: pointer;
            transition: color 0.2s;
        }
        .header-bar .icon-btn:hover {
            color: #007bff;
        }
        .header-bar .csv-btn {
            font-size: 0.95rem;
            padding: 2px 10px;
        }
        @media (max-width: 600px) {
            .header-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 10px 0 10px 0;
            }
            .header-bar .logo {
                margin: 0 auto 4px auto;
                display: block;
                height: 26px;
            }
            .header-bar .workshop-title {
                font-size: 1.05rem;
                margin-bottom: 2px;
            }
            .header-bar .school-name {
                margin: 0 auto;
                font-size: 0.98rem;
                text-align: center;
            }
            .header-bar .header-actions {
                justify-content: center;
                margin-top: 4px;
            }
        }
        .main-content {
            padding-top: 16px;
        }
        
        /* Blur sensitive data when editing is locked */
        .blur-sensitive {
            filter: blur(4px);
            transition: filter 0.3s ease;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        
        .blur-sensitive:hover {
            filter: blur(0);
        }
        
        /* Hide sensitive data from developer tools */
        .blur-sensitive {
            -webkit-filter: blur(4px);
            -moz-filter: blur(4px);
            -o-filter: blur(4px);
            -ms-filter: blur(4px);
        }
        
        /* Additional protection against inspection */
        .blur-sensitive {
            pointer-events: none;
        }
        
        .blur-sensitive:hover {
            pointer-events: auto;
        }
        
        /* Placeholder styling for sensitive data */
        .sensitive-data-placeholder {
            color: #999;
            font-family: monospace;
            letter-spacing: 2px;
        }
        
        .sensitive-data-real {
            display: none !important;
        }
        
        /* Show real data only on hover when not in locked mode */
        .blur-sensitive:hover .sensitive-data-placeholder {
            display: none;
        }
        
        .blur-sensitive:hover .sensitive-data-real {
            display: inline !important;
        }
        
        /* Prevent printing of sensitive data */
        @media print {
            .blur-sensitive,
            .sensitive-data-real,
            .sensitive-data-placeholder {
                display: none !important;
            }
        }
        
        /* Hide checkboxes when locked */
        .locked-mode .user-checkbox {
            display: none !important;
        }
        

    </style>
</head>
<body class="<?php echo $is_locked ? 'locked-mode' : ''; ?>">
    <div class="header-bar">
        <img src="https://ipnacademy.in/user/images/logo.png" alt="IPN Academy Logo" class="logo">
        <div class="workshop-title">Enroll Teachers for <?php echo htmlspecialchars($workshop['name']); ?></div>
        <div class="school-name"><?php echo htmlspecialchars($school['name']); ?></div>
        <div class="header-actions">
            <span id="countdown-timer" style="font-size:0.98rem; color:#007bff; font-weight:600;"></span>
            <button class="icon-btn" title="Help" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="ti ti-help-circle"></i></button>
        </div>
    </div>
    <div class="container main-content py-4">
    <?php if ($is_locked): ?>
        <div class="alert alert-warning text-center mb-4">Editing is locked for this workshop. You can view data, but cannot add, edit, or enroll teachers until after the workshop.</div>
    <?php endif; ?>

    <?php if ($feedback_message): ?><div class="alert alert-success mt-3"><?php echo $feedback_message; ?></div><?php endif; ?>
    <?php if ($feedback_error): ?><div class="alert alert-danger mt-3"><?php echo $feedback_error; ?></div><?php endif; ?>

    <div class="row">
        <!-- Smart Enrollment Stats Card -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="ti ti-chart-bar me-1"></i> School Enrollment & Investment Stats</h5></div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4"><div class="fw-bold">Registered</div><div class="fs-4 text-primary"><?php echo $stats['total_registered']; ?></div></div>
                    <div class="col-md-4"><div class="fw-bold">Enrolled</div><div class="fs-4 text-success"><?php echo $stats['total_enrolled']; ?></div></div>
                    <!-- <div class="col-md-3"><div class="fw-bold">Invested Amount</div><div class="fs-4 text-info">₹<?php echo number_format($stats['invested_amount'], 2); ?></div></div> -->
                    <div class="col-md-4"><div class="fw-bold">Cultivated CPD Hours</div><div class="fs-4 text-warning"><?php echo $stats['cpd_hours']; ?></div></div>
                </div>
            </div>
        </div>
        
        <?php if (!$is_locked): ?>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-file-upload me-1"></i> Enroll via CSV</h5></div>
                <div class="card-body">
                    <form method="POST" action="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&email=<?php echo urlencode($email); ?>" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Upload CSV for <strong><?php echo htmlspecialchars($school['name']); ?></strong></label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <small class="text-muted">Required: Name, Email, Mobile. Optional: Designation, Institute Name, City.</small>
                        </div>
                        <button type="submit" name="upload_school_csv" value="1" class="btn btn-success">Upload and Enroll</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-search me-1"></i> Search & Enroll | Below Table</h5></div>
                <div class="card-body">
                    <form method="GET" action="school_bulk_enroll.php">
                        <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                        <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <div class="row">
                            <div class="col-md-4"><input type="text" name="search_name" class="form-control" placeholder="By Name" value="<?php echo isset($_GET['search_name']) ? htmlspecialchars($_GET['search_name']) : ''; ?>"></div>
                            <div class="col-md-4"><input type="email" name="search_email" class="form-control" placeholder="By Email" value="<?php echo isset($_GET['search_email']) ? htmlspecialchars($_GET['search_email']) : ''; ?>"></div>
                            <div class="col-md-4"><input type="text" name="search_mobile" class="form-control" placeholder="By Mobile" value="<?php echo isset($_GET['search_mobile']) ? htmlspecialchars($_GET['search_mobile']) : ''; ?>"></div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12"><button type="submit" class="btn btn-primary">Search</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!$is_locked): ?>
    <div class="d-flex justify-content-end mb-2">
        <!-- <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addExistingUserModal">+ Add Existing User</button> -->
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">+ Add New User</button>
    </div>
    <?php endif; ?>
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST" action="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&email=<?php echo urlencode($email); ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Name *</label>
                <input type="text" name="new_name" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="new_email" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Mobile *</label>
                <input type="text" name="new_mobile" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Designation</label>
                <input type="text" name="new_designation" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Institute Name</label>
                <input type="text" name="new_institute_name" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">City</label>
                <input type="text" name="new_city" class="form-control">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="manual_add_user" value="1" class="btn btn-success">Add & Enroll</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-users me-1"></i> Teacher List</h5></div>
        <div class="card-body">
            <form method="POST" action="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&email=<?php echo urlencode($email); ?>">
                <div class="table-responsive mb-3">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllUsers"></th>
                                <th>Name</th>
                                <th>Email <?php if ($is_locked): ?><small class="text-muted">(Blurred)</small><?php endif; ?></th>
                                <th>Mobile <?php if ($is_locked): ?><small class="text-muted">(Blurred)</small><?php endif; ?></th>
                                <th>Designation <?php if ($is_locked): ?><small class="text-muted">(Blurred)</small><?php endif; ?></th>
                                <th>Enrolled?</th>
                                <th>Workshop Link</th><?php if (!$is_locked): ?><th>Edit</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="<?php echo $is_locked ? '7' : '8'; ?>" class="text-center">No teachers found matching your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="user-checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td class="<?php echo $is_locked ? 'blur-sensitive' : ''; ?>">
                                    <?php if ($is_locked): ?>
                                        <span class="sensitive-data-placeholder">••••••••••••••••••••</span>
                                        <span class="sensitive-data-real" style="display: none;" data-encrypted="<?php echo base64_encode($user['email']); ?>"></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $is_locked ? 'blur-sensitive' : ''; ?>">
                                    <?php if ($is_locked): ?>
                                        <span class="sensitive-data-placeholder">••••••••••••••••••••</span>
                                        <span class="sensitive-data-real" style="display: none;" data-encrypted="<?php echo base64_encode($user['mobile']); ?>"></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($user['mobile']); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="<?php echo $is_locked ? 'blur-sensitive' : ''; ?>">
                                    <?php if ($is_locked): ?>
                                        <span class="sensitive-data-placeholder">••••••••••••••••••••</span>
                                        <span class="sensitive-data-real" style="display: none;" data-encrypted="<?php echo base64_encode($user['designation']); ?>"></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($user['designation']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['enrolled']): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['enrolled']) : ?>
                                        <?php if (
                                            !empty($workshop['meeting_id']) &&
                                            $workshop['meeting_id'] !== '#' &&
                                            strtolower($workshop['meeting_id']) !== 'null'
                                        ): ?>
                                            <div class="d-flex gap-1">
                                                <a href="https://meet.ipnacademy.in/?display_name=<?php echo $user['id'].'_'.urlencode($user['name']); ?>&mn=<?php echo urlencode($workshop['meeting_id']); ?>&pwd=<?php echo urlencode($workshop['passcode']); ?>&meeting_email=<?php echo urlencode($user['email']); ?>" target="_blank" class="btn btn-sm btn-info">Unique Joining Link</a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary copy-link-btn" 
                                                        data-link="https://meet.ipnacademy.in/?display_name=<?php echo $user['id'].'_'.urlencode($user['name']); ?>&mn=<?php echo urlencode($workshop['meeting_id']); ?>&pwd=<?php echo urlencode($workshop['passcode']); ?>&meeting_email=<?php echo urlencode($user['email']); ?>"
                                                        title="Copy joining link">
                                                    <i class="ti ti-copy"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            Available Soon
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Not Registered
                                    <?php endif; ?>
                                </td>
                                <?php if (!$is_locked): ?>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning edit-user-btn" 
                                        data-user='<?php echo json_encode([
                                            "id" => $user['id'],
                                            "name" => $user['name'],
                                            "email" => $user['email'],
                                            "mobile" => $user['mobile'],
                                            "designation" => $user['designation'],
                                            "institute_name" => $user['institute_name'] ?? '',
                                            "city" => $user['city'] ?? ''
                                        ]); ?>'
                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        Edit
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$is_locked): ?>
                <button type="submit" name="enroll_users" value="1" class="btn btn-success">Enroll Selected Teachers</button>
                <button type="submit" name="bulk_unenroll_users" value="1" class="btn btn-danger ms-2">Bulk Unenroll Selected</button>
                <button type="submit" name="bulk_remove_school" value="1" class="btn btn-warning ms-2">Remove from School</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Attendance Stats Card -->
    <div class="card mt-4 mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="ti ti-chart-bar me-1"></i> Attendance Statistics</h5>
            <div>
                <button class="btn btn-outline-primary btn-sm me-2" type="button" id="expandAttendanceBtn">Show Attendance Details</button>
                <a href="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&email=<?php echo urlencode($email); ?>&export_attendance_csv=1" class="btn btn-outline-success btn-sm">Download CSV</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Enrolled</h6>
                            <h2 class="mb-0"><?php echo $attendance_stats['total_enrolled']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Total Attended</h6>
                            <h2 class="mb-0"><?php echo $attendance_stats['total_attended']; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Avg. Duration</h6>
                            <h2 class="mb-0"><?php echo $attendance_stats['avg_duration']; ?> min</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Completion Rate</h6>
                            <h2 class="mb-0"><?php echo $attendance_stats['completion_rate']; ?>%</h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <h6 class="mb-3">Duration Distribution</h6>
                    <div class="progress" style="height: 25px;">
                        <?php
                        $total = array_sum($attendance_stats['duration_stats']);
                        if ($total > 0) {
                            $width_0_15 = ($attendance_stats['duration_stats']['0-15'] / $total) * 100;
                            $width_15_30 = ($attendance_stats['duration_stats']['15-30'] / $total) * 100;
                            $width_30_60 = ($attendance_stats['duration_stats']['30-60'] / $total) * 100;
                            $width_60_plus = ($attendance_stats['duration_stats']['60+'] / $total) * 100;
                        ?>
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $width_0_15; ?>%"
                             title="0-15 min: <?php echo $attendance_stats['duration_stats']['0-15']; ?> participants">
                            <?php echo round($width_0_15); ?>%
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $width_15_30; ?>%"
                             title="15-30 min: <?php echo $attendance_stats['duration_stats']['15-30']; ?> participants">
                            <?php echo round($width_15_30); ?>%
                        </div>
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $width_30_60; ?>%"
                             title="30-60 min: <?php echo $attendance_stats['duration_stats']['30-60']; ?> participants">
                            <?php echo round($width_30_60); ?>%
                        </div>
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $width_60_plus; ?>%"
                             title="60+ min: <?php echo $attendance_stats['duration_stats']['60+']; ?> participants">
                            <?php echo round($width_60_plus); ?>%
                        </div>
                        <?php } ?>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-danger">0-15 min</small>
                        <small class="text-warning">15-30 min</small>
                        <small class="text-info">30-60 min</small>
                        <small class="text-success">60+ min</small>
                    </div>
                </div>
            </div>
            <div id="attendanceDetailsTable" style="display:none;">
                <hr>
                <h6>Attendance Details</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Attended?</th>
                                <th>Attended Duration (min)</th>
                                <!-- <th>CPD</th> -->
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $att_sql = "SELECT u.name, u.email, u.mobile, p.is_attended, p.attended_duration, p.cpd, p.created_at FROM users u INNER JOIN payments p ON p.user_id = u.id WHERE p.workshop_id = $workshop_id AND p.school_id = $school_id AND p.payment_status = 1 ORDER BY u.name";
                        $att_result = mysqli_query($conn, $att_sql);
                        if ($att_result && mysqli_num_rows($att_result) > 0):
                            while ($row = mysqli_fetch_assoc($att_result)):
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                                <td><?php echo $row['is_attended'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>'; ?></td>
                                <td><?php echo htmlspecialchars(min($row['attended_duration'], 120)); ?></td>
                                <!-- <td><?php echo htmlspecialchars($row['cpd']); ?></td> -->
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center">No attendance data found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&email=<?php echo urlencode($email); ?>">
        <input type="hidden" name="edit_user_id" id="edit_user_id">
        <div class="modal-header">
          <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name *</label>
            <input type="text" name="edit_name" id="edit_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" name="edit_email" id="edit_email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Mobile *</label>
            <input type="text" name="edit_mobile" id="edit_mobile" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Designation</label>
            <input type="text" name="edit_designation" id="edit_designation" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Institute Name</label>
            <input type="text" name="edit_institute_name" id="edit_institute_name" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">City</label>
            <input type="text" name="edit_city" id="edit_city" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_user" value="1" class="btn btn-success">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="addExistingUserModal" tabindex="-1" aria-labelledby="addExistingUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="GET" action="school_bulk_enroll.php">
        <div class="modal-header">
          <h5 class="modal-title" id="addExistingUserModalLabel">Add Existing User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
          <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
          <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
          <input type="hidden" name="search_existing" value="1">
          <div class="row mb-3">
            <div class="col-md-3"><input type="text" name="ex_name" class="form-control" placeholder="By Name" value="<?php echo isset($_GET['ex_name']) ? htmlspecialchars($_GET['ex_name']) : ''; ?>"></div>
            <div class="col-md-3"><input type="email" name="ex_email" class="form-control" placeholder="By Email" value="<?php echo isset($_GET['ex_email']) ? htmlspecialchars($_GET['ex_email']) : ''; ?>"></div>
            <div class="col-md-3"><input type="text" name="ex_mobile" class="form-control" placeholder="By Mobile" value="<?php echo isset($_GET['ex_mobile']) ? htmlspecialchars($_GET['ex_mobile']) : ''; ?>"></div>
            <div class="col-md-3"><input type="text" name="ex_institute" class="form-control" placeholder="By Institute" value="<?php echo isset($_GET['ex_institute']) ? htmlspecialchars($_GET['ex_institute']) : ''; ?>"></div>
          </div>
          <div class="row mb-3">
            <div class="col-12"><button type="submit" class="btn btn-primary">Search</button></div>
          </div>
          <?php if (isset($_GET['search_existing'])): ?>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Name</th><th>Email</th><th>Mobile</th><th>Institute Name</th><th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $ex_where = ["(school_id IS NULL OR school_id != $school_id)"];
                if (!empty($_GET['ex_name'])) $ex_where[] = "name LIKE '%" . mysqli_real_escape_string($conn, $_GET['ex_name']) . "%'";
                if (!empty($_GET['ex_email'])) $ex_where[] = "email LIKE '%" . mysqli_real_escape_string($conn, $_GET['ex_email']) . "%'";
                if (!empty($_GET['ex_mobile'])) $ex_where[] = "mobile LIKE '%" . mysqli_real_escape_string($conn, $_GET['ex_mobile']) . "%'";
                if (!empty($_GET['ex_institute'])) $ex_where[] = "institute_name LIKE '%" . mysqli_real_escape_string($conn, $_GET['ex_institute']) . "%'";
                $ex_sql = "SELECT id, name, email, mobile, institute_name FROM users WHERE " . implode(' AND ', $ex_where) . " ORDER BY name LIMIT 50";
                $ex_result = mysqli_query($conn, $ex_sql);
                if ($ex_result && mysqli_num_rows($ex_result) > 0):
                  while ($ex_user = mysqli_fetch_assoc($ex_result)):
                ?>
                  <tr>
                    <td><?php echo htmlspecialchars($ex_user['name']); ?></td>
                    <td><?php echo htmlspecialchars($ex_user['email']); ?></td>
                    <td><?php echo htmlspecialchars($ex_user['mobile']); ?></td>
                    <td><?php echo htmlspecialchars($ex_user['institute_name']); ?></td>
                    <td>
                      <!-- DEBUG: Add & Enroll form for user ID <?php echo $ex_user['id']; ?> -->
                      <form method="POST" action="school_bulk_enroll.php?workshop_id=<?php echo $workshop_id; ?>&school_id=<?php echo $school_id; ?>&email=<?php echo urlencode($email); ?>">
                        <input type="hidden" name="add_existing_user" value="1">
                        <input type="hidden" name="existing_user_id" value="<?php echo $ex_user['id']; ?>">
                        <button type="submit" class="btn btn-success btn-sm">Add & Enroll</button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                  <tr><td colspan="5" class="text-center">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="userSelectionModal" tabindex="-1" aria-labelledby="userSelectionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userSelectionModalLabel">Existing Users Found</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="ti ti-info-circle me-2"></i>
          We found existing users with the same email or mobile number. Please select an option:
        </div>
        
        <div id="singleUserSection" style="display: none;">
          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mb-0">Existing User Found</h6>
            </div>
            <div class="card-body">
              <div id="singleUserDetails"></div>
              <div class="mt-3">
                <button type="button" class="btn btn-success me-2" onclick="enrollSingleUser()">
                  <i class="ti ti-user-check me-1"></i>Enroll This User
                </button>
                <button type="button" class="btn btn-primary" onclick="createNewUser()">
                  <i class="ti ti-user-plus me-1"></i>Create New User Instead
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div id="multipleUsersSection" style="display: none;">
          <div class="card mb-3">
            <div class="card-header">
              <h6 class="mb-0">Multiple Users Found</h6>
            </div>
            <div class="card-body">
              <p class="text-muted mb-3">Please select which user you want to enroll:</p>
              <div id="multipleUsersList"></div>
              <div class="mt-3">
                <button type="button" class="btn btn-primary" onclick="createNewUser()">
                  <i class="ti ti-user-plus me-1"></i>Create New User Instead
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div id="newUserPreview" style="display: none;">
          <div class="card">
            <div class="card-header">
              <h6 class="mb-0">New User Details</h6>
            </div>
            <div class="card-body">
              <div id="newUserDetails"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="helpModalLabel">How to Use This Page</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-start">
        <ul>
          <li>Search for existing users or add new teachers to your school.</li>
          <li>Enroll teachers in the selected workshop using the checkboxes and buttons.</li>
          <li>Use the CSV upload for bulk enrollment.</li>
          <li>Download attendance data using the CSV button.</li>
          <li>Contact support if you need further help.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
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
    // Auto-show modal if there was a validation error on add
    <?php if (!empty($feedback_error) && isset($_POST['manual_add_user'])): ?>
    var addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
    addUserModal.show();
    <?php endif; ?>
    // Edit user modal logic
    document.querySelectorAll('.edit-user-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var user = JSON.parse(this.getAttribute('data-user'));
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_mobile').value = user.mobile;
            document.getElementById('edit_designation').value = user.designation;
            document.getElementById('edit_institute_name').value = user.institute_name;
            document.getElementById('edit_city').value = user.city;
        });
    });
    // Auto-show edit modal if there was a validation error on edit
    <?php if (!empty($feedback_error) && isset($_POST['edit_user'])): ?>
    var editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editUserModal.show();
    <?php endif; ?>
    // Auto-show Add Existing User modal if search_existing=1 in URL
    if (window.location.search.includes('search_existing=1')) {
        var addExistingUserModal = new bootstrap.Modal(document.getElementById('addExistingUserModal'));
        addExistingUserModal.show();
        // Remove search_existing=1 from URL when modal is closed
        document.getElementById('addExistingUserModal').addEventListener('hidden.bs.modal', function() {
            const url = new URL(window.location.href);
            url.searchParams.delete('search_existing');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        });
    }
    var expandBtn = document.getElementById('expandAttendanceBtn');
    var detailsTable = document.getElementById('attendanceDetailsTable');
    if (expandBtn && detailsTable) {
        expandBtn.addEventListener('click', function() {
            if (detailsTable.style.display === 'none' || detailsTable.style.display === '') {
                detailsTable.style.display = 'block';
                expandBtn.textContent = 'Hide Attendance Details';
            } else {
                detailsTable.style.display = 'none';
                expandBtn.textContent = 'Show Attendance Details';
            }
        });
    }
    // Copy Link Functionality
    document.querySelectorAll('.copy-link-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var link = this.getAttribute('data-link');
            var originalText = this.innerHTML;
            
            // Copy to clipboard
            navigator.clipboard.writeText(link).then(function() {
                // Show success feedback
                btn.innerHTML = '<i class="ti ti-check"></i>';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-success');
                btn.title = 'Link copied!';
                
                // Reset after 2 seconds
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                    btn.title = 'Copy joining link';
                }, 2000);
            }).catch(function(err) {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = link;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    btn.innerHTML = '<i class="ti ti-check"></i>';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-success');
                    btn.title = 'Link copied!';
                    
                    setTimeout(function() {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-secondary');
                        btn.title = 'Copy joining link';
                    }, 2000);
                } catch (err) {
                    alert('Failed to copy link. Please copy manually.');
                }
                document.body.removeChild(textArea);
            });
        });
    });

    // Developer Tools Detection and Protection
    (function() {
        var devToolsOpen = false;
        
        // Detect developer tools opening
        function detectDevTools() {
            var threshold = 160;
            var widthThreshold = window.outerWidth - window.innerWidth > threshold;
            var heightThreshold = window.outerHeight - window.innerHeight > threshold;
            
            if (widthThreshold || heightThreshold) {
                devToolsOpen = true;
                handleDevToolsOpen();
            }
        }
        
        // Handle developer tools opening
        function handleDevToolsOpen() {
            if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
                // Hide all sensitive data when dev tools are open
                document.querySelectorAll('.sensitive-data-real').forEach(function(el) {
                    el.style.display = 'none !important';
                    el.innerHTML = '••••••••••••••••••••';
                });
                
                // Show warning
                var warning = document.createElement('div');
                warning.id = 'devtools-warning';
                warning.style.cssText = 'position:fixed;top:0;left:0;right:0;background:red;color:white;padding:10px;text-align:center;z-index:9999;font-weight:bold;';
                warning.innerHTML = '⚠️ Developer Tools Detected - Sensitive Data Hidden ⚠️';
                document.body.appendChild(warning);
                
                // Remove warning after 3 seconds
                setTimeout(function() {
                    if (warning.parentNode) {
                        warning.parentNode.removeChild(warning);
                    }
                }, 3000);
            }
        }
        
        // Monitor for developer tools
        setInterval(detectDevTools, 1000);
        
        // Handle sensitive data display on hover
        if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
            document.addEventListener('DOMContentLoaded', function() {
                // Add hover event listeners to sensitive data cells
                document.querySelectorAll('.blur-sensitive').forEach(function(cell) {
                    cell.addEventListener('mouseenter', function() {
                        var realDataSpan = this.querySelector('.sensitive-data-real');
                        if (realDataSpan && realDataSpan.dataset.encrypted) {
                            try {
                                // Decode the base64 data
                                var decodedData = atob(realDataSpan.dataset.encrypted);
                                realDataSpan.textContent = decodedData;
                                realDataSpan.style.display = 'inline';
                            } catch (e) {
                                console.log('Error decoding sensitive data');
                            }
                        }
                    });
                    
                    cell.addEventListener('mouseleave', function() {
                        var realDataSpan = this.querySelector('.sensitive-data-real');
                        if (realDataSpan) {
                            realDataSpan.style.display = 'none';
                            realDataSpan.textContent = '';
                        }
                    });
                });
            });
        }
        
        // Additional detection methods
        document.addEventListener('keydown', function(e) {
            // Detect F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) ||
                (e.ctrlKey && e.key === 'U')) {
                if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
                    e.preventDefault();
                    alert('Developer tools access is restricted when editing is locked.');
                    return false;
                }
            }
        });
        
        // Detect right-click context menu
        document.addEventListener('contextmenu', function(e) {
            if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
                e.preventDefault();
                alert('Right-click is disabled when editing is locked.');
                return false;
            }
        });
        
        // Detect view source attempts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'S') {
                if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
                    e.preventDefault();
                    alert('Saving page is restricted when editing is locked.');
                    return false;
                }
            }
        });
        
        // Prevent printing when locked
        if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
            window.addEventListener('beforeprint', function(e) {
                e.preventDefault();
                alert('Printing is restricted when editing is locked.');
                return false;
            });
        }
        
        // Additional protection: Clear sensitive data when page loses focus
        if (<?php echo $is_locked ? 'true' : 'false'; ?>) {
            window.addEventListener('blur', function() {
                document.querySelectorAll('.sensitive-data-real').forEach(function(el) {
                    el.style.display = 'none';
                    el.textContent = '';
                });
            });
        }
        

    })();

    // Countdown Timer Logic
    (function() {
        var lockTimeIST = <?php echo $lock_time_ist ? ('"' . $lock_time_ist->format('Y-m-d H:i:s') . '"') : 'null'; ?>;
        var isLocked = <?php echo $is_locked ? 'true' : 'false'; ?>;
        var showTimer = <?php echo $show_timer ? 'true' : 'false'; ?>;
        var timerEl = document.getElementById('countdown-timer');
        
        function getISTNow() {
            var now = new Date();
            var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            return new Date(utc + (5.5 * 60 * 60 * 1000));
        }
        
        function updateTimer() {
            if (!lockTimeIST || !showTimer) return;
            var now = getISTNow();
            var lock = new Date(lockTimeIST.replace(/ /, 'T'));
            var diff = lock - now;
            if (diff > 0) {
                var h = Math.floor(diff / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);
                timerEl.textContent = 'Enrollment closes in ' + h + 'h ' + m + 'm ' + s + 's';
            } else {
                timerEl.textContent = 'Editing is locked';
            }
        }
        
        if (timerEl && showTimer) {
            updateTimer();
            setInterval(updateTimer, 1000);
        }
        
        if (isLocked) {
            // Hide all interactive elements when locked
            document.querySelectorAll('.user-checkbox, .edit-user-btn, .btn-success, .btn-danger, .btn-warning, .btn-primary, .csv-btn, form[action*="add_existing_user"], form[action*="manual_add_user"], form[action*="edit_user"]').forEach(function(el) {
                el.style.display = 'none';
            });
            document.querySelectorAll('[data-bs-target="#addUserModal"], [data-bs-target="#addExistingUserModal"]').forEach(function(el) {
                el.style.display = 'none';
            });
            var csvForm = document.querySelector('form[action*="upload_school_csv"]');
            if (csvForm) csvForm.style.display = 'none';
            
            // Disable form submissions
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    alert('Editing is locked. You cannot make changes at this time.');
                    return false;
                });
            });
            
                    // Add visual indicator for locked mode
        document.body.classList.add('locked-mode');
        
        // Prevent right-click on sensitive data
        document.addEventListener('contextmenu', function(e) {
            if (e.target.classList.contains('blur-sensitive')) {
                e.preventDefault();
                alert('Right-click is disabled on sensitive data when editing is locked.');
                return false;
            }
        });
        
        // Prevent keyboard shortcuts for copy/paste on sensitive data
        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('blur-sensitive') && 
                ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C' || e.key === 'v' || e.key === 'V' || e.key === 'a' || e.key === 'A'))) {
                e.preventDefault();
                alert('Copy/paste is disabled on sensitive data when editing is locked.');
                return false;
            }
        });
        
        // Prevent text selection on sensitive data
        document.addEventListener('selectstart', function(e) {
            if (e.target.classList.contains('blur-sensitive')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Disable select all checkbox when locked
        var selectAllCheckbox = document.getElementById('selectAllUsers');
        if (selectAllCheckbox) {
            selectAllCheckbox.disabled = true;
            selectAllCheckbox.checked = false;
        }
    }
})();

    // User Selection Modal Logic
    <?php if (isset($_SESSION['show_user_selection']) && $_SESSION['show_user_selection']): ?>
    (function() {
        var existingUsers = <?php echo json_encode($_SESSION['existing_users_data']['users'] ?? []); ?>;
        var newUserData = <?php echo json_encode($_SESSION['existing_users_data']['new_user_data'] ?? []); ?>;
        
        function showUserSelectionModal() {
            var modal = new bootstrap.Modal(document.getElementById('userSelectionModal'));
            
            if (existingUsers.length === 1) {
                // Single user found
                showSingleUserSection(existingUsers[0]);
            } else {
                // Multiple users found
                showMultipleUsersSection(existingUsers);
            }
            
            modal.show();
        }
        
        function showSingleUserSection(user) {
            document.getElementById('singleUserSection').style.display = 'block';
            document.getElementById('multipleUsersSection').style.display = 'none';
            document.getElementById('newUserPreview').style.display = 'none';
            
            var details = document.getElementById('singleUserDetails');
            details.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Name:</strong> ${user.name}<br>
                        <strong>Email:</strong> ${user.email}<br>
                        <strong>Mobile:</strong> ${user.mobile}
                    </div>
                    <div class="col-md-6">
                        <strong>Designation:</strong> ${user.designation || 'N/A'}<br>
                        <strong>Institute:</strong> ${user.institute_name || 'N/A'}<br>
                        <strong>City:</strong> ${user.city || 'N/A'}
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="ti ti-info-circle me-1"></i>
                        ${user.email === newUserData.email && user.mobile === newUserData.mobile ? 
                          'Both email and mobile match exactly.' : 
                          'Email or mobile matches with existing user.'}
                    </small>
                </div>
            `;
            
            // Store user ID for enrollment
            window.selectedUserId = user.id;
        }
        
        function showMultipleUsersSection(users) {
            document.getElementById('singleUserSection').style.display = 'none';
            document.getElementById('multipleUsersSection').style.display = 'block';
            document.getElementById('newUserPreview').style.display = 'none';
            
            var list = document.getElementById('multipleUsersList');
            list.innerHTML = '';
            
            users.forEach(function(user, index) {
                var userCard = document.createElement('div');
                userCard.className = 'card mb-2';
                userCard.innerHTML = `
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <strong>${user.name}</strong><br>
                                <small class="text-muted">
                                    Email: ${user.email} | Mobile: ${user.mobile}<br>
                                    ${user.designation ? 'Designation: ' + user.designation + '<br>' : ''}
                                    ${user.institute_name ? 'Institute: ' + user.institute_name : ''}
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="button" class="btn btn-success btn-sm" onclick="enrollSpecificUser(${user.id})">
                                    <i class="ti ti-user-check me-1"></i>Enroll This User
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                list.appendChild(userCard);
            });
        }
        
        function showNewUserPreview() {
            document.getElementById('singleUserSection').style.display = 'none';
            document.getElementById('multipleUsersSection').style.display = 'none';
            document.getElementById('newUserPreview').style.display = 'block';
            
            var details = document.getElementById('newUserDetails');
            details.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Name:</strong> ${newUserData.name}<br>
                        <strong>Email:</strong> ${newUserData.email}<br>
                        <strong>Mobile:</strong> ${newUserData.mobile}
                    </div>
                    <div class="col-md-6">
                        <strong>Designation:</strong> ${newUserData.designation || 'N/A'}<br>
                        <strong>Institute:</strong> ${newUserData.institute_name || 'N/A'}<br>
                        <strong>City:</strong> ${newUserData.city || 'N/A'}
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-success" onclick="submitNewUser()">
                        <i class="ti ti-user-plus me-1"></i>Create & Enroll New User
                    </button>
                </div>
            `;
        }
        
        // Global functions for button clicks
        window.enrollSingleUser = function() {
            submitUserSelection(window.selectedUserId, 'enroll');
        };
        
        window.enrollSpecificUser = function(userId) {
            submitUserSelection(userId, 'enroll');
        };
        
        window.createNewUser = function() {
            showNewUserPreview();
        };
        
        window.submitNewUser = function() {
            submitUserSelection(0, 'create_new');
        };
        
        function submitUserSelection(userId, action) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;
            
            var userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'selected_user_id';
            userIdInput.value = userId;
            form.appendChild(userIdInput);
            
            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            form.appendChild(actionInput);
            
            var selectInput = document.createElement('input');
            selectInput.type = 'hidden';
            selectInput.name = 'select_existing_user';
            selectInput.value = '1';
            form.appendChild(selectInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Show modal on page load
        showUserSelectionModal();
        
        // Clear session data when modal is closed
        document.getElementById('userSelectionModal').addEventListener('hidden.bs.modal', function() {
            // Send AJAX request to clear session data
            fetch(window.location.href + '&clear_session=1', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(function(error) {
                console.log('Session cleared');
            });
        });
        
        // Also clear session data when page is refreshed or navigated away
        window.addEventListener('beforeunload', function() {
            fetch(window.location.href + '&clear_session=1', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        });
    })();
    <?php endif; ?>
});
</script>
</body>
</html> 