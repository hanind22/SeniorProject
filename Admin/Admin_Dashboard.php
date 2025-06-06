<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
                <a href="doctor_dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Our Data
                </a>
                <a href="Dr_Appointment.php" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Doctors
                </a>
                
                <a href="#" class="nav-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
         </aside>

        
</div>
</body>
</html>