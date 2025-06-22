<?php
include 'config/show_errors.php';
$conn = require_once 'config/config.php';

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
if (!$workshop_id) {
    die('<div style="color:red;">No workshop selected. Please provide ?workshop_id=...</div>');
}

// Get workshop name
$workshop = null;
$workshop_query = mysqli_query($conn, "SELECT name FROM workshops WHERE id=$workshop_id AND is_deleted=0");
if ($workshop_query && mysqli_num_rows($workshop_query) > 0) {
    $workshop = mysqli_fetch_assoc($workshop_query);
} else {
    die('<div style="color:red;">Invalid workshop.</div>');
}

// Fetch all links for this workshop
$links = [];
$links_result = mysqli_query($conn, "SELECT sl.*, s.name as school_name, s.id as school_id FROM school_links sl JOIN schools s ON sl.school_id = s.id WHERE sl.workshop_id = $workshop_id ORDER BY sl.created_at DESC");
if ($links_result) {
    while ($row = mysqli_fetch_assoc($links_result)) {
        $links[] = $row;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>School Enrollment Links | IPN Academy</title>
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
            max-width: 950px;
            margin: 48px auto 32px auto;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 8px 40px #0001, 0 1.5px 8px #1976d21a;
            padding: 2.8rem 2.2rem 2.2rem 2.2rem;
            position: relative;
        }
        .logo {
            display: block;
            margin: 0 auto 1.5rem auto;
            max-width: 180px;
        }
        .header-title {
            font-size: 2.3rem;
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 0.5rem;
            text-align: center;
            letter-spacing: -1px;
        }
        .header-desc {
            font-size: 1.15rem;
            color: #607d8b;
            text-align: center;
            margin-bottom: 2.3rem;
        }
        .search-box {
            max-width: 350px;
            margin: 0 auto 2rem auto;
        }
        .table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            font-size: 1.08rem;
        }
        .table-hover tbody tr:hover {
            background: #f1f7ff;
            transition: background 0.2s;
        }
        .enroll-link-btn {
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
        .enroll-link-btn:hover, .enroll-link-btn:focus {
            background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%);
            box-shadow: 0 4px 16px #1976d23a;
            transform: translateY(-2px) scale(1.03);
            color: #fff;
        }
        .enroll-link-btn .open-icon {
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
        .school-name {
            font-weight: 600;
            color: #263238;
            letter-spacing: 0.01em;
        }
        @media (max-width: 600px) {
            .main-card { padding: 1rem 0.2rem; }
            .header-title { font-size: 1.3rem; }
            .enroll-link-btn, .copy-btn { font-size: 0.97em; padding: 6px 8px; }
        }
    </style>
</head>
<body>
<div class="main-card">
    <img src="logo.svg" alt="IPN Academy Logo" class="logo">
    <div class="header-title">School Enrollment Links</div>
    <div class="header-desc">
        All school enrollment links for <span class="text-primary fw-bold"><?php echo htmlspecialchars($workshop['name']); ?></span>
    </div>
    <div class="search-box">
        <input type="text" id="schoolSearch" class="form-control" placeholder="Search by school name...">
    </div>
    <?php if (empty($links)): ?>
        <div class="alert alert-warning text-center">No school enrollment links found for this workshop.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered align-middle table-hover" id="linksTable">
            <thead class="table-light">
                <tr>
                    <th>School Name</th>
                    <th>Enrollment Link</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($links as $link):
                $public_link = 'https://workshops.ipnacademy.in/school_bulk_enroll.php?workshop_id=' . $workshop_id . '&school_id=' . $link['school_id'];
            ?>
                <tr>
                    <td class="school-name"><?php echo htmlspecialchars($link['school_name']); ?></td>
                    <td>
                        <a href="<?php echo $public_link; ?>" target="_blank" class="enroll-link-btn" title="Open enrollment link">
                            Open Link <span class="open-icon">&#128279;</span>
                        </a>
                        <button class="copy-btn" data-link="<?php echo $public_link; ?>" title="Copy to clipboard">
                            <span class="copy-text">Copy</span>
                            <span class="checkmark">✔️</span>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="mt-4 text-center text-muted">
        Powered by <a href="https://ipnacademy.in/" target="_blank">IPN Academy</a>
    </div>
</div>
<script>
// School search filter
const searchInput = document.getElementById('schoolSearch');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        var filter = this.value.toLowerCase();
        var rows = document.querySelectorAll('#linksTable tbody tr');
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