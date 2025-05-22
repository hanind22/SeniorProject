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
    <div class="container">
        <!-- Sidebar (unchanged) -->
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
                <a href="Dr_Appointment.php" class="nav-item">
                    <i class="fa-solid fa-calendar"></i>
                    <span>Appointments</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                <a href="medical_records.php" class="nav-item active">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records<br>& Prescription</span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="#" class="nav-item">
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
            <div class="overlay-content">
                <div class="form-container">
                    <!-- Patient Demographics Section -->
                    <div class="section">
                        <h3 class="section-header"><i class="fas fa-user-injured"></i> Patient Demographics</h3>
                        <form id="appointment-form" action="process_appointment.php" method="POST">
                        <input type="hidden" name="doctor_id" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

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
                                <div class="form-group">
                                    <label>Age</label>
                                    <input type="text" class="form-control" id="patient-age" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Gender</label>
                                    <input type="text" class="form-control" id="patient-gender" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Blood Group</label>
                                    <input type="text" class="form-control" id="patient-bloodgrp" readonly>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="overlay-content">
                <div class="form-container">
                    <!-- Medical History Section -->
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
                            <div class="form-group">
                                <!-- Empty group to maintain grid layout -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clinical Notes Section -->
            <div class="overlay-content">
                <div class="form-container">
                    <div class="section">
                        <h3 class="section-header"><i class="fas fa-notes-medical"></i> Clinical Notes</h3>
                        
                        <div class="clinical-note-section">
                            <h4 class="note-subheader">Chief Complaint</h4>
                            <textarea class="form-control note-textarea" placeholder="Primary reason for visit"></textarea>
                        </div>
                        
                        <div class="clinical-note-section">
                            <h4 class="note-subheader">Doctor's Observations</h4>
                            <textarea class="form-control note-textarea" placeholder="Current observations and examination findings"></textarea>
                        </div>
                        
                        <div class="clinical-note-section">
                            <h4 class="note-subheader">Diagnosis</h4>
                            <textarea class="form-control note-textarea" placeholder="Primary and secondary diagnoses"></textarea>
                        </div>
                        
                        <div class="clinical-note-section">
                            <h4 class="note-subheader">Treatment Plan</h4>
                            <textarea class="form-control note-textarea" placeholder="Proposed treatment and follow-up plan"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prescription Section -->
            <div class="overlay-content">
                <div class="form-container">
                    <div class="section">
                        <h3 class="section-header"><i class="fas fa-prescription-bottle-alt"></i> Prescription</h3>
                        
                        <div class="medication-section">
                            <h4 class="medication-header">Medication #1</h4>
                            
                            <div class="form-group">
                                <label>Medication Name</label>
                                <input type="text" class="form-control" placeholder="Generic and brand name">
                            </div>
                            
                            <div class="form-group">
                                <label>Strength/Dosage</label>
                                <input type="text" class="form-control" placeholder="mg, ml, etc.">
                            </div>
                            
                            <div class="frequency-section">
                                <h5 class="frequency-header">Frequency</h5>
                                <div class="frequency-grid">
                                    <div class="form-group">
                                        <label>Select</label>
                                        <select class="form-control">
                                            <option>Once daily</option>
                                            <option>Twice daily</option>
                                            <option>Three times daily</option>
                                            <option>Four times daily</option>
                                            <option>As needed</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Duration</label>
                                        <select class="form-control">
                                            <option>7 days</option>
                                            <option>14 days</option>
                                            <option>30 days</option>
                                            <option>60 days</option>
                                            <option>Until finished</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Quantity</label>
                                        <input type="text" class="form-control" placeholder="Number of units">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Special Instructions</label>
                                <textarea class="form-control" placeholder="Take with food, avoid alcohol, etc." rows="2"></textarea>
                            </div>
                            
                            <div class="divider"></div>
                            
                            <button type="button" class="btn btn-add-medication">
                                <i class="fas fa-plus"></i> Add Another Medication
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Treatment Plan & Follow-up Section -->
            <div class="overlay-content">
                <div class="form-container">
                    <div class="section">
                        <h3 class="section-header"><i class="fas fa-calendar-check"></i> Treatment Plan & Follow-up</h3>
                        
                        <div class="treatment-plan-section">
                            <h4 class="treatment-subheader">Treatment Plan</h4>
                            
                            <div class="form-group">
                                <label>Follow-up Instructions</label>
                                <textarea class="form-control" placeholder="Detailed follow-up instructions"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Next Appointment</label>
                                <input type="date" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Visit Date</label>
                            <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="signature-section">
                            <div class="form-group">
                                <label>Physician Signature</label>
                                <input type="text" class="form-control" placeholder="Dr. Name">
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lab/Test Results Section -->
            <div class="overlay-content">
                <div class="form-container">
                    <div class="section">
                        <h3 class="section-header"><i class="fas fa-flask"></i> Lab/Test Results</h3>
                        
                        <div class="upload-area">
                            <div class="upload-instructions">
                                <p>Click to upload lab results, X-rays, MRI scans</p>
                                <p class="file-types">Supported formats: PDF, JPG, PNG, DICOM</p>
                            </div>
                            <div class="upload-button">
                                <button type="button" class="btn btn-upload">
                                    <i class="fas fa-cloud-upload-alt"></i> Upload Files
                                </button>
                                <input type="file" id="file-upload" multiple style="display: none;">
                            </div>
                        </div>
 
                        <div class="form-actions">
                            <button type="button" class="btn btn-outline">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Patient Record
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fetchPatientInfo() {
            const patientId = document.getElementById("appointment-patient-id").value;
            if (!patientId) return;
            // Fetch data using AJAX (mocked for now)
            fetch(`get_patient_info.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById("appointment-patient-name").value = data.name;
                    document.getElementById("patient-age").value = data.age;
                    document.getElementById("patient-gender").value = data.gender;
                    document.getElementById("patient-bloodgrp").value = data.blood_group;
                    document.getElementById("patient-condition").value = data.medical_condition;
                    document.getElementById("patient-allergies").value = data.allergies;
                    document.getElementById("patient-medications").value = data.medications;
                    document.getElementById("patient-surgeries").value = data.surgeries;
                    document.getElementById("patient-familyhistory").value = data.family_history;
                })
                .catch(error => console.error('Error fetching patient data:', error));
        }

        // File upload trigger
        document.querySelector('.btn-upload').addEventListener('click', function() {
            document.getElementById('file-upload').click();
        });

        // Patient ID lookup
    const patientIdField = document.getElementById('appointment-patient-id');
    if (patientIdField) {
        patientIdField.addEventListener('change', async function() {
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
                if (data.success) {
                    patientNameField.value = data.patient_name;
                } else {
                    patientNameField.value = 'Patient not found';
                }
            } catch (error) {
                console.error('Error fetching patient:', error);
                patientNameField.value = 'Error fetching patient';
            }
        });
    }

// Function to fetch all patient info
    async function fetchPatientInfo() {
        const patientId = document.getElementById("appointment-patient-id").value;
        if (!patientId) return;
        
        try {
            const response = await fetch(`get_patient_data.php?patient_id=${patientId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success) {
                const patient = data.data;
                
                // Update demographics
                document.getElementById("appointment-patient-name").value = patient.full_name || '';
                document.getElementById("patient-age").value = patient.age || '';
                document.getElementById("patient-gender").value = patient.gender || '';
                document.getElementById("patient-bloodgrp").value = patient.blood_type || '';
                
                // Update medical history
                document.getElementById("patient-condition").value = patient.medical_conditions || '';
                document.getElementById("patient-allergies").value = patient.allergies || '';
                document.getElementById("patient-medications").value = patient.current_medications || '';
                document.getElementById("patient-surgeries").value = patient.previous_surgeries || '';
                document.getElementById("patient-familyhistory").value = patient.family_history || '';
            } else {
                console.error('Error fetching patient:', data.error);
                alert('Patient not found or error occurred');
            }
        } catch (error) {
            console.error('Error fetching patient data:', error);
            alert('Error fetching patient data');
        }
    }

    // Add event listener for Enter key on patient ID field
    document.getElementById("appointment-patient-id").addEventListener("keypress", function(event) {
        if (event.key === "Enter") {
            event.preventDefault(); // Prevent form submission
            fetchPatientInfo();
        }
    });

    // File upload trigger
    document.querySelector('.btn-upload').addEventListener('click', function() {
        document.getElementById('file-upload').click();
    });
</script>
</body>
</html>