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
        } else {
            // Fetch appointments with pagination
            $stmt = $conn->prepare("
                SELECT a.*, u.full_name AS doctor_name, d.specialty
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.doctor_id
                JOIN users u ON d.user_id = u.user_id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date, a.appointment_time
                LIMIT 20
            ");
            $stmt->bind_param("i", $patientData['patient_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $date = $row['appointment_date'];
                $time = date('H:i', strtotime($row['appointment_time'])); 
                
                $appointments[$date][] = [
                    'id' => $row['appointment_id'],
                    'time' => $time,
                    'formatted_time' => date('g:i A', strtotime($row['appointment_time'])), // Add formatted time
                    'doctorName' => $row['doctor_name'],
                    'specialty' => $row['specialty'],
                    'type' => $row['appointment_type'],
                    'purpose' => $row['reason_for_visit'],
                    'status' => $row['status'],
                    'notes' => $row['notes'] ?? ''
                ];
            }
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
        <!-- <a href="#" class="nav-item">
          <i class="fas fa-prescription-bottle-alt"></i> Prescriptions
        </a> -->
        <a href="health_report.php" class="nav-item">
          <i class="fa-solid fa-file-lines"></i> Health Reports
        </a>
        <a href="health_chatbot.php" class="nav-item ">
          <i class="fa-solid fa-comment-medical"></i> Health Chatbot
        </a>
      </nav>
      <div class="date-time-box">
        <p id="date-time"></p>
      </div>
    </aside>

        <!-- Main Content -->
        <div class="main-content">
             <h2><i class="fas fa-calendar-plus"></i> Book a New Appointment</h2>
            <!-- Doctor Selection Process -->
            <div class="doctor-selection-container">  
                <!-- Progress Steps -->
                <div class="doctor-selection-steps">
                    <div class="step active" id="step1">
                        <div class="step-indicator">1</div>
                        <div>Select Specialty</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-indicator">2</div>
                        <div>Choose Doctor</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-indicator">3</div>
                        <div>Select Time</div>
                    </div>
                    <div class="step" id="step4">
                        <div class="step-indicator">4</div>
                        <div>Confirm</div>
                    </div>
                    <div class="step-connector">
                        <div class="step-connector-progress" id="stepProgress"></div>
                    </div>
                </div>
                
                <div class="selection-content">
                    <!-- Step 1: Select Specialty -->
                    <div id="step1-content" class="step-content fade-in">
                        <div class="form-group">
                            <label for="specialty-select"><i class="fas fa-stethoscope"></i> Select a medical specialty:</label>
                            <select class="form-control" id="specialty-select" required>
                                <option value="">-- Choose your needed specialty --</option>
                                <?php
                                // Fetch all specialties from the database
                                $specialtyQuery = $conn->query("SELECT DISTINCT specialty FROM doctors WHERE specialty IS NOT NULL AND specialty != '' ORDER BY specialty");
                                while ($specialty = $specialtyQuery->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($specialty['specialty']) . "'>" . htmlspecialchars($specialty['specialty']) . "</option>";
                                }
                                ?>
                            </select>
                            <button id="next-to-doctors" class="btn btn-primary next-step-btn" disabled>
                                 Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Choose Doctor -->
                    <div id="step2-content" class="step-content" style="display: none;">
                        <div id="doctors-list">
                            <div class="empty-state">
                                <i class="fas fa-user-md"></i>
                                <p>Please select a specialty first.</p>
                            </div>
                        </div>
                        <div class="step-navigation">
                            <button id="back-to-specialty" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button id="next-to-time" class="btn btn-primary next-step-btn" disabled>
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Select Time -->
                    <div id="step3-content" class="step-content" style="display: none;">
                        <div id="doctor-selected-info" class="doctor-card selected">
                            <div class="doctor-avatar" id="selected-doctor-avatar">DR</div>
                            <div class="doctor-info">
                                <h4 class="doctor-name" id="selected-doctor-name">Doctor Name</h4>
                                <p class="doctor-specialty" id="selected-doctor-specialty">Specialty</p>
                                <p class="doctor-contact" id="selected-doctor-email">Email: loading...</p>
                                <p class="doctor-contact" id="selected-doctor-phone">Phone: loading...</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment-date"><i class="fas fa-calendar-alt"></i> Select a date:</label>
                            <input type="date" class="form-control" id="appointment-date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div id="time-slots-container">
                            <div class="empty-state">
                                <i class="fas fa-clock"></i>
                                <p>Please select a date to see available time slots.</p>
                            </div>
                        </div>
                        
                        <div class="step-navigation">
                            <button id="back-to-doctors" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button id="next-to-confirm" class="btn btn-primary next-step-btn" disabled>
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Confirmation -->
                    <div id="step4-content" class="step-content" style="display: none;">
                        <h3><i class="fas fa-clipboard-check"></i> Confirm Your Appointment</h3><br>
                        
                        <div class="confirmation-details">
                        <div class="detail-row">
                             <div class="detail-label">Doctor:</div>
                             <div class="detail-value" id="confirm-doctor-name">Not selected</div>
                        </div>
                        <div class="detail-row">
                           <div class="detail-label">Specialty:</div>
                           <div class="detail-value" id="confirm-specialty">Not selected</div>
                        </div>
                        <div class="detail-row">
                             <div class="detail-label">Date:</div>
                             <div class="detail-value" id="confirm-date">June 15, 2023</div>
                        </div>
                        <div class="detail-row">
                             <div class="detail-label">Time:</div>
                             <div class="detail-value" id="confirm-time">10:30 AM</div>
                        </div>
                     </div>

                         <div class="form-group">
                           <label for="appointment-type">Appointment Type:</label>
                           <select class="form-control" id="appointment-type" required>
                              <option value="">-- Select the type of your Appointment --</option>
                              <option value="Regular checkup">Regular checkup</option>
                              <option value="Follow-up">Follow-up</option>
                              <option value="Consultation">Consultation</option>
                              <option value="Urgent care">Urgent care</option>
                              <option value="Other">Other</option>
                          </select>
                        </div>

                        
                        <div class="form-group">
                            <label for="appointment-purpose"><i class="fas fa-clipboard-list"></i> Purpose of Visit:</label>
                            <input type="text" class="form-control" id="appointment-purpose" placeholder="Briefly describe the reason for your visit" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment-notes"><i class="fas fa-sticky-note"></i> Additional Notes (optional):</label>
                            <textarea class="form-control" id="appointment-notes" rows="3" placeholder="Any additional information you'd like to share"></textarea>
                        </div>
                        
                        <div class="step-navigation">
                            <button id="back-to-time" class="btn btn-outline">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button id="confirm-appointment" class="btn btn-success">
                                <i class="fas fa-calendar-check"></i> Confirm Appointment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Existing Appointments -->
            <div class="calendar-nav">
                <div class="d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-calendar-week"></i> Your Upcoming Appointments</h3>
                    <button id="refresh-appointments" class="btn btn-outline">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div class="calendar-wrapper">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                        <span class="alert-close">&times;</span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($appointments)): ?>
                  <div class="appointments-list">
                   <?php foreach ($appointments as $date => $dayAppointments): ?>
                  <div class="appointment-date-header">
                     <h4><i class="fas fa-calendar-day"></i> <?php echo date('F j, Y', strtotime($date)); ?></h4>
                  </div>
               <?php foreach ($dayAppointments as $appointment): ?>
                 <!-- Updated appointment card with data attributes -->
                 <div class="appt-card <?php echo strtolower(str_replace(' ', '-', $appointment['status'])); ?> slide-up"
                     data-appointment='<?php echo json_encode([
                        'id' => $appointment['id'],
                        'time' => $appointment['time'],
                        'formatted_time' => $appointment['formatted_time'],
                        'doctor' => $appointment['doctorName'],
                        'specialty' => $appointment['specialty'],
                        'type' => $appointment['type'],
                        'status' => $appointment['status'],
                        'purpose' => $appointment['purpose'],
                        'notes' => $appointment['notes']
                     ]); ?>'>
                    <div class="appt-card__header">
                        <div class="appt-time">
                            <i class="fas fa-clock"></i>
                            <?php echo $appointment['formatted_time']; ?>
                            <span class="appointment-status-badge" style="
                                background: <?php 
                                    if ($appointment['status'] == 'scheduled') echo '#dbeafe';
                                    elseif ($appointment['status'] == 'confirmed') echo '#d1fae5';
                                    elseif ($appointment['status'] == 'completed') echo '#e5e7eb';
                                    else echo '#fee2e2';
                                ?>;
                                color: <?php 
                                    if ($appointment['status'] == 'scheduled') echo '#1e40af';
                                    elseif ($appointment['status'] == 'confirmed') echo '#065f46';
                                    elseif ($appointment['status'] == 'completed') echo '#4b5563';
                                    else echo '#991b1b';
                                ?>;
                            ">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                        <div class="appt-type">
                            <?php echo $appointment['type']; ?>
                        </div>
                    </div>
                    <div class="appt-card__body">
                        <div class="patient-avatar">
                            <?php 
                                $initials = '';
                                $nameParts = explode(' ', $appointment['doctorName']);
                                foreach ($nameParts as $part) {
                                    $initials .= strtoupper(substr($part, 0, 1));
                                }
                                echo $initials;
                            ?>
                        </div>
                        <div class="patient-info">
                            <h4 class="patient-name">Dr. <?php echo $appointment['doctorName']; ?></h4>
                            <p class="patient-purpose"><?php echo $appointment['specialty']; ?></p>
                            <p class="patient-purpose"><?php echo $appointment['purpose']; ?></p>
                        </div>
                    </div>
                    <div class="appt-card__footer">
    <?php if ($appointment['status'] !== 'Cancelled'): ?>
        <button class="btn btn-danger cancelAppointmentBtn">
            <i class="fas fa-times"></i> Cancel Appointment
        </button>
        <?php if ($appointment['status'] == 'scheduled' || $appointment['status'] == 'confirmed'): ?>
            <button class="appt-action btn-reschedule" data-appointment-id="<?php echo $appointment['id']; ?>">
                <i class="fas fa-calendar-alt"></i> Reschedule
            </button>
        <?php endif; ?>
    <?php else: ?>
        <div class="cancelled-message">
            <i class="fas fa-ban"></i> Appointment Cancelled
        </div>
    <?php endif; ?>
