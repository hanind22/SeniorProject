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
    die("Unauthorized access");
}

// Verify database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
error_log("Database connection successful");

// Collect and sanitize form data
$doctor_id = $_SESSION['user_id'];
$patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
$visit_date = $_POST['visit_date'] ?? date('Y-m-d');
$reason_for_visit = mysqli_real_escape_string($conn, $_POST['reason_for_visit'] ?? '');
$observations = mysqli_real_escape_string($conn, $_POST['observations'] ?? '');
$diagnosis = mysqli_real_escape_string($conn, $_POST['diagnosis'] ?? '');
$treatment_plan = mysqli_real_escape_string($conn, $_POST['treatment_plan'] ?? '');
$follow_up_instructions = mysqli_real_escape_string($conn, $_POST['follow_up_instructions'] ?? null);
$next_appointment_date = $_POST['next_appointment'] ?? null;
$next_appointment_time = $_POST['next_appointment_time'] ?? '09:00:00';

// Validate required fields
if (empty($patient_id) || empty($reason_for_visit) || empty($observations) || empty($diagnosis) || empty($treatment_plan)) {
    header('Location: medical_records.php?error=' . urlencode('All required fields must be filled'));
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);
error_log("Transaction started");

try {
    // Get doctor's user_id
    $doctor_user_query = "SELECT user_id FROM doctors WHERE doctor_id = ?";
    $doctor_user_stmt = mysqli_prepare($conn, $doctor_user_query);
    mysqli_stmt_bind_param($doctor_user_stmt, "i", $doctor_id);
    mysqli_stmt_execute($doctor_user_stmt);
    $doctor_user_result = mysqli_stmt_get_result($doctor_user_stmt);
    $doctor_user_row = mysqli_fetch_assoc($doctor_user_result);
    $sender_id = $doctor_user_row['user_id'];
    mysqli_stmt_close($doctor_user_stmt);

    // Get patient's user_id
    $patient_user_query = "SELECT user_id FROM patients WHERE patient_id = ?";
    $patient_user_stmt = mysqli_prepare($conn, $patient_user_query);
    mysqli_stmt_bind_param($patient_user_stmt, "i", $patient_id);
    mysqli_stmt_execute($patient_user_stmt);
    $patient_user_result = mysqli_stmt_get_result($patient_user_stmt);
    $patient_user_row = mysqli_fetch_assoc($patient_user_result);
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
        $sender_id, 
        $receiver_id,
        $medical_record_message
    );
    
    if (!mysqli_stmt_execute($medical_record_stmt)) {
        throw new Exception("Failed to create medical record notification: " . mysqli_error($conn));
    }
    error_log("Created medical record notification");
    mysqli_stmt_close($medical_record_stmt);
    
    // Create follow-up appointment if requested
    if (!empty($next_appointment_date)) {
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
            $sender_id, 
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
    // Return JSON instead of redirect
    echo json_encode([
        'success' => true,
        'redirect' => 'medical_records.php?success=1'
    ]);
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error: " . $e->getMessage());
    // Return JSON error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
} finally {
    if (isset($stmt)) mysqli_stmt_close($stmt);
    if (isset($prescriptionStmt)) mysqli_stmt_close($prescriptionStmt);
    if (isset($appointmentStmt)) mysqli_stmt_close($appointmentStmt);
    if (isset($checkStmt)) mysqli_stmt_close($checkStmt);
    if (isset($medical_record_stmt)) mysqli_stmt_close($medical_record_stmt);
    if (isset($appointment_notif_stmt)) mysqli_stmt_close($appointment_notif_stmt);
    mysqli_close($conn);
}