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

// Handle search
$search_term = trim($_GET['q'] ?? '');
$results = [];
$searched = false;
if ($search_term !== '') {
    $searched = true;
    $safe = mysqli_real_escape_string($conn, $search_term);
    $sql = "SELECT id, name, email FROM users WHERE name LIKE '%$safe%' OR email LIKE '%$safe%' ORDER BY name LIMIT 20";
    $res = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        // Check enrollment
        $uid = intval($row['id']);
        $enroll_sql = "SELECT 1 FROM payments WHERE user_id=$uid AND workshop_id=$workshop_id AND payment_status=1 LIMIT 1";
        $enroll_res = mysqli_query($conn, $enroll_sql);
        $row['enrolled'] = ($enroll_res && mysqli_num_rows($enroll_res) > 0);
        $results[] = $row;
    }
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
    <title>Find Your Joining Link | IPN Academy</title>
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
            max-width: 500px;
            margin: 48px auto 32px auto;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 8px 40px #0001, 0 1.5px 8px #1976d21a;
            padding: 2.2rem 1.5rem 2rem 1.5rem;
            position: relative;
        }
        .logo {
            display: block;
            margin: 0 auto 1.5rem auto;
            max-width: 140px;
        }
        .header-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 0.5rem;
            text-align: center;
            letter-spacing: -1px;
        }
        .header-desc {
            font-size: 1.08rem;
            color: #607d8b;
            text-align: center;
            margin-bottom: 2.1rem;
        }
        .search-box {
            margin: 0 auto 2rem auto;
            max-width: 350px;
        }
        .form-control {
            font-size: 1.1em;
            padding: 0.7em 1em;
            border-radius: 8px;
        }
        .result-card {
            background: #f7fbff;
            border-radius: 12px;
            box-shadow: 0 2px 8px #1976d21a;
            padding: 1.2em 1em 1.2em 1em;
            margin-bottom: 1.2em;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .user-name {
            font-weight: 600;
            color: #263238;
            font-size: 1.13em;
        }
        .user-email {
            color: #1976d2;
            font-size: 1em;
            margin-bottom: 0.5em;
        }
        .join-link-btn {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            padding: 7px 18px 7px 14px;
            font-size: 1em;
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
            font-size: 0.97em;
            padding: 6px 16px 6px 12px;
            border-radius: 6px;
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
        .not-enrolled {
            color: #b71c1c;
            font-weight: 500;
            margin-top: 0.5em;
        }
        @media (max-width: 600px) {
            .main-card { padding: 1rem 0.2rem; }
            .header-title { font-size: 1.1rem; }
            .join-link-btn, .copy-btn { font-size: 0.97em; padding: 6px 8px; }
        }
    </style>
</head>
<body>
<div class="main-card">
    <img src="logo.svg" alt="IPN Academy Logo" class="logo">
    <div class="header-title">Find Your Joining Link</div>
    <div class="header-desc">
        Search your name or email to get your unique joining link for <span class="text-primary fw-bold"><?php echo htmlspecialchars($workshop['name']); ?></span>
    </div>
    <div class="search-box">
        <form method="get" action="">
            <input type="hidden" name="workshop_id" value="<?php echo $workshop_id; ?>">
            <input type="text" name="q" class="form-control" placeholder="Enter your name or email..." value="<?php echo htmlspecialchars($search_term); ?>" autofocus required>
            <div class="mt-2 text-center">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
    </div>
    <?php if ($searched): ?>
        <?php if (empty($results)): ?>
            <div class="alert alert-warning text-center">No users found matching your search.</div>
        <?php else: ?>
            <?php foreach ($results as $user): ?>
                <div class="result-card">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <?php if ($user['enrolled']): ?>
                        <?php $join_link = get_join_link($user, $workshop); ?>
                        <?php if ($join_link): ?>
                            <a href="<?php echo $join_link; ?>" target="_blank" class="join-link-btn" title="Open joining link">
                                Join Meeting <span class="open-icon">&#128279;</span>
                            </a>
                            <button class="copy-btn" data-link="<?php echo $join_link; ?>" title="Copy to clipboard">
                                <span class="copy-text">Copy</span>
                                <span class="checkmark">✔️</span>
                            </button>
                        <?php else: ?>
                            <div class="not-enrolled">Joining link not available yet.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="not-enrolled">Not enrolled in this workshop.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    <div class="mt-4 text-center text-muted">
        Powered by <a href="https://ipnacademy.in/" target="_blank">IPN Academy</a>
    </div>
</div>
<script>
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