<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$conn = require_once 'config/config.php';

$payment_id = $_GET['payment_id'] ?? '';

if (empty($payment_id)) {
    echo '<div class="alert alert-warning">Payment ID is required.</div>';
    exit;
}

// Get payment details from instamojo_payments table
$payment_sql = "SELECT ip.*, il.link_name, il.workshop_ids,
                 CONVERT_TZ(ip.created_at, '+00:00', '+05:30') as created_at_ist,
                 CONVERT_TZ(ip.updated_at, '+00:00', '+05:30') as updated_at_ist
                 FROM instamojo_payments ip 
                 LEFT JOIN instamojo_links il ON ip.link_id = il.id 
                 WHERE ip.id = ?";

$payment_stmt = mysqli_prepare($conn, $payment_sql);
mysqli_stmt_bind_param($payment_stmt, "i", $payment_id);
mysqli_stmt_execute($payment_stmt);
$payment_result = mysqli_stmt_get_result($payment_stmt);
$payment = mysqli_fetch_assoc($payment_result);
mysqli_stmt_close($payment_stmt);

if (!$payment) {
    echo '<div class="alert alert-warning">Payment not found.</div>';
    exit;
}

// Get user details
$user_sql = "SELECT id, name, email, mobile, institute_name, user_type FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $payment['user_id']);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

// Get workshop enrollments
$enrollment_sql = "SELECT iwe.*, w.name as workshop_name, w.start_date, w.trainer_name, w.duration, w.price,
                          p.payment_id as instamojo_payment_id, p.amount as workshop_amount, p.payment_status
                   FROM instamojo_workshop_enrollments iwe
                   JOIN workshops w ON iwe.workshop_id = w.id
                   JOIN payments p ON iwe.payment_id = p.id
                   WHERE iwe.payment_id IN (
                       SELECT id FROM payments WHERE payment_id = ?
                   )
                   ORDER BY w.start_date ASC";

$enrollment_stmt = mysqli_prepare($conn, $enrollment_sql);
mysqli_stmt_bind_param($enrollment_stmt, "s", $payment['payment_id']);
mysqli_stmt_execute($enrollment_stmt);
$enrollment_result = mysqli_stmt_get_result($enrollment_stmt);
$enrollments = [];
while ($row = mysqli_fetch_assoc($enrollment_result)) {
    $enrollments[] = $row;
}
mysqli_stmt_close($enrollment_stmt);

// Get workshop details for the link
$workshop_ids = $payment['workshop_ids'];
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
            <!-- Payment Information -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="ti ti-credit-card me-2"></i>
                        Payment Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Payment ID:</strong> <code><?php echo htmlspecialchars($payment['payment_id']); ?></code></p>
                            <p><strong>Amount:</strong> <span class="text-success fw-bold fs-5">₹<?php echo number_format($payment['amount'], 2); ?></span></p>
                            <p><strong>Currency:</strong> <?php echo htmlspecialchars($payment['currency']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge <?php 
                                    echo $payment['status'] === 'completed' ? 'bg-success' : 
                                        ($payment['status'] === 'pending' ? 'bg-warning' : 'bg-danger'); 
                                ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Link Name:</strong> <?php echo htmlspecialchars($payment['link_name']); ?></p>
                            <p><strong>Created:</strong> <?php echo date('d M Y, h:i A', strtotime($payment['created_at_ist'])); ?> (IST)</p>
                            <p><strong>Updated:</strong> <?php echo date('d M Y, h:i A', strtotime($payment['updated_at_ist'])); ?> (IST)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Buyer Information -->
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="ti ti-user me-2"></i>
                        Buyer Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($payment['buyer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['buyer_email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($payment['buyer_phone']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($user): ?>
                                <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
                                <p><strong>Institute:</strong> <?php echo htmlspecialchars($user['institute_name'] ?? 'N/A'); ?></p>
                                <p><strong>User Type:</strong> <?php echo htmlspecialchars($user['user_type'] ?? 'N/A'); ?></p>
                            <?php else: ?>
                                <p><strong>User:</strong> <span class="text-muted">Not found in users table</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workshop Enrollments -->
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="ti ti-book me-2"></i>
                        Workshop Enrollments (<?php echo count($enrollments); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($enrollments)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Workshop</th>
                                        <th>Date & Time</th>
                                        <th>Trainer</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Enrollment Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($enrollment['workshop_name']); ?></strong>
                                                <br><small class="text-muted">ID: <?php echo $enrollment['workshop_id']; ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($enrollment['start_date'])); ?>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($enrollment['start_date'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($enrollment['trainer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($enrollment['duration']); ?> min</td>
                                            <td>₹<?php echo number_format($enrollment['workshop_amount'], 2); ?></td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($enrollment['enrollment_date'])); ?>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($enrollment['enrollment_date'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $enrollment['payment_status'] == 1 ? 'bg-success' : 'bg-warning'; 
                                                ?>">
                                                    <?php echo $enrollment['payment_status'] == 1 ? 'Paid' : 'Pending'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-2"></i>
                            No workshop enrollments found for this payment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Link Workshops Summary -->
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="ti ti-link me-2"></i>
                        Link Workshops Summary (<?php echo count($workshops); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($workshops)): ?>
                        <div class="row">
                            <?php foreach ($workshops as $workshop): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0">
                                                <i class="ti ti-book me-2"></i>
                                                <?php echo htmlspecialchars($workshop['name']); ?>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-2">
                                                <strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($workshop['start_date'])); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Trainer:</strong> <?php echo htmlspecialchars($workshop['trainer_name']); ?>
                                            </p>
                                            <p class="mb-2">
                                                <strong>Duration:</strong> <?php echo htmlspecialchars($workshop['duration']); ?> minutes
                                            </p>
                                            <p class="mb-0">
                                                <strong>Price:</strong> ₹<?php echo number_format($workshop['price'], 2); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <h6 class="alert-heading mb-2">
                                <i class="ti ti-info-circle me-2"></i>
                                Summary
                            </h6>
                            <ul class="mb-0">
                                <li>Total Workshops in Link: <strong><?php echo count($workshops); ?></strong></li>
                                <li>Total Duration: <strong><?php echo array_sum(array_column($workshops, 'duration')); ?> minutes</strong></li>
                                <li>Total Value: <strong>₹<?php echo number_format(array_sum(array_column($workshops, 'price')), 2); ?></strong></li>
                                <li>Payment Amount: <strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-2"></i>
                            No workshops found for this link.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>