<?php
require_once '../model/db.php';
require_once '../model/UserModel.php';

session_start();
$userModel = new UserModel($db);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // LOGIN SECTION
    if (isset($_POST['login'])) {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            error_log("Invalid email format: " . ($_POST['email'] ?? 'null'));
            $_SESSION['error'] = "Invalid email or password!";
            header("Location: ../view/login.php");
            exit();
        }
        $password = trim($_POST['password']);
        error_log("Login attempt - Email: $email");

        $user = $userModel->getUserByEmail($email);
        error_log("User retrieved: " . print_r($user, true));

        if (!$user) {
            error_log("No user found for email: $email");
            $_SESSION['error'] = "Invalid email or password!";
            header("Location: ../view/login.php");
            exit();
        }

        if (empty($user['password'])) {
            error_log("No password set for email: $email");
            $_SESSION['error'] = "Invalid email or password!";
            header("Location: ../view/login.php");
            exit();
        }

        if (!password_verify($password, $user['password'])) {
            error_log("Password verification failed for email: $email. Stored hash: " . $user['password']);
            $_SESSION['error'] = "Invalid email or password!";
            header("Location: ../view/login.php");
            exit();
        }

        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['email'] = $user['email']; 
        $_SESSION['user'] = [
            'email' => $user['email'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'role' => $user['role']
        ];
        $_SESSION['role'] = $user['role'];
        error_log("Login successful for email: $email");
        header("Location:../view/Dashboard.php"); 
        exit();
    }

    // REGISTRATION SECTION
    if (isset($_POST['register'])) {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $password = trim($_POST['password']);

        if (!$email) {
            $_SESSION['error'] = "Invalid email!";
            $_SESSION['active_form'] = 'registration-form';
            header("Location: ../view/login.php");
            exit();
        }

        // Password validation
        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $_SESSION['error'] = "Password must be at least 8 characters long, contain at least one number, and one special character!";
            $_SESSION['active_form'] = 'registration-form';
            header("Location: ../view/login.php");
            exit();
        }

        $user = $userModel->getUserByEmail($email);
        if (!$user) {
            $_SESSION['error'] = "User does not belong!";
            $_SESSION['active_form'] = 'registration-form';
            header("Location: ../view/login.php");
            exit();
        }

        if (!is_null($user['password'])) {
            $_SESSION['error'] = "Email already exists! Please login.";
            header("Location: ../view/login.php");
            exit();
        }

        if ($userModel->updatePasswordForNull($email, $password)) {
            $_SESSION['success'] = "Password set successfully! Please login.";
            header("Location: ../view/login.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to set password!";
            $_SESSION['active_form'] = 'registration-form';
            header("Location: ../view/login.php");
            exit();
        }
    }
}
?>