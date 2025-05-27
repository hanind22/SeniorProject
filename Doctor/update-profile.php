<?php
header('Content-Type: application/json');

// Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Database connection
require_once('../db-config/connection.php');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check for POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    // Validate required POST data
    $requiredFields = ['doctor_id', 'email', 'phone', 'specialty', 'license_number', 'education', 'certifications', 'bio',  'availability'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing field: $field");
        }
    }

    $doctor_id      = $conn->real_escape_string($_POST['doctor_id']);
    $email          = $conn->real_escape_string($_POST['email']);
    $phone          = $conn->real_escape_string($_POST['phone']);
    $specialty      = $conn->real_escape_string($_POST['specialty']);
    $license_number = $conn->real_escape_string($_POST['license_number']);
    $education      = $conn->real_escape_string($_POST['education']);
    $certifications = $conn->real_escape_string($_POST['certifications']);
    $bio            = $conn->real_escape_string($_POST['bio']);

    // Handle availability safely
    $availabilityRaw = $_POST['availability'] ?? null;
    if (is_null($availabilityRaw)) {
        throw new Exception("Missing field: availability");
    }

    if (is_array($availabilityRaw)) {
        $availability = $availabilityRaw;
    } else {
        $availability = json_decode($availabilityRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid availability JSON format: " . json_last_error_msg());
        }
    }

    if (!is_array($availability)) {
        throw new Exception("Invalid availability format - must be an array");
    }

    $conn->begin_transaction();

    // Update users table
    $stmtUser = $conn->prepare("UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?");
    $stmtUser->bind_param("ssi", $email, $phone, $doctor_id);
    $stmtUser->execute();

    // Update doctors table
    if (isset($avatarPath) && !empty($avatarPath)) {
        $stmtDoc = $conn->prepare("UPDATE doctors SET specialty = ?, license_number = ?, education = ?, certifications = ?, bio = ? WHERE doctor_id = ?");
        $stmtDoc->bind_param("sssssi", $specialty, $license_number, $education, $certifications, $bio, $doctor_id);
    } else {
        $stmtDoc = $conn->prepare("UPDATE doctors SET specialty = ?, license_number = ?, education = ?, certifications = ?, bio = ? WHERE doctor_id = ?");
        $stmtDoc->bind_param("sssssi", $specialty, $license_number, $education, $certifications, $bio, $doctor_id);
    }
    $stmtDoc->execute();

    // Remove previous work_place entries
    $stmtDelete = $conn->prepare("DELETE FROM work_place WHERE doctor_id = ?");
    $stmtDelete->bind_param("i", $doctor_id);
    $stmtDelete->execute();

    // Insert new work_place records
    foreach ($availability as $day => $slots) {
        if (!is_array($slots)) continue; // safety check

        foreach ($slots as $slot) {
            // Defensive checks and defaults
            $status     = isset($slot['status']) ? $conn->real_escape_string($slot['status']) : 'unavailable';
            $start_time = isset($slot['start_time']) ? $conn->real_escape_string($slot['start_time']) : '';
            $end_time   = isset($slot['end_time']) ? $conn->real_escape_string($slot['end_time']) : '';
            $place_name = isset($slot['place_name']) ? $conn->real_escape_string($slot['place_name']) : '';

            // Skip if place_name or times are empty
            if ($place_name === '' || $start_time === '' || $end_time === '') {
                continue;
            }

            $stmtWork = $conn->prepare("INSERT INTO work_place (doctor_id, status, day, start_time, end_time, place_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtWork->bind_param("isssss", $doctor_id, $status, $day, $start_time, $end_time, $place_name);
            $stmtWork->execute();
            $stmtWork->close();
        }
    }

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);

    // Close statements
    $stmtUser->close();
    $stmtDoc->close();
    $stmtDelete->close();
    $conn->close();
} catch (Exception $e) {
    if ($conn->errno) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
