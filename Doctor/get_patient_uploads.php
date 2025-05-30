<?php
session_start();
header('Content-Type: application/json');

include('../db-config/connection.php');


try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }

    if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
        throw new Exception("Invalid patient ID");
    }

    $patientId = $_GET['patient_id'];
    $doctorId = $_SESSION['user_id']; // Or get doctor_id from session if stored differently

    // Get patient uploads
    $stmt = $conn->prepare("
        SELECT 
            pu.upload_id,
            pu.doctor_id,
            pu.patient_id,
            pu.report_type,
            pu.file_path,
            pu.uploaded_at,
            pu.DateOfTest,
            pu.notes
        FROM patientuploads pu
        WHERE pu.patient_id = ?
        ORDER BY pu.uploaded_at DESC
    ");
    
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();

    $uploads = [];
    while ($row = $result->fetch_assoc()) {
        $uploads[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $uploads
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}