<?php
session_start();
require_once('../db-config/connection.php');
header('Content-Type: application/json');

// Debug logging
error_log("Reset password request received");
error_log("POST data: " . json_encode($_POST));

$email = trim($_POST['email'] ?? '');
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

error_log("Email: $email, New password length: " . strlen($new));

if (!$email || !$new || !$confirm) {
    error_log("Missing required fields");
    echo json_encode(['success'=>false,'message'=>'All fields are required.']);
    exit;
}

if ($new !== $confirm) {
    error_log("Passwords don't match");
    echo json_encode(['success'=>false,'message'=>'Passwords must match.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows !== 1) {
        error_log("Email not found: $email");
        echo json_encode(['success'=>false,'message'=>'Email not found.']);
        exit;
    }

    $user = $res->fetch_assoc();
    $hash = password_hash($new, PASSWORD_DEFAULT);

    $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $upd->bind_param("si", $hash, $user['user_id']);
    
    if ($upd->execute()) {
        error_log("Password updated successfully for user: " . $user['user_id']);
        echo json_encode(['success'=>true,'message'=>'Password changed successfully.']);
    } else {
        throw new Exception($upd->error);
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
?>