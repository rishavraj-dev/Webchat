<?php
/**
 * fetch_updates.php
 * This script handles real-time message fetching using long polling.
 * It waits for new messages to arrive for a specific conversation and returns them as JSON.
 */

// 1. SETUP & SECURITY
session_start();
require_once 'Login/config.php';

// A. Authentication Check: Only logged-in users can fetch updates.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];

// B. Input Validation: Get and sanitize the required parameters from the JavaScript request.
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if ($conversation_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit;
}

// C. Authorization Check: Crucial security step to ensure the user is a member of the conversation they are polling.
// This prevents a user from snooping on other conversations by guessing their ID.
$stmt_verify = $mysqli->prepare("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
$stmt_verify->bind_param("ii", $conversation_id, $current_user_id);
$stmt_verify->execute();
$is_member = $stmt_verify->get_result()->num_rows > 0;
$stmt_verify->close();

if (!$is_member) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Access denied to this conversation']);
    exit;
}


// 2. LONG POLLING LOGIC
set_time_limit(30); // Allow the script to run for up to 30 seconds
header('Content-Type: application/json'); // Ensure the browser knows to expect JSON data

// We will check the database every second for up to 25 seconds.
for ($i = 0; $i < 25; $i++) {
    
    // The main query to find messages NEWER than the last one the client has.
    $sql = "SELECT m.id, m.body, m.status, m.sent_at, m.message_type, m.file_path, m.sender_id, u.username as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ? AND m.id > ?
            ORDER BY m.sent_at ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ii", $conversation_id, $last_message_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If we find one or more new messages...
    if ($result->num_rows > 0) {
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        
        // ...send them to the client and stop the script.
        echo json_encode($messages);
        exit;
    }
    
    $stmt->close();

    // If no messages were found, wait for 1 second before checking again.
    sleep(1);
}

// 3. NO UPDATES FOUND
// If the loop completes without finding any new messages, return an empty JSON array.
// The client-side JavaScript will use this empty response to start a new polling request.
echo json_encode([]);