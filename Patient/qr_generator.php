<?php
session_start();
require_once '../libs/phpqrcode/qrlib.php'; 
require_once('../db-config/connection.php'); // This includes $conn for MySQLi

// Get the patient ID (from URL)
$patientId = $_GET['patient_id'] ?? null;
if (!$patientId) die("No patient ID provided.");

// Fetch patient and user info (MySQLi)
$query = "
    SELECT p.*, u.full_name 
    FROM patients p 
    JOIN users u ON p.user_id = u.user_id 
    WHERE p.patient_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $patientId); // 'i' for integer parameter
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
if (!$patient) die("Patient not found.");

// Fetch emergency contact (MySQLi)
$query = "SELECT * FROM emergency_contacts WHERE patient_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $patientId);
$stmt->execute();
$result = $stmt->get_result();
$contact = $result->fetch_assoc();


// QR code data now contains just a link to the patient's details page
$patientDetailsURL = "http://192.168.1.2/fyp/Patient/patient_details.php?patient_id=" . $patientId;

$filePath = '../qrcodes/patient_' . $patientId . '.png';
QRcode::png($patientDetailsURL, $filePath, QR_ECLEVEL_L, 6); // Lower error correction and higher size

echo "<h3>Patient QR Code</h3>";
echo "<img src='$filePath' />";  // Show the QR code image

?>
