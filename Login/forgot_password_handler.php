<?php
// --- ERROR REPORTING (FOR DEBUGGING) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// --- REQUIREMENTS ---
// 1. For config.php: Assumes this handler is in the /Login folder.
require_once 'config.php';

// 2. For PHPMailer: Assumes PHPMailer is in the root htdocs and we are in /Login.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../PHPMailer/Exception.php';
require_once '../PHPMailer/PHPMailer.php';
require_once '../PHPMailer/SMTP.php';

// This is the same OTP sending function from the verify handler, perfectly reusable.
function send_reset_otp($mysqli, $email) {
    $otp = random_int(100000, 999999);
    try {
        $stmt_delete = $mysqli->prepare("DELETE FROM email_verifications WHERE user_email = ?");
        $stmt_delete->bind_param("s", $email);
        $stmt_delete->execute();
        
        $stmt_insert = $mysqli->prepare("INSERT INTO email_verifications (user_email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $otp_str = (string)$otp;
        $stmt_insert->bind_param("ss", $email, $otp_str);
        $stmt_insert->execute();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
         $mail->Username = 'use your SMTP username here';
        $mail->Password = 'use your SMTP password here';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('aiuser.first@gmail.com', 'Webchat Security');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code';
        $mail->Body = "<h2>Password Reset Request</h2><p>Here is your One-Time Password (OTP) to reset your password:</p><h1>{$otp}</h1><p>This code is valid for 10 minutes. If you did not request this, please ignore this email.</p>";
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log("Reset OTP Send Error: " . $e->getMessage());
        return false;
    }
}


// --- MAIN LOGIC ROUTER ---
$action = $_POST['action'] ?? '';
$response = [];

try {
    switch ($action) {
        // ACTION 1: Send the OTP to the user's email
        case 'send_otp':
            $identifier = trim($_POST['identifier'] ?? '');
            if (empty($identifier)) { throw new Exception('Identifier is required.'); }

            $stmt = $mysqli->prepare("SELECT email FROM users WHERE email = ? OR public_id = ? LIMIT 1");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (send_reset_otp($mysqli, $user['email'])) {
                    // Store the email we are resetting in the session for the next step.
                    $_SESSION['reset_email'] = $user['email'];
                    $response = ['status' => 'otp_sent', 'message' => 'A reset code has been sent to your email.'];
                } else {
                    throw new Exception('Could not send reset code. Please try again later.');
                }
            } else {
                // Security: Don't reveal if an email exists or not. Give a generic message.
                $response = ['status' => 'not_found', 'message' => 'If an account with that identifier exists, a reset code has been sent.'];
            }
            break;

        // ACTION 2: Verify OTP and reset the password
        case 'reset_password':
            $otp = trim($_POST['otp'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $email = $_SESSION['reset_email'] ?? '';

            if (empty($otp) || empty($new_password) || empty($email)) {
                throw new Exception('Missing required information. Please start over.');
            }
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }

            $stmt = $mysqli->prepare("SELECT token FROM email_verifications WHERE user_email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 1) {
                // OTP is valid. Hash the new password and update the database.
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $password_hash, $email);
                $update_stmt->execute();

                // Clean up by deleting the used OTP and the session variable.
                $mysqli->prepare("DELETE FROM email_verifications WHERE user_email = ?")->execute([$email]);
                unset($_SESSION['reset_email']);
                
                $response = ['status' => 'success', 'message' => 'Password has been reset successfully!'];
            } else {
                $response = ['status' => 'invalid_otp', 'message' => 'Invalid or expired reset code.'];
            }
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);