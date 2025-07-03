<?php
// We must start the session to access session variables
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Web Chat</title>
    <style>
        /* --- Basic Reset & Font --- */
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

        /* --- Form Container --- */
        .form-container {
            background-color: #1f2937; /* Dark gray */
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
        }

        /* --- Headings and Text --- */
        h1 { font-size: 2rem; font-weight: 800; text-align: center; margin-bottom: 1.5rem; }
        label { display: block; text-align: left; font-weight: 500; margin-bottom: 0.5rem; }
        .subtext { font-size: 0.8rem; text-align: left; color: #9ca3af; margin-top: 0.25rem; }
        .link { color: #3b82f6; text-decoration: none; font-weight: 500; }
        .link:hover { text-decoration: underline; }
        .login-link { text-align: center; margin-top: 1.5rem; }

        /* --- Form Elements --- */
        .form-group { margin-bottom: 1rem; }
        input[type="text"], input[type="email"], input[type="password"], input[type="date"] {
            width: 100%;
            padding: 0.75rem;
            background-color: #374151; /* Lighter gray */
            border: 2px solid #4b5563;
            border-radius: 8px;
            color: #e5e7eb;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #3b82f6; }
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
        .message-box { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; }
        .success { background-color: #059669; color: #d1fae5; }
        .error { background-color: #be123c; color: #fecdd3; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Create Your Account</h1>

        <?php
            // Display feedback messages from the session
            if (isset($_SESSION['message'])) {
                echo '<div class="message-box ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            }
        ?>

        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="username">Display Name</label>
                <input type="text" id="username" name="username" placeholder="e.g., John Doe" required>
            </div>
            <div class="form-group">
                <label for="public_id">Public ID</label>
                <input type="text" id="public_id" name="public_id" placeholder="e.g., @johndoe" required>
                <p class="subtext">This is your unique, shareable username.</p>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">Show</button>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="form-group">
                <label for="dob">Date of Birth</label>
                <input type="date" id="dob" name="dob" required>
            </div>
            <button type="submit" class="submit-btn">Sign Up</button>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="login.php" class="link">Sign In</a></p>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggler = field.nextElementSibling; // Gets the button next to the input
            if (field.type === "password") {
                field.type = "text";
                toggler.textContent = "Hide";
            } else {
                field.type = "password";
                toggler.textContent = "Show";
            }
        }
    </script>
</body>
</html>