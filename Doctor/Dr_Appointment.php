<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Section</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="Dr_Appointment.css">
    <link rel="stylesheet" href="Sidebar.css">

</head>

<body>
    <div class="container">
        <!-- Side-Navigationbar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                      <i class="fas fa-heartbeat me-2"></i> MediTrack
                 </div>
                <p class="speciality">Your Trusted Medical Hub</p>
            </div>
            <nav class="nav-links">
                <a href="doctor_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="Dr_Appointment.php" class="nav-item active">
                    <i class="fa-solid fa-calendar"></i>
                    <span>Appointments</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i>
                    <span>Presscriptions</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                    <span class="alert-badge">3</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-user-md"></i>
                    <span>Profile</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log out</span>
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="calendar-container">
                <div class="mini-container">
                    <h2><i class="fa-solid fa-calendar"></i></h2>
                    <h2>Appointments</h2>
                </div>

                <div class="stats-container">
                            <div class="stat-card">
                                <div class="stat-card-value" id="total-appointments">11</div>
                                <div class="stat-card-label">Total Appointments</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-value" id="upcoming-appointments">2</div>
                                <div class="stat-card-label">Today</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card-value" id="this-week">4</div>
                                <div class="stat-card-label">This Week</div>
                            </div>
                </div>
                <div class="calendar-nav">
                        <button id="prev-month" class="month-nav-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h3 id="current-month">May 2025</h3>
                        <button id="next-month" class="month-nav-btn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                </div>

                <!-- Weekday headers -->
                <div class="weekday-header">
                    <div class="weekday">Sun</div>
                    <div class="weekday">Mon</div>
                    <div class="weekday">Tue</div>
                    <div class="weekday">Wed</div>
                    <div class="weekday">Thu</div>
                    <div class="weekday">Fri</div>
                    <div class="weekday">Sat</div>
                </div>

                <!-- Calendar Grid -->
                <div id="calendar-grid">
                     <div class="calendar-day" data-day="2025-05-12">...</div>
                </div>
            </div>
        </div>

        <!-- Appointment Details Overlay -->
        <div id="appointment-overlay" class="overlay">
            <div class="overlay-content">
                <div class="overlay-header">
                    <h3 id="overlay-date" class="text-xl font-bold">May 10, 2025</h3>
                    <button id="close-overlay" class="close-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div id="appointments-container">
                    <!-- Appointment cards will be generated here -->
                </div>
                
                <div class="overlay-footer">
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-indicator" style="background-color: #dbeafe;"></div>
                            Regular appointments
                        </div>
                        <div class="legend-item">
                            <div class="legend-indicator" style="background-color: #fee2e2;"></div>
                            Urgent appointments
                        </div>
                    </div>
                    <button class="add-appointment-btn">
                        <i class="fas fa-plus"></i> Add New Appointment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="Appointment.js"></script>
    <script>
        // Initialize lucide icons
        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', function() {
            function updateDateTime() {
                const now = new Date();
                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                document.getElementById('date-time').textContent = now.toLocaleDateString('en-US', options);
            }
            updateDateTime();
            setInterval(updateDateTime, 60000);
        });
    </script>
</body>
</html>