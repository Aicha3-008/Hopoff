<?php
class LeaveRequestModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function hasOverlappingRequests($email, $start_date, $end_date, $exclude_id = 0) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM leave_requests 
            WHERE user_email = ? 
            AND id != ? 
            AND status IN ('pending', 'approved')
            AND (
                (start_date <= ? AND end_date >= ?) 
                OR (start_date <= ? AND end_date >= ?) 
                OR (start_date >= ? AND end_date <= ?)
            )
        ");
        $stmt->execute([$email, $exclude_id, $end_date, $start_date, $end_date, $start_date, $start_date, $end_date]);
        return $stmt->fetchColumn() > 0;
    }

    public function insertLeaveRequest($email, $leave_type, $start_date, $end_date, $reason, $status, $file_path = null) {
        $stmt = $this->db->prepare("
            INSERT INTO leave_requests (user_email, leave_type, start_date, end_date, reason, status, file_path, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$email, $leave_type, $start_date, $end_date, $reason, $status, $file_path]);
    }

    public function getPendingRequestsByRole($role) {
        $stmt = $this->db->prepare("
            SELECT r.id, r.user_email, r.leave_type, r.start_date, r.end_date, r.reason, r.file_path, r.created_at, r.updated_at, u.prenom, u.nom
            FROM leave_requests r
            JOIN user u ON r.user_email = u.email
            WHERE r.status = 'pending' AND u.role = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingRequests() {
        return $this->getPendingRequestsByRole('employer');
    }

    public function getRequestById($requestId) {
        $stmt = $this->db->prepare("
            SELECT id, user_email, leave_type, start_date, end_date, reason, file_path, status, updated_at
            FROM leave_requests
            WHERE id = ?
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateRequestStatus($requestId, $status) {
        $stmt = $this->db->prepare("
            UPDATE leave_requests 
            SET status = ?
            WHERE id = ?
        ");
        return $stmt->execute([$status, $requestId]);
    }

    public function updateRequest($requestId, $start_date, $end_date, $reason, $file_path = null, $leave_type = null) {
        $stmt = $this->db->prepare("
            UPDATE leave_requests 
            SET start_date = ?, end_date = ?, reason = ?, file_path = COALESCE(?, file_path), leave_type = COALESCE(?, leave_type)
            WHERE id = ?
        ");
        return $stmt->execute([$start_date, $end_date, $reason, $file_path, $leave_type, $requestId]);
    }

    public function deleteRequest($requestId) {
        $stmt = $this->db->prepare("
            DELETE FROM leave_requests 
            WHERE id = ? AND status = 'pending'
        ");
        return $stmt->execute([$requestId]);
    }

    public function getUserRequests($email) {
        $stmt = $this->db->prepare("
            SELECT id, leave_type, start_date, end_date, reason, file_path, status, updated_at
            FROM leave_requests 
            WHERE user_email = ?
            ORDER BY start_date DESC
        ");
        $stmt->execute([$email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFilteredRequests($email, $leave_type, $search_date, $status) {
        $query = "SELECT id, leave_type, start_date, end_date, reason, file_path, status, updated_at
                  FROM leave_requests 
                  WHERE user_email = ?";
        $params = [$email];

        if ($leave_type !== 'all') {
            $query .= " AND leave_type = ?";
            $params[] = $leave_type;
        }
        if (!empty($search_date)) {
            $search_date = date('Y-m-d', strtotime($search_date)); // Normalize date
            $query .= " AND (start_date <= ? AND end_date >= ?)";
            $params[] = $search_date;
            $params[] = $search_date;
        }
        if ($status !== 'all') {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        $query .= " ORDER BY start_date DESC";
        $stmt = $this->db->prepare($query);
        try {
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getFilteredRequests: " . $e->getMessage());
            return [];
        }
    }

    public function getTotalLeaveDays($email, $exclude_id = 0) {
        $stmt = $this->db->prepare("
            SELECT start_date, end_date
            FROM leave_requests
            WHERE user_email = ? AND id != ? AND status IN ('pending', 'approved')
        ");
        $stmt->execute([$email, $exclude_id]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_days = 0;

        foreach ($requests as $request) {
            $start = new DateTime($request['start_date']);
            $end = new DateTime($request['end_date']);
            $interval = $start->diff($end);
            $total_days += $interval->days + 1; // Inclusive of start and end dates
        }

        return $total_days;
    }
}
?>