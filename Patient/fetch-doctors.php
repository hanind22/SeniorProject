<?php
include('../db-config/connection.php');

if (isset($_POST['specialty'])) {
    $specialty = $_POST['specialty'];
    
    $query = $conn->prepare("
        SELECT d.doctor_id, u.full_name, d.specialty, u.email, u.phone_number
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        WHERE d.specialty = ?
        ORDER BY u.full_name
    ");
    $query->bind_param("s", $specialty);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        echo '<div class="available-doctors">';
        echo '<h4>Available ' . htmlspecialchars($specialty) . ' Specialists</h4><br>';
    
        while ($doctor = $result->fetch_assoc()) {
            // Generate initials from name
            $initials = '';
            $nameParts = explode(' ', $doctor['full_name']);
            foreach ($nameParts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }

            // Output doctor card with full data-* attributes
            echo '<div 
                    class="doctor-card" 
                    data-doctor-id="' . htmlspecialchars($doctor['doctor_id']) . '"
                    data-name="' . htmlspecialchars($doctor['full_name']) . '"
                    data-specialty="' . htmlspecialchars($doctor['specialty']) . '"
                    data-email="' . htmlspecialchars($doctor['email']) . '"
                    data-phone="' . htmlspecialchars($doctor['phone_number']) . '"
                >';
            
            echo '<div class="doctor-avatar">' . $initials . '</div>';
            echo '<div class="doctor-info">';
            echo '<h4 class="doctor-name">' . htmlspecialchars($doctor['full_name']) . '</h4>';
            echo '<p class="doctor-specialty">' . htmlspecialchars($doctor['specialty']) . '</p>';
            echo '</div>'; // doctor-info
            echo '</div>'; // doctor-card
        }
        
        echo '</div>'; // available-doctors
    } else {
        echo '<p style="text-align: center; color: var(--text-medium); padding: 2rem;">No doctors found for this specialty. Please try another specialty.</p>';
    }
} else {
    echo '<p style="text-align: center; color: red; padding: 2rem;">Specialty not specified.</p>';
}
?>
