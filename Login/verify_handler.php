<?php
// --- ERROR REPORTING (FOR DEBUGGING) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');


// --- REQUIREMENTS ---
// 1. For config.php: It's in the SAME folder.
require_once 'config.php';

// 2. For PHPMailer: Go UP ONE level ('../') and find the files directly inside the PHPMailer folder.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../PHPMailer/Exception.php';
require_once '../PHPMailer/PHPMailer.php';
require_once '../PHPMailer/SMTP.php';


// A reusable function to send the OTP.
function send_otp($mysqli, $email) {
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
        $mail->Username = '';
        $mail->Password = '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('XXXX@gmail.com', 'WebChat');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Verification Code';
        $mail->Body = "<h2>Your One-Time Password is:</h2><h1>{$otp}</h1><p>This code is valid for 10 minutes.</p>";
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log("OTP Send Error: " . $e->getMessage());
        return false;
    }
}


// --- MAIN LOGIC ROUTER ---
$action = $_POST['action'] ?? '';
$response = [];

try {
    switch ($action) {
        case 'send_otp':
            $identifier = trim($_POST['identifier'] ?? '');
            if (empty($identifier)) {
                throw new Exception('Identifier is required.');
            }

            $stmt = $mysqli->prepare("SELECT email, verified FROM users WHERE email = ? OR public_id = ? LIMIT 1");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if ($user['verified'] === 'yes') {
                    $response = ['status' => 'already_verified', 'message' => 'This account is already verified.'];
                } else {
                    if (send_otp($mysqli, $user['email'])) {
                        $_SESSION['verification_email'] = $user['email'];
                        $_SESSION['otp_sent_time'] = time();
                        $response = ['status' => 'otp_sent', 'message' => 'An OTP has been sent to the associated email.'];
                    } else {
                        throw new Exception('Could not send verification code. Please try again later.');
                    }
                }
            } else {
                $response = ['status' => 'not_found', 'message' => 'No account was found.'];
            }
            break;

        case 'verify_otp':
            $otp = trim($_POST['otp'] ?? '');
            $email = $_SESSION['verification_email'] ?? '';
            if (empty($otp) || empty($email)) {
                throw new Exception('Invalid request or session expired. Please start over.');
            }

            $stmt = $mysqli->prepare("SELECT token FROM email_verifications WHERE user_email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 1) {
                $stmt_update = $mysqli->prepare("UPDATE users SET verified = 'yes' WHERE email = ?");
                $stmt_update->bind_param("s", $email);
                $stmt_update->execute();

                $stmt_delete = $mysqli->prepare("DELETE FROM email_verifications WHERE user_email = ?");
                $stmt_delete->bind_param("s", $email);
                $stmt_delete->execute();
                
                unset($_SESSION['verification_email'], $_SESSION['otp_sent_time']);
                $response = ['status' => 'success', 'message' => 'Account verified!'];
            } else {
                $response = ['status' => 'invalid_otp', 'message' => 'Invalid or expired OTP.'];
            }
            break;

        case 'resend_otp':
            $email = $_SESSION['verification_email'] ?? '';
            if (empty($email)) {
                throw new Exception('Invalid session.');
            }

            if ((time() - ($_SESSION['otp_sent_time'] ?? 0)) < 90) {
                $response = ['status' => 'rate_limit', 'message' => 'Please wait before requesting another code.'];
            } else {
                if (send_otp($mysqli, $email)) {
                    $_SESSION['otp_sent_time'] = time();
                    $response = ['status' => 'otp_sent', 'message' => 'A new code has been sent.'];
                } else {
                    throw new Exception('Failed to send a new code.');
                }
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