<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link rel="stylesheet" href="doctor's_dashboard.css">
    <link rel="stylesheet" href="../Sidebar.css">
</head>
<body>
    <?php
     session_start();
    include('../db-config/connection.php');

    // Initialize patient data
    $doctorData = [];
    $error = '';

    try {
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        
            // Join users and doctors tables to get all needed data
            $stmt = $conn->prepare("
                SELECT u.*, d.* 
                FROM users u
                LEFT JOIN doctors d ON u.user_id = d.user_id
                WHERE u.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $doctorData = $result->fetch_assoc();
            } else {
                $error = "Doctor record not found";
            }
        }

    } catch (Exception $e) {
        $error = "Error fetching doctor data: " . $e->getMessage();
    }
    ?>

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
                <a href="doctor_dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="#" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-file-medical"></i> Medical Records
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i> Presscriptions
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-bell"></i> Notifications
                    <span class="alert-badge">3</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-user-md"></i> Profile
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
         </aside>

         <div class="content">
            <div class="doctor-info">
                    <h2>Dr. <?php echo htmlspecialchars($doctorData['full_name']); ?></h2>
                    <p class="doctor-title"> <?php echo htmlspecialchars($doctorData['specialty']); ?></p>
                    <p class="doctor-contact"> Contact: <?php echo htmlspecialchars($doctorData['email']); ?> | +961 <?php echo htmlspecialchars($doctorData['phone_number']); ?></p>
                    </div>
            <div class="summary-cards">
                <div class="summary-card">
                    <h3><i class="fas fa-users"></i> Total Patients</h3>
                    <p>5</p>
                </div>
                <div class="summary-card">
                    <h3><i class="fas fa-calendar-check"></i> Appointments Today</h3>
                    <p>2</p>
                </div>
                <div class="summary-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Critical Cases</h3>
                    <p>6</p>
                </div>
                <div class="summary-card">
                    <h3><i class="fas fa-clipboard-list"></i> Test Results</h3>
                    <p>2</p>
                </div>
            </div>
            <!-- Notifications Section -->
            <div class="notifications-card" id="notifications-section">
                <h3><i class="fas fa-bell"></i> Recent Notifications</h3>
                <ul class="notification-list">
                    <li class="notification-item">
                        <div class="notification-icon urgent">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="notification-content">
                            <h4>Emergency: Patient Vitals Alert</h4>
                            <p>Sarah Johnson's blood pressure has reached critical levels</p>
                        </div>
                        <span class="notification-time">10 mins ago</span>
                    </li>
                    <li class="notification-item">
                        <div class="notification-icon normal">
                            <i class="fas fa-flask"></i>
                        </div>
                        <div class="notification-content">
                            <h4>Test Results Ready</h4>
                            <p>Blood work results for Michael Smith are available for review</p>
                        </div>
                        <span class="notification-time">1 hour ago</span>
                    </li>
                    <li class="notification-item">
                        <div class="notification-icon normal">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="notification-content">
                            <h4>Appointment Confirmation</h4>
                            <p>New appointment scheduled with Emily Davis at 3:30 PM</p>
                        </div>
                        <span class="notification-time">2 hours ago</span>
                    </li>
                </ul>
            </div>
            
            <!-- Data Visualization Section -->
            <div class="data-section">
                <div class="chart-card">
                    <h3>Weekly Appointments</h3>
                    <div class="chart-container">
                        <canvas id="weeklyAppointmentsChart"></canvas>
                    </div>
                </div>
            </div>
         </div>
    </div>

         </div>
    </div>

<script >
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
    })
</script>
<script src="doctor's_dashboard.js"></script>
</body>
</html>