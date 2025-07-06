<?php
// generate_school_links.php - Admin page to generate school enrollment links for multiple workshops
include 'config/show_errors.php';
session_start();

$conn = require_once 'config/config.php';

// Fetch all schools for dropdown
$schools = [];
$schools_result = mysqli_query($conn, "SELECT id, name, email, mobile FROM schools ORDER BY name");
if (!$schools_result) {
    die('<div style="color:red;">Schools query failed: ' . mysqli_error($conn) . '</div>');
}
while ($row = mysqli_fetch_assoc($schools_result)) {
    $schools[] = $row;
}

// Debug: Check if schools are loaded
if (empty($schools)) {
    echo '<div class="alert alert-warning">No schools found in the database.</div>';
}

// Fetch all upcoming workshops (type=0)
$workshops = [];
$workshops_result = mysqli_query($conn, "SELECT id, name, start_date, type FROM workshops WHERE type = 0 AND is_deleted = 0 AND start_date >= CURDATE() ORDER BY start_date ASC");
if (!$workshops_result) {
    die('<div style="color:red;">Workshops query failed: ' . mysqli_error($conn) . '</div>');
}
while ($row = mysqli_fetch_assoc($workshops_result)) {
    $workshops[] = $row;
}

// Handle link generation
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_id']) && isset($_POST['workshop_ids']) && is_array($_POST['workshop_ids'])) {
    $school_id = intval($_POST['school_id']);
    $workshop_ids = array_map('intval', $_POST['workshop_ids']);
    
    // Get school details
    $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE id = $school_id");
    if (!$school_query) {
        $feedback = "<div style='color:red;'>School query failed: " . mysqli_error($conn) . "</div>";
    } else {
        $school = mysqli_fetch_assoc($school_query);
        if ($school) {
            $success_count = 0;
            $already_count = 0;
            
            foreach ($workshop_ids as $workshop_id) {
                // Check if link already exists
                $exists = mysqli_query($conn, "SELECT id FROM school_links WHERE school_id = $school_id AND workshop_id = $workshop_id");
                if (!$exists) {
                    $feedback .= "<div style='color:red;'>Check existing link failed for workshop $workshop_id: " . mysqli_error($conn) . "</div>";
                    continue;
                }
                
                if (!mysqli_fetch_assoc($exists)) {
                    // Generate link
                    $link = "school_bulk_enroll.php?workshop_id=$workshop_id&school_id=$school_id&email=" . urlencode($school['email']);
                    $now = date('Y-m-d H:i:s');
                    
                    $insert = mysqli_query($conn, "INSERT INTO school_links (school_id, workshop_id, link, created_at, updated_at) VALUES ($school_id, $workshop_id, '$link', '$now', '$now')");
                    if (!$insert) {
                        $feedback .= "<div style='color:red;'>Insert failed for workshop $workshop_id: " . mysqli_error($conn) . "</div>";
                    } else {
                        $success_count++;
                    }
                } else {
                    $already_count++;
                }
            }
            
            if ($success_count > 0 || $already_count > 0) {
                $feedback .= "<div style='color:green;'>Links generated: $success_count. Already existed: $already_count.</div>";
            }
        } else {
            $feedback = "<div style='color:red;'>Invalid school selected.</div>";
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Generate School Links | IPN Academy</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .copy-btn { cursor: pointer; }
        .workshop-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 5px 0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .workshop-item:hover {
            background-color: #f8f9fa;
        }
        .workshop-item.selected {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
        .workshop-item input[type="checkbox"] {
            margin-right: 10px;
        }
        .workshop-date {
            color: #6c757d;
            font-size: 0.9em;
        }
        .selected-count {
            background-color: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include 'includes/sidenav.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    <div class="page-content">
        <div class="container py-4">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Generate School Links</li>
                            </ol>
                        </div>
                        <h4 class="page-title">Generate School Enrollment Links</h4>
                    </div>
                </div>
            </div>

            <?php if ($feedback): ?>
                <div class="alert alert-info"><?php echo $feedback; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Generate New Links</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- School Selection -->
                                <div class="mb-3">
                                    <label for="school_id" class="form-label">Select School</label>
                                    <select class="form-select" name="school_id" id="school_id" required>
                                        <option value="">Choose a school...</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>">
                                                <?php echo htmlspecialchars($school['name']); ?> 
                                                (<?php echo htmlspecialchars($school['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($schools)): ?>
                                        <small class="text-muted">No schools available</small>
                                    <?php else: ?>
                                        <small class="text-muted"><?php echo count($schools); ?> schools available</small>
                                    <?php endif; ?>
                                </div>

                                <!-- Workshop Selection -->
                                <div class="mb-3">
                                    <label class="form-label">Select Workshops <span id="selected-count" class="selected-count" style="display:none;">0</span></label>
                                    <div class="workshop-selection" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px;">
                                        <?php foreach ($workshops as $workshop): ?>
                                            <div class="workshop-item">
                                                <input type="checkbox" name="workshop_ids[]" value="<?php echo $workshop['id']; ?>" id="workshop_<?php echo $workshop['id']; ?>">
                                                <label for="workshop_<?php echo $workshop['id']; ?>" style="cursor: pointer; margin-bottom: 0;">
                                                    <strong><?php echo htmlspecialchars($workshop['name']); ?></strong>
                                                    <div class="workshop-date">
                                                        <?php 
                                                        $start_date = new DateTime($workshop['start_date']);
                                                        echo $start_date->format('d M Y');
                                                        ?>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" id="generate-btn" disabled>
                                    Generate Links
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary" id="select-all-workshops">
                                    Select All Workshops
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clear-selection">
                                    Clear Selection
                                </button>
                                <button type="button" class="btn btn-outline-info" id="select-next-month">
                                    Select Next Month Workshops
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected School Links Table -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Selected School Links</h5>
                        </div>
                        <div class="card-body">
                            <div id="selected-school-links" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Workshop</th>
                                                <th>Workshop Date</th>
                                                <th>Link</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="selected-links-tbody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="no-school-selected" class="text-center text-muted py-4">
                                <i class="ti ti-info-circle" style="font-size: 2rem;"></i>
                                <p class="mt-2">Select a school to view its enrollment links</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const schoolSelect = document.getElementById('school_id');
    const workshopCheckboxes = document.querySelectorAll('input[name="workshop_ids[]"]');
    const generateBtn = document.getElementById('generate-btn');
    const selectedCount = document.getElementById('selected-count');
    const selectAllBtn = document.getElementById('select-all-workshops');
    const clearBtn = document.getElementById('clear-selection');
    const selectNextMonthBtn = document.getElementById('select-next-month');

    // Update generate button state
    function updateGenerateButton() {
        const schoolSelected = schoolSelect.value !== '';
        const workshopsSelected = Array.from(workshopCheckboxes).some(cb => cb.checked);
        generateBtn.disabled = !(schoolSelected && workshopsSelected);
    }

    // Update selected count
    function updateSelectedCount() {
        const count = Array.from(workshopCheckboxes).filter(cb => cb.checked).length;
        if (count > 0) {
            selectedCount.textContent = count;
            selectedCount.style.display = 'inline';
        } else {
            selectedCount.style.display = 'none';
        }
    }

    // School selection change
    schoolSelect.addEventListener('change', function() {
        console.log('School selected:', this.value);
        updateGenerateButton();
        loadSchoolLinks();
    });

    // Workshop checkbox changes
    workshopCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateGenerateButton();
            updateSelectedCount();
            
            // Update visual selection
            const workshopItem = this.closest('.workshop-item');
            if (this.checked) {
                workshopItem.classList.add('selected');
            } else {
                workshopItem.classList.remove('selected');
            }
        });
    });

    // Select all workshops
    selectAllBtn.addEventListener('click', function() {
        workshopCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.closest('.workshop-item').classList.add('selected');
        });
        updateGenerateButton();
        updateSelectedCount();
    });

    // Clear selection
    clearBtn.addEventListener('click', function() {
        workshopCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.closest('.workshop-item').classList.remove('selected');
        });
        updateGenerateButton();
        updateSelectedCount();
    });

    // Select next month workshops
    selectNextMonthBtn.addEventListener('click', function() {
        const now = new Date();
        const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
        const nextMonthEnd = new Date(now.getFullYear(), now.getMonth() + 2, 0);
        
        workshopCheckboxes.forEach(cb => {
            const workshopItem = cb.closest('.workshop-item');
            const dateText = workshopItem.querySelector('.workshop-date').textContent.trim();
            const startDate = new Date(dateText);
            
            if (startDate >= nextMonth && startDate <= nextMonthEnd) {
                cb.checked = true;
                workshopItem.classList.add('selected');
            } else {
                cb.checked = false;
                workshopItem.classList.remove('selected');
            }
        });
        updateGenerateButton();
        updateSelectedCount();
    });

    // Copy link functionality
    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const link = this.getAttribute('data-link');
            navigator.clipboard.writeText('https://workshops.ipnacademy.in/' + link).then(function() {
                btn.textContent = 'Copied!';
                setTimeout(function() { 
                    btn.textContent = 'Copy'; 
                }, 1200);
            });
        });
    });

    // Load school links function
    function loadSchoolLinks() {
        const schoolId = schoolSelect.value;
        const selectedLinksDiv = document.getElementById('selected-school-links');
        const noSchoolDiv = document.getElementById('no-school-selected');
        const tbody = document.getElementById('selected-links-tbody');
        
        console.log('Loading links for school ID:', schoolId);
        console.log('selectedLinksDiv:', selectedLinksDiv);
        console.log('noSchoolDiv:', noSchoolDiv);
        console.log('tbody:', tbody);
        
        if (!schoolId) {
            selectedLinksDiv.style.display = 'none';
            noSchoolDiv.style.display = 'block';
            return;
        }
        
        // Show loading state
        selectedLinksDiv.style.display = 'block';
        noSchoolDiv.style.display = 'none';
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
        
        // Fetch links for selected school
        console.log('Fetching from:', `get_school_links.php?school_id=${schoolId}`);
        fetch(`get_school_links.php?school_id=${schoolId}`)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success && data.links.length > 0) {
                    tbody.innerHTML = data.links.map(link => `
                        <tr>
                            <td>${link.workshop_name}</td>
                            <td>${link.start_date}</td>
                            <td>
                                <a href="https://workshops.ipnacademy.in/${link.link}" 
                                   target="_blank" 
                                   style="font-size: 0.9em; color: #007bff; word-break: break-all;">
                                    https://workshops.ipnacademy.in/${link.link}
                                </a>
                            </td>
                            <td>${link.created_at}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary copy-btn me-1" 
                                        data-link="${link.link}">
                                    Copy
                                </button>
                                <form method="POST" action="controllers/delete_school_link.php" 
                                      style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this link?');">
                                    <input type="hidden" name="link_id" value="${link.id}">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    `).join('');
                    
                    // Re-attach copy functionality
                    document.querySelectorAll('.copy-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const link = this.getAttribute('data-link');
                            navigator.clipboard.writeText('https://workshops.ipnacademy.in/' + link).then(function() {
                                btn.textContent = 'Copied!';
                                setTimeout(function() { 
                                    btn.textContent = 'Copy'; 
                                }, 1200);
                            });
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No links found for this school</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading school links:', error);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading links</td></tr>';
            });
    }

    // Initialize
    updateGenerateButton();
    updateSelectedCount();
});
</script>
</body>
</html> 