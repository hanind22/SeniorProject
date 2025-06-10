<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];

    // Get unread count directly by user_id
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unreadCount 
        FROM notifications 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['unreadCount'];

    echo json_encode(['unreadCount' => $count]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>