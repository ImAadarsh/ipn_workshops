<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$conn = require_once 'config/config.php';

$workshop_ids = $_GET['workshop_ids'] ?? '';
$link_id = $_GET['link_id'] ?? '';

if (empty($workshop_ids) || empty($link_id)) {
    echo '<div class="alert alert-warning">No workshops found for this link.</div>';
    exit;
}

// Get link details from instamojo_links table
$link_sql = "SELECT link_name FROM instamojo_links WHERE id = ?";
$link_stmt = mysqli_prepare($conn, $link_sql);
mysqli_stmt_bind_param($link_stmt, "i", $link_id);
mysqli_stmt_execute($link_stmt);
$link_result = mysqli_stmt_get_result($link_stmt);
$link_data = mysqli_fetch_assoc($link_result);
mysqli_stmt_close($link_stmt);

if (!$link_data) {
    echo '<div class="alert alert-warning">Link not found.</div>';
    exit;
}

$link_name = $link_data['link_name'];

// Get workshop details
$workshop_ids_array = explode(',', $workshop_ids);
$placeholders = str_repeat('?,', count($workshop_ids_array) - 1) . '?';

$workshop_sql = "SELECT id, name, start_date, trainer_name, duration, price, description 
                 FROM workshops 
                 WHERE id IN ($placeholders) 
                 ORDER BY start_date ASC";

$workshop_stmt = mysqli_prepare($conn, $workshop_sql);
mysqli_stmt_bind_param($workshop_stmt, str_repeat('i', count($workshop_ids_array)), ...$workshop_ids_array);
mysqli_stmt_execute($workshop_stmt);
$workshop_result = mysqli_stmt_get_result($workshop_stmt);
$workshops = [];
while ($row = mysqli_fetch_assoc($workshop_result)) {
    $workshops[] = $row;
}
mysqli_stmt_close($workshop_stmt);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <h6 class="alert-heading mb-2">
                    <i class="ti ti-link me-2"></i>
                    Link: <strong><?php echo htmlspecialchars($link_name); ?></strong>
                </h6>
                <p class="mb-0">This link contains <?php echo count($workshops); ?> workshop(s)</p>
            </div>
            
            <?php if (!empty($workshops)): ?>
                <div class="row">
                    <?php foreach ($workshops as $workshop): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="ti ti-book me-2"></i>
                                        <?php echo htmlspecialchars($workshop['name']); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <p class="mb-2">
                                                <strong>Date & Time:</strong><br>
                                                <i class="ti ti-calendar me-1"></i>
                                                <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                            </p>
                                            
                                            <p class="mb-2">
                                                <strong>Trainer:</strong><br>
                                                <i class="ti ti-user me-1"></i>
                                                <?php echo htmlspecialchars($workshop['trainer_name']); ?>
                                            </p>
                                            
                                            <p class="mb-2">
                                                <strong>Duration:</strong><br>
                                                <i class="ti ti-clock me-1"></i>
                                                <?php echo htmlspecialchars($workshop['duration']); ?> minutes
                                            </p>
                                            
                                            <p class="mb-2">
                                                <strong>Price:</strong><br>
                                                <i class="ti ti-currency-rupee me-1"></i>
                                                ₹<?php echo number_format($workshop['price'], 2); ?>
                                            </p>
                                            
                                            <?php if (!empty($workshop['description'])): ?>
                                                <p class="mb-0">
                                                    <strong>Description:</strong><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars(substr($workshop['description'], 0, 100)); ?>
                                                        <?php if (strlen($workshop['description']) > 100): ?>...<?php endif; ?>
                                                    </small>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary">
                                            <i class="ti ti-check me-1"></i>
                                            Included in Link
                                        </span>
                                        <small class="text-muted">
                                            ID: <?php echo $workshop['id']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-success">
                            <h6 class="alert-heading mb-2">
                                <i class="ti ti-info-circle me-2"></i>
                                Summary
                            </h6>
                            <ul class="mb-0">
                                <li>Total Workshops: <strong><?php echo count($workshops); ?></strong></li>
                                <li>Total Duration: <strong><?php echo array_sum(array_column($workshops, 'duration')); ?> minutes</strong></li>
                                <li>Total Value: <strong>₹<?php echo number_format(array_sum(array_column($workshops, 'price')), 2); ?></strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="ti ti-alert-triangle me-2"></i>
                    No workshops found for this link. The workshops may have been removed or the link is invalid.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div> 