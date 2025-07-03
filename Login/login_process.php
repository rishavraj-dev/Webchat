<?php
// We don't start the session here yet. We'll start it manually after checking.
require_once 'config.php';

// The maximum number of devices a user can be logged into.
define('MAX_SESSIONS', 2);

// Function to redirect back to login with an error
function redirect_with_error($message) {
    session_start();
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'error';
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    if (empty($identifier) || empty($password)) {
        redirect_with_error("All fields are required.");
    }

    $sql = "SELECT id, username, public_id, password_hash FROM users WHERE public_id = ? OR email = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
            // --- LOGIN SUCCESS - NOW MANAGE SESSIONS ---
            
            $user_id = $user['id'];

            // 1. Count current active sessions for this user.
            $stmt_count = $mysqli->prepare("SELECT id FROM active_sessions WHERE user_id = ?");
            $stmt_count->bind_param("i", $user_id);
            $stmt_count->execute();
            $stmt_count->store_result();
            $active_session_count = $stmt_count->num_rows;

            // 2. If the user is at or over the limit, delete the oldest one.
            if ($active_session_count >= MAX_SESSIONS) {
                // Find the ID of the oldest session record for this user and delete it.
                $stmt_delete_oldest = $mysqli->prepare("DELETE FROM active_sessions WHERE user_id = ? ORDER BY last_login ASC LIMIT 1");
                $stmt_delete_oldest->bind_param("i", $user_id);
                $stmt_delete_oldest->execute();
            }

            // 3. Now it's safe to create a new session.
            // We must start the session *before* we can get its ID.
            session_start();
            session_regenerate_id(true); // Create a new, clean session ID

            // 4. Store the new session in our database.
            $new_session_id = session_id();
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            $stmt_insert_session = $mysqli->prepare("INSERT INTO active_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt_insert_session->bind_param("isss", $user_id, $new_session_id, $ip_address, $user_agent);
            $stmt_insert_session->execute();

            // 5. Store user data in the PHP session as normal.
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['public_id'] = $user['public_id'];

            // Redirect to the main application
            header("Location: ../conversations.php");
            exit();

        } else {
            redirect_with_error("Invalid credentials.");
        }
    } else {
        redirect_with_error("Invalid credentials.");
    }
} else {
    header("Location: login.php");
    exit();
}
?>