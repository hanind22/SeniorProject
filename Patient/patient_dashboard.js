document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded and parsed');

    // ====================== Utility Functions ======================
    function showLoading(button) {
        if (!button) return;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        button.disabled = true;
    }

    function hideLoading(button) {
        if (!button || !button.dataset.originalText) return;
        button.innerHTML = button.dataset.originalText;
        button.disabled = false;
    }

    // ====================== Date Time Updater ======================
    function updateDateTime() {
        try {
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
        } catch (error) {
            console.error('Error updating date/time:', error);
        }
    }

    // Initialize and update every minute
    updateDateTime();
    setInterval(updateDateTime, 60000);

    // ====================== Download ID Card ======================
    const downloadIdCardBtn = document.getElementById('download-id-card-btn');
    if (downloadIdCardBtn) {
        downloadIdCardBtn.addEventListener('click', function() {
            try {
                const qrImage = document.getElementById('qr-image');
                if (!qrImage) throw new Error('QR image not found');
                
                const imagePath = new URL(qrImage.getAttribute('src'), window.location.href).href;
                const link = document.createElement('a');
                link.href = imagePath;
                link.setAttribute('download', 'ID_Card_QR.png');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Error downloading ID card:', error);
                alert('Failed to download ID card. Please try again.');
            }
        });
    }

    // ====================== Mobile Menu Toggle ======================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-show');
            }
        });
    }

    // ====================== Update Profile Modal ======================
    const updateProfileBtn = document.getElementById('update-profile-btn');
    const updateFormModal = document.getElementById('updateFormModal');
    const closeUpdateForm = document.getElementById('closeUpdateForm');
    const cancelUpdateBtn = document.getElementById('cancelUpdateBtn');
    const updateInfoForm = document.getElementById('updateInfoForm');

    // Show update form when button is clicked
    if (updateProfileBtn && updateFormModal) {
        updateProfileBtn.addEventListener('click', function() {
            updateFormModal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
        });
    }

    // Close update form
    function closeUpdateModal() {
        if (updateFormModal) {
            updateFormModal.style.display = 'none';
            document.body.style.overflow = ''; // Re-enable scrolling
        }
    }

    if (closeUpdateForm) {
        closeUpdateForm.addEventListener('click', closeUpdateModal);
    }

    if (cancelUpdateBtn) {
        cancelUpdateBtn.addEventListener('click', closeUpdateModal);
    }

    // Close modal when clicking outside the form
    if (updateFormModal) {
        updateFormModal.addEventListener('click', function(e) {
            if (e.target === updateFormModal) {
                closeUpdateModal();
            }
        });
    }

    // ====================== Form Submission Handler ======================
    if (updateInfoForm) {
        updateInfoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log('Form submission intercepted');

            const submitBtn = this.querySelector('button[type="submit"]');
            showLoading(submitBtn);

            try {
                const formData = new FormData(this);
                
                // Add debug logging
                console.log('Form data to be submitted:', Object.fromEntries(formData.entries()));
                
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });

                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => null);
                    throw new Error(errorData?.message || `Server returned ${response.status} status`);
                }

                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    // Show success message
                    alert(data.message || 'Profile updated successfully');
                    closeUpdateModal();
                    
                    // Refresh after a short delay to ensure user sees the message
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    throw new Error(data.message || 'Update failed for an unknown reason');
                }
            } catch (error) {
                console.error('Form submission error:', error);
                alert(`Error: ${error.message}`);
            } finally {
                hideLoading(submitBtn);
            }
        });
    }

    // ====================== Health Form Navigation ======================
    const formSections = document.querySelectorAll('.form-section');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const formProgress = document.getElementById('formProgress');
    const currentStep = document.getElementById('currentStep');
    
    if (formSections.length > 0) {
        let currentSection = 0;

        // Initialize form
        showSection(currentSection);

        // Next button click handler
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (validateSection(currentSection)) {
                    currentSection++;
                    showSection(currentSection);
                }
            });
        }

        // Previous button click handler
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                currentSection--;
                showSection(currentSection);
            });
        }

        // Show current section and update navigation
        function showSection(index) {
            formSections.forEach((section, i) => {
                section.classList.toggle('active', i === index);
            });
            
            // Update button visibility
            if (prevBtn) prevBtn.disabled = index === 0;
            if (nextBtn) nextBtn.style.display = index === formSections.length - 1 ? 'none' : 'inline-flex';
            if (submitBtn) submitBtn.style.display = index === formSections.length - 1 ? 'inline-flex' : 'none';
            
            // Update progress
            if (formProgress) {
                const progressPercent = ((index + 1) / formSections.length) * 100;
                formProgress.style.width = progressPercent + '%';
            }
            if (currentStep) currentStep.textContent = index + 1;
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
    }

    // Close modal button (disabled to force completion)
    const closeModalBtn = document.getElementById('closeModalBtn');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            alert('Please complete your health profile to access the dashboard.');
        });
    }
});