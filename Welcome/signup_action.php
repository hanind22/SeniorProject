<?php
session_start();
require_once('../db-config/connection.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['Invalid request method']]);
    } else {
        header("Location: index.php");
    }
    exit();
}

try {
    // Sanitize input
    $full_name = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $password = $_POST['Password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $user_type = strtolower(trim($_POST['userType'] ?? ''));

    $specialty = '';
    $license_number = '';
    $assigned_doctor_id = '';
    $selected_doctor_name = '';

    if ($user_type === 'doctor') {
        $specialty = trim($_POST['doctorSpecialty'] ?? '');
        $license_number = trim($_POST['licenseNumber'] ?? '');
    } elseif ($user_type === 'secretary') {
        $specialty = trim($_POST['secretarySpecialty'] ?? '');
        $selected_doctor_name = trim($_POST['selectedDoctorName'] ?? '');
    }

    // Input validation
    $errors = [];
    if (!$full_name) $errors[] = "Full name is required";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (!$password || strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (!$phone) $errors[] = "Phone number is required";
    if (!in_array($user_type, ['doctor','secretary','patient','admin'])) $errors[] = "Valid user type is required";

    if ($user_type === 'doctor') {
        if (!$specialty) $errors[] = "Specialty is required for doctors";
        if (!$license_number) $errors[] = "License number is required for doctors";
    } elseif ($user_type === 'secretary') {
        if (!$specialty) $errors[] = "Doctor's specialty is required for secretaries";
        if (!$selected_doctor_name) $errors[] = "Doctor selection is required for secretaries";
    }

    // Email uniqueness check
    if (empty($errors) && $user_type !== 'secretary') {
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) $errors[] = "Email already registered";
        $stmt->close();
    }

    $existing_user_id = null;

    // Doctor verification
    if ($user_type === 'doctor' && empty($errors)) {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.email
            FROM users u
            JOIN doctors d ON u.user_id = d.user_id
            WHERE TRIM(LOWER(u.full_name))=TRIM(LOWER(?))
              AND d.license_number=?
              AND TRIM(LOWER(d.specialty))=TRIM(LOWER(?))
              AND u.user_type='doctor'
        ");
        $stmt->bind_param("sss", $full_name, $license_number, $specialty);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res->num_rows) {
            $errors[] = "Doctor verification failed.";
        } else {
            $row = $res->fetch_assoc();
            if ($row['email']) {
                $errors[] = "This doctor is already registered.";
            } else {
                $existing_user_id = $row['user_id'];
            }
        }
        $stmt->close();
    }

    $secretary_user_id = null;

    // Secretary verification
    if ($user_type === 'secretary' && empty($errors)) {
        // Find doctor_id from name + specialty
        $stmt = $conn->prepare("
            SELECT u.user_id AS doc_user_id, d.doctor_id
            FROM users u
            JOIN doctors d ON u.user_id = d.user_id
            WHERE TRIM(LOWER(u.full_name))=TRIM(LOWER(?))
              AND TRIM(LOWER(d.specialty))=TRIM(LOWER(?))
              AND u.user_type='doctor'
        ");
        $stmt->bind_param("ss", $selected_doctor_name, $specialty);
        $stmt->execute();
        $dres = $stmt->get_result();
        if (!$dres->num_rows) {
            $errors[] = "Selected doctor not found.";
        } else {
            $drow = $dres->fetch_assoc();
            $doctor_id = $drow['doctor_id'];
            $stmt->close();

            // Check pre-registered secretary record
            $stmt2 = $conn->prepare("
                SELECT user_id
                FROM secretary
                WHERE doctor_id = ?
            ");
            $stmt2->bind_param("i", $doctor_id);
            $stmt2->execute();
            $sres = $stmt2->get_result();
            if (!$sres->num_rows) {
                $errors[] = "You are not registered as a secretary for this doctor.";
            } else {
                $srow = $sres->fetch_assoc();
                $secretary_user_id = $srow['user_id'];

                // Get existing data
                $stmt3 = $conn->prepare("
                    SELECT full_name, email, password_hash
                    FROM users
                    WHERE user_id = ?
                ");
                $stmt3->bind_param("i", $secretary_user_id);
                $stmt3->execute();
                $eres = $stmt3->get_result();
                $stmt3->close();

                if (!$eres->num_rows) {
                    $errors[] = "Secretary record not found.";
                } else {
                    $userRow = $eres->fetch_assoc();
                    if ($userRow['password_hash']) {
                        $errors[] = "This secretary is already registered.";
                    } elseif (
                        strtolower(trim($userRow['full_name'])) !== strtolower(trim($full_name))
                        || strtolower(trim($userRow['email'])) !== strtolower(trim($email))
                    ) {
                        $errors[] = "Name or email doesn't match registered record.";
                    }
                }
            }
            $stmt2->close();
        }
    }

    // Return errors if any
    if (!empty($errors)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
        } else {
            $_SESSION['signup_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: index.php?show=signup");
        }
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    if ($user_type === 'doctor') {
        $stmt = $conn->prepare("
            UPDATE users SET email=?, password_hash=?, phone_number=?, created_at=NOW() WHERE user_id=?
        ");
        $stmt->bind_param("sssi", $email, $hashed, $phone, $existing_user_id);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("UPDATE doctors SET approved=1 WHERE user_id=?");
        $stmt2->bind_param("i", $existing_user_id);
        $stmt2->execute();
        $stmt2->close();

        $user_id = $existing_user_id;

    } elseif ($user_type === 'secretary') {
        $stmt = $conn->prepare("
            UPDATE users SET password_hash=?, phone_number=?, created_at=NOW() WHERE user_id=?
        ");
        $stmt->bind_param("ssi", $hashed, $phone, $secretary_user_id);
        $stmt->execute();
        $stmt->close();

        $user_id = $secretary_user_id;

    } else {
        $stype = ucfirst($user_type);
        $stmt = $conn->prepare("
            INSERT INTO users (full_name,email,password_hash,phone_number,user_type,created_at)
            VALUES (?,?,?,?,?,NOW())
        ");
        $stmt->bind_param("sssss", $full_name, $email, $hashed, $phone, $stype);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();
    }

    $conn->commit();

    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['user_type'] = ucfirst($user_type);
    $_SESSION['success'] = "Registration successful! Welcome to MediTrack.";

    $redirect_paths = [
        'doctor'=>'../Doctor/doctor_dashboard.php',
        'secretary'=>'../Secretary/secretary_dashboard.php',
        'patient'=>'../Patient/patient_dashboard.php',
        'admin'=>'../Admin/MainPage.php'
    ];
    $url = $redirect_paths[$user_type] ?? 'index.php';

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'redirect'=>$url]);
    } else {
        header("Location: $url");
    }
    exit();

} catch (Exception $e) {
    $conn->rollback();
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'errors'=>['A system error occurred']]);
    } else {
        $_SESSION['error'] = "A system error occurred";
        header("Location: index.php?show=signup");
    }
    exit();
}
