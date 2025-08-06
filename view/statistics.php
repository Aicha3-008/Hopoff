<?php
session_start();
require '../model/db.php';
require '../model/StatisticsModel.php';
require '../model/UserModel.php';
$userModel = new UserModel($db);
$theme = $userModel->getUserTheme($_SESSION['user']['email']);
$_SESSION['theme'] = $theme;
// Ensure user is logged in and has 'rh' role
if (!isset($_SESSION['user']['email']) || $_SESSION['role'] !== 'rh') {
    header('Location: login.php?error=Unauthorized access');
    exit;
}
$userData = $userModel->getUserByEmail($_SESSION['user']['email']);
if ($userData) {
    $_SESSION['user']['profile_picture'] = $userData['profile_picture'] ?? 'art.jpeg';
    $_SESSION['user']['prenom'] = $userData['prenom'];
    $_SESSION['user']['nom'] = $userData['nom'];
}
$statisticsModel = new StatisticsModel($db);
$stats = $statisticsModel->getLeaveStatistics();
$leaveTypeData = $statisticsModel->getLeaveTypeDistribution();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Statistics</title>
    <link rel="stylesheet" href="dash.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
                    <span class="profession">RH</span>
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
                    <li class="nav-link active">
                        <a href="statistics.php">
                            <ion-icon name="bar-chart-outline" class="icon"></ion-icon>
                            <span class="text nav-text">Statistics</span>
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
            <h2 class="form-title">Leave Statistics</h2>
            <?php if (isset($_GET['success'])): ?>
                <p class="message success" id="success-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
            <?php elseif (isset($_GET['error'])): ?>
                <p class="message error" id="error-message"><?php echo htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>
            <div class="search-zone">
                <div class="search-options">
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
                    <button class="leave-btn" onclick="filterStatistics()">Search</button>
                    <button class="leave-btn" onclick="exportToCSV()">Export to CSV</button>
                    <button class="leave-btn" onclick="exportToPDF()">Export to PDF</button>
                </div>
            </div>
            <?php if (empty($stats)): ?>
                <p class="no-requests">No statistics available.</p>
            <?php else: ?>
                <div class="charts-container">
                    <div class="chart-box">
                        <h3>Leave Days by Type</h3>
                        <canvas id="leaveTypeBarChart"></canvas>
                    </div>
                    <div class="chart-box">
                        <h3>Leave Type Distribution</h3>
                        <canvas id="leaveTypePieChart"></canvas>
                    </div>
                </div>
                <table class="leave-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody id="stats-table-body">
                        <tr>
                            <td>Total Employees</td>
                            <td><?php echo htmlspecialchars($stats['total_employees']); ?></td>
                        </tr>
                        <tr>
                            <td>Total Approved Leave Days</td>
                            <td><?php echo htmlspecialchars($stats['total_leave_days']); ?></td>
                        </tr>
                        <tr>
                            <td>Average Leave Days per Employee</td>
                            <td><?php echo htmlspecialchars($stats['avg_leave_days']); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
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
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        if (successMessage) {
            setTimeout(() => successMessage.style.display = 'none', 10000);
        }
        if (errorMessage) {
            setTimeout(() => errorMessage.style.display = 'none', 10000);
        }

        const leaveTypeData = <?php echo json_encode($leaveTypeData); ?>;
        let barChart, pieChart;

        function updateChartColors() {
            const isDarkMode = body.classList.contains('dark');
            const textColor = isDarkMode ? '#CCC' : '#707070';
            const bgColors = [
                'rgba(105, 92, 254, 0.8)', // primary-color
                'rgba(231, 76, 60, 0.8)', // reject color
                'rgba(46, 204, 113, 0.8)', // success color
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ];

            const barChartConfig = {
                type: 'bar',
                data: {
                    labels: ['Vacation', 'Medication', 'El Hajj', 'Parental', 'Pregnancy', 'Other'],
                    datasets: [{
                        label: 'Leave Days by Type',
                        data: [
                            leaveTypeData.vacation || 0,
                            leaveTypeData.medication || 0,
                            leaveTypeData.el_hajj || 0,
                            leaveTypeData.parental || 0,
                            leaveTypeData.pregnancy || 0,
                            leaveTypeData.other || 0
                        ],
                        backgroundColor: bgColors,
                        borderColor: bgColors.map(color => color.replace('0.8', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: textColor },
                            grid: { color: isDarkMode ? '#3A3B3C' : '#DDD' }
                        },
                        x: {
                            ticks: { color: textColor },
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: textColor } }
                    }
                }
            };

            const pieChartConfig = {
                type: 'pie',
                data: {
                    labels: ['Vacation', 'Medication', 'El Hajj', 'Parental', 'Pregnancy', 'Other'],
                    datasets: [{
                        data: [
                            leaveTypeData.vacation || 0,
                            leaveTypeData.medication || 0,
                            leaveTypeData.el_hajj || 0,
                            leaveTypeData.parental || 0,
                            leaveTypeData.pregnancy || 0,
                            leaveTypeData.other || 0
                        ],
                        backgroundColor: bgColors,
                        borderColor: isDarkMode ? '#242526' : '#FFF',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: textColor }
                        }
                    }
                }
            };

            if (barChart) barChart.destroy();
            if (pieChart) pieChart.destroy();

            barChart = new Chart(document.getElementById('leaveTypeBarChart'), barChartConfig);
            pieChart = new Chart(document.getElementById('leaveTypePieChart'), pieChartConfig);
        }

        function filterStatistics() {
            const leaveType = document.getElementById('search-type').value;
            const searchDate = document.getElementById('search-date').value;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../controller/statistics-logic.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const tableBody = document.getElementById('stats-table-body');
                    tableBody.innerHTML = response.table;
                    if (!tableBody.innerHTML.trim()) {
                        tableBody.parentElement.insertAdjacentHTML('afterend', '<p class="no-requests">No statistics found.</p>');
                    } else {
                        const noRequests = document.querySelector('.no-requests');
                        if (noRequests) noRequests.remove();
                    }
                    leaveTypeData.vacation = response.leaveTypeData.vacation || 0;
                    leaveTypeData.medication = response.leaveTypeData.medication || 0;
                    leaveTypeData.el_hajj = response.leaveTypeData.el_hajj || 0;
                    leaveTypeData.parental = response.leaveTypeData.parental || 0;
                    leaveTypeData.pregnancy = response.leaveTypeData.pregnancy || 0;
                    leaveTypeData.other = response.leaveTypeData.other || 0;
                    updateChartColors();
                    document.getElementById('search-type').value = 'all';
                    document.getElementById('search-date').value = '';
                }
            };
            xhr.send(`leave_type=${encodeURIComponent(leaveType)}&date=${encodeURIComponent(searchDate)}`);
        }

        function exportToCSV() {
            const leaveType = document.getElementById('search-type').value;
            const searchDate = document.getElementById('search-date').value;

            // Capture chart images as base64
            Promise.all([
                html2canvas(document.querySelector('#leaveTypeBarChart'), { scale: 2 }),
                html2canvas(document.querySelector('#leaveTypePieChart'), { scale: 2 })
            ]).then(([barCanvas, pieCanvas]) => {
                const barChartImage = barCanvas.toDataURL('image/png').split(',')[1]; // Get base64 part
                const pieChartImage = pieCanvas.toDataURL('image/png').split(',')[1]; // Get base64 part

                // Create a hidden form to send data via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../controller/statistics-logic.php';
                form.style.display = 'none';

                // Add export type
                const exportInput = document.createElement('input');
                exportInput.type = 'hidden';
                exportInput.name = 'export';
                exportInput.value = 'csv';
                form.appendChild(exportInput);

                // Add leave type
                const leaveTypeInput = document.createElement('input');
                leaveTypeInput.type = 'hidden';
                leaveTypeInput.name = 'leave_type';
                leaveTypeInput.value = leaveType;
                form.appendChild(leaveTypeInput);

                // Add search date
                const dateInput = document.createElement('input');
                dateInput.type = 'hidden';
                dateInput.name = 'date';
                dateInput.value = searchDate;
                form.appendChild(dateInput);

                // Add bar chart image
                const barChartInput = document.createElement('input');
                barChartInput.type = 'hidden';
                barChartInput.name = 'barChartImage';
                barChartInput.value = barChartImage;
                form.appendChild(barChartInput);

                // Add pie chart image
                const pieChartInput = document.createElement('input');
                pieChartInput.type = 'hidden';
                pieChartInput.name = 'pieChartImage';
                pieChartInput.value = pieChartImage;
                form.appendChild(pieChartInput);

                // Append form to body and submit
                document.body.appendChild(form);
                form.submit();
            }).catch(error => {
                console.error('Error capturing charts:', error);
                // Fallback to export without charts
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../controller/statistics-logic.php';
                form.style.display = 'none';

                const exportInput = document.createElement('input');
                exportInput.type = 'hidden';
                exportInput.name = 'export';
                exportInput.value = 'csv';
                form.appendChild(exportInput);

                const leaveTypeInput = document.createElement('input');
                leaveTypeInput.type = 'hidden';
                leaveTypeInput.name = 'leave_type';
                leaveTypeInput.value = leaveType;
                form.appendChild(leaveTypeInput);

                const dateInput = document.createElement('input');
                dateInput.type = 'hidden';
                dateInput.name = 'date';
                dateInput.value = searchDate;
                form.appendChild(dateInput);

                document.body.appendChild(form);
                form.submit();
            });
        }

        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const isDarkMode = body.classList.contains('dark');
            const textColor = isDarkMode ? '#CCC' : '#707070';
            const primaryColor = isDarkMode ? '#3A3B3C' : '#695CFE';

            // Header
            doc.setFont('Helvetica', 'normal'); // Changed to Helvetica for jsPDF compatibility
            doc.setFontSize(18);
            doc.setTextColor(primaryColor);
            doc.text('Gestion Congés - Leave Statistics', 20, 20);
            doc.setFontSize(10);
            doc.setTextColor(textColor);
            doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 30);

            // Capture Table
            html2canvas(document.querySelector('.leave-table'), { scale: 2 }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const imgProps = doc.getImageProperties(imgData);
                const pdfWidth = doc.internal.pageSize.getWidth() - 40;
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                doc.addImage(imgData, 'PNG', 20, 40, pdfWidth, pdfHeight);
                
                // Capture Bar Chart
                html2canvas(document.querySelector('#leaveTypeBarChart'), { scale: 2 }).then(barCanvas => {
                    const barImgData = barCanvas.toDataURL('image/png');
                    const barImgProps = doc.getImageProperties(barImgData);
                    const barHeight = (barImgProps.height * pdfWidth) / barImgProps.width;
                    doc.addPage();
                    doc.setFontSize(14);
                    doc.setTextColor(primaryColor);
                    doc.text('Leave Days by Type', 20, 20);
                    doc.addImage(barImgData, 'PNG', 20, 30, pdfWidth, barHeight);

                    // Capture Pie Chart
                    html2canvas(document.querySelector('#leaveTypePieChart'), { scale: 2 }).then(pieCanvas => {
                        const pieImgData = pieCanvas.toDataURL('image/png');
                        const pieImgProps = doc.getImageProperties(pieImgData);
                        const pieHeight = (pieImgProps.height * pdfWidth) / pieImgProps.width;
                        doc.addPage();
                        doc.setFontSize(14);
                        doc.setTextColor(primaryColor);
                        doc.text('Leave Type Distribution', 20, 20);
                        doc.addImage(pieImgData, 'PNG', 20, 30, pdfWidth, pieHeight);
                        doc.save('leave_statistics.pdf');
                    });
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateChartColors();
        });
    </script>
</body>
</html>