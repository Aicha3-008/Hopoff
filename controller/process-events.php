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
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    if ($action === 'fetch') {
        $events = $eventModel->getEvents($userEmail, $role);
        echo json_encode($events);
    } elseif ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $color = $_POST['color'] ?? '#7a22de';

        if (empty($title) || empty($startDate)) {
            throw new Exception('Title and start date are required.');
        }

        if (!DateTime::createFromFormat('Y-m-d\TH:i', $startDate) || ($endDate && !DateTime::createFromFormat('Y-m-d\TH:i', $endDate))) {
            throw new Exception('Invalid date format.');
        }

        if ($eventModel->addEvent($userEmail, $title, $startDate, $endDate, $color, 0)) {
            $id = $db->lastInsertId();
            echo json_encode(['success' => 'Event added successfully', 'id' => $id]);
        } else {
            throw new Exception('Failed to add event.');
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $color = $_POST['color'] ?? '#7a22de';

        if (empty($id) || empty($title) || empty($startDate)) {
            throw new Exception('ID, title, and start date are required.');
        }

        if (!DateTime::createFromFormat('Y-m-d\TH:i', $startDate) || ($endDate && !DateTime::createFromFormat('Y-m-d\TH:i', $endDate))) {
            throw new Exception('Invalid date format.');
        }

        if ($eventModel->isFixedEvent($id)) {
            throw new Exception('Fixed holidays cannot be modified.');
        }

        if ($eventModel->isGlobalEvent($id) && $role !== 'rh') {
            throw new Exception('Only RH can modify global events.');
        }

        if ($eventModel->updateEvent($id, $userEmail, $title, $startDate, $endDate, $color, $role)) {
            echo json_encode(['success' => 'Event updated successfully']);
        } else {
            throw new Exception('Failed to update event.');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;

        if (empty($id)) {
            throw new Exception('Event ID is required.');
        }

        if ($eventModel->isFixedEvent($id)) {
            throw new Exception('Fixed holidays cannot be deleted.');
        }

        if ($eventModel->isGlobalEvent($id) && $role !== 'rh') {
            throw new Exception('Only RH can delete global events.');
        }

        if ($eventModel->deleteEvent($id, $userEmail, $role)) {
            echo json_encode(['success' => 'Event deleted successfully']);
        } else {
            throw new Exception('Failed to delete event.');
        }
    } else {
        throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;