<?php
session_start();
require_once 'Login/config.php';

header('Content-Type: application/json');

function send_json_error($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    send_json_error('Authentication required.');
}
$current_user_id = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$message_id = isset($data['message_id']) ? (int)$data['message_id'] : 0;
$emoji = isset($data['emoji']) ? (string)$data['emoji'] : '';

if ($message_id <= 0 || empty($emoji)) {
    send_json_error('Invalid message ID or emoji.');
}

// 1. Verify user has access to the message they are reacting to
$stmt_check = $mysqli->prepare(
    "SELECT 1 FROM messages m 
     JOIN conversation_members cm ON m.conversation_id = cm.conversation_id 
     WHERE m.id = ? AND cm.user_id = ?"
);
$stmt_check->bind_param("ii", $message_id, $current_user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows === 0) {
    send_json_error('Permission denied to react to this message.');
}
$stmt_check->close();


// 2. Add or remove the reaction (toggle)
$action = "added"; // Default action

// Check if the reaction already exists
$stmt_find = $mysqli->prepare("SELECT id FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction_emoji = ?");
$stmt_find->bind_param("iis", $message_id, $current_user_id, $emoji);
$stmt_find->execute();
$result_find = $stmt_find->get_result();

if ($result_find->num_rows > 0) {
    // Reaction exists, so remove it
    $reaction_id = $result_find->fetch_assoc()['id'];
    $stmt_delete = $mysqli->prepare("DELETE FROM message_reactions WHERE id = ?");
    $stmt_delete->bind_param("i", $reaction_id);
    $stmt_delete->execute();
    $action = "removed";
} else {
    // Reaction does not exist, so add it
    $stmt_insert = $mysqli->prepare("INSERT INTO message_reactions (message_id, user_id, reaction_emoji) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("iis", $message_id, $current_user_id, $emoji);
    $stmt_insert->execute();
}
$stmt_find->close();

// Return success
echo json_encode(['success' => true, 'action' => $action]);

?>