document.addEventListener('DOMContentLoaded', function() {
    // Initialize lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Updates the date and time display in the sidebar every minute
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

    // Calendar state variables
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = '';
    let sampleAppointments = {};

    // Fetches appointments from the server with optional force refresh
    async function loadAppointments(forceRefresh = false) {
        try {
            const response = await fetch('get_appointments.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            
            const data = await response.json();
            console.log('Appointments loaded:', data);
            
            sampleAppointments = data || {};
            return data;
        } catch (error) {
            console.error('Error loading appointments:', error);
            showNotification('Failed to load appointments', 'error');
            return {};
        }
    }

    // Checks for success/error messages in URL parameters and session storage
    function checkServerMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            showNotification(urlParams.get('success'));
        } else if (urlParams.has('error')) {
            showNotification(urlParams.get('error'), 'error');
        }
        
        if (sessionStorage.getItem('refreshCalendar')) {
            loadAppointments(true).then(() => {
                generateCalendar(currentMonth, currentYear);
                sessionStorage.removeItem('refreshCalendar');
            });
        }
    }

    // Generates the calendar grid for the specified month and year
    async function generateCalendar(month, year) {
        await loadAppointments();

        const calendarGrid = document.getElementById('calendar-grid');
        if (!calendarGrid) return;
        calendarGrid.innerHTML = '';

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Previous month days
        for (let i = 0; i < firstDay; i++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day other-month';
            calendarGrid.appendChild(dayElement);
        }

        // Current month days
        const today = new Date();
        for (let i = 1; i <= daysInMonth; i++) {
            const dayElement = createDayElement(i, month, year);
            
            if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            if (sampleAppointments[formattedDate]) {
                renderCalendarAppointments(dayElement, sampleAppointments[formattedDate]);
            }
            
            calendarGrid.appendChild(dayElement);
        }

        // Update month/year display
        const monthNames = ["January", "February", "March", "April", "May", "June", 
                          "July", "August", "September", "October", "November", "December"];
        const currentMonthElement = document.getElementById('current-month');
        if (currentMonthElement) {
            currentMonthElement.textContent = `${monthNames[month]} ${year}`;
        }
    }

    // Creates a day element for the calendar grid
    function createDayElement(date, month, year) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        
        const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
        dayElement.setAttribute('data-date', formattedDate);
        dayElement.setAttribute('data-day', date);
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = date;
        dayElement.appendChild(dayNumber);
        
        dayElement.addEventListener('click', function() {
            showAppointmentsForDate(formattedDate);
        });
        
        return dayElement;
    }

    // Renders appointment previews on a calendar day element
    function renderCalendarAppointments(dayElement, appointments) {
        if (!dayElement) return;
        
        dayElement.innerHTML = '';
        
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = dayElement.dataset.day;
        dayElement.appendChild(dayNumber);
        
        const previewsContainer = document.createElement('div');
        previewsContainer.className = 'appointment-previews';
        
        const visibleAppointments = appointments.slice(0, 2);
        visibleAppointments.forEach(appt => {
            const preview = createAppointmentPreview(appt);
            preview.setAttribute('data-appointment-id', appt.id);
            preview.addEventListener('click', function(e) {
                e.stopPropagation();
                showAppointmentDetails(appt);
            });
            previewsContainer.appendChild(preview);
        });
        

// Create appointment count element
    const appointmentCount = document.createElement('div');
    appointmentCount.className = 'appointment-count';
    appointmentCount.textContent = appointments.length;
    dayElement.appendChild(appointmentCount);
    
    if (appointments.length > 0) {
        dayElement.classList.add('has-appointments');
    }
    
    dayElement.addEventListener('click', function() {
        if (appointments.length > 0) {
            showAppointmentsForDate(dayElement.dataset.date);
        }
    });

        dayElement.appendChild(previewsContainer);
        
        const indicatorsContainer = document.createElement('div');
        indicatorsContainer.className = 'appointment-indicators';
        
        // const uniqueTypes = [...new Set(appointments.map(a => a.type))];
        // uniqueTypes.forEach(type => {
        //     const typeClass = type.toLowerCase().replace(/\s+/g, '-');
        //     const indicator = document.createElement('div');
        //     indicator.className = `appointment-indicator ${typeClass}-indicator`;
        //     indicator.title = type;
        //     indicatorsContainer.appendChild(indicator);
        // });
        
        dayElement.appendChild(indicatorsContainer);
        
        if (appointments.length > 0) {
            dayElement.classList.add('has-appointments');
        }
        
        dayElement.addEventListener('click', function() {
            if (appointments.length > 0) {
                showAppointmentsForDate(dayElement.dataset.date);
            }
        });
    }

