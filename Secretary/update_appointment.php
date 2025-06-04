<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Handle different actions
$action = $_POST['action'] ?? '';
$senderId = $_SESSION['user_id']; // The user performing the action

try {
    switch ($action) {
        case 'update_time':
            // Handle appointment time update
            if (!isset($_POST['appointment_id']) || !isset($_POST['appointment_time'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields for time update']);
                exit;
            }

            $appointmentId = $_POST['appointment_id'];
            $appointmentTime = $_POST['appointment_time'];
            $notes = $_POST['notes'] ?? '';

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Convert 24-hour time to 12-hour format for storage (if needed)
                $time12h = date("g:i A", strtotime($appointmentTime));

                // Update appointment
                $stmt = $conn->prepare("UPDATE appointments SET appointment_time = ?, notes = ? WHERE appointment_id = ?");
                $stmt->bind_param("ssi", $time12h, $notes, $appointmentId);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update appointment');
                }

                // Get patient user_id for notification
                $patientStmt = $conn->prepare("
                    SELECT p.user_id, u.full_name, a.appointment_date, a.appointment_time 
                    FROM patients p
                    JOIN users u ON p.user_id = u.user_id
                    JOIN appointments a ON a.patient_id = p.patient_id
                    WHERE a.appointment_id = ?
                ");
                $patientStmt->bind_param("i", $appointmentId);
                $patientStmt->execute();
                $patientResult = $patientStmt->get_result();
                
                if ($patientResult->num_rows === 0) {
                    throw new Exception('Patient not found');
                }
                
                $patientData = $patientResult->fetch_assoc();
                $receiverId = $patientData['user_id'];
                $patientName = $patientData['full_name'];
                $appointmentDate = date("F j, Y", strtotime($patientData['appointment_date']));
                $appointmentTime = $patientData['appointment_time'];

                // Create notification message
                $message = "Your appointment on $appointmentDate at $appointmentTime has been rescheduled by the secretary.";

                // Insert notification
                $notificationQuery = "INSERT INTO notifications 
                    (sender_id, receiver_id, message, type_notification, created_at, is_read, appointment_id) 
                    VALUES (?, ?, ?, 'rescheduling', NOW(), 0, ?)";
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param("iisi", $senderId, $receiverId, $message, $appointmentId);
                
                if (!$notificationStmt->execute()) {
                    throw new Exception("Failed to send notification");
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Appointment updated and notification sent']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'cancel':
            // Handle appointment cancellation
            if (!isset($_POST['appointment_id']) || !isset($_POST['cancelled_reason'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields for cancellation']);
                exit;
            }

            $appointmentId = $_POST['appointment_id'];
            $cancelledReason = $_POST['cancelled_reason'];
            $notes = $_POST['notes'] ?? '';
            $reasonForCancelling = $_POST['reason_for_cancelling'] ?? '';
            $cancelledBy = $_POST['cancelled_by'];

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Update appointment status
                $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled', cancelled_reason = ?, notes = ?, reason_for_cancelling = ?, cancelled_by = ?, updated_at = NOW() WHERE appointment_id = ?");
                $stmt->bind_param("sssii", $cancelledReason, $notes, $reasonForCancelling, $cancelledBy, $appointmentId);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to cancel appointment');
                }

                // Get patient and appointment details for notification
                $detailsStmt = $conn->prepare("
                    SELECT p.user_id, u.full_name, a.appointment_date, a.appointment_time 
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    JOIN users u ON p.user_id = u.user_id
                    WHERE a.appointment_id = ?
                ");
                $detailsStmt->bind_param("i", $appointmentId);
                $detailsStmt->execute();
                $detailsResult = $detailsStmt->get_result();
                
                if ($detailsResult->num_rows === 0) {
                    throw new Exception('Appointment details not found');
                }
                
                $appointmentData = $detailsResult->fetch_assoc();
                $receiverId = $appointmentData['user_id'];
                $patientName = $appointmentData['full_name'];
                $appointmentDate = date("F j, Y", strtotime($appointmentData['appointment_date']));
                $appointmentTime = $appointmentData['appointment_time'];

                // Create notification message
                $message = "Your appointment on $appointmentDate at $appointmentTime has been cancelled. Reason: $cancelledReason";

                // Insert notification
                $notificationQuery = "INSERT INTO notifications 
                    (sender_id, receiver_id, message, type_notification, created_at, is_read, appointment_id) 
                    VALUES (?, ?, ?, 'cancellation', NOW(), 0, ?)";
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param("iisi", $senderId, $receiverId, $message, $appointmentId);
                
                if (!$notificationStmt->execute()) {
                    throw new Exception("Failed to send notification");
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Appointment cancelled and notification sent']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'cancel_all':
            // Handle bulk cancellation
            if (!isset($_POST['date']) || !isset($_POST['reason']) || !isset($_POST['cancelled_by'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields for bulk cancellation']);
                exit;
            }

            $date = $_POST['date'];
            $reason = $_POST['reason'];
            $cancelledBy = $_POST['cancelled_by'];
            $notes = $_POST['notes'] ?? '';

            // Begin transaction
            $conn->begin_transaction();

            try {
                // Get all active appointments for the date with patient details
                $stmt = $conn->prepare("
                    SELECT a.appointment_id, p.user_id as patient_user_id, u.full_name, a.appointment_date, a.appointment_time 
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    JOIN users u ON p.user_id = u.user_id
                    WHERE DATE(a.appointment_date) = ? AND (a.status IS NULL OR a.status != 'Cancelled')
                ");
                $stmt->bind_param("s", $date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $appointments = [];
                $appointmentIds = [];
                while ($row = $result->fetch_assoc()) {
                    $appointments[] = $row;
                    $appointmentIds[] = $row['appointment_id'];
                }

                if (empty($appointmentIds)) {
                    echo json_encode(['success' => true, 'count' => 0, 'message' => 'No appointments to cancel']);
                    break;
                }

                // Update all appointments for the date
                $placeholders = str_repeat('?,', count($appointmentIds) - 1) . '?';
                $sql = "UPDATE appointments SET status = 'Cancelled', cancelled_reason = ?, notes = ?, cancelled_by = ?, updated_at = NOW() WHERE appointment_id IN ($placeholders)";
                
                $stmt = $conn->prepare($sql);
                $types = "ssi" . str_repeat('i', count($appointmentIds));
                $params = array_merge([$reason, $notes, $cancelledBy], $appointmentIds);
                
                $stmt->bind_param($types, ...$params);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to cancel appointments');
                }

                $affectedRows = $stmt->affected_rows;

                // Create notifications for each cancelled appointment
                foreach ($appointments as $appointment) {
                    $formattedDate = date("F j, Y", strtotime($appointment['appointment_date']));
                    $message = "Your appointment on $formattedDate at {$appointment['appointment_time']} has been cancelled. Reason: $reason";

                    $notificationQuery = "INSERT INTO notifications 
                        (sender_id, receiver_id, message, type_notification, created_at, is_read, appointment_id) 
                        VALUES (?, ?, ?, 'bulk_cancellation', NOW(), 0, ?)";
                    $notificationStmt = $conn->prepare($notificationQuery);
                    $notificationStmt->bind_param("iisi", $senderId, $appointment['patient_user_id'], $message, $appointment['appointment_id']);
                    
                    if (!$notificationStmt->execute()) {
                        throw new Exception("Failed to send notification for appointment {$appointment['appointment_id']}");
                    }
                }

                $conn->commit();
                echo json_encode([
                    'success' => true, 
                    'count' => $affectedRows,
                    'message' => "Cancelled $affectedRows appointments and sent notifications"
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>