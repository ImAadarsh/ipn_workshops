<?php
// school_links.php - Admin page to generate and view school enrollment links for a single workshop
include 'config/show_errors.php';
session_start();

$conn = require_once 'config/config.php';

// Get workshop_id from URL (required)
$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
if (!$workshop_id) {
    die('<div style="color:red;">No workshop selected. Please provide ?workshop_id=...</div>');
}
$workshop_query = mysqli_query($conn, "SELECT * FROM workshops WHERE id=$workshop_id AND is_deleted=0");
if (!$workshop_query) {
    die('<div style="color:red;">Workshop query failed: ' . mysqli_error($conn) . '</div>');
}
$workshop = mysqli_fetch_assoc($workshop_query);
if (!$workshop) {
    die('<div style="color:red;">Invalid workshop.</div>');
}

// Fetch all schools
$schools = [];
$schools_result = mysqli_query($conn, "SELECT id, name, email, mobile FROM schools ORDER BY name");
if (!$schools_result) {
    die('<div style="color:red;">Schools query failed: ' . mysqli_error($conn) . '</div>');
}
while ($row = mysqli_fetch_assoc($schools_result)) {
    $schools[] = $row;
}

// Handle link generation for selected schools
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_ids']) && is_array($_POST['school_ids'])) {
    $success_count = 0;
    $already_count = 0;
    foreach ($_POST['school_ids'] as $school_id) {
        $school_id = intval($school_id);
        $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE id=$school_id");
        if (!$school_query) {
            $feedback .= "<div style='color:red;'>School query failed for ID $school_id: " . mysqli_error($conn) . "</div>";
            continue;
        }
        $school = mysqli_fetch_assoc($school_query);
        if ($school) {
            $link = "school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($school['email']);
            $exists = mysqli_query($conn, "SELECT id FROM school_links WHERE school_id=$school_id AND workshop_id=$workshop_id");
            if (!$exists) {
                $feedback .= "<div style='color:red;'>Check existing link failed for school $school_id: " . mysqli_error($conn) . "</div>";
                continue;
            }
            if (!mysqli_fetch_assoc($exists)) {
                $now = date('Y-m-d H:i:s');
                $insert = mysqli_query($conn, "INSERT INTO school_links (school_id, workshop_id, link, created_at, updated_at) VALUES ($school_id, $workshop_id, '$link', '$now', '$now')");
                if (!$insert) {
                    $feedback .= "<div style='color:red;'>Insert failed for school $school_id: " . mysqli_error($conn) . "</div>";
                } else {
                    $success_count++;
                }
            } else {
                $already_count++;
            }
        }
    }
    $feedback .= "Links generated: $success_count. Already existed: $already_count.";
}
// Fetch all links for this workshop
$links = [];
$links_result = mysqli_query($conn, "SELECT sl.*, s.name as school_name, s.email as school_email, w.name as workshop_name FROM school_links sl JOIN schools s ON sl.school_id = s.id JOIN workshops w ON sl.workshop_id = w.id WHERE sl.workshop_id = $workshop_id ORDER BY sl.created_at DESC");
if (!$links_result) {
    die('<div style="color:red;">Links query failed: ' . mysqli_error($conn) . '</div>');
}
while ($row = mysqli_fetch_assoc($links_result)) {
    $links[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>School Enrollment Links | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .copy-btn { cursor:pointer; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidenav.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    <div class="page-content">
        <div class="container py-4">
            <a href="workshop-details.php?id=<?php echo $workshop_id; ?>" class="btn btn-outline-secondary mb-3">
                <i class="ti ti-arrow-left me-1"></i> Back to Workshop Details
            </a>
            <h2 class="mb-4">Generate School Enrollment Links for <span class="text-primary"><?php echo htmlspecialchars($workshop['name']); ?></span></h2>
            <?php if ($feedback): ?><div class="alert alert-info"><?php echo $feedback; ?></div><?php endif; ?>
            <div class="mb-3">
                <input type="text" id="schoolSearch" class="form-control" placeholder="Search schools by name, email, or mobile...">
            </div>
            <form method="POST" class="mb-4">
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-bordered table-sm align-middle mb-0" id="schoolsTable">
                        <thead>
                            <tr>
                                <th style="width:40px;"><input type="checkbox" id="selectAllSchools"></th>
                                <th>School Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($schools as $s): ?>
                            <tr>
                                <td><input type="checkbox" name="school_ids[]" value="<?php echo $s['id']; ?>"></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['mobile']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Generate Links for Selected Schools</button>
                    </div>
                </div>
            </form>
            <h4 class="mb-3">All School Enrollment Links for this Workshop</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Workshop</th>
                            <th>Link</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Copy</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($link['school_name']); ?><br><small><?php echo htmlspecialchars($link['school_email']); ?></small></td>
                            <td><?php echo htmlspecialchars($link['workshop_name']); ?></td>
                            <td>
                            <a href="<?php echo 'https://workshops.ipnacademy.in/' . htmlspecialchars($link['link']); ?>" target="_blank" style="font-size:0.95em; color:#007bff; word-break:break-all;">
                                <?php echo 'https://workshops.ipnacademy.in/' . htmlspecialchars($link['link']); ?>
                            </a></td>
                            <td><?php echo htmlspecialchars($link['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($link['updated_at']); ?></td>
                            <td><button class="btn btn-sm btn-outline-secondary copy-btn" data-link="<?php echo htmlspecialchars($link['link']); ?>">Copy</button></td>
                            <td>
                                <form method="POST" action="controllers/delete_school_link.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this link?');">
                                    <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                    <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</div>
<script>
document.querySelectorAll('.copy-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        navigator.clipboard.writeText(this.getAttribute('data-link'));
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy'; }, 1200);
    });
});
// Select all schools checkbox
const selectAll = document.getElementById('selectAllSchools');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        document.querySelectorAll('input[name="school_ids[]"]').forEach(cb => {
            cb.checked = selectAll.checked;
        });
    });
}
// School search filter
document.getElementById('schoolSearch').addEventListener('keyup', function() {
    var filter = this.value.toLowerCase();
    var rows = document.querySelectorAll('#schoolsTable tbody tr');
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
});
</script>
</body>
</html> 