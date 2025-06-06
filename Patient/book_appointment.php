<?php
session_start();
require_once '../db-config/connection.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to book an appointment']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get and validate inputs
$doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';
$purpose = trim($_POST['purpose'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$appointment_type = trim($_POST['appointment_type'] ?? 'Consultation');

// Check required fields
$missingFields = [];
if (!$doctorId) $missingFields[] = 'doctor_id';
if (!$date) $missingFields[] = 'date';
if (!$time) $missingFields[] = 'time';
if (!$purpose) $missingFields[] = 'purpose';
if (!$appointment_type) $missingFields[] = 'appointment_type';

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields', 'missing_fields' => $missingFields]);
    exit;
}

// Convert user_id to patient_id
$getPatientStmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$getPatientStmt->bind_param("i", $userId);
$getPatientStmt->execute();
$getPatientResult = $getPatientStmt->get_result();

if ($getPatientResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient profile not found']);
    exit;
}
$patientId = $getPatientResult->fetch_assoc()['patient_id'];

// Validate date/time
$timeObj = DateTime::createFromFormat('H:i', $time);
if (!$timeObj || !DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date or time format']);
    exit;
}
$timeFormatted = $timeObj->format('H:i:s');
$appointmentDateTime = new DateTime("$date $timeFormatted");
if ($appointmentDateTime <= new DateTime()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Appointment time must be in the future']);
    exit;
}
$dayName = $appointmentDateTime->format('l');

// Start transaction
$conn->begin_transaction();

try {
    // 1. Check doctor's availability
    $scheduleStmt = $conn->prepare("
        SELECT status, start_time, end_time 
        FROM work_place 
        WHERE doctor_id = ? AND day = ?
    ");
    $scheduleStmt->bind_param("is", $doctorId, $dayName);
    $scheduleStmt->execute();
    $scheduleResult = $scheduleStmt->get_result();

    if ($scheduleResult->num_rows === 0) {
        throw new Exception("Doctor is not available on $dayName");
    }

    $schedule = $scheduleResult->fetch_assoc();
    if (strtolower($schedule['status']) !== 'available') {
        throw new Exception("Doctor is not accepting appointments on $dayName");
    }

    if ($timeFormatted < $schedule['start_time'] || $timeFormatted > $schedule['end_time']) {
        throw new Exception("Please take an appointment between " .
            date("g:i A", strtotime($schedule['start_time'])) . " and " .
            date("g:i A", strtotime($schedule['end_time'])) . " on $dayName");
    }

    // 2. Check if doctor is already booked at that time
    $conflictDoctorStmt = $conn->prepare("
        SELECT appointment_id FROM appointments
        WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
        AND status NOT IN ('cancelled', 'completed')
    ");
    $conflictDoctorStmt->bind_param("iss", $doctorId, $date, $timeFormatted);
    $conflictDoctorStmt->execute();
    if ($conflictDoctorStmt->get_result()->num_rows > 0) {
        throw new Exception("This doctor is already booked at that time. Please choose another time.");
    }

    // 3. Check if patient has any appointment at that time with any doctor
    $conflictPatientStmt = $conn->prepare("
        SELECT appointment_id FROM appointments
        WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ?
        AND status NOT IN ('cancelled', 'completed')
    ");
    $conflictPatientStmt->bind_param("iss", $patientId, $date, $timeFormatted);
    $conflictPatientStmt->execute();
    if ($conflictPatientStmt->get_result()->num_rows > 0) {
        throw new Exception("You already have an appointment at that time with another doctor.");
    }

    // 4. Insert appointment
    $insertStmt = $conn->prepare("
        INSERT INTO appointments (
            patient_id, doctor_id, appointment_date, appointment_time,
            appointment_type, reason_for_visit, notes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
    ");
    $insertStmt->bind_param("iisssss", $patientId, $doctorId, $date, $timeFormatted, $appointment_type, $purpose, $notes);
    if (!$insertStmt->execute()) {
        throw new Exception("Failed to book appointment");
    }

    $appointmentId = $insertStmt->insert_id;

    // 5. Get doctor user_id
    $doctorUserStmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $doctorUserStmt->bind_param("i", $doctorId);
    $doctorUserStmt->execute();
    $doctorUserResult = $doctorUserStmt->get_result();
    $doctorUserId = $doctorUserResult->fetch_assoc()['user_id'] ?? null;

    if (!$doctorUserId) throw new Exception("Doctor not found");

    // 6. Get patient and doctor names
    $getNameStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $getNameStmt->bind_param("i", $userId);
    $getNameStmt->execute();
    $patientName = $getNameStmt->get_result()->fetch_assoc()['full_name'] ?? 'Patient';

    $getNameStmt->bind_param("i", $doctorUserId);
    $getNameStmt->execute();
    $doctorName = $getNameStmt->get_result()->fetch_assoc()['full_name'] ?? 'Doctor';

    // 7. Create notification
    $notifMessage = "Patient $patientName has just made an appointment on " .
        $appointmentDateTime->format("F j, Y") . " at " . $appointmentDateTime->format("g:i A");

    $notifStmt = $conn->prepare("
        INSERT INTO notifications (
            sender_id, receiver_id, appointment_id, message, type_notification, created_at
        ) VALUES (?, ?, ?, ?, 'set appointment', NOW())
    ");
    $notifStmt->bind_param("iiis", $userId, $doctorUserId, $appointmentId, $notifMessage);
    $notifStmt->execute();

    // 8. Link doctor and patient
    $linkStmt = $conn->prepare("
        INSERT IGNORE INTO doctorpatient (doctor_id, patient_id) VALUES (?, ?)
    ");
    $linkStmt->bind_param("ii", $doctorId, $patientId);
    $linkStmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment booked successfully',
        'appointment_id' => $appointmentId
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
