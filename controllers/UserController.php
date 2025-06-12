<?php
class UserController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getAllUsers($page = 1, $limit = 10, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause based on filters
        $where_conditions = ["u.user_type = 'user'"];
        if (!empty($filters['search'])) {
            $search = mysqli_real_escape_string($this->conn, $filters['search']);
            $where_conditions[] = "(u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.mobile LIKE '%$search%')";
        }
        if (!empty($filters['grade'])) {
            $grade = mysqli_real_escape_string($this->conn, $filters['grade']);
            $where_conditions[] = "u.grade = '$grade'";
        }
        if (!empty($filters['city'])) {
            $city = mysqli_real_escape_string($this->conn, $filters['city']);
            $where_conditions[] = "u.city = '$city'";
        }
        if (!empty($filters['school'])) {
            $school = mysqli_real_escape_string($this->conn, $filters['school']);
            $where_conditions[] = "u.school LIKE '%$school%'";
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Get total count for pagination
        $count_sql = "SELECT COUNT(DISTINCT u.id) as total FROM users u WHERE $where_clause";
        $count_result = mysqli_query($this->conn, $count_sql);
        $total_records = mysqli_fetch_assoc($count_result)['total'];
        $total_pages = ceil($total_records / $limit);

        // Get users with pagination
        $sql = "SELECT u.*, 
                COUNT(b.id) as total_bookings,
                SUM(CASE WHEN b.status = 'completed' THEN p.amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN p.status != 'completed' THEN p.amount ELSE 0 END) as amount_due,
                (COUNT(CASE WHEN b.status = 'completed' THEN 1 END) / NULLIF(COUNT(b.id), 0)) * 100 as completion_rate
                FROM users u 
                LEFT JOIN bookings b ON u.id = b.user_id 
                LEFT JOIN payments p ON b.id = p.booking_id
                WHERE $where_clause
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT $offset, $limit";

        $result = mysqli_query($this->conn, $sql);
        $users = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
        }

        return [
            'users' => $users,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'total_records' => $total_records
        ];
    }

    public function deleteUser($userId) {
        $userId = mysqli_real_escape_string($this->conn, $userId);
        $sql = "DELETE FROM users WHERE id = '$userId' AND user_type = 'user'";
        return mysqli_query($this->conn, $sql);
    }

    public function getUserById($userId) {
        $userId = mysqli_real_escape_string($this->conn, $userId);
        $sql = "SELECT * FROM users WHERE id = '$userId' AND user_type = 'user'";
        $result = mysqli_query($this->conn, $sql);
        return mysqli_fetch_assoc($result);
    }

    public function updateUser($userId, $data) {
        $userId = mysqli_real_escape_string($this->conn, $userId);
        $updates = [];
        
        $allowedFields = ['first_name', 'last_name', 'email', 'mobile', 'school', 'city', 'grade', 'about'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $value = mysqli_real_escape_string($this->conn, $data[$field]);
                $updates[] = "$field = '$value'";
            }
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = '$userId' AND user_type = 'user'";
            return mysqli_query($this->conn, $sql);
        }
        return false;
    }

    public function exportUsers($format = 'csv') {
        $sql = "SELECT u.*, 
                COUNT(b.id) as total_bookings,
                SUM(CASE WHEN b.status = 'completed' THEN p.amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN p.status != 'completed' THEN p.amount ELSE 0 END) as amount_due
                FROM users u 
                LEFT JOIN bookings b ON u.id = b.user_id 
                LEFT JOIN payments p ON b.id = p.booking_id
                WHERE u.user_type = 'user'
                GROUP BY u.id";
        
        $result = mysqli_query($this->conn, $sql);
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }

        return $users;
    }
}
?> 