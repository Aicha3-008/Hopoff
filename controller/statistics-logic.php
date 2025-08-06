<?php
session_start();
require '../model/db.php';
require '../model/StatisticsModel.php';

if (!isset($_SESSION['user']['email']) || $_SESSION['role'] !== 'rh') {
    http_response_code(403);
    exit('Unauthorized access');
}

$statisticsModel = new StatisticsModel($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['export'])) {
    $leave_type = $_POST['leave_type'] ?? 'all';
    $search_date = $_POST['date'] ?? '';

    $stats = $statisticsModel->getFilteredStatistics($leave_type, $search_date);
    $leaveTypeData = $statisticsModel->getFilteredLeaveTypeDistribution($leave_type, $search_date);

    $response = [
        'table' => '',
        'leaveTypeData' => $leaveTypeData
    ];

    if (empty($stats)) {
        $response['table'] = '<tr><td colspan="2">No statistics found.</td></tr>';
    } else {
        $response['table'] .= '<tr><td>Total Employees</td><td>' . htmlspecialchars($stats['total_employees']) . '</td></tr>';
        $response['table'] .= '<tr><td>Total Approved Leave Days</td><td>' . htmlspecialchars($stats['total_leave_days']) . '</td></tr>';
        $response['table'] .= '<tr><td>Average Leave Days per Employee</td><td>' . htmlspecialchars($stats['avg_leave_days']) . '</td></tr>';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export']) && $_POST['export'] === 'csv') {
    $leave_type = $_POST['leave_type'] ?? 'all';
    $search_date = $_POST['date'] ?? '';

    $stats = $statisticsModel->getFilteredStatistics($leave_type, $search_date);
    $leaveTypeData = $statisticsModel->getFilteredLeaveTypeDistribution($leave_type, $search_date);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leave_statistics_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'w');
    // Use UTF-8 BOM to ensure proper encoding in Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Title and metadata
    fputcsv($output, ['Gestion Congés - Leave Statistics', '', '', '']);
    fputcsv($output, ['Generated on:', date('Y-m-d H:i:s'), '', '']);
    fputcsv($output, ['Filter Applied:', ($leave_type === 'all' ? 'All Types' : ucfirst($leave_type)) . ($search_date ? ' on ' . $search_date : ''), '', '']);
    fputcsv($output, ['', '', '', '']);

    // General Statistics Section with structured columns
    fputcsv($output, ['Section', 'Metric', 'Value', 'Notes']);
    fputcsv($output, ['General Statistics', 'Total Employees', $stats['total_employees'] ?? 0, '']);
    fputcsv($output, ['General Statistics', 'Total Approved Leave Days', $stats['total_leave_days'] ?? 0, '']);
    fputcsv($output, ['General Statistics', 'Average Leave Days per Employee', $stats['avg_leave_days'] ?? 0, '']);
    fputcsv($output, ['', '', '', '']);

    // Leave Type Distribution Section with structured columns
    fputcsv($output, ['Section', 'Leave Type', 'Total Days', 'Percentage']);
    foreach ($leaveTypeData as $type => $days) {
        $totalDays = array_sum($leaveTypeData);
        $percentage = $totalDays > 0 ? round(($days / $totalDays) * 100, 2) : 0;
        fputcsv($output, ['Leave Type Distribution', ucfirst($type), $days ?? 0, $percentage . '%']);
    }

    fclose($output);
    exit;
}
?>