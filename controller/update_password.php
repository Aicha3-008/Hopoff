<?php
require_once '../model/db.php';
require_once '../model/UserModel.php';

session_start();
$userModel = new UserModel($db);
$token = $_POST['token'] ?? '';
$newPassword = trim($_POST['new_password']);

if (empty($token) || empty($newPassword)) {
    $_SESSION['error'] = "Invalid request.";
} else {
    $user = $userModel->getUserByToken($token);
    if ($user) {
        if (strlen($newPassword) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($userModel->updatePassword($user['email'], $hashedPassword)) {
                $_SESSION['success'] = "Password updated successfully! You can now log in.";
            } else {
                $_SESSION['error'] = "Failed to update password.";
            }
        }
    } else {
        $_SESSION['error'] = "Invalid or expired token.";
    }
}

header("Location: ../view/login.php");
exit;
?>