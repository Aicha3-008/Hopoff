<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user']['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Initialize hiddenRequests in session if not set
if (!isset($_SESSION['hiddenRequests'])) {
    $_SESSION['hiddenRequests'] = [];
}

// Handle adding a hidden request ID
if (isset($_POST['action']) && $_POST['action'] === 'add' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if (!in_array($id, $_SESSION['hiddenRequests'])) {
        $_SESSION['hiddenRequests'][] = $id;
    }
    echo json_encode(['success' => true, 'hiddenRequests' => $_SESSION['hiddenRequests']]);
    exit;
}

// Handle fetching hidden requests
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    echo json_encode(['hiddenRequests' => $_SESSION['hiddenRequests']]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>