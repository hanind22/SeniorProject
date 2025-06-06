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

    // First, get the doctor_id associated with this secretary's user_id
    $stmt = $conn->prepare("SELECT doctor_id FROM secretary WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Secretary not found or not assigned to a doctor');
    }
    
    $secretaryData = $result->fetch_assoc();
    $doctorId = $secretaryData['doctor_id'];
    $stmt->close();

    // Now update the notification for this doctor
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
        AND receiver_id = ?
    ");
    $stmt->bind_param("ii", $notificationId, $doctorId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Notification not found or not assigned to your doctor');
    }

    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}