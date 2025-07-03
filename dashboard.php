<?php
// --- 1. SETUP AND SECURITY CHECK ---
session_start();
require_once 'Login/config.php';
if (!isset($_SESSION['loggedin'])) { header("Location: Login/login.php"); exit; }
$current_user_id = (int)$_SESSION['user_id'];
$current_session_id = session_id();

// Database-driven session validation
$stmt_validate = $mysqli->prepare("SELECT id FROM active_sessions WHERE session_id = ? AND user_id = ?");
$stmt_validate->bind_param("si", $current_session_id, $current_user_id);
$stmt_validate->execute();
if ($stmt_validate->get_result()->num_rows !== 1) {
    session_unset(); session_destroy(); session_start();
    $_SESSION['message'] = "You were logged out."; $_SESSION['message_type'] = 'error';
    header("Location: Login/login.php"); exit;
}
$stmt_validate->close();

// --- HANDLE FORM SUBMISSION for "Logout All Other" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout_all_others') {
    // Delete all sessions for this user EXCEPT the current one
    $stmt_logout = $mysqli->prepare("DELETE FROM active_sessions WHERE user_id = ? AND session_id != ?");
    $stmt_logout->bind_param("is", $current_user_id, $current_session_id);
    $stmt_logout->execute();
    // Redirect to refresh the page and show the updated list
    header("Location: dashboard.php");
    exit;
}

// --- 2. FETCH DATA FOR DISPLAY ---
// Fetch user's profile info
$user_info_stmt = $mysqli->prepare("SELECT username, public_id, email, created_at FROM users WHERE id = ?");
$user_info_stmt->bind_param("i", $current_user_id);
$user_info_stmt->execute();
$user_info = $user_info_stmt->get_result()->fetch_assoc();
$user_info_stmt->close();

// Fetch all of the user's active login sessions
$sessions_stmt = $mysqli->prepare("SELECT id, session_id, ip_address, user_agent, last_login FROM active_sessions WHERE user_id = ? ORDER BY last_login DESC");
$sessions_stmt->bind_param("i", $current_user_id);
$sessions_stmt->execute();
$active_sessions = $sessions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sessions_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Webchat</title>
    <link rel="stylesheet" href="CSS/dashboard.css">
    <style>
       
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard</h1>
        <p style="color:var(--text-secondary); margin-top:-1rem; margin-bottom:2rem;">Welcome back, <?php echo htmlspecialchars($user_info['username']); ?>!</p>
        
        <h2>Your Profile</h2>
        <div class="info-grid">
            <strong>Public ID:</strong> <span><?php echo htmlspecialchars($user_info['public_id']); ?></span>
            <strong>Email:</strong> <span><?php echo htmlspecialchars($user_info['email']); ?></span>
            <strong>Member Since:</strong> <span><?php echo date('F j, Y', strtotime($user_info['created_at'])); ?></span>
        </div>

        <h2>Active Sessions</h2>
        <div class="session-list">
            <?php foreach ($active_sessions as $session): ?>
                <div class="session-item">
                    <div class="session-details">
                        <div class="device">
                            <?php if ($session['session_id'] === $current_session_id): ?>
                                <strong>This Browser</strong>
                            <?php else: ?>
                                Other Device
                            <?php endif; ?>
                        </div>
                        <div class="ip">IP Address: <?php echo htmlspecialchars($session['ip_address']); ?></div>
                        <div class="time">Last Active: <?php echo date('F j, Y \a\t g:i A', strtotime($session['last_login'])); ?></div>
                        <div class="ip" style="font-size: 0.7rem; opacity: 0.6;"><?php echo htmlspecialchars($session['user_agent']); ?></div>
                    </div>
                    <?php if ($session['session_id'] !== $current_session_id): ?>
                        <a href="logout_session.php?id=<?php echo $session['id']; ?>" class="logout-btn" onclick="return confirm('Are you sure you want to log out this session?')">Log Out</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="logout-all-form">
            <form action="dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to log out all other devices?')">
                <input type="hidden" name="action" value="logout_all_others">
                <button type="submit" class="logout-btn">Log Out Of All Other Sessions</button>
            </form>
        </div>
        
        <a href="conversations.php" class="main-link">← Back to Chats</a>
    </div>
</body>
</html>