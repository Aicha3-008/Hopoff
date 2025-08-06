<?php
session_start();
require_once '../model/UserModel.php';
require_once '../model/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = $_SESSION['user']['email'];
$theme = isset($_POST['theme']) && in_array($_POST['theme'], ['light', 'dark']) ? $_POST['theme'] : null;

if (!$theme) {
    echo json_encode(['success' => false, 'message' => 'Invalid theme']);
    exit;
}

$userModel = new UserModel($db);
$success = $userModel->updateUserTheme($email, $theme);

if ($success) {
    $_SESSION['theme'] = $theme; // Update session for immediate effect
    echo json_encode(['success' => true, 'theme' => $theme]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update theme']);
}
?>