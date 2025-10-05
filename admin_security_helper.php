<?php
/**
 * Security Helper for Admin Profile Corrections
 * Provides CSRF protection, input validation, and audit logging
 */

class AdminSecurityHelper {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate mobile number format
     */
    public function validateMobile($mobile) {
        // Remove all non-digit characters
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        // Check if it's 10 digits
        return strlen($mobile) === 10;
    }
    
    /**
     * Validate required fields
     */
    public function validateRequired($data, $required_fields) {
        $errors = [];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        return $errors;
    }
    
    /**
     * Log admin action for audit trail
     */
    public function logAdminAction($admin_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $sql = "INSERT INTO admin_audit_logs (admin_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $old_json = $old_values ? json_encode($old_values) : null;
        $new_json = $new_values ? json_encode($new_values) : null;
        
        mysqli_stmt_bind_param($stmt, 'ississss', 
            $admin_id, $action, $table_name, $record_id, $old_json, $new_json, $ip_address, $user_agent);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Check if admin has permission for action
     */
    public function checkPermission($admin_id, $action) {
        // Get user info
        $sql = "SELECT user_type FROM users WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if (!$user) {
            return false;
        }
        
        // Define permissions based on user_type
        $permissions = [
            'admin' => ['view', 'approve', 'reject', 'bulk_approve', 'bulk_reject', 'export', 'audit'],
            'user' => ['view'] // Regular users can only view
        ];
        
        return in_array($action, $permissions[$user['user_type']] ?? []);
    }
    
    /**
     * Rate limiting for admin actions
     */
    public function checkRateLimit($admin_id, $action, $max_attempts = 10, $time_window = 300) {
        $sql = "SELECT COUNT(*) as attempts 
                FROM admin_audit_logs 
                WHERE admin_id = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $action, $time_window);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $attempts = mysqli_fetch_assoc($result)['attempts'];
        
        return $attempts < $max_attempts;
    }
    
    /**
     * Validate request ID
     */
    public function validateRequestId($request_id) {
        $request_id = intval($request_id);
        if ($request_id <= 0) {
            return false;
        }
        
        // Check if request exists
        $sql = "SELECT id FROM profile_correction_requests WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $request_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_num_rows($result) > 0;
    }
    
    /**
     * Get request details safely
     */
    public function getRequestDetails($request_id) {
        $sql = "SELECT pcr.*, u.name as current_name, u.email as current_email, u.mobile as current_mobile, u.institute_name as current_institute
                FROM profile_correction_requests pcr 
                LEFT JOIN users u ON pcr.user_id = u.id 
                WHERE pcr.id = ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $request_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Secure update request status
     */
    public function updateRequestStatus($request_id, $status, $admin_id, $admin_notes = '') {
        // Validate status
        if (!in_array($status, ['approved', 'rejected'])) {
            return false;
        }
        
        // Get current request details for audit
        $current_request = $this->getRequestDetails($request_id);
        if (!$current_request) {
            return false;
        }
        
        // Update request
        $sql = "UPDATE profile_correction_requests SET 
                status = ?, 
                processed_by = ?, 
                processed_at = NOW(),
                admin_notes = ?
                WHERE id = ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sisi', $status, $admin_id, $admin_notes, $request_id);
        $result = mysqli_stmt_execute($stmt);
        
        if ($result) {
            // Log the action
            $this->logAdminAction($admin_id, "update_request_status", "profile_correction_requests", $request_id, 
                ['status' => $current_request['status']], ['status' => $status]);
        }
        
        return $result;
    }
    
    /**
     * Secure update user profile
     */
    public function updateUserProfile($user_id, $new_data, $admin_id) {
        // Get current user data for audit
        $current_sql = "SELECT name, email, mobile, institute_name FROM users WHERE id = ?";
        $current_stmt = mysqli_prepare($this->conn, $current_sql);
        mysqli_stmt_bind_param($current_stmt, 'i', $user_id);
        mysqli_stmt_execute($current_stmt);
        $current_result = mysqli_stmt_get_result($current_stmt);
        $current_data = mysqli_fetch_assoc($current_result);
        
        if (!$current_data) {
            return false;
        }
        
        // Update user profile
        $sql = "UPDATE users SET 
                name = ?, 
                email = ?, 
                mobile = ?, 
                institute_name = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssi', 
            $new_data['name'], $new_data['email'], $new_data['mobile'], $new_data['institute_name'], $user_id);
        
        $result = mysqli_stmt_execute($stmt);
        
        if ($result) {
            // Log the action
            $this->logAdminAction($admin_id, "update_user_profile", "users", $user_id, $current_data, $new_data);
        }
        
        return $result;
    }
    
    /**
     * Generate secure random string
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash sensitive data for logging
     */
    public function hashSensitiveData($data) {
        if (is_array($data)) {
            return array_map([$this, 'hashSensitiveData'], $data);
        }
        return hash('sha256', $data);
    }
}

// Helper functions for easy use
function generateCSRFToken() {
    $security = new AdminSecurityHelper($GLOBALS['conn']);
    return $security->generateCSRFToken();
}

function validateCSRFToken($token) {
    $security = new AdminSecurityHelper($GLOBALS['conn']);
    return $security->validateCSRFToken($token);
}

function sanitizeInput($data) {
    $security = new AdminSecurityHelper($GLOBALS['conn']);
    return $security->sanitizeInput($data);
}

function logAdminAction($admin_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $security = new AdminSecurityHelper($GLOBALS['conn']);
    return $security->logAdminAction($admin_id, $action, $table_name, $record_id, $old_values, $new_values);
}
?>
