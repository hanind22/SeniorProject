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
                    SELECT u.full_name, d.specialty 
                    FROM users u
                    JOIN doctors d ON u.user_id = d.user_id
                    WHERE d.doctor_id = ?
                ");
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $doctorData = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($doctorData) {
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
    <link rel="stylesheet" href="patient.css">
    <!-- Add jQuery for AJAX functionality -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                <a href="Secretary_dashboard.php" class="nav-item">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="fa-solid fa-file-medical"></i> Patients
                </a> 
                <a href="appointments.php" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

         <!-- Main Content -->
        <div class="content">

        <div class="doctor-info-header">
                <h2>Secretary: <?php echo htmlspecialchars($secretaryData['full_name'] ?? 'N/A'); ?></h2>
                <?php if (!empty($doctorData)): ?>
                    <p>Assigned to Dr. <?php echo htmlspecialchars($doctorData['full_name']); ?> | <?php echo htmlspecialchars($doctorData['specialty'] ?? 'N/A'); ?> </p>
                <?php endif; ?>
            </div>

             <!-- Search and Add Patient -->
            <div class="patient-actions">
                <form method="GET" class="search-form" id="search-form">
                    <div class="search-container">
                        <input type="text" name="search" id="search-input" placeholder="Search patients..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <div id="search-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </form>
                <button class="add-patient-btn" id="open-add-modal">
                    <i class="fas fa-plus"></i> Add New Patient
                </button>
            </div>

            <!-- Patients Table -->
            <div class="patients-table-container">
                <div id="patients-table-wrapper">
                    <?php if (count($patients) > 0): ?>
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Email</th>
                                <th> ID</th>
                                <th>Age / Gender</th>
                                <th>Blood Type</th>
                                <th>Last Visit</th>
                                <th>QR Code</th>
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
                                <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                <td><?php echo $patient['age'] . ' / ' . htmlspecialchars($patient['gender']); ?></td>
                                <td><span class="blood-badge"><?php echo htmlspecialchars($patient['blood_type'] ?? 'N/A'); ?></span></td>
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
                                
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="no-patients">
                            <i class="fas fa-user-slash"></i>
                            <p>No patients found</p>
                            <?php if (!empty($error)): ?>
                                <p style="color: #666; font-size: 14px;"><?php echo htmlspecialchars($error); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
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
            <div id="form-status-message" class="status-message" style="display: none;"></div>
            <form id="patient-form" action="Add_Patients.php" method="post">
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
                                <input type="text" id="patient-name" name="full_name" required placeholder="John Doe">
                                <div class="help-text">Patient's legal full name</div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="patient-dob">Date of Birth <span class="required">*</span></label>
                                    <div class="input-with-icon">
                                        <input type="date" id="patient-dob" name="date_of_birth" required>
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
                                    <input type="tel" id="patient-phone" name="phone_number" required placeholder="+961 70 123 456">
                                    <i class="fas fa-phone"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="patient-email">Email <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="email" id="patient-email" name="email" required placeholder="John21@gmail.com">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="patient-password">Password <span class="required">*</span></label>
                                <div class="input-with-icon">
                                    <input type="password" class="form-control" id="patient-password" name="password" placeholder="Password" required>
                                    <i class="fa-solid fa-key"></i>
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
                                   <select id="emergency-relation" name="emergency_contact_relationship" required>
                                   <option value="">Select Relationship</option>
                                    <option value="spouse">Husband/Wife</option>
                                    <option value="parent">Parent</option>
                                    <option value="child">Child</option>
                                    <option value="sibling">Sibling</option>
                                    <option value="friend">Friend</option>
                                    <option value="other">Other</option>
                                   </select>
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

<script>
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

document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const openModalBtn = document.getElementById('open-add-modal');
    const closeModalBtn = document.getElementById('close-modal');
    const modal = document.getElementById('patient-modal');
    
    // Form navigation elements
    const formSteps = document.querySelectorAll('.form-step');
    const progressSteps = document.querySelectorAll('.progress-step');
    const nextButtons = document.querySelectorAll('.next-btn');
    const prevButtons = document.querySelectorAll('.prev-btn');
    
    // Open modal
    if (openModalBtn) {
        openModalBtn.addEventListener('click', function() {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
            resetFormSteps(); // Reset form to first step when opening
        });
    }
    
    // Close modal
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        });
    }
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
    
    // Next button functionality
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currentStep = this.closest('.form-step');
            const nextStepId = this.getAttribute('data-next');
            const nextStep = document.getElementById(`step-${nextStepId}`);
            
            if (validateStep(currentStep)) {
                currentStep.classList.remove('active');
                currentStep.style.display = 'none';
                
                nextStep.classList.add('active');
                nextStep.style.display = 'block';
                
                updateProgressSteps(nextStepId);
            }
        });
    });
    
    // Previous button functionality
    prevButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currentStep = this.closest('.form-step');
            const prevStepId = this.getAttribute('data-prev');
            const prevStep = document.getElementById(`step-${prevStepId}`);
            
            currentStep.classList.remove('active');
            currentStep.style.display = 'none';
            
            prevStep.classList.add('active');
            prevStep.style.display = 'block';
            
            updateProgressSteps(prevStepId);
        });
    });
    
    // Validate current step before proceeding
    function validateStep(step) {
        const requiredInputs = step.querySelectorAll('[required]');
        let isValid = true;
        
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                isValid = false;
            } else {
                input.classList.remove('error');
            }
        });
        
        // Special validation for specific fields if needed
        // Example: Validate email format
        const emailInput = step.querySelector('input[type="email"]');
        if (emailInput && emailInput.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value)) {
                emailInput.classList.add('error');
                isValid = false;
            }
        }
        
        if (!isValid) {
            // Scroll to first error
            const firstError = step.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        return isValid;
    }
    
    // Update progress steps visualization
    function updateProgressSteps(activeStep) {
        progressSteps.forEach(step => {
            const stepNumber = parseInt(step.getAttribute('data-step'));
            
            step.classList.remove('active');
            if (stepNumber < activeStep) {
                step.classList.add('completed');
            } else if (stepNumber === parseInt(activeStep)) {
                step.classList.add('active');
            } else {
                step.classList.remove('completed');
            }
        });
    }
    
    // Reset form to first step
    function resetFormSteps() {
        formSteps.forEach((step, index) => {
            if (index === 0) {
                step.classList.add('active');
                step.style.display = 'block';
            } else {
                step.classList.remove('active');
                step.style.display = 'none';
            }
        });
        
        progressSteps.forEach((step, index) => {
            if (index === 0) {
                step.classList.add('active');
            } else {
                step.classList.remove('active', 'completed');
            }
        });
    }
    
    // Remove error class when user starts typing
    document.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                this.classList.remove('error');
            }
        });
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
});

