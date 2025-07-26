<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Verification</title>
    <style>
        body { font-family: -apple-system, sans-serif; display: flex; justify-content: center; padding-top: 50px; background-color: #f0f2f5; }
        .container { width: 420px; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; }
        h2 { margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; text-align: left;}
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; }
        input[name="otp"] { font-size: 1.5em; text-align: center; letter-spacing: 8px; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        #resend-btn { background-color: #6c757d; }
        #resend-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .error { background-color: #f8d7da; color: #721c24; }
        .success { background-color: #d4edda; color: #155724; }
        .info { background-color: #e2e3e5; color: #383d41; }
        #timer { margin-top: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Message area will be populated by JavaScript -->
        <div id="message-area"></div>

        <!-- Step 1: Form to get user's identifier -->
        <div id="identifier-step">
            <h2>Verify Your Account</h2>
            <p>Enter your email or Public ID to begin.</p>
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
        const startTimer = () => {
            let countdown = 90;
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