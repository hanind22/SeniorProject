<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // First get the doctor_id associated with this user
    $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Doctor not found']);
        exit;
    }
    
    $doctorData = $result->fetch_assoc();
    $doctorId = $doctorData['doctor_id'];

    // Delete notifications older than the current week
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE receiver_id = ?
        AND YEARWEEK(created_at, 1) < YEARWEEK(CURDATE(), 1)
    ");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();

    echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>