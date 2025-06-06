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

    // Delete notifications older than the current week for this doctor
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE receiver_id = ?
        AND YEARWEEK(created_at, 1) < YEARWEEK(CURDATE(), 1)
    ");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'deleted' => $stmt->affected_rows,
        'message' => 'Deleted '.$stmt->affected_rows.' old notifications'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ]);
}
?>