<?php
session_start();
require '../model/db.php';
require '../model/LeaveRequestModel.php';

$leaveRequestModel = new LeaveRequestModel($db);
$user_email = $_SESSION['user']['email'] ?? '';
$leave_type = $_POST['leave_type'] ?? 'all';
$search_date = $_POST['date'] ?? '';
$search_status = $_POST['status'] ?? 'all';

// Debugging: Log the received parameters
error_log("Received: leave_type=$leave_type, date=$search_date, status=$search_status");

$filtered_requests = $leaveRequestModel->getFilteredRequests($user_email, $leave_type, $search_date, $search_status);

if (empty($filtered_requests)) {
    echo '<p class="no-requests">No requests found.</p>';
} else {
    echo '<tbody>';
    foreach ($filtered_requests as $request) {
        echo "<tr>";
        echo "<td class='" . (strtolower($request['leave_type']) === 'other' ? 'other-leave clickable' : '') . "'"
             . (strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? " data-tooltip='Click to view reason' data-reason='" . htmlspecialchars($request['reason'] ?? '') . "'" : '')
             . (strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? " onclick='showReasonPopup(this.getAttribute(\"data-reason\"))'" : '') . ">"
             . htmlspecialchars(ucfirst($request['leave_type'])) . "</td>";
        echo "<td>" . htmlspecialchars($request['start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($request['end_date']) . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst($request['status'])) . "</td>";
        echo "<td>";
        if ($request['file_path']) {
            echo "<a href='" . htmlspecialchars($request['file_path']) . "' target='_blank' class='leave-btn'>View PDF</a>";
        } else {
            echo "N/A";
        }
        echo "</td>";
        echo "<td>" . htmlspecialchars($request['updated_at'] ?? $request['created_at']) . "</td>";
        echo "</tr>";
    }
    echo '</tbody>';
}
?>