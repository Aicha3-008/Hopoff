<?php
class StatisticsModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getLeaveStatistics() {
        $stats = [];
        try {
            // Total employees (excluding admin and rh)
            $query = "SELECT COUNT(*) AS total FROM user WHERE role = 'employer'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['total_employees'] = $stmt->fetchColumn() ?: 0;

            // Total approved leave days
            $query = "SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS total
                      FROM leave_requests WHERE status = 'approved'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['total_leave_days'] = $stmt->fetchColumn() ?: 0;

            // Average leave days per employee
            $stats['avg_leave_days'] = $stats['total_employees'] ? round($stats['total_leave_days'] / $stats['total_employees'], 2) : 0;

            return $stats;
        } catch (PDOException $e) {
            error_log("StatisticsModel error: " . $e->getMessage());
            return [];
        }
    }

    public function getFilteredStatistics($leave_type, $search_date) {
        $stats = [];
        try {
            // Total employees (excluding admin and rh)
            $query = "SELECT COUNT(*) AS total FROM user WHERE role = 'employer'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats['total_employees'] = $stmt->fetchColumn() ?: 0;

            // Total approved leave days with filters
            $query = "SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS total
                      FROM leave_requests WHERE status = 'approved'";
            $params = [];
            if ($leave_type !== 'all') {
                $query .= " AND leave_type = ?";
                $params[] = $leave_type;
            }
            if (!empty($search_date)) {
                $search_date = date('Y-m-d', strtotime($search_date));
                $query .= " AND start_date <= ? AND end_date >= ?";
                $params[] = $search_date;
                $params[] = $search_date;
            }
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $stats['total_leave_days'] = $stmt->fetchColumn() ?: 0;

            // Average leave days per employee
            $stats['avg_leave_days'] = $stats['total_employees'] ? round($stats['total_leave_days'] / $stats['total_employees'], 2) : 0;

            return $stats;
        } catch (PDOException $e) {
            error_log("FilteredStatisticsModel error: " . $e->getMessage());
            return [];
        }
    }

    public function getLeaveTypeDistribution() {
        try {
            $query = "SELECT leave_type, SUM(DATEDIFF(end_date, start_date) + 1) AS total_days
                      FROM leave_requests
                      WHERE status = 'approved'
                      GROUP BY leave_type";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [
                'vacation' => 0,
                'medication' => 0,
                'el_hajj' => 0,
                'parental' => 0,
                'pregnancy' => 0,
                'other' => 0
            ];
            foreach ($result as $row) {
                $data[$row['leave_type']] = (int)$row['total_days'];
            }
            return $data;
        } catch (PDOException $e) {
            error_log("GetLeaveTypeDistribution error: " . $e->getMessage());
            return [];
        }
    }

    public function getFilteredLeaveTypeDistribution($leave_type, $search_date) {
        try {
            $query = "SELECT leave_type, SUM(DATEDIFF(end_date, start_date) + 1) AS total_days
                      FROM leave_requests
                      WHERE status = 'approved'";
            $params = [];
            if ($leave_type !== 'all') {
                $query .= " AND leave_type = ?";
                $params[] = $leave_type;
            }
            if (!empty($search_date)) {
                $search_date = date('Y-m-d', strtotime($search_date));
                $query .= " AND start_date <= ? AND end_date >= ?";
                $params[] = $search_date;
                $params[] = $search_date;
            }
            $query .= " GROUP BY leave_type";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [
                'vacation' => 0,
                'medication' => 0,
                'el_hajj' => 0,
                'parental' => 0,
                'pregnancy' => 0,
                'other' => 0
            ];
            foreach ($result as $row) {
                $data[$row['leave_type']] = (int)$row['total_days'];
            }
            return $data;
        } catch (PDOException $e) {
            error_log("GetFilteredLeaveTypeDistribution error: " . $e->getMessage());
            return [];
        }
    }
}
?>