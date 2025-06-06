document.addEventListener('DOMContentLoaded', function() {
    // Initialize lucide icons if available
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Calendar state variables
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = '';
    let appointmentsData = {};

    // DOM Elements
    const calendarGrid = document.getElementById('calendar-grid');
    const currentMonthElement = document.getElementById('current-month');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    const appointmentOverlay = document.getElementById('appointment-overlay');
    const closeOverlayBtn = document.getElementById('close-overlay');
    const appointmentsContainer = document.getElementById('appointments-container');
    const addAppointmentBtn = document.getElementById('add-appointment-btn');
    const addAppointmentDayBtn = document.getElementById('add-appointment-day-btn');
    const appointmentModal = document.getElementById('appointmentModal');
    const addAppointmentOverlay = document.getElementById('add-appointment-overlay');
    const closeAddOverlayBtn = document.getElementById('close-add-overlay');

    // Initialize the application
    initApplication();

    function initApplication() {
        updateDateTime();
        checkServerMessages();
        loadAppointments().then(() => {
            generateCalendar(currentMonth, currentYear);
        });
        
        // Update date and time every minute
        setInterval(updateDateTime, 60000);
        
        // Setup event listeners
        setupEventListeners();
        setupPatientLookup();
        setupModalHandlers();
    }

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
            
            const text = await response.text();
            if (!text) {
                console.warn('Empty response from server');
                return {};
            }
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response');
            }
            
            if (typeof data === 'object' && data !== null && !Array.isArray(data)) {
                appointmentsData = transformObjectAppointmentData(data);
                return appointmentsData;
            }
            
            if (Array.isArray(data)) {
                appointmentsData = transformAppointmentData(data);
                return appointmentsData;
            }
            
            console.warn('Unexpected data format:', typeof data);
            return {};
            
        } catch (error) {
            console.error('Error loading appointments:', error);
            showNotification('Failed to load appointments', 'error');
            return {};
        }
    }

    function transformObjectAppointmentData(rawData) {
        const formattedData = {};
        
        Object.keys(rawData).forEach(date => {
            const appointments = rawData[date];
            formattedData[date] = [];
            
            appointments.forEach(appointment => {
                formattedData[date].push({
                    id: appointment.id,
                    time: appointment.time,
                    formatted_time: appointment.time,
                    title: appointment.purpose,
                    type: appointment.type,
                    description: `Patient: ${appointment.patientName}`,
                    status: appointment.status,
                    doctorName: '',
                    specialty: '',
                    purpose: appointment.purpose,
                    notes: appointment.notes === 'undefined' ? '' : (appointment.notes || ''),
                    patientName: appointment.patientName || 'Unknown',
                    patient_id: appointment.patient_id || 'N/A'
                });
            });
        });
        
        return formattedData;
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const time = new Date(`1970-01-01T${timeString}`);
        return time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: false});
    }
    
    function formatTime12Hour(timeString) {
        if (!timeString) return '';
        const time = new Date(`1970-01-01T${timeString}`);
        return time.toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'});
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

    function refreshCalendar() {
    loadAppointments(true).then(() => {
        generateCalendar(currentMonth, currentYear);
    });
}

    function isAppointmentCancelled(appointment) {
    if (!appointment || !appointment.status) return false;
    return appointment.status.toLowerCase().includes('cancel') || 
           appointment.status.toLowerCase().includes('cancelled') ||
           appointment.status.toLowerCase() === 'canceled';
}

    function generateCalendar(month, year) {
        if (!calendarGrid) return;
        
        calendarGrid.innerHTML = '';
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        
        // Previous month days
        for (let i = 0; i < firstDay; i++) {
            calendarGrid.appendChild(createDayElement('other-month'));
        }

        // Current month days
        for (let i = 1; i <= daysInMonth; i++) {
            const dayElement = createDayElement('', i);
            const formattedDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            
            if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayElement.classList.add('today');
            }
            
            if (appointmentsData[formattedDate]) {
                renderCalendarAppointments(dayElement, appointmentsData[formattedDate]);
            }
            
            // Add click handler to show appointments for this date
            dayElement.addEventListener('click', () => {
                showAppointmentsForDate(formattedDate);
            });
            
            calendarGrid.appendChild(dayElement);
        }

        // Update month/year display
        if (currentMonthElement) {
            currentMonthElement.textContent = `${getMonthName(month)} ${year}`;
        }
    }

    function createDayElement(className, dayNumber) {
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${className}`;
        
        if (dayNumber) {
            const numberElement = document.createElement('div');
            numberElement.className = 'day-number';
            numberElement.textContent = dayNumber;
            dayElement.appendChild(numberElement);
        }
        
        return dayElement;
    }

    function renderCalendarAppointments(dayElement, appointments) {
        if (!dayElement) return;
        
        const validAppointments = appointments.filter(appt => appt && appt.type);
        if (validAppointments.length === 0) return;
        
        const previewsContainer = document.createElement('div');
        previewsContainer.className = 'appointment-previews';
        
        validAppointments.slice(0, 2).forEach(appt => {
            const preview = createAppointmentPreview(appt);
            if (preview) {
                preview.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showAppointmentDetails(appt);
                });
                previewsContainer.appendChild(preview);
            }
        });
        
        dayElement.appendChild(previewsContainer);
        
        if (validAppointments.length > 2) {
            const countElement = document.createElement('div');
            countElement.className = 'appointment-count';
            countElement.textContent = validAppointments.length;
            dayElement.appendChild(countElement);
        }
        
        dayElement.classList.add('has-appointments');
    }

    function createAppointmentPreview(appointment) {
        if (!appointment || !appointment.type) return null;

        const isCancelled = isAppointmentCancelled(appointment);
        const typeClass = appointment.type.toLowerCase().replace(/\s+/g, '-');
        const preview = document.createElement('div');
        preview.className = `appointment-preview appt-${typeClass} ${isCancelled ? 'cancelled' : ''}`;

        const time = document.createElement('span');
        time.className = 'appointment-time';
        time.textContent = appointment.time?.split(' ')[0] || 'Unknown';

        if (isCancelled) {
            const strike = document.createElement('span');
            strike.className = 'strikethrough';
            strike.appendChild(time);
            preview.appendChild(strike);
        } else {
            preview.appendChild(time);
        }

        return preview;
    }

    function showAppointmentsForDate(date) {
        if (!date || !appointmentOverlay || !appointmentsContainer) return;
        
        selectedDate = date;
        const displayDate = new Date(date + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        
        const overlayDateElement = document.getElementById('overlay-date');
        if (overlayDateElement) {
            overlayDateElement.textContent = displayDate.toLocaleDateString('en-US', options);
        }
        
        appointmentsContainer.innerHTML = '';
        
        const appointments = (appointmentsData[date] || []).filter(a => a && a.type);
        
        if (appointments.length === 0) {
            appointmentsContainer.innerHTML = '<div class="no-appointments">No appointments scheduled for this day.</div>';
        } else {
            appointments.sort((a, b) => convertTimeToMinutes(a.time) - convertTimeToMinutes(b.time));
            
            appointments.forEach(appointment => {
                const card = createAppointmentCard(appointment);
                if (card) {
                    appointmentsContainer.appendChild(card);
                }
            });
        }
        
        if (addAppointmentDayBtn) {
            addAppointmentDayBtn.setAttribute('data-date', date);
        }
        
        appointmentOverlay.classList.add('active');
    }

    function convertTimeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [time, period] = timeStr.split(' ');
        const [hours, minutes] = time.split(':').map(Number);
        let total = hours % 12 * 60 + minutes;
        if (period === 'PM') total += 12 * 60;
        return total;
    }

    function createAppointmentCard(appointment) {
        if (!appointment) return null;
        
        const typeConfig = {
            'regular-checkup': { display: 'Checkup', icon: 'fa-calendar-check', color: '#4e73df' },
            'follow-up': { display: 'Follow Up', icon: 'fa-sync-alt', color: '#1cc88a' },
            'urgent-care': { display: 'Urgent', icon: 'fa-exclamation-triangle', color: '#e74a3b' },
            'consultation': { display: 'Consult', icon: 'fa-comments', color: '#f6c23e' },
            'other': { display: 'Other', icon: 'fa-ellipsis-h', color: '#858796' }
        };

        const typeKey = appointment.type ? appointment.type.toLowerCase().replace(/\s+/g, '-') : 'other';
        const config = typeConfig[typeKey] || typeConfig['other'];
        const isCancelled = isAppointmentCancelled(appointment);

        const card = document.createElement('div');
        card.className = `appt-card ${isCancelled ? 'cancelled-appointment' : ''}`;
        
        card.innerHTML = `
            <div class="appt-card__header">
                <div class="appt-time ${isCancelled ? 'cancelled' : ''}">
                    <i class="fas ${config.icon}" style="color:${config.color}"></i>
                    <span>${appointment.formatted_time || ''}${isCancelled ? ' (CANCELLED)' : ''}</span>
                </div>
                <div class="appt-type" style="background:${config.color}20;color:${config.color}">
                    ${config.display}
                </div>
            </div>
            
            <div class="appt-card__body">
                <div class="patient-avatar" style="background:${config.color}20;color:${config.color}">
                    ${getInitials(appointment.patientName)}
                </div>
                <div class="patient-info">
                    <h3 class="patient-name ${isCancelled ? 'cancelled' : ''}">${appointment.patientName || 'No patient name'}</h3>
                    <p class="patient-purpose ${isCancelled ? 'cancelled' : ''}">${appointment.purpose || 'No purpose specified'}</p>
                </div>
            </div>
            
            <div class="appt-card__footer">
                <button class="appt-action view-details">
                    <span>View Details</span>
                </button>
            </div>
        `;

        card.querySelector('.view-details').addEventListener('click', (e) => {
            e.stopPropagation();
            showAppointmentDetails(appointment);
        });

        return card;
    }

    function getInitials(name) {
        if (!name) return '';
        return name.split(' ').map(part => part[0]).join('').toUpperCase();
    }

    function showAppointmentDetails(appointment) {
    if (!appointment || !appointmentModal) return;
    
    const isCancelled = isAppointmentCancelled(appointment);
    
    // Populate modal content
    const modalPatientName = document.getElementById('modalPatientName');
    const modalPatientId = document.getElementById('modalPatientId');
    const modalAppointmentId = document.getElementById('modalAppointmentId');
    const modalTime = document.getElementById('modalTime');
    const modalType = document.getElementById('modalType');
    const modalPurpose = document.getElementById('modalPurpose');
    
    if (modalPatientName) modalPatientName.textContent = appointment.patientName || 'No patient name';
    if (modalPatientId) modalPatientId.textContent = `Patient ID: ${appointment.patient_id || 'N/A'}`;
    if (modalAppointmentId) modalAppointmentId.textContent = `Appointment ID: ${appointment.id || 'N/A'}`;
    if (modalTime) modalTime.textContent = `${appointment.formatted_time || ''}${isCancelled ? ' (CANCELLED)' : ''}`;
    if (modalType) modalType.textContent = appointment.type || 'No type specified';
    if (modalPurpose) modalPurpose.textContent = appointment.purpose || 'No purpose specified';
    
    const notesSection = document.getElementById('modalNotesSection');
    const notesContent = document.getElementById('modalNotes');
    if (notesSection && notesContent) {
        notesSection.style.display = appointment.notes ? 'block' : 'none';
        notesContent.textContent = appointment.notes || '';
    }

    // Store appointment data for edit/cancel functions
    appointmentModal.dataset.appointmentId = appointment.id;
    appointmentModal.dataset.appointmentData = JSON.stringify(appointment);

    // Show/hide action buttons based on cancellation status
    const editBtn = document.getElementById('editAppointmentBtn');
    const cancelBtn = document.getElementById('cancelAppointmentBtn');
    
    if (editBtn && cancelBtn) {
        if (isCancelled) {
            editBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
        } else {
            editBtn.style.display = 'block';
            cancelBtn.style.display = 'block';
        }
    }

    appointmentModal.style.display = 'flex';
    appointmentModal.classList.add('active');
    
    // Close appointment overlay if it's open
    if (appointmentOverlay) {
        appointmentOverlay.classList.remove('active');
    }
}

    function closeModal() {
        if (appointmentModal) {
            appointmentModal.style.display = 'none';
            appointmentModal.classList.remove('active');
        }
    }

    function editAppointmentDetails(appointment) {
        const editModal = document.getElementById('editAppointmentModal');
        if (!editModal) return;
        
        const editPatientName = document.getElementById('editPatientName');
        const editCurrentTime = document.getElementById('editCurrentTime');
        const editAppointmentTime = document.getElementById('edit-appointment-time');
        const editAppointmentNotes = document.getElementById('edit-appointment-notes');
        const editAppointmentId = document.getElementById('edit-appointment-id');
        
        if (editPatientName) editPatientName.textContent = appointment.patientName || 'No patient name';
        if (editCurrentTime) editCurrentTime.textContent = appointment.formatted_time || '';
        if (editAppointmentTime) editAppointmentTime.value = formatTime(appointment.time) || '';
        if (editAppointmentNotes) editAppointmentNotes.value = appointment.notes || '';
        if (editAppointmentId) editAppointmentId.value = appointment.id || '';
        
        editModal.style.display = 'flex';
        editModal.classList.add('active');
    }

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
        
        // Get the current user ID from session (doctor cancelling the appointment)

        if (!appointmentId || !reason || !cancelledBy) {
            showNotification('Please fill in all required fields.', 'error');
            return;
        }

        const finalReason = reason === 'Other' && otherReason ? otherReason : reason;

        const formData = new FormData(this);
        formData.append('appointment_id', appointmentId);
        formData.append('cancel_reason', finalReason);
        formData.append('cancel_notes', notes);
        formData.append('cancelled_by', cancelledBy);
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
            refreshCalendar();
        } else {
            showNotification(result.message || 'Failed to cancel appointment', 'error');
        }
    } catch (err) {
        console.error('Error:', err);
        showNotification('An error occurred. Please try again.', 'error');
    }
});




    function showCancelConfirmation(appointment) {
        const cancelModal = document.getElementById('cancelAppointmentModal');
        if (!cancelModal) return;
        
        const cancelPatientName = document.getElementById('cancelPatientName');
        const cancelAppointmentTime = document.getElementById('cancelAppointmentTime');
        const cancelAppointmentId = document.getElementById('cancel-appointment-id');
        
        if (cancelPatientName) cancelPatientName.textContent = appointment.patientName || 'No patient name';
        if (cancelAppointmentTime) cancelAppointmentTime.textContent = appointment.formatted_time || '';
        if (cancelAppointmentId) cancelAppointmentId.value = appointment.id || '';
        
        cancelModal.style.display = 'flex';
        cancelModal.classList.add('active');
    }

    function getMonthName(monthIndex) {
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        return months[monthIndex];
    }

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

    // Setup patient lookup functionality
    function setupPatientLookup() {
        const patientIdInput = document.getElementById('appointment-patient-id');
        const patientNameInput = document.getElementById('appointment-patient-name');
        
        if (!patientIdInput || !patientNameInput) return;
        
        let debounceTimer;
        
        patientIdInput.addEventListener('input', function() {
            const patientId = this.value.trim();
            
            // Clear previous timer
            clearTimeout(debounceTimer);
            
            if (!patientId) {
                patientNameInput.value = '';
                return;
            }
            
            // Debounce the API call
            debounceTimer = setTimeout(() => {
                fetchPatientName(patientId);
            }, 500);
        });
        
        // Also trigger on Enter key
        patientIdInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const patientId = this.value.trim();
                if (patientId) {
                    clearTimeout(debounceTimer);
                    fetchPatientName(patientId);
                }
            }
        });
    }

    async function fetchPatientName(patientId) {
        const patientNameInput = document.getElementById('appointment-patient-name');
        if (!patientNameInput) return;
        
        try {
            patientNameInput.value = 'Loading...';
            
            const response = await fetch(`get_patient_name.php?patient_id=${encodeURIComponent(patientId)}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                patientNameInput.value = data.patient_name;
            } else {
                patientNameInput.value = '';
                showNotification(data.message || 'Patient not found', 'error');
            }
            
        } catch (error) {
            console.error('Error fetching patient name:', error);
            patientNameInput.value = '';
            showNotification('Error fetching patient information', 'error');
        }
    }

    // Setup modal handlers
    function setupModalHandlers() {
        // Main appointment modal close handlers
        const modalCloseBtn = document.getElementById('modalCloseButton');
        if (modalCloseBtn) {
            modalCloseBtn.addEventListener('click', closeModal);
        }
        
        // Edit appointment button
        const editAppointmentBtn = document.getElementById('editAppointmentBtn');
        if (editAppointmentBtn) {
            editAppointmentBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const appointmentData = appointmentModal.dataset.appointmentData;
                if (appointmentData) {
                    try {
                        const appointment = JSON.parse(appointmentData);
                        closeModal();
                        editAppointmentDetails(appointment);
                    } catch (error) {
                        console.error('Error parsing appointment data:', error);
                        showNotification('Error loading appointment details', 'error');
                    }
                }
            });
        }
        
        // Cancel appointment button
        const cancelAppointmentBtn = document.getElementById('cancelAppointmentBtn');
        if (cancelAppointmentBtn) {
            cancelAppointmentBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const appointmentData = appointmentModal.dataset.appointmentData;
                if (appointmentData) {
                    try {
                        const appointment = JSON.parse(appointmentData);
                        closeModal();
                        showCancelConfirmation(appointment);
                    } catch (error) {
                        console.error('Error parsing appointment data:', error);
                        showNotification('Error loading appointment details', 'error');
                    }
                }
            });
        }

        // Edit modal handlers
        const editModalCloseBtn = document.getElementById('editModalCloseButton');
        if (editModalCloseBtn) {
            editModalCloseBtn.addEventListener('click', function() {
                const editModal = document.getElementById('editAppointmentModal');
                if (editModal) {
                    editModal.style.display = 'none';
                    editModal.classList.remove('active');
                }
            });
        }

        const cancelEditBtn = document.getElementById('cancelEditBtn');
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', function() {
                const editModal = document.getElementById('editAppointmentModal');
                if (editModal) {
                    editModal.style.display = 'none';
                    editModal.classList.remove('active');
                }
            });
        }

        // Cancel modal handlers
        const cancelModalCloseBtn = document.getElementById('cancelModalCloseButton');
        if (cancelModalCloseBtn) {
            cancelModalCloseBtn.addEventListener('click', function() {
                const cancelModal = document.getElementById('cancelAppointmentModal');
                if (cancelModal) {
                    cancelModal.style.display = 'none';
                    cancelModal.classList.remove('active');
                }
            });
        }

        const cancelCancelBtn = document.getElementById('cancelCancelBtn');
        if (cancelCancelBtn) {
            cancelCancelBtn.addEventListener('click', function() {
                const cancelModal = document.getElementById('cancelAppointmentModal');
                if (cancelModal) {
                    cancelModal.style.display = 'none';
                    cancelModal.classList.remove('active');
                }
            });
        }

        // Handle cancel reason dropdown
        const cancelReasonSelect = document.getElementById('cancel-reason');
        const otherReasonContainer = document.getElementById('other-reason-container');
        if (cancelReasonSelect && otherReasonContainer) {
            cancelReasonSelect.addEventListener('change', function() {
                if (this.value === 'Other') {
                    otherReasonContainer.style.display = 'block';
                } else {
                    otherReasonContainer.style.display = 'none';
                }
            });
        }
    }

    function setupEventListeners() {
        // Month navigation
        if (prevMonthBtn) {
            prevMonthBtn.addEventListener('click', () => {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                generateCalendar(currentMonth, currentYear);
            });
        }
        
        if (nextMonthBtn) {
            nextMonthBtn.addEventListener('click', () => {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                generateCalendar(currentMonth, currentYear);
            });
        }

        // Add appointment modal close button
        if (closeAddOverlayBtn) {
            closeAddOverlayBtn.addEventListener('click', closeAddAppointmentModal);
        }

        const cancelAppointmentFormBtn = document.getElementById('cancel-appointment');
        if (cancelAppointmentFormBtn) {
            cancelAppointmentFormBtn.addEventListener('click', closeAddAppointmentModal);
        }
        
        // Close buttons
        if (closeOverlayBtn) {
            closeOverlayBtn.addEventListener('click', () => {
                appointmentOverlay.classList.remove('active');
            });
        }
        
        // Add appointment buttons
        if (addAppointmentBtn) {
            addAppointmentBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openNewAppointmentModal();
            });
        }
        
        if (addAppointmentDayBtn) {
            addAppointmentDayBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const date = addAppointmentDayBtn.getAttribute('data-date');
                openNewAppointmentModal(date);
            });
        }
        
        // Modal close handlers for clicking outside
        document.addEventListener('click', (e) => {
            if (e.target === appointmentOverlay) {
                appointmentOverlay.classList.remove('active');
            }
            
            if (e.target === appointmentModal) {
                closeModal();
            }

            if (e.target === addAppointmentOverlay) {
                closeAddAppointmentModal();
            }
        });

        // Logout functionality
        setupLogoutHandlers();
    }

    function setupLogoutHandlers() {
        const logoutLink = document.querySelector('a[href="#"]');
        const logoutOverlay = document.getElementById('logoutOverlay');
        const confirmLogout = document.getElementById('confirmLogout');
        const cancelLogout = document.getElementById('cancelLogout');

        if (logoutLink && logoutOverlay) {
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                logoutOverlay.style.display = 'flex';
            });
        }

        if (confirmLogout) {
            confirmLogout.addEventListener('click', function() {
                window.location.href = '../logout.php';
            });
        }

        if (cancelLogout && logoutOverlay) {
            cancelLogout.addEventListener('click', function() {
                logoutOverlay.style.display = 'none';
            });
        }

        // Close logout modal when clicking outside
        if (logoutOverlay) {
            logoutOverlay.addEventListener('click', function(e) {
                if (e.target === logoutOverlay) {
                    logoutOverlay.style.display = 'none';
                }
            });
        }
    }

    function openNewAppointmentModal(date = '') {
        if (!addAppointmentOverlay) {
            console.error('Add appointment overlay not found');
            return;
        }
        
        // Show the modal
        addAppointmentOverlay.style.display = 'flex';
        setTimeout(() => addAppointmentOverlay.classList.add('active'), 10);
        
        // Reset form
        const form = document.getElementById('appointment-form');
        if (form) {
            form.reset();
        }
        
        // Set date if provided
        if (date) {
            const dateInput = document.getElementById('appointment-date');
            if (dateInput) {
                dateInput.value = date;
            }
        }
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('appointment-date');
        if (dateInput) {
            dateInput.min = today;
        }
    }

    function closeAddAppointmentModal() {
        if (addAppointmentOverlay) {
            addAppointmentOverlay.classList.remove('active');
            setTimeout(() => {
                addAppointmentOverlay.style.display = 'none';
            }, 300);
        }
    }
});