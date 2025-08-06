<?php
ob_start();
session_start();
require '../model/db.php';
require '../model/UserModel.php';
require '../model/LeaveRequestModel.php';
require 'sendEmail.php';

// Ensure user is logged in
if (!isset($_SESSION['user']['email']) || !isset($_SESSION['role'])) {
    header('Location: login.php?error=Please log in to submit a request');
    exit;
}

// Check if the request is a POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: leave-request.php?error=Invalid request method');
    exit;
}

// Initialize models
$userModel = new UserModel($db);
$leaveRequestModel = new LeaveRequestModel($db);

// Retrieve and sanitize form data
$user_email = $_SESSION['user']['email'];
$role = $_SESSION['role'];
$leave_type = trim($_POST['leave_type'] ?? '');
$start_date = trim($_POST['start-date'] ?? '');
$end_date = trim($_POST['end-date'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$file_path = null;

// Handle file upload
if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['proof_file'];
    $allowed_types = ['application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $upload_dir = '../Uploads/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $errors = [];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file';
    }
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
            $errors[] = 'Failed to save file';
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode(', ', $errors);
        header("Location:../view/leave-request.php?error=" . urlencode($error_message));
        exit;
    }
} else {
    $errors[] = 'Proof file is required';
    header("Location:../view/leave-request.php?error=" . urlencode(implode(', ', $errors)));
    exit;
}

// Debugging
error_log("Received data: leave_type=$leave_type, start_date=$start_date, end_date=$end_date, reason=$reason, file_path=$file_path");

// Allowed leave types
$allowed_leave_types = ['parental', 'vacation', 'medication', 'pregnancy', 'el_hajj', 'other'];

// Validate inputs
$errors = [];
if (empty($leave_type) || !in_array($leave_type, $allowed_leave_types)) {
    $errors[] = 'Please select a valid leave type';
}
if (empty($start_date)) {
    $errors[] = 'Start date is required';
}
if (empty($end_date)) {
    $errors[] = 'End date is required';
}
if ($leave_type === 'other' && empty($reason)) {
    $errors[] = 'Reason is required for "Other" leave type';
}
if (!empty($start_date) && !empty($end_date)) {
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $today = new DateTime('today');

        if ($start > $end) {
            $errors[] = 'Start date must be before or equal to end date';
        }
        if ($start < $today) {
            $errors[] = 'Start date cannot be in the past';
        }
    } catch (Exception $e) {
        $errors[] = 'Invalid date format';
    }
}

// Calculate leave days
$days = 0;
if (empty($errors) && isset($start) && isset($end)) {
    $interval = $start->diff($end);
    $days = $interval->days + 1;
}

// Check leave balance
if (empty($errors)) {
    $leave_balance = $userModel->getLeaveBalance($user_email);
    if ($leave_balance === false) {
        $errors[] = 'User not found';
    } elseif ($leave_balance < $days) {
        $errors[] = 'Insufficient leave balance: ' . $leave_balance . ' days available';
    }
}

// Check for overlapping requests
if (empty($errors)) {
    if ($leaveRequestModel->hasOverlappingRequests($user_email, $start_date, $end_date)) {
        $errors[] = 'Requested dates overlap with an existing approved leave';
    }
}

// Handle errors
if (!empty($errors)) {
    $error_message = implode(', ', $errors);
    header("Location:../view/leave-request.php?error=" . urlencode($error_message));
    exit;
}

// Determine status based on role
$status = ($role === 'rh' || $role === 'responsable') ? 'approved' : 'pending';

// Insert leave request
try {
    $leaveRequestModel->insertLeaveRequest($user_email, $leave_type, $start_date, $end_date, $reason, $status, $file_path);
    $request_id = $db->lastInsertId();
    
    // Update leave balance if approved
    if ($status === 'approved') {
        $userModel->updateLeaveBalance($user_email, $days);
    }
    
    // Notify admins or responsables based on submitter role
    $submitter = $userModel->getUserByEmail($user_email);
    $submitterName = $submitter['prenom'] . ' ' . $submitter['nom'];
    if ($role === 'employer') {
        // Notify all admins
        $stmt = $db->prepare("SELECT email, prenom, nom FROM user WHERE role = 'admin'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $admin) {
            sendNewRequestEmail($admin['email'], $request_id, $submitterName, $leave_type, $start_date, $end_date);
        }
    } elseif ($role === 'admin') {
        // Notify all responsables
        $stmt = $db->prepare("SELECT email, prenom, nom FROM user WHERE role = 'responsable'");
        $stmt->execute();
        $responsables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($responsables as $responsable) {
            sendNewRequestEmail($responsable['email'], $request_id, $submitterName, $leave_type, $start_date, $end_date);
        }
    }
    
    // Redirect with success message
    $success_message = $status === 'pending' 
        ? 'Leave request submitted successfully and is pending approval' 
        : 'Leave request approved successfully';
    header("Location:../view/leave-request.php?success=" . urlencode($success_message));
    exit;
} catch (PDOException $e) {
    header('Location:../view/leave-request.php?error=Database error: ' . urlencode($e->getMessage()));
    exit;
}
?>