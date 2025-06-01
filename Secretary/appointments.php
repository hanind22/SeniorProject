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
        $secretaryData = $result->fetch_assoc();
        $stmt->close();

        if (!$secretaryData) {
            $error = "Secretary record not found";
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
                <a href="Secretary_dashboard.php" class="nav-item ">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-file-medical"></i> Patients
                </a> 
                <a href="appointments.php" class="nav-item active">
                    <i class="fa-solid fa-calendar"></i> Appointments
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
    <script src="Appointment.js"></script>
</body>
</html>