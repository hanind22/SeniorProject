<?php
session_start();
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
    <link rel="stylesheet" href="medical_records.css">
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
                <a href="doctor_dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <a href="Dr_Appointment.php" class="nav-item"><i class="fa-solid fa-calendar"></i><span>Appointments</span></a>
                <a href="patients.php" class="nav-item"><i class="fas fa-user-injured"></i><span>Patients</span></a>
                <a href="medical_records.php" class="nav-item active"><i class="fas fa-file-medical"></i><span>Medical Records<br>& Prescription</span></a>
                <!-- <a href="notifications.php" class="nav-item"><i class="fa-solid fa-bell"></i><span>Notifications</span></a> -->
                <a href="profile.php" class="nav-item"><i class="fas fa-user-md"></i><span>Profile</span></a>
                <a href="#" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Log out</span></a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <form id="appointment-form" action="process_medical_record.php" method="POST">
                <input type="hidden" name="doctor_id" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

                <!-- Patient Demographics -->
                <div class="overlay-content">
                    <div class="form-container">
                        <div class="section">
                            <h3 class="section-header"><i class="fas fa-user-injured"></i> Patient Demographics</h3>
                            <div class="form-row demographics-row">
                                <div class="form-group">
                                    <label for="appointment-patient-id">Patient's ID</label>
                                    <input type="number" class="form-control" id="appointment-patient-id" name="patient_id" required onblur="fetchPatientInfo()">
                                </div>
                                <div class="form-group">
                                    <label for="appointment-patient-name">Patient's Name</label>
                                    <input type="text" class="form-control" id="appointment-patient-name" name="patient_name" readonly>
                                </div>
                            </div>
                            <div class="form-row demographics-row">
                                <div class="form-group"><label>Age</label><input type="text" class="form-control" id="patient-age" readonly></div>
                                <div class="form-group"><label>Gender</label><input type="text" class="form-control" id="patient-gender" readonly></div>
                                <div class="form-group"><label>Blood Group</label><input type="text" class="form-control" id="patient-bloodgrp" readonly></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical History -->
                <div class="overlay-content">
                    <div class="form-container">
                        <div class="section">
                            <h3 class="section-header"><i class="fa-solid fa-file-medical"></i> Medical History</h3>
                            <div class="form-row medical-history-row">
                                <div class="form-group">
                                    <label>Medical Conditions</label>
                                    <textarea class="form-control" id="patient-condition" readonly></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Allergies</label>
                                    <textarea class="form-control" id="patient-allergies" readonly></textarea>
                                </div>
                            </div>
                            <div class="form-row medical-history-row">
                                <div class="form-group">
                                    <label>Current Medications</label>
                                    <textarea class="form-control" id="patient-medications" readonly></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Previous Surgeries</label>
                                    <textarea class="form-control" id="patient-surgeries" readonly></textarea>
                                </div>
                            </div>
                            <div class="form-row medical-history-row">
                                <div class="form-group">
                                    <label>Family History</label>
                                    <textarea class="form-control" id="patient-familyhistory" readonly></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clinical Notes -->
                <div class="overlay-content">
                    <div class="form-container">
                        <div class="section">
                            <h3 class="section-header"><i class="fas fa-notes-medical"></i> Clinical Notes<span class="required">*</span></h3>
                            <div class="clinical-note-section">
                                <h4 class="note-subheader">Reason For Visit<span class="required">*</span></h4>
                                <textarea class="form-control note-textarea" name="reason_for_visit" required placeholder="Primary reason for visit"></textarea>
                            </div>
                            <div class="clinical-note-section">
                                <h4 class="note-subheader">Doctor's Observations<span class="required">*</span></h4>
                                <textarea class="form-control note-textarea" name="observations" required placeholder="Current observations and examination findings"></textarea>
                            </div>
                            <div class="clinical-note-section">
                                <h4 class="note-subheader">Diagnosis<span class="required">*</span></h4>
                                <textarea class="form-control note-textarea" name="diagnosis" required placeholder="Primary and secondary diagnoses"></textarea>
                            </div>
                            <div class="clinical-note-section">
                                <h4 class="note-subheader">Treatment Plan<span class="required">*</span></h4>
                                <textarea class="form-control note-textarea" name="treatment_plan" required placeholder="Proposed treatment and follow-up plan"></textarea>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Prescription -->
