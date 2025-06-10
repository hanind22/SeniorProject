<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Sidebar.css">
    <link rel="stylesheet" href="Style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Admin</title>
</head>
<body>
    <div class="container">
        <!-- Side-Navigationbar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat me-2"></i> MediTrack
                </div>
                <p class="speciality">Your Trusted Medical Hub</p>
            </div>
            <nav class="nav-links">
                <a href="MainPage.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> Log out
                </a>
                </nav>
            <div class="date-time-box">
                <p id="date-time"></p>
            </div>
        </aside>

        <div class="content">
           <div class="header">
               <div class="header-content">
                  <div>
                     <h1><i class="fas fa-hospital" style="margin-right: 10px;"></i> MediTrack Dashboard</h1>
                     <p>Manage Your Medical Staff Efficiently</p>
                  </div>
                  <div class="header-actions">
                      <button class="btn btn-secondary">
                          <i class="fas fa-bell"></i> Notifications
                      </button>
                  </div>
              </div>
           </div>

           <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="stat-text">
                        <div class="number" id="totalDoctors">0</div>
                        <div class="label">Total Doctors</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-text">
                        <div class="number" id="totalPatients">0</div>
                        <div class="label">Total Patients</div>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-text">
                        <div class="number" id="totalSpecialties">0</div>
                        <div class="label">Medical Specialties</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="controls">
                <button class="btn btn-primary" onclick="openAddDoctorModal()">
                    <i class="fas fa-plus"></i> Add New Doctor
                </button>
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search doctors by name, license, or specialty...">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>

            <div class="doctors-grid" id="doctorsGrid"></div>
        </div>
        </div>
    </div>
     <!-- Add Doctor Modal -->
    <div id="addDoctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-md"></i> Add New Doctor</h2>
                <span class="close" onclick="closeAddDoctorModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addDoctorForm">
                    <div class="form-group">
                        <label for="doctorName">Doctor Name</label>
                        <input type="text" id="doctorName" class="form-control" required placeholder="Enter doctor's full name">
                    </div>
                    <div class="form-group">
                        <label for="licenseNumber">License Number</label>
                        <input type="text" id="licenseNumber" class="form-control" required placeholder="Enter medical license number">
                    </div>
                    <div class="form-group">
                        <label for="specialty">Medical Specialty</label>
                        <select id="specialty" class="form-control" required>
                            <option value="">Select a specialty</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Neurology">Neurology</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="Pediatrics">Pediatrics</option>
                            <option value="Dermatology">Dermatology</option>
                            <option value="General Practice">General Practice</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddDoctorModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Doctor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Action completed successfully!</span>
    </div>

    <!-- Add this HTML right before the closing </body> tag -->
<div class="logout-overlay" id="logoutOverlay">
    <div class="logout-confirmation">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout ?</p>
        <div class="logout-buttons">
            <button class="logout-btn confirm-logout" id="confirmLogout">Yes, Logout</button>
            <button class="logout-btn cancel-logout" id="cancelLogout">Cancel</button>
        </div>
    </div>
