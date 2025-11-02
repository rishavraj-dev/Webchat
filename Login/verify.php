<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify your account - Web Chat</title>
    <style>
        /* Match register.php dark UI */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #1f2937 0%, #0f172a 100%);
            color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .container {
            background-color: #1f2937;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: 1rem; }
        p { color: #9ca3af; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        label { display: block; text-align: left; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"]{
            width: 100%;
            padding: 0.75rem;
            background-color: #374151;
            border: 2px solid #4b5563;
            border-radius: 8px;
            color: #e5e7eb;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #3b82f6; }
        input[name="otp"] { font-size: 1.4rem; text-align: center; letter-spacing: 8px; }
        button {
            width: 100%;
            padding: 0.8rem;
            background-color: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 0.5rem;
        }
        button:hover { background-color: #1d4ed8; }
        #resend-btn { background-color: #6b7280; }
        #resend-btn:hover { background-color: #4b5563; }
        #resend-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .message { padding: 0.75rem; margin-bottom: 1rem; border-radius: 8px; text-align: center; }
        .error { background-color: #be123c; color: #fecdd3; }
        .success { background-color: #059669; color: #d1fae5; }
        .info { background-color: #374151; color: #e5e7eb; }
        #timer { margin-top: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Message area will be populated by JavaScript -->
        <div id="message-area"></div>

        <!-- Step 1: Form to get user's identifier -->
        <div id="identifier-step">
            <h2>Verify Your Account</h2>
            <p>Enter your email or Public ID to receive a 6-digit code.</p>
            <form id="identifier-form">
                <div class="form-group">
                    <label for="identifier">Email or Public ID</label>
                    <input type="text" name="identifier" id="identifier" required>
                </div>
                <button type="submit">Continue</button>
            </form>
        </div>

        <!-- Step 2: Form to enter OTP, hidden by default -->
        <div id="otp-step" style="display: none;">
            <h2>Check Your Email</h2>
            <p id="otp-message"></p>
            <form id="otp-form">
                <div class="form-group">
                    <label for="otp">Enter Verification Code</label>
                    <input type="text" name="otp" id="otp" required maxlength="6" pattern="\d{6}">
                </div>
                <button type="submit">Verify</button>
            </form>
            <button id="resend-btn" disabled>Resend Code</button>
            <div id="timer"></div>
        </div>
    </div>

    <script>
    // Preload verification context from PHP session (if coming right after registration)
    const prefilledEmail = <?php echo json_encode($_SESSION['verification_email'] ?? null); ?>;
    const otpSentAt = <?php echo isset($_SESSION['otp_sent_time']) ? (int)$_SESSION['otp_sent_time'] : 'null'; ?>;
    const flashMessage = <?php echo json_encode($_SESSION['message'] ?? null); ?>;
    const flashType = <?php echo json_encode($_SESSION['message_type'] ?? null); ?>;
    <?php
    // Clear one-time flash message so refresh won't duplicate
    unset($_SESSION['message'], $_SESSION['message_type']);
    ?>
    document.addEventListener('DOMContentLoaded', () => {
        const identifierForm = document.getElementById('identifier-form');
        const otpForm = document.getElementById('otp-form');
        const resendBtn = document.getElementById('resend-btn');

        const identifierStep = document.getElementById('identifier-step');
        const otpStep = document.getElementById('otp-step');
        
        const messageArea = document.getElementById('message-area');
        const otpMessage = document.getElementById('otp-message');
        const timerEl = document.getElementById('timer');

        let resendInterval;

        // Function to display messages
        const showMessage = (message, type) => {
            messageArea.innerHTML = `<div class="message ${type}">${message}</div>`;
        };

        // Function to start the resend timer
        const startTimer = (initialRemaining = 90) => {
            let countdown = initialRemaining;
            resendBtn.disabled = true;
            const update = () => {
                if (countdown <= 0) {
                    clearInterval(resendInterval);
                    timerEl.textContent = '';
                    resendBtn.disabled = false;
                } else {
                    timerEl.textContent = `You can request a new code in ${countdown}s.`;
                    countdown--;
                }
            };
            update();
            resendInterval = setInterval(update, 1000);
        };

        // If we already have a verification session, show OTP step immediately
        if (prefilledEmail) {
            identifierStep.style.display = 'none';
            otpStep.style.display = 'block';
            otpMessage.textContent = 'We sent a 6-digit code to the email associated with your account. The code expires in 10 minutes.';
            // Compute remaining time for resend (90s window)
            let remaining = 90;
            if (otpSentAt) {
                const elapsed = Math.floor(Date.now() / 1000) - otpSentAt;
                remaining = Math.max(0, 90 - elapsed);
            }
            startTimer(remaining);
            // Show any flash message
            if (flashMessage && flashType) {
                messageArea.innerHTML = `<div class="message ${flashType}">${flashMessage}</div>`;
            } else {
                messageArea.innerHTML = `<div class="message success">An OTP has been sent to your email.</div>`;
            }
        } else if (flashMessage && flashType) {
            // If no prefill but we have a message, show it
            messageArea.innerHTML = `<div class="message ${flashType}">${flashMessage}</div>`;
        }

        // Handle submission of the first form (Identifier)
        identifierForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(identifierForm);
            formData.append('action', 'send_otp');
            
            const response = await fetch('verify_handler.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            showMessage(result.message, result.status === 'otp_sent' ? 'success' : 'error');

            if (result.status === 'otp_sent') {
                identifierStep.style.display = 'none';
                otpStep.style.display = 'block';
                otpMessage.textContent = 'We sent a 6-digit code to the email associated with your account. The code expires in 10 minutes.';
                startTimer();
            }
        });

        // Handle submission of the second form (OTP)
        otpForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(otpForm);
            formData.append('action', 'verify_otp');

            const response = await fetch('verify_handler.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.status === 'success') {
                showMessage(result.message, 'success');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000); // Redirect to login after 2 seconds
            } else {
                showMessage(result.message, 'error');
            }
        });

        // Handle the Resend button click
        resendBtn.addEventListener('click', async () => {
            clearInterval(resendInterval); // Stop any existing timer
            resendBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'resend_otp');

            const response = await fetch('verify_handler.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            showMessage(result.message, result.status === 'otp_sent' ? 'success' : 'error');
            
            if (result.status === 'otp_sent' || result.status === 'rate_limit') {
                startTimer();
            } else {
                resendBtn.disabled = false; // Re-enable if there was an unknown error
            }
        });
    });
    </script>
</body>
</html>