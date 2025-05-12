// Calendar functionality
const calendarGrid = document.getElementById('calendar-grid');
const currentMonthElement = document.getElementById('current-month');
const prevMonthButton = document.getElementById('prev-month');
const nextMonthButton = document.getElementById('next-month');
const overlay = document.getElementById('appointment-overlay');
const overlayDate = document.getElementById('overlay-date');
const appointmentsContainer = document.getElementById('appointments-container');
const closeOverlayButton = document.getElementById('close-overlay');
const addAppointmentBtn = document.querySelector('.add-appointment-btn');

// Sample appointment data
let appointments = {
    '2025-05-10': [
        { 
            time: '09:00 AM', 
            patient: 'John Smith', 
            reason: 'Follow-up', 
            duration: '30 min',
            type: 'regular',
            phone: '(555) 123-4567',
            notes: 'Blood pressure check',
            id: '1a2b3c'
        },
        { 
            time: '11:30 AM', 
            patient: 'Sarah Johnson', 
            reason: 'Acute pain', 
            duration: '45 min',
            type: 'urgent',
            phone: '(555) 987-6543',
            notes: 'Patient reporting severe lower back pain',
            id: '4d5e6f'
        }
    ],
    '2025-05-15': [
        { 
            time: '10:15 AM', 
            patient: 'Michael Brown', 
            reason: 'Annual physical', 
            duration: '60 min',
            type: 'regular',
            phone: '(555) 456-7890',
            notes: 'Full checkup with blood work',
            id: '7g8h9i'
        }
    ],
    '2025-05-07': [
        { 
            time: '02:30 PM', 
            patient: 'Emily Wilson', 
            reason: 'Consultation', 
            duration: '45 min',
            type: 'regular',
            phone: '(555) 234-5678',
            notes: 'New patient referral',
            id: 'j1k2l3'
        }
    ],
    '2025-05-20': [
        { 
            time: '03:45 PM', 
            patient: 'David Lee', 
            reason: 'Post-surgery', 
            duration: '30 min',
            type: 'regular',
            phone: '(555) 876-5432',
            notes: 'Two weeks after knee surgery',
            id: 'm4n5o6'
        }
    ]
};

let currentDate = new Date();
let selectedDateForAdd = null;
let selectedAppointmentId = null;

// Function to generate unique ID for appointments
function generateId() {
    return Math.random().toString(36).substr(2, 6);
}

// Function to render the calendar
function renderCalendar(date) {
    calendarGrid.innerHTML = '';
    
    const year = date.getFullYear();
    const month = date.getMonth();
    
    currentMonthElement.textContent = `${new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}`;
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    
    // Get the day of week for the first day (0 = Sunday, 6 = Saturday)
    let firstDayOfWeek = firstDay.getDay();
    
    // Calculate total rows needed (5 or 6)
    const totalDays = daysInMonth;
    const totalCells = Math.ceil((totalDays + firstDayOfWeek) / 7) * 7;
    
    // Current month days
    const today = new Date();
    for (let i = 1; i <= daysInMonth; i++) {
        // Calculate position in grid
        const position = firstDayOfWeek + (i - 1);
        const row = Math.floor(position / 7);
        const col = position % 7;
        
        const isToday = today.getDate() === i && 
                        today.getMonth() === month && 
                        today.getFullYear() === year;
                        
        const dayElement = createDayElement(i, isToday ? 'today' : '');
        
        // Apply grid positioning
        dayElement.style.gridRow = row + 1;
        dayElement.style.gridColumn = col + 1;
        
        // Check for appointments
        const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        if (appointments[dateString]) {
            const dayAppointments = appointments[dateString];
            
            // Add indicators
            const hasUrgent = dayAppointments.some(a => a.type === 'urgent');
            const hasRegular = dayAppointments.some(a => a.type === 'regular');
            
            const indicatorsDiv = document.createElement('div');
            if (hasRegular) {
                const indicator = document.createElement('span');
                indicator.className = 'appointment-indicator regular-appt';
                indicatorsDiv.appendChild(indicator);
            }
            if (hasUrgent) {
                const indicator = document.createElement('span');
                indicator.className = 'appointment-indicator urgent-appt';
                indicatorsDiv.appendChild(indicator);
            }
            dayElement.appendChild(indicatorsDiv);
            
            // Add first appointment preview
            if (dayAppointments.length > 0) {
                const firstAppt = dayAppointments[0];
                const apptPreview = document.createElement('div');
                apptPreview.className = `appointment-preview appt-${firstAppt.type}`;
                apptPreview.textContent = `${firstAppt.time} ${firstAppt.patient}`;
                dayElement.appendChild(apptPreview);
                
                if (dayAppointments.length > 1) {
                    const moreText = document.createElement('div');
                    moreText.className = 'text-xs text-gray-500 mt-1';
                    moreText.textContent = `+${dayAppointments.length - 1} more`;
                    dayElement.appendChild(moreText);
                }
            }
            
            // Make the day clickable to show appointments
            dayElement.addEventListener('click', () => {
                showAppointments(dateString, i);
            });
        } else {
            // Make empty days clickable to add appointments
            dayElement.addEventListener('click', () => {
                showAddAppointmentForm(dateString);
            });
        }
        
        calendarGrid.appendChild(dayElement);
    }
    
    // Set explicit grid layout
    calendarGrid.style.gridTemplateRows = `repeat(${Math.ceil((daysInMonth + firstDayOfWeek) / 7)}, 1fr)`;
}