</div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h4>No Appointments Yet</h4>
        <p>You don't have any upcoming appointments. Book one now!</p>
    </div>
<?php endif; ?>
            </div>
        </div>


<!-- Cancel Appointment Modal -->
<div class="overlay" id="cancelAppointmentModal">
    <div class="modal">
        <button class="close-btn" id="cancelModalCloseButton" aria-label="Close modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <h2 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Cancel Appointment</h2>
        
        <form id="cancel-appointment-form">
            <div class="appointment-meta">
                <div class="meta-item">
                    <i class="fas fa-user-md"></i>
                    <div>
                        <span class="meta-label">Doctor</span>
                        <span class="meta-value" id="cancelDoctorName">
                            <?php echo isset($appointment['doctorName']) ? htmlspecialchars($appointment['doctorName']) : 'No doctor name'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <span class="meta-label">Time</span>
                        <span class="meta-value" id="cancelAppointmentTime"><?php echo isset($appointment['formatted_time']) ? htmlspecialchars($appointment['formatted_time']) : 'No Time'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="cancel-reason"><i class="fas fa-question-circle"></i> Reason for cancellation:</label>
                <select id="cancel-reason" name="cancelled_reason" required class="form-control">
                    <option value="">Select a reason...</option>
                    <option value="I no longer need this appointment">I no longer need this appointment</option>
                    <option value="Personal reasons">Personal reasons</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group" id="other-reason-container" style="display: none;">
                <label for="other-reason">Please specify:</label>
                <input type="text" id="other-reason" name="other_reason" class="form-control" placeholder="Enter your reason...">
            </div>
            
            <input type="hidden" id="cancel-appointment-id" name="appointment_id" value="<?php echo isset($appointment['id']) ? htmlspecialchars($appointment['id']) : ''; ?>">
            <input type="hidden" id="cancelled-by" name="cancelled_by" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">
            
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



