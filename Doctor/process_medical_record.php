<?php
session_start();
header('Content-Type: application/json');
include("../db-config/connection.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log all received POST data
error_log("Received POST data: " . print_r($_POST, true));

// Verify session and permissions
if (!isset($_SESSION['user_id'])) {
    die(json_encode(["success" => false, "error" => "Unauthorized access"]));
}

// Verify database connection
if (!$conn) {
    die(json_encode(["success" => false, "error" => "Database connection failed"]));
}
error_log("Database connection successful");

// Start transaction
mysqli_begin_transaction($conn);
error_log("Transaction started");

try {
    // FIRST get the actual doctor_id from the user_id in session
    $get_doctor_query = "SELECT doctor_id FROM doctors WHERE user_id = ?";
    $get_doctor_stmt = mysqli_prepare($conn, $get_doctor_query);
    mysqli_stmt_bind_param($get_doctor_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($get_doctor_stmt);
    $doctor_result = mysqli_stmt_get_result($get_doctor_stmt);
    $doctor_data = mysqli_fetch_assoc($doctor_result);
    mysqli_stmt_close($get_doctor_stmt);

    if (!$doctor_data) {
        throw new Exception("Doctor profile not found for this user");
    }
    
    $doctor_id = $doctor_data['doctor_id'];
    error_log("Retrieved doctor_id: $doctor_id");

    // Collect and sanitize form data
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $visit_date = $_POST['visit_date'] ?? date('Y-m-d');
    $reason_for_visit = mysqli_real_escape_string($conn, $_POST['reason_for_visit'] ?? '');
    $observations = mysqli_real_escape_string($conn, $_POST['observations'] ?? '');
    $diagnosis = mysqli_real_escape_string($conn, $_POST['diagnosis'] ?? '');
    $treatment_plan = mysqli_real_escape_string($conn, $_POST['treatment_plan'] ?? '');
    $follow_up_instructions = mysqli_real_escape_string($conn, $_POST['follow_up_instructions'] ?? null);
    
    // Check if follow-up is required and validate fields
    $require_followup = isset($_POST['require_followup']) && $_POST['require_followup'] == 'on';
    $next_appointment_date = $require_followup ? ($_POST['next_appointment'] ?? null) : null;
    $next_appointment_time = $require_followup ? ($_POST['next_appointment_time'] ?? '09:00:00') : null;

    // Validate required fields
    if (empty($patient_id) || empty($reason_for_visit) || empty($observations) || empty($diagnosis) || empty($treatment_plan)) {
        throw new Exception("All required fields must be filled");
    }

    // Validate follow-up appointment fields if required
    if ($require_followup) {
        if (empty($next_appointment_date) || empty($next_appointment_time)) {
            throw new Exception("Follow-up appointment date and time are required when scheduling a follow-up");
        }
        
        // Ensure follow-up date is in the future
        $today = new DateTime();
        $followup_date = new DateTime($next_appointment_date);
        if ($followup_date <= $today) {
            throw new Exception("Follow-up appointment must be scheduled for a future date");
        }
    }

    // Get patient's user_id
    $patient_user_query = "SELECT user_id FROM patients WHERE patient_id = ?";
    $patient_user_stmt = mysqli_prepare($conn, $patient_user_query);
    mysqli_stmt_bind_param($patient_user_stmt, "i", $patient_id);
    mysqli_stmt_execute($patient_user_stmt);
    $patient_user_result = mysqli_stmt_get_result($patient_user_stmt);
    $patient_user_row = mysqli_fetch_assoc($patient_user_result);
    
    if (!$patient_user_row) {
        throw new Exception("Patient not found");
    }
    
    $receiver_id = $patient_user_row['user_id'];
    mysqli_stmt_close($patient_user_stmt);

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
    error_log("Inserted medical record with ID: $record_id");
    mysqli_stmt_close($stmt);
    
    // Insert prescriptions if they exist
    if (!empty($_POST['med_name']) && is_array($_POST['med_name'])) {
        error_log("Processing prescriptions...");
        $prescriptionQuery = "INSERT INTO prescriptions 
            (record_id, medication_name, dosage, frequency, duration, instructions)
            VALUES (?, ?, ?, ?, ?, ?)";
        
        $prescriptionStmt = mysqli_prepare($conn, $prescriptionQuery);
        if (!$prescriptionStmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }

        foreach ($_POST['med_name'] as $index => $med_name) {
            $med_name = trim($med_name);
            $dosage = trim($_POST['med_dosage'][$index] ?? '');
            $frequency = trim($_POST['frequency'][$index] ?? '');
            $duration = trim($_POST['duration'][$index] ?? '');
            $instruction = trim($_POST['special_instructions'][$index] ?? '');

            mysqli_stmt_bind_param($prescriptionStmt, "isssss", 
                $record_id, $med_name, $dosage, $frequency, $duration, $instruction
            );

            if (!mysqli_stmt_execute($prescriptionStmt)) {
                throw new Exception("Execute failed: " . mysqli_error($conn));
            }
            error_log("Inserted prescription: $med_name");
        }
        mysqli_stmt_close($prescriptionStmt);
    }

    // Create notification for medical record and prescription
    $formatted_date = date("F j, Y", strtotime($visit_date));
    $medical_record_message = "Dr. " . $_SESSION['full_name'] . " has sent the medical record and prescription related to your appointment on $formatted_date.";
    
    $medical_record_notif = "INSERT INTO notifications 
        (sender_id, receiver_id, appointment_id, message, type_notification, created_at)
        VALUES (?, ?, NULL, ?, 'Medical Record and Prescription', NOW())";
    
    $medical_record_stmt = mysqli_prepare($conn, $medical_record_notif);
    mysqli_stmt_bind_param($medical_record_stmt, "iis", 
        $_SESSION['user_id'], // sender_id is the doctor's user_id from session
        $receiver_id,
        $medical_record_message
    );
    
    if (!mysqli_stmt_execute($medical_record_stmt)) {
        throw new Exception("Failed to create medical record notification: " . mysqli_error($conn));
    }
    error_log("Created medical record notification");
    mysqli_stmt_close($medical_record_stmt);
    
    // Create follow-up appointment if requested and validated
    if ($require_followup && !empty($next_appointment_date)) {
        error_log("Creating follow-up appointment...");
        
        // Check for existing appointment
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

        // Insert new appointment
        $appointmentQuery = "INSERT INTO appointments 
                           (patient_id, doctor_id, appointment_date, appointment_time, 
                           appointment_type, status, reason_for_visit, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $appointmentStmt = mysqli_prepare($conn, $appointmentQuery);
        
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
        
        $appointment_id = mysqli_insert_id($conn);
        error_log("Created follow-up appointment with ID: $appointment_id");
        mysqli_stmt_close($appointmentStmt);

        // Create notification for follow-up appointment
        $formatted_appt_date = date("F j, Y", strtotime($next_appointment_date));
        $formatted_appt_time = date("g:i A", strtotime($next_appointment_time));
        $appointment_message = "Dr. " . $_SESSION['full_name'] . " has scheduled a follow-up appointment for you on $formatted_appt_date at $formatted_appt_time.";
        
        $appointment_notif = "INSERT INTO notifications 
            (sender_id, receiver_id, appointment_id, message, type_notification, created_at)
            VALUES (?, ?, ?, ?, 'Follow-up Appointment', NOW())";
        
        $appointment_notif_stmt = mysqli_prepare($conn, $appointment_notif);
        mysqli_stmt_bind_param($appointment_notif_stmt, "iiis", 
            $_SESSION['user_id'], // sender_id is the doctor's user_id from session
            $receiver_id,
            $appointment_id,
            $appointment_message
        );
        
        if (!mysqli_stmt_execute($appointment_notif_stmt)) {
            throw new Exception("Failed to create appointment notification: " . mysqli_error($conn));
        }
        error_log("Created follow-up appointment notification");
        mysqli_stmt_close($appointment_notif_stmt);
    }
    
    mysqli_commit($conn);
    error_log("Transaction committed successfully");
    
    echo json_encode([
        'success' => true,
        'redirect' => 'medical_records.php?success=1'
    ]);
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
} finally {
    // Close all statements if they exist
    $statements = ['get_doctor_stmt', 'patient_user_stmt', 'stmt', 'prescriptionStmt', 
                  'appointmentStmt', 'checkStmt', 'medical_record_stmt', 'appointment_notif_stmt'];
    
    foreach ($statements as $stmt_var) {
        if (isset($$stmt_var)) {
            mysqli_stmt_close($$stmt_var);
        }
    }
    
    if ($conn) {
        mysqli_close($conn);
    }
}