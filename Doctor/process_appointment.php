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

        // 5. Create notification (with proper user_id as sender)
        // First get the doctor's user information
        $doctorQuery = "SELECT u.user_id, u.full_name FROM doctors d JOIN users u ON d.user_id = u.user_id WHERE d.doctor_id = ?";
        $doctorStmt = $conn->prepare($doctorQuery);
        $doctorStmt->bind_param("i", $doctor_id);
        $doctorStmt->execute();
        $doctorResult = $doctorStmt->get_result();

        if ($doctorResult->num_rows > 0) {
            $doctor = $doctorResult->fetch_assoc();
            $message = "You have an appointment with Dr. {$doctor['full_name']} on $date at $time.";
            $notification_type = "new_appointment";

            $notificationQuery = "
                INSERT INTO notifications 
                (sender_id, receiver_id, appointment_id, message, type_notification, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ";
            $notificationStmt = $conn->prepare($notificationQuery);
            $notificationStmt->bind_param("iiiss", $doctor['user_id'], $patient_id, $appointment_id, $message, $notification_type);
            
            if (!$notificationStmt->execute()) {
                error_log("Notification failed: " . $notificationStmt->error);
                // Don't throw exception - appointment was created successfully
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