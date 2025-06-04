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
    if (changeAvatarBtn) changeAvatarBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    window.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

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

        // Convert form data to a structured object
        const formData = new FormData(this);
        const formObject = {};
        const availability = {};

        // Process all form fields
        for (let [key, value] of formData.entries()) {
            // Handle availability fields
            if (key.startsWith('availability[')) {
                const match = key.match(/availability\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]/);
                if (match) {
                    const day = match[1];
                    const slotIndex = match[2];
                    const fieldName = match[3];
                    
                    if (!availability[day]) availability[day] = {};
                    if (!availability[day][slotIndex]) availability[day][slotIndex] = {};
                    
                    availability[day][slotIndex][fieldName] = value;
                }
            } else {
                formObject[key] = value;
            }
        }

        // Add processed availability to form object
        formObject.availability = availability;

        // Validate the form data
        const errors = validateForm(formObject);
        if (errors.length > 0) {
            showNotification(errors.join('<br>'), 'error');
            return;
        }

        // Send as JSON with proper headers
        fetch('update-profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formObject)
        })
        .then(handleResponse)
        .catch(handleError);
    });
}

function handleResponse(response) {
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json().then(data => {
        if (data.success) {
            showNotification('Profile updated successfully!', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to update profile', 'error');
        }
    });
}

function handleError(error) {
    console.error('Fetch error:', error);
    showNotification('An error occurred. Please try again.', 'error');
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
        if (dayGroup.querySelectorAll('.time-slot').length > 1) {
            slot.remove();
        } else {
            // Reset the single remaining slot instead of removing it
            slot.querySelector('.status-select').value = 'unavailable';
            slot.querySelectorAll('.time-input').forEach(input => input.value = '');
            slot.querySelector('.place-input').value = '';
        }
    };
})        
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
        window.location.href = '../Registration-Login/index.php';
        
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