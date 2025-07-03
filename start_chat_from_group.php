<?php
// Start session and include database configuration
session_start();
require_once 'Login/config.php';

// --- Security Check: Ensure user is logged in ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: Login/login.php");
    exit;
}

// Get the ID of the user we want to chat with from the URL (e.g., ?user_id=15)
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$current_user_id = (int)$_SESSION['user_id'];

// Make sure the user isn't trying to chat with themselves or an invalid user
if ($target_user_id <= 0 || $target_user_id === $current_user_id) {
    header("Location: conversations.php");
    exit;
}

// --- Check if a personal chat ALREADY exists between these two users ---
$sql_check = "SELECT T1.conversation_id FROM conversation_members AS T1 INNER JOIN conversation_members AS T2 ON T1.conversation_id = T2.conversation_id WHERE T1.user_id = ? AND T2.user_id = ? AND T1.conversation_id IN (SELECT id FROM conversations WHERE type = 'personal')";
$stmt_check = $mysqli->prepare($sql_check);
$stmt_check->bind_param("ii", $current_user_id, $target_user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    // Chat already exists. Get its ID and redirect the user there.
    $existing_convo = $result_check->fetch_assoc();
    header("Location: conversations.php?conversation_id=" . $existing_convo['conversation_id']);
    exit;
} else {
    // --- If we reach here, no chat exists. Let's create one! ---
    $mysqli->begin_transaction();
    try {
        $stmt_create_convo = $mysqli->prepare("INSERT INTO conversations (type) VALUES ('personal')");
        $stmt_create_convo->execute();
        $new_convo_id = $mysqli->insert_id;

        $stmt_add_members = $mysqli->prepare("INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt_add_members->bind_param("iiii", $new_convo_id, $current_user_id, $new_convo_id, $target_user_id);
        $stmt_add_members->execute();

        $mysqli->commit();
        header("Location: conversations.php?conversation_id=" . $new_convo_id);
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        // If there's an error, just send them back to the main page.
        header("Location: conversations.php");
        exit;
    }
}
?>