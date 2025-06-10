<?php
session_start();
include("../db-config/connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appointment'])) {
    try {
        // Validate required fields
        if (!isset($_POST['patient_id'], $_POST['doctor_id'], $_POST['date'], $_POST['time'], $_POST['type'])) {
            throw new Exception("All required fields must be filled.");
        }

        // Sanitize and validate input data
        $patient_id = (int)$_POST['patient_id'];
        $doctor_id = (int)$_POST['doctor_id'];
        $date = $_POST['date'];
        $raw_time = $_POST['time'];
        $type = $_POST['type'];
        $notes = $_POST['notes'] ?? '';
        $reason_for_visit = $_POST['purpose'] ?? '';

        // Validate date and time
        $datetime = strtotime($date . ' ' . $raw_time);
        if (!$datetime) {
            throw new Exception("Invalid date or time format.");
        }
        $time = date('H:i:s', $datetime);
        $dayName = date('l', $datetime); // Full day name (e.g. "Monday")

        // Start database transaction
        $conn->begin_transaction();

        // 1. Verify doctor's availability
        $scheduleQuery = "SELECT status, start_time, end_time FROM work_place WHERE doctor_id = ? AND day = ?";
        $scheduleStmt = $conn->prepare($scheduleQuery);
        $scheduleStmt->bind_param("is", $doctor_id, $dayName);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();

        if ($scheduleResult->num_rows === 0) {
            throw new Exception("Doctor is not available on $dayName.");
        }

        $schedule = $scheduleResult->fetch_assoc();
        if (strtolower($schedule['status']) !== 'available') {
            throw new Exception("Doctor is not accepting appointments on $dayName.");
        }

        // Check if time is within working hours
        if ($time < $schedule['start_time'] || $time > $schedule['end_time']) {
            throw new Exception("Appointment time must be between {$schedule['start_time']} and {$schedule['end_time']}.");
        }

        // 2. Check for existing appointment
        $conflictQuery = "SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?";
        $conflictStmt = $conn->prepare($conflictQuery);
        $conflictStmt->bind_param("iss", $doctor_id, $date, $time);
        $conflictStmt->execute();
        $conflictStmt->store_result();

        if ($conflictStmt->num_rows > 0) {
            throw new Exception("This time slot is already booked.");
        }

        // 3. Create the appointment
        $appointmentQuery = "
            INSERT INTO appointments 
            (patient_id, doctor_id, appointment_date, appointment_time, appointment_type, notes, reason_for_visit) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $appointmentStmt = $conn->prepare($appointmentQuery);
        $appointmentStmt->bind_param("iisssss", $patient_id, $doctor_id, $date, $time, $type, $notes, $reason_for_visit);
        
        if (!$appointmentStmt->execute()) {
            throw new Exception("Failed to create appointment: " . $appointmentStmt->error);
        }

        $appointment_id = $appointmentStmt->insert_id;

        // 4. Establish doctor-patient relationship if not exists
        $relationshipQuery = "INSERT IGNORE INTO doctorpatient (doctor_id, patient_id) VALUES (?, ?)";
        $relationshipStmt = $conn->prepare($relationshipQuery);
        $relationshipStmt->bind_param("ii", $doctor_id, $patient_id);
        $relationshipStmt->execute();

        // 5. Create notification (sender = logged-in doctor, receiver = patient's user_id)
$sender_id = $_SESSION['user_id']; // Doctor's user_id from session

// Get doctor's full name (optional, for message)
$doctorNameQuery = "SELECT full_name FROM users WHERE user_id = ?";
$doctorNameStmt = $conn->prepare($doctorNameQuery);
$doctorNameStmt->bind_param("i", $sender_id);
$doctorNameStmt->execute();
$doctorNameResult = $doctorNameStmt->get_result();
$doctorName = ($doctorNameResult->num_rows > 0) ? $doctorNameResult->fetch_assoc()['full_name'] : 'your doctor';

// Get receiver user_id from patient_id
$patientUserQuery = "SELECT user_id FROM patients WHERE patient_id = ?";
$patientUserStmt = $conn->prepare($patientUserQuery);
$patientUserStmt->bind_param("i", $patient_id);
$patientUserStmt->execute();
$patientUserResult = $patientUserStmt->get_result();

if ($patientUserResult->num_rows > 0) {
    $receiver_id = $patientUserResult->fetch_assoc()['user_id'];
    $message = "You have an appointment with Dr. {$doctorName} on $date at $time.";
    $notification_type = "new_appointment";

    $notificationQuery = "
        INSERT INTO notifications 
        (sender_id, receiver_id, appointment_id, message, type_notification, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("iiiss", $sender_id, $receiver_id, $appointment_id, $message, $notification_type);

    if (!$notificationStmt->execute()) {
        error_log("Notification failed: " . $notificationStmt->error);
        // Silent fail, log only
    }
}
        // Commit transaction if all operations succeeded
        $conn->commit();

        // Redirect with success message
        header("Location: Dr_Appointment.php?success=Appointment booked successfully!");
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        header("Location: Dr_Appointment.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: Dr_Appointment.php?error=Invalid request");
    exit();
}
?>