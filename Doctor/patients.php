<?php
session_start();
include('../db-config/connection.php');

// Initialize data
$doctorData = [];
$patients = [];
$error = '';

try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    
        // Get doctor data including doctor_id
        $stmt = $conn->prepare("
            SELECT u.*, d.* 
            FROM users u
            JOIN doctors d ON u.user_id = d.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $doctorData = $result->fetch_assoc();
            $doctorId = $doctorData['doctor_id'];

            // Updated query: patients via appointments OR doctorpatient (DISTINCT)
            $patientStmt = $conn->prepare("
                SELECT DISTINCT 
                    p.patient_id,
                    p.user_id,
                    u.full_name,
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
                    MAX(a.appointment_date) as last_visit,
                    CASE 
                        WHEN MAX(a.appointment_date) > DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 'active'
                        ELSE 'inactive'
                    END as status
                FROM patients p
                JOIN users u ON p.user_id = u.user_id
                LEFT JOIN appointments a ON p.patient_id = a.patient_id AND a.doctor_id = ?
                WHERE p.patient_id IN (
                    SELECT DISTINCT patient_id FROM appointments WHERE doctor_id = ?
                    UNION
                    SELECT DISTINCT patient_id FROM doctorpatient WHERE doctor_id = ?
                )
                GROUP BY p.patient_id
                ORDER BY u.full_name ASC
            ");

            // Bind the doctor_id 3 times (for JOIN + 2 subqueries)
            $patientStmt->bind_param("iii", $doctorId, $doctorId, $doctorId);
            $patientStmt->execute();
            $patientResult = $patientStmt->get_result();
            
            if ($patientResult->num_rows > 0) {
                while ($row = $patientResult->fetch_assoc()) {
                    // Calculate age
                    $dob = new DateTime($row['date_of_birth']);
                    $now = new DateTime();
                    $age = $dob->diff($now)->y;

                    // Format last visit
                    $last_visit = isset($row['last_visit']) && $row['last_visit'] ? 
                        date('M j, Y', strtotime($row['last_visit'])) : 'Never';

                    // Add calculated fields
                    $row['age'] = $age;
                    $row['last_visit_formatted'] = $last_visit;
                    $patients[] = $row;
                }
            } else {
                $error = "No patients found for this doctor";
            }
        } else {
            $error = "Doctor record not found";
        }
    } else {
        $error = "User not logged in";
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
    <title>Doctors Patient Section</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="patients.css">
    <style>
        .no-patients {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .no-patients i {
            font-size: 50px;
            margin-bottom: 20px;
            color: #ccc;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
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
                <a href="Dr_Appointment.php" class="nav-item">
                    <i class="fa-solid fa-calendar"></i>
                    <span>Appointments</span>
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                <a href="medical_records.php" class="nav-item">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Records<br>& Prescription</span>
                </a>
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
        <div class="content">
            <div class="doctor-info">
                <h2>Dr. <?php echo htmlspecialchars($doctorData['full_name'] ?? ''); ?></h2>
                <p class="doctor-title"><?php echo htmlspecialchars($doctorData['specialty'] ?? ''); ?></p>
                <p class="doctor-contact">Contact: <?php echo htmlspecialchars($doctorData['email'] ?? ''); ?> | +961 <?php echo htmlspecialchars($doctorData['phone_number'] ?? ''); ?></p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-user-injured"></i> Patients</h1>
                <div class="patient-stats">
                    <span class="stat-badge total-patients"><i class="fas fa-users"></i> <?php echo count($patients); ?> Total</span>
                </div>
            </div>

            <!-- Search and Add Patient -->
            <div class="patient-actions">
                <div class="search-container">
                    <input type="text" class="search-input" id="patient-search" placeholder="Search patients by name">
                </div>
            </div>

            <!-- Patients Table -->
            <div class="patients-table-container">
                <?php if (count($patients) > 0): ?>
                <table class="patients-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th> Patient ID</th>
                            <th>Age/Gender</th>
                            <th>Blood Type</th>
                            <th>Last Visit</th>
                            <th>QR Code</th>
                            <th>Patient's Medical Uploads<br>& Edit Info</th>
                        </tr>
                    </thead>
                    <tbody id="patients-table-body">
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <div class="patient-profile">
                                    <span class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                            <td><?php echo $patient['age'] . ' / ' . htmlspecialchars($patient['gender']); ?></td>
                            <td><span class="blood-badge"><?php echo htmlspecialchars($patient['blood_type']); ?></span></td>
                            <td><?php echo $patient['last_visit_formatted']; ?></td>
                            <td>
                              <?php 
                                     $qrCodePath = '../qrcodes/patient_' . $patient['patient_id'] . '.png';
                                     if (file_exists($qrCodePath)): ?>
                                     <div class="qr-code-container">
                                          <a href="#" class="qr-code-link" onclick="event.preventDefault(); showQRCode('<?php echo $qrCodePath; ?>', '<?php echo htmlspecialchars($patient['full_name']); ?>')">
                                          <img src="<?php echo $qrCodePath; ?>" alt="QR Code for Patient <?php echo $patient['patient_id']; ?>" class="qr-code-img" width="50" height="50">
                                     </a>
                                     </div>
                                     <?php else: ?>
                                      <span class="no-qr">No QR</span>
                                    <?php endif; ?>
                           </td>
                            <td class="action-cell">
                                <button class="action-btn view-btn" data-patient-id="<?php echo $patient['patient_id']; ?>" title="View details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn edit-btn" data-patient-id="<?php echo $patient['patient_id']; ?>" title="Edit health info">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="no-patients">
                        <i class="fas fa-user-slash"></i>
                        <p>No patients found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


<!-- Edit Health Info Modal -->
<div class="modal-overlay" id="edit-health-modal" style="display: none;">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Patient Health Information</h3>
            <!-- <button class="close-modal" id="close-edit-modal">&times;</button> -->
        </div>
        <div class="modal-body">
            <form id="edit-health-form" action="update_health_info.php" method="post">
                <input type="hidden" id="edit-patient-id" name="patient_id">
                
                <div class="patient-info-header">
                    <div class="patient-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="patient-meta">
                        <h4 id="edit-patient-name">Loading patient...</h4>
                        <div class="patient-details">
                            <span id="edit-patient-age-gender"></span>
                            <span id="edit-patient-blood-type" class="blood-badge"></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-section" style="max-height: 400px; overflow-y: auto; padding-right: 10px; margin-top: 20px;">
                    <div class="form-group">
                        <label for="edit-allergies">Allergies</label>
                        <textarea id="edit-allergies" name="allergies" rows="3" class="form-control" placeholder="List all known allergies"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-conditions">Medical Conditions</label>
                        <textarea id="edit-conditions" name="medical_conditions" rows="3" class="form-control" placeholder="List any medical conditions"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-medications">Current Medications</label>
                        <textarea id="edit-medications" name="current_medications" rows="3" class="form-control" placeholder="List current medications"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-surgeries">Previous Surgeries</label>
                        <textarea id="edit-surgeries" name="previous_surgeries" rows="2" class="form-control" placeholder="List any previous surgeries"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-family-history">Family History</label>
                        <textarea id="edit-family-history" name="family_history" rows="2" class="form-control" placeholder="List relevant family medical history"></textarea>
                    </div>
                </div>
                
                <div class="form-navigation" id="form-bottom">
                    <button type="button" class="cancel-btn" id="cancel-edit">
                        Cancel
                    </button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Uploads Modal -->
<div class="modal-overlay" id="view-uploads-modal" style="display: none;">
    <div class="modal" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-upload"></i> Patient Uploads</h3>
            <button class="close-modal" id="close-uploads-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="patient-info-header">
                <div class="patient-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="patient-meta">
                    <h4 id="uploads-patient-name">Loading patient...</h4>
                    <div class="patient-details">
                        <span id="uploads-patient-age-gender"></span>
                        <span id="uploads-patient-blood-type" class="blood-badge"></span>
                    </div>
                </div>
            </div>
            
            <div class="uploads-container" style="max-height: 400px; overflow-y: auto; margin-top: 20px;">
                <table class="uploads-table">
                    <thead>
                        <tr>
                            <th>Report Type</th>
                            <th>Date of Test</th>
                            <th>Uploaded At</th>
                            <th>Notes</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody id="uploads-table-body">
                        <!-- Uploads will be loaded here -->
                    </tbody>
                </table>
                <div id="no-uploads-message" style="text-align: center; padding: 20px; display: none;">
                    <i class="fas fa-folder-open" style="font-size: 40px; color: #ccc;"></i>
                    <p>No uploads found for this patient</p>
                </div>
            </div>
            
            <div class="form-navigation">
                <button type="button" class="cancel-btn" id="close-uploads-btn">
                    Close
                </button>
            </div>
        </div>
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
<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // -------------------------------
    // Modal Elements
    // -------------------------------
    const editModal = document.getElementById('edit-health-modal');
    const viewUploadsModal = document.getElementById('view-uploads-modal');
    
    // Check if modals exist
    if (!editModal) {
        console.error('Edit health modal element not found!');
    }
    
    // -------------------------------
    // Search Functionality - Fixed
    // -------------------------------
    const searchInput = document.getElementById("patient-search");
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase().trim();
            const tableRows = document.querySelectorAll("#patients-table-body tr");
            
            console.log('Searching for:', searchTerm);
            
            tableRows.forEach(row => {
                const patientNameElement = row.querySelector(".patient-name");
                if (patientNameElement) {
                    const patientName = patientNameElement.textContent.toLowerCase();
                    const shouldShow = patientName.includes(searchTerm);
                    row.style.display = shouldShow ? "" : "none";
                    
                    // Optional: highlight matching text
                    if (searchTerm && shouldShow) {
                        row.style.backgroundColor = "#f8f9fa";
                    } else {
                        row.style.backgroundColor = "";
                    }
                } else {
                    console.warn('Patient name element not found in row:', row);
                }
            });
            
            // Show "No results" message if no rows are visible
            const visibleRows = Array.from(tableRows).filter(row => row.style.display !== "none");
            const noResultsMsg = document.getElementById('no-search-results');
            
            if (visibleRows.length === 0 && searchTerm) {
                if (!noResultsMsg) {
                    const tableBody = document.getElementById('patients-table-body');
                    const noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'no-search-results';
                    noResultsRow.innerHTML = '<td colspan="6" style="text-align: center; padding: 20px; color: #666;"><i class="fas fa-search"></i> No patients found matching your search</td>';
                    tableBody.appendChild(noResultsRow);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
    } else {
        console.error('Search input element not found!');
    }

    // -------------------------------
    // Edit Button Functionality - Fixed
    // -------------------------------
    function attachEditButtonListeners() {
        const editButtons = document.querySelectorAll('.edit-btn');
        console.log('Found edit buttons:', editButtons.length);
        
        editButtons.forEach((button, index) => {
            // Remove existing listeners to prevent duplicates
            button.removeEventListener('click', handleEditClick);
            
            // Add new listener
            button.addEventListener('click', handleEditClick);
            console.log(`Attached listener to edit button ${index + 1}`);
        });
    }
    
    function handleEditClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.currentTarget;
        const patientId = button.getAttribute('data-patient-id');
        
        console.log('Edit button clicked for patient:', patientId);
        
        if (!patientId) {
            console.error('No patient ID found on button');
            alert('Error: Patient ID not found');
            return;
        }
        
        openEditHealthModal(patientId);
    }
    
    // Initialize edit button listeners
    attachEditButtonListeners();
    
    // -------------------------------
    // View Button Functionality
    // -------------------------------
    function attachViewButtonListeners() {
        const viewButtons = document.querySelectorAll('.view-btn');
        console.log('Found view buttons:', viewButtons.length);
        
        viewButtons.forEach((button, index) => {
            button.removeEventListener('click', handleViewClick);
            button.addEventListener('click', handleViewClick);
            console.log(`Attached listener to view button ${index + 1}`);
        });
    }
    
    function handleViewClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.currentTarget;
        const patientId = button.getAttribute('data-patient-id');
        
        console.log('View button clicked for patient:', patientId);
        
        if (!patientId) {
            console.error('No patient ID found on view button');
            alert('Error: Patient ID not found');
            return;
        }
        
        openViewUploadsModal(patientId);
    }
    
    // Initialize view button listeners
    attachViewButtonListeners();

    // -------------------------------
    // Modal Close Functionality
    // -------------------------------
    function setupModalClosing() {
        // Edit Modal Close
        const closeEditModalBtn = document.getElementById('close-edit-modal');
        const cancelEditBtn = document.getElementById('cancel-edit');
        
        if (closeEditModalBtn) {
            closeEditModalBtn.addEventListener('click', closeEditModal);
        }
        
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', closeEditModal);
        }
        
        // View Uploads Modal Close
        const closeUploadsModalBtn = document.getElementById('close-uploads-modal');
        const closeUploadsBtnBottom = document.getElementById('close-uploads-btn');
        
        if (closeUploadsModalBtn) {
            closeUploadsModalBtn.addEventListener('click', closeUploadsModal);
        }
        
        if (closeUploadsBtnBottom) {
            closeUploadsBtnBottom.addEventListener('click', closeUploadsModal);
        }
        
        // Close modals when clicking outside
        if (editModal) {
            editModal.addEventListener('click', function(e) {
                if (e.target === editModal) {
                    closeEditModal();
                }
            });
        }
        
        if (viewUploadsModal) {
            viewUploadsModal.addEventListener('click', function(e) {
                if (e.target === viewUploadsModal) {
                    closeUploadsModal();
                }
            });
        }
    }
    
    setupModalClosing();

    // -------------------------------
    // Form Submission
    // -------------------------------
    const editHealthForm = document.getElementById('edit-health-form');
    if (editHealthForm) {
        editHealthForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitPatientHealthForm();
        });
    }

    // -------------------------------
    // Date & Time Display
    // -------------------------------
    function updateDateTime() {
        const dateTimeElement = document.getElementById('date-time');
        if (dateTimeElement) {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            dateTimeElement.textContent = now.toLocaleDateString('en-US', options);
        }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);

    // -------------------------------
    // Logout Functionality
    // -------------------------------
    function setupLogout() {
        const logoutLink = document.querySelector('.nav-links .nav-item:last-child');
        const logoutOverlay = document.getElementById('logoutOverlay');
        const confirmLogout = document.getElementById('confirmLogout');
        const cancelLogout = document.getElementById('cancelLogout');

        if (logoutLink && logoutOverlay) {
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                logoutOverlay.classList.add('show');
            });
        }

        if (cancelLogout && logoutOverlay) {
            cancelLogout.addEventListener('click', function() {
                logoutOverlay.classList.remove('show');
            });
        }

        if (confirmLogout) {
            confirmLogout.addEventListener('click', function() {
                window.location.href = '../Registration-Login/index.php';
            });
        }

        if (logoutOverlay) {
            logoutOverlay.addEventListener('click', function(e) {
                if (e.target === logoutOverlay) {
                    logoutOverlay.classList.remove('show');
                }
            });
        }
    }
    
    setupLogout();

});