<!-- message for successfully setting an appointment -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
              <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>


    <!-- Add jQuery for AJAX functionality -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function () {
    // Initialization variables
    let currentStep = 1;
    let selectedSpecialty = '';
    let selectedDoctor = null;
    let selectedDate = '';
    let selectedTime = '';
    let currentAppointmentId = null;

    // DOM elements
    const $notification = $('#notification');
    const $notificationMessage = $('#notification-message');
    const $appointmentModal = $('#appointmentModal');
    const $cancelAppointmentModal = $('#cancelAppointmentModal');

    // Initialize everything
    initEventListeners();
    updateStepProgress();

    function initEventListeners() {
        // General UI listeners
        $('.alert-close').click(function () {
            $(this).parent().fadeOut();
        });
        setTimeout(() => $('.alert').fadeOut(), 5000);

        // Appointment booking flow
        $('#specialty-select').change(handleSpecialtyChange);
        $('#next-to-doctors').click(handleNextToDoctors);
        $('#back-to-specialty').click(() => showStep(1));
        $('#next-to-time').click(handleNextToTime);
        $('#appointment-date').change(handleDateChange);
        $('#back-to-doctors').click(() => showStep(2));
        $('#next-to-confirm').click(handleNextToConfirm);
        $('#back-to-time').click(() => showStep(3));
        $('#confirm-appointment').click(handleConfirmAppointment);

        // Appointment management
        $(document).on('click', '.view-details', handleViewDetails);
        $(document).on('click', '.btn-reschedule', handleReschedule);
        $(document).on('click', '#refresh-appointments', () => location.reload());

        // Time slot selection
        $(document).on('click', '.time-slot:not(.booked)', function () {
            $('.time-slot').removeClass('selected');
            $(this).addClass('selected');
            selectedTime = $(this).data('time');
            $('#next-to-confirm').prop('disabled', false);
            updateConfirmationDisplay();
        });

        // Doctor selection
        $(document).on('click', '.doctor-card', function () {
            $('.doctor-card').removeClass('selected');
            $(this).addClass('selected');
            selectedDoctor = $(this).data('doctor-id');

            const name = $(this).data('name') || '';
            const specialty = $(this).data('specialty') || '';
            const email = $(this).data('email') || '';
            const phone = $(this).data('phone') || '';

            $('#selected-doctor-name').text(name);
            $('#selected-doctor-specialty').text(specialty);
            $('#selected-doctor-email').text(`Email: ${email}`);
            $('#selected-doctor-phone').text(`Phone: ${phone}`);

            const initials = name.split(' ').map(n => n[0]).join('').toUpperCase();
            $('#selected-doctor-avatar').text(initials);

            $('#next-to-time').prop('disabled', false);
        });

        // Cancel appointment modal
        $(document).on('click', '.cancelAppointmentBtn', showCancelModal);
        $('#cancel-reason').change(handleCancelReasonChange);
        $('#cancel-appointment-form').submit(handleCancelAppointment);
        $('#cancelCancelBtn').click(() => {
            $cancelAppointmentModal.hide();
            $appointmentModal.show();
        });

        // Modal close buttons
        $('#modalCloseButton, #cancelModalCloseButton').click(() => {
            $appointmentModal.hide();
            $cancelAppointmentModal.hide();
        });
    }

    function updateStepProgress() {
        const progress = ((currentStep - 1) / 3) * 100;
        $('#stepProgress').css('width', progress + '%');

        $('.step').removeClass('active completed');
        for (let i = 1; i <= 4; i++) {
            const $step = $('#step' + i);
            if (i < currentStep) $step.addClass('completed');
            else if (i === currentStep) $step.addClass('active');
        }
    }

    function showStep(step) {
        $('.step-content').hide();
        $('#step' + step + '-content').fadeIn();
        currentStep = step;
        updateStepProgress();
        $('html, body').animate({
            scrollTop: $('.doctor-selection-container').offset().top - 20
        }, 300);
    }

    function updateConfirmationDisplay() {
        const [hoursRaw, minutes] = selectedTime.split(':');
        let hours = parseInt(hoursRaw);
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        const formattedTime = `${hours}:${minutes} ${ampm}`;

        const dateObj = new Date(selectedDate);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });

        $('#confirm-date').text(formattedDate);
        $('#confirm-time').text(formattedTime);
        
        // Add these lines to update doctor info
        $('#confirm-doctor-name').text($('#selected-doctor-name').text());
        $('#confirm-specialty').text($('#selected-doctor-specialty').text());
    }

    function handleSpecialtyChange() {
        selectedSpecialty = $(this).val();
        $('#next-to-doctors').prop('disabled', !selectedSpecialty);
    }

    function handleNextToDoctors() {
        if (!selectedSpecialty) return;

        $.ajax({
            url: 'fetch-doctors.php',
            type: 'POST',
            data: { specialty: selectedSpecialty },
            beforeSend: () => {
                $('#doctors-list').html(`
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <p>Loading doctors...</p>
                    </div>
                `);
            },
            success: (response) => {
                $('#doctors-list').html(response);
                selectedDoctor = null;
                $('#next-to-time').prop('disabled', true);
                showStep(2);
            },
            error: () => {
                showNotification('Error loading doctors. Please try again.', 'error');
                $('#doctors-list').html(`
                    <div class="alert alert-error">
                        Error loading doctors. Please try again.
                        <span class="alert-close">&times;</span>
                    </div>
                `);
            }
        });
    }

    function handleNextToTime() {
        if (!selectedDoctor) {
            showNotification('Please select a doctor', 'error');
            return;
        }
        showStep(3);
        $('#next-to-confirm').prop('disabled', true);
        selectedDate = '';
        selectedTime = '';
        $('#appointment-date').val('');
        $('#time-slots-container').empty();
    }

    function handleDateChange() {
        selectedDate = $(this).val();
        if (!selectedDate || !selectedDoctor) return;

        $.ajax({
            url: 'fetch-timeslots.php',
            type: 'POST',
            data: {
                doctor_id: selectedDoctor,
                date: selectedDate
            },
            beforeSend: () => {
                $('#time-slots-container').html(`
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <p>Loading available time slots...</p>
                    </div>
                `);
            },
            success: (response) => {
                $('#time-slots-container').html(response);
            },
            error: () => {
                showNotification('Error loading time slots. Please try again.', 'error');
                $('#time-slots-container').html(`
                    <div class="alert alert-error">
                        Error loading time slots. Please try again.
                        <span class="alert-close">&times;</span>
                    </div>
                `);
            }
        });
    }

    function handleNextToConfirm() {
        if (!selectedTime) {
            showNotification('Please select a time slot', 'error');
            return;
        }
        // Add these lines to update doctor info in confirmation step
        $('#confirm-doctor-name').text($('#selected-doctor-name').text());
        $('#confirm-specialty').text($('#selected-doctor-specialty').text());
        showStep(4);
    }

    function handleConfirmAppointment() {
        const purpose = $('#appointment-purpose').val().trim();
        const notes = $('#appointment-notes').val().trim();
        const appointment_type = $('#appointment-type').val();

        if (!purpose) {
            alert('Please enter the purpose of your visit');
            $('#appointment-purpose').focus();
            return;
        }

        const $btn = $(this);
        $btn.html('<span class="loading"></span> Processing...').prop('disabled', true);

        setTimeout(() => {
            $.ajax({
                url: 'book-appointment.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    patient_id: <?php echo json_encode($patientData['patient_id'] ?? 0); ?>,
                    doctor_id: selectedDoctor,
                    date: selectedDate,
                    time: selectedTime,
                    appointment_type: appointment_type,
                    purpose: purpose,
                    notes: notes
                },
                success: (response) => {
                    if (response?.success) {
                        $('body').append(`
                            <div id="success-popup" style="
                                position: fixed;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                background-color: #28a745;
                                color: white;
                                padding: 20px 40px;
                                font-size: 18px;
                                border-radius: 10px;
                                z-index: 9999;
                                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                            ">
                                Appointment has been booked successfully
                            </div>
                        `);

                        setTimeout(() => location.reload(), 2000);
                    } else {
                        alert("Error: " + (response?.message || 'Unknown error'));
                        $btn.html('<i class="fas fa-calendar-check"></i> Confirm Appointment').prop('disabled', false);
                    }
                },
                error: () => {
                    alert('Error booking appointment. Please try again.');
                    $btn.html('<i class="fas fa-calendar-check"></i> Confirm Appointment').prop('disabled', false);
                }
            });
        }, 2000);
    }

    function handleViewDetails() {
        currentAppointmentId = $(this).data('appointment-id');

        $.ajax({
            url: 'fetch-appointment-details.php',
            type: 'POST',
            dataType: 'json',
            data: { appointment_id: currentAppointmentId },
            beforeSend: () => {
                $appointmentModal.find('.modal-body').html(`
                    <div class="loading-container">
                        <div class="loading-spinner"></div>
                        <p>Loading appointment details...</p>
                    </div>
                `);
                $appointmentModal.show();
            },
            success: (response) => {
                if (response?.success) {
                    const data = response.data;
                    $appointmentModal.find('.modal-body').html(`
                        <h3>Appointment Details</h3>
                        <p><strong>Doctor:</strong> ${data.doctor_name}</p>
                        <p><strong>Patient:</strong> ${data.patient_name}</p>
                        <p><strong>Date:</strong> ${data.date}</p>
                        <p><strong>Time:</strong> ${data.appointment_time}</p>
                        <p><strong>Purpose:</strong> ${data.purpose}</p>
                        <p><strong>Notes:</strong> ${data.notes}</p>
                    `);
                } else {
                    $appointmentModal.find('.modal-body').html(`
                        <div class="alert alert-error">Failed to load details.</div>
                    `);
                }
            },
            error: () => {
                $appointmentModal.find('.modal-body').html(`
                    <div class="alert alert-error">Error loading details.</div>
                `);
            }
        });
    }

    function handleReschedule() {
        $appointmentModal.hide();
        showNotification("Reschedule feature coming soon!", "info");
    }

    function showCancelModal() {
        const appointmentCard = $(this).closest('.appt-card');
        const appointmentData = JSON.parse(appointmentCard.attr('data-appointment'));
        
        currentAppointmentId = appointmentData.id;
        
        // Set modal content
        $('#cancelDoctorName').text(appointmentData.doctor || "No doctor name");
        $('#cancelAppointmentTime').text(appointmentData.formatted_time || "No time specified");
        $('#cancel-appointment-id').val(currentAppointmentId);
        $('#cancelled-by').val(<?php echo json_encode($_SESSION['user_id'] ?? ''); ?>);
        
        // Reset form
        $('#cancel-reason').val('');
        $('#other-reason').val('');
        $('#other-reason-container').hide();
        
        $appointmentModal.hide();
        $cancelAppointmentModal.show();
    }

    function handleCancelReasonChange() {
        const reason = $(this).val();
        $('#submitCancelBtn').prop('disabled', !reason);
    }

   
