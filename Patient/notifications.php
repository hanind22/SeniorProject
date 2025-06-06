<style>
    /* Notification Bell Styles (kept exactly as you specified) */
    .notification-bell {
        position: fixed;
        top: 20px;
        right: 30px;
        z-index: 1000;
    }

    .bell-btn {
        background: #4a90e2;
        border: 1px solid rgba(0, 64, 148, 0.69);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        color: white;
        font-size: 20px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        position: relative;
    }

    .bell-btn:hover {
        background: #357abd;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
    }

    .notification-dropdown {
        position: absolute;
        top: 60px;
        right: 0;
        width: 380px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        border: 1px solid #e1e8ed;
    }

    .notification-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-header {
        padding: 20px;
        border-bottom: 1px solid #e1e8ed;
        background: #f8f9fa;
        border-radius: 12px 12px 0 0;
    }

    .dropdown-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 18px;
        font-weight: 600;
    }

    .dropdown-notifications {
        max-height: 550px;
        overflow-y: auto;
    }

    .dropdown-notification-item {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f3f4;
        transition: background-color 0.2s ease;
        cursor: pointer;
    }

    .dropdown-notification-item:hover {
        background-color: #f8f9fa;
    }

    .dropdown-notification-item:last-child {
        border-bottom: none;
    }

    .notification-content-wrapper {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .notification-icon-wrapper {
        flex-shrink: 0;
    }

    .notification-icon.urgent {
        color: #e74c3c;
        background: #fee;
        padding: 8px;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-icon.normal {
        color: #4a90e2;
        background: #e8f4fd;
        padding: 8px;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-text {
        flex: 1;
    }

    .notification-text h4 {
        margin: 0 0 4px 0;
        font-size: 14px;
        font-weight: 600;
        color: #2c3e50;
        line-height: 1.3;
    }

    .notification-text p {
        margin: 0;
        font-size: 13px;
        color: #7f8c8d;
        line-height: 1.4;
    }

    .notification-time-stamp {
        font-size: 11px;
        color: #95a5a6;
        margin-top: 6px;
    }

    .dropdown-footer {
        padding: 16px 20px;
        text-align: center;
        background: #f8f9fa;
        border-radius: 0 0 12px 12px;
    }

    .view-all-btn {
        color: #4a90e2;
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        transition: color 0.2s ease;
    }

    .view-all-btn:hover {
        color: #357abd;
    }

    #notifications-section {
        display: none;
    }

    @media (max-width: 768px) {
        .notification-bell {
            top: 15px;
            right: 15px;
        }

        .notification-dropdown {
            width: 320px;
            right: -10px;
        }
    }

    @media (max-width: 480px) {
        .notification-dropdown {
            width: 280px;
            right: -20px;
        }
    }

    /* New additions for functionality */
    .dropdown-notification-item.unread {
        background-color:rgba(224, 226, 228, 0.3);
    }
    
    .dropdown-notification-item.read {
        background-color: white;
    }
    
    .dropdown-notification-item.cancellation {
        border-left: 3px solid #e74c3c;
    }
    
    .dropdown-notification-item.cancellation .notification-text h4 {
        color: #e74c3c;
    }
    
    .notification-dropdown.expanded {
        max-height: 70vh;
    }
      /* New styles for the expanded view */
    .notification-dropdown.expanded .dropdown-notifications {
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .notification-dropdown:not(.expanded) .dropdown-notifications {
        max-height: 200px;
        overflow: hidden;
    }
    
    .view-all-btn {
        cursor: pointer;
    }
</style>

<!-- Notification Bell (kept exactly as you specified) -->
<div class="notification-bell">
    <button class="bell-btn" id="notificationBell">
        <i class="fas fa-bell"></i>
        <span class="notification-badge" id="notificationBadge">0</span>
    </button>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="dropdown-header">
            <h3>Notifications</h3>
        </div>
        <div class="dropdown-notifications" id="notificationList">
           <!-- Dynamic notifications will be injected here -->
        </div>
        <div class="dropdown-footer">
            <a href="#" class="view-all-btn" id="viewAllBtn">View All Notifications</a>
        </div>
    </div>
</div>

<script>
// Self-executing function to avoid conflicts
(function() {
    // Wait for full DOM load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Notification system initializing...');
        
        const bellBtn = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        const list = document.getElementById('notificationList');
        const badge = document.getElementById('notificationBadge');
        const viewAllBtn = document.getElementById('viewAllBtn');
        let isExpanded = false;

        // Debug: Verify elements exist
        if (!bellBtn || !dropdown || !list || !badge || !viewAllBtn) {
            console.error('Missing notification elements:', {
                bellBtn, dropdown, list, badge, viewAllBtn
            });
            return;
        }

        // Click handler for bell button
        bellBtn.addEventListener('click', async function(e) {
            e.stopPropagation();
            console.log('Bell button clicked');
            
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                await fetchNotifications();
            }
        });

        // View All functionality
        viewAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            isExpanded = !isExpanded;
            
            if (isExpanded) {
                dropdown.classList.add('expanded');
                viewAllBtn.textContent = 'Show Less';
            } else {
                dropdown.classList.remove('expanded');
                viewAllBtn.textContent = 'View All Notifications';
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                dropdown.classList.remove('show');
                dropdown.classList.remove('expanded');
                isExpanded = false;
                viewAllBtn.textContent = 'View All Notifications';
            }
        });

        // Fetch notifications
        async function fetchNotifications() {
            try {
                console.log('Fetching notifications...');
                const response = await fetch('get_notifications.php');
                const data = await response.json();
                console.log('Notifications data:', data);
                renderNotifications(data);
            } catch (error) {
                console.error('Error fetching notifications:', error);
                list.innerHTML = '<div class="dropdown-notification-item">Error loading notifications</div>';
            }
        }

        // Render notifications
        function renderNotifications(data) {
            if (data.error || !data || data.length === 0) {
                list.innerHTML = '<div class="dropdown-notification-item">No notifications</div>';
                updateBadge(0);
                return;
            }

            list.innerHTML = '';
            let unreadCount = 0;

            data.forEach(notification => {
                if (!notification.is_read) unreadCount++;

                const isCancellation = notification.message.toLowerCase().includes('cancel');
                const item = document.createElement('div');
                item.className = `dropdown-notification-item ${notification.is_read ? 'read' : 'unread'} ${isCancellation ? 'cancellation' : ''}`;
                
                item.innerHTML = `
                    <div class="notification-content-wrapper">
                        <div class="notification-icon-wrapper">
                            <div class="notification-icon ${isCancellation ? 'urgent' : 'normal'}">
                                <i class="fas fa-${isCancellation ? 'exclamation-circle' : 'info-circle'}"></i>
                            </div>
                        </div>
                        <div class="notification-text">
                            <h4>${notification.message || 'No message'}</h4>
                            <p>From: ${notification.sender_name || 'System'}</p>
                            <div class="notification-time-stamp">${formatTime(notification.created_at)}</div>
                        </div>
                    </div>
                `;

                if (!notification.is_read) {
                    item.addEventListener('click', () => markAsRead(notification.id, item));
                }

                list.appendChild(item);
            });

            updateBadge(unreadCount);
            viewAllBtn.style.display = data.length > 3 ? 'block' : 'none';
        }

        // Mark notification as read
        async function markAsRead(notificationId, element) {
            try {
                console.log('Marking notification as read:', notificationId);
                const response = await fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                });
                
                if (response.ok) {
                    element.classList.remove('unread');
                    element.classList.add('read');
                    updateBadge(parseInt(badge.textContent) - 1);
                }
            } catch (error) {
                console.error('Error marking as read:', error);
            }
        }

        function updateBadge(count) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }

        function formatTime(dateString) {
            try {
                const date = new Date(dateString);
                return date.toLocaleString();
            } catch {
                return 'Just now';
            }
        }

        // Initial fetch of unread count
        fetchUnreadCount();

        async function fetchUnreadCount() {
            try {
                const response = await fetch('get_unread_count.php');
                const data = await response.json();
                
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                
                updateBadge(data.unreadCount || 0);
            } catch (error) {
                console.error('Error fetching unread count:', error);
            }
        }

        console.log('Notification system initialized');
    });
})();
</script>