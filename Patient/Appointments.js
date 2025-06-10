document.addEventListener('DOMContentLoaded', function() {
    // Global variables
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    let selectedDate = '';
    let appointmentsData = {};
    
    // DOM elements
    const editModal = document.getElementById('editAppointmentModal');
    const editModalCloseBtn = document.getElementById('editModalCloseButton');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const editForm = document.getElementById('edit-appointment-form');
    const modal = document.getElementById('appointmentModal');
    const closeModalBtn = document.getElementById('modalCloseButton');
    const calendarGrid = document.getElementById('calendar-grid');
    const currentMonthElement = document.getElementById('current-month');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    const appointmentOverlay = document.getElementById('appointment-overlay');
    const closeOverlayBtn = document.getElementById('close-overlay');
    const overlayDateElement = document.getElementById('overlay-date');
    const appointmentsContainer = document.getElementById('appointments-container');
    const addAppointmentBtn = document.getElementById('add-appointment-btn');
    const addAppointmentDayBtn = document.getElementById('add-appointment-day-btn');
    const appointmentDetailsModal = document.getElementById('appointment-details-modal');
    const newAppointmentModal = document.getElementById('newAppointmentModal');
    

    // Helper functions
    function showNotification(message, type = 'info') {
    let el = document.getElementById('notification');

    // Create notification div if it doesn't exist
    if (!el) {
        el = document.createElement('div');
        el.id = 'notification';

        // Create a background overlay to dim the page
        const overlay = document.createElement('div');
        overlay.id = 'notification-overlay';

        // Append overlay and notification div to body
        document.body.appendChild(overlay);
        document.body.appendChild(el);

        // Style overlay
        Object.assign(overlay.style, {
            position: 'fixed',
            top: 0,
            left: 0,
            width: '100vw',
            height: '100vh',
            backgroundColor: 'rgba(0,0,0,0.5)',  // semi-transparent black
            zIndex: 10000,
            display: 'none',
        });

        // Style notification box
        Object.assign(el.style, {
            position: 'fixed',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            padding: '20px 30px',
            borderRadius: '8px',
            color: '#fff',
            fontWeight: 'bold',
            minWidth: '300px',
            maxWidth: '80vw',
            textAlign: 'center',
            zIndex: 10001,
            display: 'none',
            boxShadow: '0 4px 10px rgba(0,0,0,0.3)',
            fontSize: '18px',
            userSelect: 'none',
            fontFamily: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
             letterSpacing: '0.5px',
        });

        // Save reference for later use
        window._notificationOverlay = overlay;
    }

    // Set background color based on type
    switch(type) {
        case 'error':
            el.style.backgroundColor = '#fd3b33';  // red
            break;
        case 'info':
            el.style.backgroundColor = '#3498db';  // blue
            break;
        case 'success':
            el.style.backgroundColor = '#2ecc71';  // green
            break;
        default:
            el.style.backgroundColor = '#333';     // default gray
    }

    el.textContent = message;

    // Show notification and overlay
    el.style.display = 'block';
    window._notificationOverlay.style.display = 'block';

    // Hide after 4 seconds
    clearTimeout(window._notificationTimeout);
    window._notificationTimeout = setTimeout(() => {
        el.style.display = 'none';
        window._notificationOverlay.style.display = 'none';
    }, 3000);
}


    function closeModal() {
        const modal = document.getElementById('appointmentModal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    function closeNewAppointmentModal() {
        newAppointmentModal.classList.remove('active');
        setTimeout(() => {
            newAppointmentModal.style.display = 'none';
        }, 300);
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

    function getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(part => part[0]).join('').toUpperCase();
    }

    function convertTimeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    function getMonthName(monthIndex) {
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        return months[monthIndex];
    }

    function isAppointmentCancelled(appointment) {
        if (!appointment || !appointment.status) return false;
        return appointment.status.toLowerCase().includes('cancel');
    }

    // Core functions
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
            
            const formattedData = {};
            data.forEach(appointment => {
                const date = appointment.appointment_date;
                if (!formattedData[date]) {
                    formattedData[date] = [];
                }
                
                formattedData[date].push({
                    id: appointment.id,
                    time: formatTime(appointment.appointment_time),
                    formatted_time: formatTime12Hour(appointment.appointment_time),
                    formatted_date: new Date(date).toLocaleDateString(undefined, {
                       year: 'numeric',
                       month: 'long',
                       day: 'numeric'
                    }),
                    title: appointment.reason_for_visit,
                    type: appointment.appointment_type,
                    description: `With Dr. ${appointment.doctor_name} (${appointment.specialty})`,
                    status: appointment.status,
                    doctorName: appointment.doctor_name,
                    specialty: appointment.specialty,
                    purpose: appointment.reason_for_visit,
                    notes: appointment.notes || ''
                });
            });
            
            appointmentsData = formattedData;
            return formattedData;
        } catch (error) {
            console.error('Error loading appointments:', error);
            showNotification('Failed to load appointments', 'error');
            return {};
        }
    }

    function generateCalendar(month, year) {
        currentMonthElement.textContent = `${getMonthName(month)} ${year}`;
        calendarGrid.innerHTML = '';
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysFromPrevMonth = firstDay;
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        
        let dayCount = 1;
        let nextMonthDayCount = 1;
        const totalCells = Math.ceil((daysInMonth + daysFromPrevMonth) / 7) * 7;
        
        for (let i = 0; i < totalCells; i++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            
            if (i < daysFromPrevMonth) {
                const prevMonthDay = prevMonthLastDay - daysFromPrevMonth + i + 1;
                dayElement.textContent = prevMonthDay;
                dayElement.classList.add('other-month');
            } else if (dayCount <= daysInMonth) {
                dayElement.textContent = dayCount;
                
                if (dayCount === currentDate.getDate() && 
                    month === currentDate.getMonth() && 
                    year === currentDate.getFullYear()) {
                    dayElement.classList.add('today');
                }
                
                const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(dayCount).padStart(2, '0')}`;
                if (appointmentsData[dateKey]) {
                    renderCalendarAppointments(dayElement, appointmentsData[dateKey]);
                }
                
                dayElement.addEventListener('click', function(e) {
                    if (e.target.closest('.appointment-preview')) {
                        return;
                    }
                    
                    if (appointmentsData[dateKey] && appointmentsData[dateKey].length > 0) {
                        showAppointmentsForDate(dateKey);
                    } else {
                        openNewAppointmentModal(dateKey);
                    }
                });
                
                dayCount++;
            } else {
                dayElement.textContent = nextMonthDayCount;
                dayElement.classList.add('other-month');
                nextMonthDayCount++;
            }
            
            calendarGrid.appendChild(dayElement);
        }
    }

    function renderCalendarAppointments(dayElement, appointments) {
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = dayElement.textContent;
        dayElement.innerHTML = '';
        dayElement.appendChild(dayNumber);
        
        const validAppointments = appointments.filter(appt => appt && appt.type);
        
        if (validAppointments.length === 0) {
            return;
        }
        
        const previewsContainer = document.createElement('div');
        previewsContainer.className = 'appointment-previews';
        
        const visibleAppointments = validAppointments.slice(0, 2);
        visibleAppointments.forEach(appt => {
            const preview = createAppointmentPreview(appt);
            if (preview) {
                preview.setAttribute('data-appointment-id', appt.id);
                preview.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showAppointmentDetails(appt);
                });
                previewsContainer.appendChild(preview);
            }
        });
        
        dayElement.appendChild(previewsContainer);
        updateAppointmentCount(dayElement, validAppointments);
    }

    function createAppointmentPreview(appointment) {
        const isCancelled = isAppointmentCancelled(appointment);
        const typeClass = appointment.type.toLowerCase().replace(/\s+/g, '-');
        
        const preview = document.createElement('div');
        preview.className = `appointment-preview appt-${typeClass} ${isCancelled ? 'cancelled' : ''}`;
        
        const time = document.createElement('span');
        time.className = 'appointment-time';
        time.textContent = appointment.time;
        
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

    function updateAppointmentCount(dayElement, appointments) {
        const existingCount = dayElement.querySelector('.appointment-count');
        if (existingCount) {
            existingCount.remove();
        }
        
        const activeAppointments = appointments.filter(appt => {
            return appt && !isAppointmentCancelled(appt);
        });
        
        const appointmentCount = activeAppointments.length;
        
        if (appointmentCount > 0) {
            const countCircle = document.createElement('div');
            countCircle.className = 'appointment-count';
            countCircle.textContent = appointmentCount;
            dayElement.appendChild(countCircle);
            dayElement.classList.add('has-appointments');
        } else {
            dayElement.classList.remove('has-appointments');
        }
    }

    function updateAllAppointmentCounts() {
        return loadAppointments(true).then((newAppointmentsData) => {
            appointmentsData = newAppointmentsData;
            const calendarDays = document.querySelectorAll('.calendar-day:not(.other-month)');
            
            calendarDays.forEach(dayElement => {
                const dayNumber = parseInt(dayElement.querySelector('.day-number')?.textContent || dayElement.textContent);
                
                if (dayNumber) {
                    const dateKey = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayNumber).padStart(2, '0')}`;
                    const dayAppointments = appointmentsData[dateKey] || [];
                    updateAppointmentCount(dayElement, dayAppointments);
                }
            });
            
            return newAppointmentsData;
        });
    }

    function showAppointmentsForDate(date) {
        selectedDate = date;
        const displayDate = new Date(date + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        
        overlayDateElement.textContent = displayDate.toLocaleDateString('en-US', options);
        appointmentsContainer.innerHTML = '';
        
        const appointments = appointmentsData[date] || [];
        
        if (appointments.length === 0) {
            appointmentsContainer.innerHTML = '<div class="no-appointments">No appointments scheduled for this day.</div>';
        } else {
            appointments.sort((a, b) => {
                return convertTimeToMinutes(a.time) - convertTimeToMinutes(b.time);
            });
            
            appointments.forEach(appointment => {
                const card = createAppointmentCard(appointment);
                if (card) {
                    appointmentsContainer.appendChild(card);
                }
            });
        }
        
        addAppointmentDayBtn.setAttribute('data-date', date);
        appointmentOverlay.style.display = 'flex';
    }

    function createAppointmentCard(appointment) {
        const typeConfig = {
            'regular': { display: 'Regular', icon: 'fa-calendar-check', color: 'var(--regular-bg)' },
            'follow-up': { display: 'Follow Up', icon: 'fa-sync-alt', color: '#4a6fa5' },
            'urgent': { display: 'Urgent', icon: 'fa-exclamation-triangle', color: 'var(--urgent-bg)' },
            'consultation': { display: 'Consult', icon: 'fa-comments', color: '#6a4a8c' }
        };
        
        const typeKey = appointment.type ? appointment.type.toLowerCase().replace(/\s+/g, '-') : 'regular';
        const config = typeConfig[typeKey] || typeConfig['regular'];
        const isCancelled = isAppointmentCancelled(appointment);
        
        const card = document.createElement('div');
        card.className = `appt-card ${isCancelled ? 'cancelled-appointment' : ''}`;
        
        card.innerHTML = `
            <div class="appt-card__header">
                <div class="appt-time ${isCancelled ? 'cancelled' : ''}">
                    <i class="fas ${config.icon}" style="color:${config.color}"></i>
                    <span>${appointment.formatted_time}${isCancelled ? ' (CANCELLED)' : ''}</span>
                </div>
                <div class="appt-type" style="background:${config.color}20;color:${config.color}">
                    ${config.display}
                </div>
            </div>
            <div class="appt-card__body">
                <div class="patient-avatar" style="background:${config.color}20;color:${config.color}">
                    ${getInitials(appointment.doctorName)}
                </div>
                <div class="patient-info">
                    <h3 class="patient-name ${isCancelled ? 'cancelled' : ''}">Dr. ${appointment.doctorName}</h3>
                    <p class="patient-purpose ${isCancelled ? 'cancelled' : ''}">${appointment.purpose}</p>
                    <p class="patient-specialty">${appointment.specialty}</p>
                </div>
            </div>
            <div class="appt-card__footer">
                <button class="appt-action view-details" data-appointment-id="${appointment.id}">
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

    function showAppointmentDetails(appointment) {
        const isCancelled = isAppointmentCancelled(appointment);
        
        document.getElementById('modalPatientName').textContent = `Dr. ${appointment.doctorName}`;
        document.getElementById('modalPatientId').textContent = `Specialty: ${appointment.specialty}`;
        document.getElementById('modalAppointmentId').textContent = `Appointment ID: ${appointment.id}`;
        document.getElementById('modalTime').textContent = `${appointment.formatted_time}${isCancelled ? ' (CANCELLED)' : ''}`;
        document.getElementById('modalType').textContent = appointment.type;
        document.getElementById('modalPurpose').textContent = appointment.purpose;
        
        const notesSection = document.getElementById('modalNotesSection');
        if (appointment.notes && appointment.notes.trim() !== '') {
            document.getElementById('modalNotes').textContent = appointment.notes;
            notesSection.style.display = 'block';
        } else {
            notesSection.style.display = 'none';
        }
        
        modal.classList.add('active');
        
        const editBtn = document.getElementById('editAppointmentBtn');
        const cancelBtn = document.getElementById('cancelAppointmentBtn');
        
        if (!isCancelled) {
            if (editBtn) {
                editBtn.replaceWith(editBtn.cloneNode(true));
                const newEditBtn = document.getElementById('editAppointmentBtn');
                newEditBtn.onclick = function(e) {
                    e.preventDefault();
                    closeModal();
                    editAppointmentDetails(appointment);
                };
            }

            if (cancelBtn) {
               cancelBtn.addEventListener('click', (e) => {
               e.preventDefault();
               console.log("Cancel Appointment clicked");

               showCancelConfirmation(appointment); 
               loadAppointments();
               closeModal();// You must define this function
                

            });
            } else {
               console.warn("Cancel button not found in DOM at time of binding");
            }
        } else {
            if (editBtn) editBtn.style.display = 'none';
            if (cancelBtn) cancelBtn.style.display = 'none';
        }
    }

    function editAppointmentDetails(appointment) {
        const editDoctorName = document.getElementById('editDoctorName');
        const editCurrentTime = document.getElementById('editCurrentTime');
        const editAppointmentTime = document.getElementById('edit-appointment-time');
        const editAppointmentNotes = document.getElementById('edit-appointment-notes');
        const editAppointmentId = document.getElementById('edit-appointment-id');
        
        if (editDoctorName) editDoctorName.textContent = `Dr. ${appointment.doctorName}`;
        if (editCurrentTime) editCurrentTime.textContent = appointment.formatted_time;
        
        if (editAppointmentTime) {
            const timeMatch = appointment.time.match(/(\d{1,2}):(\d{2})/);
            if (timeMatch) {
                const hours = timeMatch[1].padStart(2, '0');
                const minutes = timeMatch[2];
                editAppointmentTime.value = `${hours}:${minutes}`;
            }
        }
        
        if (editAppointmentNotes) editAppointmentNotes.value = appointment.notes || '';
        if (editAppointmentId) editAppointmentId.value = appointment.id;
        
        editModal.style.display = 'flex';
        editModal.classList.add('active');
        editModal.originalAppointment = appointment;
    }

function cancelAppointment(appointment) {
    const cancelReason = document.getElementById('cancelReasonInput').value.trim();
    const form = document.getElementById('new-appointment-form');
    const cancelledBy = form ? form.dataset.patientId : null;

    if (!cancelReason) {
        alert("Please provide a reason for cancellation.");
        return;
    }

    fetch('http://localhost/fyp/Patient/cancel_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            appointment_id: appointment.id,
            cancelled_by: cancelledBy,
            cancel_reason: cancelReason
        })
    })
    .then(res => res.text())
    .then(text => {
        console.log("Raw response text:", text);
        try {
            const data = JSON.parse(text);
            console.log("Parsed response:", data);

            if (data.success) {
                // Close all modals
                document.getElementById('cancelModal').style.display = 'none';
                closeModal();
                
                // Show success message
                showNotification(data.message || 'Appointment cancelled successfully', 'success');
                
                // Force refresh the appointments and calendar
                return loadAppointments(true);
            } else {
                throw new Error(data.message || 'Failed to cancel appointment');
            }
        } catch (err) {
            console.error("JSON parse error:", err);
            throw new Error("Server response is not valid JSON:\n" + text);
        }
    })
    .then(() => {
        // Regenerate the calendar with updated data
        generateCalendar(currentMonth, currentYear);
        
        // If viewing appointments for a specific date, refresh that view too
        if (selectedDate) {
            showAppointmentsForDate(selectedDate);
        }
    })
    .catch(error => {
        console.error('Error cancelling appointment:', error);
        showNotification(error.message || 'Failed to cancel appointment', 'error');
    });
}


function showCancelConfirmation(appointment) {
    console.log("Appointment passed to showCancelConfirmation:", appointment);

    const cancelModal = document.getElementById('cancelModal');
    if (!cancelModal) {
        console.error('Cancel modal not found in DOM');
        return;
    }

    // Populate modal data
    document.getElementById('cancelDoctorName').textContent = `Dr. ${appointment.doctorName}`;
    document.getElementById('cancelDate').textContent = appointment.formatted_date || 'DATE';
    document.getElementById('cancelTime').textContent = appointment.formatted_time || 'TIME';
    cancelModal.classList.add('active');
    // Reset reason input
    document.getElementById('cancelReasonInput').value = '';

    // Show the modal
    cancelModal.style.display = 'flex';
    
    // Set up event listeners (clean up old ones first)
    const confirmBtn = document.getElementById('confirmCancelBtn');
    const denyBtn = document.getElementById('denyCancelBtn');
    const closeBtn = document.getElementById('cancelModalCloseBtn');

    // Remove previous listeners
    confirmBtn.replaceWith(confirmBtn.cloneNode(true));
    denyBtn.replaceWith(denyBtn.cloneNode(true));
    closeBtn.replaceWith(closeBtn.cloneNode(true));

    // Get fresh references after cloning
    const newConfirmBtn = document.getElementById('confirmCancelBtn');
    const newDenyBtn = document.getElementById('denyCancelBtn');
    const newCloseBtn = document.getElementById('cancelModalCloseBtn');

    newConfirmBtn.onclick = function() {
        const reason = document.getElementById('cancelReasonInput').value;
        if (!reason) {
            alert('Please provide a reason for cancellation.');
            return;
        }
        cancelModal.style.display = 'none';
        cancelAppointment(appointment);
    };

    newDenyBtn.onclick = function() {
        cancelModal.style.display = 'none';
    };

    newCloseBtn.onclick = function() {
        cancelModal.style.display = 'none';
    };
}


    function openNewAppointmentModal(selectedDate) {
        newAppointmentModal.style.display = 'block';
        setTimeout(() => newAppointmentModal.classList.add('active'), 10);
        
        document.getElementById('new-appointment-form').reset();
        document.getElementById('doctor').innerHTML = '<option value="">Select a doctor</option>';
        document.getElementById('doctor').disabled = true;
        
        if (selectedDate) {
            document.getElementById('appointment-date').value = selectedDate;
        }
        
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('appointment-date').min = today;
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

    function checkServerMessages() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            showNotification(urlParams.get('success'));
        } else if (urlParams.has('error')) {
            showNotification(urlParams.get('error'), 'error');
        }
    }

    function initCalendar() {
        updateDateTime();
        checkServerMessages();
        loadAppointments().then(() => {
            generateCalendar(currentMonth, currentYear);
        });
        
        setInterval(updateDateTime, 60000);
    }

    
    
    if (editForm) {
    editForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(editForm);
        formData.append('action', 'update_time');

        // Safe notification
        if (typeof showNotification === 'function') {
            showNotification('Updating appointment...', 'info');
        } else {
            alert('Updating appointment...');
        }

        fetch('update_appointment.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);

                if (data.success) {
                    // ✅ Show success
                    if (typeof showNotification === 'function') {
                        showNotification(data.message || 'Appointment updated successfully', 'success');
                    } else {
                        alert(data.message || 'Appointment updated successfully');
                    }

                    // ✅ Close modal after 500ms to allow message display
                    setTimeout(() => {
                        if (editModal) {
                            editModal.style.display = 'none';
                            editModal.classList.remove('active');
                        }
                    }, 500);

                    // ✅ Refresh calendar if available
                    if (typeof loadAppointments === 'function') {
                        loadAppointments(true).then(() => {
                            if (typeof generateCalendar === 'function') {
                                generateCalendar(currentMonth, currentYear);
                            }
                        });
                    } else {
                        // Fallback: reload
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                } else {
                    if (typeof showNotification === 'function') {
                        showNotification('Failed: ' + data.message, 'error');
                    } else {
                        alert('Failed: ' + data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                if (typeof showNotification === 'function') {
                    showNotification('Fetch error occurred', 'error');
                } else {
                    alert('Fetch error occurred');
                }
            });
    });
}

// ✅ Click outside to close modal
if (editModal) {
  editModal.addEventListener('click', function (e) {
    // Only close if clicking directly on the overlay (not the form inside)
    if (e.target === editModal) {
      editModal.style.display = 'none';
      editModal.classList.remove('active');
    }
  });
}


    
    closeModalBtn.addEventListener('click', () => {
        modal.classList.remove('active');
    });

    document.querySelectorAll('.appointment-preview').forEach(item => {
        item.addEventListener('click', () => {
            const patientName = item.dataset.patientName || 'Unknown';
            const patientId = item.dataset.patientId || 'N/A';
            const appointmentId = item.dataset.appointmentId || 'N/A';
            const time = item.dataset.time || 'No time specified';
            const type = item.dataset.type || 'No type specified';
            const purpose = item.dataset.purpose || 'No purpose specified';
            const notes = item.dataset.notes || '';

            document.getElementById('modalPatientName').textContent = patientName;
            document.getElementById('modalPatientId').textContent = 'Patient ID: ' + patientId;
            document.getElementById('modalAppointmentId').textContent = 'Appointment ID: ' + appointmentId;
            document.getElementById('modalTime').textContent = time;
            document.getElementById('modalType').textContent = type;
            document.getElementById('modalPurpose').textContent = purpose;
            document.getElementById('ModalNotes').textContent = notes;

            const notesSection = document.getElementById('modalNotesSection');
            if (notes.trim() !== '') {
                document.getElementById('modalNotes').textContent = notes;
                notesSection.style.display = 'block';
            } else {
                notesSection.style.display = 'none';
            }

            modal.classList.add('active');
        });
    });

    // Initialize lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    addAppointmentBtn.addEventListener('click', function(e) {
        e.preventDefault();
        newAppointmentModal.style.display = 'block';
        setTimeout(() => newAppointmentModal.classList.add('active'), 10);
        
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('appointment-date').min = today;
        document.getElementById('doctor').innerHTML = '<option value="">Select a doctor</option>';
        document.getElementById('doctor').disabled = true;
    });

    document.getElementById('speciality').addEventListener('change', function() {
        const speciality = this.value;
        const doctorSelect = document.getElementById('doctor');
        
        if (!speciality) {
            doctorSelect.innerHTML = '<option value="">Select a doctor</option>';
            doctorSelect.disabled = true;
            return;
        }
        
        doctorSelect.innerHTML = '<option value="">Loading doctors...</option>';
        doctorSelect.disabled = true;
        
        fetch(`get_doctors_by_speciality.php?speciality=${encodeURIComponent(speciality)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(doctors => {
                if (doctors.length === 0) {
                    doctorSelect.innerHTML = '<option value="">No doctors available</option>';
                    showNotification('No doctors available for this speciality', 'warning');
                } else {
                    doctorSelect.innerHTML = '<option value="">Select a doctor</option>';
                    doctors.forEach(doctor => {
                        const option = document.createElement('option');
                        option.value = doctor.doctor_id;
                        option.textContent = doctor.full_name + (doctor.title ? ', ' + doctor.title : '');
                        doctorSelect.appendChild(option);
                    });
                    doctorSelect.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error loading doctors:', error);
                doctorSelect.innerHTML = '<option value="">Error loading doctors</option>';
                showNotification('Failed to load doctors', 'error');
            });
    });

    document.getElementById('new-appointment-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('[DEBUG] Form submission started');
        
        try {
            const form = this;
            const formData = new FormData(form);
            const patientId = form.dataset.patientId;
            formData.append('patient_id', patientId);
            
            console.log('[DEBUG] FormData entries:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }

            const requiredFields = ['doctor_id', 'date', 'time', 'purpose'];
            const missingFields = requiredFields.filter(field => !formData.get(field));
            
            if (missingFields.length > 0) {
                console.error('[VALIDATION] Missing fields:', missingFields);
                showNotification('Please fill all required fields', 'error');
                return;
            }

            showNotification('Booking appointment...', 'info');
            
            const response = await fetch('book_appointment.php', {
                method: 'POST',
                body: formData
            });
            
            console.log('[DEBUG] Response status:', response.status);
            const responseData = await response.json();
            console.log('[DEBUG] Response data:', responseData);
            
            if (!response.ok) {
                throw new Error(responseData.message || 'Failed to book appointment');
            }

            // Check success flag in JSON
            if (responseData.success === false) {
                showNotification(responseData.message || 'Failed to book appointment', 'error');
                return; // Stop further execution
            }

            
            showNotification(responseData.message || 'Appointment booked successfully!');
            closeNewAppointmentModal();
            await loadAppointments(true);
            generateCalendar(currentMonth, currentYear);
            
        } catch (error) {
            console.error('[ERROR] Booking failed:', error);
            showNotification(error.message || 'An error occurred while booking', 'error');
        }
    });

    addAppointmentDayBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const date = this.getAttribute('data-date');
        
        newAppointmentModal.style.display = 'block';
        setTimeout(() => newAppointmentModal.classList.add('active'), 10);
        
        document.getElementById('new-appointment-form').reset();
        document.getElementById('doctor').innerHTML = '<option value="">Select a doctor</option>';
        document.getElementById('doctor').disabled = true;
        
        if (date) {
            document.getElementById('appointment-date').value = date;
        }
        
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('appointment-date').min = today;
    });

    newAppointmentModal.addEventListener('click', function(e) {
        if (e.target === newAppointmentModal) {
            closeNewAppointmentModal();
        }
    });

    document.getElementById('newModalCloseButton').addEventListener('click', closeNewAppointmentModal);
    document.getElementById('cancelNewAppointment').addEventListener('click', closeNewAppointmentModal);

    prevMonthBtn.addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    nextMonthBtn.addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    closeOverlayBtn.addEventListener('click', function() {
        appointmentOverlay.style.display = 'none';
    });
    
    appointmentOverlay.addEventListener('click', function(e) {
        if (e.target === appointmentOverlay) {
            appointmentOverlay.style.display = 'none';
        }
    });
    
    window.addEventListener('click', function(e) {
        if (e.target === appointmentDetailsModal) {
            appointmentDetailsModal.style.display = 'none';
        }
    });

    // Initialize the calendar
    initCalendar();
});

       
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
        window.location.href = '../Welcome/Index.php';
        
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