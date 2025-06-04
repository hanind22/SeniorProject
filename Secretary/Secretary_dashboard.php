<?php
session_start();
include('../db-config/connection.php');

// Initialize data
$secretaryData = [];
$doctorData = [];
$patients = [];
$error = '';
$doctor_id = null;
$search_query = '';
$totalPatients = 0;
$appointmentsToday = 0;
$urgentCases = 0;
$ageDistribution = [
    '0-17' => 0,
    '18-24' => 0,
    '25-34' => 0,
    '35-44' => 0,
    '45-54' => 0,
    '55-64' => 0,
    '65+' => 0
];

// Handle search functionality
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

try {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        // Get secretary's basic info from users table
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $secretaryData = $result->fetch_assoc();
        $stmt->close();

        if (!$secretaryData) {
            $error = "Secretary record not found";
        } else {
            // Get the doctor_id assigned to this secretary
            $stmt = $conn->prepare("SELECT doctor_id FROM secretary WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $secretaryInfo = $result->fetch_assoc();
            $stmt->close();

            if ($secretaryInfo && isset($secretaryInfo['doctor_id'])) {
                $doctor_id = $secretaryInfo['doctor_id'];
                
                // Get doctor's details for display
                $stmt = $conn->prepare("
                    SELECT u.*, d.* 
                    FROM users u
                    JOIN doctors d ON u.user_id = d.user_id
                    WHERE d.doctor_id = ?
                ");
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $doctorData = $result->fetch_assoc();
                $stmt->close();

                if ($doctorData) {
                    // Get statistics for the dashboard
                    // Total patients
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total 
                        FROM doctorpatient 
                        WHERE doctor_id = ?
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $patientCount = $result->fetch_assoc();
                    $totalPatients = $patientCount['total'];
                    $stmt->close();

                    // Today's appointments
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS count 
                        FROM appointments 
                        WHERE doctor_id = ? AND DATE(appointment_date) = ?
                    ");
                    $stmt->bind_param("is", $doctor_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $apptCount = $result->fetch_assoc();
                    $appointmentsToday = $apptCount['count'];
                    $stmt->close();

                    // Urgent cases
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS count 
                        FROM appointments 
                        WHERE doctor_id = ? AND appointment_type = 'Urgent Care'
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $urgentCount = $result->fetch_assoc();
                    $urgentCases = $urgentCount['count'];
                    $stmt->close();

                    // Get age distribution data for the chart
                    $stmt = $conn->prepare("
                        SELECT p.date_of_birth
                        FROM DoctorPatient dp
                        JOIN patients p ON dp.patient_id = p.patient_id
                        WHERE dp.doctor_id = ? AND p.date_of_birth IS NOT NULL
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        if ($row['date_of_birth']) {
                            $dob = new DateTime($row['date_of_birth']);
                            $now = new DateTime();
                            $age = $dob->diff($now)->y;
                            
                            // Categorize by age group
                            if ($age < 18) {
                                $ageDistribution['0-17']++;
                            } elseif ($age >= 18 && $age <= 24) {
                                $ageDistribution['18-24']++;
                            } elseif ($age >= 25 && $age <= 34) {
                                $ageDistribution['25-34']++;
                            } elseif ($age >= 35 && $age <= 44) {
                                $ageDistribution['35-44']++;
                            } elseif ($age >= 45 && $age <= 54) {
                                $ageDistribution['45-54']++;
                            } elseif ($age >= 55 && $age <= 64) {
                                $ageDistribution['55-64']++;
                            } else {
                                $ageDistribution['65+']++;
                            }
                        }
                    }
                    $stmt->close();

                    // Patient list query (only if we're on the patients page)
                    if (basename($_SERVER['PHP_SELF']) == 'patients.php') {
                        // Base query for patients
                        $patient_query = "
                            SELECT 
                                p.patient_id,
                                u.user_id,
                                u.full_name,
                                u.email,
                                u.phone_number,
                                p.date_of_birth,
                                p.gender,
                                p.blood_type,
                                p.allergies,
                                p.medical_conditions,
                                p.current_medications,
                                p.previous_surgeries,
                                p.family_history,
                                p.QR_code,
                                p.health_form_completed,
                                p.insurance_provider,
                                MAX(a.appointment_date) as last_visit_date
                            FROM DoctorPatient dp
                            JOIN patients p ON dp.patient_id = p.patient_id
                            JOIN users u ON p.user_id = u.user_id
                            LEFT JOIN appointments a ON p.patient_id = a.patient_id
                            WHERE dp.doctor_id = ?
                        ";
                        
                        // Add search condition if search query exists
                        if (!empty($search_query)) {
                            $patient_query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
                        }
                        
                        $patient_query .= "
                            GROUP BY 
                                p.patient_id, u.user_id, u.full_name, u.email, u.phone_number, 
                                p.date_of_birth, p.gender, p.blood_type, p.allergies, 
                                p.medical_conditions, p.current_medications, p.previous_surgeries, 
                                p.family_history, p.QR_code, p.health_form_completed, 
                                p.insurance_provider
                            ORDER BY u.full_name ASC
                        ";
                        
                        $stmt = $conn->prepare($patient_query);
                        
                        // Bind parameters based on whether we have a search query
                        if (!empty($search_query)) {
                            $search_param = "%$search_query%";
                            $stmt->bind_param("isss", $doctor_id, $search_param, $search_param, $search_param);
                        } else {
                            $stmt->bind_param("i", $doctor_id);
                        }
                        
                        $stmt->execute();
                        $patientResult = $stmt->get_result();
                        
                        if ($patientResult->num_rows > 0) {
                            while ($row = $patientResult->fetch_assoc()) {
                                // Calculate age from date_of_birth
                                if ($row['date_of_birth']) {
                                    $dob = new DateTime($row['date_of_birth']);
                                    $now = new DateTime();
                                    $age = $dob->diff($now)->y;
                                } else {
                                    $age = 'N/A';
                                }
                                
                                // Format last visit date
                                $last_visit_formatted = 'No visits';
                                if ($row['last_visit_date']) {
                                    $last_visit = new DateTime($row['last_visit_date']);
                                    $last_visit_formatted = $last_visit->format('M j, Y');
                                }
                                
                                // Add calculated fields to patient data
                                $row['age'] = $age;
                                $row['last_visit_formatted'] = $last_visit_formatted;
                                $patients[] = $row;
                            }
                        } else {
                            $error = "No patients found" . (!empty($search_query) ? " matching your search criteria" : "");
                        }
                    }
                } else {
                    $error = "Doctor record not found";
                }
            } else {
                $error = "No doctor assigned to this secretary";
            }
        }
    } else {
        $error = "User not logged in";
        header('Location: ../Register-Login/index.php');
        exit();
    }
} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

// Convert data to JSON for JavaScript
$ageDistributionJson = json_encode($ageDistribution);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            height: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .chart-container:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .chart-content {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        
        .chart-wrapper {
            height: 100px;
            margin: 0 auto;
        }
        
        .chart-title {
            text-align: center;
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .chart-title i {
            color: #4E79A7;
            font-size: 1.3rem;
        }
        
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            padding: 5px 10px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .no-data-message {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px 20px;
            font-size: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .no-data-message i {
            font-size: 2rem;
            color: #aaa;
        }
        
        /* Charts Grid Layout */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        /* Responsive adjustments */
        @media (min-width: 768px) {
            .chart-wrapper {
                height: 350px;
            }
            
            .chart-title {
                font-size: 1.3rem;
            }
            
            .legend-item {
                font-size: 0.85rem;
            }
        }
        
        @media (min-width: 992px) {
            .chart-wrapper {
                height: 400px;
            }
        }
        
        .content {
            flex: 1;
            padding: 30px;
            width: 100%;
            float: left;
            box-sizing: border-box;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .doctor-info-header {
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Side-Navigationbar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat me-2"></i> MediTrack
                </div>
                <p class="speciality">Your Health Journey Starts Here</p>
            </div>
            <nav class="nav-links">
                <a href="Secretary_dashboard.php" class="nav-item active">
                    <i class="fa-solid fa-user"></i> Profile
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="fa-solid fa-file-medical"></i> Patients
                </a> 
                <a href="appointments.php" class="nav-item">
                    <i class="fa-solid fa-calendar"></i> Appointments
                </a>
            </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <div class="content">
            <div class="doctor-info-header">
                <h2>Secretary: <?php echo htmlspecialchars($secretaryData['full_name'] ?? 'N/A'); ?></h2>
                <?php if (!empty($doctorData)): ?>
                    <p>Assigned to Dr. <?php echo htmlspecialchars($doctorData['full_name']); ?> | <?php echo htmlspecialchars($doctorData['specialty'] ?? 'N/A'); ?> </p>
                <?php endif; ?>
            </div>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h3><i class="fas fa-users"></i> Total Patients</h3>
                    <p><?php echo $totalPatients; ?></p>
                </div>
                <div class="summary-card">
                    <h3><i class="fas fa-calendar-check"></i> Appointments Today</h3>
                    <p><?php echo $appointmentsToday; ?></p>
                </div>
                <div class="summary-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Urgent Cases</h3>
                    <p><?php echo $urgentCases; ?></p>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Age Distribution Chart - Pie Chart -->
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i> Patient Age Distribution
                    </div>
                    <?php if ($totalPatients > 0): ?>
                        <div class="chart-content">
                            <div class="chart-wrapper">
                                <canvas id="ageDistributionChart"></canvas>
                            </div>
                            <div class="chart-legend" id="ageDistributionLegend"></div>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i> No patient data available for chart
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Date and time display
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('date-time').textContent = now.toLocaleDateString('en-US', options);
        }
        
        updateDateTime();
        setInterval(updateDateTime, 60000); // Update every minute

        // Age Distribution Chart - Pie Chart
        <?php if ($totalPatients > 0): ?>
        const ageData = <?php echo $ageDistributionJson; ?>;
        
        // Prepare data for chart
        const chartLabels = [];
        const chartData = [];
        // Color palette (medical theme)
        const chartColors = [
            '#456FBA', // Soft blue
            '#EEA736', // Soft orange
            '#DF4C68', // Soft coral
            '#4CDFC2', // Soft teal
            '#8CC926', // Soft green
            '#FFCA3A', // Soft yellow
            '#9B5DE5', // Soft purple
            '#F15BB5', // Soft pink
            '#A68A67', // Soft brown
            '#A5A5A5'  // Soft gray
        ];
        const backgroundColors = [];
        const borderColors = [];
        
        // Filter out age groups with 0 patients and prepare data
        let colorIndex = 0;
        for (const [ageGroup, count] of Object.entries(ageData)) {
            if (count > 0) {
                chartLabels.push(ageGroup);
                chartData.push(count);
                backgroundColors.push(chartColors[colorIndex % chartColors.length]);
                borderColors.push('#ffffff');
                colorIndex++;
            }
        }

        if (chartData.length > 0) {
            const ctx = document.getElementById('ageDistributionChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Number of Patients',
                        data: chartData,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1,
                        hoverBackgroundColor: backgroundColors.map(color => {
                            // Slightly darker version for hover effect
                            return Chart.helpers.color(color).darken(0.2).rgbString();
                        }),
                        hoverBorderColor: borderColors,
                        hoverBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = chartData.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.raw} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });

            // Create custom legend
            const legendContainer = document.getElementById('ageDistributionLegend');
            const total = chartData.reduce((a, b) => a + b, 0);
            
            chartLabels.forEach((label, index) => {
                const percentage = ((chartData[index] / total) * 100).toFixed(1);
                
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                
                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color';
                colorBox.style.backgroundColor = backgroundColors[index];
                
                const textSpan = document.createElement('span');
                textSpan.textContent = `${label} years: ${chartData[index]} (${percentage}%)`;
                
                legendItem.appendChild(colorBox);
                legendItem.appendChild(textSpan);
                legendContainer.appendChild(legendItem);
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>