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
$stmtPatient = $conn->prepare($query);
$stmtPatient->bind_param('i', $patientId); // 'i' for integer parameter
$stmtPatient->execute();
$resultPatient = $stmtPatient->get_result();
$patient = $resultPatient->fetch_assoc();
if (!$patient) die("Patient not found.");

// Directory for QR codes
$qrCodeDir = '../qrcodes/';
if (!file_exists($qrCodeDir)) {
    mkdir($qrCodeDir, 0755, true);
}

// File path for QR code
$fileName = 'patient_' . $patientId . '.png';
$filePath = $qrCodeDir . $fileName;

// QR code data - link to patient's details page
// Use server's IP address manually (or dynamically, see below)
$ip = getHostByName(getHostName()); // This gives the local IP of your computer
$patientDetailsURL = "http://$ip/fyp/Patient/patient_details.php?patient_id=" . $patientId;


// Generate QR code
QRcode::png($patientDetailsURL, $filePath, QR_ECLEVEL_L, 6);

// Save QR code path to database
$updateQuery = "UPDATE patients SET QR_code = ? WHERE patient_id = ?";
$stmtUpdate = $conn->prepare($updateQuery);
$relativePath = 'qrcodes/' . $fileName; // Relative path for web access
$stmtUpdate->bind_param('si', $relativePath, $patientId);
$stmtUpdate->execute();

// Fetch emergency contact (MySQLi)
$queryEmergency = "SELECT * FROM emergency_contacts WHERE patient_id = ?";
$stmtEmergency = $conn->prepare($queryEmergency);
$stmtEmergency->bind_param('i', $patientId);
$stmtEmergency->execute();
$resultEmergency = $stmtEmergency->get_result();
$contact = $resultEmergency->fetch_assoc();

// Display the QR code image
echo "<h3>Patient QR Code</h3>";
echo "<img src='$filePath' />";  // Show the QR code image

?>