<div class="overlay-content">
    <div class="form-container">
        <div class="section">
            <h3 class="section-header"><i class="fas fa-prescription-bottle-alt"></i> Prescription <span class="required">*</span></h3>
            <div id="medication-sections">
                <!-- Initial Medication Section -->
                <div class="medication-section">
                     <div class="medication-header-wrapper">
                         <h4 class="medication-header">Medication #1</h4>
                         <button type="button" class="btn-close-medication" onclick="removeMedication(this)">
                         &times;
                         </button>
                     </div>
                    <div class="form-group">
                        <label>Medication Name</label>
                        <input type="text" class="form-control" name="med_name[]" placeholder="Generic and brand name">
                    </div>
                    <div class="form-group">
                        <label>Strength/Dosage</label>
                        <input type="text" class="form-control" name="med_dosage[]" placeholder="mg, ml, etc.">
                    </div>
                    <div class="frequency-section">
                        <h5 class="frequency-header">Frequency</h5>
                        <div class="frequency-grid">
                            <div class="form-group">
                                <label>Select</label>
                                <select class="form-control" name="frequency[]">
                                    <option>Once daily</option>
                                    <option>Twice daily</option>
                                    <option>Three times daily</option>
                                    <option>Four times daily</option>
                                    <option>As needed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Duration</label>
                                <select class="form-control" name="duration[]">
                                    <option>7 days</option>
                                    <option>14 days</option>
                                    <option>30 days</option>
                                    <option>60 days</option>
                                    <option>Until finished</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Special Instructions</label>
                        <textarea class="form-control" name="special_instructions[]" placeholder="Take with food, avoid alcohol, etc." rows="2"></textarea>
                    </div>
                    <div class="divider"></div>
                </div>
            </div>
            <button type="button" class="btn btn-add-medication"><i class="fas fa-plus"></i> Add Another Medication</button>
        </div>
    </div>
