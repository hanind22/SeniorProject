<?php
session_start();
header('Content-Type: application/json');
require_once('../db-config/connection.php');

error_log("=== DEBUGGING APPOINTMENT UPDATE ===");
error_log("Session user_id (patient): " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Received POST data: " . print_r($_POST, true));

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access - no session user_id");
    }

    $action = $_POST['action'] ?? '';
    $appointmentId = $_POST['appointment_id'] ?? null;

    if (!$appointmentId) {
        throw new Exception("Appointment ID is required");
    }

    // Verify appointment ownership and get required info
    $stmt = $conn->prepare("
        SELECT a.*, 
               d.user_id AS doctor_user_id, 
               u_d.full_name AS doctor_name,
               p.patient_id, 
               p.user_id AS patient_user_id
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN users u_d ON d.user_id = u_d.user_id
        WHERE a.appointment_id = ? 
        AND p.user_id = ?
    ");
    $stmt->bind_param("ii", $appointmentId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found or unauthorized access");
    }

    $appointment = $result->fetch_assoc();

    if ($action === 'update_time') {
        $newTime = $_POST['appointment_time'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $updated_at = date('Y-m-d H:i:s');

        if (empty($newTime)) {
            throw new Exception("New time is required");
        }

        $date = $appointment['appointment_date'];
        $doctor_id = $appointment['doctor_id'];
        $doctor_user_id = $appointment['doctor_user_id'];
        $patient_user_id = $appointment['patient_user_id'];
        $dayName = date('l', strtotime($date));
        $parsed_time = date('H:i:s', strtotime($newTime));

        // Start transaction
        $conn->begin_transaction();

        try {
            // 1. Check doctor's availability on that day
            $scheduleQuery = "SELECT status, start_time, end_time FROM work_place WHERE doctor_id = ? AND day = ?";
            $scheduleStmt = $conn->prepare($scheduleQuery);
            $scheduleStmt->bind_param("is", $doctor_id, $dayName);
            $scheduleStmt->execute();
            $scheduleResult = $scheduleStmt->get_result();

            if ($scheduleResult->num_rows === 0) {
                throw new Exception("Doctor does not work on $dayName.");
            }

            $schedule = $scheduleResult->fetch_assoc();
            if (strtolower($schedule['status']) !== 'available') {
                throw new Exception("Doctor is unavailable on $dayName.");
            }

            if ($parsed_time < $schedule['start_time'] || $parsed_time > $schedule['end_time']) {
                throw new Exception("Please place an appointment between {$schedule['start_time']} and {$schedule['end_time']}.");
            }

            // 2. Check for conflict with other appointments
            $conflictQuery = "SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND appointment_id != ?";
            $conflictStmt = $conn->prepare($conflictQuery);
            $conflictStmt->bind_param("issi", $doctor_id, $date, $parsed_time, $appointmentId);
            $conflictStmt->execute();
            $conflictStmt->store_result();

            if ($conflictStmt->num_rows > 0) {
                throw new Exception("Time slot already taken by another appointment.");
            }

            // 3. Update appointment
            $updateStmt = $conn->prepare("
                UPDATE appointments 
                SET appointment_time = ?, 
                    notes = ?, 
                    updated_at = ? 
                WHERE appointment_id = ?
            ");
            $updateStmt->bind_param("sssi", $parsed_time, $notes, $updated_at, $appointmentId);
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update appointment");
            }

            // 4. Send notification to doctor
            $formattedDate = date('F j, Y', strtotime($date));
            $formattedTime = date('g:i A', strtotime($parsed_time));
            $doctorName = "Dr. " . $appointment['doctor_name'];
            $message = "Your appointment with $doctorName has been rescheduled to $formattedDate at $formattedTime.";
error_log("Patient user_id: $patient_user_id");
error_log("Doctor user_id: $doctor_user_id");

            $notificationQuery = "INSERT INTO notifications 
                (sender_id, receiver_id, message, type_notification, created_at, is_read, appointment_id) 
                VALUES (?, ?, ?, 'rescheduling', NOW(), 0, ?)";
            $notificationStmt = $conn->prepare($notificationQuery);
            $notificationStmt->bind_param("iisi", $patient_user_id, $doctor_user_id, $message, $appointmentId);
            if (!$notificationStmt->execute()) {
                throw new Exception("Failed to send notification");
            }

            // Commit
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Appointment time updated successfully'
            ]);
        } catch (Exception $innerEx) {
            $conn->rollback();
            throw $innerEx;
        }
    } else {
        throw new Exception("Invalid action specified: " . $action);
    }
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
