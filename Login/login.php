<?php
// Start the session to display feedback messages
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Webchat</title>
    <style>
        /* --- Basic Reset & Font --- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #111827; /* Dark blue-gray background */
            color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        /* --- Form Container --- */
        .form-container {
            background-color: #1f2937; /* Darker gray container */
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
        }

        /* --- Headings and Text --- */
        h1 { font-size: 2.25rem; font-weight: 700; text-align: center; margin-bottom: 2rem; color: #fff; }
        label { display: block; text-align: left; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #d1d5db; }
        .link { color: #60a5fa; text-decoration: none; font-weight: 500; }
        .link:hover { text-decoration: underline; }
        .footer-links { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: #9ca3af; }
        .forgot-password { text-align: right; font-size: 0.875rem; margin-top: 0.5rem; }

        /* --- Form Elements --- */
        .form-group { margin-bottom: 1.25rem; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.8rem 1rem;
            background-color: #374151;
            border: 1px solid #4b5563;
            border-radius: 8px;
            color: #e5e7eb;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }
        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
        }
        .submit-btn {
            width: 100%;
            padding: 0.8rem;
            background-color: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 1rem;
        }
        .submit-btn:hover { background-color: #1d4ed8; }

        /* --- Message Boxes --- */
        .message-box { padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; }
        .success { background-color: #059669; color: #d1fae5; }
        .error { background-color: #be123c; color: #fecdd3; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Sign In to Webchat</h1>

        <?php
            // Display feedback messages from session
            if (isset($_SESSION['message'])) {
                echo '<div class="message-box ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
        ?>

        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="identifier">Public ID or Email</label>
                <input type="text" id="identifier" name="identifier" placeholder="e.g., @johndoe or yourname@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.25rem; height:1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </div>
                <div class="forgot-password">
                    <a href="#" class="link">Forgot Password?</a>
                </div>
            </div>
            <button type="submit" class="submit-btn">Sign In</button>
        </form>
        <div class="footer-links">
            <p>Don't have an account? <a href="register.php" class="link">Register Now</a></p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggler = field.nextElementSibling;
            if (field.type === "password") {
                field.type = "text";
            } else {
                field.type = "password";
            }
        }
    </script>
</body>
</html>