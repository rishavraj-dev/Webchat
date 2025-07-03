<?php
session_start();
require_once 'Login/config.php';

// Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

// Get the JSON payload from the request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$message_id = isset($data['message_id']) ? (int)$data['message_id'] : 0;
$target_conversation_id = isset($data['target_conversation_id']) ? (int)$data['target_conversation_id'] : 0;

if ($message_id <= 0 || $target_conversation_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
header('Content-Type: application/json');

// Transaction for safety
$mysqli->begin_transaction();

try {
    // 1. Verify user can see the original message
    $stmt_verify_source = $mysqli->prepare("
        SELECT m.* FROM messages m 
        JOIN conversation_members cm ON m.conversation_id = cm.conversation_id
        WHERE m.id = ? AND cm.user_id = ?
    ");
    $stmt_verify_source->bind_param("ii", $message_id, $current_user_id);
    $stmt_verify_source->execute();
    $source_result = $stmt_verify_source->get_result();

    if ($source_result->num_rows === 0) {
        throw new Exception('Original message not found or you do not have permission to view it.');
    }
    $original_message = $source_result->fetch_assoc();
    $stmt_verify_source->close();

    // 2. Verify user is a member of the target conversation
    $stmt_verify_target = $mysqli->prepare("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
    $stmt_verify_target->bind_param("ii", $target_conversation_id, $current_user_id);
    $stmt_verify_target->execute();
    if ($stmt_verify_target->get_result()->num_rows === 0) {
        throw new Exception('You are not a member of the target conversation.');
    }
    $stmt_verify_target->close();

    // 3. Handle file copying
    $new_file_path = NULL; // Default to no file
    if (!empty($original_message['file_path']) && file_exists($original_message['file_path'])) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate a new unique name for the forwarded file to prevent conflicts
        $original_basename = basename($original_message['file_path']);
        $new_filename = uniqid('forwarded-') . '-' . $original_basename;
        $new_file_path_destination = $upload_dir . $new_filename;

        // Attempt to copy the file
        if (copy($original_message['file_path'], $new_file_path_destination)) {
            $new_file_path = $new_file_path_destination; // On success, set the new path
        } else {
            // Optional: Handle copy failure. For now, we'll just not include the file.
            // You could also throw an exception here if the file is critical.
            $new_file_path = NULL;
        }
    }

    // 4. Create the new forwarded message text
    $forwarded_body = $original_message['body'];
    $is_media_only = empty($forwarded_body);

    if ($is_media_only && !empty($new_file_path)) {
        // If there's only a file, the body can indicate it's forwarded media.
        $forwarded_body = null; // Or you could set a caption like "[Forwarded Media]"
    }
    // Note: We are not adding a "[Forwarded]" prefix to the text anymore,
    // as it's cleaner to handle this on the frontend if desired.
    // The forwarded message should look like a new message sent by the user.

    // 5. Insert the new message record into the database
    $stmt_insert = $mysqli->prepare("
        INSERT INTO messages (conversation_id, sender_id, message_type, body, file_path, status) 
        VALUES (?, ?, ?, ?, ?, 'delivered')
    ");
    // Use the potentially new file path
    $stmt_insert->bind_param(
        "iisss",
        $target_conversation_id,
        $current_user_id,
        $original_message['message_type'],
        $original_message['body'], // Keep the original body
        $new_file_path           // Use the new, copied file path
    );
    $stmt_insert->execute();

    if ($stmt_insert->affected_rows > 0) {
        $response = ['success' => true];
        $mysqli->commit();
    } else {
        throw new Exception('Failed to insert the forwarded message.');
    }
    $stmt_insert->close();

} catch (Exception $e) {
    $mysqli->rollback();
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
exit;