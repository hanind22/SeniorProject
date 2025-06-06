<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // Get doctor_id
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

    // Get unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unreadCount 
        FROM notifications 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['unreadCount'];

    echo json_encode(['unreadCount' => $count]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>