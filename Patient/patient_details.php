<?php 
// db connection (MySQLi)
require_once('../db-config/connection.php');

// Get patient_id from the URL query parameter
$patientId = $_GET['patient_id'] ?? null;
if (!$patientId) die("No patient ID provided.");

// Fetch patient info from the database (MySQLi version)
$query = "SELECT p.*, u.full_name FROM patients p JOIN users u ON p.user_id = u.user_id WHERE p.patient_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patientId); // "i" for integer
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

// Fetch emergency contact info (MySQLi version)
$query = "SELECT * FROM emergency_contacts WHERE patient_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patientId); // "i" for integer
$stmt->execute();
$result = $stmt->get_result();
$contact = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card {
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .card h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .patient-info, .emergency-contact {
            margin-bottom: 20px;
        }
        .patient-info p, .emergency-contact p {
            margin: 5px 0;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>Patient Details</h2>
        
        <!-- Patient Info -->
        <div class="patient-info">
            <h3>Patient Information</h3>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></p>
            <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient['date_of_birth']); ?></p>
            <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($patient['blood_type']); ?></p>
            <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies']); ?></p>
            <p><strong>Medical Conditions:</strong> <?php echo htmlspecialchars($patient['medical_conditions']); ?></p>
            <p><strong>Current Medications:</strong> <?php echo htmlspecialchars($patient['current_medications']); ?></p>
            <p><strong>Previous Surgeries:</strong> <?php echo htmlspecialchars($patient['previous_surgeries']); ?></p>
            <p><strong>Insurance Provider:</strong> <?php echo htmlspecialchars($patient['insurance_provider']); ?></p>
            <p><strong>Insurance Number:</strong> <?php echo htmlspecialchars($patient['insurance_number']); ?></p>
        </div>

        <!-- Emergency Contact Info -->
        <div class="emergency-contact">
            <h3>Emergency Contact</h3>
            <p><strong>Contact Name:</strong> <?php echo htmlspecialchars($contact['emergency_contact_name']); ?></p>
            <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($contact['emergency_contact_phone']); ?></p>
            <p><strong>Relationship:</strong> <?php echo htmlspecialchars($contact['emergency_contact_relationship']); ?></p>
        </div>
    </div>
</div>

</body>
</html>
