<?php
session_start();
include("../db-config/connection.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Collect form data
$doctor_id = $_SESSION['user_id'] ?? null;
$patient_id = $_POST['patient_id'] ?? null;
$visit_date = $_POST['visit_date'] ?? date('Y-m-d');
$reason_for_visit = $_POST['reason_for_visit'] ?? '';
$observations = $_POST['observations'] ?? '';
$diagnosis = $_POST['diagnosis'] ?? '';
$treatment_plan = $_POST['treatment_plan'] ?? '';
$follow_up_instructions = $_POST['follow_up_instructions'] ?? null;
$next_appointment_date = $_POST['next_appointment'] ?? null;
$next_appointment_time = $_POST['next_appointment_time'] ?? ''; // Default time or get from form

// Validate and format the time
if (!empty($next_appointment_time)) {
    // Convert to proper time format (HH:MM:SS)
    $time = DateTime::createFromFormat('H:i', $next_appointment_time);
    if (!$time) {
        // Try alternative format if first attempt fails
        $time = DateTime::createFromFormat('H:i:s', $next_appointment_time);
    }
    
    if ($time) {
        $next_appointment_time = $time->format('H:i:s');
    } else {
        // Fallback to default if time is invalid
        $next_appointment_time = '09:00:00';
        error_log("Invalid time format provided, using default: " . $_POST['next_appointment_time']);
    }
} else {
    $next_appointment_time = '09:00:00';
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Insert medical record
    $query = "INSERT INTO medical_records 
              (doctor_id, patient_id, visit_date, reason_for_visit, doctors_observation, 
              diagnosis, treatment_plan, followup_instruction, nextAppointmentDate)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "iisssssss", 
        $doctor_id,
        $patient_id,
        $visit_date,
        $reason_for_visit,
        $observations,
        $diagnosis,
        $treatment_plan,
        $follow_up_instructions,
        $next_appointment_date
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Execute failed: " . mysqli_error($conn));
    }
    
    $record_id = mysqli_insert_id($conn);
    if (!$record_id) {
        throw new Exception("Failed to get last insert ID");
    }
    
    // Insert prescription if medication data exists
    if (!empty($_POST['med_name']) && is_array($_POST['med_name'])) {
        $med_names = $_POST['med_name'];
        $dosages = $_POST['med_dosage'];
        $frequencies = $_POST['frequency'];
        $durations = $_POST['duration'];
        $instructions = $_POST['special_instructions'];

        $prescriptionQuery = "INSERT INTO prescriptions 
            (record_id, medication_name, dosage, frequency, duration, instructions)
            VALUES (?, ?, ?, ?, ?, ?)";
        
        $prescriptionStmt = mysqli_prepare($conn, $prescriptionQuery);
        if (!$prescriptionStmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }

        foreach ($med_names as $index => $med_name) {
            $med_name = trim($med_name);
            $dosage = trim($dosages[$index] ?? '');
            $frequency = trim($frequencies[$index] ?? '');
            $duration = trim($durations[$index] ?? '');
            $instruction = trim($instructions[$index] ?? '');

            mysqli_stmt_bind_param($prescriptionStmt, "isssss", 
                $record_id, $med_name, $dosage, $frequency, $duration, $instruction
            );

            if (!mysqli_stmt_execute($prescriptionStmt)) {
                throw new Exception("Execute failed: " . mysqli_error($conn));
            }
        }
        mysqli_stmt_close($prescriptionStmt);
    }
    
    // If next appointment date is set, create the follow-up appointment
    if (!empty($next_appointment_date)) {

          // Debug output
        error_log("Creating appointment with date: $next_appointment_date and time: $next_appointment_time");

        // Check if appointment already exists
        $checkQuery = "SELECT appointment_id FROM appointments 
                      WHERE doctor_id = ? 
                      AND appointment_date = ? 
                      AND appointment_time = ?";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "iss", $doctor_id, $next_appointment_date, $next_appointment_time);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            throw new Exception("A follow-up appointment already exists for the selected date and time.");
        }
        mysqli_stmt_close($checkStmt);

        // Create the follow-up appointment
        $appointmentQuery = "INSERT INTO appointments 
                           (patient_id, doctor_id, appointment_date, appointment_time, 
                           appointment_type, status, reason_for_visit, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $appointmentStmt = mysqli_prepare($conn, $appointmentQuery);
        if (!$appointmentStmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        
        $appointmentType = 'Follow-up';
        $status = 'Scheduled';
        $reason = 'Follow-up for previous treatment';
        $notes = $follow_up_instructions;
        
        mysqli_stmt_bind_param($appointmentStmt, "iissssss", 
            $patient_id,
            $doctor_id,
            $next_appointment_date,
            $next_appointment_time,
            $appointmentType,
            $status,
            $reason,
            $notes
        );
        
        if (!mysqli_stmt_execute($appointmentStmt)) {
            throw new Exception("Execute failed: " . mysqli_error($conn));
        }
        mysqli_stmt_close($appointmentStmt);
    }
    
    mysqli_commit($conn);
    header('Location: medical_records.php?success=1');
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Database Error: " . $e->getMessage());
    header('Location: medical_records.php?error=' . urlencode($e->getMessage()));
    exit();
} finally {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    if (isset($prescriptionStmt)) {
        mysqli_stmt_close($prescriptionStmt);
    }
    if (isset($appointmentStmt)) {
        mysqli_stmt_close($appointmentStmt);
    }
    if (isset($checkStmt)) {
        mysqli_stmt_close($checkStmt);
    }
    mysqli_close($conn);
}
?>