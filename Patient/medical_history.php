<?php
session_start();
include('../db-config/connection.php');

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.$_SESSION['error'].'</div>';
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">'.$_SESSION['success'].'</div>';
    unset($_SESSION['success']);
}

// Initialize patient data and medical records
$patientData = [];
$medicalRecords = [];
$error = '';

try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    
        // Get patient information
        $stmt = $conn->prepare("
            SELECT u.*, p.* 
            FROM users u
            LEFT JOIN patients p ON u.user_id = p.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $patientData = $result->fetch_assoc();
            $patientId = $patientData['patient_id'];
            
            // Get medical records for this patient
            $recordsStmt = $conn->prepare("
                SELECT 
        mr.id,
        mr.visit_date,
        mr.reason_for_visit,
        mr.doctors_observation,
        mr.diagnosis,
        mr.treatment_plan,
        mr.followup_instruction,
        mr.nextAppointmentDate,
        u.full_name AS doctor_name,
        p.id  AS prescription_id,
        p.medication_name,
        p.dosage,
        p.frequency,
        p.duration,
        p.instructions
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.doctor_id
    JOIN users u ON d.user_id = u.user_id
    LEFT JOIN prescriptions p ON mr.id = p.record_id
    WHERE mr.patient_id = ?
    ORDER BY mr.visit_date DESC
            ");
            $recordsStmt->bind_param("i", $patientId);
            $recordsStmt->execute();
            $recordsResult = $recordsStmt->get_result();
            
            while ($record = $recordsResult->fetch_assoc()) {
    $recordId = $record['id'];
    
    // If we haven't seen this record before, create a new entry
    if (!isset($medicalRecords[$recordId])) {
        $medicalRecords[$recordId] = [
            'visitDate' => $record['visit_date'],
            'doctorName' => $record['doctor_name'],
            'doctorObservation' => $record['doctors_observation'],
            'diagnosis' => $record['diagnosis'],
            'treatment' => $record['treatment_plan'],
            'followupInstructions' => $record['followup_instruction'],
            'nextAppointment' => $record['nextAppointmentDate'],
            'reasonForVisit' => $record['reason_for_visit'],
            'prescriptions' => []
        ];
    }
    
    // Add prescription if it exists for this record
    if (!empty($record['prescription_id'])) {
        $medicalRecords[$recordId]['prescriptions'][] = [
            'medication' => $record['medication_name'],
            'dosage' => $record['dosage'],
            'frequency' => $record['frequency'],
            'duration' => $record['duration'],
            'instructions' => $record['instructions']
        ];
    }
}

// Convert to simple array if needed
$medicalRecords = array_values($medicalRecords);
        } else {
            $error = "Patient record not found";
        }
    }
        
} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
$patientId = $patientData['patient_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="medical_history.css">
    <title>Medical History</title>
    <style>
        /* Enhanced styles for medical records */
        .medical-record {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .medical-record:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .record-header {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .visit-date, .doctor-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .record-content {
            padding: 20px;
        }
        
        .record-field {
            margin-bottom: 15px;
        }
        
        .field-label {
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .field-content {
            color: #495057;
            line-height: 1.6;
            padding-left: 26px;
        }
        
        .next-appointment {
            background: #e6f7ff;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #0056b3;
        }
        
        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .no-records i {
            font-size: 48px;
            color: #adb5bd;
            margin-bottom: 15px;
        }
        
        /* Search and filter styles */
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-input, .filter-select {
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 14px;
            flex: 1;
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .record-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-filter {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
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
                <a href="medical_history.php" class="nav-item active">
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

        <main class="main-content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="patient-info">
                <div class="patient-name"><?php echo htmlspecialchars($patientData['full_name'] ?? 'Patient'); ?></div>
                <div class="patient-id">Patient ID: #<?php echo htmlspecialchars($patientData['patient_id'] ?? 'N/A'); ?></div>
            </div>

            <div class="search-filter">
                <input type="text" class="search-input" id="searchInput" placeholder="Search by diagnosis, doctor, or treatment...">
                <select class="filter-select" id="doctorFilter">
                    <option value="">All Doctors</option>
                    <?php
                    // Get unique doctors from the records
                    $doctors = array_unique(array_column($medicalRecords, 'doctorName'));
                    foreach ($doctors as $doctor) {
                        echo '<option value="' . htmlspecialchars($doctor) . '">' . htmlspecialchars($doctor) . '</option>';
                    }
                    ?>
                </select>
                <select class="filter-select" id="yearFilter">
                    <option value="">All Years</option>
                    <?php
                    // Get unique years from the records
                    $years = [];
                    foreach ($medicalRecords as $record) {
                        if (!empty($record['visitDate'])) {
                            $year = date('Y', strtotime($record['visitDate']));
                            if (!in_array($year, $years)) {
                                $years[] = $year;
                                echo '<option value="' . $year . '">' . $year . '</option>';
                            }
                        }
                    }
                    rsort($years); // Show most recent years first
                    ?>
                </select>
            </div>

            <div class="records-container" id="recordsContainer">
                <?php if (empty($medicalRecords)): ?>
                    <div class="no-records">
                        <i class="fas fa-file-medical"></i>
                        <h3>No Medical Records Found</h3>
                        <p>You don't have any medical records yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($medicalRecords as $recordId => $record): ?>
                        <div class="medical-record" 
                             data-doctor="<?php echo htmlspecialchars($record['doctorName']); ?>" 
                             data-year="<?php echo !empty($record['visitDate']) ? date('Y', strtotime($record['visitDate'])) : ''; ?>">
                            <div class="record-header">
                                <div class="visit-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo !empty($record['visitDate']) ? date('F j, Y', strtotime($record['visitDate'])) : 'No date'; ?>
                                </div>
                                <div class="doctor-name">
                                    <i class="fas fa-user-md"></i>
                                    <?php echo htmlspecialchars($record['doctorName']); ?>
                                </div>
                            </div>
                            
                            <div class="record-content">
                                <div class="record-field">
                                    <div class="field-label">
                                        <i class="fas fa-comment-medical"></i>
                                        Reason for Visit
                                    </div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['reasonForVisit']); ?></div>
                                </div>
                                
                                <div class="record-field">
                                    <div class="field-label">
                                        <i class="fas fa-eye"></i>
                                        Doctor's Observation
                                    </div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['doctorObservation']); ?></div>
                                </div>
                                
                                <div class="record-field">
                                    <div class="field-label">
                                        <i class="fas fa-diagnoses"></i>
                                        Diagnosis
                                    </div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['diagnosis']); ?></div>
                                </div>
                                
                                <div class="record-field">
                                    <div class="field-label">
                                        <i class="fas fa-pills"></i>
                                        Treatment
                                    </div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['treatment']); ?></div>
                                </div>
                                
                                <div class="record-field">
                                    <div class="field-label">
                                        <i class="fas fa-clipboard-list"></i>
                                        Follow-up Instructions
                                    </div>
                                    <div class="field-content"><?php echo htmlspecialchars($record['followupInstructions']); ?></div>
                                </div>

                                <div class="record-actions">
                                   <?php if (!empty($record['prescriptions'])): ?>
                                   <button class="view-prescription-btn" 
                                      data-record-id="<?php echo $recordId; ?>"
                                      data-doctor="<?php echo htmlspecialchars($record['doctorName']); ?>"
                                      data-date="<?php echo !empty($record['visitDate']) ? date('F j, Y', strtotime($record['visitDate'])) : 'No date'; ?>"
                                      data-prescriptions='<?php echo json_encode($record['prescriptions']); ?>'>
                                     <i class="fas fa-prescription-bottle-alt"></i> View Prescription
                                  </button>
                                  <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($record['nextAppointment'])): ?>
                                    <div class="next-appointment">
                                        <i class="fas fa-calendar-check"></i>
                                        Next Appointment: <?php echo date('F j, Y', strtotime($record['nextAppointment'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
<!-- Prescription Overlay -->
<div id="prescriptionOverlay" class="overlay" style="display: none;">
    <div class="modal" style="max-width: 600px;">
        <button class="close-btn" id="closePrescriptionBtn" aria-label="Close modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
        <h2 class="modal-title"><i class="fa-solid fa-capsules"></i> Prescription Details</h2>
        
        <div class="prescription-meta">
            <div class="meta-item">
                <i class="fas fa-user-md"></i>
                <div>
                    <span class="meta-label">Doctor</span>
                    <span class="meta-value" id="prescriptionDoctor">Loading...</span>
                </div>
            </div>
            <div class="meta-item">
                <i class="fas fa-calendar-alt"></i>
                <div>
                    <span class="meta-label">Date</span>
                    <span class="meta-value" id="prescriptionDate">Loading...</span>
                </div>
            </div>
        </div>
        
        <div class="prescription-list" id="prescriptionList">
            <!-- Prescription items will be added here dynamically -->
        </div>
    </div>
</div>
    <script>
        // JavaScript for filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Filter records based on search and filters
            function filterRecords() {
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                const selectedDoctor = document.getElementById('doctorFilter').value;
                const selectedYear = document.getElementById('yearFilter').value;
                
                const records = document.querySelectorAll('.medical-record');
                let hasVisibleRecords = false;
                
                records.forEach(record => {
                    const doctor = record.dataset.doctor;
                    const year = record.dataset.year;
                    const textContent = record.textContent.toLowerCase();
                    
                    const matchesSearch = !searchTerm || textContent.includes(searchTerm);
                    const matchesDoctor = !selectedDoctor || doctor === selectedDoctor;
                    const matchesYear = !selectedYear || year === selectedYear;
                    
                    if (matchesSearch && matchesDoctor && matchesYear) {
                        record.style.display = '';
                        hasVisibleRecords = true;
                    } else {
                        record.style.display = 'none';
                    }
                });
                
                // Show "no records" message if none match filters
                const noRecordsMsg = document.querySelector('.no-records');
                if (!hasVisibleRecords && records.length > 0) {
                    if (!noRecordsMsg) {
                        const container = document.getElementById('recordsContainer');
                        container.innerHTML = `
                            <div class="no-records">
                                <i class="fas fa-file-medical"></i>
                                <h3>No Matching Records</h3>
                                <p>No medical records match your current search criteria.</p>
                            </div>
                        `;
                    }
                }
            }
            
            // Add event listeners for filters
            document.getElementById('searchInput').addEventListener('input', filterRecords);
            document.getElementById('doctorFilter').addEventListener('change', filterRecords);
            document.getElementById('yearFilter').addEventListener('change', filterRecords);
        });
        // Prescription Overlay Functionality
document.addEventListener('DOMContentLoaded', function() {
    const prescriptionOverlay = document.getElementById('prescriptionOverlay');
    const closePrescriptionBtn = document.getElementById('closePrescriptionBtn');
    
    // Close overlay when clicking close button
    closePrescriptionBtn.addEventListener('click', function() {
        prescriptionOverlay.style.display = 'none';
    });
    
    // Close overlay when clicking outside the modal
    prescriptionOverlay.addEventListener('click', function(e) {
        if (e.target === prescriptionOverlay) {
            prescriptionOverlay.style.display = 'none';
        }
    });
    
    // Handle view prescription button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-prescription-btn')) {
            const btn = e.target.closest('.view-prescription-btn');
            const doctor = btn.dataset.doctor;
            const date = btn.dataset.date;
            const prescriptions = JSON.parse(btn.dataset.prescriptions);
            
            // Update overlay content
            document.getElementById('prescriptionDoctor').textContent = doctor;
            document.getElementById('prescriptionDate').textContent = date;
            
            const prescriptionList = document.getElementById('prescriptionList');
            prescriptionList.innerHTML = '';
            
            prescriptions.forEach(prescription => {
                const item = document.createElement('div');
                item.className = 'prescription-item';
                item.innerHTML = `
                    <div class="prescription-medication">
                        <span>${prescription.medication}</span>
                    </div>
                    <div class="prescription-details">
                        <div class="detail-item">
                            <div class="detail-label">Dosage</div>
                            <div class="detail-value">${prescription.dosage}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Frequency</div>
                            <div class="detail-value">${prescription.frequency}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Duration</div>
                            <div class="detail-value">${prescription.duration}</div>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <div class="detail-label">Instructions</div>
                            <div class="detail-value">${prescription.instructions}</div>
                        </div>
                    </div>
                `;
                prescriptionList.appendChild(item);
            });
            
            // Show the overlay
            prescriptionOverlay.style.display = 'flex';
        }
    });
});
    </script>
</body>
</html>