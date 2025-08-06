<?php
// Start session and ensure consistency
session_start();

// Debug session state
if (!isset($_SESSION['user']['email'])) {
    error_log("Session error: user.email not set. Session ID: " . session_id() . ", Session data: " . print_r($_SESSION, true));
    header('Location: ../view/login.php?error=' . urlencode('Unauthorized access: Session expired or not set'));
    exit;
}

require '../model/db.php';
require '../model/UserModel.php';

$userModel = new UserModel($db);
$email = $_SESSION['user']['email'];

// Get the referring page or fallback to Dashboard.php
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../view/Dashboard.php';

// Validate referer to prevent open redirect
$allowedDomains = ['localhost', 'yourdomain.com']; // Replace 'yourdomain.com' with your actual domain
$refererParsed = parse_url($referer);
$refererHost = isset($refererParsed['host']) ? $refererParsed['host'] : '';
if (!in_array($refererHost, $allowedDomains) && $referer !== '../view/Dashboard.php') {
    $referer = '../view/Dashboard.php';
    error_log("Invalid referer: $refererHost. Falling back to Dashboard.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes)) {
        error_log("Invalid file type: {$file['type']} for user: $email");
        header("Location: $referer?error=" . urlencode('Invalid file type'));
        exit;
    }

    if ($file['size'] > $maxSize) {
        error_log("File size exceeds 2MB: {$file['size']} for user: $email");
        header("Location: $referer?error=" . urlencode('File size exceeds 2MB'));
        exit;
    }

    $uploadDir = '../Uploads/profile_pictures/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create directory: $uploadDir");
            header("Location: $referer?error=" . urlencode('Failed to create upload directory'));
            exit;
        }
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Update database with new profile picture path
        try {
            $userModel->updateProfilePicture($email, $filePath);
            // Update session only after successful database update
            $_SESSION['user']['profile_picture'] = $filePath;
            error_log("Profile picture uploaded successfully for user: $email, Path: $filePath");
            header("Location: $referer?success=" . urlencode('Profile picture updated successfully'));
        } catch (Exception $e) {
            error_log("Database update failed for user: $email, Error: " . $e->getMessage());
            unlink($filePath); // Clean up the uploaded file
            header("Location: $referer?error=" . urlencode('Failed to update database'));
        }
    } else {
        error_log("Failed to move uploaded file for user: $email, Temp path: {$file['tmp_name']}");
        header("Location: $referer?error=" . urlencode('Failed to upload file'));
        exit;
    }
} else {
    error_log("Invalid request: Not POST or no profile_picture file for user: $email");
    header("Location: $referer?error=" . urlencode('Invalid request'));
    exit;
}
?>