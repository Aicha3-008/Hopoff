<?php
session_start();
require '../model/db.php';
require '../model/LeaveRequestModel.php';
require '../model/UserModel.php';

// Ensure user is logged in and is admin or responsable
if (!isset($_SESSION['user']['email']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'responsable')) {
    header('Location: login.php?error=Unauthorized access');
    exit;
}

$leaveRequestModel = new LeaveRequestModel($db);
$currentRole = $_SESSION['role'];
$userModel = new UserModel($db);
$theme = $userModel->getUserTheme($_SESSION['user']['email']);
$_SESSION['theme'] = $theme;
$userData = $userModel->getUserByEmail($_SESSION['user']['email']);
if ($userData) {
    $_SESSION['user']['profile_picture'] = $userData['profile_picture'] ?? 'art.jpeg';
    $_SESSION['user']['prenom'] = $userData['prenom'];
    $_SESSION['user']['nom'] = $userData['nom'];
}
// Filter requests based on role
if ($currentRole === 'admin') {
    $pendingRequests = $leaveRequestModel->getPendingRequestsByRole('employer');
} elseif ($currentRole === 'responsable') {
    $pendingRequests = $leaveRequestModel->getPendingRequestsByRole('admin');
} else {
    $pendingRequests = [];
    error_log("Unexpected role: " . $currentRole);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Approval</title>
    <link rel="stylesheet" href="dash.css">
</head>
<body class="<?php echo htmlspecialchars($theme); ?>">
    <nav class="sidebar close">
        <header>
            <div class="image-text">
                <span class="image">
                    <img src="art.jpeg" alt="logo">
                </span>
                <div class="text header-text">
                    <span class="name">Gestion Congés</span>
                    <span class="profession">Admin</span>
                </div>
            </div>
            <ion-icon name="chevron-forward-outline" class="toggle"></ion-icon>
        </header>
        <div class="menu-bar">
            <div class="menu">
                <ul class="menu-links">
                    <li class="nav-link">
                        <a href="Dashboard.php">
                            <ion-icon name="home-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-link">
                        <a href="leave-request.php">
                            <ion-icon name="duplicate-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Submit a request</span>
                        </a>
                    </li>
                    <li class="nav-link active">
                        <a href="admin-approval.php">
                            <ion-icon name="grid-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Approve Requests</span>
                        </a>
                    </li>
                    <li class="nav-link">
                        <a href="consult-requests.php">
                            <ion-icon name="albums-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Consult request</span>
                        </a>
                    </li>
                    <li class="nav-link">
                        <a href="history.php">
                            <ion-icon name="calendar-outline" class="icon"></ion-icon>
                            <span class="text nav-text">History</span>
                        </a>
                    </li>
                <li>
                    <a href="../controller/logout.php">
                        <ion-icon name="log-out-outline" class="icon"></ion-icon>
                        <span class="text nav-text">Logout</span>
                    </a>
                </li>
                <li class="mode">
                    <label class="mode-toggle">
                        <input type="checkbox" class="mode-checkbox" <?php echo $theme === 'dark' ? 'checked' : ''; ?> hidden>
                        <span class="switch">
                            <ion-icon name="<?php echo $theme === 'dark' ? 'sunny-outline' : 'moon-outline'; ?>" class="mode-icon"></ion-icon>
                        </span>
                    </label>
                    <span class="text nav-text"><?php echo $theme === 'dark' ? 'Light Mode' : 'Dark Mode'; ?></span>
                </li>
            </ul>
            </div>
        </div>
    </nav>
     <div class="popup-overlay" id="profile-modal">
        <div class="popup-content">
            <h2 class="form-title">Change Profile Picture</h2>
            <form id="profile-form" enctype="multipart/form-data" action="upload-profile-picture.php" method="POST">
                <div class="input-box">
                    <input type="file" id="profile-picture" name="profile_picture" accept="image/jpeg,image/png,image/gif" required>
                    <label>Profile Picture</label>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="leave-btn">Upload</button>
                    <button type="button" class="leave-btn" onclick="closeProfileModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <section class="home">
        <div class="profile-section">
            <div class="profile-content">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></span>
                <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_picture'] ?? 'art.jpeg'); ?>" alt="Profile Picture" class="profile-img clickable" onclick="showProfilePopup()">
            </div>
        </div>
        <div class="consult-requests">
            <h2 class="form-title">Pending Leave Requests</h2>
            <div class="search-zone">
                <div class="search-options">
                    <input type="text" id="search-name" class="search-input" placeholder="Enter user name">
                    <select id="search-type" class="search-select">
                        <option value="all">All Leave Types</option>
                        <option value="vacation">Vacation</option>
                        <option value="medication">Medication</option>
                        <option value="el_hajj">El Hajj</option>
                        <option value="parental">Parental</option>
                        <option value="pregnancy">Pregnancy</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="date" id="search-date" class="search-date">
                    <button class="leave-btn" onclick="filterRequests()">Search</button>
                </div>
            </div>
            <?php if (isset($_GET['success'])): ?>
                <p class="message success" id="success-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
            <?php elseif (isset($_GET['error'])): ?>
                <p class="message error" id="error-message"><?php echo htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>
            <?php if (empty($pendingRequests)): ?>
                <p class="no-requests">No pending requests found.</p>
            <?php else: ?>
                <table class="leave-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Reason</th>
                            <th>Proof Document</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="request-table-body">
                        <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['prenom'] . ' ' . $request['nom']); ?></td>
                                <td class="<?php echo strtolower($request['leave_type']) === 'other' ? 'other-leave clickable' : ''; ?>" 
                                    <?php echo strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? 'data-tooltip="Click to view reason"' : ''; ?>
                                    data-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>"
                                    onclick="<?php echo strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? 'showReasonPopup(this.getAttribute(\'data-reason\'))' : ''; ?>">
                                    <?php echo htmlspecialchars(ucfirst($request['leave_type'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['reason'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php if ($request['file_path']): ?>
                                        <a href="<?php echo htmlspecialchars($request['file_path']); ?>" target="_blank" class="leave-btn">View PDF</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <form action="../controller/process-admin-approval.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="leave-btn approve">Approve</button>
                                    </form>
                                    <form action="../controller/process-admin-approval.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="action" value="reject" class="leave-btn reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
    <div class="popup-overlay" id="reason-popup">
        <div class="popup-content">
            <h2 class="form-title">Reason for Leave</h2>
            <p id="reason-text" class="reason-text"></p>
            <div class="action-buttons">
                <button type="button" class="leave-btn reject" onclick="closeReasonPopup()">Close</button>
            </div>
        </div>
    </div>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script>
         function showProfilePopup() {
            const modal = document.getElementById('profile-modal');
            if (modal) {
                modal.style.display = 'flex';
            } else {
                console.error('Profile modal not found');
                alert('Error: Profile modal not found');
            }
        }

        function closeProfileModal() {
            const modal = document.getElementById('profile-modal');
            const form = document.getElementById('profile-form');
            if (modal) {
                modal.style.display = 'none';
                if (form) form.reset();
            }
        }
        const body = document.querySelector('body'),
              sidebar = body.querySelector('.sidebar'),
              toggle = body.querySelector('.toggle'),
              modeSwitch = body.querySelector('.mode-checkbox'),
              reasonPopup = document.getElementById('reason-popup');

        document.addEventListener('DOMContentLoaded', () => {
    const body = document.querySelector('body'),
          sidebar = body.querySelector('.sidebar'),
          toggle = body.querySelector('.toggle'),
          modeSwitch = document.querySelector('.mode-checkbox'),
          modeText = document.querySelector('.mode .text.nav-text'),
          modeIcon = document.querySelector('.mode-icon');

    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('close');
        });
    }

    if (modeSwitch) {
        modeSwitch.addEventListener('change', () => {
            const newTheme = modeSwitch.checked ? 'dark' : 'light';
            fetch('../controller/toggle_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `theme=${newTheme}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    body.className = data.theme;
                    modeText.textContent = data.theme === 'dark' ? 'Light Mode' : 'Dark Mode';
                    modeIcon.setAttribute('name', data.theme === 'dark' ? 'sunny-outline' : 'moon-outline');
                } else {
                    console.error('Failed to toggle theme:', data.message);
                    alert('Failed to update theme.');
                    modeSwitch.checked = !modeSwitch.checked; // Revert checkbox
                }
            })
            .catch(error => {
                console.error('Error toggling theme:', error);
                alert('Error updating theme.');
                modeSwitch.checked = !modeSwitch.checked; // Revert checkbox
            });
        });
    }

    // Preserve existing JavaScript (e.g., for otherCells, filterRequests, etc.)
    const otherCells = document.querySelectorAll('.other-leave.clickable');
    if (otherCells.length > 0) {
        otherCells.forEach(cell => {
            cell.addEventListener('click', (e) => {
                const reason = cell.getAttribute('data-reason');
                showReasonPopup(reason);
            });
        });
    }
});

        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        if (successMessage) {
            setTimeout(() => successMessage.style.display = 'none', 10000);
        }
        if (errorMessage) {
            setTimeout(() => errorMessage.style.display = 'none', 10000);
        }

        function showReasonPopup(reason) {
            if (reasonPopup) {
                document.getElementById('reason-text').textContent = reason || 'No reason provided';
                reasonPopup.style.display = 'flex';
            }
        }

        function closeReasonPopup() {
            if (reasonPopup) {
                reasonPopup.style.display = 'none';
            }
        }

        function filterRequests() {
            const userName = document.getElementById('search-name').value;
            const leaveType = document.getElementById('search-type').value;
            const searchDate = document.getElementById('search-date').value;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../controller/process-admin-approval.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const tableBody = document.getElementById('request-table-body');
                    tableBody.innerHTML = xhr.responseText;
                    if (!tableBody.innerHTML.trim()) {
                        tableBody.parentElement.insertAdjacentHTML('afterend', '<p class="no-requests">No requests found.</p>');
                    } else {
                        const noRequests = document.querySelector('.no-requests');
                        if (noRequests) noRequests.remove();
                    }
                    // Reset filters
                    document.getElementById('search-name').value = '';
                    document.getElementById('search-type').value = 'all';
                    document.getElementById('search-date').value = '';
                }
            };
            xhr.send(`user_name=${encodeURIComponent(userName)}&leave_type=${encodeURIComponent(leaveType)}&date=${encodeURIComponent(searchDate)}`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const otherCells = document.querySelectorAll('.other-leave.clickable');
            if (otherCells.length > 0) {
                otherCells.forEach(cell => {
                    cell.addEventListener('click', (e) => {
                        const reason = cell.getAttribute('data-reason');
                        showReasonPopup(reason);
                    });
                });
            }
        });
    </script>
</body>
</html>