function createAppointmentPreview(appointment) {
    if (!appointment || !appointment.type || typeof appointment.type !== 'string') {
        console.warn('Skipping appointment with invalid or missing type:', appointment);
        return null; // don't render it
    }

    const preview = document.createElement('div');
    const typeClass = appointment.type.toLowerCase().replace(/\s+/g, '-');
    preview.className = `appointment-preview appt-${typeClass} ${appointment.status === 'Cancelled' ? 'cancelled' : ''}`;

    const time = document.createElement('span');
    time.className = 'appointment-time';
    time.textContent = appointment.time?.split?.(' ')[0] ?? 'Unknown time';

    if (appointment.status === 'Cancelled') {
        const strike = document.createElement('span');
        strike.className = 'strikethrough';
        strike.appendChild(time);
        preview.appendChild(strike);
    } else {
        preview.appendChild(time);
    }

    return preview;
}


    // Displays all appointments for a specific date in the overlay
    function showAppointmentsForDate(date) {
        if (!date) return;
        
        selectedDate = date;
        const displayDate = new Date(date + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        
        const overlayDateElement = document.getElementById('overlay-date');
        if (overlayDateElement) {
            overlayDateElement.textContent = displayDate.toLocaleDateString('en-US', options);
        }
        
        const appointmentsContainer = document.getElementById('appointments-container');
        if (!appointmentsContainer) return;
        
        appointmentsContainer.innerHTML = '';
        
        const appointments = sampleAppointments[date] || [];
        
        if (appointments.length === 0) {
            appointmentsContainer.innerHTML = '<div class="no-appointments">No appointments scheduled for this day.</div>';
        } else {
            appointments.sort((a, b) => {
                return convertTimeToMinutes(a.time) - convertTimeToMinutes(b.time);
            });
            
            appointments.forEach(appointment => {
                appointmentsContainer.appendChild(createAppointmentCard(appointment));
            });
        }
        
        const addAppointmentBtn = document.getElementById('add-appointment-day-btn');
        if (addAppointmentBtn) {
            addAppointmentBtn.setAttribute('data-date', date);
        }
        
        const appointmentOverlay = document.getElementById('appointment-overlay');
        if (appointmentOverlay) {
            appointmentOverlay.classList.add('active');
        }
    }

    // Converts time string (HH:MM AM/PM) to minutes for sorting
    function convertTimeToMinutes(timeStr) {
        if (!timeStr) return 0;
        
        const [time, period] = timeStr.split(' ');
        const [hours, minutes] = time.split(':').map(Number);
        let total = hours % 12 * 60 + minutes;
        if (period === 'PM') total += 12 * 60;
        return total;
    }

    // Creates a card element for an appointment
    function createAppointmentCard(appointment) {
        if (!appointment) return document.createElement('div');
        
        const typeConfig = {
            'regular-checkup': {
                display: 'Checkup',
                icon: 'fa-calendar-check',
                color: 'var(--regular-text)'
            },
            'follow-up': {
                display: 'Follow Up',
                icon: 'fa-sync-alt',
                color: 'var(--follow-up-text)'
            },
            'urgent-care': {
                display: 'Urgent',
                icon: 'fa-exclamation-triangle',
                color: 'var(--urgent-text)'
            },
            'consultation': {
                display: 'Consult',
                icon: 'fa-comments',
                color: 'var(--regular-text)'
            },
            'other': {
                display: 'Other',
                icon: 'fa-ellipsis-h',
                color: 'var(--regular-text)'
            }
        };

        const typeKey = appointment.type ? appointment.type.toLowerCase().replace(/\s+/g, '-') : 'other';
        const config = typeConfig[typeKey] || typeConfig['other'];
        const initials = getInitials(appointment.patientName);

        const card = document.createElement('div');
        card.className = 'appt-card';
        card.innerHTML = `
            <div class="appt-card__header">
                <div class="appt-time">
                    <i class="fas ${config.icon}" style="color:${config.color}"></i>
                    <span>${appointment.time || ''}</span>
                </div>
                <div class="appt-type" style="background:${config.color}20;color:${config.color}">
                    ${config.display}
                </div>
            </div>
            
            <div class="appt-card__body">
                <div class="patient-avatar" style="background:${config.color}20;color:${config.color}">
                    ${initials}
                </div>
                <div class="patient-info">
                    <h3 class="patient-name">${appointment.patientName || 'No patient name'}</h3>
                    <p class="patient-purpose">${appointment.purpose || 'No purpose specified'}</p>
                </div>
            </div>
            
            <div class="appt-card__footer">
                <button class="appt-action view-details">
                    <span>View Details</span>
                </button>
            </div>
        `;

        const viewDetailsBtn = card.querySelector('.view-details');
        if (viewDetailsBtn) {
            viewDetailsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showAppointmentDetails(appointment);
            });
        }

        return card;
    }

    // Gets initials from a full name
    function getInitials(name) {
        if (!name) return '';
        return name.split(' ').map(part => part[0]).join('').toUpperCase();
    }

    // Shows detailed view of an appointment in a modal
    function showAppointmentDetails(appointment) {
        if (!appointment) return;
        
        const appointmentDate = new Date(selectedDate);
        
        const fullDateOptions = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };

        const patientId = appointment.patient_id || 'N/A';
        
        const formattedAppointmentDate = isValidDate(appointmentDate) ? 
            appointmentDate.toLocaleDateString('en-US', fullDateOptions) : 
            'Date not available';
        
        setModalText('modalPatientName', appointment.patientName || 'No patient name');
        setModalText('modalPatientId', `Patient ID: ${patientId}`);
        setModalText('modalAppointmentId', `Appointment ID: ${appointment.id || appointment.appointment_id || 'N/A'}`);
        setModalText('modalTime', appointment.time || 'No time specified');
        setModalText('modalType', appointment.type || 'No type specified');
        setModalText('modalPurpose', appointment.purpose || 'No purpose specified');
        
        const notesSection = document.getElementById('modalNotesSection');
        const notesContent = document.getElementById('modalNotes');
        if (notesSection && notesContent) {
            if (appointment.notes) {
                notesContent.textContent = appointment.notes;
                notesSection.style.display = 'block';
            } else {
                notesSection.style.display = 'none';
            }
        }
        
        const editBtn = document.getElementById('editAppointmentBtn');
    if (editBtn) {
        editBtn.onclick = function(e) {
            e.preventDefault();
            closeModal();
            editAppointmentDetails(appointment);
        };
    }
    
    const cancelBtn = document.getElementById('cancelAppointmentBtn');
    if (cancelBtn) {
        cancelBtn.onclick = function(e) {
            e.preventDefault();
            closeModal();
            showCancelConfirmation(appointment);
        };
    }
    
    const appointmentModal = document.getElementById("appointmentModal");
    if (appointmentModal) {
        appointmentModal.style.display = 'flex';
        appointmentModal.classList.add("active");
    }
}

    // Helper function to set text content of modal elements
    function setModalText(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
        }
    }

    // Validates if a date object is valid
    function isValidDate(date) {
        return date instanceof Date && !isNaN(date);
    }

    // Update the closeModal function
    function closeModal() {
    const appointmentModal = document.getElementById("appointmentModal");
    if (appointmentModal) {
        appointmentModal.style.display = 'none';
        appointmentModal.classList.remove("active");
    }
}
    
    window.closeModal = closeModal;

    // Update the showCancelConfirmation function to use proper modal display
    function showCancelConfirmation(appointment) {
    if (!appointment) return;
    
    closeModal();
    
    setModalText('cancelPatientName', appointment.patientName || 'No patient name');
    setModalText('cancelAppointmentDate', selectedDate);
    setModalText('cancelAppointmentTime', appointment.time || 'No time specified');
    
    const cancelAppointmentId = document.getElementById('cancel-appointment-id');
    if (cancelAppointmentId) {
        cancelAppointmentId.value = appointment.id;
    }
    
    const cancelModal = document.getElementById('cancelAppointmentModal');
    if (cancelModal) {
        cancelModal.style.display = 'flex'; // Change from classList.add to style.display
        cancelModal.classList.add('active');
    }
}
      
