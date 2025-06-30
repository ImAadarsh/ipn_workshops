<?php
include 'config/show_errors.php';
$conn = require_once 'config/config.php';

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
if (!$workshop_id) {
    die('<div style="color:red;">No workshop selected. Please provide ?workshop_id=...</div>');
}

// Get workshop details (name, meeting_id, passcode)
$workshop = null;
$workshop_query = mysqli_query($conn, "SELECT name, meeting_id, passcode FROM workshops WHERE id=$workshop_id AND is_deleted=0");
if ($workshop_query && mysqli_num_rows($workshop_query) > 0) {
    $workshop = mysqli_fetch_assoc($workshop_query);
} else {
    die('<div style="color:red;">Invalid workshop.</div>');
}

// Fetch all enrolled users for this workshop
$users = [];
$sql = "SELECT u.id, u.name, u.email FROM users u INNER JOIN payments p ON p.user_id = u.id WHERE p.workshop_id = $workshop_id AND p.payment_status = 1 ORDER BY u.name";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $users[] = $row;
}

function get_join_link($user, $workshop) {
    $meeting_id = $workshop['meeting_id'];
    $passcode = $workshop['passcode'];
    if (!$meeting_id || $meeting_id === '#' || strtolower($meeting_id) === 'null') return false;
    $display_name = $user['id'] . '_' . urlencode($user['name']);
    $mn = urlencode($meeting_id);
    $pwd = urlencode($passcode);
    $email = urlencode($user['email']);
    return "https://meet.ipnacademy.in/?display_name=$display_name&mn=$mn&pwd=$pwd&meeting_email=$email";
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>All Joining Links | IPN Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/app.min.css">
    <link rel="icon" href="logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e9f0fa 0%, #f8f9fa 100%);
            min-height: 100vh;
            font-family: 'Inter', Arial, sans-serif;
        }
        .main-card {
            max-width: 900px;
            margin: 48px auto 32px auto;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 8px 40px #0001, 0 1.5px 8px #1976d21a;
            padding: 2.5rem 2.2rem 2.2rem 2.2rem;
            position: relative;
        }
        .logo {
            display: block;
            margin: 0 auto 1.5rem auto;
            max-width: 140px;
        }
        .header-title {
            font-size: 2.1rem;
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 0.5rem;
            text-align: center;
            letter-spacing: -1px;
        }
        .header-desc {
            font-size: 1.13rem;
            color: #607d8b;
            text-align: center;
            margin-bottom: 2.2rem;
        }
        .search-box {
            margin: 0 auto 2.2rem auto;
            max-width: 400px;
        }
        .form-control {
            font-size: 1.13em;
            padding: 0.85em 1.1em;
            border-radius: 10px;
            box-shadow: 0 1px 4px #1976d21a;
        }
        .table-container {
            background: #f7fbff;
            border-radius: 18px;
            box-shadow: 0 2px 16px #1976d21a;
            padding: 1.5rem 1.2rem;
            margin-bottom: 1.5rem;
        }
        .table {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            font-size: 1.09rem;
            margin-bottom: 0;
        }
        .table th, .table td {
            padding: 1.1em 1em;
            vertical-align: middle;
        }
        .table thead th {
            background: #e3f2fd;
            color: #1976d2;
            font-weight: 700;
            border-bottom: 2px solid #bbdefb;
        }
        .table-hover tbody tr:hover {
            background: #e3f2fd55;
            transition: background 0.2s;
        }
        .table tbody tr {
            border-bottom: 1px solid #e3e8ee;
        }
        .join-link-btn {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 7px;
            padding: 9px 22px 9px 16px;
            font-size: 1.08em;
            text-decoration: none;
            box-shadow: 0 2px 8px #1976d22a;
            margin-right: 0.7em;
            transition: background 0.18s, box-shadow 0.18s, transform 0.13s;
            cursor: pointer;
            outline: none;
            position: relative;
        }
        .join-link-btn:hover, .join-link-btn:focus {
            background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%);
            box-shadow: 0 4px 16px #1976d23a;
            transform: translateY(-2px) scale(1.03);
            color: #fff;
        }
        .join-link-btn .open-icon {
            margin-left: 8px;
            font-size: 1.1em;
            vertical-align: middle;
            display: inline-block;
        }
        .copy-btn {
            font-size: 1em;
            padding: 8px 18px 8px 14px;
            border-radius: 7px;
            border: none;
            background: #e3f2fd;
            color: #1976d2;
            cursor: pointer;
            transition: background 0.18s, color 0.18s, transform 0.13s;
            position: relative;
            vertical-align: middle;
            font-weight: 600;
        }
        .copy-btn:active, .copy-btn.copied {
            background: #1976d2;
            color: #fff;
        }
        .copy-btn .checkmark {
            display: none;
            margin-left: 6px;
            font-size: 1.1em;
            vertical-align: middle;
        }
        .copy-btn.copied .checkmark {
            display: inline;
        }
        .copy-btn .copy-text {
            vertical-align: middle;
        }
        .copy-btn[title] {
            position: relative;
        }
        .copy-btn[title]:hover:after {
            content: attr(title);
            position: absolute;
            left: 50%;
            top: -32px;
            transform: translateX(-50%);
            background: #222;
            color: #fff;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.95em;
            white-space: nowrap;
            z-index: 10;
            opacity: 0.95;
        }
        @media (max-width: 900px) {
            .main-card { padding: 1.2rem 0.2rem; }
            .table-container { padding: 1rem 0.2rem; }
        }
        @media (max-width: 600px) {
            .main-card { padding: 0.5rem 0.1rem; }
            .header-title { font-size: 1.1rem; }
            .table { font-size: 0.98em; }
            .table th, .table td { padding: 0.7em 0.5em; }
            .table-container { padding: 0.5rem 0.1rem; }
        }
        /* Responsive table scroll */
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
<div class="main-card">
    <img src="logo.svg" alt="IPN Academy Logo" class="logo">
    <div class="header-title">All Joining Links</div>
    <div class="header-desc">
        All users enrolled in <span class="text-primary fw-bold"><?php echo htmlspecialchars($workshop['name']); ?></span>
    </div>
    <div class="search-box">
        <input type="text" id="userSearch" class="form-control" placeholder="Search by name or email...">
    </div>
    <div class="table-container">
    <?php if (empty($users)): ?>
        <div class="alert alert-warning text-center">No users are enrolled in this workshop.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle table-hover" id="usersTable">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Joining Link</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user):
                $join_link = get_join_link($user, $workshop);
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <?php if ($join_link): ?>
                            <a href="<?php echo $join_link; ?>" target="_blank" class="join-link-btn" title="Open joining link">
                                Join <span class="open-icon">&#128279;</span>
                            </a>
                            <button class="copy-btn" data-link="<?php echo $join_link; ?>" title="Copy to clipboard">
                                <span class="copy-text">Copy</span>
                                <span class="checkmark">✔️</span>
                            </button>
                        <?php else: ?>
                            <span class="text-danger">Not available</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    </div>
    <div class="mt-4 text-center text-muted">
        Powered by <a href="https://ipnacademy.in/" target="_blank">IPN Academy</a>
    </div>
</div>
<script>
// User search filter
const searchInput = document.getElementById('userSearch');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        var filter = this.value.toLowerCase();
        var rows = document.querySelectorAll('#usersTable tbody tr');
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
        });
    });
}
// Copy button
const copyBtns = document.querySelectorAll('.copy-btn');
copyBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
        const link = btn.getAttribute('data-link');
        navigator.clipboard.writeText(link);
        btn.querySelector('.copy-text').textContent = 'Copied!';
        btn.classList.add('copied');
        btn.querySelector('.checkmark').style.display = 'inline';
        setTimeout(function() {
            btn.querySelector('.copy-text').textContent = 'Copy';
            btn.classList.remove('copied');
            btn.querySelector('.checkmark').style.display = 'none';
        }, 1200);
    });
});
</script>
</body>
</html> 