// Function to create a day element
function createDayElement(day, className) {
    const dayElement = document.createElement('div');
    dayElement.className = `calendar-day ${className}`;
    
    const dayNumber = document.createElement('div');
    dayNumber.className = 'day-number';
    dayNumber.textContent = day;
    dayElement.appendChild(dayNumber);
    
    return dayElement;
}

// Function to show appointments for a specific day
function showAppointments(dateString, day) {
    overlayDate.textContent = new Date(dateString).toLocaleDateString('en-US', { 
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
    
    appointmentsContainer.innerHTML = '';
    
    if (appointments[dateString]) {
        // Sort appointments by time
        const dayAppointments = [...appointments[dateString]].sort((a, b) => {
            return new Date('1970/01/01 ' + a.time) - new Date('1970/01/01 ' + b.time);
        });
        
        dayAppointments.forEach(appt => {
            const apptCard = document.createElement('div');
            apptCard.className = `appointment-card ${appt.type === 'urgent' ? 'urgent' : ''}`;
            apptCard.dataset.id = appt.id;
            
            const timeElement = document.createElement('div');
            timeElement.className = 'appointment-time';
            timeElement.textContent = appt.time;
            apptCard.appendChild(timeElement);
            
            const patientElement = document.createElement('div');
            patientElement.className = 'appointment-patient';
            patientElement.textContent = appt.patient;
            apptCard.appendChild(patientElement);
            
            const reasonElement = document.createElement('div');
            reasonElement.textContent = appt.reason;
            apptCard.appendChild(reasonElement);
            
            const detailsDiv = document.createElement('div');
            detailsDiv.className = 'appointment-details';
            
            const durationDetail = document.createElement('div');
            durationDetail.className = 'detail-item';
            durationDetail.innerHTML = `<i class="fas fa-clock"></i> ${appt.duration}`;
            detailsDiv.appendChild(durationDetail);
            
            const phoneDetail = document.createElement('div');
            phoneDetail.className = 'detail-item';
            phoneDetail.innerHTML = `<i class="fas fa-phone"></i> ${appt.phone}`;
            detailsDiv.appendChild(phoneDetail);
            
            const notesDetail = document.createElement('div');
            notesDetail.className = 'detail-item';
            notesDetail.innerHTML = `<i class="fas fa-sticky-note"></i> ${appt.notes}`;
            detailsDiv.appendChild(notesDetail);
            
            // Add edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'edit-appointment-btn';
            editBtn.innerHTML = '<i class="fas fa-edit"></i> Modify';
            editBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showEditAppointmentForm(dateString, appt.id);
            });
            detailsDiv.appendChild(editBtn);
            
            apptCard.appendChild(detailsDiv);
            
            // Make entire card clickable to view details
            apptCard.addEventListener('click', () => {
                showAppointmentDetails(dateString, appt.id);
            });
            
            appointmentsContainer.appendChild(apptCard);
        });
    } else {
        showAddAppointmentForm(dateString);
        return;
    }
    
    // Show the "Add Appointment" button in the footer
    addAppointmentBtn.style.display = 'flex';
    addAppointmentBtn.onclick = () => {
        showAddAppointmentForm(dateString);
    };
    
    overlay.classList.add('active');
}

