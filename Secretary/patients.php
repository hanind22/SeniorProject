<?php
// Move session_start() to the very top - before any HTML output

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

// Handle session messages - moved here to be before HTML output
$session_error = '';
$session_success = '';
if (isset($_SESSION['error'])) {
    $session_error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $session_success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Initialize patient data (this section seems misplaced in your original code)
$patientData = [];
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
        }
    }
} catch (Exception $e) {
    // Handle error silently or log it
    error_log("Error fetching patient data: " . $e->getMessage());
}
$patientId = $patientData['patient_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="patient_dashboard.css">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <?php
    // Display session messages
    if (!empty($session_error)) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($session_error) . '</div>';
    }
    if (!empty($session_success)) {
        echo '<div class="alert alert-success">' . htmlspecialchars($session_success) . '</div>';
    }

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
                <a href="#" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

         <!-- Main Content -->
        <div class="content">

            <!-- Search and Add Patient -->
            <div class="patient-actions">
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
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    if (formStatus) {
        formStatus.style.display = 'none';
    }
    
    try {
        const formData = new FormData(form);
        
        const response = await fetch('Add_Patients.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        
        // Try to parse as JSON, but handle HTML responses gracefully
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            // If response is not JSON, it's likely an error page or redirect
            console.error('Response was not JSON:', responseText);
            throw new Error('Server returned an unexpected response. Please check the server logs.');
        }
        
        if (result.success) {
            formStatus.textContent = result.message || 'Patient saved successfully!';
            formStatus.className = 'success';
            formStatus.style.display = 'block';
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(result.message || 'Failed to save patient');
        }
    } catch (error) {
        console.error('Submission error:', error);
        formStatus.textContent = 'Error: ' + error.message;
        formStatus.className = 'error';
        formStatus.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Patient';
    }
});
</script>

</body>
</html>