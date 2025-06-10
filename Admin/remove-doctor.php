<?php
header('Content-Type: application/json');
include('../db-config/connection.php');

$raw_input = file_get_contents('php://input');
error_log("RAW INPUT: " . $raw_input);

$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON received']);
    exit;
}

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Doctor ID not provided']);
    exit;
}

$doctor_id = intval($data['id']);
error_log("Looking for doctor_id: " . $doctor_id);

// Debug: Let's see what doctors exist in the database
$debug_query = "SELECT doctor_id, user_id FROM doctors";
$debug_result = $conn->query($debug_query);
$all_doctors = [];
while ($debug_row = $debug_result->fetch_assoc()) {
    $all_doctors[] = $debug_row;
}
error_log("All doctors in database: " . json_encode($all_doctors));

// Start transaction
$conn->begin_transaction();

try {
    // Get user_id from doctors table
    $stmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    error_log("Query result rows: " . $result->num_rows);

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Doctor not found',
            'debug' => [
                'searched_id' => $doctor_id,
                'all_doctors' => $all_doctors
            ]
        ]);
        $conn->rollback();
        exit;
    }

    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
    $stmt->close();

    error_log("Found doctor with user_id: " . $user_id);

    // Delete from doctors table first (child record)
    $stmt = $conn->prepare("DELETE FROM doctors WHERE doctor_id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $stmt->close();

    // Delete from users table (parent record)
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Commit the transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Doctor removed successfully']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error removing doctor: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>