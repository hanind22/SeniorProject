<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="patient_dashboard.css">
    <link rel="stylesheet" href="Sidebar.css">

</head>
<body>
     
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

    // Initialize patient data
    $patientData = [];
    $error = '';

    try {
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        
            // Join users and patients tables to get all needed data
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
            } else {
                $error = "Patient record not found";
            }

            // Check if health form needs to be shown
            $showHealthForm = true; // Default to showing form
            if (isset($patientData['health_form_completed']) && $patientData['health_form_completed']) {
                $showHealthForm = false;
            }
        } else {
            $error = "User not logged in";
            // Consider redirecting to login page here
            header('Location: ../Register-Login/index.php');
            // exit();
        }
    } catch (Exception $e) {
        $error = "Error fetching patient data: " . $e->getMessage();
    }
    $patientId = $patientData['patient_id'] ?? null;
    ?>
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
                <a href="patient_dashboard.php" class="nav-item active">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="appointment.php" class="nav-item">
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
                <a href="health_chatbot.php" class="nav-item">
                    <i class="fa-solid fa-comment-medical"></i> Health Chatbot
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
            <div class="page-header">
                <h2 class="page-title"><i class="fa-solid fa-hospital-user"></i> My Dashboard</h2>
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="section-container active" id="profile-section">
                <div class="section">
                    <div class="section-header">
                        <h2>Profile Overview</h2>
                        <button class="btn btn-outline" id="update-profile-btn">Update Info</button>
                    </div>

                    <div class="section-content">
                        <div class="profile-overview">
                            <div class="profile-photo-container">
                               <div class="initials-circle">
                                    <?php
                                    // Get the patient's full name from your data
                                    $full_name = $patientData['full_name'] ?? 'JD'; // Default to "JD" if not available
        
                                   // Extract initials
                                   $names = explode(' ', $full_name);
                                   $initials = '';
        
                                   if (count($names) >= 2) {
                                   // First letter of first name + first letter of last name
                                   $initials = strtoupper(substr($names[0], 0, 1)) . strtoupper(substr(end($names), 0, 1));
                                   } else {
                                  // Just first two letters if only one name
                                  $initials = strtoupper(substr($full_name, 0, 2));
                                  }
        
                               echo $initials;
                                   ?>
                               </div>
