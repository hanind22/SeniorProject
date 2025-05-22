
<?php
// get_patient_data.php

// ========================
// 1. Force JSON response and disable errors
// ========================
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

// ========================
// 2. Database connection (using MySQLi to match patients.php)
// ========================
include('../db-config/connection.php');

// Check if connection exists
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

try {
    $patientId = (int)($_GET['patient_id'] ?? 0);
    
    // Validate ID
    if ($patientId <= 0) {
        throw new Exception("Invalid patient ID");
    }

    // ========================
    // 3. Fetch data using MySQLi
    // ========================
    $stmt = $conn->prepare("
        SELECT 
            p.patient_id,
            p.user_id,
            p.date_of_birth,
            p.gender,
            p.blood_type,
            p.allergies,
            p.medical_conditions,
            p.current_medications,
            p.previous_surgeries,
            p.family_history,
            u.full_name
        FROM patients p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.patient_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Patient not found");
    }
    
    $patient = $result->fetch_assoc();

    // Calculate age
    if (!empty($patient['date_of_birth'])) {
        $birthDate = new DateTime($patient['date_of_birth']);
        $today = new DateTime();
        $patient['age'] = $birthDate->diff($today)->y;
    }

    // ========================
    // 4. Return success
    // ========================
    echo json_encode([
        'success' => true,
        'data' => $patient
    ]);

} catch (Exception $e) {
    // Return error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    // Close statement if it exists
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
}
?>