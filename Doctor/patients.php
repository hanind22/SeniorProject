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
            
            // Get patients who have appointments with this doctor
            $patientStmt = $conn->prepare("
                SELECT 
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
                JOIN appointments a ON p.patient_id = a.patient_id
                WHERE a.doctor_id = ?
                GROUP BY p.patient_id
                ORDER BY u.full_name ASC
            ");
            
            // Bind the doctor_id parameter
            $patientStmt->bind_param("i", $doctorData['doctor_id']);
            $patientStmt->execute();
            $patientResult = $patientStmt->get_result();
            
            if ($patientResult->num_rows > 0) {
                while ($row = $patientResult->fetch_assoc()) {
                    // Calculate age from date_of_birth
                    $dob = new DateTime($row['date_of_birth']);
                    $now = new DateTime();
                    $age = $dob->diff($now)->y;
                    
                    // Format last visit date if exists
                    $last_visit = isset($row['last_visit']) && $row['last_visit'] ? 
                        date('M j, Y', strtotime($row['last_visit'])) : 'Never';
                    
                    // Add calculated fields to patient data
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
    <title>Patient Section</title>
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
                    <span class="stat-badge active-patients"><i class="fas fa-heartbeat"></i> <?php echo count(array_filter($patients, function($p) { return $p['status'] === 'active'; })); ?> Active</span>
                    <span class="stat-badge critical-patients"><i class="fas fa-exclamation-triangle"></i> <?php echo count(array_filter($patients, function($p) { return $p['status'] === 'critical'; })); ?> Critical</span>
                </div>
            </div>

            <!-- Search and Add Patient -->
            <div class="patient-actions">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="patient-search" placeholder="Search patients by name">
                    <div class="filter-dropdown">
                        <select class="filter-select" id="patient-filter">
                            <option value="all">All Patients</option>
                            <option value="active">Active</option>
                            <option value="critical">Critical</option>
                            <option value="recent">Recent (7 days)</option>
                        </select>
                    </div>
                </div>
                <button class="add-patient-btn" id="open-add-modal">
                    <i class="fas fa-plus"></i> Add New Patient
                </button>
            </div>

            <!-- Patients Table -->
            <div class="patients-table-container">
                <?php if (count($patients) > 0): ?>
                <table class="patients-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Age/Gender</th>
                            <th>Blood Type</th>
                            <th>Last Visit</th>
                            <th>QR Code</th>
                            <th>Actions</th>
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
                            <td><?php echo $patient['age'] . ' / ' . htmlspecialchars($patient['gender']); ?></td>
                            <td><span class="blood-badge"><?php echo htmlspecialchars($patient['blood_type']); ?></span></td>
                            <td><?php echo $patient['last_visit_formatted']; ?></td>
                            <td>
                              <?php 
                               $qrCodePath = '../qrcodes/patient_' . $patient['patient_id'] . '.png';
                               if (file_exists($qrCodePath)): ?>
                               <a href="<?php echo $qrCodePath; ?>" class="qr-code-link" data-lightbox="qr-code" data-title="QR Code for <?php echo htmlspecialchars($patient['full_name']); ?>">
                                  <img src="<?php echo $qrCodePath; ?>" alt="QR Code for Patient <?php echo $patient['patient_id']; ?>" class="qr-code-img" width="50" height="50">
                               </a>
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
            
            <!-- Pagination -->
            <?php if (count($patients) > 0): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <button class="pagination-btn" id="prev-page" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="pagination-numbers" id="pagination-numbers">
                        <button class="pagination-btn active">1</button>
                        <?php if (count($patients) > 10): ?>
                            <button class="pagination-btn">2</button>
                        <?php endif; ?>
                        <?php if (count($patients) > 20): ?>
                            <button class="pagination-btn">3</button>
                        <?php endif; ?>
                    </div>
                    <button class="pagination-btn" id="next-page" <?php echo count($patients) <= 10 ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
               
            </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Add Patient Modal -->
<div class="modal-overlay" id="patient-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Patient</h3>
            <button class="close-modal" id="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="patient-form" action="add_patient.php" method="post">
                <!-- Progress indicator -->
                <div class="form-progress">
                    <div class="progress-step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-info">
                            <div class="step-icon"><i class="fas fa-user"></i></div>
                            <span class="step-text">Personal Info</span>
                        </div>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-info">
                            <div class="step-icon"><i class="fas fa-phone"></i></div>
                            <span class="step-text">Contact Details</span>
                        </div>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-info">
                            <div class="step-icon"><i class="fas fa-heartbeat"></i></div>
                            <span class="step-text">Medical Info</span>
                        </div>
                    </div>
                </div>
                
                <!-- Form steps -->
                <div class="form-step-container">
                    <!-- Step 1: Personal Information -->
                    <div class="form-step active" id="step-1">
                        <div class="form-section">
                            <h4><i class="fas fa-user-circle"></i> Personal Information</h4>
                            
                            <div class="form-group">
                                <label for="patient-name">Full Name <span class="required">*</span></label>
                                <input type="text" id="patient-name" name="name" required placeholder="John Doe">
                                <div class="help-text">Patient's legal full name</div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="patient-dob">Date of Birth <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <input type="date" id="patient-dob" name="date_of_birth" required>
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="patient-gender">Gender <span class="required">*</span></label>
                                    <div class="select-wrapper">
                                        <select id="patient-gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="patient-blood">Blood Type <span class="required">*</span></label>
                                <div class="select-wrapper">
                                    <select id="patient-blood" name="blood_type" required>
                                        <option value="">Select Blood Type</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                    <i class="fas fa-tint"></i>
                                </div>
                            </div>
                        </div>
                        <div class="form-navigation">
                            <div></div>
                            <button type="button" class="btn btn-primary next-btn" data-next="2">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Contact Information -->
                    <div class="form-step" id="step-2" style="display: none;">
                        <div class="form-section">
                            <div class="form-group">
                                <label for="patient-phone">Phone Number <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="tel" id="patient-phone" name="phone" required placeholder="+961 70 123 456">
                                    <i class="fas fa-phone"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="patient-address">Email <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="email" id="patient-address" rows="2" name="address" required placeholder="John21@gmail.com"></input>
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                            </div>

                            <h4><i class="fas fa-exclamation-triangle"></i> Emergency Contact</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="emergency-name">Name <span class="required">*</span></label>
                                    <input type="text" id="emergency-name" name="emergency_contact_name" required placeholder="Emergency contact name">
                                </div>
                                <div class="form-group">
                                    <label for="emergency-relation">Relationship <span class="required">*</span></label>
                                    <input type="text" id="emergency-relation" name="emergency_contact_relationship" required placeholder="Spouse, Parent, etc.">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="emergency-phone">Phone Number <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="tel" id="emergency-phone" name="emergency_contact_phone" required placeholder="+961 70 987 654">
                                    <i class="fas fa-phone"></i>
                                </div>
                            </div>
                            
                            <h4><i class="fas fa-shield-alt"></i> Insurance Information</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="insurance-provider">Insurance Provider</label>
                                    <input type="text" id="insurance-provider" name="insurance_provider" placeholder="Insurance company name">
                                </div>
                                <div class="form-group">
                                    <label for="insurance-number">Insurance Number</label>
                                    <input type="text" id="insurance-number" name="insurance_number" placeholder="Policy number">
                                </div>
                            </div>
                        </div>
                        <div class="form-navigation">
                            <button type="button" class="btn btn-outline prev-btn" data-prev="1">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-primary next-btn" data-next="3">
                                Next <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Medical Information -->
                    <div class="form-step" id="step-3" style="display: none;">
                        <div class="form-section">
                            <h4><i class="fas fa-heartbeat"></i> Medical Information</h4>
                            
                            <div class="form-group">
                                <label for="patient-status">Status <span class="required">*</span></label>
                                <div class="select-wrapper">
                                    <select id="patient-status" name="status" required>
                                        <option value="checkup">Checkup</option>
                                        <option value="follow-up">Follow-up</option>
                                        <option value="critical">Critical</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-info-circle"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="patient-allergies">Allergies</label>
                                <textarea id="patient-allergies" rows="2" name="allergies" placeholder="Penicillin, Peanuts, etc."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="patient-conditions">Existing Medical Conditions</label>
                                <textarea id="patient-conditions" rows="2" name="medical_conditions" placeholder="Diabetes, Hypertension, etc."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="patient-medications">Current Medications</label>
                                <textarea id="patient-medications" rows="2" name="current_medications" placeholder="Medication names with dosages"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="previous-surgeries">Previous Surgeries</label>
                                <textarea id="previous-surgeries" rows="2" name="previous_surgeries" placeholder="List any previous surgeries"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="family-history">Family Medical History</label>
                                <textarea id="family-history" rows="2" name="family_history" placeholder="Any significant family medical history"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="voice-notes"><i class="fas fa-microphone"></i> Additional Notes</label>
                                <div class="voice-recorder">
                                    <button type="button" id="record-btn" class="btn btn-outline voice-btn">
                                        <i class="fas fa-microphone"></i> Start Recording
                                    </button>
                                    <button type="button" id="stop-btn" class="btn btn-outline voice-btn" disabled>
                                        <i class="fas fa-stop"></i> Stop
                                    </button>
                                    <span id="recording-status" class="recording-status">Ready to record</span>
                                </div>
                                <textarea id="voice-notes" rows="3" name="voice_notes" placeholder="Record any additional notes..."></textarea>
                            </div>
                        </div>
                        <div class="form-navigation">
                            <button type="button" class="btn btn-outline prev-btn" data-prev="2">
                                <i class="fas fa-arrow-left"></i> Previous
                            </button>
                            <button type="submit" class="btn btn-success submit-btn">
                                <i class="fas fa-save"></i> Save Patient
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Health Info Modal -->
<div class="modal-overlay" id="edit-health-modal" style="display: none;">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Patient Health Information</h3>
            <button class="close-modal" id="close-edit-modal">&times;</button>
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
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------
    // General Modal Handling
    // -------------------------------
    const editModal = document.getElementById('edit-health-modal');

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    // Open Add Modal
    document.getElementById('open-add-modal').addEventListener('click', function () {
        document.getElementById('patient-modal').style.display = 'flex';
    });

    // Close Add Modal
    document.getElementById('close-modal').addEventListener('click', function () {
        document.getElementById('patient-modal').style.display = 'none';
    });

    // Close Edit Modal
    document.getElementById('close-edit-modal').addEventListener('click', closeEditModal);
    document.getElementById('cancel-edit').addEventListener('click', closeEditModal);

    // -------------------------------
    // Edit Button Handling
    // -------------------------------
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const patientId = this.getAttribute('data-patient-id');
            openEditHealthModal(patientId);
        });
    });

    document.getElementById('edit-health-form').addEventListener('submit', function (e) {
        e.preventDefault();
        submitPatientHealthForm(); // Assume this is defined elsewhere
    });

    // -------------------------------
    // Search Functionality
    // -------------------------------
    document.getElementById("patient-search").addEventListener("input", function () {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll("#patients-table-body tr").forEach(row => {
            const name = row.querySelector(".patient-name").textContent.toLowerCase();
            row.style.display = name.includes(searchTerm) ? "" : "none";
        });
    });

    // -------------------------------
    // Date & Time Display
    // -------------------------------
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
    setInterval(updateDateTime, 60000);

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
        

    // -------------------------------
    // Pagination Placeholder
    // -------------------------------
    document.getElementById('next-page').addEventListener('click', function () {
        // Implement pagination logic here
    });

    document.getElementById('prev-page').addEventListener('click', function () {
        // Implement pagination logic here
    });

    // -------------------------------
    // Lightbox for QR Code
    // -------------------------------
    const lightboxOverlay = document.createElement('div');
    lightboxOverlay.className = 'lightbox-overlay';

    const lightboxContent = document.createElement('div');
    lightboxContent.className = 'lightbox-content';

    const lightboxTitle = document.createElement('div');
    lightboxTitle.className = 'lightbox-title';

    lightboxOverlay.appendChild(lightboxContent);
    lightboxOverlay.appendChild(lightboxTitle);
    document.body.appendChild(lightboxOverlay);

    lightboxOverlay.addEventListener('click', function (e) {
        if (e.target === lightboxOverlay) {
            lightboxOverlay.classList.remove('active');
        }
    });

    document.querySelectorAll('.qr-code-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const img = document.createElement('img');
            img.src = this.href;
            img.alt = this.querySelector('img').alt;

            lightboxContent.innerHTML = '';
            lightboxContent.appendChild(img);

            lightboxTitle.textContent = this.dataset.title || 'QR Code';

            lightboxOverlay.classList.add('active');
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            lightboxOverlay.classList.remove('active');
        }
    });

    // -------------------------------
    // Form Steps Navigation
    // -------------------------------
    const formSteps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');

    formSteps.forEach((step, index) => {
        if (index !== 0) step.style.display = 'none';
    });

    document.querySelectorAll('.next-btn').forEach(button => {
        button.addEventListener('click', function () {
            const currentStep = document.querySelector('.form-step.active');
            const nextStepId = this.getAttribute('data-next');
            const nextStep = document.getElementById(`step-${nextStepId}`);

            if (validateStep(currentStep)) {
                currentStep.classList.remove('active');
                currentStep.style.display = 'none';

                nextStep.classList.add('active');
                nextStep.style.display = 'block';

                updateProgress(nextStepId);
            }
        });
    });

    document.querySelectorAll('.prev-btn').forEach(button => {
        button.addEventListener('click', function () {
            const currentStep = document.querySelector('.form-step.active');
            const prevStepId = this.getAttribute('data-prev');
            const prevStep = document.getElementById(`step-${prevStepId}`);

            currentStep.classList.remove('active');
            currentStep.style.display = 'none';

            prevStep.classList.add('active');
            prevStep.style.display = 'block';

            updateProgress(prevStepId);
        });
    });

    function validateStep(step) {
        const inputs = step.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.style.borderColor = '#e74c3c';
                isValid = false;
            } else {
                input.style.borderColor = '#ddd';
            }
        });

        return isValid;
    }

    function updateProgress(activeStep) {
        progressSteps.forEach(step => {
            step.classList.remove('active');
            if (parseInt(step.getAttribute('data-step')) <= parseInt(activeStep)) {
                step.classList.add('active');
            }
        });
    }
});

