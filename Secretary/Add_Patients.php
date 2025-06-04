<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();
header('Content-Type: application/json');

require_once('../db-config/connection.php');
require_once '../libs/phpqrcode/qrlib.php';

// Verify database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => "Database connection failed"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify secretary is logged in
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User not logged in");
        }

        // Get doctor_id from secretary table
        $stmt = $conn->prepare("SELECT doctor_id FROM secretary WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Secretary not assigned to any doctor");
        }
        
        $row = $result->fetch_assoc();
        $doctor_id = $row['doctor_id'];
        $stmt->close();

        // Rest of your validation and processing code...
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

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        if (strlen($data['password']) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }

        if (!empty($errors)) {
            throw new Exception(implode(", ", $errors));
        }

        // Check email uniqueness
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already registered");
        }
        $stmt->close();

        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        if (!$hashed_password) {
            throw new Exception("Password hashing failed");
        }

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

            // Insert into patients
            $stmt = $conn->prepare("INSERT INTO patients (
                user_id, date_of_birth, gender, blood_type, 
                insurance_provider, insurance_number,
                allergies, medical_conditions, current_medications, 
                previous_surgeries, family_history, health_form_completed
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            
            $insurance_provider = $_POST['insurance_provider'] ?? '';
            $insurance_number = $_POST['insurance_number'] ?? '';
            $allergies = $_POST['allergies'] ?? '';
            $medical_conditions = $_POST['medical_conditions'] ?? '';
            $current_medications = $_POST['current_medications'] ?? '';
            $previous_surgeries = $_POST['previous_surgeries'] ?? '';
            $family_history = $_POST['family_history'] ?? '';

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
                

            // Create doctor-patient relationship if not already exists
           $stmt = $conn->prepare("SELECT * FROM doctorpatient WHERE doctor_id = ? AND patient_id = ?");
           $stmt->bind_param("ii", $doctor_id, $patient_id);
           $stmt->execute();
           $result = $stmt->get_result();

           if ($result->num_rows > 0) {
              // Relationship already exists, do not insert again
              throw new Exception("Doctor-patient relationship already exists");
            }
            else{
            // Relationship does not exist, insert into doctorpatient table
            $stmt = $conn->prepare("INSERT INTO doctorpatient (doctor_id, patient_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $doctor_id, $patient_id);
            if (!$stmt->execute()) {
               throw new Exception("Doctor-patient relationship failed: ".$stmt->error);
            }}
            $stmt->close();

            // Generate and save QR code
            $qrCodePath = generateAndSaveQRCode($patient_id, $conn);

            // Commit transaction
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => "Patient registered successfully",
                'data' => [
                    'patient_id' => $patient_id,
                    'qr_code_path' => $qrCodePath
                ]
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => "Registration failed: ".$e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Invalid request method"]);
}

function generateAndSaveQRCode($patientId, $conn) {
    $qrDir = '../qrcodes/';
    if (!file_exists($qrDir)) {
        mkdir($qrDir, 0755, true);
    }

    $fileName = 'patient_'.$patientId.'.png';
    $filePath = $qrDir.$fileName;
    $relativePath = 'qrcodes/'.$fileName;

    // QR code data - link to patient's details page
    // Use server's IP address manually (or dynamically, see below)
    $ip = getHostByName(getHostName()); // This gives the local IP of your computer
    $url = "http://$ip/fyp/Patient/patient_details.php?patient_id=" . $patientId;

    QRcode::png($url, $filePath, QR_ECLEVEL_L, 6);

    $stmt = $conn->prepare("UPDATE patients SET QR_code = ? WHERE patient_id = ?");
    $stmt->bind_param('si', $relativePath, $patientId);
    $stmt->execute();
    $stmt->close();

    return $relativePath;
}
?>