// Function to show appointment details
function showAppointmentDetails(dateString, appointmentId) {
    const appointment = appointments[dateString].find(a => a.id === appointmentId);
    
    appointmentsContainer.innerHTML = `
        <div class="appointment-detail-view">
            <h3 class="detail-view-title">Appointment Details</h3>
            <div class="detail-view-content">
                <div class="detail-row">
                    <span class="detail-label">Patient:</span>
                    <span class="detail-value">${appointment.patient}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">${appointment.time}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reason:</span>
                    <span class="detail-value">${appointment.reason}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration:</span>
                    <span class="detail-value">${appointment.duration}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value">${appointment.type === 'urgent' ? 'Urgent' : 'Regular'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">${appointment.phone}</span>
                </div>
                <div class="detail-row notes-row">
                    <span class="detail-label">Notes:</span>
                    <span class="detail-value">${appointment.notes}</span>
                </div>
            </div>
            <div class="detail-view-actions">
                <button id="modify-appointment" class="modify-btn">
                    <i class="fas fa-edit"></i> Modify Appointment
                </button>
                <button id="back-to-list" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to List
                </button>
            </div>
        </div>
    `;
    
    // Set up modify button
    document.getElementById('modify-appointment').addEventListener('click', () => {
        showEditAppointmentForm(dateString, appointmentId);
    });
    
    // Set up back button
    document.getElementById('back-to-list').addEventListener('click', () => {
        showAppointments(dateString);
    });
}

// Function to show the edit appointment form
function showEditAppointmentForm(dateString, appointmentId) {
    selectedAppointmentId = appointmentId;
    const appointment = appointments[dateString].find(a => a.id === appointmentId);
    
    appointmentsContainer.innerHTML = `
        <div class="edit-appointment-form">
            <h3 class="form-title">Modify Appointment for ${appointment.patient}</h3>
            <form id="edit-appointment-form">
                <div class="form-group">
                    <label for="edit-appointment-time">Time</label>
                    <input type="time" id="edit-appointment-time" value="${convertTo24Hour(appointment.time)}" required>
                </div>
                <div class="form-group">
                    <label for="edit-appointment-reason">Reason</label>
                    <input type="text" id="edit-appointment-reason" value="${appointment.reason}" required>
                </div>
                <div class="form-group">
                    <label for="edit-appointment-duration">Duration</label>
                    <select id="edit-appointment-duration" required>
                        <option value="15 min" ${appointment.duration === '15 min' ? 'selected' : ''}>15 minutes</option>
                        <option value="30 min" ${appointment.duration === '30 min' ? 'selected' : ''}>30 minutes</option>
                        <option value="45 min" ${appointment.duration === '45 min' ? 'selected' : ''}>45 minutes</option>
                        <option value="60 min" ${appointment.duration === '60 min' ? 'selected' : ''}>60 minutes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-appointment-type">Type</label>
                    <select id="edit-appointment-type" required>
                        <option value="regular" ${appointment.type === 'regular' ? 'selected' : ''}>Regular</option>
                        <option value="urgent" ${appointment.type === 'urgent' ? 'selected' : ''}>Urgent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-appointment-notes">Notes</label>
                    <textarea id="edit-appointment-notes" rows="3">${appointment.notes}</textarea>
                </div>
                <div class="form-group">
                    <label for="edit-change-reason">Reason for Change (required)</label>
                    <textarea id="edit-change-reason" rows="2" required placeholder="Explain why you're modifying this appointment..."></textarea>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="edit-notify-patient" checked>
                    <label for="edit-notify-patient">Notify patient about this change</label>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancel-edit" class="cancel-btn">Cancel</button>
                    <button type="button" id="delete-appointment" class="delete-btn">Cancel Appointment</button>
                    <button type="submit" class="submit-btn">Save Changes</button>
                </div>
            </form>
        </div>
    `;
    
    // Set up form submission
    const form = document.getElementById('edit-appointment-form');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        updateAppointment(dateString, appointmentId);
    });
    
    // Set up cancel button
    document.getElementById('cancel-edit').addEventListener('click', () => {
        showAppointmentDetails(dateString, appointmentId);
    });
    
    // Set up delete button
    document.getElementById('delete-appointment').addEventListener('click', () => {
        showCancelAppointmentForm(dateString, appointmentId);
    });
}

