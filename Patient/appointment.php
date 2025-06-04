<?php
session_start();
include('../db-config/connection.php');

// Show session messages with improved styling and auto-dismiss
if (isset($_SESSION['message'])) {
    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['message']) . "</div>";
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['error']) . "</div>";
    unset($_SESSION['error']);
}

// Initialize variables
$patientData = [];
$appointments = [];
$error = '';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Fetch patient data with error handling
    try {
        $stmt = $conn->prepare("
            SELECT u.*, p.* 
            FROM users u
            JOIN patients p ON u.user_id = p.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $patientData = $result->fetch_assoc();
        
        if (!$patientData) {
            $error = "Patient profile not found.";
        }
    } catch (Exception $e) {
        $error = "Error fetching data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Appointment Section</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="appointmentStyle.css">
    <style>
        /* Enhanced CSS for better UI/UX */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideDown 0.3s ease-out;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert-close {
            cursor: pointer;
            font-weight: bold;
            margin-left: 15px;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Improved loading states */
        .loading-container {
            display: flex;
            justify-content: center;
            padding: 2rem;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        /* Enhanced form controls */
        .form-control {
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        /* Better responsive design */
        @media (max-width: 768px) {
            .doctor-selection-steps {
                overflow-x: auto;
                white-space: nowrap;
                display: flex;
                padding-bottom: 10px;
            }
            
            .step {
                display: inline-block;
                min-width: 120px;
            }
            
            .time-slot-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .main-content h2{
           font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
           color:rgb(31, 64, 99);
           font-weight: 650;
           margin-bottom: 20px;
         }
        
        /* Appointment preview styles */
        .appointment-previews {
            margin-top: 5px;
        }
        
        .appointment-preview {
            font-size: 0.75rem;
            padding: 2px 5px;
            margin: 2px 0;
            border-radius: 3px;
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .appointment-preview .appointment-time {
            margin-right: 5px;
        }
        
        .appointment-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
        
        /* Cancelled appointment styling */
        .cancelled {
            text-decoration: line-through;
            opacity: 0.7;
        }
        
        .strikethrough {
            text-decoration: line-through;
        }
        
        /* Appointment card styles */
        .appt-card {
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background-color: white;
        }
        
        .appt-card__header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .appt-time {
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .appt-time i {
            margin-right: 5px;
        }
        
        .appt-type {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .appt-card__body {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .patient-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-name {
            font-size: 1rem;
            margin: 0;
        }
        
        .patient-purpose {
            font-size: 0.85rem;
            color: #666;
            margin: 0;
        }
        
        .appt-card__footer {
            display: flex;
            justify-content: flex-end;
        }
        
        .appt-action {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .appt-action:hover {
            background-color: rgba(74, 111, 165, 0.1);
        }
        
        .cancelled-appointment {
            opacity: 0.7;
            background-color: #f5f5f5;
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
                <a href="patient_dashboard.php" class="nav-item">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="appointment.php" class="nav-item active">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
                <a href="medical_history.php" class="nav-item">
                    <i class="fa-solid fa-file-medical"></i> Medical History &<br>Prescriptions
                </a>
                <a href="health_report.php" class="nav-item">
                    <i class="fa-solid fa-file-lines"></i> Health Reports
                </a>
                <a href="health_chatbot.php" class="nav-item">
                    <i class="fa-solid fa-comment-medical"></i> Health Chatbot
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Calendar Navigation -->
            <div class="calendar-nav">
                <div class="month-nav">
                    <button id="prev-month" class="month-nav-btn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h3 id="current-month">May 2025</h3>
                    <button id="next-month" class="month-nav-btn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="calendar-actions">
                    <button id="add-appointment-btn" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Appointment
                    </button>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-wrapper">
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
                     <!-- JavaScript will populate this -->
                </div>
            </div>
        </div>

        <!-- Appointment Details Overlay -->
        <div id="appointment-overlay" class="overlay">
            <div class="overlay-content">
                <div class="overlay-header">
                    <h3 class="overlay-title" id="overlay-date">May 10, 2025</h3>
                    <button id="close-overlay" class="close-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="appointments-container" id="appointments-container">
                    <!-- Appointment cards will be generated here -->
                </div>
                
                <div class="overlay-footer">
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-indicator" style="background-color: var(--regular-bg);"></div>
                            Regular appointments
                        </div>
                        <div class="legend-item">
                            <div class="legend-indicator" style="background-color: var(--urgent-bg);"></div>
                            Urgent appointments
                        </div>
                    </div>
                    <button id="add-appointment-day-btn" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Appointment
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- New Appointment Modal -->
<div class="overlay-appoinr" id="newAppointmentModal" style="display: none;">
    <div class="modal-new">
        <button class="close-btn" id="newModalCloseButton" aria-label="Close modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <h2 class="modal-title">New Appointment</h2>
        
        <form id="new-appointment-form" data-patient-id="<?php echo $_SESSION['user_id'] ?? 0; ?>">
            <!-- Speciality Dropdown -->
            <div class="form-group">
                <label for="speciality">Speciality:</label>
                <select id="speciality" name="speciality" required class="form-control">
                    <option value="">Select a speciality</option>
                    <option value="Cardiology">Cardiology</option>
                    <option value="Neurology">Neurology</option>
                    <option value="Pediatrics">Pediatrics</option>
                    <option value="Orthopedics">Orthopedics</option>
                    <option value="Dermatology">Dermatology</option>
                    <option value="General">General Practice</option>
                </select>
            </div>
            
            <!-- Doctor Dropdown (will be populated dynamically) -->
            <div class="form-group">
                <label for="doctor">Doctor:</label>
                <select id="doctor" name="doctor_id" required class="form-control" disabled>
                    <option value="">Select a doctor</option>
                </select>
            </div>
            
            <!-- Date and Time -->
            <div class="form-group">
                <label for="appointment-date">Date:</label>
                <input type="date" id="appointment-date" name="date" required class="form-control" min="">
            </div>
            
            <div class="form-group">
                <label for="appointment-time">Time:</label>
                <input type="time" id="appointment-time" name="time" required class="form-control">
            </div>
            
            <!-- Appointment Details -->
            <div class="form-group">
                <label for="appointment-type">Appointment Type:</label>
                <select id="appointment-type" name="appointment_type" required class="form-control">
                    <option value="Regular Checkup">Regular Checkup</option>
                    <option value="Follow-up">Follow-up</option>
                    <option value="Urgent Care">Urgent Care</option>
                    <option value="Consultation">Consultation</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="appointment-purpose">Purpose:</label>
                <input type="text" id="appointment-purpose" name="purpose" required class="form-control" placeholder="Briefly describe the reason for your visit">
            </div>
            
            <div class="form-group">
                <label for="appointment-notes">Notes:</label>
                <textarea id="appointment-notes" name="notes" class="form-control" rows="3" placeholder="Any additional information..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" id="cancelNewAppointment">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i> Book Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Appointment Details Overlay Modal -->
<div class="overlay" id="appointmentModal">
    <div class="modal">
    <button class="close-btn" id="modalCloseButton"  aria-label="Close modal">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
    </button> 
    <h2 class="modal-title">Appointment Details</h2>
        
        <div class="appointment-header">
            <div class="patient-info">
                <div class="patient-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="patient-details">
                    <h3 class="doctor-name" id="modalPatientName">No doctor name</h3>
                    <p class="doctor-id" id="modalPatientId">Doctor ID: N/A</p>
                    <p class="appointment-id" id="modalAppointmentId">Appointment ID: N/A</p>
                </div>
            </div>
        </div>
        
        <div class="appointment-meta">
            <div class="meta-item">
                <i class="fas fa-clock"></i>
                <div>
                    <span class="meta-label">Time</span>
                    <span class="meta-value" id="modalTime">No time specified</span>
                </div>
            </div>
            <div class="meta-item">
                <i class="fas fa-stethoscope"></i>
                <div>
                    <span class="meta-label">Type</span>
                    <span class="meta-value" id="modalType">No type specified</span>
                </div>
            </div>
        </div>
        
        <div class="appointment-content">
            <div class="content-section">
                <span class="section-title">
                    <i class="fas fa-clipboard"></i> <span class="meta-label"> Purpose</span>
                </span>
                <span class="section-content" id="modalPurpose">No purpose specified</span>
            </div>
            
            <div class="content-section" id="modalNotesSection" style="display: none;">
                <span class="section-title">
                    <i class="fas fa-notes-medical"></i> Notes
                </span>
                <p class="section-content" id="modalNotes"></p>
            </div>
        </div>
        
        <div class="edit-appointment-btn"  id="editAppointmentControls">
            <button class="btn btn-outline" id="editAppointmentBtn">
                <i class="fas fa-edit"></i> Edit Details
            </button>
            <button class="cancel-appointment-btn" id="cancelAppointmentBtn">
                <i class="fas fa-times"></i> Cancel Appointment
            </button>
        </div>
    </div>
</div>
<!-- Cancel Confirmation Modal -->
<div class="overlay" id="cancelModal" style="display: none;">
    <div class="modal small">
        <button class="close-btn" id="cancelModalCloseBtn" aria-label="Close cancel modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>
        <h3 class="modal-title">Confirm Cancellation</h3>
        <p id="cancelWarningText">
            You are about to cancel this appointment with <strong id="cancelDoctorName">Dr. N/A</strong> 
            on <strong id="cancelDate">DATE</strong> at <strong id="cancelTime">TIME</strong>.
            <br>This step cannot be undone. Are you sure?
        </p>

        <label for="cancelReasonInput">Select a reason for cancellation:</label>
        <select id="cancelReasonInput" class="form-control" style="margin: 10px 0;">
            <option value="">-- Select a reason --</option>
            <option value="No Longer Need the appointment">No longer needed</option>
            <option value="Need to reschedule another appointment">Need to reschedule</option>
            <option value="Emergency case">Unexpected emergency</option>
            <option value="Otehrs">Other</option>
        </select>

        <div style="margin-top: 10px; display: flex; gap: 10px;">
            <button id="confirmCancelBtn" class="btn btn-danger">Yes, Cancel</button>
            <button id="denyCancelBtn" class="btn btn-secondary">No</button>
        </div>
    </div>
</div>


<!-- Edit Appointment Modal (hidden by default) -->
<div class="overlay" id="editAppointmentModal" style="display: none;">
    <div class="modal">
        <button class="close-btn" id="editModalCloseButton" aria-label="Close modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <h2 class="modal-title">Edit Appointment</h2>
        
        <form id="edit-appointment-form"  method="POST" action="update_appointment.php">
            <input type="hidden" name="appointment_id" value="...">
            <div class="appointment-meta">
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <span class="meta-label">Doctor</span>
                        <span class="meta-value" id="editDoctorName">No doctor selected</span>
                    </div>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <span class="meta-label">Current Time</span>
                        <span class="meta-value" id="editCurrentTime">No time selected</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit-appointment-time">New Time:</label>
                <div class="time-selection">
                    <input type="time" id="edit-appointment-time" name="appointment_time" required class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit-appointment-notes">Notes:</label>
                <textarea id="edit-appointment-notes" name="notes" class="form-control" rows="3" placeholder="Add any additional notes..."></textarea>
            </div>
            
            <input type="hidden" id="edit-appointment-id" name="appointment_id">
            
            <div class="appointment-actions">
                <button type="button" class="btn btn-outline" id="cancelEditBtn">
                    <i class="fas fa-arrow-left"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<div id="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
<script src="Appointments.js"></script>
</body>
</html>