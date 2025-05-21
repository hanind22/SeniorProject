<?php
header('Content-Type: application/json');
require_once('../db-config/connection.php');

$patientId = $_GET['patient_id'] ?? '';

if (empty($patientId)) {
    echo json_encode(['error' => 'Patient ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT u.full_name 
        FROM users u
        JOIN patients p ON u.user_id = p.user_id
        WHERE p.patient_id = ?
    ");
    
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        echo json_encode($patient);
    } else {
        echo json_encode(['error' => 'Patient not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>