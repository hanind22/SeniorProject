<?php
header('Content-Type: application/json'); // Important

require '../db-config/connection.php'; // if needed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = $_POST['patient_id'] ?? 0;
    $doctorId = $_POST['doctor_id'] ?? 0;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $appointment_type = $_POST['appointment_type'] ?? '';

    if (!$patientId || !$doctorId || !$date || !$time || !$purpose) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $timeFormatted = date("H:i:s", strtotime($time));

        $checkStmt = $conn->prepare("
            SELECT appointment_id FROM appointments
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'
        ");
        $checkStmt->bind_param("iss", $doctorId, $date, $timeFormatted);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This time slot is no longer available']);
            exit;
        }

        $insertStmt = $conn->prepare("
            INSERT INTO appointments (
                patient_id,
                doctor_id,
                appointment_date,
                appointment_time,
                appointment_type,
                reason_for_visit,
                notes,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
        ");
        $insertStmt->bind_param("iisssss", $patientId, $doctorId, $date, $timeFormatted, $appointment_type, $purpose, $notes);

        if ($insertStmt->execute()) {
            $doctorName = 'the doctor';
            $doctorQuery = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
            $doctorQuery->bind_param("i", $doctorId);
            $doctorQuery->execute();
            $result = $doctorQuery->get_result();
            if ($result->num_rows > 0) {
                $doctorRow = $result->fetch_assoc();
                $doctorName = 'Dr. ' . $doctorRow['full_name'];
            }

            $formattedDate = date("Y-m-d", strtotime($date));
            $formattedTime = date("g:i A", strtotime($time));

            $message = "You have successfully booked an appointment with $doctorName on $formattedDate at $formattedTime.";

            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error booking appointment']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>
