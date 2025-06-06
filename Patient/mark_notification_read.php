<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? null;

    if (!$notificationId) {
        throw new Exception('Notification ID is required');
    }

    // Verify the notification belongs to this doctor
    $stmt = $conn->prepare("
        UPDATE notifications n
        JOIN patients d ON n.receiver_id = d.patient_id
        SET n.is_read = 1
        WHERE n.notification_id = ?
        AND d.user_id = ?
    ");
    $stmt->bind_param("ii", $notificationId, $_SESSION['user_id']);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}