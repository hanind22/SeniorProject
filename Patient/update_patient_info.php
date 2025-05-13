<?php
session_start();
require_once '../db-config/connection.php';

// Debug: Log POST data
error_log("POST Data: " . print_r($_POST, true));

// Check authentication
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) !== 'patient') {
    die(json_encode([
        'status' => 'error',
        'message' => 'Authentication failed',
        'session' => $_SESSION
    ]));
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log raw input
    error_log("Raw input: " . file_get_contents('php://input'));
    
    // Get form data with additional validation
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
    $allergies = filter_input(INPUT_POST, 'allergies', FILTER_SANITIZE_STRING) ?? '';
    $medical_conditions = filter_input(INPUT_POST, 'medical_conditions', FILTER_SANITIZE_STRING) ?? '';
    $current_medications = filter_input(INPUT_POST, 'current_medications', FILTER_SANITIZE_STRING) ?? '';
    $previous_surgeries = filter_input(INPUT_POST, 'previous_surgeries', FILTER_SANITIZE_STRING) ?? '';
    $insurance_provider = filter_input(INPUT_POST, 'insurance_provider', FILTER_SANITIZE_STRING) ?? '';
    $insurance_number = filter_input(INPUT_POST, 'insurance_number', FILTER_SANITIZE_STRING) ?? '';

    // Validate inputs
    $errors = [];
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone_number)) $errors[] = "Phone number is required";
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

    if (!empty($errors)) {
        $_SESSION['update_error'] = implode("<br>", $errors);
        echo('An error occured');
        exit();
    }

    try {
        $conn->begin_transaction();
        
        // Update users table
        $stmt = $conn->prepare("UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ssi", $email, $phone_number, $user_id);
        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
        
        // Update patients table
        $stmt = $conn->prepare("UPDATE patients SET 
            allergies = ?, medical_conditions = ?, 
            current_medications = ?, previous_surgeries = ?,
            insurance_provider = ?, insurance_number = ?
            WHERE user_id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $stmt->bind_param("ssssssi", $allergies, $medical_conditions, 
                         $current_medications, $previous_surgeries,$insurance_provider,$insurance_number, $user_id);
        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
        
        if (!$conn->commit()) {
            throw new Exception("Commit failed: " . $conn->error);
        }
        
        // Verify update
        $check = $conn->query("SELECT email, phone_number FROM users WHERE user_id = $user_id");
        error_log("Update verification: " . print_r($check->fetch_assoc(), true));
        
        // Update session
        $_SESSION['email'] = $email;
        $_SESSION['phone_number'] = $phone_number;
        $_SESSION['update_success'] = "Profile updated successfully";
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Update error: " . $e->getMessage());
        $_SESSION['update_error'] = "Update failed. Please try again.";
    }

    header("Location: patient_dashboard.php");
    exit();
}
?>