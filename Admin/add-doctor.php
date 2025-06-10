<?php
include('../db-config/connection.php');

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Validate input data
if (!$data || !isset($data['name']) || !isset($data['licenseNumber']) || !isset($data['specialty'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Sanitize input data
$name = mysqli_real_escape_string($conn, trim($data['name']));
$licenseNumber = mysqli_real_escape_string($conn, trim($data['licenseNumber']));
$specialty = mysqli_real_escape_string($conn, trim($data['specialty']));

// Validate that fields are not empty
if (empty($name) || empty($licenseNumber) || empty($specialty)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Check if license number already exists
$checkQuery = "SELECT d.license_number, u.full_name FROM doctors d 
               JOIN users u ON d.user_id = u.user_id 
               WHERE d.license_number = '$licenseNumber'";
$checkResult = mysqli_query($conn, $checkQuery);

if (!$checkResult) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($checkResult) > 0) {
    $existingDoctor = mysqli_fetch_assoc($checkResult);
    echo json_encode([
        'success' => false, 
        'message' => 'License number already exists for doctor: ' . $existingDoctor['full_name']
    ]);
    exit;
}

// Check if doctor with same name and specialty already exists
$duplicateCheckQuery = "SELECT u.full_name, d.specialty FROM users u 
                        JOIN doctors d ON u.user_id = d.user_id 
                        WHERE TRIM(LOWER(u.full_name)) = TRIM(LOWER('$name')) 
                        AND TRIM(LOWER(d.specialty)) = TRIM(LOWER('$specialty'))";
$duplicateResult = mysqli_query($conn, $duplicateCheckQuery);

if (!$duplicateResult) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($duplicateResult) > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Doctor with same name and specialty already exists'
    ]);
    exit;
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Insert into users table with NULL email and password (to be filled during registration)
    $userQuery = "INSERT INTO users (full_name, user_type, email, password_hash, phone_number, created_at) 
                  VALUES ('$name', 'doctor', NULL, NULL, NULL, NOW())";
    
    if (!mysqli_query($conn, $userQuery)) {
        throw new Exception('Failed to create user: ' . mysqli_error($conn));
    }
    
    $user_id = mysqli_insert_id($conn);
    
    // Insert into doctors table
    $doctorQuery = "INSERT INTO doctors (user_id, license_number, specialty, approved, updated_at) 
                    VALUES ($user_id, '$licenseNumber', '$specialty', 0, NOW())";
    
    if (!mysqli_query($conn, $doctorQuery)) {
        throw new Exception('Failed to create doctor record: ' . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Doctor added successfully. They can now register using their credentials.',
        'doctor_id' => $user_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection
mysqli_close($conn);
?>