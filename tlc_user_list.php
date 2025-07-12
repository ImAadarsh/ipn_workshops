<?php
include 'config/show_errors.php';
session_start();

$special_access_key = '5678y3uhsc76270e9yuwqdjq9q72u1ejqiw';
$is_logged_in = isset($_SESSION['user_id']);
$is_guest_access = !$is_logged_in && isset($_GET['uvx']) && $_GET['uvx'] === $special_access_key;

if (!$is_logged_in && !$is_guest_access) {
    header("Location: index.php");
    exit();
}

$conn = require_once 'config/config.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$users = [];

switch ($type) {
    case 'total_registrations':
        $sql = "SELECT id, name, email, mobile, institute_name, city, is_tlc_new, tlc_join_date, tlc_email_sent 
                FROM users 
                WHERE tlc_2025 = 1 
                ORDER BY id DESC";
        break;
        
    case 'new_users':
        $sql = "SELECT id, name, email, mobile, institute_name, city, is_tlc_new, tlc_join_date, tlc_email_sent 
                FROM users 
                WHERE tlc_2025 = 1 AND is_tlc_new = 1 
                ORDER BY id DESC";
        break;
        
    case 'old_users':
        $sql = "SELECT id, name, email, mobile, institute_name, city, is_tlc_new, tlc_join_date, tlc_email_sent 
                FROM users 
                WHERE tlc_2025 = 1 AND (is_tlc_new = 0 OR is_tlc_new IS NULL) 
                ORDER BY id DESC";
        break;
        
    case 'attended_any_day':
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent 
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                ORDER BY u.id DESC";
        break;
        
    case 'attended_both_days':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent 
                FROM users u
                JOIN (
                    SELECT user_id 
                    FROM tlc_join_durations 
                    GROUP BY user_id 
                    HAVING COUNT(DISTINCT day) = 2
                ) both_days ON u.id = both_days.user_id
                ORDER BY u.id DESC";
        break;
        
    case 'grace_granted':
        $sql = "SELECT DISTINCT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent 
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                WHERE t.grace_grant = 1
                ORDER BY u.id DESC";
        break;
        
    case 'peak_performance':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 648
                ORDER BY total_duration DESC";
        break;
        
    case 'low_engagement':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) < 100
                ORDER BY total_duration ASC";
        break;
        
    case 'under_100_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) < 100
                ORDER BY total_duration ASC";
        break;
        
    case 'over_100_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 100
                ORDER BY total_duration DESC";
        break;
        
    case 'over_200_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 200
                ORDER BY total_duration DESC";
        break;
        
    case 'over_300_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 300
                ORDER BY total_duration DESC";
        break;
        
    case 'over_324_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 324
                ORDER BY total_duration DESC";
        break;
        
    case 'over_400_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 400
                ORDER BY total_duration DESC";
        break;
        
    case 'over_500_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 500
                ORDER BY total_duration DESC";
        break;
        
    case 'over_600_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 600
                ORDER BY total_duration DESC";
        break;
        
    case 'over_648_min':
        $sql = "SELECT u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent, 
                       SUM(t.total_duration) as total_duration
                FROM users u
                JOIN tlc_join_durations t ON u.id = t.user_id
                GROUP BY u.id, u.name, u.email, u.mobile, u.institute_name, u.city, u.is_tlc_new, u.tlc_join_date, u.tlc_email_sent
                HAVING SUM(t.total_duration) >= 648
                ORDER BY total_duration DESC";
        break;
        
    default:
        $sql = "SELECT id, name, email, mobile, institute_name, city, is_tlc_new, tlc_join_date, tlc_email_sent 
                FROM users 
                WHERE tlc_2025 = 1 
                ORDER BY id DESC";
        break;
}

$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

$total_users = count($users);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6>Total Users: <?php echo number_format($total_users); ?></h6>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportToCSV('<?php echo $type; ?>')">
        <i class="ti ti-download"></i> Export CSV
    </button>
</div>

<?php if ($total_users == 0): ?>
    <div class="alert alert-info">No users found for this criteria.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm" id="userListTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Institute</th>
                    <th>City</th>
                    <th>User Type</th>
                                         <?php if (in_array($type, ['peak_performance', 'low_engagement', 'under_100_min', 'over_100_min', 'over_200_min', 'over_300_min', 'over_324_min', 'over_400_min', 'over_500_min', 'over_600_min', 'over_648_min'])): ?>
                         <th>Total Duration</th>
                     <?php endif; ?>
                    <th>Join Date</th>
                    <th>Email Sent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($user['institute_name'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['city'] ?: 'N/A'); ?></td>
                        <td>
                            <?php if ($user['is_tlc_new'] == 1): ?>
                                <span class="badge bg-primary">New</span>
                            <?php else: ?>
                                <span class="badge bg-success">Existing</span>
                            <?php endif; ?>
                        </td>
                                                 <?php if (in_array($type, ['peak_performance', 'low_engagement', 'under_100_min', 'over_100_min', 'over_200_min', 'over_300_min', 'over_324_min', 'over_400_min', 'over_500_min', 'over_600_min', 'over_648_min'])): ?>
                             <td><strong><?php echo (int)$user['total_duration']; ?> min</strong></td>
                         <?php endif; ?>
                        <td><?php echo $user['tlc_join_date'] ? date('d M Y', strtotime($user['tlc_join_date'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($user['tlc_email_sent'] == 1): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function exportToCSV(type) {
    const url = `tlc_user_list.php?type=${type}&export=csv&uvx=<?php echo $special_access_key; ?>`;
    window.open(url, '_blank');
}
</script>

<?php
// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tlc_users_' . $type . '.csv"');
    $output = fopen('php://output', 'w');
    
    // CSV headers
    $headers = ['ID', 'Name', 'Email', 'Mobile', 'Institute', 'City', 'User Type'];
         if (in_array($type, ['peak_performance', 'low_engagement', 'under_100_min', 'over_100_min', 'over_200_min', 'over_300_min', 'over_324_min', 'over_400_min', 'over_500_min', 'over_600_min', 'over_648_min'])) {
         $headers[] = 'Total Duration (min)';
     }
    $headers[] = 'Join Date';
    $headers[] = 'Email Sent';
    fputcsv($output, $headers);
    
    // CSV data
    foreach ($users as $user) {
        $row = [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['mobile'],
            $user['institute_name'] ?: 'N/A',
            $user['city'] ?: 'N/A',
            $user['is_tlc_new'] == 1 ? 'New' : 'Existing'
        ];
        
                 if (in_array($type, ['peak_performance', 'low_engagement', 'under_100_min', 'over_100_min', 'over_200_min', 'over_300_min', 'over_324_min', 'over_400_min', 'over_500_min', 'over_600_min', 'over_648_min'])) {
             $row[] = (int)$user['total_duration'];
         }
        
        $row[] = $user['tlc_join_date'] ? date('d M Y', strtotime($user['tlc_join_date'])) : 'N/A';
        $row[] = $user['tlc_email_sent'] == 1 ? 'Yes' : 'No';
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}
?> 