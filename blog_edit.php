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

// Check if blog ID is provided
if (!isset($_GET['id'])) {
    header("Location: blogs.php");
    exit();
}

$blog_id = (int)$_GET['id'];

// Fetch blog details
$sql = "SELECT * FROM blogs WHERE id = ? AND is_deleted = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $blog_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$blog = mysqli_fetch_assoc($result);

if (!$blog) {
    header("Location: blogs.php");
    exit();
}

// Fetch categories for dropdown
$sql = "SELECT * FROM blog_categories ORDER BY name";
$categories = mysqli_query($conn, $sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $content = $_POST['content'];
    $category_id = (int)$_POST['category_id'];
    $author_name = $_POST['author_name'];
    $quote = $_POST['quote'];
    $tags = $_POST['tags'];

    // Prepare curl request
    $curl = curl_init();
    
    $postFields = array(
        'token' => $_SESSION['token'],
        'title' => $title,
        'subtitle' => $subtitle,
        'content' => $content,
        'category_id' => $category_id,
        'author_name' => $author_name,
        'quote' => $quote,
        'tags' => $tags,
        'id' => $blog_id,
        'blog_id' => $blog_id
    );
    
    // Handle file uploads
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] === 0) {
        $postFields['icon'] = new CURLFile($_FILES['icon']['tmp_name'], $_FILES['icon']['type'], $_FILES['icon']['name']);
    }
    
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === 0) {
        $postFields['banner'] = new CURLFile($_FILES['banner']['tmp_name'], $_FILES['banner']['type'], $_FILES['banner']['name']);
    }

    curl_setopt_array($curl, array(
        CURLOPT_URL => API_BASE_URL . '/api/insertBlog',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postFields,
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if ($err) {
        $error = "Error updating blog: " . $err;
    } else if ($http_code != 200 && $http_code != 201) {
        // 201 is "Created" - a success response for resource creation
        $error = "HTTP Error: " . $http_code . " - Response: " . $response;
    } else {
        // For debugging
        // Uncomment the next line to see the raw response
        // $error = "API Response: " . $response;
        
        $responseData = json_decode($response, true);
        if (!$responseData) {
            $error = "JSON parsing error. Raw response: " . substr($response, 0, 200) . "...";
        } else if (isset($responseData['status']) && $responseData['status']) {
            header("Location: blogs.php");
            exit();
        } else {
            $message = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
            $error = "Error updating blog: " . $message;
            // Uncomment for more details about the response
            // $error .= "<br>Full response: " . json_encode($responseData);
        }
    }
    
    curl_close($curl);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Edit Blog | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <!-- Include CKEditor -->
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
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
                                    <li class="breadcrumb-item"><a href="blogs.php">Blogs</a></li>
                                    <li class="breadcrumb-item active">Edit Blog</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Edit Blog</h4>
                        </div>
                    </div>
                </div>

                <?php if (isset($error)) : ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title</label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="subtitle" class="form-label">Subtitle</label>
                                        <textarea class="form-control" id="subtitle" name="subtitle" rows="2" required><?php echo htmlspecialchars($blog['subtitle']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="content" class="form-label">Content</label>
                                        <textarea class="form-control" id="content" name="content" rows="10" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select  data-choices data-choices-sorting-false class="form-control" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php mysqli_data_seek($categories, 0); ?>
                                            <?php while ($category = mysqli_fetch_assoc($categories)) : ?>
                                                <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $blog['category_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="author_name" class="form-label">Author Name</label>
                                        <input type="text" class="form-control" id="author_name" name="author_name" value="<?php echo htmlspecialchars($blog['author_name']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="icon" class="form-label">Icon Image</label>
                                        <?php if ($blog['icon']) : ?>
                                            <div class="mb-2">
                                                <img src="<?php echo htmlspecialchars($uri.$blog['icon']); ?>" alt="Current Icon" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="icon" name="icon" accept="image/*">
                                    </div>

                                    <div class="mb-3">
                                        <label for="banner" class="form-label">Banner Image</label>
                                        <?php if ($blog['banner']) : ?>
                                            <div class="mb-2">
                                                <img src="<?php echo htmlspecialchars($uri.$blog['banner']); ?>" alt="Current Banner" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="banner" name="banner" accept="image/*">
                                    </div>

                                    <div class="mb-3">
                                        <label for="quote" class="form-label">Quote</label>
                                        <textarea class="form-control" id="quote" name="quote" rows="2"><?php echo htmlspecialchars($blog['quote']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tags" class="form-label">Tags (comma separated)</label>
                                        <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($blog['tags']); ?>" required>
                                    </div>

                                    <div class="text-end">
                                        <a href="blogs.php" class="btn btn-secondary me-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Update Blog</button>
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

    <script>
        CKEDITOR.replace('content');
    </script>
</body>
</html> 