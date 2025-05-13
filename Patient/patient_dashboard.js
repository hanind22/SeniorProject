document.addEventListener('DOMContentLoaded', function() {
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
    setInterval(updateDateTime, 60000);
    
document.getElementById('download-id-card-btn').addEventListener('click', function () {
    const imagePath = new URL(document.getElementById('qr-image').getAttribute('src'), window.location.href).href;

    const link = document.createElement('a');
    link.href = imagePath;
    link.setAttribute('download', 'ID_Card_QR.png');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});


    
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('mobile-show');
    });
});


document.addEventListener('DOMContentLoaded', function() {
    // Form navigation logic
    const formSections = document.querySelectorAll('.form-section');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const formProgress = document.getElementById('formProgress');
    const currentStep = document.getElementById('currentStep');
    let currentSection = 0;

    // Initialize form
    showSection(currentSection);

    // Next button click handler
    nextBtn.addEventListener('click', function() {
        if (validateSection(currentSection)) {
            currentSection++;
            showSection(currentSection);
        }
    });

    // Previous button click handler
    prevBtn.addEventListener('click', function() {
        currentSection--;
        showSection(currentSection);
    });


    // Show current section and update navigation
    function showSection(index) {
        formSections.forEach((section, i) => {
            section.classList.toggle('active', i === index);
        });
        
        // Update button visibility
        prevBtn.disabled = index === 0;
        nextBtn.style.display = index === formSections.length - 1 ? 'none' : 'inline-flex';
        submitBtn.style.display = index === formSections.length - 1 ? 'inline-flex' : 'none';
        
        // Update progress
        const progressPercent = ((index + 1) / formSections.length) * 100;
        formProgress.style.width = progressPercent + '%';
        currentStep.textContent = index + 1;
    }

    // Validate current section
    function validateSection(index) {
        const currentSection = formSections[index];
        const inputs = currentSection.querySelectorAll('[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                isValid = false;
            } else {
                input.classList.remove('error');
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields before proceeding.');
        }
        
        return isValid;
    }

    // Close modal button (disabled to force completion)
    document.getElementById('closeModalBtn').addEventListener('click', function() {
        alert('Please complete your health profile to access the dashboard.');
    });
});
// Update Info Btn
        document.addEventListener('DOMContentLoaded', function() {
            const updateProfileBtn = document.getElementById('update-profile-btn');
            const updateFormModal = document.getElementById('updateFormModal');
            const closeUpdateForm = document.getElementById('closeUpdateForm');
            const cancelUpdateBtn = document.getElementById('cancelUpdateBtn');
            
            // Show update form when button is clicked
            updateProfileBtn.addEventListener('click', function() {
                updateFormModal.style.display = 'flex';
            });
            
            // Close update form
            function closeUpdateModal() {
                updateFormModal.style.display = 'none';
            }
            
            closeUpdateForm.addEventListener('click', closeUpdateModal);
            cancelUpdateBtn.addEventListener('click', closeUpdateModal);
            
            // Close modal when clicking outside the form
            updateFormModal.addEventListener('click', function(e) {
                if (e.target === updateFormModal) {
                    closeUpdateModal();
                }
            });

        });
