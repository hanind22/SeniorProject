<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create debug log function
function debug_log($message) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
debug_log("Script started");

// Log raw POST data
debug_log("Raw POST data: " . print_r($_POST, true));

// Use absolute path or proper relative path
require_once '../db-config/connection.php';
debug_log("Database connection file included");

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Start session if you need patient_id from session
session_start();
debug_log("Session started. Session data: " . print_r($_SESSION, true));

// Get patient_id from session instead of POST for security
$patientId = $_SESSION['user_id'] ?? 0;
if (!$patientId) {
    debug_log("No patient ID in session");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to book an appointment']);
    exit;
}

// Get and validate all input fields with debugging
$doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';
$purpose = trim($_POST['purpose'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$appointment_type = trim($_POST['appointment_type'] ?? 'Consultation');

// Validate inputs
$missingFields = [];
if (!$patientId) $missingFields[] = 'patient_id';
if (!$doctorId) $missingFields[] = 'doctor_id';
if (!$date) $missingFields[] = 'date';
if (!$time) $missingFields[] = 'time';
if (!$purpose) $missingFields[] = 'purpose';
if (!$appointment_type) $missingFields[] = 'appointment_type';

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fill all required fields',
        'missing_fields' => $missingFields
    ]);
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Please use YYYY-MM-DD format']);
    exit;
}

try {
    // Format time properly and validate
    $timeObj = DateTime::createFromFormat('H:i', $time);
    if (!$timeObj) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid time format. Please use HH:MM format (e.g., 14:30)']);
        exit;
    }
    $timeFormatted = $timeObj->format('H:i:s');

    // Check if the selected time is in the future
    $appointmentDateTime = new DateTime("$date $timeFormatted");
    $currentDateTime = new DateTime();
    
    if ($appointmentDateTime <= $currentDateTime) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Appointment time must be in the future']);
        exit;
    }

    // Get day name (Monday, Tuesday, etc.)
    $dayName = $appointmentDateTime->format('l');

    // Begin transaction
    $conn->begin_transaction();

    try {
        // 1. Check doctor's work schedule
        $scheduleStmt = $conn->prepare("
            SELECT status, start_time, end_time 
            FROM work_place 
            WHERE doctor_id = ? AND day = ?
        ");
        $scheduleStmt->bind_param("is", $doctorId, $dayName);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();

        if ($scheduleResult->num_rows === 0) {
            // Get doctor's available days and working hours for better error message
            $daysStmt = $conn->prepare("
                SELECT day, start_time, end_time FROM work_place 
                WHERE doctor_id = ? AND status = 'available'
                ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
            ");
            $daysStmt->bind_param("i", $doctorId);
            $daysStmt->execute();
            $daysResult = $daysStmt->get_result();
            
            $availableDays = [];
            while ($row = $daysResult->fetch_assoc()) {
                $startTime = date("g:i A", strtotime($row['start_time']));
                $endTime = date("g:i A", strtotime($row['end_time']));
                $availableDays[] = $row['day'] . " ($startTime - $endTime)";
            }
            
            if (count($availableDays) > 0) {
                $availableDaysStr = implode(', ', $availableDays);
                throw new Exception("Doctor is not available on $dayName. Please book an appointment on these available days: $availableDaysStr");
            } else {
                throw new Exception("This doctor has no available working days currently");
            }
        }

        $schedule = $scheduleResult->fetch_assoc();
        
        if (strtolower($schedule['status']) !== 'available') {
            throw new Exception("Doctor is not available on $dayName. Status: " . ucfirst($schedule['status']));
        }

        // Validate working hours
        $start_time = $schedule['start_time'];
        $end_time = $schedule['end_time'];
        $formattedStart = date("g:i A", strtotime($start_time));
        $formattedEnd = date("g:i A", strtotime($end_time));
        
        if ($timeFormatted < $start_time) {
            throw new Exception("Please take an appointment between $formattedStart and $formattedEnd on $dayName");
        }
        
        if ($timeFormatted > $end_time) {
            throw new Exception("Please take an appointment between $formattedStart and $formattedEnd on $dayName");
        }

        // 2. Check for existing appointment
        $checkStmt = $conn->prepare("
            SELECT appointment_id FROM appointments
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
            AND status NOT IN ('cancelled', 'completed')
        ");
        $checkStmt->bind_param("iss", $doctorId, $date, $timeFormatted);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            throw new Exception("This time slot is already booked. Please choose another time");
        }

        // 3. Insert the appointment
        $insertStmt = $conn->prepare("
            INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, appointment_time,
                appointment_type, reason_for_visit, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
        ");
        $insertStmt->bind_param(
            "iisssss", 
            $patientId, $doctorId, $date, $timeFormatted, 
            $appointment_type, $purpose, $notes
        );

        if (!$insertStmt->execute()) {
            throw new Exception("Failed to book appointment. Please try again");
        }

        // Get the appointment ID that was just inserted
        $appointmentId = $insertStmt->insert_id;

        // 4. Get patient details for notification message
        $patientQuery = $conn->prepare("
            SELECT full_name FROM users WHERE user_id = ?
        ");
        $patientQuery->bind_param("i", $patientId);
        $patientQuery->execute();
        $patientResult = $patientQuery->get_result();
        $patientName = $patientResult->fetch_assoc()['full_name'] ?? 'Patient';

        // 5. Get doctor user_id for notification and doctor name
        $doctorUserIdStmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
        $doctorUserIdStmt->bind_param("i", $doctorId);
        $doctorUserIdStmt->execute();
        $doctorUserIdResult = $doctorUserIdStmt->get_result();

        if ($doctorUserIdResult->num_rows === 0) {
            throw new Exception("Doctor not found");
        }

        $doctorUserId = $doctorUserIdResult->fetch_assoc()['user_id'];

        // Get doctor full name from users table
        $doctorNameStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $doctorNameStmt->bind_param("i", $doctorUserId);
        $doctorNameStmt->execute();
        $doctorNameResult = $doctorNameStmt->get_result();

        $doctorName = 'Dr. ' . ($doctorNameResult->fetch_assoc()['full_name'] ?? 'the doctor');

        // Format date and time for notification message
        $formattedDate = $appointmentDateTime->format("F j, Y");
        $formattedTime = $timeObj->format("g:i A");

        // Create notification message
        $notificationMessage = "Patient $patientName has just made an appointment on $formattedDate at $formattedTime";

        // 6. Insert notification for the doctor
        $notificationStmt = $conn->prepare("
            INSERT INTO notifications (
                sender_id, receiver_id, appointment_id, message, type_notification, created_at
            ) VALUES (?, ?, ?, ?, 'set appointment', NOW())
        ");
        $notificationStmt->bind_param("iiis", $patientId, $doctorUserId, $appointmentId, $notificationMessage);
        
        if (!$notificationStmt->execute()) {
            throw new Exception("Appointment was booked but failed to send notification to doctor");
        }

        // 7. Link doctor and patient if not already linked
        $linkStmt = $conn->prepare("
            INSERT IGNORE INTO doctorpatient (doctor_id, patient_id)
            VALUES (?, ?)
        ");
        $linkStmt->bind_param("ii", $doctorId, $patientId);
        $linkStmt->execute(); // It's okay if it fails silently here

        // Commit transaction
        $conn->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Appointment booked successfully',
            'appointment_id' => $appointmentId
        ]);
        debug_log("Appointment booked successfully for patient $patientId with doctor $doctorId");

    } catch (Exception $e) {
        $conn->rollback();
        debug_log("Error in booking appointment: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} catch (Exception $e) {
    debug_log("Unexpected error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

$conn->close();
debug_log("Script ended");
?>