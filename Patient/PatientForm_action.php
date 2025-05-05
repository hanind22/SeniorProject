<?php
// Start session first
session_start();

require_once('../db-config/connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        die("Error: User not logged in");
    }

    $userId = $_SESSION['user_id'];
    
    // Map ALL form fields correctly (notice the exact names matching your form)
    $data = [
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'blood_type' => $_POST['blood_type'] ?? '',
        'allergies' => $_POST['allergies'] ?? 'None',
        'medical_conditions' => $_POST['medical_conditions'] ?? 'None',  // Changed from medicalConditions
        'current_medications' => $_POST['current_medications'] ?? 'None', // Changed from medications
        'previous_surgeries' => $_POST['previous_surgeries'] ?? 'None',  // Changed from surgeries
        'family_history' => $_POST['family_history'] ?? 'None',
        'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '', // Changed from emergencyName
        'emergency_contact_relationship' => $_POST['emergency_contact_relationship'] ?? '', // Changed from emergencyRelationship
        'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? '', // Changed from emergencyPhone
        'insurance_provider' => $_POST['insurance_provider'] ?? '',
        'insurance_number' => $_POST['insurance_number'] ?? '',
        'health_form_completed' => 1
    ];
    
    try {
        // First check if patient exists
        $check = $conn->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        
        if ($exists) {
            // UPDATE existing record
            $sql = "UPDATE patients SET 
                    date_of_birth = ?,
                    gender = ?,
                    blood_type = ?,
                    allergies = ?,
                    medical_conditions = ?,
                    current_medications = ?,
                    previous_surgeries = ?,
                    family_history = ?,
                    emergency_contact_name = ?,
                    emergency_contact_relationship = ?,
                    emergency_contact_phone = ?,
                    insurance_provider = ?,
                    insurance_number = ?,
                    health_form_completed = ?
                    WHERE user_id = ?";
        } else {
            // INSERT new record
            $sql = "INSERT INTO patients SET 
                    user_id = ?,
                    date_of_birth = ?,
                    gender = ?,
                    blood_type = ?,
                    allergies = ?,
                    medical_conditions = ?,
                    current_medications = ?,
                    previous_surgeries = ?,
                    family_history = ?,
                    emergency_contact_name = ?,
                    emergency_contact_relationship = ?,
                    emergency_contact_phone = ?,
                    insurance_provider = ?,
                    insurance_number = ?,
                    health_form_completed = ?";
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters based on operation
        if ($exists) {
            $bind = $stmt->bind_param("ssssssssssssssi", 
                $data['date_of_birth'],
                $data['gender'],
                $data['blood_type'],
                $data['allergies'],
                $data['medical_conditions'],
                $data['current_medications'],
                $data['previous_surgeries'],
                $data['family_history'],
                $data['emergency_contact_name'],
                $data['emergency_contact_relationship'],
                $data['emergency_contact_phone'],
                $data['insurance_provider'],
                $data['insurance_number'],
                $data['health_form_completed'],
                $userId
            );
        } else {
            $bind = $stmt->bind_param("isssssssssssss", 
                $userId,
                $data['date_of_birth'],
                $data['gender'],
                $data['blood_type'],
                $data['allergies'],
                $data['medical_conditions'],
                $data['current_medications'],
                $data['previous_surgeries'],
                $data['family_history'],
                $data['emergency_contact_name'],
                $data['emergency_contact_relationship'],
                $data['emergency_contact_phone'],
                $data['insurance_provider'],
                $data['insurance_number'],
                $data['health_form_completed']
            );
        }
        
        if (!$bind) {
            throw new Exception("Bind failed: " . $stmt->error);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            $_SESSION['health_form_completed'] = true;
            $_SESSION['success'] = "Health profile completed successfully!";
            header('Location: patient_dashboard.php');
            exit();
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        // Log detailed error
        file_put_contents('form_debug.log', "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        $_SESSION['error'] = "Failed to save health information. Please try again.";
        header('Location: patient_dashboard.php');
        exit();
    }
}
?>