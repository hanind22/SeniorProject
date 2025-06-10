<?php
// get_doctors.php
header('Content-Type: application/json');

// For testing - comment out in production
// echo json_encode([['user_id'=>1, 'full_name'=>'Test Doctor']]);
// exit;

require_once('../db-config/connection.php'); // Adjust path as needed

try {
    if (!isset($_GET['specialty'])) {
        throw new Exception('Specialty parameter missing');
    }
    
    $specialty = $_GET['specialty'];
    
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name 
        FROM users u
        JOIN doctors d ON u.user_id = d.user_id
        WHERE d.specialty = ?
        ORDER BY u.full_name ASC
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $specialty);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $doctors = [];
    
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    
    echo json_encode($doctors);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>