<?php
session_start();
header('Content-Type: application/json');
require_once '../db-config/connection.php';

// Debug logging
// file_put_contents('cancel_debug.log', 
//     date('Y-m-d H:i:s') . " - " . 
//     print_r(['method' => $_SERVER['REQUEST_METHOD'], true]) . 
//     print_r($_POST, true) . "\n",
//     FILE_APPEND
// );

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false, 
        'message' => 'Invalid request method',
        'received_method' => $_SERVER['REQUEST_METHOD']
    ]));
}

// Validate required fields
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

// In cancel_appointment.php, before the UPDATE query:
$checkStmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
$checkStmt->bind_param("i", $appointmentId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Appointment not found']));
}

$appointment = $checkResult->fetch_assoc();
if ($appointment['status'] === 'Cancelled') {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Appointment already cancelled']));
}
try {
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
    $success = $stmt->execute();

    if ($success && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No rows affected - appointment not found or already canceled'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>