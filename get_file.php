<?php
session_start();
require_once 'Login/config.php';

// Security: Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401); exit('Unauthorized');
}
$current_user_id = (int)$_SESSION['user_id'];

// Get the requested file name from the URL
$file_name = $_GET['file'] ?? '';

// Security: Prevent directory traversal attacks
if (strpos($file_name, '..') !== false || strpos($file_name, '/') !== false || empty($file_name)) {
    http_response_code(400); exit('Bad Request');
}

// Construct the full, real path to the file on the server
$file_path_on_server = 'uploads/' . $file_name;

// CRITICAL: Check if the file exists in our database records to find its conversation
$stmt = $mysqli->prepare("SELECT conversation_id FROM messages WHERE file_path = ?");
$db_path = 'uploads/' . $file_name; // The path as it is stored in the DB
$stmt->bind_param("s", $db_path);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    http_response_code(404); exit('File record not found in database.');
}
$message = $result->fetch_assoc();
$conversation_id = $message['conversation_id'];
$stmt->close();

// CRITICAL: Verify the current user is a member of that conversation
$stmt_verify = $mysqli->prepare("SELECT role FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
$stmt_verify->bind_param("ii", $conversation_id, $current_user_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();

// Allow access if user is a member OR if the conversation is a public group
$is_member = $result_verify->num_rows > 0;
$stmt_verify->close();

$is_public_group = false;
$stmt_pub = $mysqli->prepare("SELECT id FROM conversations WHERE id = ? AND type = 'group' AND visibility = 'public'");
$stmt_pub->bind_param("i", $conversation_id);
$stmt_pub->execute();
if ($stmt_pub->get_result()->num_rows > 0) {
    $is_public_group = true;
}
$stmt_pub->close();

if (!$is_member && !$is_public_group) {
    http_response_code(403); exit('Forbidden: You are not a member of this chat.');
}

// If all checks pass, serve the file
if (file_exists($file_path_on_server)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path_on_server);
    finfo_close($finfo);

    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path_on_server));
    header('Content-Disposition: inline; filename="' . basename($file_path_on_server) . '"');
    
    readfile($file_path_on_server);
    exit;
} else {
    http_response_code(404);
    exit('File not found on server.');
}
?>