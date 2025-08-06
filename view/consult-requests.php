<?php
session_start();
require '../model/db.php';
require '../model/UserModel.php';
require '../model/LeaveRequestModel.php';

$role = $_SESSION['role'] ?? 'employer';
$userModel = new UserModel($db);
$leaveRequestModel = new LeaveRequestModel($db);
$theme = $userModel->getUserTheme($_SESSION['user']['email']);
$_SESSION['theme'] = $theme;
$user_email = $_SESSION['user']['email'] ?? '';
$requests = $leaveRequestModel->getUserRequests($user_email) ?? [];
$filtered_requests = [];
$forty_eight_hours_ago = date('Y-m-d H:i:s', strtotime('-48 hours'));
$userData = $userModel->getUserByEmail($_SESSION['user']['email']);
if ($userData) {
    $_SESSION['user']['profile_picture'] = $userData['profile_picture'] ?? 'art.jpeg';
    $_SESSION['user']['prenom'] = $userData['prenom'];
    $_SESSION['user']['nom'] = $userData['nom'];
}
foreach ($requests as $request) {
    $updated_at = $request['updated_at'] ?? $request['created_at'];
    if ($request['status'] === 'pending' || ($request['status'] !== 'pending' && $updated_at >= $forty_eight_hours_ago)) {
        $filtered_requests[] = $request;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consult Requests</title>
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
                    <span class="profession"><?php 
                        $role = $_SESSION['role'] ?? 'employer';
                        echo ucfirst($role === 'responsable' ? 'Admin' : $role);
                    ?></span>
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
                    <?php if ($role === 'admin' || $role === 'responsable'): ?>
                        <li class="nav-link">
                            <a href="admin-approval.php">
                                <ion-icon name="grid-outline" class="icon"></ion-icon>
                                <span class="text nav-text">Approve Requests</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-link active">
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
            <h2 class="form-title">Consult Your Leave Requests</h2>
            <?php if (!empty($message)): ?>
                <p class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>" id="message">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>
            <?php if (empty($filtered_requests)): ?>
                <p class="no-requests">No requests found.</p>
            <?php else: ?>
                <table class="leave-table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Proof Document</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_requests as $request): ?>
                            <tr data-id="<?php echo $request['id']; ?>" class="<?php echo in_array($request['id'], $hiddenRequests ?? []) ? 'hidden' : ''; ?>">
                                <td class="<?php echo strtolower($request['leave_type']) === 'other' ? 'other-leave clickable' : ''; ?>" 
                                    <?php echo strtolower($request['leave_type']) === 'other' && !empty($request['reason']) ? 'data-tooltip="Click to view reason"' : ''; ?>
                                    data-reason="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($request['leave_type']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($request['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['status']); ?></td>
                                <td>
                                    <?php if ($request['file_path']): ?>
                                        <a href="<?php echo htmlspecialchars($request['file_path']); ?>" target="_blank" class="leave-btn">View PDF</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" action="../controller/requestModif.php" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="leave-btn delete" onclick="return confirm('Are you sure you want to delete this leave request?')">
                                                <ion-icon name="close-outline"></ion-icon>
                                            </button>
                                        </form>
                                        <button class="leave-btn modify" onclick="showModifyPopup(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['start_date']); ?>', '<?php echo htmlspecialchars($request['end_date']); ?>', '<?php echo htmlspecialchars($request['reason'] ?? ''); ?>')">
                                            <ion-icon name="create-outline"></ion-icon>
                                        </button>
                                    <?php else: ?>
                                        <button class="leave-btn delete" onclick="hideNonPendingRequest(<?php echo $request['id']; ?>)">
                                            <ion-icon name="close-outline"></ion-icon>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="popup-overlay" id="reason-popup">
                <div class="popup-content">
                    <h2 class="form-title">Reason for Leave</h2>
                    <p id="reason-text" class="reason-text"></p>
                    <div class="action-buttons">
                        <button type="button" class="leave-btn reject" onclick="closeReasonPopup()">Close</button>
                    </div>
                </div>
            </div>
            <div class="popup-overlay" id="modify-popup">
                <div class="popup-content">
                    <h2 class="form-title">Modify Leave Request</h2>
                    <form method="POST" action="../controller/requestModif.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="modify">
                        <input type="hidden" name="id" id="modify-id">
                        <div class="date-inputs">
                            <div>
                                <label class="date-label" for="modify-start-date">Start Date:</label>
                                <input type="date" id="modify-start-date" name="start_date" required>
                            </div>
                            <div class="date-spacer"></div>
                            <div>
                                <label class="date-label" for="modify-end-date">End Date:</label>
                                <input type="date" id="modify-end-date" name="end_date" required>
                            </div>
                        </div>
                        <div id="modify-other-reason" style="display: none;">
                            <label class="other-label" for="modify-reason">Reason:</label>
                            <textarea id="modify-reason" name="reason" placeholder="Enter reason for leave"></textarea>
                        </div>
                        <div class="input-box">
                            <label for="proof-file" class="date-label">Upload Proof (PDF, optional):</label>
                            <input type="file" id="proof-file" name="proof_file" accept="application/pdf">
                        </div>
                        <div class="action-buttons">
                            <button type="submit" class="submit-btn">Update Request</button>
                            <button type="button" class="leave-btn reject" onclick="closeModifyPopup()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
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
              popupOverlay = document.getElementById('modify-popup'),
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

        function showModifyPopup(id, startDate, endDate, reason) {
            const modifyForm = document.querySelector('#modify-popup form');
            document.getElementById('modify-id').value = id;
            document.getElementById('modify-start-date').value = startDate;
            document.getElementById('modify-end-date').value = endDate;
            const reasonField = document.getElementById('modify-other-reason');
            const reasonInput = document.getElementById('modify-reason');
            if (reason) {
                reasonField.style.display = 'block';
                reasonInput.value = reason;
            } else {
                reasonField.style.display = 'none';
                reasonInput.value = '';
            }
            popupOverlay.style.display = 'flex';

            modifyForm.addEventListener('submit', (e) => {
                const startDateInput = document.getElementById('modify-start-date').value;
                const endDateInput = document.getElementById('modify-end-date').value;
                const today = new Date().toISOString().split('T')[0];
                const start = new Date(startDateInput);
                const end = new Date(endDateInput);

                if (startDateInput < today) {
                    alert('Start date cannot be before today.');
                    e.preventDefault();
                } else if (end < start) {
                    alert('End date must be after start date.');
                    e.preventDefault();
                }
            });
        }

        function closeModifyPopup() {
            popupOverlay.style.display = 'none';
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

        function hideNonPendingRequest(id) {
            let hiddenRequests = JSON.parse(localStorage.getItem('hiddenRequests') || '[]');
            if (!hiddenRequests.includes(id)) {
                hiddenRequests.push(id);
                localStorage.setItem('hiddenRequests', JSON.stringify(hiddenRequests));
            }
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const otherCells = document.querySelectorAll('.other-leave.clickable');
            otherCells.forEach(cell => {
                cell.addEventListener('click', (e) => {
                    const reason = cell.getAttribute('data-reason');
                    showReasonPopup(reason);
                });
            });
        });

        window.addEventListener('load', () => {
            const message = document.getElementById('message');
            if (message) {
                setTimeout(() => {
                    message.style.display = 'none';
                    const url = new URL(window.location);
                    url.searchParams.delete('message');
                    window.history.replaceState({}, document.title, url);
                }, 8000);
            }
            let hiddenRequests = JSON.parse(localStorage.getItem('hiddenRequests') || '[]');
            hiddenRequests.forEach(id => {
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) row.classList.add('hidden');
            });
        });

        const style = document.createElement('style');
        style.textContent = `
            .hidden { display: none !important; }
            .clickable { cursor: pointer; }
            .reason-text { padding: 15px; color: var(--text-color); }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>