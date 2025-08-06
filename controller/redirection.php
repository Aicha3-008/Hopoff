<?php
session_start();
require_once '../model/db.php';
require_once '../model/UserModel.php';
$userModel = new UserModel($db);
$email = $_SESSION['email'] ?? '';

if (empty($email) || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['error'] = "Please log in first.";
    header("Location: ../view/login.php");
    exit;
}

$user = $userModel->getUserByEmail($email);
if ($user && isset($user['role'])) {
    $role = strtolower($user['role']);
    $_SESSION['role'] = $role; // Ensure role is set in session
    switch ($role) {
        case 'employer':
            header("Location: ../view/EmployerDash.php");
            break;
        case 'responsable':
            header("Location: ../view/EmployerDash.php");
            break;
        case 'admin':
            header("Location: ../view/EmployerDash.php");
            break;
        case 'rh':
            header("Location: ../view/EmployerDash.php");
            break;
        default:
            $_SESSION['error'] = "Unknown role. Contact support.";
            header("Location: ../view/login.php");
            exit;
    }
} else {
    $_SESSION['error'] = "User role not found.";
    header("Location: ../view/login.php");
    exit;
}
exit;
?>