function handleCancelAppointment(e) {
    e.preventDefault();
    
    const formData = {
        appointment_id: $('#cancel-appointment-id').val(),
        cancelled_by: $('#cancelled-by').val(),
        cancel_reason: $('#cancel-reason').val() === 'Other' 
                      ? $('#other-reason').val() 
                      : $('#cancel-reason').val()
    };

    const $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

    $.ajax({
        url: 'cancel_appointment.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#cancelAppointmentModal').hide();
                
                // More reliable selector using data attribute
                const appointmentCard = $(`.appt-card[data-appointment*='"id":"${formData.appointment_id}"']`);
                
                if (appointmentCard.length) {
                    // Update status classes
                    appointmentCard.removeClass('scheduled confirmed completed').addClass('canceled');
                    
                    // Update status badge
                    const statusBadge = appointmentCard.find('.appointment-status-badge');
                    if (statusBadge.length) {
                        statusBadge.text('Canceled')
                            .css({
                                'background': '#fee2e2',
                                'color': '#991b1b'
                            });
                    }
                    
                    // Remove action buttons more reliably
                    appointmentCard.find('.appt-card__footer').html(`
                        <div class="cancelled-message">
                            <i class="fas fa-ban"></i> Appointment Cancelled
                        </div>
                    `);
                }
                
                showNotification('Appointment cancelled successfully!', 'success');
            } else {
                showNotification(response.message || 'Failed to cancel appointment', 'error');
            }
        },
        error: function(xhr) {
            showNotification('An error occurred. Please try again.', 'error');
            console.error("Error:", xhr.responseText);
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html('<i class="fas fa-times"></i> Confirm Cancellation');
        }
    });
}


// ✅ Helper notification function
function showNotification(message, type = 'success') {
    $('#notification-message').text(message);
    $('#notification').removeClass('error success info').addClass(`${type} show`);
    setTimeout(() => $('#notification').removeClass('show'), 5000);
}


    // Helper function to show notifications
    function showNotification(message, type = 'success') {
        $notificationMessage.text(message);
        $notification.removeClass('error success info').addClass(`${type} show`);
        setTimeout(() => $notification.removeClass('show'), 5000);
    }
});
 // Update date and time display
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
            setInterval(updateDateTime, 60000); // Update every minute
          
    </script>
</body>
</html>