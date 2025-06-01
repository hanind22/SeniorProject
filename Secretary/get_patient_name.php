<?php
require_once('../db-config/connection.php');

if (isset($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    
    $stmt = $conn->prepare("
        SELECT u.full_name 
        FROM patients p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'patient_name' => $patient['full_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Patient not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No patient ID provided'
    ]);
}
?>