<?php
header('Content-Type: application/json');
include('../db-config/connection.php');

try {
    $query = "SELECT 
                d.doctor_id as id,
                u.full_name as name,
                d.license_number as licenseNumber,
                d.specialty,
                YEAR(u.created_at) as experience, 
                COALESCE(
                    (SELECT COUNT(DISTINCT patient_id) FROM doctorpatient WHERE doctor_id = d.doctor_id), 
                    0
                ) as patientCount
              FROM doctors d
              JOIN users u ON d.user_id = u.user_id
              ORDER BY u.full_name";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $doctors
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>