// Update the editAppointmentDetails function to properly display current time
function editAppointmentDetails(appointment) {
    if (!appointment) return;
    
    closeAllModals();
    
    // Set patient name and date
    setModalText('editPatientName', appointment.patientName || 'No patient name');
    setModalText('editAppointmentDate', selectedDate);
    
    // Display the current appointment time
    const currentTimeElement = document.getElementById('editCurrentTime');
    if (currentTimeElement && appointment.time) {
        currentTimeElement.textContent = formatTimeForDisplay(appointment.time);
    }
    
    // Set the time input field
    const editTime = document.getElementById('edit-appointment-time');
    if (editTime) {
        editTime.value = convertTo24Hour(appointment.time);
    }
    
    // Set notes
    const editNotes = document.getElementById('edit-appointment-notes');
    if (editNotes) {
        editNotes.value = appointment.notes || '';
    }
    
    // Set appointment ID
    const editAppointmentId = document.getElementById('edit-appointment-id');
    if (editAppointmentId) {
        editAppointmentId.value = appointment.id;
    }
    
    // Show the modal
    const editModal = document.getElementById('editAppointmentModal');
    if (editModal) {
        editModal.style.display = 'flex';
        editModal.classList.add('active');
    }
}

// Improved time formatting function
function formatTimeForDisplay(timeStr) {
    if (!timeStr) return 'No time specified';
    
    // If already in AM/PM format, return as is
    if (timeStr.includes('AM') || timeStr.includes('PM')) {
        return timeStr;
    }
    
    // If in 24-hour format (HH:MM), convert to 12-hour format
    const timeParts = timeStr.split(':');
    if (timeParts.length === 2) {
        let hours = parseInt(timeParts[0]);
        const minutes = timeParts[1];
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert 0 to 12
        return `${hours}:${minutes} ${ampm}`;
    }
    
    // Fallback for unexpected formats
    return timeStr;
}

    // Update the closeAllModals function to handle both display and class
    function closeAllModals() {
    document.querySelectorAll('.overlay').forEach(modal => {
        modal.style.display = 'none';
        modal.classList.remove('active');
    });
}
    // Finds an appointment by ID in the sampleAppointments object
    function findAppointmentById(id) {
        for (const date in sampleAppointments) {
            const appointment = sampleAppointments[date].find(a => a.id == id);
            if (appointment) return appointment;
        }
        return null;
    }

    // Converts 12-hour time string to 24-hour format
    function convertTo24Hour(time12h) {
        if (!time12h) return '';
        
        const [time, period] = time12h.split(' ');
        if (!period) return time;
        
        let [hours, minutes] = time.split(':');
        
        if (period === 'PM' && hours !== '12') {
            hours = parseInt(hours, 10) + 12;
        } else if (period === 'AM' && hours === '12') {
            hours = '00';
        }
        
        return `${hours}:${minutes}`;
    }

    // Refreshes the calendar display
    function refreshCalendar() {
        loadAppointments(true).then(() => {
            generateCalendar(currentMonth, currentYear);
        });
    }

    // Shows a notification message
    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const messageElement = document.getElementById('notification-message');
        
        if (!notification || !messageElement) return;
        
        messageElement.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.add('active');
        
        setTimeout(function() {
            notification.classList.remove('active');
        }, 3000);
    }

    // Update the setupCloseButton function to properly close modals
    function setupCloseButton(buttonId, modalId) {
    const button = document.getElementById(buttonId);
    const modal = document.getElementById(modalId);
    if (button && modal) {
        button.addEventListener('click', function() {
            modal.style.display = 'none';
            modal.classList.remove('active');
        });
    }
}

    // Sets up a close button for an overlay
    function setupOverlayCloseButton(buttonId, overlayId) {
        const button = document.getElementById(buttonId);
        const overlay = document.getElementById(overlayId);
        if (button && overlay) {
            button.addEventListener('click', function() {
                overlay.classList.remove('active');
            });
        }
    }

    // Initialize calendar and event listeners
    generateCalendar(currentMonth, currentYear);
    checkServerMessages();

    // Month navigation
    const prevMonthBtn = document.getElementById('prev-month');
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            generateCalendar(currentMonth, currentYear);
        });
    }

    const nextMonthBtn = document.getElementById('next-month');
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            generateCalendar(currentMonth, currentYear);
        });
    }

    // Close overlays
    setupOverlayCloseButton('close-overlay', 'appointment-overlay');
    setupOverlayCloseButton('close-add-overlay', 'add-appointment-overlay');
    setupOverlayCloseButton('cancel-appointment', 'add-appointment-overlay');

    // Form handlers
    const addAppointmentBtn = document.getElementById('add-appointment-btn');
    if (addAppointmentBtn) {
        addAppointmentBtn.addEventListener('click', function() {
            const appointmentForm = document.getElementById('appointment-form');
            const appointmentIdField = document.getElementById('appointment-id');
            const appointmentDateField = document.getElementById('appointment-date');
            const overlayTitle = document.querySelector('#add-appointment-overlay h3');
            const submitButton = document.querySelector('#appointment-form button[type="submit"]');
            const addAppointmentOverlay = document.getElementById('add-appointment-overlay');
            
            if (!appointmentForm || !appointmentDateField || !overlayTitle || !submitButton || !addAppointmentOverlay) return;
            
            appointmentForm.reset();
            
            if (appointmentIdField) {
                appointmentIdField.remove();
            }
            
            appointmentDateField.value = new Date().toISOString().split('T')[0];
            
            overlayTitle.textContent = 'Add New Appointment';
            submitButton.innerHTML = '<i class="fas fa-save"></i> Save Appointment';
            appointmentForm.removeAttribute('data-mode');
            
            addAppointmentOverlay.classList.add('active');
        });
    }

    const addAppointmentDayBtn = document.getElementById('add-appointment-day-btn');
    if (addAppointmentDayBtn) {
        addAppointmentDayBtn.addEventListener('click', function() {
            const appointmentForm = document.getElementById('appointment-form');
            const appointmentIdField = document.getElementById('appointment-id');
            const appointmentDateField = document.getElementById('appointment-date');
            const overlayTitle = document.querySelector('#add-appointment-overlay h3');
            const submitButton = document.querySelector('#appointment-form button[type="submit"]');
            const addAppointmentOverlay = document.getElementById('add-appointment-overlay');
            
            if (!appointmentForm || !appointmentDateField || !overlayTitle || !submitButton || !addAppointmentOverlay) return;
            
            appointmentForm.reset();
            
            if (appointmentIdField) {
                appointmentIdField.remove();
            }
            
            appointmentDateField.value = this.getAttribute('data-date');
            
            overlayTitle.textContent = 'Add New Appointment';
            submitButton.innerHTML = '<i class="fas fa-save"></i> Save Appointment';
            appointmentForm.removeAttribute('data-mode');
            
            addAppointmentOverlay.classList.add('active');
        });
    }

    // Patient ID lookup
    const patientIdField = document.getElementById('appointment-patient-id');
    if (patientIdField) {
        patientIdField.addEventListener('change', async function() {
            const patientId = this.value;
            const patientNameField = document.getElementById('appointment-patient-name');
            
            if (!patientNameField) return;
            
            if (!patientId) {
                patientNameField.value = '';
                return;
            }

            try {
                const response = await fetch(`get_patient_name.php?patient_id=${patientId}`);
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                if (data.success) {
                    patientNameField.value = data.patient_name;
                } else {
                    patientNameField.value = 'Patient not found';
                }
            } catch (error) {
                console.error('Error fetching patient:', error);
                patientNameField.value = 'Error fetching patient';
            }
        });
    }

    // Update the modal event listeners
