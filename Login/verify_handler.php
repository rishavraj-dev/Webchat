<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json'); // Crucial: This tells the browser we're sending JSON data.

require_once 'config.php'; // Your database connection
require_once 'vendor/autoload.php'; // Your PHPMailer installation

use ../PHPMailer\PHPMailer\PHPMailer;
use ../PHPMailer\PHPMailer\Exception;

// A reusable function to send the OTP. This is ONLY used inside this backend file.
function send_otp($mysqli, $email) {
    $otp = random_int(100000, 999999); // 6-digit OTP
    try {
        $mysqli->query("DELETE FROM email_verifications WHERE user_email = '$email'");
        $stmt = $mysqli->prepare("INSERT INTO email_verifications (user_email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'use your own SMTP username here'; // Your SMTP username
        $mail->Password = 'use your own SMTP password here'; // Your SMTP password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        $mail->setFrom('aiuser.first@gmail.com', 'Web Chat Verification');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Verification Code';
        $mail->Body = "<h2>Your One-Time Password is:</h2><h1>{$otp}</h1><p>This code is valid for 10 minutes.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP Send Error: " . $e->getMessage()); // Log error for your own review
        return false;
    }
}

// Check what action the frontend is requesting
$action = $_POST['action'] ?? '';

switch ($action) {
    // CASE 1: User submitted their Email or Public ID
    case 'send_otp':
        $identifier = trim($_POST['identifier'] ?? '');
        if (empty($identifier)) {
            echo json_encode(['status' => 'error', 'message' => 'Identifier is required.']);
            exit();
        }

        $stmt = $mysqli->prepare("SELECT email, verified FROM users WHERE email = ? OR public_id = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($user['verified'] === 'yes') {
                echo json_encode(['status' => 'already_verified', 'message' => 'This account is already verified. You can log in.']);
            } else {
                if (send_otp($mysqli, $user['email'])) {
                    $_SESSION['verification_email'] = $user['email'];
                    $_SESSION['otp_sent_time'] = time();
                    echo json_encode(['status' => 'otp_sent', 'message' => 'An OTP has been sent to ' . htmlspecialchars($user['email'])]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Could not send verification code. Please try again later.']);
                }
            }
        } else {
            echo json_encode(['status' => 'not_found', 'message' => 'No account was found with that identifier.']);
        }
        break;

    // CASE 2: User submitted the OTP
    case 'verify_otp':
        $otp = trim($_POST['otp'] ?? '');
        $email = $_SESSION['verification_email'] ?? '';

        if (empty($otp) || empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request. Please start over.']);
            exit();
        }

        $stmt = $mysqli->prepare("SELECT token FROM email_verifications WHERE user_email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 1) {
            $mysqli->query("UPDATE users SET verified = 'yes' WHERE email = '$email'");
            $mysqli->query("DELETE FROM email_verifications WHERE user_email = '$email'");
            unset($_SESSION['verification_email'], $_SESSION['otp_sent_time']);
            
            echo json_encode(['status' => 'success', 'message' => 'Account verified successfully! Redirecting...']);
        } else {
            echo json_encode(['status' => 'invalid_otp', 'message' => 'Invalid or expired OTP.']);
        }
        break;

    // CASE 3: User requested to resend the OTP
    case 'resend_otp':
        $email = $_SESSION['verification_email'] ?? '';
        if (empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid session.']);
            exit();
        }

        $time_since_last_send = time() - ($_SESSION['otp_sent_time'] ?? 0);
        if ($time_since_last_send < 90) {
            echo json_encode(['status' => 'rate_limit', 'message' => 'Please wait before requesting another code.']);
            exit();
        }

        if (send_otp($mysqli, $email)) {
            $_SESSION['otp_sent_time'] = time();
            echo json_encode(['status' => 'otp_sent', 'message' => 'A new code has been sent.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send a new code.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
        break;
}