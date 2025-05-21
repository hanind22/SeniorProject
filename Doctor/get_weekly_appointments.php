<?php
session_start();
header('Content-Type: application/json');
include('../db-config/connection.php');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode([0, 0, 0, 0, 0, 0, 0]);
    exit;
}

// Initialize all days to 0
$weeklyAppointments = array_fill(0, 7, 0); // Monday=0, Sunday=6

// Calculate start of week (Monday)
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

try {
    $stmt = $conn->prepare("
        SELECT DAYOFWEEK(appointment_date) AS weekday, COUNT(*) AS count
        FROM appointments
        WHERE doctor_id = (SELECT doctor_id FROM doctors WHERE user_id = ?)
          AND appointment_date BETWEEN ? AND ?
        GROUP BY weekday
    ");
    $stmt->bind_param("iss", $userId, $monday, $sunday);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // MySQL: Sunday = 1, Monday = 2, ..., Saturday = 7
        $weekday = (int)$row['weekday'];
        $index = ($weekday + 5) % 7; // Convert MySQL weekday to 0 (Mon) - 6 (Sun)
        $weeklyAppointments[$index] = (int)$row['count'];
    }

    echo json_encode($weeklyAppointments);
} catch (Exception $e) {
    echo json_encode([0, 0, 0, 0, 0, 0, 0]);
}
?>
