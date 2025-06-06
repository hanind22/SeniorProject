<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

// Step 1: Get the patient_id from the patients table
$patientQuery = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$patientQuery->bind_param("i", $userId);
$patientQuery->execute();
$patientResult = $patientQuery->get_result();

if ($patientResult->num_rows === 0) {
    echo json_encode(['error' => 'Patient not found for this user']);
    exit;
}

$patientId = $patientResult->fetch_assoc()['patient_id'];

try {
    $stmt = $conn->prepare("
        SELECT 
            a.appointment_id as id,
            DATE_FORMAT(a.appointment_date, '%Y-%m-%d') as appointment_date,
            TIME_FORMAT(a.appointment_time, '%H:%i:%s') as appointment_time,
            a.appointment_type,
            a.reason_for_visit as purpose,
            a.status,
            a.notes,
            u.full_name as doctor_name,
            d.specialty
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        JOIN users u ON d.user_id = u.user_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    echo json_encode($appointments);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