</div>
                <!-- Follow-up -->
                <div class="overlay-content">
                    <div class="form-container">
                        <div class="section">
                            <h3 class="section-header"><i class="fas fa-calendar-check"></i> Treatment Plan & Follow-up</h3>
                            <div class="treatment-plan-section">
                                <h4 class="treatment-subheader">Treatment Plan</h4>
                                <div class="form-group">
                                    <label>Follow-up Instructions</label>
                                    <textarea class="form-control" id="follow-up-instructions"  name="follow_up_instructions" placeholder="Detailed follow-up instructions"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Next Appointment Date</label>
                                    <input type="date" class="form-control" id="next-appointment" name="next_appointment">
                                </div>
                                <div class="form-group">
                                    <label>Next Appointment Time</label>
                                    <input type="time" class="form-control"  id="next-appointment-time" name="next_appointment_time" step="900" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Visit Date</label>
                                    <input type="date" class="form-control" name="visit_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-submit">Submit</button>
                        </div>
                    </div>
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
document.addEventListener("DOMContentLoaded", function () {
    // Update date and time every second
    function updateDateTime() {
        const dateTimeElement = document.getElementById('date-time');
        if (dateTimeElement) {
            const now = new Date();
            dateTimeElement.textContent = now.toLocaleString();
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Function to fetch all patient info
    async function fetchPatientInfo() {
        const patientIdField = document.getElementById("appointment-patient-id");
        if (!patientIdField) return;

        const patientId = patientIdField.value;
        if (!patientId) return;

        try {
            const response = await fetch(`get_patient_data.php?patient_id=${patientId}`);
            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();

            if (data.success) {
                const patient = data.data;

                document.getElementById("appointment-patient-name").value = patient.full_name || '';
                document.getElementById("patient-age").value = patient.age || '';
                document.getElementById("patient-gender").value = patient.gender || '';
                document.getElementById("patient-bloodgrp").value = patient.blood_type || '';
                document.getElementById("patient-condition").value = patient.medical_conditions || '';
                document.getElementById("patient-allergies").value = patient.allergies || '';
                document.getElementById("patient-medications").value = patient.current_medications || '';
                document.getElementById("patient-surgeries").value = patient.previous_surgeries || '';
                document.getElementById("patient-familyhistory").value = patient.family_history || '';
            } else {
                alert('Patient not found or error occurred');
            }
        } catch (error) {
            console.error('Error fetching patient data:', error);
            alert('Error fetching patient data');
        }
    }

    // Patient ID lookup on change
    const patientIdField = document.getElementById('appointment-patient-id');
    if (patientIdField) {
        patientIdField.addEventListener('change', async function () {
            const patientId = this.value;
            const patientNameField = document.getElementById('appointment-patient-name');
            if (!patientNameField) return;

            if (!patientId) {
                patientNameField.value = '';
                return;
            }

            try {
                const response = await fetch(`get_patient_name.php?patient_id=${patientId}`);
                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                patientNameField.value = data.success ? data.patient_name : 'Patient not found';
            } catch (error) {
                console.error('Error fetching patient:', error);
                patientNameField.value = 'Error fetching patient';
            }
        });

        patientIdField.addEventListener("keypress", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                fetchPatientInfo();
            }
        });
    }

    // File upload trigger
    const uploadBtn = document.querySelector('.btn-upload');
    const fileInput = document.getElementById('file-upload');
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function () {
            fileInput.click();
        });
    }

    // Medication section management
    function addMedicationSection() {
        const container = document.getElementById('medication-sections');
        if (!container) return;

        const count = container.querySelectorAll('.medication-section').length + 1;

        const section = document.createElement('div');
        section.className = 'medication-section';
        section.innerHTML = `
            <div class="medication-header-wrapper">
                <h4 class="medication-header">Medication #${count}</h4>
                <button type="button" class="btn-close-medication" onclick="removeMedication(this)">
                    &times;
                </button>
            </div>
            <div class="form-group">
                <label>Medication Name</label>
                <input type="text" class="form-control" name="med_name[]" placeholder="Generic and brand name" required>
            </div>
            <div class="form-group">
                <label>Strength/Dosage</label>
                <input type="text" class="form-control" name="med_dosage[]" placeholder="mg, ml, etc." required>
            </div>
            <div class="frequency-section">
                <h5 class="frequency-header">Frequency</h5>
                <div class="frequency-grid">
                    <div class="form-group">
                        <label>Select</label>
                        <select class="form-control" name="frequency[]" required>
                            <option value="">Select frequency</option>
                            <option value="Once daily">Once daily</option>
                            <option value="Twice daily">Twice daily</option>
                            <option value="Three times daily">Three times daily</option>
                            <option value="Four times daily">Four times daily</option>
                            <option value="As needed">As needed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <select class="form-control" name="duration[]" required>
                            <option value="">Select duration</option>
                            <option value="7 days">7 days</option>
                            <option value="14 days">14 days</option>
                            <option value="30 days">30 days</option>
                            <option value="60 days">60 days</option>
                            <option value="Until finished">Until finished</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Special Instructions</label>
                <textarea class="form-control" name="special_instructions[]" placeholder="Take with food, avoid alcohol, etc." rows="2"></textarea>
            </div>
            <div class="divider"></div>
        `;
        container.appendChild(section);
    }

    function removeMedication(btn) {
        const section = btn.closest('.medication-section');
        if (section) {
            section.remove();
            updateMedicationHeaders();
        }
    }

    function updateMedicationHeaders() {
        const sections = document.querySelectorAll('.medication-section');
        sections.forEach((section, index) => {
            const header = section.querySelector('.medication-header');
            if (header) header.textContent = `Medication #${index + 1}`;
        });
    }

    // Add medication button event listener
    const addMedBtn = document.querySelector(".btn-add-medication");
    if (addMedBtn) {
        addMedBtn.addEventListener("click", addMedicationSection);
    }

    // Make removeMedication function available globally
    window.removeMedication = removeMedication;
// Notification bell functionality
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');

            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });

            // Prevent dropdown from closing when clicking inside it
            notificationDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
        
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
        window.location.href = '../Registration-Login/index.php';
        
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
</body>
</html>