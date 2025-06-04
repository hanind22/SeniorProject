<?php
session_start();
header('Content-Type: application/json');
require_once('../db-config/connection.php');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access");
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $appointmentId = $input['appointment_id'] ?? null;

    if (!$appointmentId) {
        throw new Exception("Appointment ID is required");
    }

    // Verify patient owns the appointment and fetch details
    $stmt = $conn->prepare("
        SELECT a.*, d.doctor_id, u.full_name AS doctor_name, p.user_id as patient_user_id
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        JOIN users u ON d.user_id = u.user_id
        JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.appointment_id = ? AND p.user_id = ? AND a.status != 'Cancelled'
    ");
    $stmt->bind_param("ii", $appointmentId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found, unauthorized access, or already cancelled");
    }

    $appointment = $result->fetch_assoc();
    $doctorName = $appointment['doctor_name'];
    $appointmentDate = $appointment['appointment_date'];
    $appointmentTime = $appointment['appointment_time'];
    $updated_at = date('Y-m-d H:i:s');

    // Get patient's full name
    $patientQuery = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $patientQuery->bind_param("i", $_SESSION['user_id']);
    $patientQuery->execute();
    $patientResult = $patientQuery->get_result();
    $patientData = $patientResult->fetch_assoc();
    $patientName = $patientData['full_name'];

    // Cancel appointment
    $cancelReason = "Cancelled by patient";
    $cancelNotes = "[CANCELLED BY PATIENT] Appointment cancelled by patient request.";

    $updateStmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'Cancelled', 
            notes = CONCAT(IFNULL(notes, ''), '\n', ?),
            cancelled_reason = ?,
            cancelled_by = ?,
            updated_at = ?
        WHERE appointment_id = ?
    ");
    $updateStmt->bind_param("ssisi", $cancelNotes, $cancelReason, $_SESSION['user_id'], $updated_at, $appointmentId);
    $updateStmt->execute();

    // Update or create notification for doctor
    $notifMsg = "Patient $patientName has cancelled their appointment scheduled for $appointmentDate at $appointmentTime.";
    $notifType = "appointment cancelled by patient";

    // Check if notification exists
    $checkNotif = $conn->prepare("SELECT notification_id FROM notifications WHERE appointment_id = ?");
    $checkNotif->bind_param("i", $appointmentId);
    $checkNotif->execute();
    $notifExists = $checkNotif->get_result();

    if ($notifExists->num_rows > 0) {
        // Update existing notification
        $notifStmt = $conn->prepare("
            UPDATE notifications 
            SET message = ?, type_notification = ?, created_at = NOW()
            WHERE appointment_id = ?
        ");
        $notifStmt->bind_param("ssi", $notifMsg, $notifType, $appointmentId);
    } else {
        // Create new notification
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (appointment_id, message, type_notification, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $notifStmt->bind_param("iss", $appointmentId, $notifMsg, $notifType);
    }
    $notifStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>