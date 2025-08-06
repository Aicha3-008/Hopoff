<?php
session_start();
require '../model/db.php';
require '../model/UserModel.php';
$role = $_SESSION['role'] ?? 'employer';
$userModel = new UserModel($db);
$userData = $userModel->getUserByEmail($_SESSION['user']['email']);
if ($userData) {
    $_SESSION['user']['profile_picture'] = $userData['profile_picture'] ?? 'art.jpeg';
    $_SESSION['user']['prenom'] = $userData['prenom'];
    $_SESSION['user']['nom'] = $userData['nom'];
}
$nom= $_SESSION['user']['nom'] ?? '';
$prenom = $_SESSION['user']['prenom'] ?? '';
$theme = $userModel->getUserTheme($_SESSION['user']['email']);
$_SESSION['theme'] = $theme;
$user_email = $_SESSION['user']['email'] ?? '';
$leave_balance = $userModel->getLeaveBalance($user_email) ?? 28;
$theme = $_SESSION['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request</title>
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
                    <li class="nav-link active">
                        <a href="leave-request.php">
                            <ion-icon name="duplicate-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Submit a request</span>
                        </a>
                    </li>
                    <?php if ($role === 'admin'||$role === 'responsable'): ?>
                        <li class="nav-link">
                            <a href="admin-approval.php">
                                <ion-icon name="grid-outline" class="icon"></ion-icon>
                                <span class="text nav-text">Approve Requests</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($role !== 'rh'): ?>
                    <li class="nav-link">
                        <a href="consult-requests.php">
                            <ion-icon name="albums-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Consult request</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-link">
                        <a href="history.php">
                            <ion-icon name="calendar-outline" class="icon"></ion-icon>
                            <span class="text nav-text">History</span>
                        </a>
                    </li>
                    <?php if ($role === 'rh'): ?>
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
                    <?php endif; ?>
                
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
        <div class="leave-form" role="region" aria-label="Leave Request Form">
            <h2 class="form-title">Leave Request</h2>
            <?php if (isset($_GET['success'])): ?>
                <p class="message success" id="success-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
            <?php elseif (isset($_GET['error'])): ?>
                <p class="message error" id="error-message"><?php echo htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>
            <p class="requester-info">Request submitted by: <span class="requester-name"><?php echo htmlspecialchars("$prenom $nom"); ?></span></p>
            <div class="leave-options" role="radiogroup" aria-label="Select Leave Type">
                <button type="button" class="leave-btn" data-type="parental" aria-label="Parental Leave">Parental</button>
                <button type="button" class="leave-btn" data-type="vacation" aria-label="Vacation Leave">Vacation</button>
                <button type="button" class="leave-btn" data-type="medication" aria-label="Medication Leave">Medication</button>
                <button type="button" class="leave-btn" data-type="pregnancy" aria-label="Pregnancy Leave">Pregnancy</button>
                <button type="button" class="leave-btn" data-type="el_hajj" aria-label="El Hajj Leave">El Hajj</button>
                <button type="button" class="leave-btn" data-type="other" id="other-btn" aria-label="Other Leave Type">Other</button>
            </div>
            <form action="../controller/request-tretement.php" method="POST" id="leave-form" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="leave_type" id="leave-type">
                <div id="other-reason" class="input-box" role="region" aria-labelledby="other-label" style="display: none;">
                    <label for="reason" class="other-label">Reason:</label>
                    <textarea id="reason" name="reason" placeholder="Please specify your reason" style="width: 100%; height: 100px;"></textarea>
                </div>
                <div class="date-inputs">
                    <div class="input-box">
                        <label for="start-date" class="date-label">Start Date:</label>
                        <input type="date" id="start-date" name="start-date" required aria-required="true" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="input-box">
                        <label for="end-date" class="date-label">End Date:</label>
                        <input type="date" id="end-date" name="end-date" required aria-required="true" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="input-box">
                    <label for="proof-file" class="date-label">Upload Proof (PDF):</label>
                    <input type="file" id="proof-file" name="proof_file" accept="application/pdf" required aria-required="true">
                </div>
                <button type="submit" class="submit-btn">Submit Request</button>
            </form>
            <p class="remaining-leave">Remaining Leave: <strong><?php echo htmlspecialchars($leave_balance) . ' days'; ?></strong></p>
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

        const leaveButtons = document.querySelectorAll('.leave-btn'),
              otherBtn = document.getElementById('other-btn'),
              otherReason = document.getElementById('other-reason'),
              reasonTextarea = document.getElementById('reason'),
              leaveTypeInput = document.getElementById('leave-type'),
              leaveForm = document.getElementById('leave-form'),
              startDateInput = document.getElementById('start-date'),
              endDateInput = document.getElementById('end-date'),
              proofFileInput = document.getElementById('proof-file');

        // Set requester name
        const requesterName = document.querySelector('.requester-name');
        requesterName.textContent = '<?php echo addslashes("$prenom $nom"); ?>';

        // Handle leave type selection
        leaveButtons.forEach(button => {
            button.addEventListener('click', () => {
                leaveButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                const type = button.getAttribute('data-type');
                leaveTypeInput.value = type;

                if (type === 'other') {
                    otherReason.style.display = 'block';
                    reasonTextarea.setAttribute('required', 'required');
                } else {
                    otherReason.style.display = 'none';
                    reasonTextarea.removeAttribute('required');
                    reasonTextarea.value = '';
                }
            });
        });

        // Form submission validation
        leaveForm.addEventListener('submit', (e) => {
            const errors = [];
            if (!leaveTypeInput.value) {
                errors.push('Please select a leave type.');
            }
            if (leaveTypeInput.value === 'other' && !reasonTextarea.value.trim()) {
                errors.push('Please specify the reason for "Other" leave type.');
            }
            if (!startDateInput.value) {
                errors.push('Please select a start date.');
            }
            if (!endDateInput.value) {
                errors.push('Please select an end date.');
            }
            if (!proofFileInput.files.length) {
                errors.push('Please upload a PDF proof document.');
            } else if (proofFileInput.files[0].type !== 'application/pdf') {
                errors.push('Uploaded file must be a PDF.');
            }
            if (startDateInput.value && endDateInput.value) {
                const start = new Date(startDateInput.value);
                const end = new Date(endDateInput.value);
                const today = new Date('<?php echo date('Y-m-d'); ?>');
                if (start > end) {
                    errors.push('Start date must be before or equal to end date.');
                }
                if (start < today) {
                    errors.push('Start date cannot be in the past.');
                }
            }
            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
            } else {
                // Ensure reason is included in the form data
                if (leaveTypeInput.value === 'other' && reasonTextarea.value.trim()) {
                    const reasonInput = document.createElement('input');
                    reasonInput.type = 'hidden';
                    reasonInput.name = 'reason';
                    reasonInput.value = reasonTextarea.value.trim();
                    leaveForm.appendChild(reasonInput);
                }
            }
        });
    </script>
    <script>
    // Hide message after 10 seconds
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    if (successMessage) {
        setTimeout(() => successMessage.style.display = 'none', 10000); // 10 seconds
    }
    if (errorMessage) {
        setTimeout(() => errorMessage.style.display = 'none', 10000); // 10 seconds
    }
</script>
</body>
</html>