document.getElementById('patient-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const submitBtn = form.querySelector('.submit-btn');
    const formStatus = document.getElementById('form-status-message');
    const modal = document.getElementById('patient-modal');
    const qrCodeContainer = document.getElementById('qr-code-container'); // Add this container in your HTML
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    if (formStatus) {
        formStatus.style.display = 'none';
        formStatus.textContent = '';
        formStatus.className = '';
    }
    
    // Clear previous QR code if exists
    if (qrCodeContainer) {
        qrCodeContainer.innerHTML = '';
    }
    
    try {
        const formData = new FormData(form);
        const response = await fetch('Add_Patients.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        let responseData;
        
        try {
            responseData = JSON.parse(responseText);
            
            if (responseData.success) {
                // Show success message with QR code if available
                showSuccessMessage(
                    responseData.message || 'Patient saved successfully!', 
                    modal, 
                    formStatus,
                    responseData.data?.qr_code_path,
                    responseData.data?.patient_id
                );
            } else {
                throw new Error(responseData.message || 'Failed to save patient');
            }
        } catch (e) {
            // If JSON parsing fails but operation succeeded
            if (responseText.toLowerCase().includes('success')) {
                showSuccessMessage(
                    'Patient saved successfully!', 
                    modal, 
                    formStatus
                );
            } else {
                throw new Error('Server response error. Please check console for details.');
            }
        }
    } catch (error) {
        console.error('Submission error:', error);
        
        if (formStatus) {
            formStatus.textContent = error.message;
            formStatus.className = 'error';
            formStatus.style.display = 'block';
            formStatus.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Patient';
    }
});

function showSuccessMessage(message, modal, formStatus, qrCodePath = null, patientId = null) {
    // Show success message
    if (formStatus) {
        formStatus.textContent = message;
        formStatus.className = 'success';
        formStatus.style.display = 'block';
        
        // Display QR code if available
        if (qrCodePath && patientId) {
            const qrCodeHTML = `
                <div class="qr-code-success">
                    <h4>Patient QR Code</h4>
                    <img src="${qrCodePath}" alt="Patient QR Code" class="qr-code-image">
                    <p class="patient-id">Patient ID: ${patientId}</p>
                    <button onclick="downloadQRCode('${qrCodePath}', 'patient_${patientId}_qrcode.png')" 
                            class="download-qr-btn">
                        <i class="fas fa-download"></i> Download QR Code
                    </button>
                </div>
            `;
            formStatus.insertAdjacentHTML('beforeend', qrCodeHTML);
        }
    }
    
    // Reset form
    document.getElementById('patient-form').reset();
    
    // Close modal after 3 seconds (giving time to see QR code)
    setTimeout(() => {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Show toast notification
        showToast(message);
        
        // Refresh the page to show updated data
        window.location.reload();
    }, 3000);
}

// Download QR code function
function downloadQRCode(imagePath, fileName) {
    const link = document.createElement('a');
    link.href = imagePath;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 500);
    }, 3000);
}

