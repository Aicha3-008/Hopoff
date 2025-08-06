<?php
session_start();
require '../model/db.php';
require '../model/LeaveRequestModel.php';
require '../model/UserModel.php';
require 'sendEmail.php';

// Ensure user is logged in and is either admin or responsable
if (!isset($_SESSION['user']['email']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'responsable')) {
    header('Location: login.php?error=Unauthorized access');
    exit;
}

$leaveRequestModel = new LeaveRequestModel($db);
$userModel = new UserModel($db);
$currentRole = $_SESSION['role'];
$currentUserEmail = $_SESSION['user']['email'];
$currentUser = $userModel->getUserByEmail($currentUserEmail);
$currentUserName = $currentUser['prenom'] . ' ' . $currentUser['nom'];

// Check if approval/rejection parameters are present
$requestId = $_POST['request_id'] ?? null;
$action = $_POST['action'] ?? null;

// Check if filter parameters are present
$user_name = $_POST['user_name'] ?? null;
$leave_type = $_POST['leave_type'] ?? null;
$search_date = $_POST['date'] ?? null;

if ($requestId && $action) {
    $request = $leaveRequestModel->getRequestById($requestId);
    if ($request) {
        $submitterRole = $userModel->getUserRole($request['user_email']);
        $isValidRequest = false;
        if ($currentRole === 'admin' && $submitterRole === 'employer' && $currentUserEmail !== $request['user_email']) {
            $isValidRequest = true;
        } elseif ($currentRole === 'responsable' && $submitterRole === 'admin' && $currentUserEmail !== $request['user_email']) {
            $isValidRequest = true;
        }

        if ($isValidRequest) {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $success = $leaveRequestModel->updateRequestStatus($requestId, $newStatus);

            if ($success) {
                if ($newStatus === 'approved') {
                    $startDate = new DateTime($request['start_date']);
                    $endDate = new DateTime($request['end_date']);
                    $days = $startDate->diff($endDate)->days + 1;
                    $userModel->updateLeaveBalance($request['user_email'], $days);
                }
                // Notify the submitter
                sendRequestStatusEmail($request['user_email'], $requestId, $newStatus, $currentUserName);
                
                $message = "Leave request " . ($newStatus === 'approved' ? 'approved' : 'rejected') . " successfully.";
                header("Location: ../view/admin-approval.php?success=" . urlencode($message));
            } else {
                $message = "Failed to " . ($newStatus === 'approved' ? 'approve' : 'reject') . " the leave request.";
                header("Location: ../view/admin-approval.php?error=" . urlencode($message));
            }
        } else {
            header("Location: ../view/admin-approval.php?error=You are not authorized to process this request.");
        }
    } else {
        header("Location: ../view/admin-approval.php?error=Invalid request ID.");
    }
} elseif ($user_name !== null || $leave_type !== null || $search_date !== null) {
    // Handle filtering logic
    if ($currentRole === 'admin') {
        $requests = $leaveRequestModel->getPendingRequestsByRole('employer');
    } elseif ($currentRole === 'responsable') {
        $requests = $leaveRequestModel->getPendingRequestsByRole('admin');
    } else {
        $requests = [];
    }

    $filtered_requests = [];

    foreach ($requests as $request) {
        $full_name = strtolower($request['prenom'] . ' ' . $request['nom']);
        $matches_name = empty($user_name) || strpos($full_name, strtolower($user_name)) !== false;
        $matches_leave_type = $leave_type === 'all' || $request['leave_type'] === $leave_type;
        $matches_date = empty($search_date) || ($request['start_date'] <= $search_date && $request['end_date'] >= $search_date);

        if ($matches_name && $matches_leave_type && $matches_date) {
            $filtered_requests[] = $request;
        }
    }

    foreach ($filtered_requests as $request) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($request['prenom'] . ' ' . $request['nom']) . "</td>";
        echo "<td class='" . (strtolower($request['leave_type']) === 'other' ? 'other-leave clickable' : '') . "' "
             . (strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? "data-tooltip='Click to view reason' data-reason='" . htmlspecialchars($request['reason'] ?? '') . "'" : '') . "' "
             . (strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? "onclick='showReasonPopup(this.getAttribute(\"data-reason\"))'" : '') . ">"
             . htmlspecialchars(ucfirst($request['leave_type'])) . "</td>";
        echo "<td>" . htmlspecialchars($request['start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($request['end_date']) . "</td>";
        echo "<td>" . htmlspecialchars($request['reason'] ?? 'N/A') . "</td>";
        echo "<td>";
        if ($request['file_path']) {
            echo "<a href='" . htmlspecialchars($request['file_path']) . "' target='_blank' class='leave-btn'>View PDF</a>";
        } else {
            echo "N/A";
        }
        echo "</td>";
        echo "<td class='action-buttons'>";
        echo "<form action='process-admin-approval.php' method='POST' style='display:inline;'>";
        echo "<input type='hidden' name='request_id' value='" . htmlspecialchars($request['id']) . "'>";
        echo "<button type='submit' name='action' value='approve' class='leave-btn approve'>Approve</button>";
        echo "</form>";
        echo "<form action='process-admin-approval.php' method='POST' style='display:inline;'>";
        echo "<input type='hidden' name='request_id' value='" . htmlspecialchars($request['id']) . "'>";
        echo "<button type='submit' name='action' value='reject' class='leave-btn reject'>Reject</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    header("Location: ../view/admin-approval.php?error=Invalid action or request ID.");
}
exit;
?>