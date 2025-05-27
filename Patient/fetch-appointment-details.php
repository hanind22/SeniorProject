<?php
session_start();
include('../db-config/connection.php');

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check for POST method and appointment ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointmentId = intval($_POST['appointment_id']);

    try {
        $query = $conn->prepare("
            SELECT 
                a.appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.appointment_type,
                a.reason_for_visit,
                a.notes,
                a.status,
                a.created_at,
                d.full_name AS doctor_name,
                p.full_name AS patient_name
            FROM appointments a
            JOIN users d ON a.doctor_id = d.user_id
            JOIN users p ON a.patient_id = p.user_id
            WHERE a.appointment_id = ?
        ");
        $query->bind_param("i", $appointmentId);
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows === 1) {
            $appointmentDetails = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $appointmentDetails]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
