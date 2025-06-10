<?php
include('../db-config/connection.php');

// Get total doctors
$query = "SELECT COUNT(doctor_id) as totalDoctors FROM doctors";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$totalDoctors = $row['totalDoctors'];

// Get total patients
$query = "SELECT COUNT(patient_id) as totalPatients FROM patients";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$totalPatients = $row['totalPatients'];

// Get total specialties
$query = "SELECT COUNT(DISTINCT specialty) as totalSpecialties FROM doctors";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$totalSpecialties = $row['totalSpecialties'];

header('Content-Type: application/json');
echo json_encode([
    'totalDoctors' => $totalDoctors,
    'totalPatients' => $totalPatients,
    'totalSpecialties' => $totalSpecialties
]);
?>