</div>
    <script>

    document.addEventListener('DOMContentLoaded', function() {
    // Get the logout elements
    const logoutLink = document.querySelector('.nav-links .nav-item:last-child');
    const logoutOverlay = document.getElementById('logoutOverlay');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    // Show overlay when logout is clicked
    logoutLink.addEventListener('click', function(e) {
        e.preventDefault();
        logoutOverlay.classList.add('show');
    });

    // Hide overlay when cancel is clicked
    cancelLogout.addEventListener('click', function() {
        logoutOverlay.classList.remove('show');
    });

    // Handle actual logout
    confirmLogout.addEventListener('click', function() {
        // In a real implementation, this would redirect to your logout script
        window.location.href = '../Welcome/Index.php';
        
        // For demonstration, we'll just show an alert
        // alert('Logging out...');
        // logoutOverlay.classList.remove('show');
    });

    // Close overlay when clicking outside the confirmation box
    logoutOverlay.addEventListener('click', function(e) {
        if (e.target === logoutOverlay) {
            logoutOverlay.classList.remove('show');
        }
    });
});
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
            const dateTimeElement = document.getElementById('date-time');
            if (dateTimeElement) {
                dateTimeElement.textContent = now.toLocaleDateString('en-US', options);
            }
        }

        updateDateTime();
        setInterval(updateDateTime, 60000);

        // Global doctors array to store fetched data
        let doctors = [];
        let nextId = 1;

        // Function to fetch stats from database
        async function fetchStats() {
            try {
                const response = await fetch('get-stats.php');
                const data = await response.json();
                
                document.getElementById('totalDoctors').textContent = data.totalDoctors;
                document.getElementById('totalPatients').textContent = data.totalPatients;
                document.getElementById('totalSpecialties').textContent = data.totalSpecialties;
            } catch (error) {
                console.error('Error fetching stats:', error);
            }
        }

        // Function to fetch doctors from database
        async function fetchDoctors() {
    try {
        const response = await fetch('./get-doctors.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to fetch doctors');
        }
        
        doctors = result.data;
        if (doctors.length > 0) {
            nextId = Math.max(...doctors.map(d => d.id)) + 1;
        }
        renderDoctors();
    } catch (error) {
        console.error('Error fetching doctors:', error);
        showToast('Error loading doctors: ' + error.message, 'error');
    }
}

        // Function to render doctors
        function renderDoctors(doctorsToRender = doctors) {
            const grid = document.getElementById('doctorsGrid');
            
            if (doctorsToRender.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-md"></i>
                        <h3>No doctors found</h3>
                        <p>Try adjusting your search criteria or add a new doctor to get started.</p>
                        <button class="btn btn-primary" onclick="openAddDoctorModal()" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> Add Your First Doctor
                        </button>
                    </div>
                `;
                return;
            }

            grid.innerHTML = doctorsToRender.map((doctor, index) => `
                <div class="doctor-card fade-in" style="animation-delay: ${index * 0.1}s">
                    <div class="doctor-header">
                        <div class="doctor-info">
                            <h3 class="doctor-name"> Dr. ${doctor.name}</h3>
                            <span class="doctor-specialty">${doctor.specialty}</span>
                        </div>
                        <div class="doctor-details">
                            <div class="detail-row">
                                <span class="detail-label">License Number</span>
                                <span class="detail-value">${doctor.licenseNumber}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Experience</span>
                                <span class="detail-value">Since ${doctor.experience || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Department</span>
                                <span class="detail-value">${doctor.specialty}</span>
                            </div>
                        </div>
                        <div class="patient-count">
                            <i class="fas fa-users"></i>
                            Managing ${doctor.patientCount || 'N/A'} patients
                        </div>
                    </div>
                    <div class="doctor-actions">
                        <button class="btn btn-danger btn-sm" onclick="removeDoctor(${doctor.id})">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `).join('');
        }

async function removeDoctor(doctorId) {
    console.log("removeDoctor called with ID:", doctorId);
    console.log("Type of doctorId:", typeof doctorId);
    console.log("All doctors array:", doctors);
    
    // Find the doctor by id in the global doctors array
    const doctor = doctors.find(doc => Number(doc.id) === Number(doctorId));

    console.log("Found doctor:", doctor);
    
    if (!doctor) {
        console.error("Doctor not found in frontend array");
        showToast("Doctor not found", "error");
        return;
    }

    // Confirm removal, use raw name without "Dr." prefix
    const confirmDelete = confirm(`Are you sure you want to remove Dr. ${doctor.name} from the MediTrack system?`);
    
    if (confirmDelete) {
        try {
            console.log("Sending request to remove doctor with ID:", doctorId);
            
            const response = await fetch('remove-doctor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: doctorId })
            });

            console.log("Response status:", response.status);
            const result = await response.json();
            console.log("Response data:", result);

            if (result.success) {
                showToast(`Dr. ${doctor.name} was removed successfully!`);
                await fetchDoctors(); // Refresh the list after removal
                await fetchStats();   // Refresh stats too (optional)
            } else {
                console.error("Server error:", result);
                showToast(result.message || "Failed to remove doctor", "error");
            }
        } catch (error) {
            console.error("Error removing doctor:", error);
            showToast("Error removing doctor: " + error.message, "error");
        }
    }
}


        // Function to add a new doctor
        async function addDoctor(name, licenseNumber, specialty) {
            try {
                const response = await fetch('add-doctor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: name,
                        licenseNumber: licenseNumber,
                        specialty: specialty
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`${name} has been added successfully!`);
                    fetchDoctors(); // Refresh the doctors list
                    fetchStats(); // Refresh the stats
                } else {
                    showToast(result.message || 'Failed to add doctor', 'error');
                }
            } catch (error) {
                console.error('Error adding doctor:', error);
                showToast('Error adding doctor', 'error');
            }
        }



        function openAddDoctorModal() {
            document.getElementById('addDoctorModal').style.display = 'flex';
        }

        function closeAddDoctorModal() {
            document.getElementById('addDoctorModal').style.display = 'none';
            document.getElementById('addDoctorForm').reset();
        }

        function searchDoctors() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filteredDoctors = doctors.filter(doctor => 
                doctor.name.toLowerCase().includes(searchTerm) ||
                doctor.licenseNumber.toLowerCase().includes(searchTerm) ||
                doctor.specialty.toLowerCase().includes(searchTerm)
            );
            renderDoctors(filteredDoctors);
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('i');
            
            toast.className = `toast ${type}`;
            toastMessage.textContent = message;
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Event listeners
document.getElementById('addDoctorForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const nameInput = document.getElementById('doctorName');
    const licenseInput = document.getElementById('licenseNumber');
    const specialtyInput = document.getElementById('specialty');

    const doctorName = nameInput.value.trim();  // Do NOT prepend "Dr." here

    if (!doctorName) {
        showToast('Please enter the doctor\'s name.', 'error');
        return;
    }

    await addDoctor(doctorName, licenseInput.value.trim(), specialtyInput.value);
    closeAddDoctorModal();
});



        document.getElementById('searchInput').addEventListener('input', function () {
    const searchTerm = this.value.toLowerCase();
    const filtered = doctors.filter(doc => 
        doc.name.toLowerCase().includes(searchTerm) ||
        doc.licenseNumber.toLowerCase().includes(searchTerm) ||
        doc.specialty.toLowerCase().includes(searchTerm)
    );
    renderDoctors(filtered);
});


        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addDoctorModal');
            if (event.target === modal) {
                closeAddDoctorModal();
            }
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            fetchStats();
            fetchDoctors();
        });
    </script>
</body>
</html>