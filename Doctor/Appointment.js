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
    const newAppointmentModal = document.getElementById('newAppointmentModal');

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
        
        // First check if response has content
        const text = await response.text();
        if (!text) {
            console.warn('Empty response from server');
            return {};
        }
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response');
        }
        
        // Verify data is an array
        if (!Array.isArray(data)) {
            console.warn('Expected array but got:', typeof data);
            if (data.appointments) { // Handle case where data is {appointments: [...]}
                data = data.appointments;
            } else {
                return {};
            }
        }
        
        appointmentsData = transformAppointmentData(data);
        return appointmentsData;
    } catch (error) {
        console.error('Error loading appointments:', error);
        showNotification('Failed to load appointments', 'error');
        return {};
    }
}

    function transformAppointmentData(rawData) {
        const formattedData = {};
        rawData.forEach(appointment => {
            const date = appointment.appointment_date;
            if (!formattedData[date]) {
                formattedData[date] = [];
            }
            
            formattedData[date].push({
                id: appointment.id,
                time: formatTime(appointment.appointment_time),
                formatted_time: formatTime12Hour(appointment.appointment_time),
                title: appointment.reason_for_visit,
                type: appointment.appointment_type,
                description: `With Dr. ${appointment.doctor_name} (${appointment.specialty})`,
                status: appointment.status,
                doctorName: appointment.doctor_name,
                specialty: appointment.specialty,
                purpose: appointment.reason_for_visit,
                notes: appointment.notes || '',
                patientName: appointment.patient_name || 'Unknown',
                patient_id: appointment.patient_id || 'N/A'
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

    function checkServerMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            showNotification(urlParams.get('success'));
        } else if (urlParams.has('error')) {
            showNotification(urlParams.get('error'), 'error');
        }
    }

    function isAppointmentCancelled(appointment) {
        if (!appointment || !appointment.status) return false;
        return appointment.status.toLowerCase().includes('cancel');
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
        
        document.getElementById('overlay-date').textContent = displayDate.toLocaleDateString('en-US', options);
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
        document.getElementById('modalPatientName').textContent = appointment.patientName || 'No patient name';
        document.getElementById('modalPatientId').textContent = `Patient ID: ${appointment.patient_id || 'N/A'}`;
        document.getElementById('modalAppointmentId').textContent = `Appointment ID: ${appointment.id || 'N/A'}`;
        document.getElementById('modalTime').textContent = `${appointment.formatted_time || ''}${isCancelled ? ' (CANCELLED)' : ''}`;
        document.getElementById('modalType').textContent = appointment.type || 'No type specified';
        document.getElementById('modalPurpose').textContent = appointment.purpose || 'No purpose specified';
        
        const notesSection = document.getElementById('modalNotesSection');
        const notesContent = document.getElementById('modalNotes');
        if (notesSection && notesContent) {
            notesSection.style.display = appointment.notes ? 'block' : 'none';
            notesContent.textContent = appointment.notes || '';
        }

        // Handle edit/cancel buttons
        const editControls = document.getElementById('editAppointmentControls');
        if (editControls) {
            editControls.style.display = isCancelled ? 'none' : 'flex';
            
            if (!isCancelled) {
                const editBtn = document.getElementById('editAppointmentBtn');
                const cancelBtn = document.getElementById('cancelAppointmentBtn');
                
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
        }

        appointmentModal.style.display = 'flex';
        appointmentModal.classList.add('active');
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
        
        // Populate edit form
        document.getElementById('editPatientName').textContent = appointment.patientName || 'No patient name';
        document.getElementById('editCurrentTime').textContent = appointment.formatted_time || '';
        document.getElementById('edit-appointment-time').value = formatTime(appointment.time) || '';
        document.getElementById('edit-appointment-notes').value = appointment.notes || '';
        document.getElementById('edit-appointment-id').value = appointment.id || '';
        
        editModal.style.display = 'flex';
        editModal.classList.add('active');
    }

    function showCancelConfirmation(appointment) {
        const cancelModal = document.getElementById('cancelAppointmentModal');
        if (!cancelModal) return;
        
        // Populate cancel form
        document.getElementById('cancelPatientName').textContent = appointment.patientName || 'No patient name';
        document.getElementById('cancelAppointmentDate').textContent = selectedDate;
        document.getElementById('cancelAppointmentTime').textContent = appointment.formatted_time || '';
        document.getElementById('cancel-appointment-id').value = appointment.id || '';
        
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

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.innerHTML = `
            ${message}
            <span class="alert-close">&times;</span>
        `;
        
        document.body.prepend(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        notification.querySelector('.alert-close').addEventListener('click', () => {
            notification.remove();
        });
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
        
        // Modal close handlers
        document.addEventListener('click', (e) => {
            if (e.target === appointmentOverlay) {
                appointmentOverlay.classList.remove('active');
            }
            
            if (e.target === appointmentModal) {
                closeModal();
            }
        });
    }

    function openNewAppointmentModal(date = '') {
        if (!newAppointmentModal) return;
        
        newAppointmentModal.style.display = 'block';
        setTimeout(() => newAppointmentModal.classList.add('active'), 10);
        
        // Reset form
        const form = document.getElementById('new-appointment-form');
        if (form) form.reset();
        
        // Set date if provided
        if (date) {
            const dateInput = document.getElementById('appointment-date');
            if (dateInput) dateInput.value = date;
        }
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('appointment-date');
        if (dateInput) dateInput.min = today;
        
      
    }


});