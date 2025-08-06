<?php
session_start();
require '../model/db.php';
require '../model/UserModel.php';
require '../model/EventModel.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['email']) || $_SESSION['role'] !== 'rh') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$userModel = new UserModel($db);
$eventModel = new EventModel($db);
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    if ($action === 'search') {
        $query = strtolower(trim($_GET['query'] ?? ''));
        $users = [];
        if ($query) {
            $stmt = $db->prepare("SELECT prenom, nom, email, role FROM user WHERE LOWER(prenom) LIKE ? OR LOWER(nom) LIKE ? OR LOWER(role) LIKE ?");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT prenom, nom, email, role FROM user");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $html = '';
        if ($users) {
            $html = implode('', array_map(function($user) {
                return "<tr>
                    <td>" . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . "</td>
                    <td>" . htmlspecialchars($user['email']) . "</td>
                    <td><select class='role-select' data-email='" . htmlspecialchars($user['email']) . "'>
                        <option value='admin' " . ($user['role'] === 'admin' ? 'selected' : '') . ">Admin</option>
                        <option value='responsable' " . ($user['role'] === 'responsable' ? 'selected' : '') . ">Responsable</option>
                        <option value='employer' " . ($user['role'] === 'employer' ? 'selected' : '') . ">Employer</option>
                    </select></td>
                    <td class='action-buttons'>
                        <button class='leave-btn modify' onclick='updateRole(this)'>Update</button>
                        <button class='leave-btn delete' onclick=\"deleteUser('" . htmlspecialchars($user['email']) . "')\">Delete</button>
                    </td>
                </tr>";
            }, $users));
        }
        echo json_encode(['html' => $html]);
    } elseif ($action === 'updateRole') {
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        if (!$email || !in_array($role, ['admin', 'responsable', 'employer'])) {
            throw new Exception('Invalid input: email or role missing/invalid');
        }
        if ($userModel->updateUserRole($email, $role)) {
            echo json_encode(['message' => 'Role updated successfully', 'success' => true]);
        } else {
            throw new Exception('Failed to update role in database');
        }
    } elseif ($action === 'deleteUser') {
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            throw new Exception('Invalid email');
        }
        $stmt = $db->prepare("DELETE FROM user WHERE email = ? AND role != 'rh'");
        if ($stmt->execute([$email])) {
            echo json_encode(['message' => 'User deleted successfully', 'success' => true]);
        } else {
            throw new Exception('Failed to delete user from database');
        }
    } elseif ($action === 'addEvent') {
        $title = trim($_POST['title'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        $color = $_POST['color'] ?? '#7a22de';
        $isGlobal = (int)($_POST['is_global'] ?? 0);
        if (empty($title) || empty($startDate)) {
            throw new Exception('Title and start date are required.');
        }
        if ($eventModel->addEvent($_SESSION['user']['email'], $title, $startDate, $endDate, $color, $isGlobal)) {
          
            echo json_encode(['message' => 'Event added successfully', 'success' => true]);
        } else {
            throw new Exception('Failed to add event to database');
        }
    } elseif ($action === 'addUser') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'employer');
        error_log("AddUser attempt - POST data: " . print_r($_POST, true));

        if (empty($prenom) || empty($nom) || empty($email) || !in_array($role, ['admin', 'responsable', 'employer'])) {
            throw new Exception('All fields are required and role must be valid.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format: ' . $email);
        }
        $password = null;
        error_log("Attempting to register user: $prenom $nom, email: $email, role: $role");
        if ($userModel->registerUser($prenom, $nom, $email, $password, $role)) {
            error_log("User registered successfully: $email");
            echo json_encode(['message' => 'User added successfully. User must set password during registration.', 'success' => true]);
        } else {
            error_log("RegisterUser failed for email: $email");
            throw new Exception('Failed to add user; email may already exist or database error occurred.');
        }
    } elseif ($action === 'debug') {
        $error = $_POST['error'] ?? 'No error message';
        error_log("Debug: Client reported error - $error");
        echo json_encode(['debug' => 'Logged error on server']);
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    $errorMsg = "Users-logic error: " . $e->getMessage();
    error_log($errorMsg);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;