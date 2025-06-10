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
    $userId = $_SESSION['user_id'];

    // First, get the doctor_id from the doctors table using the user_id
    $doctorStmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    $doctorStmt->bind_param("i", $userId);
    $doctorStmt->execute();
    $doctorResult = $doctorStmt->get_result();

    if ($doctorResult->num_rows === 0) {
        throw new Exception("You are not authorized as a doctor");
    }

    $doctorRow = $doctorResult->fetch_assoc();
    $doctorId = $doctorRow['doctor_id'];

    // Get patient uploads - only those uploaded by this doctor
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
        AND pu.doctor_id = ?  -- Only show uploads by this doctor
        ORDER BY pu.uploaded_at DESC
    ");
    
    $stmt->bind_param("ii", $patientId, $doctorId);
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