// Function to show cancel appointment form
function showCancelAppointmentForm(dateString, appointmentId) {
    const appointment = appointments[dateString].find(a => a.id === appointmentId);
    
    appointmentsContainer.innerHTML = `
        <div class="cancel-appointment-form">
            <h3 class="form-title">Cancel Appointment for ${appointment.patient}</h3>
            <div class="warning-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Are you sure you want to cancel this appointment?</p>
            </div>
            <form id="cancel-appointment-form">
                <div class="form-group">
                    <label for="cancel-reason">Reason for Cancellation (required)</label>
                    <textarea id="cancel-reason" rows="3" required placeholder="Explain why you're cancelling this appointment..."></textarea>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="cancel-notify-patient" checked>
                    <label for="cancel-notify-patient">Notify patient about this cancellation</label>
                </div>
                <div class="form-actions">
                    <button type="button" id="go-back" class="cancel-btn">Go Back</button>
                    <button type="submit" class="delete-btn">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    `;
    
    // Set up form submission
    const form = document.getElementById('cancel-appointment-form');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        cancelAppointment(dateString, appointmentId);
    });
    
    // Set up go back button
    document.getElementById('go-back').addEventListener('click', () => {
        showEditAppointmentForm(dateString, appointmentId);
    });
}

// Function to convert AM/PM to 24-hour time
function convertTo24Hour(timeStr) {
    const [time, modifier] = timeStr.split(' ');
    let [hours, minutes] = time.split(':');
    
    if (hours === '12') {
        hours = '00';
    }
    
    if (modifier === 'PM') {
        hours = parseInt(hours, 10) + 12;
    }
    
    return `${hours}:${minutes}`;
}

// Function to update an appointment
function updateAppointment(dateString, appointmentId) {
    const timeInput = document.getElementById('edit-appointment-time');
    const reasonInput = document.getElementById('edit-appointment-reason');
    const durationInput = document.getElementById('edit-appointment-duration');
    const typeInput = document.getElementById('edit-appointment-type');
    const notesInput = document.getElementById('edit-appointment-notes');
    const changeReasonInput = document.getElementById('edit-change-reason');
    const notifyPatientCheckbox = document.getElementById('edit-notify-patient');
    
    // Format time to AM/PM format
    const timeValue = timeInput.value;
    const timeParts = timeValue.split(':');
    let hours = parseInt(timeParts[0]);
    const minutes = timeParts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    const formattedTime = `${hours}:${minutes} ${ampm}`;
    
    // Find the appointment to update
    const appointmentIndex = appointments[dateString].findIndex(a => a.id === appointmentId);
    
    // Store old values for notification
    const oldAppointment = {...appointments[dateString][appointmentIndex]};
    
    // Update the appointment
    appointments[dateString][appointmentIndex] = {
        ...appointments[dateString][appointmentIndex],
        time: formattedTime,
        reason: reasonInput.value,
        duration: durationInput.value,
        type: typeInput.value,
        notes: notesInput.value
    };
    
    // Re-render calendar to show the updated appointment
    renderCalendar(currentDate);
    
    // Show notification if checked
    if (notifyPatientCheckbox.checked) {
        showNotification('success', `Patient ${oldAppointment.patient} has been notified about the appointment change.`);
        
        // In a real app, you would send this to your backend
        console.log(`Notification sent to ${oldAppointment.patient} at ${oldAppointment.phone}`);
        console.log(`Message: Your appointment has been modified. New time: ${formattedTime}. Reason: ${changeReasonInput.value}`);
    }
    
    // Show the updated appointment details
    showAppointmentDetails(dateString, appointmentId);
}

// Function to cancel an appointment
function cancelAppointment(dateString, appointmentId) {
    const cancelReasonInput = document.getElementById('cancel-reason');
    const notifyPatientCheckbox = document.getElementById('cancel-notify-patient');
    
    // Find the appointment to cancel
    const appointmentIndex = appointments[dateString].findIndex(a => a.id === appointmentId);
    const appointment = appointments[dateString][appointmentIndex];
    
    // Remove the appointment
    appointments[dateString].splice(appointmentIndex, 1);
    
    // If no more appointments on this date, remove the date from appointments
    if (appointments[dateString].length === 0) {
        delete appointments[dateString];
    }
    
    // Re-render calendar
    renderCalendar(currentDate);
    
    // Show notification if checked
    if (notifyPatientCheckbox.checked) {
        showNotification('success', `Patient ${appointment.patient} has been notified about the appointment cancellation.`);
        
        // In a real app, you would send this to your backend
        console.log(`Notification sent to ${appointment.patient} at ${appointment.phone}`);
        console.log(`Message: Your appointment has been cancelled. Reason: ${cancelReasonInput.value}`);
    }
    
    // Close the overlay or show appointments for the date if there are any left
    if (appointments[dateString] && appointments[dateString].length > 0) {
        showAppointments(dateString);
    } else {
        overlay.classList.remove('active');
    }
}

