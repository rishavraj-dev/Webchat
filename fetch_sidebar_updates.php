<?php
// fetch_sidebar_updates.php

session_start();
require_once 'Login/config.php';

// Authentication Check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
header('Content-Type: application/json');

// This query is optimized to get just the necessary info for the sidebar:
// - The last message text
// - The unread message count for the current user
$sql = "
SELECT
    c.id as conversation_id,
    (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
    (SELECT COUNT(*) FROM messages m_count WHERE m_count.conversation_id = c.id AND m_count.sender_id != ? AND m_count.status != 'read') AS unread_count
FROM
    conversations c
JOIN
    conversation_members cm ON c.id = cm.conversation_id
WHERE
    cm.user_id = ?
GROUP BY
    c.id
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $current_user_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$updates = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($updates);