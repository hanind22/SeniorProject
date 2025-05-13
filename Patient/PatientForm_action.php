<?php
session_start();
require_once('../db-config/connection.php');
require_once '../libs/phpqrcode/qrlib.php';  // Include QR code library

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        die("Error: User not logged in");
    }

    $userId = $_SESSION['user_id'];

    // Prepare data for Patients table
    $patientData = [
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'blood_type' => $_POST['blood_type'] ?? '',
        'allergies' => $_POST['allergies'] ?? 'None',
        'medical_conditions' => $_POST['medical_conditions'] ?? 'None',
        'current_medications' => $_POST['current_medications'] ?? 'None',
        'previous_surgeries' => $_POST['previous_surgeries'] ?? 'None',
        'family_history' => $_POST['family_history'] ?? 'None',
        'insurance_provider' => $_POST['insurance_provider'] ?? '',
        'insurance_number' => $_POST['insurance_number'] ?? '',
        'health_form_completed' => 1
    ];

    // Prepare data for Emergency_Contacts table
    $emergencyContactData = [
        'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
        'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? '',
        'emergency_contact_relationship' => $_POST['emergency_contact_relationship'] ?? ''
    ];

    try {
        $conn->begin_transaction();
        
        // Check if patient record exists
        $checkPatient = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
        $checkPatient->bind_param("i", $userId);
        if (!$checkPatient->execute()) {
    throw new Exception("Check patient failed: " . $checkPatient->error);
}
        $patientResult = $checkPatient->get_result();
        $patientExists = $patientResult->num_rows > 0;
        $patientId = 0;
        
        if ($patientExists) {
            // Get the existing patient_id
            $patientRow = $patientResult->fetch_assoc();
            $patientId = $patientRow['patient_id'];
            
            // Update existing patient
            $patientSql = "UPDATE patients SET 
                    date_of_birth = ?,
                    gender = ?,
                    blood_type = ?,
                    allergies = ?,
                    medical_conditions = ?,
                    current_medications = ?,
                    previous_surgeries = ?,
                    family_history = ?,
                    insurance_provider = ?,
                    insurance_number = ?,
                    health_form_completed = ?
                    WHERE user_id = ?";
                    
            $patientStmt = $conn->prepare($patientSql);
            $patientStmt->bind_param("sssssssssssi", 
                $patientData['date_of_birth'],
                $patientData['gender'],
                $patientData['blood_type'],
                $patientData['allergies'],
                $patientData['medical_conditions'],
                $patientData['current_medications'],
                $patientData['previous_surgeries'],
                $patientData['family_history'],
                $patientData['insurance_provider'],
                $patientData['insurance_number'],
                $patientData['health_form_completed'],
                $userId
            );
            
            $patientResult = $patientStmt->execute();
        } else {
            // Insert new patient
            $patientSql = "INSERT INTO patients (
                    user_id, date_of_birth, gender, blood_type, allergies, 
                    medical_conditions, current_medications, previous_surgeries, 
                    family_history, insurance_provider, insurance_number, health_form_completed
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
            $patientStmt = $conn->prepare($patientSql);
            $patientStmt->bind_param("issssssssssi", 
                $userId,
                $patientData['date_of_birth'],
                $patientData['gender'],
                $patientData['blood_type'],
                $patientData['allergies'],
                $patientData['medical_conditions'],
                $patientData['current_medications'],
                $patientData['previous_surgeries'],
                $patientData['family_history'],
                $patientData['insurance_provider'],
                $patientData['insurance_number'],
                $patientData['health_form_completed']
            );
            
            $patientResult = $patientStmt->execute();
            $patientId = $conn->insert_id;
        }
        
        if (!$patientResult) {
            throw new Exception("Patient data save failed: " . $patientStmt->error);
        }
        
        // Now handle the Emergency_Contacts table
        // First, make sure we have a valid patient_id
        if ($patientId <= 0) {
            throw new Exception("Invalid patient ID: " . $patientId);
        }
        
        $checkContact = $conn->prepare("SELECT contact_id FROM emergency_contacts WHERE patient_id = ?");
        $checkContact->bind_param("i", $patientId);
        $checkContact->execute();
        $contactResult = $checkContact->get_result();
        $contactExists = $contactResult->num_rows > 0;
        
        if ($contactExists) {
            // Update existing emergency contact
            $contactSql = "UPDATE emergency_contacts SET 
                    emergency_contact_name = ?,
                    emergency_contact_phone = ?,
                    emergency_contact_relationship = ?
                    WHERE patient_id = ?";
                    
            $contactStmt = $conn->prepare($contactSql);
            $contactStmt->bind_param("sssi", 
                $emergencyContactData['emergency_contact_name'],
                $emergencyContactData['emergency_contact_phone'],
                $emergencyContactData['emergency_contact_relationship'],
                $patientId
            );
        } else {
            // Insert new emergency contact
            $contactSql = "INSERT INTO emergency_contacts (
                    patient_id, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship
                ) VALUES (?, ?, ?, ?)";
                
            $contactStmt = $conn->prepare($contactSql);
            $contactStmt->bind_param("isss", 
                $patientId,
                $emergencyContactData['emergency_contact_name'],
                $emergencyContactData['emergency_contact_phone'],
                $emergencyContactData['emergency_contact_relationship']
            );
        }
        
        $contactResult = $contactStmt->execute();
        
        if (!$contactResult) {
            throw new Exception("Emergency contact save failed: " . $contactStmt->error);
        }
        
        // Define the patient ID for QR generator
        $_GET['patient_id'] = $patientId;
       include('qr_generator.php');

        

        $conn->commit();
        
        $_SESSION['health_form_completed'] = true;
        $_SESSION['success'] = "Health profile completed successfully!";
        header('Location: patient_dashboard.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        // More detailed error logging
        file_put_contents('form_debug.log', date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        $_SESSION['error'] = "Failed to save health information: " . $e->getMessage();
        header('Location: patient_dashboard.php');
        exit();

    }
}

?>