// Function to show the add appointment form
function showAddAppointmentForm(dateString) {
    selectedDateForAdd = dateString;
    overlayDate.textContent = new Date(dateString).toLocaleDateString('en-US', { 
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
    
    appointmentsContainer.innerHTML = `
        <div class="add-appointment-form">
            <h3 class="form-title">Add New Appointment</h3>
            <form id="appointment-form">
                <div class="form-group">
                    <label for="appointment-time">Time</label>
                    <input type="time" id="appointment-time" required>
                </div>
                <div class="form-group">
                    <label for="appointment-patient">Patient Name</label>
                    <input type="text" id="appointment-patient" required>
                </div>
                <div class="form-group">
                    <label for="appointment-reason">Reason</label>
                    <input type="text" id="appointment-reason" required>
                </div>
                <div class="form-group">
                    <label for="appointment-duration">Duration</label>
                    <select id="appointment-duration" required>
                        <option value="15 min">15 minutes</option>
                        <option value="30 min" selected>30 minutes</option>
                        <option value="45 min">45 minutes</option>
                        <option value="60 min">60 minutes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="appointment-type">Type</label>
                    <select id="appointment-type" required>
                        <option value="regular">Regular</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="appointment-phone">Phone Number</label>
                    <input type="tel" id="appointment-phone" required>
                </div>
                <div class="form-group">
                    <label for="appointment-notes">Notes</label>
                    <textarea id="appointment-notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancel-appointment" class="cancel-btn">Cancel</button>
                    <button type="submit" class="submit-btn">Save Appointment</button>
                </div>
            </form>
        </div>
    `;
    
    // Hide the "Add Appointment" button in the footer since we're already in add mode
    addAppointmentBtn.style.display = 'none';
    
    // Set up form submission
    const form = document.getElementById('appointment-form');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        saveAppointment(dateString);
    });
    
    // Set up cancel button
    const cancelBtn = document.getElementById('cancel-appointment');
    cancelBtn.addEventListener('click', () => {
        if (appointments[dateString] && appointments[dateString].length > 0) {
            showAppointments(dateString);
        } else {
            overlay.classList.remove('active');
        }
    });
    
    overlay.classList.add('active');
}

// Function to save a new appointment
function saveAppointment(dateString) {
    const timeInput = document.getElementById('appointment-time');
    const patientInput = document.getElementById('appointment-patient');
    const reasonInput = document.getElementById('appointment-reason');
    const durationInput = document.getElementById('appointment-duration');
    const typeInput = document.getElementById('appointment-type');
    const phoneInput = document.getElementById('appointment-phone');
    const notesInput = document.getElementById('appointment-notes');
    
    // Format time to AM/PM format
    const timeValue = timeInput.value;
    const timeParts = timeValue.split(':');
    let hours = parseInt(timeParts[0]);
    const minutes = timeParts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    const formattedTime = `${hours}:${minutes} ${ampm}`;
    
    const newAppointment = {
        time: formattedTime,
        patient: patientInput.value,
        reason: reasonInput.value,
        duration: durationInput.value,
        type: typeInput.value,
        phone: phoneInput.value,
        notes: notesInput.value,
        id: generateId()
    };
    
    // Add to appointments object
    if (!appointments[dateString]) {
        appointments[dateString] = [];
    }
    appointments[dateString].push(newAppointment);
    
    // Re-render calendar to show the new appointment
    renderCalendar(currentDate);
    
    // Show notification
    showNotification('success', `Appointment for ${newAppointment.patient} has been added.`);
    
    // Show the appointments for this date
    showAppointments(dateString);
}

// Function to show notification
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 3000);
}

// Event listeners
prevMonthButton.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar(currentDate);
});

nextMonthButton.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar(currentDate);
});

closeOverlayButton.addEventListener('click', () => {
    overlay.classList.remove('active');
});

// Close overlay when clicking outside
overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
        overlay.classList.remove('active');
    }
});

// Initialize calendar
renderCalendar(currentDate);