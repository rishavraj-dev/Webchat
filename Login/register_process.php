<?php
// Start the session to store messages
session_start();
// Make sure you have your database connection file
require_once 'config.php';

// --- FIXED: Updated redirect functions to use 'message' and 'message_type' ---
function redirect_with_error($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'error'; // Set type to 'error' for styling
    header("Location: register.php");
    exit();
}

function redirect_with_success($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'success'; // Set type to 'success' for styling
    header("Location: register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- 1. Get All User Input ---
    $username = trim($_POST['username']);
    $public_id = trim($_POST['public_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = $_POST['dob'];

    // --- 2. Full Validation ---
    if (empty($username) || empty($public_id) || empty($email) || empty($password) || empty($dob)) {
        redirect_with_error("All fields are required.");
    }
    if ($password !== $confirm_password) {
        redirect_with_error("Passwords do not match.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_error("Invalid email format.");
    }

    // --- 3. Age Verification (Must be 13 or older) ---
    $today = new DateTime();
    $birthDate = new DateTime($dob);
    $age = $today->diff($birthDate)->y;
    if ($age < 13) {
        redirect_with_error("You must be at least 13 years old to register.");
    }

    // --- 4. Check for Duplicates (Public ID and Email) ---
    // Use prepared statements to prevent SQL Injection
    $stmt_check_id = $mysqli->prepare("SELECT id FROM users WHERE public_id = ?");
    $stmt_check_id->bind_param("s", $public_id);
    $stmt_check_id->execute();
    $stmt_check_id->store_result();
    if ($stmt_check_id->num_rows > 0) {
        redirect_with_error("This Public ID is already taken.");
    }
    $stmt_check_id->close();

    $stmt_check_email = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check_email->bind_param("s", $email);
    $stmt_check_email->execute();
    $stmt_check_email->store_result();
    if ($stmt_check_email->num_rows > 0) {
        redirect_with_error("This email address is already registered.");
    }
    $stmt_check_email->close();

    // --- 5. Hash the Password ---
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // --- 6. Do NOT create the user yet. Stage registration details until OTP is verified ---
    // Stash the pending registration in the session (minimal & reliable without schema change)
    $_SESSION['pending_registration'] = [
        'username' => $username,
        'public_id' => $public_id,
        'email' => $email,
        'password_hash' => $password_hash,
        'dob' => $dob,
    ];

    // Proceed to OTP creation and email send flow
    {
        // On staged registration, send an email OTP and redirect to verification flow
        $user_email_for_otp = $email;

        // Store/refresh session for verification context
        $_SESSION['verification_email'] = $user_email_for_otp;
        $_SESSION['otp_sent_time'] = time();

        // Try to generate and send OTP via email
        try {
            // Clean any existing OTP for this email
            $stmt_delete = $mysqli->prepare("DELETE FROM email_verifications WHERE user_email = ?");
            if (!$stmt_delete) { throw new Exception('Failed to prepare OTP delete statement.'); }
            $stmt_delete->bind_param("s", $user_email_for_otp);
            $stmt_delete->execute();

            // Create a new 6-digit OTP valid for 10 minutes
            $otp = random_int(100000, 999999);
            $otp_str = (string)$otp;
            $stmt_insert_otp = $mysqli->prepare("INSERT INTO email_verifications (user_email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
            if (!$stmt_insert_otp) { throw new Exception('Failed to prepare OTP insert statement.'); }
            $stmt_insert_otp->bind_param("ss", $user_email_for_otp, $otp_str);
            $stmt_insert_otp->execute();

            // Send email using PHPMailer
            // Load mailer library
            require_once '../PHPMailer/Exception.php';
            require_once '../PHPMailer/PHPMailer.php';
            require_once '../PHPMailer/SMTP.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp-relay.brevo.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'user_name_here';
            $mail->Password = 'password_here';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('XXX@example.com', 'WebChat');
            $mail->addAddress($user_email_for_otp);
            $mail->isHTML(true);
            $mail->Subject = 'Verify your WebChat account';
            $mail->Body = "<h2>Your One-Time Password is:</h2><h1>{$otp}</h1><p>This code is valid for 10 minutes.</p>";
            $mail->send();

            // Redirect user straight to the verification page
            $_SESSION['message'] = 'We\'ve sent a 6-digit code to your email. Enter it to verify your account.';
            $_SESSION['message_type'] = 'success';
            header('Location: verify.php');
            exit();
        } catch (Throwable $e) {
            // If email sending fails, keep registration but ask user to verify later
            error_log('OTP email send failed: ' . $e->getMessage());
            $_SESSION['message'] = 'Registered successfully, but we could not send a verification code right now. Please open Verify and request a new code.';
            $_SESSION['message_type'] = 'error';
            header('Location: verify.php');
            exit();
        }
    }

    // Close connection
    $mysqli->close();

} else {
    // If accessed directly, redirect back
    header("Location: register.php");
    exit();
}
?>