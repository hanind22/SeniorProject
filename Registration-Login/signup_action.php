<?php
session_start();
require_once('../db-config/connection.php');

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debugging setup
$debugLog = function($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('debug.log', "[$timestamp] $message\n", FILE_APPEND);
};

$debugLog("Script started. Request method: " . $_SERVER['REQUEST_METHOD']);
$debugLog("Received POST data: " . print_r($_POST, true));
$debugLog("Session data: " . print_r($_SESSION, true));

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate input
        $full_name = trim(filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_STRING));
        $email = trim(filter_input(INPUT_POST, 'Email', FILTER_SANITIZE_EMAIL));
        $password = $_POST['Password'] ?? '';
        $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $user_type = $_POST['userType'] ?? '';
        
        // Initialize specialty-specific variables
        $specialty = '';
        $license_number = '';
        $secretary_specialty = '';
        $assigned_doctor = '';

        // Get specialty-specific data
        if ($user_type === 'doctor') {
            $specialty = trim(filter_input(INPUT_POST, 'doctorSpecialty', FILTER_SANITIZE_STRING));
            $license_number = trim(filter_input(INPUT_POST, 'licenseNumber', FILTER_SANITIZE_STRING));
        } elseif ($user_type === 'secretary') {
            $secretary_specialty = trim(filter_input(INPUT_POST, 'secretarySpecialty', FILTER_SANITIZE_STRING));
            $assigned_doctor = trim(filter_input(INPUT_POST, 'assignedDoctor', FILTER_SANITIZE_STRING));
        }

        $debugLog("Processed input data:");
        $debugLog("Full Name: $full_name");
        $debugLog("Email: $email");
        $debugLog("Phone: $phone");
        $debugLog("User Type: $user_type");
        $debugLog("Specialty: $specialty");
        $debugLog("License: $license_number");
        $debugLog("Secretary Specialty: $secretary_specialty");
        $debugLog("Assigned Doctor: $assigned_doctor");

        // Validate inputs
        $errors = [];
        
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
        if (empty($password) || strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        if (empty($phone)) $errors[] = "Phone number is required";
        
        // Specialty-specific validation
        if ($user_type === 'doctor') {
            if (empty($specialty)) $errors[] = "Specialty is required for doctors";
            if (empty($license_number)) $errors[] = "License number is required for doctors";
        } elseif ($user_type === 'secretary') {
            if (empty($secretary_specialty)) $errors[] = "Doctor specialty is required for secretaries";
            if (empty($assigned_doctor)) $errors[] = "Assigned doctor is required for secretaries";
        }

        $debugLog("Validation errors: " . print_r($errors, true));

        // Check if email exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "Email already registered";
            $stmt->close();
        }

        // If errors, return to form
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            $debugLog("Redirecting back with errors");
            header("Location: index.php");
            exit();
        }

        // Begin transaction
        $conn->begin_transaction();
        $debugLog("Transaction started");

        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $debugLog("Password hashed");

            // Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, user_type) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("User prepare failed: " . $conn->error);
            }
            
            $uc_user_type = ucfirst($user_type); // Convert to proper case
            $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $phone, $uc_user_type);
            
            if (!$stmt->execute()) {
                throw new Exception("User insert failed: " . $stmt->error);
            }
            
            $user_id = $conn->insert_id;
            $stmt->close();
            $debugLog("User inserted with ID: $user_id");

            // Handle specific user types
            if ($user_type === 'doctor') {
                $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialty, license_number) VALUES (?, ?, ?)");
                if (!$stmt) throw new Exception("Doctor prepare failed: " . $conn->error);
                
                $stmt->bind_param("iss", $user_id, $specialty, $license_number);
                if (!$stmt->execute()) throw new Exception("Doctor insert failed: " . $stmt->error);
                
                $stmt->close();
                $debugLog("Doctor record inserted");
                
            } elseif ($user_type === 'secretary') {
                // Get doctor_id from assigned_doctor (which is user_id)
                $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
                if (!$stmt) throw new Exception("Doctor lookup prepare failed: " . $conn->error);
                
                $stmt->bind_param("i", $assigned_doctor);
                if (!$stmt->execute()) throw new Exception("Doctor lookup execute failed: " . $stmt->error);
                
                $result = $stmt->get_result();
                if ($result->num_rows === 0) throw new Exception("Doctor not found");
                
                $assigned_doctor_id = $result->fetch_assoc()['doctor_id'];
                $stmt->close();
                $debugLog("Found doctor ID: $assigned_doctor_id");
                
                // Insert secretary
                $stmt = $conn->prepare("INSERT INTO secretary (user_id, doctor_id) VALUES (?, ?)");
                if (!$stmt) throw new Exception("Secretary prepare failed: " . $conn->error);
                
                $stmt->bind_param("ii", $user_id, $assigned_doctor_id);
                if (!$stmt->execute()) throw new Exception("Secretary insert failed: " . $stmt->error);
                
                $stmt->close();
                $debugLog("Secretary record inserted");
                
            }elseif($user_type === 'admin'){

                 $stmt = $conn->prepare("INSERT INTO admins (user_id) VALUES (?)");
                if (!$stmt) throw new Exception("Admin prepare failed: " . $conn->error);
                
                $stmt->bind_param("i", $user_id);
                if (!$stmt->execute()) throw new Exception("Doctor insert failed: " . $stmt->error);
                
                $stmt->close();
                $debugLog("Admin record inserted");
    

            }
             else { // Patient
                $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                if (!$stmt) throw new Exception("Patient prepare failed: " . $conn->error);
                
                $stmt->bind_param("i", $user_id);
                if (!$stmt->execute()) throw new Exception("Patient insert failed: " . $stmt->error);
                
                $stmt->close();
                $debugLog("Patient record inserted");
            }

            // Commit transaction
            $conn->commit();
            $debugLog("Transaction committed");

            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['user_type'] = $uc_user_type;
            
            $debugLog("Session variables set. Registration successful for $email");

            // Determine redirect path
            $redirect_paths = [
                'doctor' => '../Doctor/doctor_dashboard.php',
                'secretary' => '../Secretary/secretary_dashboard.php',
                'patient' => '../Patient/patient_dashboard.php',
                'admin' => '../Admin/Admin_Dashboard.php'
            ];
            
            $redirect = $redirect_paths[strtolower($uc_user_type)] ?? 'index.php';
            $debugLog("Redirecting to: $redirect");
            
            header("Location: $redirect");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Registration failed: " . $e->getMessage();
            $debugLog($errorMsg);
            
            $_SESSION['error'] = $errorMsg;
            $_SESSION['form_data'] = $_POST;
            header("Location: index.php");
            exit();
        }

    } catch (Exception $e) {
        $errorMsg = "System error: " . $e->getMessage();
        $debugLog($errorMsg);
        
        $_SESSION['error'] = "A system error occurred. Please try again later.";
        header("Location: index.php");
        exit();
    }
} else {
    $debugLog("Invalid request method");
    header("Location: index.php");
    exit();
}
?>