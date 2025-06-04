<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all errors to a file
ini_set('log_errors', 1);
ini_set('error_log', '../logs/cancel_appointments_errors.log');

header('Content-Type: application/json');
require_once('../db-config/connection.php');

// Function to log debug information
function debugLog($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . print_r($data, true);
    }
    error_log($logMessage);
    return $logMessage;
}

try {
    debugLog("=== CANCEL APPOINTMENTS REQUEST STARTED ===");
    
    // Check if session exists
    debugLog("Session data", $_SESSION);
    
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        debugLog("ERROR: User not logged in");
        throw new Exception("Unauthorized access - user not logged in");
    }
    
    debugLog("User ID from session", $_SESSION['user_id']);

    // Get and log raw input
    $rawInput = file_get_contents('php://input');
    debugLog("Raw input received", $rawInput);
    
    if (empty($rawInput)) {
        debugLog("ERROR: No input data received");
        throw new Exception("No input data received");
    }

    // Get input data
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("ERROR: JSON decode failed", json_last_error_msg());
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    debugLog("Decoded input data", $input);

    $doctor_id = $input['doctor_id'] ?? null;
    $date = $input['date'] ?? null;
    $reason = $input['reason'] ?? null;
    $other_reason = $input['other_reason'] ?? null;

    debugLog("Extracted parameters", [
        'doctor_id' => $doctor_id,
        'date' => $date,
        'reason' => $reason,
        'other_reason' => $other_reason
    ]);

    if (!$doctor_id || !$date) {
        debugLog("ERROR: Missing required parameters");
        throw new Exception("Doctor ID and date are required");
    }

    // Validate cancellation reason
    if (empty($reason)) {
        debugLog("ERROR: Missing cancellation reason");
        throw new Exception("Cancellation reason is required");
    }

    if ($reason === 'Other' && empty($other_reason)) {
        debugLog("ERROR: Other reason not specified");
        throw new Exception("Please specify the cancellation reason");
    }

    // Determine final reason text
    $final_reason = ($reason === 'Other') ? $other_reason : $reason;
    debugLog("Final reason determined", $final_reason);

    // Check database connection
    if (!$conn) {
        debugLog("ERROR: Database connection failed");
        throw new Exception("Database connection failed");
    }
    debugLog("Database connection successful");

    // Verify the secretary is assigned to this doctor
    debugLog("Checking secretary assignment for user_id: " . $_SESSION['user_id'] . " and doctor_id: " . $doctor_id);
    
    $stmt = $conn->prepare("SELECT secretary_id FROM secretary WHERE user_id = ? AND doctor_id = ?");
    if (!$stmt) {
        debugLog("ERROR: Failed to prepare secretary check query", $conn->error);
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $_SESSION['user_id'], $doctor_id);
    
    if (!$stmt->execute()) {
        debugLog("ERROR: Failed to execute secretary check query", $stmt->error);
        throw new Exception("Failed to verify secretary assignment: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    debugLog("Secretary check query executed, rows found: " . $result->num_rows);
    
    $secretaryInfo = $result->fetch_assoc();
    $stmt->close();

    debugLog("Secretary info retrieved", $secretaryInfo);

    if (!$secretaryInfo) {
        debugLog("ERROR: Secretary not assigned to doctor");
        throw new Exception("Unauthorized action - not assigned to this doctor");
    }

    $secretary_id = $secretaryInfo['secretary_id'];
    $updated_at = date('Y-m-d H:i:s');
    $status = 'Cancelled'; // Must match enum value in your table

    debugLog("Secretary ID found", $secretary_id);
    debugLog("Updated timestamp", $updated_at);

    // Prepare cancellation note
    $cancellation_note = "[BULK CANCELLATION] Reason: " . $final_reason;
    $cancellation_note .= "\nCancelled by secretary ID: " . $secretary_id;
    $cancellation_note .= "\nCancelled at: " . $updated_at;
    
    debugLog("Cancellation note prepared", $cancellation_note);

    // First, let's check what appointments exist for this date and doctor
    debugLog("Checking existing appointments for doctor_id: $doctor_id and date: $date");
    
    $checkStmt = $conn->prepare("
        SELECT appointment_id, patient_id, appointment_date, status, purpose, notes 
        FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_date) = ?
    ");
    
    if (!$checkStmt) {
        debugLog("ERROR: Failed to prepare check appointments query", $conn->error);
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("is", $doctor_id, $date);
    
    if (!$checkStmt->execute()) {
        debugLog("ERROR: Failed to execute check appointments query", $checkStmt->error);
        throw new Exception("Failed to check existing appointments: " . $checkStmt->error);
    }
    
    $checkResult = $checkStmt->get_result();
    $existingAppointments = [];
    
    while ($row = $checkResult->fetch_assoc()) {
        $existingAppointments[] = $row;
    }
    $checkStmt->close();
    
    debugLog("Found " . count($existingAppointments) . " total appointments", $existingAppointments);
    
    // Check specifically for Scheduled or Confirmed appointments
    $eligibleAppointments = array_filter($existingAppointments, function($apt) {
        return in_array($apt['status'], ['Scheduled', 'Confirmed']);
    });
    
    debugLog("Found " . count($eligibleAppointments) . " eligible appointments (Scheduled/Confirmed)", $eligibleAppointments);

    if (empty($existingAppointments)) {
        debugLog("ERROR: No appointments found for the specified date and doctor");
        throw new Exception("No appointments found for the specified date");
    }
    
    if (empty($eligibleAppointments)) {
        debugLog("ERROR: No eligible appointments to cancel");
        throw new Exception("No appointments found to cancel (only Scheduled or Confirmed appointments can be cancelled)");
    }

    // Update all appointments for this date to cancelled status
    debugLog("Preparing to update appointments");
    
    $updateQuery = "
        UPDATE appointments 
        SET status = ?,
            notes = CONCAT(IFNULL(notes, ''), '\n', ?),
            cancelled_reason = ?,
            cancelled_by = ?,
            updated_at = ?
        WHERE doctor_id = ? 
        AND DATE(appointment_date) = ? 
        AND status IN ('Scheduled', 'Confirmed')
    ";
    
    debugLog("Update query", $updateQuery);
    
    $stmt = $conn->prepare($updateQuery);
    
    if (!$stmt) {
        debugLog("ERROR: Failed to prepare update query", $conn->error);
        throw new Exception("Database update preparation failed: " . $conn->error);
    }
    
    debugLog("Binding parameters", [
        'status' => $status,
        'cancellation_note' => $cancellation_note,
        'final_reason' => $final_reason,
        'secretary_id' => $secretary_id,
        'updated_at' => $updated_at,
        'doctor_id' => $doctor_id,
        'date' => $date
    ]);
    
    $stmt->bind_param(
        "ssssis", 
        $status, 
        $cancellation_note, 
        $final_reason, 
        $secretary_id,
        $updated_at, 
        $doctor_id, 
        $date
    );
    
    if (!$stmt->execute()) {
        debugLog("ERROR: Failed to execute update query", $stmt->error);
        throw new Exception("Failed to update appointments: " . $stmt->error);
    }
    
    $affectedRows = $stmt->affected_rows;
    debugLog("Update executed successfully, affected rows: " . $affectedRows);
    
    $stmt->close();

    if ($affectedRows === 0) {
        debugLog("ERROR: No rows were affected by the update");
        
        // Let's check the appointment statuses again after the failed update
        $recheckStmt = $conn->prepare("
            SELECT appointment_id, status 
            FROM appointments 
            WHERE doctor_id = ? AND DATE(appointment_date) = ?
        ");
        $recheckStmt->bind_param("is", $doctor_id, $date);
        $recheckStmt->execute();
        $recheckResult = $recheckStmt->get_result();
        $afterUpdateStatuses = [];
        while ($row = $recheckResult->fetch_assoc()) {
            $afterUpdateStatuses[] = $row;
        }
        $recheckStmt->close();
        
        debugLog("Appointment statuses after failed update", $afterUpdateStatuses);
        
        throw new Exception("No appointments were updated. This might be due to status constraints or appointments already being cancelled.");
    }

    // Verify the update was successful
    $verifyStmt = $conn->prepare("
        SELECT appointment_id, status, cancelled_reason 
        FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status = 'Cancelled'
    ");
    $verifyStmt->bind_param("is", $doctor_id, $date);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $cancelledAppointments = [];
    while ($row = $verifyResult->fetch_assoc()) {
        $cancelledAppointments[] = $row;
    }
    $verifyStmt->close();
    
    debugLog("Verification: Found " . count($cancelledAppointments) . " cancelled appointments", $cancelledAppointments);

    $successResponse = [
        'success' => true,
        'message' => "Successfully cancelled $affectedRows appointments",
        'cancelled_count' => $affectedRows,
        'cancelled_by' => $secretary_id,
        'reason' => $final_reason,
        'debug_info' => [
            'total_appointments_found' => count($existingAppointments),
            'eligible_appointments' => count($eligibleAppointments),
            'actually_cancelled' => count($cancelledAppointments)
        ]
    ];
    
    debugLog("SUCCESS: Sending response", $successResponse);
    echo json_encode($successResponse);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    debugLog("EXCEPTION CAUGHT: " . $errorMessage);
    
    http_response_code(400);
    $errorResponse = [
        'success' => false,
        'message' => $errorMessage,
        'debug_info' => [
            'session_user_id' => $_SESSION['user_id'] ?? 'NOT SET',
            'raw_input' => $rawInput ?? 'NOT SET',
            'parsed_input' => $input ?? 'NOT SET'
        ]
    ];
    
    debugLog("ERROR RESPONSE", $errorResponse);
    echo json_encode($errorResponse);
} catch (Error $e) {
    $errorMessage = "Fatal error: " . $e->getMessage();
    debugLog("FATAL ERROR: " . $errorMessage);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

debugLog("=== CANCEL APPOINTMENTS REQUEST ENDED ===");
?>