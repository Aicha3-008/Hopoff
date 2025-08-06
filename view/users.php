<?php
session_start();
require '../model/db.php';
require '../model/UserModel.php';
require '../model/EventModel.php';

if (!isset($_SESSION['user']['email']) || $_SESSION['role'] !== 'rh') {
    header("Location: login.php");
    exit;
}

$userModel = new UserModel($db);
$theme = $userModel->getUserTheme($_SESSION['user']['email']);
$_SESSION['theme'] = $theme;

// Fetch user data including profile picture from database on every load
$userData = $userModel->getUserByEmail($_SESSION['user']['email']);
if ($userData) {
    $_SESSION['user']['profile_picture'] = $userData['profile_picture'] ?? 'art.jpeg';
    $_SESSION['user']['prenom'] = $userData['prenom'];
    $_SESSION['user']['nom'] = $userData['nom'];
}

$eventModel = new EventModel($db);
$stmt = $db->prepare("SELECT prenom, nom, email, role FROM user");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users & Events</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        /* Ensure popup overlay is above everything, including .home section */
        .popup-overlay {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999; /* Much higher than .home or sidebar */
        }
        .popup-content {
            position: relative;
            z-index: 100000; /* On top of overlay */
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($theme); ?>">
   <?php if (isset($_GET['success'])): ?>
        <script>alert('<?php echo htmlspecialchars($_GET['success']); ?>');</script>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <script>alert('<?php echo htmlspecialchars($_GET['error']); ?>');</script>
    <?php endif; ?>
    <nav class="sidebar close">
        <header>
            <div class="image-text">
                <span class="image"><img src="art.jpeg" alt="logo"></span>
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
                    <li class="nav-link">
                        <a href="history.php">
                            <ion-icon name="calendar-outline" class="icon"></ion-icon>
                            <span class="text nav-text">History</span>
                        </a>
                    </li>
                    <li class="nav-link active">
                        <a href="users.php">
                            <ion-icon name="people-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Manage Users</span>
                        </a>
                    </li>
                    <li class="nav-link">
                        <a href="statistics.php">
                            <ion-icon name="bar-chart-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Statistics</span>
                        </a>
                    </li>
                <li><a href="../controller/logout.php"><ion-icon name="log-out-outline" class="icon"></ion-icon><span class="text nav-text">Logout</span></a></li>
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
            <h2 class="form-title">Manage Users & Events</h2>
            <h3 class="form-title">Add Event</h3>
            <div class="input-box">
                <input type="text" id="event-title" placeholder="Event Title" required>
                <input type="datetime-local" id="event-start" required>
                <input type="datetime-local" id="event-end">
                <input type="color" id="event-color" value="#7a22de">
                <button class="leave-btn" onclick="addEvent()">Add Event</button>
            </div>
            <h3 class="form-title">Add User</h3>
            <div class="input-box">
                <input type="text" id="new-prenom" placeholder="First Name" required>
                <input type="text" id="new-nom" placeholder="Last Name" required>
                <input type="email" id="new-email" placeholder="Email" required>
                <select id="new-role">
                    <option value="admin">Admin</option>
                    <option value="responsable">Responsable</option>
                    <option value="employer">Employer</option>
                </select>
                <button class="leave-btn" onclick="addUser()">Add User</button>
            </div>
            <div class="search-zone">
                <div class="search-options">
                    <input type="text" id="search-user" class="search-input" placeholder="Search by name or role...">
                    <button class="leave-btn" onclick="searchUsers()">Search</button>
                </div>
            </div>
            <div id="users-table-container">
                <table class="leave-table" id="users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <select class="role-select" data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="responsable" <?php echo $user['role'] === 'responsable' ? 'selected' : ''; ?>>Responsable</option>
                                        <option value="employer" <?php echo $user['role'] === 'employer' ? 'selected' : ''; ?>>Employer</option>
                                    </select>
                                </td>
                                <td class="action-buttons">
                                    <button class="leave-btn modify" onclick="updateRole(this)">Update</button>
                                    <button class="leave-btn delete" onclick="deleteUser('<?php echo htmlspecialchars($user['email']); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($users)): ?>
                    <p class="no-requests">No users found.</p>
                <?php endif; ?>
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
              modeSwitch = body.querySelector('.mode-checkbox');

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

        function searchUsers() {
            const query = document.getElementById('search-user').value.toLowerCase();
            fetch(`../controller/users-logic.php?action=search&query=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    const tableBody = document.getElementById('users-table-body');
                    tableBody.innerHTML = data.html || '<tr><td colspan="4">No users found.</td></tr>';
                    if (!data.html) {
                        document.querySelector('.no-requests')?.remove();
                        tableBody.insertAdjacentHTML('afterend', '<p class="no-requests">No users found.</p>');
                    } else {
                        document.querySelector('.no-requests')?.remove();
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    alert('Failed to search users: ' + error.message);
                });
        }

        function updateRole(button) {
            const row = button.closest('tr');
            const email = row.querySelector('.role-select').dataset.email;
            const newRole = row.querySelector('.role-select').value;
            if (!email || !newRole || !['admin', 'responsable', 'employer'].includes(newRole)) {
                alert('Invalid email or role selection');
                return;
            }
            fetch('../controller/users-logic.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=updateRole&email=${encodeURIComponent(email)}&role=${encodeURIComponent(newRole)}`
            })
            .then(response => {
                if (!response.ok) throw new Error('HTTP error: ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                alert(data.message);
                if (data.success) searchUsers(); // Refresh table
            })
            .catch(error => {
                console.error('Update error:', error);
                alert('Failed to update role: ' + error.message);
            });
        }

        function deleteUser(email) {
            if (confirm('Are you sure you want to delete this user?')) {
                fetch('../controller/users-logic.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=deleteUser&email=${encodeURIComponent(email)}`
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) searchUsers(); // Refresh table
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    alert('Failed to delete user: ' + error.message);
                });
            }
        }

        function addEvent() {
            const title = document.getElementById('event-title').value.trim();
            const start = document.getElementById('event-start').value;
            const end = document.getElementById('event-end').value;
            const color = document.getElementById('event-color').value;
            if (!title || !start) {
                alert('Title and start date are required.');
                return;
            }
            fetch('../controller/users-logic.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=addEvent&title=${encodeURIComponent(title)}&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}&color=${encodeURIComponent(color)}&is_global=1`
            })
            .then(response => response.json())
            .then(data => alert(data.message))
            .catch(error => {
                console.error('Add event error:', error);
                alert('Failed to add event: ' + error.message);
            });
        }

        function addUser() {
            const prenom = document.getElementById('new-prenom').value.trim();
            const nom = document.getElementById('new-nom').value.trim();
            const email = document.getElementById('new-email').value.trim();
            const role = document.getElementById('new-role').value;
            if (!prenom || !nom || !email || !role) {
                alert('All fields are required.');
                return;
            }
            if (!/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/.test(email)) {
                alert('Invalid email format.');
                return;
            }
            console.log('Sending addUser request:', { prenom, nom, email, role });
            fetch('../controller/users-logic.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=addUser&prenom=${encodeURIComponent(prenom)}&nom=${encodeURIComponent(nom)}&email=${encodeURIComponent(email)}&role=${encodeURIComponent(role)}`
            })
            .then(response => {
                if (!response.ok) throw new Error('HTTP error: ' + response.statusText);
                return response.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);
                alert(data.message);
                if (data.success) {
                    document.getElementById('new-prenom').value = '';
                    document.getElementById('new-nom').value = '';
                    document.getElementById('new-email').value = '';
                    document.getElementById('new-role').value = 'employer';
                    searchUsers();
                }
            })
            .catch(error => {
                console.error('Add user error:', error);
                alert('Failed to add user: ' + error.message);
                fetch('../controller/users-logic.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=debug&error=${encodeURIComponent(error.message)}`
                }).catch(debugErr => console.error('Debug failed:', debugErr));
            });
        }
    </script>
</body>
</html>