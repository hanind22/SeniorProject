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

    // Verify the notification belongs to this user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE notification_id = ? 
        AND receiver_id = ?
    ");
    $stmt->bind_param("ii", $notificationId, $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Return the updated notification count
        $countStmt = $conn->prepare("
            SELECT COUNT(*) AS unreadCount 
            FROM notifications 
            WHERE receiver_id = ? AND is_read = 0
        ");
        $countStmt->bind_param("i", $_SESSION['user_id']);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $count = $countResult->fetch_assoc()['unreadCount'];
        
        echo json_encode([
            'success' => true,
            'unreadCount' => $count
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>