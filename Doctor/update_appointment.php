<?php
session_start();
header('Content-Type: application/json');
require_once('../db-config/connection.php');

try {
    // Verify doctor is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access");
    }

    // Get action type
    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? null;

    if (!$appointmentId) {
        throw new Exception("Appointment ID is required");
    }

    // First verify the doctor owns this appointment
    $stmt = $conn->prepare("
        SELECT a.* 
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.appointment_id = ? AND d.user_id = ?
    ");
    $stmt->bind_param("ii", $appointmentId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found or unauthorized access");
    }

    // Handle different actions
    if ($action === 'update_time') {
        // Update appointment time
        $newTime = $_POST['appointment_time'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $updated_at = date('Y-m-d H:i:s');
        
        if (empty($newTime)) {
            throw new Exception("New time is required");
        }

        $updateStmt = $conn->prepare("
            UPDATE appointments 
            SET appointment_time = ?, 
                notes = ?,
                updated_at = ?
            WHERE appointment_id = ?
        ");
        $updateStmt->bind_param("sssi", $newTime, $notes, $updated_at, $appointmentId);
        $updateStmt->execute();

        echo json_encode([
            'success' => true, 
            'message' => 'Appointment time updated successfully'
        ]);

    } elseif ($action === 'cancel') {
        // Cancel appointment
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
?>