const modalCloseButton = document.getElementById("modalCloseButton");
if (modalCloseButton) {
    modalCloseButton.addEventListener('click', function() {
        closeModal();
    });
}

const editModalCloseButton = document.getElementById('editModalCloseButton');
if (editModalCloseButton) {
    editModalCloseButton.addEventListener('click', function() {
        const editModal = document.getElementById('editAppointmentModal');
        if (editModal) {
            editModal.style.display = 'none';
            editModal.classList.remove('active');
        }
    });
}

const cancelModalCloseButton = document.getElementById('cancelModalCloseButton');
if (cancelModalCloseButton) {
    cancelModalCloseButton.addEventListener('click', function() {
        const cancelModal = document.getElementById('cancelAppointmentModal');
        if (cancelModal) {
            cancelModal.style.display = 'none';
            cancelModal.classList.remove('active');
        }
    });
}


    const appointmentModal = document.getElementById("appointmentModal");
    if (appointmentModal) {
        appointmentModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

// Update the form submission handlers to properly close modals
const editForm = document.getElementById('edit-appointment-form');
if (editForm) {
    editForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData(this);
            formData.append('action', 'update_time');
            
            const response = await fetch('update_appointment.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Appointment updated successfully', 'success');
                const editModal = document.getElementById('editAppointmentModal');
                if (editModal) {
                    editModal.style.display = 'none';
                    editModal.classList.remove('active');
                }
                refreshCalendar();
            } else {
                showNotification('Error updating appointment: ' + (result.message || 'Please try again'), 'error');
            }
        } catch (error) {
            console.error('Error updating appointment:', error);
            showNotification('Error updating appointment. Please try again.', 'error');
        }
    });
}


