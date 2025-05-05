<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="doctor's_dashboard.css">
    <link rel="stylesheet" href="../Sidebar.css">
</head>
<body>
    <?php
     session_start();
    include('../db-config/connection.php');
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
                    <i class="fas fa-user-injured"></i> Appointments
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-file-medical"></i> Medical Records
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i> Presscriptions
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
                    <h2>Dr. Hanin Diab</h2>
                    <p class="doctor-title"> CardiologY</p>
                    <p class="doctor-contact">Contact: hanindiab22@gmail.com | +961 71 175 060 </p>
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
            </div>
         </div>
    </div>

<script>
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
</body>
</html>