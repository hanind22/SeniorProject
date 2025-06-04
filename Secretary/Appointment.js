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
    let currentDoctorId = 1;
    let refreshInterval;

    // Notification system with fallback
    function showNotification(message, type = 'success') {
        try {
            const notification = document.getElementById('notification');
            const messageElement = document.getElementById('notification-message');
            
            if (!notification || !messageElement) {
                console.warn('Notification elements not found');
                // Fallback to console and alert
                console.log(`${type}: ${message}`);
                alert(`${type}: ${message}`);
                return;
            }
            
            messageElement.textContent = message;
            notification.className = `notification ${type}`;
            notification.classList.add('active');
            
            setTimeout(function() {
                notification.classList.remove('active');
            }, 3000);
        } catch (error) {
            console.error('Error showing notification:', error, message);
            alert(`${type}: ${message}`);
        }
    }

    // Make available globally
    window.showNotification = showNotification;

    // Fetches appointments from the server
    async function loadAppointments(forceRefresh = false) {
        try {
            // Add cache busting if forcing refresh
            const url = forceRefresh 
                ? 'get_appointments.php?t=' + Date.now() 
                : 'get_appointments.php';
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server error: ${response.status} - ${errorText}`);
            }  

            const data = await response.json();
            
            // Transform the data to mark all appointments on cancelled dates as cancelled
            const transformedData = {};
            for (const [date, appointments] of Object.entries(data)) {
                transformedData[date] = appointments.map(appt => ({
                    ...appt,
                    status: appt.status || 'Scheduled' // Default to Scheduled if no status
                }));
            }
            
            sampleAppointments = transformedData;
            return transformedData;
        } catch (error) {
            console.error('Error loading appointments:', error);
            showNotification('Failed to load appointments', 'error');
            return {};
        }
    }

    // Checks for server messages
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

    // Calendar generation
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

    // Creates a day element
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
        
        return dayElement;
    }

    // Renders appointments on calendar day
    function renderCalendarAppointments(dayElement, appointments) {
        if (!dayElement) return;
        
        const dayNumber = dayElement.querySelector('.day-number');
        dayElement.innerHTML = '';
        if (dayNumber) dayElement.appendChild(dayNumber);
        
        const validAppointments = appointments.filter(appt => {
            if (!appt || !appt.type || typeof appt.type !== 'string') {
                console.warn('Skipping invalid appointment:', appt);
                return false;
            }
            return true;
        });

        if (validAppointments.length === 0) return;

        const previewsContainer = document.createElement('div');
        previewsContainer.className = 'appointment-previews';
        
        const visibleAppointments = validAppointments.slice(0, 2);
        visibleAppointments.forEach(appt => {
            const preview = createAppointmentPreview(appt);
            if (preview) {
                preview.setAttribute('data-appointment-id', appt.id);
                previewsContainer.appendChild(preview);
            }
        });

        const appointmentCount = document.createElement('div');
        appointmentCount.className = 'appointment-count';
        appointmentCount.textContent = validAppointments.length;
        dayElement.appendChild(appointmentCount);
        dayElement.appendChild(previewsContainer);
        
        if (validAppointments.length > 0) {
            dayElement.classList.add('has-appointments');
        }
    }

    // Creates appointment preview
    function createAppointmentPreview(appointment) {
        if (!appointment || !appointment.type) return null;

        const preview = document.createElement('div');
        const typeClass = appointment.type.toLowerCase().replace(/\s+/g, '-');
        preview.className = `appointment-preview appt-${typeClass} ${appointment.status === 'Cancelled' ? 'cancelled' : ''}`;

        const time = document.createElement('span');
        time.className = 'appointment-time';
        time.textContent = appointment.time?.split(' ')[0] || 'Unknown time';

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

    // Date formatting functions
    function formatDateForAPI(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function formatDateForDisplay(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    // Shows appointments for a date
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

        // Get ACTIVE appointments only (non-cancelled)
        const activeAppointments = (sampleAppointments[date] || []).filter(a => 
            a && a.type && (!a.status || a.status.toLowerCase() !== 'cancelled')
        );

        // Update Cancel All button - ONLY show if there are active appointments
        const cancelAllBtn = document.getElementById('cancel-all-appointments');
        if (cancelAllBtn) {
            cancelAllBtn.style.display = activeAppointments.length > 0 ? 'inline-block' : 'none';
            cancelAllBtn.setAttribute('data-date', date);
        }

        if (activeAppointments.length === 0) {
            appointmentsContainer.innerHTML = '<div class="no-appointments">No active appointments for this day.</div>';
        } else {
            activeAppointments.sort((a, b) => convertTimeToMinutes(a.time) - convertTimeToMinutes(b.time));
            activeAppointments.forEach(appointment => {
                const card = createAppointmentCard(appointment);
                if (card) appointmentsContainer.appendChild(card);
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

    // Time conversion
    function convertTimeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [time, period] = timeStr.split(' ');
        const [hours, minutes] = time.split(':').map(Number);
        let total = hours % 12 * 60 + minutes;
        if (period === 'PM') total += 12 * 60;
        return total;
    }

    // Creates appointment card
    function createAppointmentCard(appointment) {
        if (!appointment) return null;
        
        const typeConfig = {
            'regular-checkup': { display: 'Checkup', icon: 'fa-calendar-check', color: 'var(--regular-text)' },
            'follow-up': { display: 'Follow Up', icon: 'fa-sync-alt', color: 'var(--follow-up-text)' },
            'urgent-care': { display: 'Urgent', icon: 'fa-exclamation-triangle', color: 'var(--urgent-text)' },
            'consultation': { display: 'Consult', icon: 'fa-comments', color: 'var(--regular-text)' },
            'other': { display: 'Other', icon: 'fa-ellipsis-h', color: 'var(--regular-text)' }
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

        return card;
    }

    // Helper functions
    function getInitials(name) {
        return name ? name.split(' ').map(part => part[0]).join('').toUpperCase() : '';
    }

    function setModalText(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) element.textContent = text;
    }

    function isValidDate(date) {
        return date instanceof Date && !isNaN(date);
    }

    // Modal functions
    function closeModal() {
        const modal = document.getElementById("appointmentModal");
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove("active");
        }
    }
    
    function closeAllModals() {
        document.querySelectorAll('.overlay').forEach(modal => {
            modal.style.display = 'none';
            modal.classList.remove('active');
        });
    }

    // Appointment details
    function showAppointmentDetails(appointment) {
        if (!appointment) return;
        
        const appointmentDate = new Date(selectedDate);
        const fullDateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const formattedAppointmentDate = isValidDate(appointmentDate) ? 
        appointmentDate.toLocaleDateString('en-US', fullDateOptions) : 'Date not available';
        const cancelAllBtn = document.getElementById('cancel-all-appointments');
        
        setModalText('modalPatientName', appointment.patientName || 'No patient name');
        setModalText('modalPatientId', `Patient ID: ${appointment.patient_id || 'N/A'}`);
        setModalText('modalAppointmentId', `Appointment ID: ${appointment.id || appointment.appointment_id || 'N/A'}`);
        setModalText('modalTime', appointment.time || 'No time specified');
        setModalText('modalType', appointment.type || 'No type specified');
        setModalText('modalPurpose', appointment.purpose || appointment.reason_for_visit || 'No purpose specified');
        
        const notesSection = document.getElementById('modalNotesSection');
        const notesContent = document.getElementById('modalNotes');
        if (notesSection && notesContent) {
            notesSection.style.display = appointment.notes ? 'block' : 'none';
            notesContent.textContent = appointment.notes || '';
        }

        // Hide or disable edit/cancel if already cancelled
        const editControls = document.getElementById('editAppointmentControls');
        const editBtn = document.getElementById('editAppointmentBtn');
        const cancelBtn = document.getElementById('cancelAppointmentBtn');

        const isCancelled = appointment.status && appointment.status.toLowerCase() === 'cancelled';

        if (editControls) {
            editControls.style.display = isCancelled ? 'none' : 'flex';
        }

        // Still attach handlers in case it's not cancelled
        if (!isCancelled) {
            if (editBtn) {
                editBtn.onclick = function(e) {
                    e.preventDefault();
                    closeModal();
                    editAppointmentDetails(appointment);
                };
            }

            if (cancelBtn) {
                cancelBtn.onclick = function(e) {
                    e.preventDefault();
                    closeModal();
                    showCancelConfirmation(appointment);
                };
            }
        }

        if (cancelAllBtn && selectedDate) {
            cancelAllBtn.dataset.date = selectedDate;
        }

        const appointmentModal = document.getElementById("appointmentModal");
        if (appointmentModal) {
            appointmentModal.style.display = 'flex';
            appointmentModal.classList.add("active");
        }
    }

    // Cancel confirmation
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
            cancelModal.style.display = 'flex';
            cancelModal.classList.add('active');
        }
    }

    // Edit appointment
    function editAppointmentDetails(appointment) {
        if (!appointment) return;
        
        closeAllModals();
        
        setModalText('editPatientName', appointment.patientName || 'No patient name');
        setModalText('editAppointmentDate', selectedDate);
        
        const currentTimeElement = document.getElementById('editCurrentTime');
        if (currentTimeElement && appointment.time) {
            currentTimeElement.textContent = formatTimeForDisplay(appointment.time);
        }
        
        const editTime = document.getElementById('edit-appointment-time');
        if (editTime) editTime.value = convertTo24Hour(appointment.time);
        
        const editNotes = document.getElementById('edit-appointment-notes');
        if (editNotes) editNotes.value = appointment.notes || '';
        
        const editAppointmentId = document.getElementById('edit-appointment-id');
        if (editAppointmentId) editAppointmentId.value = appointment.id;
        
        const editModal = document.getElementById('editAppointmentModal');
        if (editModal) {
            editModal.style.display = 'flex';
            editModal.classList.add('active');
        }
    }

    // Time formatting
    function formatTimeForDisplay(timeStr) {
        if (!timeStr) return 'No time specified';
        if (timeStr.includes('AM') || timeStr.includes('PM')) return timeStr;
        
        const timeParts = timeStr.split(':');
        if (timeParts.length === 2) {
            let hours = parseInt(timeParts[0]);
            const minutes = timeParts[1];
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            return `${hours}:${minutes} ${ampm}`;
        }
        return timeStr;
    }

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

    // Calendar navigation
    function refreshCalendar() {
        loadAppointments(true).then(() => {
            generateCalendar(currentMonth, currentYear);
        });
    }

    // Cancel all appointments
    function showCancelAllModal() {
        if (!selectedDate) {
            showNotification('Please select a date first', 'error');
            return;
        }

        const modal = document.getElementById('cancelAllAppointmentsModal');
        if (modal) {
            modal.dataset.date = selectedDate;
            modal.classList.add('active');

            const dateText = document.getElementById('cancelAllDateText');
            if (dateText) {
                dateText.textContent = formatDateForDisplay(selectedDate);
                dateText.dataset.date = selectedDate;
            }
        }
    }

    function closeCancelAllModal(fullReset = false) {
        const modal = document.getElementById('cancelAllAppointmentsModal');
        if (!modal) return;
        
        modal.classList.remove('active');
        
        if (fullReset) {
            // Reset form and state
            const form = document.getElementById('cancelAllAppointmentsForm');
            if (form) form.reset();
            
            // Clear any error messages
            const errorElements = modal.querySelectorAll('.error-message');
            errorElements.forEach(el => el.remove());
            
            // Reset the selected date
            selectedDate = '';
        }
    }

    async function cancelAllAppointmentsForDate() {
        if (!selectedDate) {
            showNotification('Please select a date first', 'error');
            return;
        }
        
        if (!currentDoctorId) {
            showNotification('Doctor ID is not set', 'error');
            return;
        }
        
        const appointments = sampleAppointments[selectedDate] || [];
        if (appointments.length === 0) {
            showNotification('No appointments to cancel for this date', 'warning');
            return;
        }
        
        showCancelAllModal();
    }

    // Setup functions
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

    function setupOverlayCloseButton(buttonId, overlayId) {
        const button = document.getElementById(buttonId);
        const overlay = document.getElementById(overlayId);
        if (button && overlay) {
            button.addEventListener('click', function() {
                overlay.classList.remove('active');
            });
        }
    }

    // Initialize calendar
    generateCalendar(currentMonth, currentYear);
    checkServerMessages();

    // Set up periodic refresh (every 5 minutes)
    refreshInterval = setInterval(() => {
        loadAppointments(true).then(() => {
            generateCalendar(currentMonth, currentYear);
            if (selectedDate) {
                showAppointmentsForDate(selectedDate);
            }
        });
    }, 300000); // 5 minutes in milliseconds

    // Event delegation for dynamic elements
    document.addEventListener('click', function(e) {
        // Calendar day click
        if (e.target.closest('.calendar-day')) {
            const dayElement = e.target.closest('.calendar-day');
            const date = dayElement.getAttribute('data-date');
            selectedDate = date;
            document.getElementById('overlay-date').textContent = date;
            document.getElementById('cancelAllDateText').textContent = date;
            document.getElementById('cancelAllDateText').dataset.date = date;

            // Show cancel all button if there are appointments
            const appointments = sampleAppointments[date] || [];
            document.getElementById('cancel-all-appointments').style.display = appointments.length ? 'inline-block' : 'none';

            // Store selectedDate in modal as well
            const modal = document.getElementById('cancelAllAppointmentsModal');
            if (modal) modal.dataset.date = date;

            showAppointmentsForDate(date);
        }

        // Appointment preview click
        if (e.target.closest('.appointment-preview')) {
            const preview = e.target.closest('.appointment-preview');
            const appointmentId = preview.getAttribute('data-appointment-id');
            const date = preview.closest('.calendar-day').getAttribute('data-date');
            const appointment = (sampleAppointments[date] || []).find(a => a.id == appointmentId);
            if (appointment) {
                showAppointmentDetails(appointment);
            }
        }

        // View details button click
        if (e.target.closest('.view-details')) {
            const card = e.target.closest('.appt-card');
            const patientName = card.querySelector('.patient-name').textContent;
            const date = selectedDate;
            const appointment = (sampleAppointments[date] || []).find(a => a.patientName === patientName);
            if (appointment) {
                showAppointmentDetails(appointment);
            }
        }

        // Modal close buttons
        if (e.target.id === 'modalCloseButton') {
            closeModal();
        }
        if (e.target.id === 'editModalCloseButton') {
            const editModal = document.getElementById('editAppointmentModal');
            if (editModal) {
                editModal.style.display = 'none';
                editModal.classList.remove('active');
            }
        }
        if (e.target.id === 'cancelModalCloseButton') {
            const cancelModal = document.getElementById('cancelAppointmentModal');
            if (cancelModal) {
                cancelModal.style.display = 'none';
                cancelModal.classList.remove('active');
            }
        }
        if (e.target.id === 'cancelAllModalCloseButton') {
            closeCancelAllModal(true);
        }

        // Click outside modals
        if (e.target === document.getElementById('appointmentModal')) {
            closeModal();
        }
        if (e.target === document.getElementById('editAppointmentModal')) {
            const editModal = document.getElementById('editAppointmentModal');
            if (editModal) {
                editModal.style.display = 'none';
                editModal.classList.remove('active');
            }
        }
        if (e.target === document.getElementById('cancelAppointmentModal')) {
            const cancelModal = document.getElementById('cancelAppointmentModal');
            if (cancelModal) {
                cancelModal.style.display = 'none';
                cancelModal.classList.remove('active');
            }
        }
        if (e.target === document.getElementById('cancelAllAppointmentsModal')) {
            closeCancelAllModal(true);
        }
    });

    // Calendar navigation buttons
    document.getElementById('prev-month')?.addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });

    document.getElementById('next-month')?.addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });

    // Close buttons
    setupOverlayCloseButton('close-overlay', 'appointment-overlay');
    setupOverlayCloseButton('close-add-overlay', 'add-appointment-overlay');
    setupOverlayCloseButton('cancel-appointment', 'add-appointment-overlay');

    // Cancel all appointments button
    document.getElementById('cancel-all-appointments')?.addEventListener('click', cancelAllAppointmentsForDate);

    // Add appointment buttons
    document.getElementById('add-appointment-btn')?.addEventListener('click', function() {
        const appointmentForm = document.getElementById('appointment-form');
        const appointmentIdField = document.getElementById('appointment-id');
        const appointmentDateField = document.getElementById('appointment-date');
        const overlayTitle = document.querySelector('#add-appointment-overlay h3');
        const submitButton = document.querySelector('#appointment-form button[type="submit"]');
        const addAppointmentOverlay = document.getElementById('add-appointment-overlay');
        
        if (!appointmentForm || !appointmentDateField || !overlayTitle || !submitButton || !addAppointmentOverlay) return;
        
        appointmentForm.reset();
        if (appointmentIdField) appointmentIdField.remove();
        
        appointmentDateField.value = new Date().toISOString().split('T')[0];
        overlayTitle.textContent = 'Add New Appointment';
        submitButton.innerHTML = '<i class="fas fa-save"></i> Save Appointment';
        appointmentForm.removeAttribute('data-mode');
        addAppointmentOverlay.classList.add('active');
    });

    document.getElementById('add-appointment-day-btn')?.addEventListener('click', function() {
        const appointmentForm = document.getElementById('appointment-form');
        const appointmentIdField = document.getElementById('appointment-id');
        const appointmentDateField = document.getElementById('appointment-date');
        const overlayTitle = document.querySelector('#add-appointment-overlay h3');
        const submitButton = document.querySelector('#appointment-form button[type="submit"]');
        const addAppointmentOverlay = document.getElementById('add-appointment-overlay');
        
        if (!appointmentForm || !appointmentDateField || !overlayTitle || !submitButton || !addAppointmentOverlay) return;
        
        appointmentForm.reset();
        if (appointmentIdField) appointmentIdField.remove();
        
        appointmentDateField.value = this.getAttribute('data-date');
        overlayTitle.textContent = 'Add New Appointment';
        submitButton.innerHTML = '<i class="fas fa-save"></i> Save Appointment';
        appointmentForm.removeAttribute('data-mode');
        addAppointmentOverlay.classList.add('active');
    });

    // Patient ID lookup
    document.getElementById('appointment-patient-id')?.addEventListener('change', async function() {
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
            patientNameField.value = data.success ? data.patient_name : 'Patient not found';
        } catch (error) {
            console.error('Error fetching patient:', error);
            patientNameField.value = 'Error fetching patient';
        }
    });

    // Reason selection
    document.getElementById('cancel-reason')?.addEventListener('change', function() {
        const otherReasonContainer = document.getElementById('other-reason-container');
        if (otherReasonContainer) {
            otherReasonContainer.style.display = this.value === 'Other' ? 'block' : 'none';
        }
    });

    document.getElementById('cancelAllReason')?.addEventListener('change', function() {
        const otherReasonContainer = document.getElementById('otherReasonContainer');
        if (otherReasonContainer) {
            otherReasonContainer.style.display = this.value === 'Other' ? 'block' : 'none';
        }
    });

    // Form submissions
    document.getElementById('edit-appointment-form')?.addEventListener('submit', async function(e) {
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

    document.getElementById('cancel-appointment-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        try {
            const appointmentId = document.getElementById('cancel-appointment-id')?.value;
            const reason = document.getElementById('cancel-reason')?.value;
            const otherReason = document.getElementById('other-reason')?.value;
            const notes = document.getElementById('cancel-notes')?.value;
            const note_about_cancelling = document.getElementById('reason_for_cancelling')?.value;
            const secretaryId = document.getElementById('session-user-id')?.value;

            const finalReason = reason === 'Other' && otherReason ? otherReason : reason;

            if (!appointmentId || !finalReason || !secretaryId) {
                showNotification('Please fill in all required fields.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            formData.append('cancelled_reason', finalReason);
            formData.append('notes', notes);
            formData.append('reason_for_cancelling', note_about_cancelling);
            formData.append('cancelled_by', secretaryId);
            formData.append('action', 'cancel');

            const response = await fetch('update_appointment.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();

            if (result.success) {
                showNotification('Appointment cancelled successfully', 'success');
                const cancelModal = document.getElementById('cancelAppointmentModal');
                if (cancelModal) {
                    cancelModal.style.display = 'none';
                    cancelModal.classList.remove('active');
                }

                const date = selectedDate;
            if (sampleAppointments[date]) {
                const appointmentIndex = sampleAppointments[date].findIndex(a => a.id == appointmentId);
                if (appointmentIndex !== -1) {
                    sampleAppointments[date][appointmentIndex].status = 'Cancelled';
                    
                    // Update the count display
                    const dayElement = document.querySelector(`.calendar-day[data-date="${date}"]`);
                    if (dayElement) {
                        const activeAppointments = sampleAppointments[date].filter(a => 
                            !a.status || a.status.toLowerCase() !== 'cancelled'
                        );
                        
                        const countElement = dayElement.querySelector('.appointment-count');
                        if (countElement) {
                            countElement.textContent = activeAppointments.length;
                        }
                        
                        // Update the previews
                        renderCalendarAppointments(dayElement, sampleAppointments[date]);
                    }
                }
            }
                // Also update the appointments list in the overlay
                showAppointmentsForDate(date);
                refreshCalendar();
            } else {
                showNotification(result.message || 'Failed to cancel appointment', 'error');
            }
        } catch (err) {
            console.error('Error:', err);
            showNotification('An error occurred. Please try again.', 'error');
        }
    });

    // Cancel all appointments form
    document.getElementById('cancelAllAppointmentsForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const modal = document.getElementById('cancelAllAppointmentsModal');
            const date = modal?.dataset.date;
            
            if (!date) {
                showNotification('No date selected for cancellation', 'error');
                return;
            }
            
            const reasonSelect = document.getElementById('cancelAllReason');
            const otherReasonInput = document.getElementById('cancelAllOtherReason');
            
            if (!reasonSelect?.value) {
                showNotification('Please select a cancellation reason', 'error');
                return;
            }

            let finalReason = reasonSelect.value;
            if (reasonSelect.value === 'Other') {
                const otherReason = otherReasonInput?.value.trim();
                if (!otherReason) {
                    showNotification('Please specify the cancellation reason', 'error');
                    return;
                }
                finalReason = otherReason;
            }

            const cancelledBy = document.body.getAttribute('data-user-id');
            if (!cancelledBy) {
                showNotification('User information not found', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'cancel_all');
            formData.append('date', date);
            formData.append('reason', finalReason);
            formData.append('cancelled_by', cancelledBy);
            formData.append('cancelled_by', cancelledBy);
            formData.append('notes', `Bulk cancellation: ${finalReason}`);

            const response = await fetch('update_appointment.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(`Cancelled ${result.count} appointments`, 'success');
                this.reset();
                document.getElementById('otherReasonContainer').style.display = 'none';
                closeCancelAllModal();
                
                // COMPLETELY refresh the appointments data
                sampleAppointments = {};
                await loadAppointments(true);
                
                // Force update the calendar
                generateCalendar(currentMonth, currentYear);
                 // Update the local data
            if (sampleAppointments[date]) {
                sampleAppointments[date].forEach(appt => {
                    appt.status = 'Cancelled';
                });
                
                // Update the calendar day
                const dayElement = document.querySelector(`.calendar-day[data-date="${date}"]`);
                if (dayElement) {
                    const countElement = dayElement.querySelector('.appointment-count');
                    if (countElement) {
                        countElement.textContent = '0';
                    }
                    
                    // Update the previews
                    renderCalendarAppointments(dayElement, sampleAppointments[date]);
                    
                    // Remove has-appointments class
                    dayElement.classList.remove('has-appointments');
                }
            }
                
                // Re-open the date to show updated state
                showAppointmentsForDate(date);
                
                // Explicitly hide the cancel all button
                const cancelAllBtn = document.getElementById('cancel-all-appointments');
                if (cancelAllBtn) cancelAllBtn.style.display = 'none';
            } else {
                showNotification(result.message || 'Failed to cancel appointments', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to cancel appointments. Please try again.', 'error');
        }
    });

    function clearAppointmentCache() {
        sampleAppointments = {};
        const calendarGrid = document.getElementById('calendar-grid');
        if (calendarGrid) calendarGrid.innerHTML = '';
    }

    // Notification bell
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationBell && notificationDropdown) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });

        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Logout functionality
    const logoutLink = document.querySelector('.nav-links .nav-item:last-child');
    const logoutOverlay = document.getElementById('logoutOverlay');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    if (logoutLink && logoutOverlay && confirmLogout && cancelLogout) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            logoutOverlay.classList.add('show');
        });

        cancelLogout.addEventListener('click', function() {
            logoutOverlay.classList.remove('show');
        });

        confirmLogout.addEventListener('click', function() {
            window.location.href = '../Registration-Login/index.php';
        });

        logoutOverlay.addEventListener('click', function(e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.classList.remove('show');
            }
        });
    }

    // Calendar day click
    document.querySelectorAll('.calendar-day').forEach(day => {
    day.addEventListener('click', function () {
        selectedDate = this.dataset.date; // Assuming each day has data-date="YYYY-MM-DD"
        document.getElementById('overlay-date').textContent = selectedDate;
        document.getElementById('cancelAllDateText').textContent = selectedDate;
        document.getElementById('cancelAllDateText').dataset.date = selectedDate;

        // Show cancel all button if there are appointments
        const appointments = sampleAppointments[selectedDate] || [];
        document.getElementById('cancel-all-appointments').style.display = appointments.length ? 'inline-block' : 'none';

        // Store selectedDate in modal as well
        const modal = document.getElementById('cancelAllAppointmentsModal');
        if (modal) modal.dataset.date = selectedDate;

        showOverlay(); // Show the overlay
    });
});

});