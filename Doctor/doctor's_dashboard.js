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

    
    // Weekly appointments chart
    const weeklyAppointmentsCtx = document.getElementById('weeklyAppointmentsChart').getContext('2d');
    const weeklyAppointmentsChart = new Chart(weeklyAppointmentsCtx, {
        type: 'bar',
        data: {
            labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' , 'Saturday'],
            datasets: [{
                label: 'Appointments',
                data: [4, 7, 5, 9, 6 , 3],
                backgroundColor: '#3498db',
                borderColor: '#2980b9',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 2
                    }
                }
            }
        }
    });
    
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