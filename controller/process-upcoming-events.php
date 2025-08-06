<?php
session_start();
require '../model/db.php';
require '../model/EventModel.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user']['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$userEmail = $_SESSION['user']['email'];
$role = $_SESSION['role'] ?? 'employer';
$eventModel = new EventModel($db);

try {
    $events = $eventModel->getUpcomingEvents($userEmail, 5, $role);
    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;