</div>
                            
                            <div class="profile-info">
                                <h3 id="patient-name"><?php echo htmlspecialchars($patientData['full_name']); ?></h3>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Patient ID:</span>
                                        <span id="user_id"><?php echo htmlspecialchars($patientData['patient_id']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Date of Birth:</span>
                                        <span id="patient-dob" class="info-value"><?php echo htmlspecialchars($patientData['date_of_birth']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Gender:</span>
                                        <span class="info-badge badge-male"><?php echo htmlspecialchars($patientData['gender']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Blood Type:</span>
                                        <span class="info-badge badge-blood"><?php echo htmlspecialchars($patientData['blood_type']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Email:</span>
                                        <div class="contact-info">
                                            <div class="contact-icon">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                            <span id="patient-email" class="info-value"><?php echo htmlspecialchars($patientData['email']); ?></span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Phone:</span>
                                        <div class="contact-info">
                                            <div class="contact-icon">
                                                <i class="fas fa-phone"></i>
                                            </div>
                                            <span id="patient-phone" class="info-value"><?php echo htmlspecialchars($patientData['phone_number']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="qr-code-container">
                                <div class="qr-code">
                                    <img src="../qrcodes/patient_<?php echo htmlspecialchars($patientId) ?>.png" alt="QR Code" width="150" height="150" id="qr-image">
                                    <p>Health ID QR Code</p>
                                    <button class="btn btn-secondary" id="download-id-card-btn">
                                        <i class="fas fa-download"></i> Download ID Card
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Info Modal Form -->
    <div class="update-form-modal" id="updateFormModal">
        <div class="update-form-container">
            <div class="update-form-header">
                <h2><i class="fas fa-user-edit"></i> Update Your Information</h2>
                <button class="close-update-form" id="closeUpdateForm">&times;</button>
            </div>
            
            <form id="updateInfoForm" action="update_patient_info.php" method="POST">
                <div class="update-form-content">
                    <div class="form-group">
                        <label for="update-full-name">Full Name </label>
                        <input type="text" id="update-full-name" name="full_name" value="<?php echo htmlspecialchars($patientData['full_name']); ?>" readonly >
                    </div> <br>

                    
                    
                    <div class="form-group">
                        <label for="update-email">Email <span class="required">*</span></label>
                        <input type="email" id="update-email" name="email" value="<?php echo htmlspecialchars($patientData['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="update-phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="update-phone" name="phone_number" value="<?php echo htmlspecialchars($patientData['phone_number']); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="update-insurance_provider">Insurance Provider</label>
                        <input type="text" id="update-insurance_provider" name="insurance_provider" rows="3" value="<?php echo htmlspecialchars($patientData['insurance_provider']); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="update-insurance_number">Insurance Number</label>
                        <input type="text" id="update-insurance_number" name="insurance_number" rows="3" value= "<?php echo htmlspecialchars($patientData['insurance_number']); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="update-allergies">Allergies</label>
                        <textarea id="update-allergies" name="allergies" rows="3"><?php echo htmlspecialchars($patientData['allergies'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="update-medical-conditions">Medical Conditions</label>
                        <textarea id="update-medical-conditions" name="medical_conditions" rows="3"><?php echo htmlspecialchars($patientData['medical_conditions'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="update-current-medications">Current Medications</label>
                        <textarea id="update-current-medications" name="current_medications" rows="3"><?php echo htmlspecialchars($patientData['current_medications'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="update-current-medications">Previous Surgeries</label>
                        <textarea id="update-previous_surgeries" name="previous_surgeries" rows="3"><?php echo htmlspecialchars($patientData['previous_surgeries'] ?? ''); ?></textarea>
                    </div>
                
                    <div class="update-form-actions">
                        <button type="button" class="btn btn-outline" id="cancelUpdateBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Health Form Modal (For First-Time Login) -->
    <div class="modal-backdrop" id="healthFormModal" style="<?php echo $showHealthForm ? 'display:flex' : 'display:none'; ?>">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <h2 class="modal-title">Complete Your Health Profile</h2>
                    <p class="modal-subtitle">Required for full dashboard access</p>
                </div>
                <button class="close-modal" id="closeModalBtn" disabled>&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="form-progress">
                    <div class="progress-bar">
                        <div class="progress" id="formProgress"></div>
                    </div>
                    <span class="progress-text">Step <span id="currentStep">1</span> of 3</span>
                </div>
                
                <form id="healthInfoForm" action='PatientForm_action.php' method='POST'>
                    <!-- Personal Information Section -->
                    <div class="form-section active" id="section-personal">
                        <h3 class="section-title"><i class="fas fa-user-circle"></i> Personal Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" class="form-control" value="<?php echo htmlspecialchars($patientData['full_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="dateOfBirth">Date of Birth <span class="required">*</span></label>
                                <input type="date" id="dateOfBirth" name="date_of_birth" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender <span class="required">*</span></label>
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="bloodType">Blood Type <span class="required">*</span></label>
                                <select id="bloodType" name="blood_type" class="form-control" required>
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
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical History Section -->
                    <div class="form-section" id="section-medical">
                        <h3 class="section-title"><i class="fas fa-file-medical"></i> Medical History</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="allergies">Allergies <span class="required">*</span></label>
                                <textarea id="allergies" name="allergies" class="form-control" placeholder="List any allergies you have (medications, food, etc.). If none, please write 'None'." required></textarea>
                                <div class="input-hint">Be specific about reactions if known</div>
                            </div>
                            <div class="form-group full-width">
                                <label for="medicalConditions">Existing Medical Conditions <span class="required">*</span></label>
                                <textarea id="medicalConditions" name="medical_conditions" class="form-control" placeholder="List any existing medical conditions (e.g., diabetes, asthma, hypertension). If none, please write 'None'." required></textarea>
                                <div class="input-hint">Include diagnosis year if possible</div>
                            </div>
                            <div class="form-group full-width">
                                <label for="medications">Current Medications <span class="required">*</span></label>
                                <textarea id="medications" name="current_medications" class="form-control" placeholder="List all medications you are currently taking with dosages. If none, please write 'None'." required></textarea>
                                <div class="input-hint">Include dosage and frequency</div>
                            </div>
                            <div class="form-group full-width">
                                <label for="surgeries">Previous Surgeries <span class="required">*</span></label>
                                <textarea id="surgeries" name="previous_surgeries" class="form-control" placeholder="List any surgeries you've had, including approximate dates if known. If none, please write 'None'." required></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label for="familyHistory">Family Medical History</label>
                                <textarea id="familyHistory" name="family_history" class="form-control" placeholder="Please provide relevant family medical history (e.g., heart disease, cancer, diabetes in parents/siblings)."></textarea>
                                <div class="input-hint">Mention relation and age at diagnosis if known</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency & Insurance Section -->
                    <div class="form-section" id="section-emergency">
                        <h3 class="section-title"><i class="fas fa-phone-alt"></i> Emergency & Insurance</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="emergencyName">Emergency Contact Name <span class="required">*</span></label>
                                <input type="text" id="emergencyName" name="emergency_contact_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="emergencyRelationship">Relationship <span class="required">*</span></label>
                                <select id="emergencyRelationship" name="emergency_contact_relationship" class="form-control" required>
                                    <option value="">Select Relationship</option>
                                    <option value="spouse">Husband/Wife</option>
                                    <option value="parent">Parent</option>
                                    <option value="child">Child</option>
                                    <option value="sibling">Sibling</option>
                                    <option value="friend">Friend</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="emergencyPhone">Emergency Contact Phone <span class="required">*</span></label>
                                <input type="tel" id="emergencyPhone" name="emergency_contact_phone" class="form-control" required>
                                <div class="input-hint">Include country code if international</div>
                            </div>
                            <div class="form-group">
                                <label for="insuranceProvider">Primary Insurance Provider</label>
                                <input type="text" id="insuranceProvider" name="insurance_provider" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="insuranceNumber">Policy/Member ID</label>
                                <input type="text" id="insuranceNumber" name="insurance_number" class="form-control">
                            </div>
                        </div>
                        
                        <div class="consent-checkbox">
                            <input type="checkbox" id="dataConsent" required>
                            <label for="dataConsent">I consent to MediTrack storing and using my health information to provide me with medical services and emergency care.</label>
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn btn-outline" id="prevBtn" disabled>
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                            <i class="fas fa-check-circle"></i> Complete Registration
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
    <script src="patient_dashboard.js"></script>

</body>
</html>