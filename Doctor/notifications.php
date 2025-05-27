    <style>
        /* Notification Bell Styles */
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
            max-height: 400px;
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

        /* Hide the original notifications section */
        #notifications-section {
            display: none;
        }

        /* Responsive design */
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
    </style>
    
    <!-- Notification Bell -->
    <div class="notification-bell">
        <button class="bell-btn" id="notificationBell">
            <i class="fas fa-bell"></i>
            <span class="notification-badge">3</span>
        </button>
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="dropdown-header">
                <h3>Notifications</h3>
            </div>
            <div class="dropdown-notifications">
                <div class="dropdown-notification-item">
                    <div class="notification-content-wrapper">
                        <div class="notification-icon-wrapper">
                            <div class="notification-icon urgent">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                        </div>
                        <div class="notification-text">
                            <h4>Emergency: Patient Vitals Alert</h4>
                            <p>Sarah Johnson's blood pressure has reached critical levels</p>
                            <div class="notification-time-stamp">10 mins ago</div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-notification-item">
                    <div class="notification-content-wrapper">
                        <div class="notification-icon-wrapper">
                            <div class="notification-icon normal">
                                <i class="fas fa-flask"></i>
                            </div>
                        </div>
                        <div class="notification-text">
                            <h4>Test Results Ready</h4>
                            <p>Blood work results for Michael Smith are available for review</p>
                            <div class="notification-time-stamp">1 hour ago</div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-notification-item">
                    <div class="notification-content-wrapper">
                        <div class="notification-icon-wrapper">
                            <div class="notification-icon normal">
                                <i class="fas fa-calendar"></i>
                            </div>
                        </div>
                        <div class="notification-text">
                            <h4>Appointment Confirmation</h4>
                            <p>New appointment scheduled with Emily Davis at 3:30 PM</p>
                            <div class="notification-time-stamp">2 hours ago</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dropdown-footer">
                <a href="notifications.php" class="view-all-btn">View All Notifications</a>
            </div>
        </div>
    </div>
