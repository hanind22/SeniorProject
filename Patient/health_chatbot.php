<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Sidebar.css">
    <title>Document</title>
    <style>
        .content{
            padding: 30px;
           
        }
        body, html {
  overflow: hidden; /* Disables scrolling */
  margin: 0;
  height: 100%;
}
    </style>
</head>
<body>
 <div class="container">
        <!-- Side-Navigationbar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat me-2"></i> MediTrack
                </div>
                <p class="speciality">Your Health Journey Starts Here</p>
            </div>
            <nav class="nav-links">
                <a href="doctor_dashboard.php" class="nav-item ">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="#" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
                <a href="#" class="nav-item">
                    <i class="fa-solid fa-file-medical"></i> Medical History
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-prescription-bottle-alt"></i> Prescriptions
                </a>
                <a href="#" class="nav-item">
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

        <div class="content">    
         <script> window.chtlConfig = { chatbotId: "3691567812", display: "page_inline" } </script>
<div id="chtl-inline-bot" style="width: 1550px; height: 900px;"></div>
<script async data-id="3691567812" data-display="page_inline" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
        </div>
</div>

</body>
</html>