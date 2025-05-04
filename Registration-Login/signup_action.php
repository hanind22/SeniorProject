<?php

session_start();
require_once('../db-config/connection.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug received data
file_put_contents('debug.log', "Received POST data:\n" . print_r($_POST, true) . "\n", FILE_APPEND);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data with proper fallbacks
    $full_name = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $password = $_POST['Password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $_POST['userType'] ?? '';
    
    // Initialize doctor-specific variables
    $specialty = '';
    $license_number = '';
    
    // Get doctor-specific data if applicable
    if ($user_type === 'doctor') {
        $specialty = trim($_POST['doctorSpecialty'] ?? '');
        $license_number = trim($_POST['licenseNumber'] ?? '');
    }

    // Validate inputs
    $errors = [];
    
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($password) || strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($phone)) $errors[] = "Phone number is required";
    
    if ($user_type === 'doctor') {
        if (empty($specialty)) $errors[] = "Specialty is required for doctors";
        if (empty($license_number)) $errors[] = "License number is required for doctors";
    }

    // Check if email exists only if no errors so far
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) $errors[] = "Email already registered";
        $stmt->close();
    }

    file_put_contents('debug.log', "Validation errors:\n" . print_r($errors, true) . "\n", FILE_APPEND);

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            $conn->begin_transaction();
            
            // Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, user_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $phone, $user_type);
            
            if (!$stmt->execute()) {
                throw new Exception("User insert failed: " . $stmt->error);
            }
            
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // If doctor, insert into doctors table
            if ($user_type === 'doctor') {
                $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialty, license_number) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $specialty, $license_number);
                
                if (!$stmt->execute()) {
                    throw new Exception("Doctor insert failed: " . $stmt->error);
                }
                $stmt->close();
            } else {
                // If patient, insert into patients table
                $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                $stmt->bind_param("i", $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Patient insert failed: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['user_type'] = $user_type;
            
            file_put_contents('debug.log', "Registration successful for $email\n", FILE_APPEND);
            
            // Redirect
            header("Location: " . ($user_type === 'doctor' ? '../Doctor/doctor_dashboard.php' : '../Patient/patient_dashboard.php'));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            file_put_contents('debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
            $_SESSION['error'] = "Registration failed. Please try again.";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>