// -------------------------------
// Improved Fetch and Modal Fill Function
// -------------------------------
// Safely sets a field's value by ID
function setFieldValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value;
    else console.warn(`Element with ID "${id}" not found.`);
}

// Safely sets textContent by ID
function setTextContent(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
    else console.warn(`Element with ID "${id}" not found.`);
}

// Function to close the modal
function closeEditModal() {
    const modal = document.getElementById('edit-health-modal');
    if (modal) modal.style.display = 'none';
}

// Main function to open and populate the edit modal
async function openEditHealthModal(patientId) {
    console.log('Opening edit modal for patient:', patientId);

    try {
        const patientRow = document.querySelector(`.edit-btn[data-patient-id="${patientId}"]`)?.closest('tr');
        if (!patientRow) throw new Error('Patient row not found in table');

        const patientName = patientRow.querySelector('.patient-name')?.textContent;
        const patientAgeGender = patientRow.querySelector('td:nth-child(2)')?.textContent;
        const patientBloodType = patientRow.querySelector('.blood-badge')?.textContent;

        if (!patientName || !patientAgeGender || !patientBloodType) {
            throw new Error('Some patient info missing in table row');
        }

        setTextContent('edit-patient-name', patientName);
        setTextContent('edit-patient-age-gender', patientAgeGender);
        setTextContent('edit-patient-blood-type', patientBloodType);

        const modal = document.getElementById('edit-health-modal');
        if (!modal) throw new Error('Edit health modal not found');

        const formFields = modal.querySelectorAll('textarea');
        formFields.forEach(field => {
            field.value = 'Loading...';
            field.disabled = true;
        });

        modal.style.display = 'flex';

        const response = await fetch(`get_patient_data.php?patient_id=${patientId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });

        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (err) {
            console.error('Error parsing JSON:', err);
            throw new Error('Invalid JSON received from server.');
        }

        if (!result.success) {
            throw new Error(result.error || result.message || 'Unknown error from server');
        }

        const data = result.data;

        // Enable fields and fill in actual data
        formFields.forEach(field => field.disabled = false);

        setFieldValue('edit-patient-id', data.patient_id);
        setFieldValue('edit-allergies', data.allergies || '');
        setFieldValue('edit-conditions', data.medical_conditions || data.conditions || '');
        setFieldValue('edit-medications', data.current_medications || data.medications || '');
        // setFieldValue('edit-notes', data.notes || '');

    } catch (error) {
        console.error('Error loading modal:', error);
        alert('Failed to load patient health data. Check console for details.');
        closeEditModal();
    }
}

function submitPatientHealthForm() {
    const form = document.getElementById('edit-health-form');
    const formData = new FormData(form);
    const patientId = formData.get('patient_id');

    if (!patientId || isNaN(patientId)) {
        alert('Invalid patient ID');
        return;
    }

    const submitBtn = form.querySelector('.submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Saving...';

    fetch('update_health_info.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text || 'Network error'); });
        }
        return response.json();
    })
    .then(data => {
        console.log('Update response:', data);
        if (data.success) {
            alert('Patient updated successfully!');
            // update UI if needed
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
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Save Changes';
    });
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