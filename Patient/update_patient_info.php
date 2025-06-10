<?php
session_start();
header('Content-Type: application/json');
require_once('../db-config/connection.php');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access");
    }

    // Get all the form data
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone_number'] ?? '';
    $insuranceProvider = $_POST['insurance_provider'] ?? '';
    $insuranceNumber = $_POST['insurance_number'] ?? '';
    $allergies = $_POST['allergies'] ?? '';
    $medicalConditions = $_POST['medical_conditions'] ?? '';
    $currentMedications = $_POST['current_medications'] ?? '';
    $previousSurgeries = $_POST['previous_surgeries'] ?? '';

    // Update patients table
    $stmt = $conn->prepare("
        UPDATE patients 
        SET     
            insurance_provider = ?,
            insurance_number = ?,
            allergies = ?,
            medical_conditions = ?,
            current_medications = ?,
            previous_surgeries = ?
        WHERE user_id = ?
    ");
    
    $stmt->bind_param(
        "ssssssi",
       
        $insuranceProvider,
        $insuranceNumber,
        $allergies,
        $medicalConditions,
        $currentMedications,
        $previousSurgeries,
        $_SESSION['user_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update patient info");
    }

    // Update users table (email)
    $stmt = $conn->prepare("UPDATE users SET email = ? , phone_number = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $email, $phone, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update email");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>