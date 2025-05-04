document.addEventListener('DOMContentLoaded', function() {
    const formContainer = document.getElementById('formContainer');
    const showSignupBtn = document.getElementById('show-signup-btn');
    const showLoginBtn = document.getElementById('show-login-btn');
    
    // Initialize forms - make sure signup form is visible for transitions
    document.getElementById('signup-form').style.display = 'block';
    
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
        const user = JSON.parse(storedUser);
        document.getElementById('loginEmail').value = user.email;
    }
    
})

document.addEventListener('DOMContentLoaded', function() {
    const userTypeSelect = document.getElementById('userType');
    const doctorFields = document.getElementById('doctorFields');
    
    if (userTypeSelect && doctorFields) {
        userTypeSelect.addEventListener('change', function() {
            if (this.value === 'doctor') {
                doctorFields.style.display = 'block';
                document.getElementById('doctorSpecialty').required = true;
                document.getElementById('licenseNumber').required = true;
            } else {
                doctorFields.style.display = 'none';
                document.getElementById('doctorSpecialty').required = false;
                document.getElementById('licenseNumber').required = false;
            }
        });
    }

    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9\-]/g, '');
        });
    }
});