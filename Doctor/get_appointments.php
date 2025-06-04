<?php
session_start();
header('Content-Type: application/json');
require_once('../db-config/connection.php');

try {
    // Get doctor_id from session
    $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    
    if (!$doctor) {
        throw new Exception("Doctor not found");
    }

    // Get all appointments for this doctor
    $stmt = $conn->prepare("
        SELECT a.*, u.full_name AS patient_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u ON p.user_id = u.user_id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->bind_param("i", $doctor['doctor_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['appointment_date'];
        $time = date("g:i A", strtotime($row['appointment_time']));
        
        $appointments[$date][] = [
            'id' => $row['appointment_id'],
            'time' => $time,
            'patientName' => $row['patient_name'],
            'type' => $row['appointment_type'],
            'purpose' => $row['reason_for_visit'],
            'notes' => $row['notes'],
            'patient_id' =>$row['patient_id'],
            'status' => $row['status'] 
        ];
    }

    echo json_encode($appointments);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>