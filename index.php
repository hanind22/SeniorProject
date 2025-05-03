<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Center - Login/Signup</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <div class="form-container" id="formContainer">
        <!-- Login Form -->
        <div class="form-side login-side" id="login-form">
            <div class="logo">
                <i class="fas fa-heartbeat me-2"></i>MediTrack
            </div>
            <h2 class="form-title">Welcome Back</h2>
            <form id="login-form" action="login_action.php" method="post">
                <div class="form-group">
                    <input type="email" class="form-control" name="email" placeholder="Email address" required>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                    <span class="password-toggle" onclick="togglePassword('loginPassword', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="forgot-password">
                    <a href="#">Forgot Password?</a>
                </div>

                    <button type="submit" class="btn btn-custom-active">Login</button>
                    <button type="button" class="btn btn-custom" id="show-signup-btn">Sign Up</button>

            </form>
            <div class="social-login">
                <div class="social-icon">
                    <i class="fab fa-google"></i>
                </div>
                <div class="social-icon">
                    <i class="fab fa-facebook-f"></i>
                </div>
                <div class="social-icon">
                    <i class="fab fa-apple"></i>
                </div>
            </div>
        </div>

        <!-- Signup Form -->
        <div class="form-side signup-side" id="signup-form" style="display:none;">
            <div class="logo">
                <i class="fas fa-heartbeat me-2"></i>MediCare Center
            </div>
            <h2 class="form-title">Create Account</h2>
            <form id="signup-form" action="signup_action.php" method="post">
                <div class="form-group">
                    <input type="text" class="form-control" name="fullName" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <select class="form-control" id="userType" name="userType" required>
                        <option value="" selected disabled>Select user type</option>
                        <option value="patient">Patient</option>
                        <option value="doctor">Doctor</option>
                    </select>
                </div>
        
                <!-- Doctor-specific fields (hidden by default) -->
                <div id="doctorFields" style="display: none;">
                    <div class="form-group">
                        <select class="form-control" id="doctorSpecialty" name="doctorSpecialty" required>
                            <option value="" selected disabled>Select specialty</option>
                            <option value="cardiology">Cardiology</option>
                            <option value="neurology">Neurology</option>
                            <option value="pediatrics">Pediatrics</option>
                            <option value="orthopedics">Orthopedics</option>
                            <option value="dermatology">Dermatology</option>
                            <option value="general">General Practice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="licenseNumber" name="licenseNumber" placeholder="License Number" required>
                    </div>
                </div>
        
                <div class="form-group">
                    <input type="email" class="form-control" id="Email" name="Email" placeholder="Email Address" required>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" id="Password" name="Password" placeholder="Password" required>
                    <span class="password-toggle" onclick="togglePassword('signupPassword', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" id="phone" name="phone" placeholder="Phone Number" pattern="^[0-9\-]+$" required >
                </div>

                <div class="form-toggle-buttons">
                    <button type="button" class="btn btn-custom" id="show-login-btn">Login</button>
                    <button type="submit" class="btn btn-custom-active">Sign Up</button>
                </div>
            </form>
        </div>

        <!-- Image Side with Animations -->
        <div class="image-side" id="imageSide">
            <div class="pulse-circle"></div>
            <div class="pulse-circle" style="animation-delay: 2s;"></div>
            
            <div class="medical-cross">
                <div class="cross-horizontal"></div>
                <div class="cross-vertical"></div>
            </div>
            
            <h2><i> Your Health Is Our Priority </i></h2>
            
            <div class="heartbeat-line">
                <svg viewBox="0 0 600 100" class="heartbeat">
                    <path d="M0,50 L50,50 L70,20 L90,80 L110,20 L130,80 L150,20 L170,80 L190,50 L240,50 L260,20 L280,80 L300,20 L320,80 L340,20 L360,80 L380,50 L600,50"></path>
                </svg>
            </div>
                
            <div class="medical-symbols">
                <i class="fas fa-pills symbol" style="top: 10%; left: 20%; font-size: 24px; animation-duration: 15s; animation-delay: 0s;"></i>
                <i class="fas fa-heartbeat symbol" style="top: 30%; left: 80%; font-size: 28px; animation-duration: 18s; animation-delay: 1s;"></i>
                <i class="fas fa-prescription-bottle-alt symbol" style="top: 70%; left: 30%; font-size: 20px; animation-duration: 20s; animation-delay: 4s;"></i>
                <i class="fas fa-stethoscope symbol" style="top: 20%; left: 60%; font-size: 26px; animation-duration: 17s; animation-delay: 6s;"></i>
                <i class="fas fa-user-md symbol" style="top: 60%; left: 70%; font-size: 22px; animation-duration: 19s; animation-delay: 8s;"></i>
                <i class="fas fa-hospital symbol" style="top: 80%; left: 20%; font-size: 24px; animation-duration: 16s; animation-delay: 10s;"></i>
                <i class="fas fa-syringe symbol" style="top: 40%; left: 10%; font-size: 20px; animation-duration: 21s; animation-delay: 3s;"></i>
                <i class="fas fa-notes-medical symbol" style="top: 15%; left: 50%; font-size: 22px; animation-duration: 14s; animation-delay: 5s;"></i>
                <i class="fas fa-pills symbol" style="top: 20%; left: 40%; font-size: 25px; animation-duration: 30s; animation-delay: 0s;"></i>
                <i class="fas fa-lungs symbol" style="top: 50%; left: 80%; font-size: 28px; animation-duration: 22s; animation-delay: 7s;"></i>
                <i class="fas fa-brain symbol" style="top: 75%; left: 45%; font-size: 26px; animation-duration: 23s; animation-delay: 9s;"></i>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="index.js"></script>
</body>
</html>