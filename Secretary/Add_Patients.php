<?php
session_start();
require_once('../db-config/connection.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for JSON response
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Debug received data
file_put_contents('debug.log', "Received POST data:\n" . print_r($_POST, true) . "\n", FILE_APPEND);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data with proper fallbacks
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone_number'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $blood_type = $_POST['blood_type'] ?? '';
        
        // Emergency contact info
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_relationship = trim($_POST['emergency_contact_relationship'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        
        // Insurance info
        $insurance_provider = trim($_POST['insurance_provider'] ?? '');
        $insurance_number = trim($_POST['insurance_number'] ?? '');
        
        // Medical info
        $allergies = trim($_POST['allergies'] ?? '');
        $medical_conditions = trim($_POST['medical_conditions'] ?? '');
        $current_medications = trim($_POST['current_medications'] ?? '');
        $previous_surgeries = trim($_POST['previous_surgeries'] ?? '');
        $family_history = trim($_POST['family_history'] ?? '');

        // Validate inputs
        $errors = [];
        
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
        if (empty($password) || strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        if (empty($phone)) $errors[] = "Phone number is required";
        if (empty($date_of_birth)) $errors[] = "Date of birth is required";
        if (empty($gender)) $errors[] = "Gender is required";
        if (empty($blood_type)) $errors[] = "Blood type is required";
        if (empty($emergency_contact_name)) $errors[] = "Emergency contact name is required";
        if (empty($emergency_contact_relationship)) $errors[] = "Emergency contact relationship is required";
        if (empty($emergency_contact_phone)) $errors[] = "Emergency contact phone is required";

        // Check if email exists only if no errors so far
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $errors[] = "Email already registered";
            }
            $stmt->close();
        }

        file_put_contents('debug.log', "Validation errors:\n" . print_r($errors, true) . "\n", FILE_APPEND);

        // If there are validation errors, return them
        if (!empty($errors)) {
            sendJsonResponse(false, implode(", ", $errors));
        }

        // If no errors, proceed with registration
        // Hash password using bcrypt
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($hashed_password === false) {
            throw new Exception("Password hashing failed");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, user_type) VALUES (?, ?, ?, ?, 'patient')");
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssss", $full_name, $email, $hashed_password, $phone);
        
        if (!$stmt->execute()) {
            throw new Exception("User insert failed: " . $stmt->error);
        }
        
        $user_id = $conn->insert_id;
        $stmt->close();
        
        // Insert into patients table (without emergency contact fields)
        $stmt = $conn->prepare("
            INSERT INTO patients (
                user_id, date_of_birth, gender, blood_type, 
                insurance_provider, insurance_number,
                allergies, medical_conditions, current_medications, 
                previous_surgeries, family_history, health_form_completed
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("issssssssss", 
            $user_id, $date_of_birth, $gender, $blood_type,
            $insurance_provider, $insurance_number,
            $allergies, $medical_conditions, $current_medications,
            $previous_surgeries, $family_history
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Patient insert failed: " . $stmt->error);
        }
        
        $patient_id = $conn->insert_id;
        $stmt->close();
        
        // Insert into emergency_contacts table
        $stmt = $conn->prepare("
            INSERT INTO emergency_contacts (
                patient_id, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone
            ) VALUES (?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isss", 
            $patient_id, $emergency_contact_name, $emergency_contact_relationship, $emergency_contact_phone
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Emergency contact insert failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        file_put_contents('debug.log', "Patient registration successful for $email with patient_id: $patient_id\n", FILE_APPEND);
        
        // Generate QR code (optional - you might want to do this separately)
        // generateQRCode($patient_id);
        
        // Return success response
        sendJsonResponse(true, "Patient registered successfully!", [
            'patient_id' => $patient_id,
            'user_id' => $user_id,
            'full_name' => $full_name
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction ?? false) {
            $conn->rollback();
        }
        
        file_put_contents('debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        sendJsonResponse(false, "Registration failed: " . $e->getMessage());
    }
    
} else {
    // If not POST request
    sendJsonResponse(false, "Invalid request method");
}

// Optional: Function to generate QR code
function generateQRCode($patient_id) {
    // You'll need to implement QR code generation here
    // This is just a placeholder
    try {
        // Example using a QR code library
        // $qr_data = "patient_id:" . $patient_id;
        // $qr_file = "../qrcodes/patient_" . $patient_id . ".png";
        // Generate QR code and save to file
        return true;
    } catch (Exception $e) {
        file_put_contents('debug.log', "QR Code generation failed: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
?>