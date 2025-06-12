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


// Check if event ID is provided
if (!isset($_GET['id'])) {
    header("Location: events.php");
    exit();
}

$event_id = (int)$_GET['id'];

// Fetch event details
$sql = "SELECT * FROM events WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    header("Location: events.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $location = $_POST['location'];
    $event_start = $_POST['date_time'];
    $link = $_POST['link'];

    $postFields = array(
        'token' => $_SESSION['token'],
        'id' => $event_id,
        'name' => $name,
        'location' => $location,
        'link' => $link,
        'event_start' => $event_start
    );

    // Add image if new one is uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $postFields['image'] = new CURLFILE($_FILES['image']['tmp_name']);
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => API_BASE_URL.'/api/insertEvent',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Authorization: Bearer ' . $_SESSION['token']
        ),
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $result = json_decode($response, true);

    if ($http_code === 200 && isset($result['success']) && $result['success']) {
        // Update local database for sync
        $image_path = isset($_FILES['image']) ? ($result['data']['image'] ?? $event['image']) : $event['image'];
        
        $sql = "UPDATE events SET 
                name = ?, location = ?, date_time = ?, 
                image = ?, link = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssi", 
            $name, $location, $event_start, $image_path, $link, $event_id
        );
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Event updated successfully";
        } else {
            $_SESSION['error'] = "Error syncing event to local database";
        }
    } else {
        $_SESSION['error'] = "Updating event: " . ($result['message'] ?? 'Unknown error');
    }

    header("Location: events.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Edit Event | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
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
                                    <li class="breadcrumb-item"><a href="events.php">Events</a></li>
                                    <li class="breadcrumb-item active">Edit Event</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Edit Event</h4>
                        </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Event Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($event['name']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo htmlspecialchars($event['location']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="date_time" class="form-label">Event Start Date & Time</label>
                                        <input type="text" class="form-control" id="date_time" name="date_time" 
                                               value="<?php echo $event['event_start']; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">Event Image</label>
                                        <?php if ($event['image']) : ?>
                                            <div class="mb-2">
                                                <img src="<?php echo $uri . htmlspecialchars($event['image']); ?>" 
                                                     alt="Current Event Image" style="max-width: 200px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Leave empty to keep current image</small>
                                        <div id="imagePreview" class="mt-2"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="link" class="form-label">Event Link</label>
                                        <input type="url" class="form-control" id="link" name="link" 
                                               value="<?php echo htmlspecialchars($event['link']); ?>" required>
                                    </div>

                                    <div class="text-end">
                                        <a href="events.php" class="btn btn-secondary me-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Update Event</button>
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
    <!-- Flatpickr js -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        // Initialize datetime picker
        flatpickr("#date_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true
        });

        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '200px';
                    img.style.height = 'auto';
                    preview.appendChild(img);
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html> 