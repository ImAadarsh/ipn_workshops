<?php
session_start();
// School Wise Joining Links page
// No session or admin login required

$workshop_id = isset($_GET['workshop_id']) ? intval($_GET['workshop_id']) : 0;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// If email is missing, prompt for it
if ($workshop_id > 0 && $school_id > 0 && empty($email)) {
    echo '<!DOCTYPE html><html><head><title>School Joining Links - Email Required</title>';
    echo '<link rel="stylesheet" href="assets/css/app.min.css">';
    echo '</head><body>';
    echo '<div class="container py-5" style="max-width: 400px;">';
    echo '<div class="card p-4">';
    echo '<h4 class="mb-3">Enter School Email</h4>';
    echo '<form method="get" action="schoolWiseJL.php">';
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

// --- DATA FETCHING FOR DISPLAY ---
$users = [];
$sql = "SELECT u.id, u.name, u.email, u.mobile, u.designation,
        (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.workshop_id = $workshop_id AND p.payment_status = 1) as enrolled
        FROM users u 
        WHERE u.school_id = $school_id 
        ORDER BY u.name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
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
    <title>Joining Links | <?php echo htmlspecialchars($school['name']); ?> | IPN Academy</title>
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
        .search-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table th {
            background: #007bff;
            color: white;
            border: none;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
        }
        .joining-link {
            max-width: 300px;
            word-break: break-all;
        }
        .copy-btn {
            margin-left: 10px;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <img src="https://ipnacademy.in/user/images/logo.png" alt="IPN Academy Logo" class="logo">
        <div class="workshop-title">Joining Links for <?php echo htmlspecialchars($workshop['name']); ?></div>
        <div class="school-name"><?php echo htmlspecialchars($school['name']); ?></div>
        <div class="header-actions">
            <button class="icon-btn" title="Help" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="ti ti-help-circle"></i></button>
        </div>
    </div>
    
    <div class="container main-content py-4">
        <div class="search-container">
            <div class="row">
                <div class="col-md-6">
                    <label for="searchInput" class="form-label"><i class="ti ti-search me-1"></i>Search by Name</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Type to search users...">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="text-muted">
                        <small>Showing <span id="resultCount"><?php echo count($users); ?></span> of <?php echo count($users); ?> users</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="ti ti-users me-1"></i> Joining Links</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usersTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">S.No.</th>
                                <th>Name</th>
                                <th>Joining Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="3" class="no-results">No users found for this school.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $index => $user): ?>
                                <tr class="user-row" data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                        <?php if ($user['enrolled']): ?>
                                            <span class="badge bg-success ms-2">Enrolled</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning ms-2">Not Enrolled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['enrolled']): ?>
                                            <?php if (
                                                !empty($workshop['meeting_id']) &&
                                                $workshop['meeting_id'] !== '#' &&
                                                strtolower($workshop['meeting_id']) !== 'null'
                                            ): ?>
                                                <div class="d-flex align-items-center">
                                                    <a href="https://meet.ipnacademy.in/?display_name=<?php echo $user['id'].'_'.urlencode($user['name']); ?>&mn=<?php echo urlencode($workshop['meeting_id']); ?>&pwd=<?php echo urlencode($workshop['passcode']); ?>&meeting_email=<?php echo urlencode($user['email']); ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-info joining-link">
                                                        <i class="ti ti-external-link me-1"></i>Join Meeting
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-secondary copy-btn copy-link-btn" 
                                                            data-link="https://meet.ipnacademy.in/?display_name=<?php echo $user['id'].'_'.urlencode($user['name']); ?>&mn=<?php echo urlencode($workshop['meeting_id']); ?>&pwd=<?php echo urlencode($workshop['passcode']); ?>&meeting_email=<?php echo urlencode($user['email']); ?>"
                                                            title="Copy joining link">
                                                        <i class="ti ti-copy"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Available Soon</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-danger">Not Enrolled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">How to Use This Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    <ul>
                        <li>Use the search box to find specific users by name.</li>
                        <li>Click "Join Meeting" to open the meeting link in a new tab.</li>
                        <li>Click the copy icon to copy the joining link to clipboard.</li>
                        <li>Only enrolled users will have active joining links.</li>
                        <li>Users marked as "Not Enrolled" need to be enrolled first.</li>
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
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const userRows = document.querySelectorAll('.user-row');
        const resultCount = document.getElementById('resultCount');
        const totalUsers = userRows.length;

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;

            userRows.forEach(function(row) {
                const userName = row.getAttribute('data-name');
                if (userName.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            resultCount.textContent = visibleCount;
            
            // Update S.No. for visible rows
            let serialNumber = 1;
            userRows.forEach(function(row) {
                if (row.style.display !== 'none') {
                    const firstCell = row.querySelector('td:first-child');
                    if (firstCell) {
                        firstCell.textContent = serialNumber;
                        serialNumber++;
                    }
                }
            });
        });

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

        // Focus search input on page load
        searchInput.focus();
    });
    </script>
</body>
</html> 

