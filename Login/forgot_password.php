<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Webchat - Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* This is the same branded dark mode theme */
        :root {
            --background-color: #121212;
            --surface-color: #1e1e1e;
            --primary-color: #3b82f6;
            --primary-hover-color: #2563eb;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #333;
        }
        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            background-color: var(--background-color);
            color: var(--text-secondary);
        }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { font-size: 3rem; font-weight: 700; color: var(--text-primary); margin: 0; }
        .header p { font-size: 1.1rem; color: var(--text-secondary); margin-top: 5px; }
        .container { width: 100%; max-width: 420px; padding: 30px; background-color: var(--surface-color); border-radius: 8px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color); }
        h2 { margin-top: 0; margin-bottom: 20px; color: var(--text-primary); text-align: center; }
        .container p { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #bbbbbb; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; box-sizing: border-box; background-color: #2c2c2c; border: 1px solid #444; border-radius: 5px; color: #e0e0e0; font-size: 16px; transition: border-color 0.2s, box-shadow 0.2s; }
        input[name="otp"] { font-size: 1.5em; text-align: center; letter-spacing: 8px; font-weight: bold; }
        input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); outline: none; }
        button { width: 100%; padding: 12px; background-color: var(--primary-color); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 10px; transition: background-color 0.2s; }
        button:hover { background-color: var(--primary-hover-color); }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 5px; text-align: center; border: 1px solid transparent; font-weight: 600; }
        .error { background-color: rgba(220, 53, 69, 0.15); color: #f8969f; border-color: #dc3545; }
        .success { background-color: rgba(25, 135, 84, 0.15); color: #7ee2a1; border-color: #198754; }
        .info { background-color: rgba(13, 202, 240, 0.15); color: #6edff6; border-color: #0dcaf0; }
        .fallback-link { margin-top: 25px; font-size: 0.9rem; }
        .fallback-link a { color: var(--text-secondary); text-decoration: none; border-bottom: 1px solid transparent; transition: all 0.2s ease-in-out; }
        .fallback-link a:hover { color: var(--text-primary); border-bottom-color: var(--text-primary); }
    </style>
</head>
<body>

    <header class="header">
        <h1>Webchat</h1>
        <p>Secure & Real-time Messaging</p>
    </header>

    <main class="container">
        <div id="message-area"></div>

        <!-- Step 1: Form to get user's identifier -->
        <div id="identifier-step">
            <h2>Reset Your Password</h2>
            <form id="identifier-form">
                <p>Enter your account's email or Public ID. We'll send you a code to reset your password.</p>
                <div class="form-group">
                    <label for="identifier">Email or Public ID</label>
                    <input type="text" name="identifier" id="identifier" required>
                </div>
                <button type="submit">Send Reset Code</button>
            </form>
        </div>

        <!-- Step 2: Form to enter OTP and new password, hidden by default -->
        <div id="reset-step" style="display: none;">
            <h2>Enter Details</h2>
            <form id="reset-form">
                <p>A code was sent to your email. Enter it below, along with your new password.</p>
                <div class="form-group">
                    <label for="otp">Reset Code (OTP)</label>
                    <input type="text" name="otp" id="otp" required maxlength="6" pattern="\d{6}">
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" required minlength="8">
                </div>
                <button type="submit">Reset Password</button>
            </form>
        </div>
    </main>
    
    <footer class="fallback-link">
        <a href="login.php">Remembered your password? Login</a>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const identifierForm = document.getElementById('identifier-form');
        const resetForm = document.getElementById('reset-form');
        const identifierStep = document.getElementById('identifier-step');
        const resetStep = document.getElementById('reset-step');
        const messageArea = document.getElementById('message-area');

        const showMessage = (message, type) => {
            messageArea.innerHTML = `<div class="message ${type}">${message}</div>`;
        };

        // Handle submission of the first form (Identifier)
        identifierForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(identifierForm);
            formData.append('action', 'send_otp');
            
            const response = await fetch('forgot_password_handler.php', { method: 'POST', body: formData });
            const result = await response.json();

            showMessage(result.message, result.status);

            if (result.status === 'otp_sent' || result.status === 'not_found') {
                identifierStep.style.display = 'none';
                resetStep.style.display = 'block';
            }
        });

        // Handle submission of the second form (OTP and New Password)
        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Client-side validation for matching passwords
            if (newPassword !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                return;
            }

            const formData = new FormData(resetForm);
            formData.append('action', 'reset_password');

            const response = await fetch('forgot_password_handler.php', { method: 'POST', body: formData });
            const result = await response.json();

            showMessage(result.message, result.status);

            if (result.status === 'success') {
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2500); // Wait 2.5 seconds then redirect
            }
        });
    });
    </script>
</body>
</html>