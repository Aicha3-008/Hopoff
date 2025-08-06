<?php
session_start();
require '../model/db.php';
require '../model/EventModel.php';
require '../model/UserModel.php';
require '../model/LeaveRequestModel.php';

// Ensure user is logged in
if (!isset($_SESSION['user']['email'])) {
    header('Location: login.php?error=Unauthorized access');
    exit;
}

$role = $_SESSION['role'] ?? 'employer';
$userModel = new UserModel($db);
$eventModel = new EventModel($db);
$leaveModel = new LeaveRequestModel($db);
$userData = $userModel->getUserByEmail($_SESSION['user']['email']);
if ($userData) {
    $_SESSION['user']['profile_picture'] = $userData['profile_picture'] ?? 'art.jpeg';
    $_SESSION['user']['prenom'] = $userData['prenom'];
    $_SESSION['user']['nom'] = $userData['nom'];
}
$userEmail = $_SESSION['user']['email'];
$prenom = $_SESSION['user']['prenom'] ?? 'Unknown';
$nom = $_SESSION['user']['nom'] ?? 'User';
$leave_balance = $userModel->getLeaveBalance($userEmail) ?? 28;
$theme = $userModel->getUserTheme($userEmail);
$_SESSION['theme'] = $theme; // Store theme in session
$upcomingEvents = $eventModel->getUpcomingEvents($userEmail, 5, $role);
error_log("Loaded upcoming events for $userEmail: " . print_r($upcomingEvents, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="dash.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <style>
        .custom-calendar .fc {
            background: var(--primary-color-light);
            border-radius: 10px;
            padding: 10px;
        }
        .fc .fc-toolbar-title {
            color: var(--text-color);
            font-weight: bold;
        }
        .custom-calendar .fc-daygrid-day-number,
        .custom-calendar .fc-col-header-cell-cushion,
        .custom-calendar .fc-event-title,
        .custom-calendar .fc-event-time,
        .custom-calendar .fc-daygrid-event-dot,
        .custom-calendar .fc-daygrid-block-event,
        .custom-calendar .fc-timegrid-slot-label,
        .custom-calendar .fc-timegrid-event {
            color: var(--text-color) !important;
        }
        .custom-calendar .fc-daygrid-day.fc-day-today {
            background: var(--primary-color) !important;
        }
        body.dark .custom-calendar .fc-daygrid-day-number,
        body.dark .custom-calendar .fc-col-header-cell-cushion,
        body.dark .custom-calendar .fc-event-title,
        body.dark .custom-calendar .fc-event-time,
        body.dark .custom-calendar .fc-daygrid-event-dot,
        body.dark .custom-calendar .fc-daygrid-block-event,
        body.dark .custom-calendar .fc-timegrid-slot-label,
        body.dark .custom-calendar .fc-timegrid-event {
            color: var(--text-color) !important;
        }
        .custom-calendar .fc-daygrid-day {
            transition: background 0.3s;
        }
        .custom-calendar .fc-daygrid-day:hover {
            background: rgba(0, 0, 0, 0.1) !important;
        }
        body.dark .custom-calendar .fc-daygrid-day:hover {
            background: rgba(255, 255, 255, 0.1) !important;
        }
        .custom-calendar .fc-event {
            border: 1px solid var(--toggle-color);
            background-color: var(--primary-color-light);
        }
        body.dark .custom-calendar .fc-event {
            background-color: var(--primary-color);
        }
        .custom-calendar .fc-event-fixed {
            border: 2px solid var(--toggle-color) !important;
            cursor: default !important;
        }
    </style>
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
                    <span class="profession"><?php echo ucfirst($role === 'responsable' ? 'Admin' : $role); ?></span>
                </div>
            </div>
            <ion-icon name="chevron-forward-outline" class="toggle"></ion-icon>
        </header>
        <div class="menu-bar">
            <div class="menu">
                <ul class="menu-links">
                    <li class="nav-link active">
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
                         <li class="nav-link">
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
        <div id="calendar" class="custom-calendar"></div>
        <div class="upcoming-events">
            <h2 class="form-title">Upcoming Events</h2>
            <table class="leave-table" style="display: <?php echo empty($upcomingEvents) ? 'none' : 'table'; ?>">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody id="upcoming-events-table">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <tr>
                            <td style="color: <?php echo htmlspecialchars($event['color']); ?>">
                                <?php echo htmlspecialchars($event['title']); ?>
                                <?php if (isset($event['editable']) && $event['editable'] === false): ?>
                                    <span style="font-size: 0.8em; color: var(--text-color);">
                                        (<?php echo $event['is_global'] ? 'Global' : 'Holiday'; ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($event['start']))); ?></td>
                            <td><?php echo $event['end'] ? htmlspecialchars(date('Y-m-d', strtotime($event['end']))) : 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($upcomingEvents)): ?>
                <p class="no-requests">No upcoming events found.</p>
            <?php endif; ?>
        </div>
        <div class="event-modal" id="event-modal">
            <div class="modal-content">
                <h2 class="form-title" id="modal-title">Event Details</h2>
                <form id="event-form">
                    <input type="hidden" id="event-id">
                    <div class="input-box">
                        <span class="icon"><ion-icon name="text"></ion-icon></span>
                        <input type="text" id="event-title" required>
                        <label>Title</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><ion-icon name="calendar"></ion-icon></span>
                        <input type="datetime-local" id="event-start" required>
                        <label>Start Date</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><ion-icon name="calendar"></ion-icon></span>
                        <input type="datetime-local" id="event-end">
                        <label>End Date </label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><ion-icon name="color-palette"></ion-icon></span>
                        <input type="color" id="event-color" value="#3788d8">
                        <label>Color</label>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="leave-btn">Save</button>
                        <button type="button" class="leave-btn" id="delete-btn" style="display: none;">Delete</button>
                        <button type="button" class="leave-btn" onclick="closeEventModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
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
        function closeEventModal() {
            const eventModal = document.getElementById('event-modal');
            const eventForm = document.getElementById('event-form');
            if (eventModal) {
                eventModal.style.display = 'none';
                if (eventForm) eventForm.reset();
                document.getElementById('delete-btn').style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const body = document.querySelector('body'),
                  sidebar = body.querySelector('.sidebar'),
                  toggle = body.querySelector('.toggle'),
                  modeSwitch = document.querySelector('.mode-checkbox'),
                  modeText = document.querySelector('.mode .text.nav-text'),
                  modeIcon = document.querySelector('.mode-icon'),
                  calendarEl = document.getElementById('calendar'),
                  eventModal = document.getElementById('event-modal'),
                  eventForm = document.getElementById('event-form'),
                  upcomingEventsTable = document.getElementById('upcoming-events-table');

            if (!calendarEl) console.error('Missing calendarEl');
            if (!eventModal) console.error('Missing eventModal');
            if (!eventForm) console.error('Missing eventForm');
            if (!upcomingEventsTable) console.error('Missing upcomingEventsTable');

            if (calendarEl) {
                toggle.addEventListener('click', () => {
                    sidebar.classList.toggle('close');
                });

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

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    editable: true,
                    selectable: true,
                    height: 'auto',
                    contentHeight: 'auto',
                    events: {
                        url: '../controller/process-events.php?action=fetch',
                        method: 'GET',
                        failure: function() {
                            console.error('Failed to fetch events');
                        }
                    },
                    eventClassNames: function(arg) {
                        return arg.event.extendedProps.editable === false ? ['fc-event-fixed'] : [];
                    },
                    dateClick: function(info) {
                        openEventModal(null, '', info.dateStr + 'T00:00', '', '#3788d8');
                    },
                    eventClick: function(info) {
                        if (info.event.extendedProps.editable === false) {
                            alert('This is a fixed holiday or global event and cannot be modified.');
                            return;
                        }
                        openEventModal(
                            info.event.id,
                            info.event.title,
                            info.event.start.toISOString().slice(0, 16),
                            info.event.end ? info.event.end.toISOString().slice(0, 16) : '',
                            info.event.backgroundColor
                        );
                    },
                    eventDrop: function(info) {
                        if (info.event.extendedProps.editable === false) {
                            alert('Fixed holidays or global events cannot be moved.');
                            info.revert();
                            return;
                        }
                        updateEvent(info.event);
                    },
                    eventResize: function(info) {
                        if (info.event.extendedProps.editable === false) {
                            alert('Fixed holidays or global events cannot be resized.');
                            info.revert();
                            return;
                        }
                        updateEvent(info.event);
                    }
                });
                calendar.render();

                function openEventModal(id, title, start, end, color) {
                    if (eventModal) {
                        document.getElementById('modal-title').textContent = id ? 'Edit Event' : 'Add Event';
                        document.getElementById('event-id').value = id || '';
                        document.getElementById('event-title').value = title || '';
                        document.getElementById('event-start').value = start ? start.slice(0, 16) : '';
                        document.getElementById('event-end').value = end ? end.slice(0, 16) : '';
                        document.getElementById('event-color').value = color || '#3788d8';
                        document.getElementById('delete-btn').style.display = id ? 'inline-block' : 'none';
                        eventModal.style.display = 'flex';
                    }
                }

                function updateEvent(event) {
                    const data = new FormData();
                    data.append('action', 'update');
                    data.append('id', event.id);
                    data.append('title', event.title);
                    data.append('start_date', event.start.toISOString().slice(0, 16));
                    data.append('end_date', event.end ? event.end.toISOString().slice(0, 16) : '');
                    data.append('color', event.backgroundColor);

                    fetch('../controller/process-events.php', {
                        method: 'POST',
                        body: data
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              console.log('Update success:', data.success);
                              event.setProp('title', event.title);
                              event.setDates(event.start, event.end || null);
                              event.setProp('backgroundColor', event.backgroundColor);
                              updateUpcomingEvents();
                          } else {
                              console.error('Update error:', data.error);
                              alert(data.error);
                              calendar.refetchEvents();
                          }
                      }).catch(error => {
                          console.error('Fetch error:', error);
                          alert('Error updating event.');
                          calendar.refetchEvents();
                      });
                }

                eventForm?.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const id = document.getElementById('event-id').value;
                    const title = document.getElementById('event-title').value;
                    const start = document.getElementById('event-start').value;
                    const end = document.getElementById('event-end').value || null;
                    const color = document.getElementById('event-color').value;

                    const data = new FormData();
                    data.append('action', id ? 'update' : 'add');
                    if (id) data.append('id', id);
                    data.append('title', title);
                    data.append('start_date', start);
                    data.append('end_date', end);
                    data.append('color', color);

                    fetch('../controller/process-events.php', {
                        method: 'POST',
                        body: data
                    }).then(response => response.json())
                      .then(data => {
                          if (data.success) {
                              console.log('Save success:', data.success);
                              if (!id) {
                                  calendar.addEvent({
                                      id: data.id || Date.now(),
                                      title: title,
                                      start: start,
                                      end: end,
                                      backgroundColor: color,
                                      editable: true
                                  });
                              } else {
                                  const event = calendar.getEventById(id);
                                  if (event) {
                                      event.setProp('title', title);
                                      event.setDates(start, end || null);
                                      event.setProp('backgroundColor', color);
                                  }
                              }
                              updateUpcomingEvents();
                              closeEventModal();
                              alert(data.success);
                          } else {
                              console.error('Save error:', data.error);
                              alert(data.error);
                          }
                      }).catch(error => {
                          console.error('Fetch error:', error);
                          alert('Error saving event.');
                      });
                });

                document.getElementById('delete-btn').addEventListener('click', () => {
                    const id = document.getElementById('event-id').value;
                    if (id && confirm('Are you sure you want to delete this event?')) {
                        const data = new FormData();
                        data.append('action', 'delete');
                        data.append('id', id);

                        fetch('../controller/process-events.php', {
                            method: 'POST',
                            body: data
                        }).then(response => response.json())
                          .then(data => {
                              if (data.success) {
                                  console.log('Delete success:', data.success);
                                  const event = calendar.getEventById(id);
                                  if (event) event.remove();
                                  updateUpcomingEvents();
                                  closeEventModal();
                                  alert(data.success);
                              } else {
                                  console.error('Delete error:', data.error);
                                  alert(data.error);
                              }
                          }).catch(error => {
                              console.error('Fetch error:', error);
                              alert('Error deleting event.');
                          });
                    }
                });

                function updateUpcomingEvents() {
                    fetch('../controller/process-upcoming-events.php', {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(events => {
                        const tableBody = document.getElementById('upcoming-events-table');
                        const table = document.querySelector('.leave-table');
                        const noRequests = document.querySelector('.no-requests');
                        if (tableBody) {
                            if (events.length > 0) {
                                tableBody.innerHTML = events.map(event => `
                                    <tr>
                                        <td style="color: ${event.color}">
                                            ${event.title}
                                            ${event.editable === false ? '<span style="font-size: 0.8em; color: var(--text-color);">' + (event.is_global ? '(Global)' : '(Holiday)') + '</span>' : ''}
                                        </td>
                                        <td>${new Date(event.start).toLocaleDateString('en-CA')}</td>
                                        <td>${event.end ? new Date(event.end).toLocaleDateString('en-CA') : 'N/A'}</td>
                                    </tr>
                                `).join('');
                                table.style.display = 'table';
                                if (noRequests) noRequests.remove();
                            } else {
                                tableBody.innerHTML = '';
                                table.style.display = 'none';
                                if (!noRequests) {
                                    table.insertAdjacentHTML('afterend', '<p class="no-requests">No upcoming events found.</p>');
                                }
                            }
                        } else {
                            console.error('Element "upcoming-events-table" not found');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating upcoming events:', error);
                        alert('Failed to refresh upcoming events.');
                    });
                }

                function handleApproval(requestId, action) {
                    if (confirm(`Are you sure you want to ${action} this leave request?`)) {
                        const data = new FormData();
                        data.append('action', action);
                        data.append('request_id', requestId);

                        fetch('../controller/request-tretement.php', {
                            method: 'POST',
                            body: data
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.success);
                                const row = document.querySelector(`button[data-id="${requestId}"]`).closest('tr');
                                if (row) row.remove();
                                const tableBody = document.querySelector('.consult-requests .leave-table tbody');
                                if (tableBody && tableBody.children.length === 0) {
                                    const table = document.querySelector('.consult-requests .leave-table');
                                    table.style.display = 'none';
                                    table.insertAdjacentHTML('afterend', '<p class="no-requests">No pending requests found.</p>');
                                }
                            } else {
                                alert(data.error || 'Failed to process request.');
                            }
                        })
                        .catch(error => {
                            console.error('Error processing approval:', error);
                            alert('Error processing request.');
                        });
                    }
                }
            }
        });
    </script>
</body>
</html>