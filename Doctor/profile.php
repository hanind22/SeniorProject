<?php
session_start();
include('../db-config/connection.php');

// Initialize variables
$doctorData = [];
$availabilityData = [];
$error = '';

try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];

        // 1. Get full doctor and user profile
        $stmt = $conn->prepare("
            SELECT u.*, d.*
            FROM users u
            LEFT JOIN doctors d ON u.user_id = d.user_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $doctorData = $result->fetch_assoc();

            // 2. If doctor_id exists, fetch availability from work_place (with address)
            if (!empty($doctorData['doctor_id'])) {
                $stmt = $conn->prepare("
                    SELECT day, start_time, end_time, address, status,place_name
                    FROM work_place
                    WHERE doctor_id = ?
                    ORDER BY 
                        CASE day 
                            WHEN 'Monday' THEN 1
                            WHEN 'Tuesday' THEN 2
                            WHEN 'Wednesday' THEN 3
                            WHEN 'Thursday' THEN 4
                            WHEN 'Friday' THEN 5
                            WHEN 'Saturday' THEN 6
                            WHEN 'Sunday' THEN 7
                        END, start_time
                ");
                $stmt->bind_param("i", $doctorData['doctor_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $availabilityData[] = $row;
                }

                // 3. Stats
                $totalPatients = 0;
                $appointmentsToday = 0;
                $urgentCases = 0;

                // Total Patients
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_id) AS total FROM appointments WHERE doctor_id = ?");
                $stmt->bind_param("i", $doctorData['doctor_id']);
                $stmt->execute();
                $stmt->bind_result($totalPatients);
                $stmt->fetch();
                $stmt->close();

                // Appointments Today
                $today = date('Y-m-d');
                $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ?");
                $stmt->bind_param("is", $doctorData['doctor_id'], $today);
                $stmt->execute();
                $stmt->bind_result($appointmentsToday);
                $stmt->fetch();
                $stmt->close();

                // Urgent Cases
                $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM appointments WHERE doctor_id = ? AND appointment_type = 'Urgent Care'");
                $stmt->bind_param("i", $doctorData['doctor_id']);
                $stmt->execute();
                $stmt->bind_result($urgentCases);
                $stmt->fetch();
                $stmt->close();
            }
        } else {
            $error = "Doctor record not found.";
        }
    }
} catch (Exception $e) {
    $error = "Error fetching doctor data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack - Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="profile.css">
    <style>
        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        .day-availability {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            transition: all 0.3s ease;
        }
        .day-availability:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .day-availability.weekend {
            background-color: #f0f0f0;
        }
        .day {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }
        .place-name {
            color: #555;
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        .time {
            color: #2c7be5;
            margin-top: 5px;
            font-weight: 500;
        }
        
        /* Enhanced Availability Editor Styles */
        .availability-editor {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #eaeaea;
        }
        
        .availability-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .availability-instructions {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 20px;
        }
        
        .availability-day-group {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            background-color: white;
        }
        
        .day-label {
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
            color: #444;
        }
        
        .time-slot {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background-color: #f5f7fa;
            border-radius: 4px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .time-slot:last-child {
            margin-bottom: 0;
        }
        
        .time-slot-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .time-slot select, 
        .time-slot input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .time-slot select {
            min-width: 120px;
        }
        
        .time-input {
            width: 100px;
        }
        
        .place-input {
            flex-grow: 1;
            min-width: 200px;
        }
        
        .remove-slot {
            background: #ff6b6b;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .remove-slot:hover {
            background: #ff5252;
        }
        
        .add-slot-btn {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-slot-btn:hover {
            background: #3d8b40;
        }
        
        .add-slot-btn i {
            font-size: 0.8em;
        }
        
        /* Enhanced Form Styles */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95em;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }
        
        .avatar-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: #999;
            border: 2px dashed #ccc;
        }
        
        /* Modal Enhancements */
        .edit-profile-modal .modal-content {
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .time-slot {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .time-slot-controls {
                width: 100%;
                justify-content: space-between;
            }
            
            .place-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include('notifications.php'); ?>
    <div class="container">
        <!-- Side-Navigationbar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat me-2"></i> MediTrack
                </div>
                <p class="speciality">Your Trusted Medical Hub</p>
            </div>
            <nav class="nav-links">
                <a href="doctor_dashboard.php" class="nav-item ">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="Dr_Appointment.php" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fas fa-user-injured"></i> Patients
                </a>
                <a href="medical_records.php" class="nav-item">
                    <i class="fas fa-file-medical"></i> 
                    <span>Medical Records<br>& Prescription</span>
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user-md"></i> Profile
                </a>
                <a href="#" class="nav-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="content">
            <!-- Profile Section -->
            <section class="profile-section">
                <div class="profile-header">
                    <h2><i class="fas fa-user-md" style="color: #3498db;"></i> My Profile</h2>
                    <button class="edit-profile-btn" id="openEditModal"><i class="fas fa-edit"></i> Edit Profile</button>
                </div>

                <div class="profile-content">
                    <!-- Left Column - Personal Info -->
                    <div class="profile-personal">
                        

                        <div class="personal-info">
                            <h3 id="doctor-name">Dr. <?php echo htmlspecialchars($doctorData['full_name']); ?></h3>
                            <p class="specialization" id="doctor-speciality"><?php echo htmlspecialchars($doctorData['specialty']); ?></p>
                            <p class="license-number" id="doctor-license">License: <?php echo htmlspecialchars($doctorData['license_number'] ?? 'Not provided'); ?></p>

                            <div class="contact-info">
                                <p><i class="fas fa-envelope"></i> <span id="doctor-email"><?php echo htmlspecialchars($doctorData['email']); ?></span></p>
                                <p><i class="fas fa-phone"></i> <span id="doctor-phone">+961 <?php echo htmlspecialchars($doctorData['phone_number']); ?></span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Professional Details -->
                    <div class="profile-details">
                        <div class="detail-card">
                            <h4><i class="fas fa-graduation-cap"></i> Education</h4>
                            <ul id="education-list">
                                <?php 
                                if (!empty($doctorData['education'])) {
                                    $educationItems = explode("\n", $doctorData['education']);
                                    foreach ($educationItems as $item) {
                                        if (!empty(trim($item))) {
                                            echo '<li>' . htmlspecialchars(trim($item)) . '</li>';
                                        }
                                    }
                                } else {
                                    echo '<li>No education information provided</li>';
                                }
                                ?>
                            </ul>
                        </div>

                        <div class="detail-card">
                            <h4><i class="fas fa-certificate"></i> Certifications</h4>
                            <ul id="certifications-list">
                                <?php 
                                if (!empty($doctorData['certifications'])) {
                                    $certItems = explode("\n", $doctorData['certifications']);
                                    foreach ($certItems as $item) {
                                        if (!empty(trim($item))) {
                                            echo '<li>' . htmlspecialchars(trim($item)) . '</li>';
                                        }
                                    }
                                } else {
                                    echo '<li>No certifications provided</li>';
                                }
                                ?>
                            </ul>
                        </div>

                       <div class="detail-card">
                        <h4><i class="fas fa-star"></i> Professional Bio</h4>
                        <p id="doctor-bio"><?php echo !empty($doctorData['bio']) ? nl2br(htmlspecialchars($doctorData['bio'])) : 'No bio provided'; ?></p>
                    </div>
                    </div>
                </div>

                <!-- Availability Section -->
                <div class="availability-section">
                    <h3><i class="fas fa-calendar-alt"></i> Availability</h3>
                    <div class="availability-grid" id="availability-grid">
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $day) {
                            $dayEntries = array_filter($availabilityData, function($item) use ($day) {
                                return strtolower($item['day']) === strtolower($day);
                            });
                            
                            $isWeekend = in_array($day, ['Saturday', 'Sunday']);
                            $place_name = isset($entry['place_name']) ? $entry['place_name'] : 'Unknown Location';

                            if (empty($dayEntries)) {
                                echo '<div class="day-availability ' . ($isWeekend ? 'weekend' : '') . '">
                                    <span class="day">' . $day . '</span>
                                    <span class-"place_name">' . $place_name .'</span>
                                    <span class="time">Not Available</span>
                                </div>';
                            } else {
                                foreach ($dayEntries as $entry) {
                                    echo '<div class="day-availability ' . ($isWeekend ? 'weekend' : '') . '">
                                        <span class="day">' . $day . '</span>';
                                    
                                    if (!empty($entry['place_name'])) {
                                        echo '<span class="place-name">' . htmlspecialchars($entry['place_name']) . '</span>';
                                    }
                                    
                                    echo '<br> <span class="time">' . 
                                         htmlspecialchars($entry['start_time']) . ' - ' . 
                                         htmlspecialchars($entry['end_time']) . '</span>
                                    </div>';
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

<!-- Enhanced Edit Profile Modal -->
    <div class="edit-profile-modal" id="editProfileModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2 style="margin-bottom: 15px;"><i class="fas fa-user-edit"></i> Edit Profile</h2>

            <form id="profileForm" enctype="multipart/form-data">
                <input type="hidden" name="doctor_id" value="<?php echo $doctorData['doctor_id'] ?? ''; ?>">
                
                <!-- Personal Info Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="specialty">Speciality</label>
                        <select id="specialty" name="specialty" required>
                             <?php
        $specialities = ['Cardiologist', 'Dermatologist', 'Neurologist', 'Pediatrician', 'Surgeon', 'General Practitioner'];
        foreach ($specialities as $spec) {
            $selected = ($doctorData['specialty'] ?? '') === $spec ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($spec) . '" ' . $selected . '>' . htmlspecialchars($spec) . '</option>';
        }
        ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="license_number">License Number</label>
                        <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($doctorData['license_number'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($doctorData['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($doctorData['phone_number']); ?>" required pattern="[0-9]{8}" title="8-digit phone number">
                    </div>
                </div>

                <!-- <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($doctorData['address'] ?? ''); ?>" required>
                </div> -->

                <div class="form-group">
                    <label for="education">Education (One per line)</label>
                    <textarea id="education" name="education" placeholder="Example:&#10;MD in Cardiology - Harvard Medical School (2015)&#10;Residency - Massachusetts General Hospital (2012-2015)"><?php echo htmlspecialchars($doctorData['education'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="certifications">Certifications (One per line)</label>
                    <textarea id="certifications" name="certifications" placeholder="Example:&#10;Board Certified in Cardiology (2016)&#10;Advanced Cardiac Life Support (ACLS)"><?php echo htmlspecialchars($doctorData['certifications'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="bio">Professional Bio</label>
                    <textarea id="bio" name="bio" placeholder="Tell patients about your experience, approach to care, and any specialties..."><?php echo htmlspecialchars($doctorData['bio'] ?? ''); ?></textarea>
                </div>

                <!-- Enhanced Availability Editor -->
                <div class="availability-editor">
                    <div class="availability-header">
                        <h3><i class="fas fa-calendar-alt"></i> Weekly Availability</h3>
                    </div>
                    
                    <p class="availability-instructions">
                        Set your regular weekly schedule. Patients will see this when booking appointments.
                        Add multiple time slots for each day if you work at different locations or have breaks.
                    </p>
                    
                    <?php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days as $day) {
                        $dayEntries = array_filter($availabilityData, function($item) use ($day) {
                            return strtolower($item['day']) === strtolower($day);
                        });
                        
                        echo '<div class="availability-day-group" data-day="' . strtolower($day) . '">';
                        echo '<span class="day-label">' . $day . '</span>';
                        
                        if (empty($dayEntries)) {
                            // Default empty slot
                            echo '<div class="time-slot">
                                <select name="availability[' . $day . '][0][status]" class="status-select">
                                    <option value="available">Available</option>
                                    <option value="unavailable" selected>Unavailable</option>
                                </select>
                                <input type="time" name="availability[' . $day . '][0][start_time]" class="time-input" value="09:00">
                                <span>to</span>
                                <input type="time" name="availability[' . $day . '][0][end_time]" class="time-input" value="17:00">
                                <input type="text" class="place-input" name="availability[' . $day . '][0][place_name]" placeholder="Location (e.g., Main Clinic)">
                                <div class="time-slot-controls">
                                    <button type="button" class="add-slot-btn" onclick="addTimeSlot(this)">
                                        <i class="fas fa-plus"></i> Add Slot
                                    </button>
                                    <button type="button" class="remove-slot" onclick="removeTimeSlot(this)">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </div>';
                        } else {
                            foreach ($dayEntries as $index => $entry) {
                                echo '<div class="time-slot">
                                    <select name="availability[' . $day . '][' . $index . '][status]" class="status-select">
                                        <option value="available" ' . ($entry['status'] === 'available' ? 'selected' : '') . '>Available</option>
                                        <option value="unavailable" ' . ($entry['status'] === 'unavailable' ? 'selected' : '') . '>Unavailable</option>
                                    </select>
                                    <input type="time" name="availability[' . $day . '][' . $index . '][start_time]" class="time-input" value="' . substr($entry['start_time'], 0, 5) . '">
                                    <span>to</span>
                                    <input type="time" name="availability[' . $day . '][' . $index . '][end_time]" class="time-input" value="' . substr($entry['end_time'], 0, 5) . '">
                                    <input type="text" class="place-input" name="availability[' . $day . '][' . $index . '][place_name]" 
                                           value="' . htmlspecialchars($entry['place_name']) . '" placeholder="Location name">
                                    <div class="time-slot-controls">
                                        <button type="button" class="add-slot-btn" onclick="addTimeSlot(this)">
                                            <i class="fas fa-plus"></i> Add Slot
                                        </button>
                                        <button type="button" class="remove-slot" onclick="removeTimeSlot(this)">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                </div>';
                            }
                        }
                        
                        echo '</div>'; // Close day group
                    }
                    ?>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logout Overlay -->
    <div class="logout-overlay" id="logoutOverlay">
        <div class="logout-confirmation">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="logout-buttons">
                <button class="logout-btn confirm-logout" id="confirmLogout">Yes, Logout</button>
                <button class="logout-btn cancel-logout" id="cancelLogout">Cancel</button>
            </div>
        </div>
    </div>

    <script src="profile.js"></script>
</body>
</html>