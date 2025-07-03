<?php
// delete_message.php

session_start();
require_once 'Login/config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
header('Content-Type: application/json');

// Get the message ID from the POST request
$data = json_decode(file_get_contents('php://input'), true);
$message_id = isset($data['message_id']) ? (int)$data['message_id'] : 0;

if ($message_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

// --- CRITICAL SECURITY CHECK ---
// Verify that the user attempting to delete the message is the one who sent it.
// This prevents a user from deleting someone else's messages.
$sql_verify = "SELECT file_path FROM messages WHERE id = ? AND sender_id = ?";
$stmt_verify = $mysqli->prepare($sql_verify);
$stmt_verify->bind_param("ii", $message_id, $current_user_id);
$stmt_verify->execute();
$result = $stmt_verify->get_result();

if ($result->num_rows === 1) {
    // Message belongs to the user, proceed with deletion.
    $message = $result->fetch_assoc();

    // If the message has an associated file, delete it from the server first.
    if (!empty($message['file_path']) && file_exists($message['file_path'])) {
        unlink($message['file_path']);
    }

    // Now, delete the message record from the database.
    $sql_delete = "DELETE FROM messages WHERE id = ?";
    $stmt_delete = $mysqli->prepare($sql_delete);
    $stmt_delete->bind_param("i", $message_id);
    
    if ($stmt_delete->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message deleted successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error during deletion.']);
    }
    $stmt_delete->close();

} else {
    // The user does not own this message or it doesn't exist.
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this message.']);
}

$stmt_verify->close();