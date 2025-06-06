<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // First check if user is a secretary
    $stmt = $conn->prepare("SELECT doctor_id FROM secretary WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // User is a secretary - get their associated doctor_id
        $userData = $result->fetch_assoc();
        $doctorId = $userData['doctor_id'];
    } else {
        // If not a secretary, check if they're a doctor
        $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['error' => 'User is neither a doctor nor a secretary']);
            exit;
        }
        
        $userData = $result->fetch_assoc();
        $doctorId = $userData['doctor_id'];
    }

    // Get all notifications for the doctor
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