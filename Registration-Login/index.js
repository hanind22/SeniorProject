// Listen for messages from parent window
    window.addEventListener('message', function(event) {
        if (event.data.action === 'showForm') {
            if (event.data.formType === 'signup') {
                document.getElementById('show-signup-btn').click();
            } else {
                document.getElementById('show-login-btn').click();
            }
        }
    });

    // When forms are submitted, notify parent window to close
    document.getElementById('login-form').addEventListener('submit', function() {
        window.parent.postMessage({ action: 'closeModal' }, '*');
    });

    document.getElementById('signup-form').addEventListener('submit', function() {
        window.parent.postMessage({ action: 'closeModal' }, '*');
    });

document.addEventListener('DOMContentLoaded', function() {
    // Form container and tab handling
    const formContainer = document.getElementById('formContainer');
    const showSignupBtn = document.getElementById('show-signup-btn');
    const showLoginBtn = document.getElementById('show-login-btn');
    const signupForm = document.getElementById('signup-form');
    
    // Initialize forms
    signupForm.style.display = 'block';
    
    // Tab click handlers
    showSignupBtn.addEventListener('click', function(e) {
        e.preventDefault();
        formContainer.classList.add('show-signup');
    });
    
    showLoginBtn.addEventListener('click', function(e) {
        e.preventDefault();
        formContainer.classList.remove('show-signup');
    });

    // Password toggle function
    window.togglePassword = function(inputId, element) {
        const input = document.getElementById(inputId);
        const icon = element.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    };
    
    // Initialize floating symbols
    function createRandomSymbols() {
        const symbols = document.querySelectorAll('.symbol');
        symbols.forEach(symbol => {
            const delay = Math.random() * 20;
            const duration = 15 + Math.random() * 15;
            symbol.style.animationDelay = `${delay}s`;
            symbol.style.animationDuration = `${duration}s`;
        });
    }
    createRandomSymbols();
    
    // Pre-fill login if user exists
    const storedUser = localStorage.getItem('medicareUser');
    if (storedUser) {
        try {
            const user = JSON.parse(storedUser);
            if (user.email) {
                document.getElementById('loginEmail').value = user.email;
            }
        } catch (e) {
            console.error('Error parsing stored user:', e);
        }
    }
    
    // Handle user type selection
    document.getElementById('userType').addEventListener('change', function() {
        const userType = this.value;
        const doctorFields = document.getElementById('doctorFields');
        const secretaryFields = document.getElementById('secretaryFields');
        
        // Hide all fields first
        doctorFields.classList.add('hidden-fields');
        secretaryFields.classList.add('hidden-fields');
        
        // Show relevant fields based on selection
        if (userType === 'doctor') {
            doctorFields.classList.remove('hidden-fields');
            document.getElementById('doctorSpecialty').required = true;
            document.getElementById('licenseNumber').required = true;
            document.getElementById('secretarySpecialty').required = false;
            document.getElementById('assignedDoctor').required = false;
        } 
        else if (userType === 'secretary') {
            secretaryFields.classList.remove('hidden-fields');
            document.getElementById('secretarySpecialty').required = true;
            document.getElementById('assignedDoctor').required = true;
            document.getElementById('doctorSpecialty').required = false;
            document.getElementById('licenseNumber').required = false;
        }
        else {
            // For patient, no additional fields needed
            document.getElementById('doctorSpecialty').required = false;
            document.getElementById('licenseNumber').required = false;
            document.getElementById('secretarySpecialty').required = false;
            document.getElementById('assignedDoctor').required = false;
        }
    });

    // Phone number validation
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9\-]/g, '');
        });
    }

    // Form submission validation
    signupForm.addEventListener('submit', function(e) {
        // Log all form data before submission
        const formData = new FormData(this);
        console.log('Form submission data:', Object.fromEntries(formData));
        
        // Additional validation can be added here if needed
        // For example, check if secretary has selected a doctor
        const userType = document.getElementById('userType').value;
        if (userType === 'secretary') {
            const assignedDoctor = document.getElementById('assignedDoctor').value;
            if (!assignedDoctor) {
                e.preventDefault();
                alert('Please select a doctor');
                return false;
            }
        }
        
        return true;
    });

    // Update doctors dropdown when specialty is selected
    document.getElementById('secretarySpecialty').addEventListener('change', async function() {
        const specialty = this.value;
        const doctorSelect = document.getElementById('assignedDoctor');
        
        // Clear existing options and show loading
        doctorSelect.innerHTML = '<option value="" selected disabled>Loading doctors...</option>';
        
        if (specialty) {
            try {
                console.log(`Fetching doctors for specialty: ${specialty}`);
                const response = await fetch('/fyp/Welcome/get_doctors.php?specialty=' + encodeURIComponent(specialty), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Invalid response format');
                }
                
                const doctors = await response.json();
                console.log('Received doctors:', doctors);
                
                // Clear and repopulate dropdown
                doctorSelect.innerHTML = '<option value="" selected disabled>Select doctor</option>';
                
                if (doctors.length > 0) {
                    doctors.forEach(doctor => {
                        const option = document.createElement('option');
                        option.value = doctor.user_id || doctor.doctor_id; // Handle both cases
                        option.textContent = doctor.full_name;
                        doctorSelect.appendChild(option);
                    });
                } else {
                    doctorSelect.innerHTML = '<option value="" selected disabled>No doctors found</option>';
                }
            } catch (error) {
                console.error('Error fetching doctors:', error);
                doctorSelect.innerHTML = `<option value="" selected disabled>Error: ${error.message}</option>`;
            }
        } else {
            doctorSelect.innerHTML = '<option value="" selected disabled>Select specialty first</option>';
        }
    });

    
});