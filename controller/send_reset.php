<?php
require_once '../model/db.php';
require_once '../model/UserModel.php';
require_once '../model/mail_config.php';
if (!file_exists('../vendor/autoload.php')) {
    die('Autoload file not found. Please run "composer install" or "composer require phpmailer/phpmailer".');
}
require '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    $_SESSION['error'] = "Invalid email address.";
    header("Location: ../view/login.php");
    exit;
}

$userModel = new UserModel($db);
if ($userModel) {
    $user = $userModel->getUserByEmail($email);

    if ($user) {
        $token = bin2hex(random_bytes(16));
        $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour
        if ($userModel->storeResetToken($email, $token, $expiry)) {
            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/gestionConge/view/reset_password.php?token=$token";

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port = MAIL_PORT;

            $mail->setFrom(MAIL_FROM, MAIL_NAME);
            $mail->addAddress($email);
            $mail->Subject = "Reset Your Password";
            $mail->Body = "Click here to reset your password: $resetLink";

            try {
                $mail->send();
                $_SESSION['success'] = "Email sent! Check your inbox.";
            } catch (Exception $e) {
                error_log("[" . date('Y-m-d H:i:s') . "] Failed to send reset email for $email: " . $mail->ErrorInfo);
                $_SESSION['error'] = "Failed to send reset link. Please try again later.";
            }
        } else {
            $_SESSION['error'] = "Failed to store reset token.";
        }
    } else {
        $_SESSION['error'] = "Email not found.";
    }
} else {
    $_SESSION['error'] = "Database connection failed.";
}

header("Location: ../view/login.php");
exit;
?>