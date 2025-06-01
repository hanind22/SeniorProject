<?php
session_start();
include("../db-config/connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_appointment'])) {
    try {
        // Add 'type' to required fields check
        if (!isset($_POST['patient_id'], $_POST['doctor_id'], $_POST['date'], $_POST['time'], $_POST['type'])) {
            throw new Exception("Missing required fields.");
        }

        $patient_id = $_POST['patient_id'];
        $doctor_id = $_POST['doctor_id'];
        $date = $_POST['date'];
        $raw_time = $_POST['time'];
        $type = $_POST['type']; // ADDED: Capture the type

        $datetime = strtotime($date . ' ' . $raw_time);
        if (!$datetime) {
            throw new Exception("Invalid datetime format: $date $raw_time");
        }
        $time = date('H:i:s', $datetime);

        // Update query to check type as well if needed
        $checkQuery = "SELECT appointment_id FROM appointments 
                      WHERE doctor_id = ? 
                      AND appointment_date = ? 
                      AND appointment_time = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("iss", $doctor_id, $date, $time);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            header("Location: Dr_Appointment.php?error=" . urlencode("This appointment can't be set at that time because there is already an appointment scheduled."));
            exit();
        }

        // UPDATE: Include type in INSERT query
        $query = "INSERT INTO appointments 
                 (patient_id, doctor_id, appointment_date, appointment_time, appointment_type) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iisss", $patient_id, $doctor_id, $date, $time, $type); // Added 's' for type

        if (!$stmt->execute()) {
            throw new Exception("Error saving appointment: " . $stmt->error);
        }

        // 🔄 Insert into DoctorPatient if not already linked
        $linkStmt = $conn->prepare("INSERT IGNORE INTO DoctorPatient (doctor_id, patient_id) VALUES (?, ?)");
        $linkStmt->bind_param("ii", $doctor_id, $patient_id);
        $linkStmt->execute();

        // Redirect back with success message
        header("Location: Dr_Appointment.php?success=" . urlencode("Appointment saved successfully."));
        exit();

    } catch (Exception $e) {
        header("Location: Dr_Appointment.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>