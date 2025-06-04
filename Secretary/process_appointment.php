<?php
session_start();
include("../db-config/connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appointment'])) {
    try {
        // Validate required fields
        if (!isset($_POST['patient_id'], $_POST['doctor_id'], $_POST['date'], $_POST['time'], $_POST['type'])) {
            throw new Exception("Missing required fields.");
        }

        // Sanitize and validate input
        $patient_id = filter_var($_POST['patient_id'], FILTER_VALIDATE_INT);
        $doctor_id = filter_var($_POST['doctor_id'], FILTER_VALIDATE_INT);
        $date = $_POST['date']; // e.g. 2025-06-09
        $raw_time = $_POST['time']; // e.g. 09:30
        $type = htmlspecialchars($_POST['type']);
        $notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '';
        $reason_for_visit = isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : '';
        
        // Validate session user
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Session expired. Please login again.");
        }
        $secretary_id = $_SESSION['user_id'];

        // Parse date and time
        $datetime = strtotime($date . ' ' . $raw_time);
        if (!$datetime) {
            throw new Exception("Invalid datetime format: $date $raw_time");
        }
        $time = date('H:i:s', $datetime);
        $dayName = date('l', strtotime($date)); // 'Monday', 'Tuesday', etc.

        // Start transaction
        $conn->begin_transaction();

        try {
            // 1. Check doctor's work schedule
            $scheduleQuery = "SELECT status, start_time, end_time FROM work_place WHERE doctor_id = ? AND day = ?";
            $scheduleStmt = $conn->prepare($scheduleQuery);
            $scheduleStmt->bind_param("is", $doctor_id, $dayName);
            $scheduleStmt->execute();
            $result = $scheduleStmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Doctor does not work on $dayName.");
            }

            $schedule = $result->fetch_assoc();
            if (strtolower($schedule['status']) !== 'available') {
                throw new Exception("Doctor is unavailable on $dayName.");
            }

            // Validate working hours
            $start_time = $schedule['start_time'];
            $end_time = $schedule['end_time'];
            if ($time < $start_time || $time > $end_time) {
                throw new Exception("Please place an appointment between $start_time and $end_time.");
            }

            // 2. Check for appointment conflict
            $checkQuery = "SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("iss", $doctor_id, $date, $time);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                throw new Exception("This appointment can't be set at that time because there is already an appointment scheduled.");
            }

            // 3. Insert appointment
            $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, appointment_type, notes, reason_for_visit) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iisssss", $patient_id, $doctor_id, $date, $time, $type, $notes, $reason_for_visit);
            if (!$stmt->execute()) {
                throw new Exception("Error saving appointment: " . $stmt->error);
            }

            // Get the inserted appointment_id
            $appointment_id = $stmt->insert_id;

            // 4. Get patient's user_id from patients table
            $patientUserQuery = "SELECT user_id FROM patients WHERE patient_id = ?";
            $patientUserStmt = $conn->prepare($patientUserQuery);
            $patientUserStmt->bind_param("i", $patient_id);
            $patientUserStmt->execute();
            $patientUserResult = $patientUserStmt->get_result();

            if ($patientUserResult->num_rows === 0) {
                throw new Exception("Patient record not found.");
            }
            $patientUser = $patientUserResult->fetch_assoc();
            $patient_user_id = $patientUser['user_id'];

            // 5. Get doctor's name for notification
            $doctorQuery = "SELECT full_name FROM users WHERE user_id = ?";
            $doctorStmt = $conn->prepare($doctorQuery);
            $doctorStmt->bind_param("i", $doctor_id);
            $doctorStmt->execute();
            $doctorResult = $doctorStmt->get_result();
            
            if ($doctorResult->num_rows === 0) {
                throw new Exception("Doctor not found.");
            }
            $doctor = $doctorResult->fetch_assoc();
            $doctorName = "Dr. " . $doctor['full_name'];

            // 6. Create notification
            $formattedDate = date('F j, Y', strtotime($date));
            $formattedTime = date('g:i A', strtotime($time));
            $message = "You have an appointment with $doctorName at $formattedDate $formattedTime";

            $notificationQuery = "INSERT INTO notifications (sender_id, receiver_id, message, type_notification, created_at, is_read, appointment_id) VALUES (?, ?, ?, 'setting an appointment', NOW(), 0, ?)";
            $notificationStmt = $conn->prepare($notificationQuery);
            $notificationStmt->bind_param("iisi", $secretary_id, $patient_user_id, $message, $appointment_id);
            if (!$notificationStmt->execute()) {
                throw new Exception("Error creating notification: " . $notificationStmt->error);
            }

            // 7. Link doctor and patient if not already linked
            $linkQuery = "SELECT * FROM doctorpatient WHERE doctor_id = ? AND patient_id = ?";
            $linkStmt = $conn->prepare($linkQuery);
            $linkStmt->bind_param("ii", $doctor_id, $patient_id);
            $linkStmt->execute();
            $linkResult = $linkStmt->get_result();

            if ($linkResult->num_rows == 0) {
                $insertLink = $conn->prepare("INSERT INTO doctorpatient (doctor_id, patient_id) VALUES (?, ?)");
                $insertLink->bind_param("ii", $doctor_id, $patient_id);
                if (!$insertLink->execute()) {
                    throw new Exception("Doctor-patient relationship failed: " . $insertLink->error);
                }
            }

            // Commit transaction
            $conn->commit();
            header("Location: appointments.php?success=" . urlencode("Appointment saved successfully."));
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        header("Location: appointments.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>
