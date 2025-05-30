<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notificationIds = $data['notificationIds'] ?? [];

if (empty($notificationIds)) {
    echo json_encode(['success' => false, 'message' => 'No notifications provided']);
    exit;
}

// Convert array to comma-separated string for SQL
$placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
$types = str_repeat('i', count($notificationIds));

$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE notification_id IN ($placeholders) AND receiver_id = ?
");
$params = array_merge($notificationIds, [$_SESSION['user_id']]);
$stmt->bind_param($types . 'i', ...$params);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
?>