<?php
session_start();
file_put_contents('debug_cancel.txt', print_r($_POST, true));


header('Content-Type: application/json');
require_once('../db-config/connection.php');


try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access");
    }

    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? null;

    if (!$appointmentId) {
        throw new Exception("Appointment ID is required");
    }

    // Step 1: Verify doctor owns the appointment and fetch details
    $stmt = $conn->prepare("
        SELECT a.*, d.doctor_id, u.full_name AS doctor_name, a.patient_id
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        JOIN users u ON d.user_id = u.user_id
        WHERE a.appointment_id = ? AND d.user_id = ?
    ");
    $stmt->bind_param("ii", $appointmentId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found or unauthorized access");
    }

    $appointment = $result->fetch_assoc();
    $doctorId = $appointment['doctor_id'];
    $doctorName = $appointment['doctor_name'];
    $patientId = $appointment['patient_id'];
    $appointmentDate = $appointment['appointment_date'];

    // Get patient's full name
    $patientQuery = $conn->prepare("SELECT u.full_name 
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.patient_id = ?");
    
    $patientQuery->bind_param("i", $patientId);
    $patientQuery->execute();
    $patientResult = $patientQuery->get_result();

    if ($patientResult->num_rows === 0) {
        throw new Exception("Patient not found");
    }

    $patientRow = $patientResult->fetch_assoc();
    $patientName = $patientRow['full_name'];

    // Handle actions
    if ($action === 'update_time') {
        $newTime = $_POST['appointment_time'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $updated_at = date('Y-m-d H:i:s');

        if (empty($newTime)) {
            throw new Exception("New time is required");
        }

        // Step 1.5: Check doctor's availability for the new time
        $appointmentDateTime = new DateTime("$appointmentDate $newTime");
        $dayOfWeek = $appointmentDateTime->format('l'); // e.g. "Monday"
        
        // Get doctor's schedule for that day
        $scheduleStmt = $conn->prepare("
            SELECT start_time, end_time 
            FROM work_place 
            WHERE doctor_id = ? AND day = ? AND status = 'available'
        ");
        $scheduleStmt->bind_param("is", $doctorId, $dayOfWeek);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();

        if ($scheduleResult->num_rows === 0) {
            throw new Exception("Doctor is not available on $dayOfWeek");
        }

        $schedule = $scheduleResult->fetch_assoc();
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];

        // Convert times to comparable formats
        $newTimeFormatted = $appointmentDateTime->format('H:i:s');
        $startTimeObj = new DateTime($startTime);
        $endTimeObj = new DateTime($endTime);
        $newTimeObj = new DateTime($newTimeFormatted);

        if ($newTimeObj < $startTimeObj || $newTimeObj > $endTimeObj) {
            $formattedStart = $startTimeObj->format('g:i A');
            $formattedEnd = $endTimeObj->format('g:i A');
            throw new Exception("Doctor is only available between $formattedStart and $formattedEnd on $dayOfWeek");
        }

        // Step 2: Check for time conflicts with other appointments
        $conflictStmt = $conn->prepare("
            SELECT appointment_id 
            FROM appointments 
            WHERE doctor_id = ? 
              AND appointment_date = ? 
              AND appointment_time = ? 
              AND appointment_id != ? 
              AND status != 'Cancelled'
        ");
        $conflictStmt->bind_param("issi", $doctorId, $appointmentDate, $newTime, $appointmentId);
        $conflictStmt->execute();
        
        if ($conflictStmt->get_result()->num_rows > 0) {
            throw new Exception("This time slot is already booked");
        }

        // Step 3: Update appointment
        $updateStmt = $conn->prepare("
            UPDATE appointments 
            SET appointment_time = ?, 
                notes = ?, 
                updated_at = ? 
            WHERE appointment_id = ?
        ");
        $updateStmt->bind_param("sssi", $newTime, $notes, $updated_at, $appointmentId);
        $updateStmt->execute();

        // Step 4: Update notification
        $notifMsg = "Dear $patientName, your appointment with Dr. $doctorName is rescheduled to $appointmentDate at $newTime.";
        $notifType = "update time of appointment";

        $notifStmt = $conn->prepare("
            UPDATE notifications 
            SET message = ?, type_notification = ?
            WHERE appointment_id = ?
        ");
        $notifStmt->bind_param("ssi", $notifMsg, $notifType, $appointmentId);
        $notifStmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Appointment time updated successfully'
        ]);

    } elseif ($action === 'cancel') {
        $reason = $_POST['cancel_reason'] ?? '';
        $otherReason = $_POST['other_reason'] ?? '';
        $cancelNotes = $_POST['cancel_notes'] ?? '';
        $updated_at = date('Y-m-d H:i:s');

        if (empty($reason)) {
            throw new Exception("Cancellation reason is required");
        }

        if ($reason === 'Other' && empty($otherReason)) {
            throw new Exception("Please specify the cancellation reason");
        }

        $finalReason = $reason === 'Other' ? $otherReason : $reason;
        $combinedNotes = "[CANCELLED] Reason: $finalReason";
        if (!empty($cancelNotes)) {
            $combinedNotes .= "\nAdditional Notes: $cancelNotes";
        }
        

        // Step 2: Cancel appointment
        $updateStmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'Cancelled', 
                notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                cancelled_reason = ?,
                cancelled_by = ?,
                updated_at = ?
            WHERE appointment_id = ?
        ");
        $updateStmt->bind_param("ssisi", $combinedNotes, $finalReason, $_SESSION['user_id'], $updated_at, $appointmentId);
        $updateStmt->execute();

        // Step 3: Update notification
        $notifMsg = "Dear $patientName, your appointment with Dr. $doctorName has been cancelled due to: $finalReason.";
        $notifType = "appointment cancellation";

        $notifStmt = $conn->prepare("
            UPDATE notifications 
            SET message = ?, type_notification = ?
            WHERE appointment_id = ?
        ");
        $notifStmt->bind_param("ssi", $notifMsg, $notifType, $appointmentId);
        $notifStmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ]);

    } else {
        throw new Exception("Invalid action specified");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}