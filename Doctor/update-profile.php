<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('../db-config/connection.php');

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

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
    
    // Secretary data
    $secretary_name = $conn->real_escape_string($data['secretary_name'] ?? '');
    $secretary_email = $conn->real_escape_string($data['secretary_email'] ?? '');

    $conn->begin_transaction();

    // 1. Get the user_id associated with this doctor
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

    // 4. Handle secretary data
    if (!empty($secretary_name) && !empty($secretary_email)) {
        // Check if secretary already exists in users table
        $stmtCheckSecretary = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmtCheckSecretary->bind_param("s", $secretary_email);
        $stmtCheckSecretary->execute();
        $secretaryResult = $stmtCheckSecretary->get_result();
        
        $secretary_user_id = null;
        
        if ($secretaryResult->num_rows > 0) {
            // Secretary exists - get user_id
            $secretaryData = $secretaryResult->fetch_assoc();
            $secretary_user_id = $secretaryData['user_id'];
            
            // Update secretary info
            $stmtUpdateSecretary = $conn->prepare("UPDATE users SET 
                full_name = ?
                WHERE user_id = ?");
            $stmtUpdateSecretary->bind_param("si", $secretary_name, $secretary_user_id);
            if (!$stmtUpdateSecretary->execute()) {
                throw new Exception("Failed to update secretary: " . $stmtUpdateSecretary->error);
            }
        } else {
            // Create new secretary user (without password)
            $stmtCreateSecretary = $conn->prepare("INSERT INTO users 
                (full_name, email, user_type, created_at) 
                VALUES (?, ?, 'Secretary', NOW())");
            $stmtCreateSecretary->bind_param("ss", $secretary_name, $secretary_email);
            if (!$stmtCreateSecretary->execute()) {
                throw new Exception("Failed to create secretary: " . $stmtCreateSecretary->error);
            }
            $secretary_user_id = $conn->insert_id;
        }
        
        // Update secretary association with doctor
        // First delete any existing association
        $stmtDeleteSecretary = $conn->prepare("DELETE FROM secretary WHERE doctor_id = ?");
        $stmtDeleteSecretary->bind_param("i", $doctor_id);
        if (!$stmtDeleteSecretary->execute()) {
            throw new Exception("Failed to clear secretary association: " . $stmtDeleteSecretary->error);
        }
        
        // Create new association
        if ($secretary_user_id) {
            $stmtAssignSecretary = $conn->prepare("INSERT INTO secretary 
                (doctor_id, user_id) VALUES (?, ?)");
            $stmtAssignSecretary->bind_param("ii", $doctor_id, $secretary_user_id);
            if (!$stmtAssignSecretary->execute()) {
                throw new Exception("Failed to assign secretary: " . $stmtAssignSecretary->error);
            }
        }
    } else {
        // If no secretary data provided, remove any existing association
        $stmtDeleteSecretary = $conn->prepare("DELETE FROM secretary WHERE doctor_id = ?");
        $stmtDeleteSecretary->bind_param("i", $doctor_id);
        $stmtDeleteSecretary->execute(); // We don't throw error if this fails
    }

    // 5. Update availability - first delete existing
    $stmtDeleteAvailability = $conn->prepare("DELETE FROM work_place WHERE doctor_id = ?");
    $stmtDeleteAvailability->bind_param("i", $doctor_id);
    if (!$stmtDeleteAvailability->execute()) {
        throw new Exception("Failed to clear availability: " . $stmtDeleteAvailability->error);
    }

    // 6. Insert new availability slots
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

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}