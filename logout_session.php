<?php
session_start();
require_once 'Login/config.php';

// Security: Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login/login.php");
    exit;
}

// Get the ID of the session the user wants to log out
$session_id_to_delete = $_GET['id'] ?? 0;
$current_user_id = (int)$_SESSION['user_id'];

if ($session_id_to_delete > 0) {
    // CRITICAL: Make sure the user is only deleting THEIR OWN sessions.
    // A user should not be able to log out another user.
    $stmt = $mysqli->prepare("DELETE FROM active_sessions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $session_id_to_delete, $current_user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the dashboard to see the updated list
header("Location: dashboard.php");
exit;
?>