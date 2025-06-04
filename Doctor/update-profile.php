<?php
header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once('../db-config/connection.php');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die(json_encode(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()]));
}

try {
    // Validate required fields
    $requiredFields = ['doctor_id', 'email', 'phone', 'license_number'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Extract data
    $doctor_id = $conn->real_escape_string($data['doctor_id']);
    $email = $conn->real_escape_string($data['email']);
    $phone = $conn->real_escape_string($data['phone']);
    $license_number = $conn->real_escape_string($data['license_number']);
    $education = $conn->real_escape_string($data['education'] ?? '');
    $certifications = $conn->real_escape_string($data['certifications'] ?? '');
    $bio = $conn->real_escape_string($data['bio'] ?? '');
    $availability = $data['availability'] ?? [];

    $conn->begin_transaction();

    // 1. First get the user_id associated with this doctor
    $stmtGetUser = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $stmtGetUser->bind_param("i", $doctor_id);
    $stmtGetUser->execute();
    $userResult = $stmtGetUser->get_result();
    $userData = $userResult->fetch_assoc();
    
    if (!$userData) {
        throw new Exception("No user associated with this doctor ID");
    }
    $user_id = $userData['user_id'];

    // 2. Update users table (email and phone)
    $stmtUpdateUser = $conn->prepare("UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?");
    $stmtUpdateUser->bind_param("ssi", $email, $phone, $user_id);
    if (!$stmtUpdateUser->execute()) {
        throw new Exception("Failed to update user: " . $stmtUpdateUser->error);
    }

    // 3. Update doctors table (other fields)
    $stmtUpdateDoctor = $conn->prepare("UPDATE doctors SET 
        license_number = ?, 
        education = ?, 
        certifications = ?, 
        bio = ? 
        WHERE doctor_id = ?");
    $stmtUpdateDoctor->bind_param("ssssi", $license_number, $education, $certifications, $bio, $doctor_id);
    if (!$stmtUpdateDoctor->execute()) {
        throw new Exception("Failed to update doctor: " . $stmtUpdateDoctor->error);
    }

    // 4. Update availability - first delete existing
    $stmtDeleteAvailability = $conn->prepare("DELETE FROM work_place WHERE doctor_id = ?");
    $stmtDeleteAvailability->bind_param("i", $doctor_id);
    if (!$stmtDeleteAvailability->execute()) {
        throw new Exception("Failed to clear availability: " . $stmtDeleteAvailability->error);
    }

    // 5. Insert new availability slots
    foreach ($availability as $day => $slots) {
        foreach ($slots as $slot) {
            if (empty($slot['place_name']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                continue;
            }

            $status = $conn->real_escape_string($slot['status'] ?? 'available');
            $start_time = $conn->real_escape_string($slot['start_time']);
            $end_time = $conn->real_escape_string($slot['end_time']);
            $place_name = $conn->real_escape_string($slot['place_name']);

            $stmtInsertAvailability = $conn->prepare("INSERT INTO work_place 
                (doctor_id, status, day, start_time, end_time, place_name) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsertAvailability->bind_param("isssss", 
                $doctor_id, $status, $day, $start_time, $end_time, $place_name);
            if (!$stmtInsertAvailability->execute()) {
                throw new Exception("Failed to insert availability: " . $stmtInsertAvailability->error);
            }
            $stmtInsertAvailability->close();
        }
    }

    $conn->commit();

    // Verify the updates
    $result = $conn->query("SELECT email, phone_number FROM users WHERE user_id = $user_id");
    $currentValues = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'debug' => [
            'user_id' => $user_id,
            'doctor_id' => $doctor_id,
            'email_updated' => $email,
            'phone_updated' => $phone,
            'current_email' => $currentValues['email'],
            'current_phone' => $currentValues['phone_number']
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTrace()
    ]);
} finally {
    $conn->close();
}