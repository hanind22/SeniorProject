<?php
// Enable maximum error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/php_errors.log');

ob_start();
session_start();
header('Content-Type: application/json');

require_once('../db-config/connection.php');
require_once '../libs/phpqrcode/qrlib.php';

function debugLog($message, $data = null) {
    $log = "[".date('Y-m-d H:i:s')."] $message\n";
    if ($data) $log .= print_r($data, true)."\n";
    file_put_contents('debug.log', $log, FILE_APPEND);
    echo "DEBUG: $message\n";
    if ($data) echo "DATA: ".print_r($data, true)."\n";
}

function sendJsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'debug' => ob_get_clean()
    ];
    echo json_encode($response);
    exit();
}

// Verify database connection
if (!$conn) {
    sendJsonResponse(false, "Database connection failed");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Extract and validate all input data
        $required = [
            'full_name', 'email', 'password', 'phone_number',
            'date_of_birth', 'gender', 'blood_type',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone'
        ];
        
        $errors = [];
        $data = [];
        
        foreach ($required as $field) {
            $data[$field] = trim($_POST[$field] ?? '');
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field))." is required";
            }
        }

        // Additional validations
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (strlen($data['password']) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }

        if (!empty($errors)) {
            sendJsonResponse(false, implode(", ", $errors));
        }

        // Check email uniqueness
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            sendJsonResponse(false, "Email already registered");
        }
        $stmt->close();

        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        if (!$hashed_password) {
            throw new Exception("Password hashing failed");
        }

        // Verify doctor exists
        $doctor_id = $_SESSION['doctor_id'] ?? null;
        if (!$doctor_id) {
            throw new Exception("Doctor ID not found in session");
        }

        $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Invalid doctor ID");
        }
        $stmt->close();

        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert into users
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, user_type) 
                                  VALUES (?, ?, ?, ?, 'patient')");
            $stmt->bind_param("ssss", $data['full_name'], $data['email'], $hashed_password, $data['phone_number']);
            if (!$stmt->execute()) {
                throw new Exception("User insert failed: ".$stmt->error);
            }
            $user_id = $conn->insert_id;
            $stmt->close();

            // Assign optional fields to variables (required fix for bind_param)
            $insurance_provider = $_POST['insurance_provider'] ?? '';
            $insurance_number = $_POST['insurance_number'] ?? '';
            $allergies = $_POST['allergies'] ?? '';
            $medical_conditions = $_POST['medical_conditions'] ?? '';
            $current_medications = $_POST['current_medications'] ?? '';
            $previous_surgeries = $_POST['previous_surgeries'] ?? '';
            $family_history = $_POST['family_history'] ?? '';

            // Insert into patients
            $stmt = $conn->prepare("INSERT INTO patients (
                user_id, date_of_birth, gender, blood_type, 
                insurance_provider, insurance_number,
                allergies, medical_conditions, current_medications, 
                previous_surgeries, family_history, health_form_completed
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            
            $stmt->bind_param("issssssssss", 
                $user_id, $data['date_of_birth'], $data['gender'], $data['blood_type'],
                $insurance_provider, $insurance_number, $allergies, $medical_conditions,
                $current_medications, $previous_surgeries, $family_history
            );

            if (!$stmt->execute()) {
                throw new Exception("Patient insert failed: ".$stmt->error);
            }
            $patient_id = $conn->insert_id;
            $stmt->close();

            // Insert emergency contact
            $stmt = $conn->prepare("INSERT INTO emergency_contacts (
                patient_id, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone
            ) VALUES (?, ?, ?, ?)");
            
            $stmt->bind_param("isss", 
                $patient_id, $data['emergency_contact_name'], 
                $data['emergency_contact_relationship'], $data['emergency_contact_phone']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Emergency contact insert failed: ".$stmt->error);
            }
            $stmt->close();

            // Create doctor-patient relationship
            $stmt = $conn->prepare("INSERT INTO doctorpatient (doctor_id, patient_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $doctor_id, $patient_id);
            if (!$stmt->execute()) {
                throw new Exception("Doctor-patient relationship failed: ".$stmt->error);
            }
            $stmt->close();

            // Generate and save QR code
            $qrCodePath = generateAndSaveQRCode($patient_id, $conn);

            // Commit transaction
            $conn->commit();

            sendJsonResponse(true, "Patient registered successfully", [
                'patient_id' => $patient_id,
                'qr_code_path' => $qrCodePath
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        sendJsonResponse(false, "Registration failed: ".$e->getMessage());
    }
} else {
    sendJsonResponse(false, "Invalid request method");
}

function generateAndSaveQRCode($patientId, $conn) {
    $qrDir = '../qrcodes/';
    if (!file_exists($qrDir)) {
        mkdir($qrDir, 0755, true);
    }

    $fileName = 'patient_'.$patientId.'.png';
    $filePath = $qrDir.$fileName;
    $relativePath = 'qrcodes/'.$fileName;

    $url = "http://yourdomain.com/patient_details.php?patient_id=".$patientId;
    QRcode::png($url, $filePath, QR_ECLEVEL_L, 6);

    $stmt = $conn->prepare("UPDATE patients SET QR_code = ? WHERE patient_id = ?");
    $stmt->bind_param('si', $relativePath, $patientId);
    $stmt->execute();
    $stmt->close();

    return $relativePath;
}
?>
