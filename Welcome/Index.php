<?php
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    require_once('db-config/connection.php');
    
    $date = $_GET['date'] ?? date('Y-m-d');
    $date = $conn->real_escape_string($date);
    
    $sql = "SELECT COUNT(appointment_id) as count FROM appointments 
            WHERE DATE(appointment_date) = '$date'";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo json_encode(['count' => $row['count']]);
    } else {
        echo json_encode(['error' => 'Query failed']);
    }
    
    $conn->close();
    exit(); // Stop execution after sending JSON
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack - Health Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
</head>
<body>

<!-- Place this right after the <body> tag -->
<div id="resetModal" class="modal-overlay" style="
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(6px);
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
">
  <div class="modal-content" style="
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 2rem;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
    color: #f0f0f0;
    backdrop-filter: blur(20px);
    position: relative;
    animation: fadeSlideIn 0.4s ease-out;
  ">
    <button id="reset-close" style="
      position: absolute;
      top: 16px; right: 16px;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #ccc;
      cursor: pointer;
      transition: color 0.3s;
    ">&times;</button>

    <div style="text-align: center; margin-bottom: 1.2rem;">
      <svg style="width: 48px; height: 48px; color: #38bdf8; margin-bottom: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
      </svg>
      <h2 style="margin-bottom: 0.3rem;">Reset Password</h2>
      <p id="entered-email-display" style="font-size: 0.9rem; color: #bbb;"></p>
    </div>

    <form id="reset-form">
      <input type="hidden" id="reset-email" name="email">

      <div style="margin-bottom: 1.2rem;">
        <label style="display: block; margin-bottom: 0.4rem; font-size: 0.85rem;">New Password</label>
        <input type="password" name="new_password" required style="
          width: 100%;
          padding: 12px;
          background: rgba(255, 255, 255, 0.08);
          border: 1px solid rgba(255,255,255,0.2);
          color: #f9fafb;
          border-radius: 8px;
          font-size: 0.95rem;
        ">
      </div>

      <div style="margin-bottom: 1.4rem;">
        <label style="display: block; margin-bottom: 0.4rem; font-size: 0.85rem;">Confirm Password</label>
        <input type="password" name="confirm_password" required style="
          width: 100%;
          padding: 12px;
          background: rgba(255, 255, 255, 0.08);
          border: 1px solid rgba(255,255,255,0.2);
          color: #f9fafb;
          border-radius: 8px;
          font-size: 0.95rem;
        ">
      </div>

      <div style="display: flex; gap: 10px; margin-top: 1.5rem;">
        <button type="submit" style="
          flex: 1;
          background: linear-gradient(135deg, #0ea5e9, #38bdf8);
          color: white;
          padding: 12px;
          border: none;
          border-radius: 8px;
          font-weight: 500;
          cursor: pointer;
          transition: background 0.3s;
        ">
          Change Password
        </button>
        <button type="button" id="reset-close" style="
          flex: 1;
          background: transparent;
          color: #ccc;
          border: 1px solid rgba(255,255,255,0.2);
          padding: 12px;
          border-radius: 8px;
          cursor: pointer;
          font-weight: 500;
        ">
          Cancel
        </button>
      </div>
    </form>

    <div id="reset-msg" style="
      margin-top: 1.5rem;
      font-size: 0.85rem;
      text-align: center;
      color: #facc15;
    "></div>
  </div>
</div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-heartbeat me-2"></i>MediTrack
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-signup" id="login-btn">Login</button>
                    <button class="btn btn-login" id="signup-btn">Sign Up</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content fade-in">
                        <h1>We Take Care of<br><span style="color: var(--primary-color);">Your Health</span></h1>
                        <p class="subtitle">Advanced healthcare management system providing comprehensive medical solutions with cutting-edge technology and personalized patient care.</p>
                        <div class="d-flex gap-3 flex-wrap">
                            <button class="btn btn-primary-custom">Get Started</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image position-relative" style="margin-left: 40px;">
                        <img src="../images/image (2).jpg" alt="Healthcare Team" class="img-fluid">

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="section-title fade-in">About MediTrack</h2>
                    <p class="section-subtitle fade-in">We are dedicated to revolutionizing healthcare through innovative technology and compassionate care</p>
                </div>
            </div>
            <div class="row mb-5">
                <div class="col-lg-6 fade-in">
                    <div class="pe-lg-4">
                        <h3 class="mb-4">Leading Healthcare Innovation</h3>
                        <p class="mb-4">MediTrack is at the forefront of healthcare technology, providing comprehensive health management solutions that empower patients and healthcare providers alike. Our platform integrates cutting-edge medical technology with personalized patient care.</p>
                        <p class="mb-4">We believe in making healthcare accessible, efficient, and patient-centered. Our team of medical professionals and technology experts work together to create solutions that improve health outcomes and enhance the patient experience.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>24/7 Patient Monitoring</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Electronic Health Records</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Telemedicine Solutions</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Preventive Care Programs</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6 fade-in">
                    <img src="../images/image2.jpg" alt="Medical Team" class="img-fluid rounded-4">
                </div>
            </div>
            <div class="row" style="margin-top: 60px;">
                <div class="col-md-4 fade-in">
                    <div class="stats-card">
                        <div class="stats-number">50K+</div>
                        <h5>Patients Served</h5>
                        <p class="text-muted">Providing quality healthcare to thousands of patients worldwide</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="stats-card">
                        <div class="stats-number">200+</div>
                        <h5>Medical Professionals</h5>
                        <p class="text-muted">Expert doctors and healthcare specialists in our network</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="stats-card">
                        <div class="stats-number">98%</div>
                        <h5>Patient Satisfaction</h5>
                        <p class="text-muted">High satisfaction rate from our comprehensive care approach</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="section-title fade-in">Our Specialties</h2>
                    <p class="section-subtitle fade-in">Comprehensive healthcare services designed to meet all your medical needs</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h5>Cardiology</h5>
                        <p>Comprehensive heart care including diagnostics, treatment, and prevention of cardiovascular diseases.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h5>Neurology</h5>
                        <p>Advanced neurological care for brain, spine, and nervous system disorders with latest technology.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-bone"></i>
                        </div>
                        <h5>Orthopedics</h5>
                        <p>Expert bone, joint, and muscle care including surgery and rehabilitation services.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                           <i class="fa-solid fa-person-dots-from-line"></i></i>
                        </div>
                        <h5>Dermatology </h5>
                        <p>Skin, hair, and nail care including treatment for acne, eczema, psoriasis, and skin cancer.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-baby"></i>
                        </div>
                        <h5>Pediatrics</h5>
                        <p>Specialized healthcare for infants, children, and adolescents with compassionate care.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fa-solid fa-stethoscope"></i>
                        </div>
                        <h5>General Practice</h5>
                        <p>Comprehensive healthcare including treatment for acute illnesses, chronic conditions, preventive care, and routine check-ups.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h5>Ophthalmology</h5>
                        <p>Complete eye care including vision correction, surgery, and treatment of eye diseases.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 fade-in">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-ambulance"></i>
                        </div>
                        <h5>Emergency Care</h5>
                        <p>24/7 emergency medical services with rapid response and critical care capabilities.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="team-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="section-title fade-in">Our Medical Team</h2>
                    <p class="section-subtitle fade-in">Meet our experienced healthcare professional doctors dedicated to your wellbeing</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4 fade-in">
                    <div class="doctor-card">
                        <div class="doctor-image d-flex align-items-center justify-content-center">
                            <img src="../images/dr-elie-chammas.jpg" alt="Dr. Sarah Johnson" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div class="doctor-info">
                            <h5>Dr. Elie Chammas</h5>
                            <p class="text-primary mb-2">Chief Cardiologist</p>
                            <p class="text-muted">15+ years experience in cardiovascular medicine and interventional cardiology.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 fade-in">
                    <div class="doctor-card">
                        <div class="doctor-image d-flex align-items-center justify-content-center">
                            <img src="../images/atweh.jpg" alt="Dr. Michael Chen" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div class="doctor-info">
                            <h5>Dr. Samir Atweh</h5>
                            <p class="text-primary mb-2">Neurologist</p>
                            <p class="text-muted">Specialist in neurological disorders with expertise in brain and spine conditions.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4 fade-in">
                    <div class="doctor-card">
                        <div class="doctor-image d-flex align-items-center justify-content-center">
                            <img src="../images/R.jpg" alt="Dr. Emily Rodriguez" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div class="doctor-info">
                            <h5>Dr. Rami Mahfouz</h5>
                            <p class="text-primary mb-2">Pediatrician</p>
                            <p class="text-muted">Dedicated to providing comprehensive healthcare for children and adolescents.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="section-title text-white fade-in">Contact Us</h2>
                    <p class="section-subtitle text-white-50 fade-in">Get in touch with us for appointments and inquiries</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-4 mb-4 fade-in">
                    <div class="contact-card h-100">
                        <div class="service-icon mx-auto mb-3" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5>Phone</h5>
                        <p>+961 01 830 300</p>
                        <p>24/7 Emergency Line</p>
                    </div>
                </div>
                <div class="col-lg-4 mb-4 fade-in">
                    <div class="contact-card h-100">
                        <div class="service-icon mx-auto mb-3" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5>Email</h5>
                        <p>info@meditrack.com</p>
                        <p>support@meditrack.com</p>
                    </div>
                </div>
                <div class="col-lg-4 mb-4 fade-in">
                    <div class="contact-card h-100">
                        <div class="service-icon mx-auto mb-3" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5>Location</h5>
                        <p>Ministry of Public Health</p>
                        <p>Bir Hassan, Al-Jnah, near Ogero<br>Beirut - Lebanon</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
     <footer class="footer" style="background: linear-gradient(135deg, rgba(248, 250, 252, 0.9), rgba(241, 245, 249, 0.9)); backdrop-filter: blur(10px); border-top: 1px solid rgba(0,0,0,0.05);">
        <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-heartbeat me-2" style="font-size: 1.8rem; color: #2c3e50;;"></i>
                    <h4 class="mb-0" style="color: var(--secondary-color); font-weight: 700;">MediTrack</h4>
                </div>
                <p class="text-muted mb-4" style="max-width: 300px;">Advanced healthcare management system providing comprehensive medical solutions.</p>
                <div class="social-links d-flex gap-3">
                    <a href="#" class="d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(8, 145, 178, 0.1); color: var(--secondary-color);">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(8, 145, 178, 0.1); color: var(--secondary-color);">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(8, 145, 178, 0.1); color: var(--secondary-color);">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(8, 145, 178, 0.1); color: var(--secondary-color);">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4">
                <h5 class="mb-3" style="color: var(--dark-color); font-weight: 600;">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#home" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Home</a></li>
                    <li class="mb-2"><a href="#about" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">About</a></li>
                    <li class="mb-2"><a href="#services" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Services</a></li>
                    <li class="mb-2"><a href="#team" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Doctors</a></li>
                    <li><a href="#contact" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Contact</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-4">
                <h5 class="mb-3" style="color: var(--dark-color); font-weight: 600;">Services</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Cardiology</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Neurology</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Orthopedics</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Pediatrics</a></li>
                    <li><a href="#" class="text-muted text-decoration-none hover-primary" style="transition: all 0.3s ease;">Emergency Care</a></li>
                </ul>
            </div>
            
            <div class="col-lg-4 col-md-4">
                <h5 class="mb-3" style="color: var(--dark-color); font-weight: 600;">Contact us</h5>
                <p class="text-muted mb-3">Contact us for more</p>
                <div class="d-flex align-items-center text-muted">
                    <i class="fas fa-phone-alt me-2" style="color: var(--primary-color);"></i>
                    <small>24/7 Support: +961 01 830 300</small>
                </div>
            </div>
        </div>
        
        <hr class="my-4" style="border-color: rgba(0,0,0,0.05);">
        
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                <p class="mb-0 small" style="color: var(--medium-gray);">
                    &copy; 2025 MediTrack. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item"><a href="#" class="text-decoration-none small hover-primary" style="color: var(--medium-gray); transition: all 0.3s ease;">Privacy Policy</a></li>
                    <li class="list-inline-item mx-2">•</li>
                    <li class="list-inline-item"><a href="#" class="text-decoration-none small hover-primary" style="color: var(--medium-gray); transition: all 0.3s ease;">Terms of Service</a></li>
                    <li class="list-inline-item mx-2">•</li>
                    <li class="list-inline-item"><a href="#" class="text-decoration-none small hover-primary" style="color: var(--medium-gray); transition: all 0.3s ease;">Sitemap</a></li>
                </ul>
            </div>
        </div>
       </div>
    </footer>


     <!-- Form Container (initially hidden) -->
    <div class="form-container2" id="formContainer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1050; display: flex; justify-content: center; align-items: center; ">
        <div style="background-color: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
            <!-- Close Button -->
            <button id="close-form-btn" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; cursor: pointer;"></button>
            
            <!-- Login Form -->
            <div class="form-side2 login-side" id="login-form-div">
                <div class="logo">
                    <i class="fas fa-heartbeat me-2"></i>MediTrack
                </div>
                <h2 class="form-title">Welcome Back</h2>
                <form id="login-form" action="login_action.php" method="post">
                    <div class="form-group">
                        <input type="email" class="form-control" name="email" placeholder="Email address" required>
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control" name="password" placeholder="Password" autocomplete="current-password" required>
                    </div>
                    <div class="forgot-password">
                        <a href="#">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn2 btn2-custom-active">Login</button>
                    <button type="button" class="btn2 btn2-custom" id="show-signup-btn">Sign Up</button>
                </form>
                <div class="social-login">
                   <a href="https://www.google.com" target="_blank" class="social-icon">
                   <i class="fab fa-google"></i>
                   </a>
                   <a href="https://www.facebook.com" target="_blank" class="social-icon">
                   <i class="fab fa-facebook-f"></i>
                   </a>
                   <a href="https://www.instagram.com" target="_blank" class="social-icon">
                   <i class="fab fa-instagram"></i>
                   </a>
                </div>
            </div>
            <!-- Signup Form --> 
            <div class="form-side signup-side" id="signup-form-div" style="display:none;">
                <div class="logo">
                    <i class="fas fa-heartbeat me-2"></i>MediTrack
                </div>
                <h2 class="form-title">Create Account</h2>
                <div id="error-container"></div>
                <form id="signup-form" action="signup_action.php" method="post">
                    <div class="form-group">
                        <input type="text" class="form-control" name="fullName" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <select class="form-control" id="userType" name="userType" required>
                            <option value="" selected disabled>Select user type</option>
                            <option value="patient">Patient</option>
                            <option value="doctor">Doctor</option>
                            <option value="secretary">Secretary</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
            
                    <!-- Doctor-specific fields (hidden by default) -->
                    <div id="doctorFields" class="hidden-fields">
                        <div class="form-group">
                            <select class="form-control" id="doctorSpecialty" name="doctorSpecialty">
                                <option value="" selected disabled>Select specialty</option>
                                <option value="Cardiology">Cardiology</option>
                                <option value="Neurology">Neurology</option>
                                <option value="Pediatrics">Pediatrics</option>
                                <option value="Orthopedics">Orthopedics</option>
                                <option value="Dermatology">Dermatology</option>
                                <option value="Ophthalmology">Ophthalmology</option>
                                <option value="General">General Practice</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" id="licenseNumber" name="licenseNumber" placeholder="License Number">
                        </div>
                    </div>
            
                    <!-- Secretary-specific fields (hidden by default) -->
                    <div id="secretaryFields" class="hidden-fields">
                        <div class="form-group">
                            <select class="form-control" id="secretarySpecialty" name="secretarySpecialty">
                                <option value="" selected disabled>Select doctor's specialty</option>
                                <option value="Cardiology">Cardiology</option>
                                <option value="Neurology">Neurology</option>
                                <option value="Pediatrics">Pediatrics</option>
                                <option value="Orthopedics">Orthopedics</option>
                                <option value="Dermatology">Dermatology</option>
                                <option value="Ophthalmology">Ophthalmology</option>
                                <option value="General">General Practice</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select class="form-control" id="assignedDoctor" name="selectedDoctorName">
                                <option value="" selected disabled>Select doctor</option>
                                <!-- Will be populated dynamically based on specialty selection -->
                            </select>
                        </div>
                    </div>
            
                    <div class="form-group">
                        <input type="email" class="form-control" id="Email" name="Email" placeholder="Email Address" required>
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control" id="Password" name="Password" placeholder="Password" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="phone" name="phone" placeholder="Phone Number" pattern="^[0-9\-]+$" required >
                    </div>

                    <div class="form-toggle-buttons">
                        <button type="button" class="btn2 btn2-custom" id="show-login-btn">Login</button>
                        <button type="submit" class="btn2 btn2-custom-active">Sign Up</button>
                    </div>
                </form>
            </div>
            

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
// Enhanced form functionality with error handling and password reset
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DOM Content Loaded - Starting initialization');
    
    // Form elements
    const formContainer = document.getElementById('formContainer');
    const loginBtn = document.getElementById('login-btn');
    const signupBtn = document.getElementById('signup-btn');
    const closeFormBtn = document.getElementById('close-form-btn');
    const showSignupBtn = document.getElementById('show-signup-btn');
    const showLoginBtn = document.getElementById('show-login-btn');
    const loginFormDiv = document.getElementById('login-form-div');
    const signupFormDiv = document.getElementById('signup-form-div');

    // Password reset elements
    const loginForm = document.getElementById('login-form');
    const forgot = document.querySelector('.forgot-password a');
    const modal = document.getElementById('resetModal');
    const resetForm = document.getElementById('reset-form');
    const emailInput = document.querySelector('#login-form input[name="email"]');
    const displayEmail = document.getElementById('entered-email-display');
    const hiddenEmail = document.getElementById('reset-email');
    const msgDiv = document.getElementById('reset-msg');
    const resetClose = document.getElementById('reset-close');

    // Create error display function
    function showErrors(errors, formType = 'signup') {
        console.log('🔍 Showing errors:', errors, 'for form:', formType);
        const targetForm = formType === 'signup' ? signupFormDiv : loginFormDiv;
        
        // Remove existing error display
        const existingError = targetForm.querySelector('.error-display');
        if (existingError) {
            existingError.remove();
        }
        
        if (errors && errors.length > 0) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-display alert alert-danger mb-3';
            errorDiv.style.cssText = 'font-size: 14px; padding: 10px; margin-bottom: 15px; border-radius: 5px;';
            
            if (errors.length === 1) {
                errorDiv.textContent = errors[0];
            } else {
                const errorList = document.createElement('ul');
                errorList.style.cssText = 'margin: 0; padding-left: 20px;';
                errors.forEach(error => {
                    const listItem = document.createElement('li');
                    listItem.textContent = error;
                    errorList.appendChild(listItem);
                });
                errorDiv.appendChild(errorList);
            }
            
            // Insert error div at the top of the form
            const form = targetForm.querySelector('form');
            if (form) {
                form.insertBefore(errorDiv, form.firstChild);
            } else {
                targetForm.insertBefore(errorDiv, targetForm.firstChild);
            }
            
            // Auto-hide error after 8 seconds
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.remove();
                }
            }, 8000);
        }
    }

    // Check for PHP session errors on page load
    function checkForErrors() {
        console.log('🔍 Checking for errors on page load');
        const urlParams = new URLSearchParams(window.location.search);
        const showForm = urlParams.get('show');
        
        if (showForm === 'signup') {
            console.log('🔍 URL parameter indicates showing signup form');
            formContainer.style.display = 'flex';
            loginFormDiv.style.display = 'none';
            signupFormDiv.style.display = 'block';
        }
    }

    // Show login form
    if (loginBtn) {
        loginBtn.addEventListener('click', function() {
            console.log('🔍 Login button clicked');
            formContainer.style.display = 'flex';
            loginFormDiv.style.display = 'block';
            signupFormDiv.style.display = 'none';
        });
    }

    // Show signup form
    if (signupBtn) {
        signupBtn.addEventListener('click', function() {
            console.log('🔍 Signup button clicked');
            formContainer.style.display = 'flex';
            loginFormDiv.style.display = 'none';
            signupFormDiv.style.display = 'block';
        });
    }

    // Close form
    if (closeFormBtn) {
        closeFormBtn.addEventListener('click', function() {
            console.log('🔍 Close form button clicked');
            formContainer.style.display = 'none';
            document.querySelectorAll('.error-display').forEach(el => el.remove());
        });
    }

    // Switch to signup form from login form
    if (showSignupBtn) {
        showSignupBtn.addEventListener('click', function() {
            console.log('🔍 Show signup button clicked');
            loginFormDiv.style.display = 'none';
            signupFormDiv.style.display = 'block';
        });
    }

    // Switch to login form from signup form
    if (showLoginBtn) {
        showLoginBtn.addEventListener('click', function() {
            console.log('🔍 Show login button clicked');
            loginFormDiv.style.display = 'block';
            signupFormDiv.style.display = 'none';
        });
    }

    // Close when clicking outside the form
    if (formContainer) {
        formContainer.addEventListener('click', function(e) {
            if (e.target === formContainer) {
                console.log('🔍 Clicked outside form - closing');
                formContainer.style.display = 'none';
                document.querySelectorAll('.error-display').forEach(el => el.remove());
            }
        });
    }

    // Password Reset Functionality
    if (forgot && modal && resetForm && emailInput && displayEmail && hiddenEmail && msgDiv && resetClose) {
        console.log('✅ All essential password reset elements found');
        
        // Handle forgot password click
        forgot.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔍 Forgot password clicked');
            
            const email = emailInput.value.trim();
            if (!email) {
                showErrors(['Please enter your email first.'], 'login');
                return;
            }
            
            // Set email in modal
            displayEmail.textContent = `Email: ${email}`;
            hiddenEmail.value = email;
            
            // Show modal
            modal.style.display = 'flex';
            console.log('🔍 Modal should be visible now');
        });

        // Close modal
        resetClose.addEventListener('click', function() {
            modal.style.display = 'none';
            msgDiv.textContent = '';
        });

        // Close when clicking outside modal
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                msgDiv.textContent = '';
            }
        });

        // Handle reset form submission
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('🔍 Reset form submitted');
            
            const formData = new FormData(resetForm);
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                msgDiv.style.color = 'red';
                msgDiv.textContent = 'Passwords do not match.';
                return;
            }
            
            // Show loading state
            const submitBtn = resetForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Processing...';
            submitBtn.disabled = true;
            
            // Submit reset request
            fetch('reset_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                msgDiv.style.color = data.success ? 'green' : 'red';
                msgDiv.textContent = data.message;
                
                if (data.success) {
                    setTimeout(() => {
                        modal.style.display = 'none';
                        resetForm.reset();
                        msgDiv.textContent = '';
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                msgDiv.style.color = 'red';
                msgDiv.textContent = 'An error occurred. Please try again.';
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    } else {
        console.error('❌ Missing essential password reset elements');
    }

    // Handle user type selection
    const userTypeSelect = document.getElementById('userType');
    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', function() {
            console.log('🔍 User type changed to:', this.value);
            const userType = this.value;
            const doctorFields = document.getElementById('doctorFields');
            const secretaryFields = document.getElementById('secretaryFields');
            
            // Hide all fields first
            if (doctorFields) doctorFields.classList.add('hidden-fields');
            if (secretaryFields) secretaryFields.classList.add('hidden-fields');
            
            // Clear validation requirements
            const allSpecialFields = document.querySelectorAll('#doctorFields input, #doctorFields select, #secretaryFields input, #secretaryFields select');
            allSpecialFields.forEach(field => {
                field.required = false;
            });
            
            // Show relevant fields based on selection
            if (userType === 'doctor' && doctorFields) {
                doctorFields.classList.remove('hidden-fields');
                const doctorSpecialty = document.getElementById('doctorSpecialty');
                const licenseNumber = document.getElementById('licenseNumber');
                if (doctorSpecialty) doctorSpecialty.required = true;
                if (licenseNumber) licenseNumber.required = true;
            } 
            else if (userType === 'secretary' && secretaryFields) {
                secretaryFields.classList.remove('hidden-fields');
                const secretarySpecialty = document.getElementById('secretarySpecialty');
                const assignedDoctor = document.getElementById('assignedDoctor');
                if (secretarySpecialty) secretarySpecialty.required = true;
                if (assignedDoctor) assignedDoctor.required = true;
            }
        });
    }

    // Phone number validation
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9\s\-\(\)\+]/g, '');
        });
    }

    // Enhanced form submission validation
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            console.log('🔍 Signup form submitted');
            e.preventDefault();
            
            // Clear previous errors
            document.querySelectorAll('.error-display').forEach(el => el.remove());
            
            const form = this;
            const formData = new FormData(form);
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Creating Account...';
            submitBtn.disabled = true;
            
            // Submit form via AJAX
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    return response.json();
                }
            })
            .then(data => {
                if (data) {
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                    } else if (data.errors) {
                        showErrors(data.errors, 'signup');
                        if (data.formData) {
                            Object.keys(data.formData).forEach(key => {
                                const field = document.querySelector(`[name="${key}"]`);
                                if (field) {
                                    field.value = data.formData[key];
                                }
                            });
                            
                            const userTypeSelect = document.getElementById('userType');
                            if (userTypeSelect && data.formData.userType) {
                                userTypeSelect.value = data.formData.userType;
                                userTypeSelect.dispatchEvent(new Event('change'));
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrors(['An error occurred. Please try again.'], 'signup');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // Update doctors dropdown when specialty is selected (for secretaries)
    const secretarySpecialtySelect = document.getElementById('secretarySpecialty');
    if (secretarySpecialtySelect) {
        secretarySpecialtySelect.addEventListener('change', async function() {
            console.log('🔍 Secretary specialty changed to:', this.value);
            const specialty = this.value;
            const doctorSelect = document.getElementById('assignedDoctor');
            
            if (!doctorSelect) return;
            
            doctorSelect.innerHTML = '<option value="" selected disabled>Loading doctors...</option>';
            
            if (specialty) {
                try {
                    const response = await fetch(`get_doctors.php?specialty=${encodeURIComponent(specialty)}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status}`);
                    }
                    
                    const doctors = await response.json();
                    
                    doctorSelect.innerHTML = '<option value="" selected disabled>Select doctor</option>';
                    
                    if (doctors.length > 0) {
                        doctors.forEach(doctor => {
                            const option = document.createElement('option');
                            option.value = doctor.full_name;
                            option.textContent = doctor.full_name;
                            doctorSelect.appendChild(option);
                        });
                    } else {
                        doctorSelect.innerHTML = '<option value="" selected disabled>No doctors found for this specialty</option>';
                    }
                } catch (error) {
                    console.error('Error fetching doctors:', error);
                    doctorSelect.innerHTML = '<option value="" selected disabled>Error loading doctors</option>';
                }
            } else {
                doctorSelect.innerHTML = '<option value="" selected disabled>Select specialty first</option>';
            }
        });
    }

    // Initialize error checking
    checkForErrors();

    // Scroll animations
    const fadeElements = document.querySelectorAll('.fade-in');
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });
    
    fadeElements.forEach(element => {
        fadeObserver.observe(element);
    });
    
    // Navbar background change on scroll
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
                navbar.style.padding = '0.5rem 0';
            } else {
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
                navbar.style.padding = '1rem 0';
            }
        }
    });

    console.log('✅ JavaScript initialization complete');
});
</script>
</body>
</html>