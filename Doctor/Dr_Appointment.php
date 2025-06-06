<?php
session_start();
include('../db-config/connection.php');

// Show session messages
if (isset($_SESSION['message'])) {
    echo "<div style='color: green; padding: 10px; font-weight: bold;'>" . htmlspecialchars($_SESSION['message']) . "</div>";
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo "<div style='color: red; padding: 10px; font-weight: bold;'>" . htmlspecialchars($_SESSION['error']) . "</div>";
    unset($_SESSION['error']);
}

// Refresh calendar handling
if (isset($_SESSION['refresh_calendar'])) {
    unset($_SESSION['refresh_calendar']);
    echo '<script>sessionStorage.setItem("refreshCalendar", "true");</script>';
}

// Initialize variables
$doctorData = [];
$appointments = [];
$error = '';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Fetch doctor data
    $stmt = $conn->prepare("
        SELECT u.*, d.* 
        FROM users u
        JOIN doctors d ON u.user_id = d.user_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctorData = $result->fetch_assoc();
    
    if (!$doctorData) {
        $error = "Doctor profile not found.";
    } else {
        // Fetch appointments for this doctor
        $stmt = $conn->prepare("
            SELECT a.*, u.full_name AS patient_name 
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u ON p.user_id = u.user_id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_date, a.appointment_time
        ");
        $stmt->bind_param("i", $doctorData['doctor_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $date = $row['appointment_date'];
            $time = date("g:i A", strtotime($row['appointment_time']));
            
            $appointments[$date][] = [
                'id' => $row['appointment_id'],
                'time' => $time,
                'patientName' => $row['patient_name'],
                'type' => $row['appointment_type'],
                'purpose' => $row['reason_for_visit'],
                'notes' => $row['notes']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor's Appointment Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="Dr_Appointment.css">

</head>
<body>
    <?php include('notifications.php'); ?>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i> MediTrack
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
                <a href="medical_records.php" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records<br>& Prescription</span>
                </a>
                <!-- <a href="notifications.php" class="nav-item">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                    <-- <span class="alert-badge">3</span> --
                </a> -->
                <a href="profile.php" class="nav-item">
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

           <div class="doctor-info">
                    <h2>Dr. <?php echo htmlspecialchars($doctorData['full_name']); ?></h2>
                    <p class="doctor-title"> <?php echo htmlspecialchars($doctorData['specialty']); ?></p>
                    <p class="doctor-contact"> Contact: <?php echo htmlspecialchars($doctorData['email']); ?> | +961 <?php echo htmlspecialchars($doctorData['phone_number']); ?></p>
            </div>
            

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

<!-- In your add-appointment-overlay section -->
<div id="add-appointment-overlay" class="overlay">
    <div class="overlay-content">
        <div class="overlay-header">
            <h3 class="overlay-title">Add New Appointment</h3>
            <button id="close-add-overlay" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="form-container">
            <form id="appointment-form" action="process_appointment.php" method="POST">
                <input type="hidden" name="doctor_id" value="<?= htmlspecialchars($doctorData['doctor_id']) ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment-patient-id">Patient's ID</label>
                        <input type="number" class="form-control" id="appointment-patient-id" name="patient_id" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment-patient-name">Patient's Name</label>
                        <input type="text" class="form-control" id="appointment-patient-name" name="patient_name" readonly>
                    </div>
                </div>

                <!-- Rest of your form fields with name attributes -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment-type">Appointment Type</label>
                        <select class="form-control" id="appointment-type" name="type" required>
                            <option value="Regular Checkup">Regular Checkup</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Urgent Care">Urgent Care</option>
                            <option value="Consultation">Consultation</option>
                            <option value="Other">Others</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="appointment-status">Status</label>
                        <select class="form-control" id="appointment-status" name="status" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment-date">Date</label>
                        <input type="date" class="form-control" id="appointment-date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment-time">Time</label>
                        <input type="time" class="form-control" id="appointment-time" name="time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="appointment-purpose">Purpose (Reason for Visit)</label>
                    <input type="text" class="form-control" id="appointment-purpose" name="purpose" required>
                </div>

                <div class="form-group">
                    <label for="appointment-notes">Notes</label>
                    <textarea class="form-control textarea" id="appointment-notes" name="notes"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" id="cancel-appointment" class="btn btn-outline">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" name="save_appointment">
                        <i class="fas fa-save"></i> Save Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
 

        <!-- Notification -->
        <div id="notification" class="notification">
            <i class="fas fa-check-circle"></i>
            <span id="notification-message">Appointment saved successfully!</span>
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
                    <h3 class="patient-name" id="modalPatientName">No patient name</h3>
                    <p class="patient-id" id="modalPatientId">Patient ID: N/A</p>
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
                <h4 class="section-title">
                    <i class="fas fa-notes-medical"></i> Notes
                </h4>
                <p class="section-content" id="modalNotes"></p>
            </div>
        </div>
        
        <div class="edit-appointment-btn">
            <button class="btn btn-outline" id="editAppointmentBtn">
                <i class="fas fa-edit"></i> Edit Details
            </button>
            <button class="cancel-appointment-btn" id="cancelAppointmentBtn">
                <i class="fas fa-times"></i> Cancel Appointment
            </button>
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
        
        <form id="edit-appointment-form">
            <div class="appointment-meta">
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <span class="meta-label">Patient</span>
                        <span class="meta-value" id="editPatientName">No patient selected</span>
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
<!-- Cancel Appointment Modal (hidden by default) -->
<div class="overlay" id="cancelAppointmentModal" style="display: none;">
    <div class="modal">
        <button class="close-btn" id="cancelModalCloseButton" aria-label="Close modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <h2 class="modal-title">Cancel Appointment</h2>
        
        <form id="cancel-appointment-form">
    <input type="hidden" id="cancel-appointment-id" name="appointment_id">
    
    <div class="form-group">
        <label for="cancel-reason">Reason for cancellation:</label>
        <select id="cancel-reason" name="cancel_reason" required class="form-control">
            <option value="">Select a reason...</option>
            <option value="Patient Request">Patient Request</option>
            <option value="Doctor Unavailable">Doctor Unavailable</option>
            <option value="Emergency">Emergency</option>
            <option value="Other">Other</option>
        </select>
    </div>
    
    <div class="form-group" id="other-reason-container" style="display: none;">
        <label for="other-reason">Please specify:</label>
        <input type="text" id="other-reason" name="other_reason" class="form-control">
    </div>
    
    <div class="form-group">
        <label for="cancel-notes">Additional Notes:</label>
        <textarea id="cancel-notes" name="cancel_notes" class="form-control" rows="3"></textarea>
    </div>
    
    <input type="hidden" name="action" value="cancel">
    
    <div class="appointment-actions">
        <button type="button" class="btn btn-outline" id="cancelCancelBtn">
            <i class="fas fa-arrow-left"></i> Go Back
        </button>
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-times"></i> Confirm Cancellation
        </button>
    </div>
</form>
    </div>
</div>


<!-- Add this HTML right before the closing </body> tag -->
<div class="logout-overlay" id="logoutOverlay">
    <div class="logout-confirmation">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout ?</p>
        <div class="logout-buttons">
            <button class="logout-btn confirm-logout" id="confirmLogout">Yes, Logout</button>
            <button class="logout-btn cancel-logout" id="cancelLogout">Cancel</button>
        </div>
    </div>
</div>
<!-- -------------- -->

<script>
// In your HTML/PHP file, define this first
const cancelledBy = <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : 'null'; ?>;
</script>
<script src="Appointment.js"></script>

<!-- <script> window.appointmentsData = <?php echo json_encode($appointments); ?>;</script> -->
</body>
</html>

    