const cancelForm = document.getElementById('cancel-appointment-form');
if (cancelForm) {
    cancelForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData(this);
            
            const response = await fetch('update_appointment.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Appointment cancelled successfully', 'success');
                const cancelModal = document.getElementById('cancelAppointmentModal');
                if (cancelModal) {
                    cancelModal.style.display = 'none';
                    cancelModal.classList.remove('active');
                }
                refreshCalendar();
            } else {
                showNotification('Error cancelling appointment: ' + (result.message || 'Please try again'), 'error');
            }
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            showNotification('Error cancelling appointment. Please try again.', 'error');
        }
    });
}

    // Close buttons
    setupCloseButton('editModalCloseButton', 'editAppointmentModal');
    setupCloseButton('cancelModalCloseButton', 'cancelAppointmentModal');
    setupCloseButton('cancelEditBtn', 'editAppointmentModal');
    setupCloseButton('cancelCancelBtn', 'cancelAppointmentModal');

    // Show/hide other reason field
    const cancelReason = document.getElementById('cancel-reason');
    if (cancelReason) {
        cancelReason.addEventListener('change', function() {
            const otherReasonContainer = document.getElementById('other-reason-container');
            if (otherReasonContainer) {
                otherReasonContainer.style.display = this.value === 'Other' ? 'block' : 'none';
            }
        });
    }

    // Close modals when clicking outside
    document.addEventListener('mousedown', function(e) {
        const appointmentModal = document.getElementById("appointmentModal");
        if (appointmentModal) {
            const modalContent = appointmentModal.querySelector(".modal");
            if (appointmentModal.classList.contains("active") && modalContent && !modalContent.contains(e.target)) {
                appointmentModal.classList.remove("active");
            }
        }
        
        const addOverlay = document.getElementById("add-appointment-overlay");
        if (addOverlay) {
            const addOverlayContent = addOverlay.querySelector(".overlay-content");
            if (addOverlay.classList.contains("active") && addOverlayContent && !addOverlayContent.contains(e.target)) {
                addOverlay.classList.remove("active");
            }
        }
    });
});