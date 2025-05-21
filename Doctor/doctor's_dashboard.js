document.addEventListener('DOMContentLoaded', function() {
    // Update date and time
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

    //For the chart

    updateDateTime();
    setInterval(updateDateTime, 60000);
    fetchWeeklyAppointments();


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

function fetchWeeklyAppointments() {
    fetch('get_weekly_appointments.php')
        .then(response => response.json())
        .then(data => {
            renderChart(data);
        })
        .catch(error => console.error('Error fetching chart data:', error));
}

function renderChart(appointmentData) {
    const ctx = document.getElementById('weeklyAppointmentsChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            datasets: [{
                label: 'Appointments',
                data: appointmentData,
                backgroundColor: 'rgba(52, 152, 219, 0.2)',
                borderColor: '#3498db',
                borderWidth: 3,
                pointBackgroundColor: '#2980b9',
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 20
                    }
                }
            }
        }
    });
}



    // Simulate real-time alerts
    function simulateAlert() {
        const alertBadge = document.querySelector('.alert-badge');
        const currentCount = parseInt(alertBadge.textContent);
        
        if (Math.random() > 0.7) {
            alertBadge.textContent = currentCount + 1;
            
            // Create notification element
            const notificationList = document.querySelector('.notification-list');
            const newNotification = document.createElement('li');
            newNotification.className = 'notification-item';
            newNotification.innerHTML = `
                <div class="notification-icon urgent">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div class="notification-content">
                    <h4>New Alert: Patient Status Change</h4>
                    <p>David Miller's heart rate has increased significantly</p>
                </div>
                <span class="notification-time">Just now</span>
            `;
            
            // Add to top of list
            notificationList.insertBefore(newNotification, notificationList.firstChild);
            
            // Remove oldest notification if more than 5
            if (notificationList.children.length > 5) {
                notificationList.removeChild(notificationList.lastChild);
            }
            
            // Add flash effect
            newNotification.style.backgroundColor = '#fff8e1';
            setTimeout(() => {
                newNotification.style.backgroundColor = 'transparent';
                newNotification.style.transition = 'background-color 1s ease';
            }, 100);
        }
    }
    
    // Simulate alerts every 30-60 seconds
    setInterval(simulateAlert, Math.random() * 30000 + 30000);
});