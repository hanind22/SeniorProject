<?php
session_start();
require_once('../db-config/connection.php');

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: index.php");
        exit();
    }

    try {
        // Prepare SQL to get user
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect based on user type
                if ($user['user_type'] === 'Doctor') {
                     $_SESSION['doctor_id'] = $user['user_id'];
                    header("Location: /fyp/Doctor/doctor_dashboard.php");
                } elseif($user['user_type'] === 'Secretary'){
                    $_SESSION['secretary_id'] = $user['user_id'];
                    header("Location: ../Secretary/Secretary_dashboard.php");
                }
                elseif($user['user_type'] === 'Admin'){
                    $_SESSION['admin_id'] = $user['user_id'];
                    header("Location: /fyp/Admin/Admin_Dashboard.php");
                }
                else {
                     $_SESSION['patient_id'] = $user['user_id'];
                    header("Location: ../Patient/patient_dashboard.php");
                }
                exit();
            } else {
                // Invalid password
                $_SESSION['error'] = "Invalid email or password";
                header("Location: index.php");
                exit();
            }
        } else {
            // User not found
            $_SESSION['error'] = "Invalid email or password";
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again later.";
        header("Location: index.php");
        exit();
    }
} else {
    // Not a POST request
    header("Location: index.php");
    exit();
}
?>