// Real-time search functionality
$(document).ready(function() {
    // Add a loading indicator to the search input
    const searchInput = $('#search-input');
    const searchLoading = $('#search-loading');
    const patientsTableWrapper = $('#patients-table-wrapper');
    
    // Variable to track the last search request
    let lastSearchRequest = null;
    
    // Function to perform search
    function performSearch(query) {
        // Show loading indicator
        searchLoading.show();
        
        // Cancel previous request if it exists
        if (lastSearchRequest) {
            lastSearchRequest.abort();
        }
        
        // Make AJAX request
        lastSearchRequest = $.ajax({
            url: 'patients.php',
            type: 'GET',
            data: { search: query },
            success: function(data) {
                // Extract the table content from the response
                const responseHTML = $(data);
                const newTableContent = responseHTML.find('#patients-table-wrapper').html();
                
                // Update the table content
                patientsTableWrapper.html(newTableContent);
                
                // Hide loading indicator
                searchLoading.hide();
            },
            error: function(xhr, status, error) {
                if (status !== 'abort') {
                    console.error('Search error:', error);
                    searchLoading.hide();
                }
            }
        });
    }
    
    // Event handler for search input
    searchInput.on('input', function() {
        const query = $(this).val().trim();
        
        // Only search if query is not empty or has at least 1 characters
        if (query.length === 0 || query.length >= 1) {
            performSearch(query);
        }
    });
    
    // Also trigger search when form is submitted (for browsers that don't support input event well)
    $('#search-form').on('submit', function(e) {
        e.preventDefault();
        const query = searchInput.val().trim();
        performSearch(query);
    });
    
    // Add a small delay to prevent too many requests while typing
    searchInput.on('input', $.debounce(300, function() {
        const query = $(this).val().trim();
        if (query.length === 0 || query.length >= 2) {
            performSearch(query);
        }
    }));
});

// Debounce function to limit how often a function is called
$.debounce = function(wait, func) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        const later = function() {
            timeout = null;
            func.apply(context, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};
</script>

</body>
</html>