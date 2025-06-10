<?php
session_start();
include('../db-config/connection.php');


// Initialize data
$secretaryData = [];
$doctorData = [];
$patients = [];
$error = '';
$doctor_id = null;
$search_query = '';
$totalPatients = 0;
$appointmentsToday = 0;
$urgentCases = 0;

// Handle search functionality
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        // Get secretary's basic info from users table
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
        die("Query failed: " . $stmt->error);
        }
        $secretaryData = $result->fetch_assoc();
        $stmt->close();

        if (!$secretaryData) {
            $error = "User record for ID $userId not found in 'users' table.";
        } else {
            // Get the doctor_id assigned to this secretary
            $stmt = $conn->prepare("SELECT doctor_id FROM secretary WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $secretaryInfo = $result->fetch_assoc();
            $stmt->close();

            if ($secretaryInfo && isset($secretaryInfo['doctor_id'])) {
                $doctor_id = $secretaryInfo['doctor_id'];
                
                // Get doctor's details for display
                $stmt = $conn->prepare("
                    SELECT u.*, d.* 
                    FROM users u
                    JOIN doctors d ON u.user_id = d.user_id
                    WHERE d.doctor_id = ?
                ");
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $doctorData = $result->fetch_assoc();
                $stmt->close();

                if ($doctorData) {
                    // Get statistics for the dashboard
                    // Total patients
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total 
                        FROM doctorpatient 
                        WHERE doctor_id = ?
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $patientCount = $result->fetch_assoc();
                    $totalPatients = $patientCount['total'];
                    $stmt->close();

                    // Today's appointments
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS count 
                        FROM appointments 
                        WHERE doctor_id = ? AND DATE(appointment_date) = ?
                    ");
                    $stmt->bind_param("is", $doctor_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $apptCount = $result->fetch_assoc();
                    $appointmentsToday = $apptCount['count'];
                    $stmt->close();

                    // Urgent cases
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS count 
                        FROM appointments 
                        WHERE doctor_id = ? AND appointment_type = 'Urgent Care'
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $urgentCount = $result->fetch_assoc();
                    $urgentCases = $urgentCount['count'];
                    $stmt->close();

                    // Patient list query (only if we're on the patients page)
                    if (basename($_SERVER['PHP_SELF']) == 'patients.php') {
                        // Base query for patients
                        $patient_query = "
                            SELECT 
                                p.patient_id,
                                u.user_id,
                                u.full_name,
                                u.email,
                                u.phone_number,
                                p.date_of_birth,
                                p.gender,
                                p.blood_type,
                                p.allergies,
                                p.medical_conditions,
                                p.current_medications,
                                p.previous_surgeries,
                                p.family_history,
                                p.QR_code,
                                p.health_form_completed,
                                p.insurance_provider,
                                MAX(a.appointment_date) as last_visit_date
                            FROM DoctorPatient dp
                            JOIN patients p ON dp.patient_id = p.patient_id
                            JOIN users u ON p.user_id = u.user_id
                            LEFT JOIN appointments a ON p.patient_id = a.patient_id
                            WHERE dp.doctor_id = ?
                        ";
                        
                        // Add search condition if search query exists
                        if (!empty($search_query)) {
                            $patient_query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
                        }
                        
                        $patient_query .= "
                            GROUP BY 
                                p.patient_id, u.user_id, u.full_name, u.email, u.phone_number, 
                                p.date_of_birth, p.gender, p.blood_type, p.allergies, 
                                p.medical_conditions, p.current_medications, p.previous_surgeries, 
                                p.family_history, p.QR_code, p.health_form_completed, 
                                p.insurance_provider
                            ORDER BY u.full_name ASC
                        ";
                        
                        $stmt = $conn->prepare($patient_query);
                        
                        // Bind parameters based on whether we have a search query
                        if (!empty($search_query)) {
                            $search_param = "%$search_query%";
                            $stmt->bind_param("isss", $doctor_id, $search_param, $search_param, $search_param);
                        } else {
                            $stmt->bind_param("i", $doctor_id);
                        }
                        
                        $stmt->execute();
                        $patientResult = $stmt->get_result();
                        
                        if ($patientResult->num_rows > 0) {
                            while ($row = $patientResult->fetch_assoc()) {
                                // Calculate age from date_of_birth
                                if ($row['date_of_birth']) {
                                    $dob = new DateTime($row['date_of_birth']);
                                    $now = new DateTime();
                                    $age = $dob->diff($now)->y;
                                } else {
                                    $age = 'N/A';
                                }
                                
                                // Format last visit date
                                $last_visit_formatted = 'No visits';
                                if ($row['last_visit_date']) {
                                    $last_visit = new DateTime($row['last_visit_date']);
                                    $last_visit_formatted = $last_visit->format('M j, Y');
                                }
                                
                                // Add calculated fields to patient data
                                $row['age'] = $age;
                                $row['last_visit_formatted'] = $last_visit_formatted;
                                $patients[] = $row;
                            }
                        } else {
                            $error = "No patients found" . (!empty($search_query) ? " matching your search criteria" : "");
                        }
                    }
                } else {
                    $error = "Doctor record not found";
                }
            } else {
                $error = "No doctor assigned to this secretary";
            }
        }
    } else {
        $error = "User not logged in";
        header('Location: ../Register-Login/index.php');
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="appointments.css">
</head>
<body data-user-id="<?= $_SESSION['secretary_id'] ?? '' ?>">
    
    <?php include('notifications.php'); ?>
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
                <a href="Secretary_dashboard.php" class="nav-item ">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-file-medical"></i> Patients
                </a> 
                <a href="appointments.php" class="nav-item active">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
                <a href="#" class="nav-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <div class="content">
          <div class="doctor-info-header">
                <h2>Secretary: <?php echo htmlspecialchars($secretaryData['full_name'] ?? 'N/A'); ?></h2>
                <?php if (!empty($doctorData)): ?>
                    <p>Assigned to Dr. <?php echo htmlspecialchars($doctorData['full_name']); ?> | <?php echo htmlspecialchars($doctorData['specialty'] ?? 'N/A'); ?> </p>
                <?php endif; ?>
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
                        <i class="fas fa-plus"></i> Add New Appointment
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
                    <h3 class="overlay-title" id="overlay-date"></h3>
                    <button id="close-overlay" class="close-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="appointments-container" id="appointments-container">
                    <!-- Appointment cards will be generated here -->
                </div>
                
                <div class="overlay-footer">
                    <!-- Add this new button -->
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
                     <button id="cancel-all-appointments" class="btn btn-danger btn-sm" style="display: none;" >
                           Cancel All Appointments
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
        <button class="close-btn" id="editModalCloseButton" aria-label="Close modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <!-- Secretary ID stored here (outside the form is okay) -->
        <input type="hidden" id="session-user-id" value="<?php echo $_SESSION['user_id']; ?>">

        <button class="close-btn" id="cancelModalCloseButton" aria-label="Close modal">
            <!-- Close icon -->
        </button>
        <h2 class="modal-title">Cancel Appointment</h2>
        
        <form id="cancel-appointment-form">
            <!-- Only one hidden input for appointment ID -->
            <input type="hidden" id="cancel-appointment-id" name="appointment_id" value="">
            
            <div class="appointment-meta">
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <span class="meta-label">Patient</span>
                        <span class="meta-value" id="cancelPatientName"></span>
                    </div>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <span class="meta-label">Time</span>
                        <span class="meta-value" id="cancelAppointmentTime"></span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="cancel-reason">Reason for cancellation:</label>
                <select id="cancel-reason" name="cancel_reason" required class="form-control">
                    <option value="">Select a reason...</option>
                    <option value="Patient Request">Patient Request</option>
                    <option value="Doctor Unavailable">Doctor Unavailable</option>
                    <option value="Emergency">Emergency</option>
                </select>
            </div>
            
            
            
            <div class="form-group">
                <label for="cancel-notes">Additional Notes:</label>
                <textarea id="reason_for_cancelling" name="reason_for_cancelling" class="form-control" rows="3"></textarea>
            </div>

            <!-- Action flag -->
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


<!-- Cancel All Appointments Modal -->
<div id="cancelAllAppointmentsModal" class="overlay">
    <div class="modal">
        <div class="modal-header">
            <button class="close-btn" id="cancelAllModalCloseButton">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="modal-title">Cancel All Appointments</h3>
            <p class="modal-subtitle">This Action Cannot Be Undone</p>
        </div>
        
        <div class="modal-content">
            <p class="warning-text">
                You are about to cancel <strong>All Appointments</strong> for 
                <span id="cancelAllDateText" data-date="2025-06-01"> the selected date</span>. 
                Please confirm this action and select a reason for cancellation.
            </p>
            
            <form id="cancelAllAppointmentsForm">
                <div class="form-group">
                    <label class="form-label" for="cancelAllReason">
                        Reason for Cancellation <span class="required">*</span>
                    </label>
                    <select class="form-control" id="cancelAllReason" name="reason" required>
                        <option value="">Select a reason...</option>
                        <option value="Doctor is unavailable">Doctor is Unavailable</option>
                        <option value="Holiday">Holiday</option>
                        <option value="Emergency Case">Emergency Case</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                
                <div class="form-group" id="otherReasonContainer" style="display: none;">
                    <label class="form-label" for="cancelAllOtherReason">
                        Please specify <span class="required">*</span>
                    </label>
                    <textarea 
                        class="form-control textarea" 
                        id="cancelAllOtherReason" 
                        name="other_reason" 
                        placeholder="Please provide details about the reason for cancellation..."
                    ></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="cancelAllModalCancelBtn">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> Yes Cancel 
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

<!-- Logout Overlay -->
    <div class="logout-overlay" id="logoutOverlay">
        <div class="logout-confirmation">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="logout-buttons">
                <button class="logout-btn confirm-logout" id="confirmLogout">Yes, Logout</button>
                <button class="logout-btn cancel-logout" id="cancelLogout">Cancel</button>
            </div>
        </div>
    </div>
<script>
    let currentDoctorId = <?php echo json_encode($doctor_id ?? null); ?>;
        // Shows the cancel all appointments modal
   function showCancelAllModal(date) {
    const modal = document.getElementById('cancelAllAppointmentsModal');
    if (modal) {
        // Store the date in the modal's dataset
        modal.dataset.date = date;
        
        // Update the displayed date text
        const dateText = document.getElementById('cancelAllDateText');
        if (dateText) {
            const displayDate = new Date(date);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateText.textContent = displayDate.toLocaleDateString('en-US', options);
        }
        
        modal.classList.add('active');
    }
}
       
document.addEventListener('DOMContentLoaded', function() {
    // Get the logout elements
    const logoutLink = document.querySelector('.nav-links .nav-item:last-child');
    const logoutOverlay = document.getElementById('logoutOverlay');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    // Show overlay when logout is clicked
    logoutLink.addEventListener('click', function(e) {
        e.preventDefault();
        logoutOverlay.classList.add('show');
    });

    // Hide overlay when cancel is clicked
    cancelLogout.addEventListener('click', function() {
        logoutOverlay.classList.remove('show');
    });

    // Handle actual logout
    confirmLogout.addEventListener('click', function() {
        // In a real implementation, this would redirect to your logout script
        window.location.href = '../Welcome/Index.php';
        
        // For demonstration, we'll just show an alert
        // alert('Logging out...');
        // logoutOverlay.classList.remove('show');
    });

    // Close overlay when clicking outside the confirmation box
    logoutOverlay.addEventListener('click', function(e) {
        if (e.target === logoutOverlay) {
            logoutOverlay.classList.remove('show');
        }
    });
});
</script>
    <script src="Appointment.js"></script>
</body>
</html>