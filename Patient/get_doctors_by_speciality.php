<?php
header('Content-Type: application/json');


// Include your MySQLi connection file
require_once __DIR__ . '/../db-config/connection.php';

// Verify database connection exists
if (!isset($conn)) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

try {
    // Get and validate speciality parameter
    $speciality = $_GET['speciality'] ?? '';
    if (empty($speciality)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Speciality is required']));
    }

    // First get doctor IDs with the matching speciality
    $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE specialty = ?");
    $stmt->bind_param("s", $speciality);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctorIds = [];
    
    while ($row = $result->fetch_assoc()) {
        $doctorIds[] = $row['doctor_id'];
    }
    
    if (empty($doctorIds)) {
        echo json_encode([]);
        exit;
    }
    
    // Then get user details for these doctors
    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
    $types = str_repeat('i', count($doctorIds));
    
    $query = "
        SELECT u.user_id, u.full_name, d.doctor_id
        FROM users u
        JOIN doctors d ON u.user_id = d.user_id
        WHERE d.doctor_id IN ($placeholders)
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$doctorIds);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    
    echo json_encode($doctors);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>