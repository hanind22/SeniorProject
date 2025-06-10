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

    // Fetch notifications where receiver_id = user_id directly
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

    $stmt->bind_param("i", $userId);
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
