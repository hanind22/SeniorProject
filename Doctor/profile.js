document.addEventListener('DOMContentLoaded', function () {
    // ----------------- Current date and time display ----------------- //
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

    // ----------------- Modal elements ----------------- //
    const modal = document.getElementById('editProfileModal');
    const openBtn = document.getElementById('openEditModal');
    const closeBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelEdit');
    const changeAvatarBtn = document.getElementById('changeAvatarBtn');
    const avatarUpload = document.getElementById('avatarUpload');
    const avatarPreview = document.getElementById('avatarPreview');
    const profileForm = document.getElementById('profileForm');

    // ----------------- Modal functions ----------------- //
    function openModal() {
        if (modal) modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // ----------------- Modal event listeners ----------------- //
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (changeAvatarBtn) changeAvatarBtn.addEventListener('click', () => {
        if (avatarUpload) avatarUpload.click();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (modal) {
        window.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
    }

    // ----------------- Form validation ----------------- //
    function validateForm(formData) {
        const errors = [];
        if (!formData.license_number) errors.push('License number is required');
        if (!formData.email) errors.push('Email is required');
        if (!formData.phone) errors.push('Phone number is required');

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (formData.email && !emailRegex.test(formData.email)) {
            errors.push('Please enter a valid email address');
        }

        const phoneRegex = /^\d{8}$/;
        if (formData.phone && !phoneRegex.test(formData.phone)) {
            errors.push('Phone number must be exactly 8 digits');
        }

        return errors;
    }

    // ----------------- Profile form submission ----------------- //
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Safely get form values with null checks
            const getFormValue = (selector) => {
                const element = document.querySelector(selector);
                return element ? element.value : null;
            };

            const formData = {
                doctor_id: getFormValue('input[name="doctor_id"]'),
                email: getFormValue('#email'),
                phone: getFormValue('#phone'),
                license_number: getFormValue('#license_number'),
                education: getFormValue('#education'),
                certifications: getFormValue('#certifications'),
                bio: getFormValue('#bio'),
                secretary_name: getFormValue('#secretary_name'),
                secretary_email: getFormValue('#secretary_email'),
                availability: {}
            };

            // Validate required fields
            if (!formData.doctor_id || !formData.email || !formData.phone || !formData.license_number) {
                showNotification('Please fill all required fields', 'error');
                return;
            }

            // Collect availability data with null checks
            const dayGroups = document.querySelectorAll('.availability-day-group');
            if (dayGroups) {
                dayGroups.forEach(group => {
                    const day = group.dataset.day;
                    if (!day) return;
                    
                    const capitalizedDay = day.charAt(0).toUpperCase() + day.slice(1);
                    formData.availability[capitalizedDay] = [];
                    
                    const timeSlots = group.querySelectorAll('.time-slot');
                    timeSlots.forEach((slot, index) => {
                        const statusSelect = slot.querySelector('.status-select');
                        const startTimeInput = slot.querySelector('input[type="time"]:first-of-type');
                        const endTimeInput = slot.querySelector('input[type="time"]:last-of-type');
                        const placeInput = slot.querySelector('.place-input');
                        
                        if (statusSelect && startTimeInput && endTimeInput && placeInput) {
                            formData.availability[capitalizedDay].push({
                                status: statusSelect.value,
                                start_time: startTimeInput.value,
                                end_time: endTimeInput.value,
                                place_name: placeInput.value
                            });
                        }
                    });
                });
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            // Send data to server
            fetch('update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification('Profile updated successfully!', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Failed to update profile', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating profile', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // ----------------- Notification system ----------------- //
    function showNotification(message, type = 'success', duration = 4000) {
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, duration);
    }

    // ----------------- Availability time slot management ----------------- //
    window.addTimeSlot = function (button) {
        const dayGroup = button.closest('.availability-day-group');
        if (!dayGroup) return;

        const day = dayGroup.dataset.day;
        if (!day) return;

        const dayCapitalized = day.charAt(0).toUpperCase() + day.slice(1);
        const slotCount = dayGroup.querySelectorAll('.time-slot').length;

        const newSlot = document.createElement('div');
        newSlot.className = 'time-slot';
        newSlot.innerHTML = `
            <select name="availability[${dayCapitalized}][${slotCount}][status]" class="status-select">
                <option value="available" selected>Available</option>
                <option value="unavailable">Unavailable</option>
            </select>
            <input type="time" name="availability[${dayCapitalized}][${slotCount}][start_time]" class="time-input" value="09:00" required>
            <span>to</span>
            <input type="time" name="availability[${dayCapitalized}][${slotCount}][end_time]" class="time-input" value="17:00" required>
            <input type="text" class="place-input" name="availability[${dayCapitalized}][${slotCount}][place_name]" placeholder="Location (e.g., Main Clinic)" required>
            <div class="time-slot-controls">
                <button type="button" class="add-slot-btn" onclick="addTimeSlot(this)">
                    <i class="fas fa-plus"></i> Add Slot
                </button>
                <button type="button" class="remove-slot" onclick="removeTimeSlot(this)">
                    <i class="fas fa-times"></i> Remove
                </button>
            </div>
        `;

        dayGroup.appendChild(newSlot);
    };

    window.removeTimeSlot = function (button) {
        const slot = button.closest('.time-slot');
        if (!slot) return;

        const dayGroup = slot.closest('.availability-day-group');
        if (!dayGroup) return;

        if (dayGroup.querySelectorAll('.time-slot').length > 1) {
            slot.remove();
        } else {
            // Reset the single remaining slot instead of removing it
            const statusSelect = slot.querySelector('.status-select');
            const timeInputs = slot.querySelectorAll('.time-input');
            const placeInput = slot.querySelector('.place-input');
            
            if (statusSelect) statusSelect.value = 'unavailable';
            if (timeInputs) timeInputs.forEach(input => input.value = '');
            if (placeInput) placeInput.value = '';
        }
    };

    // ----------------- Logout functionality ----------------- //
    const logoutLink = document.querySelector('.nav-links .nav-item:last-child');
    const logoutOverlay = document.getElementById('logoutOverlay');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    if (logoutLink && logoutOverlay) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            logoutOverlay.classList.add('show');
        });
    }

    if (cancelLogout && logoutOverlay) {
        cancelLogout.addEventListener('click', function() {
            logoutOverlay.classList.remove('show');
        });
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', function() {
            window.location.href = '../Welcome/Index.php';
        });
    }

    if (logoutOverlay) {
        logoutOverlay.addEventListener('click', function(e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.classList.remove('show');
            }
        });
    }
});