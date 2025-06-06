<?php
session_start();
header('Content-Type: application/json');
require_once '../db-config/connection.php';

if (!$conn) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid request method',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]));
}

$required = ['appointment_id', 'cancelled_by', 'cancel_reason'];
$missing = array_diff($required, array_keys($_POST));

if (!empty($missing)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Missing required fields',
        'missing_fields' => array_values($missing),
        'received_data' => $_POST
    ]));
}

$appointmentId = (int)$_POST['appointment_id'];
$cancelledBy = (int)$_POST['cancelled_by'];
$cancelReason = trim($_POST['cancel_reason']);

// Get appointment details including patient and doctor info
$checkStmt = $conn->prepare("
    SELECT 
        a.status, 
        a.appointment_date, 
        a.appointment_time, 
        a.patient_id,
        a.doctor_id,
        pu.user_id as patient_user_id,
        du.user_id as doctor_user_id,
        pu.full_name as patient_name,
        du.full_name as doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users pu ON p.user_id = pu.user_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users du ON d.user_id = du.user_id
    WHERE a.appointment_id = ?
");
$checkStmt->bind_param("i", $appointmentId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Appointment not found']));
}

$appointment = $checkResult->fetch_assoc();
$checkStmt->close();

if (strtolower($appointment['status']) === 'cancelled') {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Appointment already cancelled']));
}

$conn->begin_transaction();

try {
    // Update appointment status
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET 
            status = 'Cancelled',
            cancelled_reason = ?,
            cancelled_by = ?,
            updated_at = NOW()
        WHERE appointment_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sii", $cancelReason, $cancelledBy, $appointmentId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Appointment update failed or no changes made");
    }
    $stmt->close();

    // Insert notification with appointment_id
    $patientName = $appointment['patient_name'];
    $doctorName = $appointment['doctor_name'];
    $appointmentDate = $appointment['appointment_date'];
    $appointmentTime = $appointment['appointment_time'];

    $message = "Patient {$patientName} has Cancelled their Appointment on {$appointmentDate} at {$appointmentTime} with Dr. {$doctorName} ";
    $type = "appointment_cancellation";

    $notifStmt = $conn->prepare("
        INSERT INTO notifications (
            message, 
            type_notification, 
            sender_id, 
            receiver_id,
            appointment_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    // sender_id = patient's user_id
    // receiver_id = doctor's user_id
    // appointment_id = the cancelled appointment's ID
    $notifStmt->bind_param("ssiii", 
        $message, 
        $type, 
        $appointment['patient_user_id'], 
        $appointment['doctor_user_id'],
        $appointmentId
    );
    $notifStmt->execute();
    $notifStmt->close();

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Cancel Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>