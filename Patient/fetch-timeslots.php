<?php
include('../db-config/connection.php');

if (isset($_POST['doctor_id']) && isset($_POST['date'])) {
    $doctorId = $_POST['doctor_id'];
    $date = $_POST['date'];
    
    // Get doctor's working hours (this should be from your database)
    $workingHours = [
        'start' => '09:00:00',
        'end' => '17:00:00',
        'lunch_start' => '12:00:00',
        'lunch_end' => '13:00:00'
    ];
    
    // Generate time slots (30-minute intervals)
    $start = strtotime($workingHours['start']);
    $end = strtotime($workingHours['end']);
    $lunchStart = strtotime($workingHours['lunch_start']);
    $lunchEnd = strtotime($workingHours['lunch_end']);
    
    // Check existing appointments for this doctor on this date
    $query = $conn->prepare("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'
    ");
    $query->bind_param("is", $doctorId, $date);
    $query->execute();
    $result = $query->get_result();
    
    $bookedTimes = [];
    while ($row = $result->fetch_assoc()) {
        $bookedTimes[] = $row['appointment_time'];
    }
    
    echo '<div class="time-slot-grid">';
    
    for ($time = $start; $time <= $end; $time += 1800) { // 1800 seconds = 30 minutes
        // Skip lunch time
        if ($time >= $lunchStart && $time < $lunchEnd) {
            continue;
        }
        
        $timeFormatted = date('H:i:s', $time);
        $displayTime = date('g:i A', $time);
        
        // Check if this time is already booked
        $isBooked = in_array($timeFormatted, $bookedTimes);
        
        echo '<div class="time-slot ' . ($isBooked ? 'booked' : '') . '" data-time="' . $timeFormatted . '">';
        echo $displayTime;
        if ($isBooked) {
            echo '<div class="slot-status">Booked</div>';
        }
        echo '</div>';
    }
    
    echo '</div>';
} else {
    echo '<p style="text-align: center; color: red; padding: 1rem;">Doctor or date not specified.</p>';
}
?>