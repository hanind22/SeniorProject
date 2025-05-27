<?php
session_start();
include('../db-config/connection.php');

// Initialize variables
$patientId = $_SESSION['user_id'] ?? null;
$doctors = [];
$error = '';
$success = '';

// Fetch doctors this patient has appointments with
if ($patientId) {
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT d.doctor_id, u.full_name, d.specialty 
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.doctor_id
            JOIN users u ON d.user_id = u.user_id
            WHERE a.patient_id = ?
            ORDER BY u.full_name
        ");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctors = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $error = "Error fetching doctors: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $doctorId = $_POST['doctor_id'] ?? null;
    $reportType = $_POST['report_type'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $dateOfTest = $_POST['date_of_test'] ?? date('Y-m-d');
    
    try {
        // Validate inputs
        if (empty($doctorId)) {
            throw new Exception("Please select a doctor");
        }
        
        if (empty($reportType)) {
            throw new Exception("Please select a report type");
        }
        
        // Handle file upload
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['report_file'];
            
            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception("Only JPG, PNG, GIF, and PDF files are allowed");
            }
            
            if ($file['size'] > $maxSize) {
                throw new Exception("File size must be less than 10MB");
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = '../uploads/patient_reports/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('report_') . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception("Failed to upload file");
            }
            
            // Insert into database
            $stmt = $conn->prepare("
                INSERT INTO patientuploads 
                (doctor_id, patient_id, report_type, file_path, uploaded_at, DateOfTest, notes) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->bind_param("iissss", $doctorId, $patientId, $reportType, $filePath, $dateOfTest, $notes);
            
            if ($stmt->execute()) {
                 header("Location: health_report.php?success=1");
                 exit();
            } else {
                throw new Exception("Failed to save report to database");
            }
        } else {
            throw new Exception("Please upload a file");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Sidebar.css">
    <style>
        
        .main-content {
            flex: 1;
            padding: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .page-title {
            color:rgb(32, 59, 105);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #718096;
            
        }

        .report-form-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            color:rgb(32, 59, 105);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .file-upload-area {
            border: 3px dashed #cbd5e0;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .file-upload-area:hover {
            border-color: #667eea;
            background-color: #f7fafc;
        }

        .file-upload-area.dragover {
            border-color: #667eea;
            background-color: #edf2f7;
        }

        .file-upload-icon {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        .file-upload-text {
            color: #718096;
            font-size: 1.1rem;
        }

        .file-upload-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 10px;
            display: none;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .file-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .file-details {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-icon {
            font-size: 2rem;
            color: #667eea;
        }

        .remove-file {
            background: #fed7d7;
            color: #e53e3e;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-file:hover {
            background: #feb2b2;
        }

        .report-type-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .report-type-card {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .report-type-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .report-type-card.selected {
            border-color: #667eea;
            background: #edf2f7;
        }

        .report-type-icon {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .priority-selector {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .priority-option {
            flex: 1;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .priority-option.low {
            border-color: #68d391;
            color: #38a169;
        }

        .priority-option.medium {
            border-color: #fbb40a;
            color: #d69e2e;
        }

        .priority-option.high {
            border-color: #fc8181;
            color: #e53e3e;
        }

        .priority-option.selected {
            background-color: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .submit-btn {
            background: linear-gradient(90deg,rgba(32, 59, 105, 0.86),rgb(7, 143, 125));
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 1rem;
            overflow: hidden;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg,rgb(32, 59, 105),rgb(32, 100, 105));
            width: 0%;
            transition: width 0.3s ease;
        }

        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            display: none;
        }

        .recent-reports {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .report-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .report-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .report-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fef5e7;
            color: #d69e2e;
        }

        .status-reviewed {
            background: #c6f6d5;
            color: #2f855a;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .report-type-selector {
                grid-template-columns: 1fr;
            }

            .priority-selector {
                flex-direction: column;
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
                <a href="medical_history.php" class="nav-item">
                    <i class="fa-solid fa-file-medical"></i> Medical History &<br>Prescriptions
                </a>
                <a href="health_report.php" class="nav-item active">
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

        <main class="main-content">
            <div class="page-header">
                <h2 class="page-title"><i class="fa-solid fa-notes-medical"></i> Health Reports</h2>
                <p class="page-subtitle">Upload and share your medical reports with your healthcare providers</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="report-form-container">
                <form id="healthReportForm" method="POST" enctype="multipart/form-data">
                    <!-- Doctor Selection Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-md"></i>
                            Select Doctor
                        </h3>
                        <div class="form-group">
                            <label class="form-label" for="doctorSelect">Choose your doctor *</label>
                            <select class="form-select" id="doctorSelect" name="doctor_id" required>
                                <option value="">Select a doctor...</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['doctor_id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Report Type Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i>
                            Report Type
                        </h3>
                        <div class="report-type-selector">
                            <div class="report-type-card" data-type="xray">
                                <div class="report-type-icon">
                                    <i class="fas fa-x-ray"></i>
                                </div>
                                <div>X-Ray</div>
                            </div>
                            <div class="report-type-card" data-type="mri">
                                <div class="report-type-icon">
                                    <i class="fas fa-brain"></i>
                                </div>
                                <div>MRI Scan</div>
                            </div>
                            <div class="report-type-card" data-type="ct">
                                <div class="report-type-icon">
                                    <i class="fas fa-lungs"></i>
                                </div>
                                <div>CT Scan</div>
                            </div>
                            <div class="report-type-card" data-type="blood">
                                <div class="report-type-icon">
                                    <i class="fas fa-vial"></i>
                                </div>
                                <div>Blood Test</div>
                            </div>
                            <div class="report-type-card" data-type="ultrasound">
                                <div class="report-type-icon">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <div>Ultrasound</div>
                            </div>
                            <div class="report-type-card" data-type="other">
                                <div class="report-type-icon">
                                    <i class="fas fa-file-medical"></i>
                                </div>
                                <div>Other</div>
                            </div>
                        </div>
                        <input type="hidden" id="reportType" name="report_type" required>
                    </div>

                    <!-- File Upload Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Upload Files
                        </h3>
                        <div class="form-group">
                            <div class="file-upload-area" id="fileUploadArea">
                                <input type="file" class="file-upload-input" id="fileInput" name="report_file" required accept="image/*,.pdf">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">
                                    <strong>Click to upload</strong> or drag and drop<br>
                                    Images or PDF files (Max 10MB)
                                </div>
                            </div>
                            <div class="file-preview" id="filePreview"></div>
                        </div>
                    </div>

                    <!-- Notes Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-sticky-note"></i>
                            Additional Notes
                        </h3>
                        <div class="form-group">
                            <label class="form-label" for="reportNotes">Add your notes, symptoms, or questions</label>
                            <textarea class="form-textarea" id="reportNotes" name="notes" placeholder="Describe your symptoms, when they started, any pain levels, or specific questions for your doctor..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reportDate">Date of test/examination</label>
                            <input type="date" class="form-input" id="reportDate" name="date_of_test" required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" name="submit_report" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        Submit Health Report
                    </button>
                </form>
            </div>

            <!-- Recent Reports Section -->
            <div class="recent-reports">
                <h3 class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Reports
                </h3>
                <?php
                if ($patientId) {
                    $stmt = $conn->prepare("
                        SELECT pu.*, u.full_name AS doctor_name 
                        FROM patientuploads pu
                        JOIN doctors d ON pu.doctor_id = d.doctor_id
                        JOIN users u ON d.user_id = u.user_id
                        WHERE pu.patient_id = ?
                        ORDER BY pu.uploaded_at DESC
                        LIMIT 5
                    ");
                    $stmt->bind_param("i", $patientId);
                    $stmt->execute();
                    $recentReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    if (count($recentReports) > 0) {
                        foreach ($recentReports as $report) {
                            echo '
                            <div class="report-item">
                                <div class="report-details">
                                    <strong>' . htmlspecialchars(ucfirst($report['report_type'])) . '</strong><br>
                                    <small>Submitted to Dr. ' . htmlspecialchars($report['doctor_name']) . ' on ' . date('M j, Y', strtotime($report['uploaded_at'])) . '</small>
                                </div>
                                <a href="' . htmlspecialchars($report['file_path']) . '" target="_blank" class="report-status status-reviewed">View</a>
                            </div>';
                        }
                    } else {
                        echo '<p>No recent reports found</p>';
                    }
                }
                ?>
            </div>
        </main>
    </div>

    <script>
        // Keep all your existing JavaScript
        // Update to handle real form submission
        
        document.addEventListener('DOMContentLoaded', function() {
            // Update date and time
            updateDateTime();
            setInterval(updateDateTime, 60000);
            
            // Set default date to today
            document.getElementById('reportDate').valueAsDate = new Date();
            
            // Report type selection
            const reportTypeCards = document.querySelectorAll('.report-type-card');
            const reportTypeInput = document.getElementById('reportType');
            
            reportTypeCards.forEach(card => {
                card.addEventListener('click', () => {
                    reportTypeCards.forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    reportTypeInput.value = card.dataset.type;
                });
            });
            
            // File upload preview
            const fileInput = document.getElementById('fileInput');
            const filePreview = document.getElementById('filePreview');
            
            fileInput.addEventListener('change', function(e) {
                filePreview.innerHTML = '';
                
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'file-info';
                    
                    const fileDetails = document.createElement('div');
                    fileDetails.className = 'file-details';
                    
                    const fileIcon = document.createElement('div');
                    fileIcon.className = 'file-icon';
                    
                    if (file.type.startsWith('image/')) {
                        fileIcon.innerHTML = '<i class="fas fa-image"></i>';
                        
                        // Create image preview
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        filePreview.appendChild(img);
                    } else if (file.type === 'application/pdf') {
                        fileIcon.innerHTML = '<i class="fas fa-file-pdf"></i>';
                    }
                    
                    const fileInfo = document.createElement('div');
                    fileInfo.innerHTML = `
                        <strong>${file.name}</strong><br>
                        <small>${formatFileSize(file.size)}</small>
                    `;
                    
                    fileDetails.appendChild(fileIcon);
                    fileDetails.appendChild(fileInfo);
                    fileDiv.appendChild(fileDetails);
                    filePreview.appendChild(fileDiv);
                    filePreview.style.display = 'block';
                }
            });
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
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
        });
    </script>
</body>
</html>