// -------------------------------
// Helper Functions
// -------------------------------
function setFieldValue(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.value = value || '';
    } else {
        console.warn(`Element with ID "${id}" not found.`);
    }
}

function setTextContent(id, text) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = text || '';
    } else {
        console.warn(`Element with ID "${id}" not found.`);
    }
}

function closeEditModal() {
    const modal = document.getElementById('edit-health-modal');
    if (modal) {
        modal.style.display = 'none';
        console.log('Edit modal closed');
    }
}

function closeUploadsModal() {
    const modal = document.getElementById('view-uploads-modal');
    if (modal) {
        modal.style.display = 'none';
        console.log('Uploads modal closed');
    }
}

// -------------------------------
// Main Modal Functions
// -------------------------------
async function openEditHealthModal(patientId) {
    console.log('Opening edit modal for patient:', patientId);

    if (!patientId) {
        console.error('No patient ID provided');
        alert('Error: No patient ID provided');
        return;
    }

    try {
        // Find the patient row using the edit button
        const editButton = document.querySelector(`.edit-btn[data-patient-id="${patientId}"]`);
        if (!editButton) {
            throw new Error('Edit button not found for patient ID: ' + patientId);
        }
        
        const patientRow = editButton.closest('tr');
        if (!patientRow) {
            throw new Error('Patient row not found in table');
        }

        // Extract patient info from the table row
        const patientNameElement = patientRow.querySelector('.patient-name');
        const ageGenderElement = patientRow.querySelector('td:nth-child(2)');
        const bloodTypeElement = patientRow.querySelector('.blood-badge');

        if (!patientNameElement || !ageGenderElement || !bloodTypeElement) {
            throw new Error('Some patient info missing in table row');
        }

        const patientName = patientNameElement.textContent.trim();
        const patientAgeGender = ageGenderElement.textContent.trim();
        const patientBloodType = bloodTypeElement.textContent.trim();

        // Update modal header with patient info
        setTextContent('edit-patient-name', patientName);
        setTextContent('edit-patient-age-gender', patientAgeGender);
        setTextContent('edit-patient-blood-type', patientBloodType);

        const modal = document.getElementById('edit-health-modal');
        if (!modal) {
            throw new Error('Edit health modal not found');
        }

        // Show loading state
        const formFields = modal.querySelectorAll('textarea');
        formFields.forEach(field => {
            field.value = 'Loading...';
            field.disabled = true;
        });

        // Show the modal
        modal.style.display = 'flex';
        console.log('Modal displayed');

        // Fetch patient data
        console.log('Fetching patient data...');
        const response = await fetch(`get_patient_data.php?patient_id=${patientId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parsing JSON:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Invalid JSON received from server. Check server logs.');
        }

        if (!result.success) {
            throw new Error(result.error || result.message || 'Unknown error from server');
        }

        const data = result.data;
        console.log('Patient data loaded:', data);

        // Enable fields and populate with actual data
        formFields.forEach(field => field.disabled = false);

        setFieldValue('edit-patient-id', data.patient_id);
        setFieldValue('edit-allergies', data.allergies);
        setFieldValue('edit-conditions', data.medical_conditions || data.conditions);
        setFieldValue('edit-medications', data.current_medications || data.medications);
        setFieldValue('edit-surgeries', data.previous_surgeries || data.surgeries);
        setFieldValue('edit-family-history', data.family_history);

        console.log('Modal populated successfully');

    } catch (error) {
        console.error('Error loading modal:', error);
        alert(`Failed to load patient health data: ${error.message}`);
        closeEditModal();
    }
}

async function openViewUploadsModal(patientId) {
    console.log('Opening uploads modal for patient:', patientId);

    try {
        const viewButton = document.querySelector(`.view-btn[data-patient-id="${patientId}"]`);
        if (!viewButton) {
            throw new Error('View button not found for patient ID: ' + patientId);
        }
        
        const patientRow = viewButton.closest('tr');
        if (!patientRow) {
            throw new Error('Patient row not found in table');
        }

        const patientNameElement = patientRow.querySelector('.patient-name');
        const ageGenderElement = patientRow.querySelector('td:nth-child(2)');
        const bloodTypeElement = patientRow.querySelector('.blood-badge');

        if (!patientNameElement || !ageGenderElement || !bloodTypeElement) {
            throw new Error('Some patient info missing in table row');
        }

        const patientName = patientNameElement.textContent.trim();
        const patientAgeGender = ageGenderElement.textContent.trim();
        const patientBloodType = bloodTypeElement.textContent.trim();

        setTextContent('uploads-patient-name', patientName);
        setTextContent('uploads-patient-age-gender', patientAgeGender);
        setTextContent('uploads-patient-blood-type', patientBloodType);

        const modal = document.getElementById('view-uploads-modal');
        if (!modal) {
            throw new Error('View uploads modal not found');
        }

        // Show loading state
        const uploadsTableBody = document.getElementById('uploads-table-body');
        if (uploadsTableBody) {
            uploadsTableBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Loading uploads...</td></tr>';
        }
        
        const noUploadsMessage = document.getElementById('no-uploads-message');
        if (noUploadsMessage) {
            noUploadsMessage.style.display = 'none';
        }

        modal.style.display = 'flex';

        // Fetch patient uploads
        const response = await fetch(`get_patient_uploads.php?patient_id=${patientId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseText = await response.text();
        console.log('Raw uploads response:', responseText);

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parsing JSON:', parseError);
            throw new Error('Invalid JSON received from server.');
        }

        if (!result.success) {
            throw new Error(result.error || result.message || 'Unknown error from server');
        }

        const uploads = result.data;

        // Populate uploads table
        if (uploadsTableBody) {
            uploadsTableBody.innerHTML = '';

            if (uploads.length > 0) {
                uploads.forEach(upload => {
                    const row = document.createElement('tr');
                    
                    row.innerHTML = `
                        <td>${upload.report_type || 'N/A'}</td>
                        <td>${upload.DateOfTest ? new Date(upload.DateOfTest).toLocaleDateString() : 'N/A'}</td>
                        <td>${upload.uploaded_at ? new Date(upload.uploaded_at).toLocaleString() : 'N/A'}</td>
                        <td>${upload.notes || 'No notes'}</td>
                        <td>
                            ${upload.file_path ? 
                                `<a href="${upload.file_path}" target="_blank" class="view-file-link">
                                    <i class="fas fa-file-download"></i> View
                                </a>` : 
                                'No file'}
                        </td>
                    `;
                    
                    uploadsTableBody.appendChild(row);
                });
            } else {
                if (noUploadsMessage) {
                    noUploadsMessage.style.display = 'block';
                }
            }
        }

    } catch (error) {
        console.error('Error loading uploads:', error);
        const uploadsTableBody = document.getElementById('uploads-table-body');
        if (uploadsTableBody) {
            uploadsTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: red;">Error loading uploads: ${error.message}</td></tr>`;
        }
    }
}

function submitPatientHealthForm() {
    const form = document.getElementById('edit-health-form');
    if (!form) {
        console.error('Form not found');
        return;
    }
    
    const formData = new FormData(form);
    const patientId = formData.get('patient_id');

    if (!patientId || isNaN(patientId)) {
        alert('Invalid patient ID');
        return;
    }

    const submitBtn = form.querySelector('.submit-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    fetch('update_health_info.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { 
                throw new Error(text || 'Network error'); 
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Update response:', data);
        if (data.success) {
            alert('Patient health information updated successfully!');
            closeEditModal();
        } else {
            alert('Update failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error occurred: ' + error.message);
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    });
}

// QR Code Functions
    function showQRCode(qrCodePath, patientName) {
        const qrModal = document.createElement('div');
        qrModal.className = 'qr-modal-overlay';
        qrModal.innerHTML = `
            <div class="qr-modal-content">
                <div class="qr-modal-header">
                    <h3>QR Code for ${patientName}</h3>
                </div>
                <div class="qr-modal-body">
                    <img src="${qrCodePath}" alt="QR Code for ${patientName}" class="qr-code-full">
                </div>
                <div class="qr-modal-footer">
                    <button onclick="downloadQRCode('${qrCodePath}', 'patient_${patientName.replace(/\s+/g, '_')}_qrcode.png')" class="download-qr-btn">
                        <i class="fas fa-download"></i> Download QR Code
                    </button>
                </div>
            </div>
        `;
        
        // Close modal when clicking outside
        qrModal.addEventListener('click', function(e) {
            if (e.target === qrModal) {
                document.body.removeChild(qrModal);
                document.body.style.overflow = 'auto';
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.body.contains(qrModal)) {
                document.body.removeChild(qrModal);
                document.body.style.overflow = 'auto';
            }
        });
        
        document.body.appendChild(qrModal);
        document.body.style.overflow = 'hidden';
    }

function downloadQRCode(imagePath, fileName) {
    const link = document.createElement('a');
    link.href = imagePath;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
</body>
</html>