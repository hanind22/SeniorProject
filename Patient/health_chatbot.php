<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Register-Login/index.php");
    exit();
}

// If this is not a patient, block access
// OPTIONAL: Only if you want to limit chatbot to patients

$userId = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="Sidebar.css" />
  <title>Health Chatbot</title>
  <style>
    .content {
      padding: 30px;
    }
    body, html {
      overflow: hidden;
      margin: 0;
      height: 100%;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo">
          <i class="fas fa-heartbeat me-2"></i> MediTrack
        </div>
        <p class="speciality">Your Health Journey Starts Here</p>
      </div>
      <nav class="nav-links">
        <a href="patient_dashboard.php" class="nav-item">
          <i class="fa-solid fa-user"></i> Profile
        </a>
        <a href="appointment.php" class="nav-item">
          <i class="fa-solid fa-calendar"></i> Appointments
        </a>
        <a href="medical_history.php" class="nav-item">
          <i class="fa-solid fa-file-medical"></i> Medical History & <br>Prescriptions
        </a>
        <!-- <a href="#" class="nav-item">
          <i class="fas fa-prescription-bottle-alt"></i> Prescriptions
        </a> -->
        <a href="health_report.php" class="nav-item">
          <i class="fa-solid fa-file-lines"></i> Health Reports
        </a>
        <a href="health_chatbot.php" class="nav-item active">
          <i class="fa-solid fa-comment-medical"></i> Health Chatbot
        </a>
      </nav>
      <div class="date-time-box">
        <p id="date-time"></p>
      </div>
    </aside>

    <!-- Chatbot Content -->
    <div class="content">
      <!-- Embed Chatling with Unique Patient Session -->
      <script>
        window.chtlConfig = {
          chatbotId: "3691567812",
          display: "page_inline",
          userId: "patient_<?php echo $userId; ?>"  // Unique ID for each patient
        };
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
    
      </script>
      <div id="chtl-inline-bot" style="width: 1550px; height: 900px;"></div>
      <script async data-id="3691567812" data-display="page_inline" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
    </div>
  </div>
</body>
</html>