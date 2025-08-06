<?php
session_start();
require '../model/db.php';
require '../model/LeaveRequestModel.php';
require '../model/UserModel.php'; 
$leaveRequestModel = new LeaveRequestModel($db);
$userModel = new UserModel($db);
$user_email = $_SESSION['user']['email'] ?? '';
$role = $_SESSION['role'] ?? 'employer';
$action = $_POST['action'] ?? '';
$request_id = $_POST['id'] ?? 0;
$message = '';

if (!$user_email || !in_array($role, ['employer', 'admin', 'rh'])) {
    header('Location:../view/login.php');
    exit;
}

if ($action === 'delete' && $request_id) {
    $request = $leaveRequestModel->getRequestById($request_id);
    if ($request && $request['user_email'] === $user_email) {
        if ($request['status'] === 'pending') {
            if ($leaveRequestModel->deleteRequest($request_id)) {
                $message = 'Request deleted successfully.';
            } else {
                $message = 'Failed to delete request.';
            }
        } else {
            $message = 'Cannot delete request. It is already ' . htmlspecialchars($request['status']) . '.';
        }
    } else {
        $message = 'Cannot delete request. It may not exist or does not belong to you.';
    }
    header("Location:../view/consult-requests.php?message=" . urlencode($message));
    exit;
}

if ($action === 'modify' && $request_id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST Data: " . print_r($_POST, true));
    $request = $leaveRequestModel->getRequestById($request_id);
    error_log("Request ID: $request_id, Request: " . print_r($request, true));
    if (!$request) {
        $message = 'Request not found for ID: ' . $request_id;
    } elseif ($request['user_email'] !== $user_email) {
        $message = 'Request does not belong to you.';
    } elseif ($request['status'] !== 'pending') {
        $message = 'Cannot modify request. It is already ' . htmlspecialchars($request['status']) . '.';
    } else {
        $leave_type = $_POST['leave_type'] ?? $request['leave_type'];
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $file_path = $request['file_path'] ?? null; // Keep existing file path by default
        $today = date('Y-m-d');

        if (empty($start_date) || empty($end_date)) {
            $message = 'Start date and end date are required.';
        } elseif ($start_date < $today) {
            $message = 'Start date cannot be before today (' . $today . ').';
        } elseif ($end_date < $start_date) {
            $message = 'End date must be after start date.';
        } elseif ($leaveRequestModel->hasOverlappingRequests($user_email, $start_date, $end_date, $request_id)) {
            $message = 'New dates overlap with an existing pending or approved request.';
        } else {
            // Calculate leave days for the new request
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $new_leave_days = $interval->days + 1; // Inclusive of start and end dates

            // Check total leave days (pending + approved, excluding current request)
            $total_leave_days = $leaveRequestModel->getTotalLeaveDays($user_email, $request_id);
            $remaining_leave = $userModel->getLeaveBalance($user_email);
            if ($remaining_leave === false) {
                $message = 'Failed to retrieve leave balance.';
            } elseif ($total_leave_days + $new_leave_days > $remaining_leave) {
                $message = 'Insufficient leave balance. You have ' . $remaining_leave . ' days available, but the total requested days (including pending/approved) would be ' . ($total_leave_days + $new_leave_days) . '.';
            } else {
                // Handle file upload if present
                if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['proof_file'];
                    $allowed_types = ['application/pdf'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    $upload_dir = '../Uploads/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $errors = [];
                    if (!in_array($file['type'], $allowed_types)) {
                        $errors[] = 'Only PDF files are allowed';
                    }
                    if ($file['size'] > $max_size) {
                        $errors[] = 'File size exceeds 5MB limit';
                    }

                    if (empty($errors)) {
                        $file_name = uniqid('proof_') . '_' . basename($file['name']);
                        $file_path = $upload_dir . $file_name;
                        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                            $message = 'Failed to save the uploaded file.';
                            header("Location:../view/consult-requests.php?message=" . urlencode($message));
                            exit;
                        }
                    } else {
                        $message = implode(', ', $errors);
                        header("Location:../view/consult-requests.php?message=" . urlencode($message));
                        exit;
                    }
                }

                // Update request in a transaction
                $db->beginTransaction();
                if ($leaveRequestModel->updateRequest($request_id, $start_date, $end_date, $reason, $file_path, $leave_type)) {
                    $db->commit();
                    $message = 'Request updated successfully.';
                } else {
                    $db->rollBack();
                    $message = 'Failed to update request due to a server error.';
                }
            }
        }
    }
    header("Location:../view/consult-requests.php?message=" . urlencode($message));
    exit;
}

error_log("Fallback - Action: $action, Request ID: $request_id, Method: " . $_SERVER['REQUEST_METHOD']);
header('Location:../view/consult-requests.php?message=' . urlencode('Invalid action.'));
exit;
?>