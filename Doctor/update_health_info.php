<?php
header('Content-Type: application/json');
// During debugging, uncomment the following lines:
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

include('../db-config/connection.php');

if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'No connection object')
    ]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    if ($patientId <= 0) {
        throw new Exception("Invalid patient ID");
    }

    $allergies = trim($_POST['allergies'] ?? '');
    $medicalConditions = trim($_POST['medical_conditions'] ?? '');
    $currentMedications = trim($_POST['current_medications'] ?? '');
    $previousSurgeries = trim($_POST['previous_surgeries'] ?? '');
    $familyHistory = trim($_POST['family_history'] ?? '');

    // Check patient existence
    $checkStmt = $conn->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    if (!$checkStmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    $checkStmt->bind_param("i", $patientId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows === 0) {
        throw new Exception("Patient not found");
    }
    $checkStmt->close();

    // Update patient info
    $updateStmt = $conn->prepare("
        UPDATE patients
        SET allergies = ?, medical_conditions = ?, current_medications = ?, previous_surgeries = ?, family_history = ?
        WHERE patient_id = ?
    ");
    if (!$updateStmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    $updateStmt->bind_param("sssssi",
        $allergies,
        $medicalConditions,
        $currentMedications,
        $previousSurgeries,
        $familyHistory,
        $patientId
    );
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update patient information: " . $updateStmt->error);
    }

    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Patient health information updated successfully',
        'updated_rows' => $affectedRows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
