<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Patient not found']);
        exit;
    }
    
    $patientData = $result->fetch_assoc();
    $doctorId = $patientData['patient_id'];

    $stmt = $conn->prepare("
        SELECT 
            n.notification_id AS id,
            n.message,
            n.type_notification AS type,
            n.created_at,
            n.is_read,
            u.full_name AS sender_name
        FROM notifications n
        JOIN users u ON n.sender_id = u.user_id
        WHERE n.receiver_id = ?
        ORDER BY n.created_at DESC
    ");
    
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    echo json_encode($notifications);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>