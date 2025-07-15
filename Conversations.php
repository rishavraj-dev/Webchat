<?php
// --- ADD THESE TWO LINES FOR DEBUGGING ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// -----------------------------------------

// --- 1. SETUP AND SECURITY ---
session_start();
require_once 'Login/config.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header("Location: Login/login.php"); exit; }
$current_user_id = (int)$_SESSION['user_id'];

// --- PHP HELPER AND FORM LOGIC ---

// Update last_active timestamp
$mysqli->query("UPDATE users SET last_active = NOW() WHERE id = $current_user_id");

// Auto-Delete Logic (Note: A real cron job is the preferred method for this)
$cleanup_interval = 3600; $message_lifetime = 86400; $tracker_file = 'last_cleanup.txt';
if (!file_exists($tracker_file) || (time() - @filemtime($tracker_file)) > $cleanup_interval) {
    @touch($tracker_file);
    // Delete files first
    $sql_find_files = "SELECT file_path FROM messages m JOIN conversations c ON m.conversation_id = c.id WHERE c.auto_delete_enabled = 1 AND m.file_path IS NOT NULL AND m.sent_at < NOW() - INTERVAL ? SECOND";
    if ($stmt_find = $mysqli->prepare($sql_find_files)) {
        $stmt_find->bind_param("i", $message_lifetime); $stmt_find->execute();
        $result = $stmt_find->get_result(); $files_to_delete = $result->fetch_all(MYSQLI_ASSOC); $stmt_find->close();
        foreach ($files_to_delete as $file_row) {
            $file_path_to_unlink = $file_row['file_path'];
            // Security check: ensure we only delete from the uploads directory
            if (!empty($file_path_to_unlink) && file_exists($file_path_to_unlink) && strpos($file_path_to_unlink, 'uploads/') === 0) {
                unlink($file_path_to_unlink);
            }
        }
    }
    // Then delete database records
    $sql_delete_records = "DELETE m FROM messages AS m JOIN conversations AS c ON m.conversation_id = c.id WHERE c.auto_delete_enabled = 1 AND m.sent_at < NOW() - INTERVAL ? SECOND";
    if ($stmt_delete = $mysqli->prepare($sql_delete_records)) {
        $stmt_delete->bind_param("i", $message_lifetime); $stmt_delete->execute(); $stmt_delete->close();
    }
}

function findOrCreatePersonalConversation($mysqli, $current_user_id, $target_user_id) {
    $sql_check = "SELECT T1.conversation_id FROM conversation_members AS T1 INNER JOIN conversation_members AS T2 ON T1.conversation_id = T2.conversation_id WHERE T1.user_id = ? AND T2.user_id = ? AND T1.conversation_id IN (SELECT id FROM conversations WHERE type = 'personal')";
    $stmt_check = $mysqli->prepare($sql_check); $stmt_check->bind_param("ii", $current_user_id, $target_user_id); $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        return $result_check->fetch_assoc()['conversation_id'];
    } else {
        $mysqli->begin_transaction();
        try {
            $stmt_create = $mysqli->prepare("INSERT INTO conversations (type, creator_id) VALUES ('personal', ?)");
            $stmt_create->bind_param("i", $current_user_id);
            $stmt_create->execute(); $new_convo_id = $mysqli->insert_id;
            $stmt_add = $mysqli->prepare("INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?), (?, ?)");
            $stmt_add->bind_param("iiii", $new_convo_id, $current_user_id, $new_convo_id, $target_user_id); $stmt_add->execute();
            $mysqli->commit(); return $new_convo_id;
        } catch (Exception $e) { $mysqli->rollback(); return false; }
    }
}

// --- NEW: LINKIFY FUNCTION TO MAKE URLS CLICKABLE ---
/**
 * Safely converts plain text URLs into clickable HTML links.
 *
 * @param string $text The raw text from a message.
 * @return string The HTML-safe text with links.
 */
function linkify($text) {
    // 1. First, escape all HTML to prevent XSS. This is the most important step.
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2. Regular expression to find URLs.
    $url_pattern = '/(https?:\/\/[^\s<]+|www\.[^\s<]+)/';

    // 3. The replacement logic.
    $text = preg_replace_callback($url_pattern, function($matches) {
        $url = $matches[0];
        $display_url = $url;
        
        // If the URL starts with "www.", prepend "http://" for the href attribute.
        if (strpos($url, 'www.') === 0) {
            $url = 'http://' . $url;
        }

        // 4. Create the clickable link with security attributes.
        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $display_url . '</a>';
    }, $text);

    // 5. Finally, convert newlines to <br> tags.
    return nl2br($text, false);
}


if (isset($_GET['action']) && $_GET['action'] === 'start_dm' && isset($_GET['user_id'])) {
    $target_user_id = (int)$_GET['user_id'];
    if ($target_user_id > 0 && $target_user_id !== $current_user_id) {
        $conversation_id = findOrCreatePersonalConversation($mysqli, $current_user_id, $target_user_id);
        if ($conversation_id) { header("Location: conversations.php?conversation_id=" . $conversation_id); exit; }
    }
}

$modal_error = ''; $show_modal_on_load = false; $modal_to_show = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message_or_file') {
        $conversation_id = (int)$_POST['conversation_id'];
        $message_body = trim($_POST['message_body']);
        $reply_to = isset($_POST['reply_to']) && !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
        if ($conversation_id > 0) {
            $stmt_verify = $mysqli->prepare("SELECT role FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
            $stmt_verify->bind_param("ii", $conversation_id, $current_user_id); $stmt_verify->execute();
            if ($stmt_verify->get_result()->num_rows === 1) {
                $files_uploaded = isset($_FILES['file_upload']) && $_FILES['file_upload']['error'][0] !== UPLOAD_ERR_NO_FILE;
                if ($files_uploaded) {
                    $upload_dir = 'uploads/'; if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $caption_used = false;
                    foreach ($_FILES['file_upload']['name'] as $i => $name) {
                        if ($_FILES['file_upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $tmp_name = $_FILES['file_upload']['tmp_name'][$i]; $type = mime_content_type($tmp_name);
                        $filename = uniqid() . '-' . basename($name); $destination = $upload_dir . $filename;
                        if (move_uploaded_file($tmp_name, $destination)) {
                            $msg_type = strpos($type, 'image/') === 0 ? 'image' : (strpos($type, 'pdf') !== false ? 'pdf' : (strpos($type, 'video/') === 0 ? 'video' : 'file'));
                            $current_body = (!$caption_used && !empty($message_body)) ? $message_body : null;
                            $caption_used = true;
                            $stmt_insert = $mysqli->prepare("INSERT INTO messages (conversation_id, sender_id, message_type, body, file_path, status, reply_to) VALUES (?, ?, ?, ?, ?, 'delivered', ?)");
                            $stmt_insert->bind_param("issssi", $conversation_id, $current_user_id, $msg_type, $current_body, $destination, $reply_to);
                            $stmt_insert->execute();
                        }
                    }
                }
                if (!empty($message_body) && !$files_uploaded) {
                    $stmt_insert = $mysqli->prepare("INSERT INTO messages (conversation_id, sender_id, body, status, reply_to) VALUES (?, ?, ?, 'delivered', ?)");
                    $stmt_insert->bind_param("iisi", $conversation_id, $current_user_id, $message_body, $reply_to);
                    $stmt_insert->execute();
                }
            }
        }
        header("Location: conversations.php?conversation_id=" . $conversation_id); 
        exit;
    } 
    elseif ($action === 'create_channel') {
        $channel_name = trim($_POST['channel_name'] ?? '');
        if (!empty($channel_name)) {
            $mysqli->begin_transaction();
            try {
                $stmt_create = $mysqli->prepare("INSERT INTO conversations (name, type, visibility, creator_id) VALUES (?, 'channel', 'public', ?)");
                $stmt_create->bind_param("si", $channel_name, $current_user_id);
                $stmt_create->execute();
                $new_convo_id = $mysqli->insert_id;
                $stmt_add_creator = $mysqli->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'admin')");
                $stmt_add_creator->bind_param("ii", $new_convo_id, $current_user_id);
                $stmt_add_creator->execute();
                $mysqli->commit();
                header("Location: conversations.php?conversation_id=" . $new_convo_id);
                exit;
            } catch (Exception $e) {
                $mysqli->rollback();
                $modal_error = "Error creating channel. Please try again.";
                $show_modal_on_load = true;
                $modal_to_show = 'newChannelModal';
            }
        } else {
            $modal_error = "Channel name cannot be empty.";
            $show_modal_on_load = true;
            $modal_to_show = 'newChannelModal';
        }
    }
    elseif ($action === 'join_channel') {
        $conversation_id_to_join = (int)$_POST['conversation_id'];
        $stmt_check = $mysqli->prepare("SELECT type, visibility FROM conversations WHERE id = ?");
        $stmt_check->bind_param("i", $conversation_id_to_join);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows === 1) {
            $convo = $result->fetch_assoc();
            if (($convo['type'] === 'channel' || $convo['type'] === 'group') && $convo['visibility'] === 'public') {
                $stmt_insert = $mysqli->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'member') ON DUPLICATE KEY UPDATE user_id=user_id");
                $stmt_insert->bind_param("ii", $conversation_id_to_join, $current_user_id);
                $stmt_insert->execute();
            }
        }
        header("Location: conversations.php?conversation_id=" . $conversation_id_to_join);
        exit;
    }
    elseif ($action === 'create_group') {
        $group_name = trim($_POST['group_name'] ?? '');
        $visibility = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
        $group_members = trim($_POST['group_members'] ?? '');
        if (!empty($group_name)) {
            $mysqli->begin_transaction();
            try {
                $stmt_create = $mysqli->prepare("INSERT INTO conversations (name, type, visibility, creator_id) VALUES (?, 'group', ?, ?)");
                $stmt_create->bind_param("ssi", $group_name, $visibility, $current_user_id);
                $stmt_create->execute();
                $new_group_id = $mysqli->insert_id;
                $stmt_add_creator = $mysqli->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'admin')");
                $stmt_add_creator->bind_param("ii", $new_group_id, $current_user_id);
                $stmt_add_creator->execute();
                if (!empty($group_members)) {
                    $public_ids = array_filter(array_map('trim', explode(',', $group_members)));
                    if (!empty($public_ids)) {
                        $placeholders = implode(',', array_fill(0, count($public_ids), '?'));
                        $types = str_repeat('s', count($public_ids));
                        $stmt_users = $mysqli->prepare("SELECT id FROM users WHERE public_id IN ($placeholders)");
                        $stmt_users->bind_param($types, ...$public_ids);
                        $stmt_users->execute();
                        $result_users = $stmt_users->get_result();
                        while ($row = $result_users->fetch_assoc()) {
                            $uid = $row['id'];
                            if ($uid != $current_user_id) {
                                $stmt_add = $mysqli->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'member')");
                                $stmt_add->bind_param("ii", $new_group_id, $uid);
                                $stmt_add->execute();
                            }
                        }
                    }
                }
                $mysqli->commit();
                header("Location: conversations.php?conversation_id=" . $new_group_id);
                exit;
            } catch (Exception $e) {
                $mysqli->rollback();
                $modal_error = "Error creating group. Please try again.";
                $show_modal_on_load = true;
                $modal_to_show = 'newGroupModal';
            }
        } else {
            $modal_error = "Group name cannot be empty.";
            $show_modal_on_load = true;
            $modal_to_show = 'newGroupModal';
        }
    }
    // --- FIX: This block handles DBs where the '@' is stored in the public_id ---
    elseif ($action === 'start_chat') {
        $public_id_input = trim($_POST['public_id'] ?? '');

        if (!empty($public_id_input)) {
            // --- MODIFIED LOGIC: Ensure the public ID ALWAYS starts with '@' before searching ---
            $public_id_to_search = '';
            if (strpos($public_id_input, '@') === 0) {
                // If it already has '@', use it as is
                $public_id_to_search = $public_id_input;
            } else {
                // If it's missing, add it
                $public_id_to_search = '@' . $public_id_input;
            }

            // The case-insensitive search is still a good idea for robustness
            $stmt_find_user = $mysqli->prepare("SELECT id FROM users WHERE LOWER(public_id) = LOWER(?) AND id != ?");
            $stmt_find_user->bind_param("si", $public_id_to_search, $current_user_id);
            $stmt_find_user->execute();
            $result_user = $stmt_find_user->get_result();

            if ($user_row = $result_user->fetch_assoc()) {
                $target_user_id = $user_row['id'];
                // This function already handles finding or creating a conversation
                $conversation_id = findOrCreatePersonalConversation($mysqli, $current_user_id, $target_user_id);
                if ($conversation_id) {
                    header("Location: conversations.php?conversation_id=" . $conversation_id);
                    exit;
                } else {
                    $modal_error = "A database error occurred while creating the chat.";
                    $show_modal_on_load = true;
                    $modal_to_show = 'newChatModal';
                }
            } else {
                // This error will now be accurate
                $modal_error = "User with that Public ID was not found.";
                $show_modal_on_load = true;
                $modal_to_show = 'newChatModal';
            }
        } else {
            $modal_error = "Public ID cannot be empty.";
            $show_modal_on_load = true;
            $modal_to_show = 'newChatModal';
        }
    }
}


// --- 3. DATA FETCHING & PAGE SETUP ---
$is_conversation_view = isset($_GET['conversation_id']) && is_numeric($_GET['conversation_id']);
$conversation_id = $is_conversation_view ? (int)$_GET['conversation_id'] : null;

if ($is_conversation_view) {
    $stmt_mark_read = $mysqli->prepare("UPDATE messages SET status = 'read', read_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND status != 'read'");
    $stmt_mark_read->bind_param("ii", $conversation_id, $current_user_id); $stmt_mark_read->execute();
}

$search_term = $_GET['search'] ?? '';
$conversations = [];

if (!empty($search_term)) {
    // Search logic remains the same. It's complex but serves a different purpose.
    $like_term = "%{$search_term}%";
    $public_id_term = ltrim($search_term, '@');

    $sql_search = "
        (SELECT c.id, c.type, c.name, (CASE WHEN c.type = 'personal' THEN (SELECT u.username FROM users u JOIN conversation_members cm2 ON u.id = cm2.user_id WHERE cm2.conversation_id = c.id AND cm2.user_id != ?) ELSE c.name END) AS display_name, (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC LIMIT 1) AS last_message, 0 as unread_count, 'existing_chat' AS result_type, c.id AS action_id, (SELECT COALESCE(MAX(m.sent_at), c.created_at) FROM messages m WHERE m.conversation_id = c.id) as order_date FROM conversations c JOIN conversation_members cm ON c.id = cm.conversation_id WHERE cm.user_id = ? AND (c.name LIKE ? OR (c.type = 'personal' AND (SELECT u.username FROM users u JOIN conversation_members cm2 ON u.id = cm2.user_id WHERE cm2.conversation_id = c.id AND cm2.user_id != ?) LIKE ?)))
        UNION
        (SELECT c.id, c.type, c.name, c.name AS display_name, CONCAT((SELECT COUNT(*) FROM conversation_members WHERE conversation_id = c.id), ' members') AS last_message, 0 as unread_count, 'joinable' AS result_type, c.id AS action_id, c.created_at as order_date FROM conversations c WHERE c.visibility = 'public' AND c.name LIKE ? AND NOT EXISTS (SELECT 1 FROM conversation_members cm_check WHERE cm_check.conversation_id = c.id AND cm_check.user_id = ?))
        UNION
        (SELECT u.id, 'personal' as type, u.username as name, u.username as display_name, CONCAT('Start chat with @', u.public_id) as last_message, 0 as unread_count, 'new_chat' as result_type, u.id as action_id, u.created_at as order_date FROM users u WHERE u.id != ? AND (u.username LIKE ? OR u.public_id = ?))
        ORDER BY order_date DESC";
    $stmt_search = $mysqli->prepare($sql_search);
    if ($stmt_search) {
        $stmt_search->bind_param("iisississs", $current_user_id, $current_user_id, $like_term, $current_user_id, $like_term, $like_term, $current_user_id, $current_user_id, $like_term, $public_id_term);
        $stmt_search->execute();
        $conversations = $stmt_search->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        die("Error preparing search statement: " . $mysqli->error);
    }
} else {
    // --- FIX: N+1 QUERY PROBLEM ---
    // This new, optimized query replaces the old, slow one.
    // NOTE: This requires MySQL 8+ or MariaDB 10.2+ due to the ROW_NUMBER() window function.
    $sql_chats_optimized = "
        SELECT
            c.id, c.type, c.name,
            (CASE WHEN c.type = 'personal' THEN u_other.username ELSE c.name END) AS display_name,
            last_msg.body AS last_message,
            COALESCE(unread.unread_count, 0) AS unread_count,
            'existing_chat' as result_type,
            c.id as action_id
        FROM conversation_members cm
        JOIN conversations c ON cm.conversation_id = c.id
        LEFT JOIN conversation_members cm_other ON c.id = cm_other.conversation_id AND cm_other.user_id != ?
        LEFT JOIN users u_other ON cm_other.user_id = u_other.id AND c.type = 'personal'
        LEFT JOIN (
            SELECT conversation_id, body, sent_at
            FROM (
                SELECT conversation_id, body, sent_at,
                    ROW_NUMBER() OVER(PARTITION BY conversation_id ORDER BY sent_at DESC) as rn
                FROM messages
            ) m
            WHERE rn = 1
        ) last_msg ON c.id = last_msg.conversation_id
        LEFT JOIN (
            SELECT conversation_id, COUNT(*) as unread_count
            FROM messages
            WHERE status != 'read' AND sender_id != ?
            GROUP BY conversation_id
        ) unread ON c.id = unread.conversation_id
        WHERE cm.user_id = ?
        GROUP BY c.id
        ORDER BY COALESCE(last_msg.sent_at, c.created_at) DESC
    ";
    
    $stmt_chats = $mysqli->prepare($sql_chats_optimized);
    $stmt_chats->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
    $stmt_chats->execute();
    $conversations = $stmt_chats->get_result()->fetch_all(MYSQLI_ASSOC);
    // --- END FIX ---
}

$messages = []; $current_conversation = null; $last_message_id = 0; $is_member = false; $other_user_id = null;
if ($is_conversation_view) {
    $sql_verify = "SELECT c.id, c.type, c.name, c.visibility, c.creator_id, (CASE WHEN c.type = 'personal' THEN (SELECT u.username FROM users u JOIN conversation_members cm2 ON u.id = cm2.user_id WHERE cm2.conversation_id = c.id AND cm2.user_id != ?) ELSE c.name END) AS display_name FROM conversations c WHERE c.id = ?";
    $stmt_verify = $mysqli->prepare($sql_verify); $stmt_verify->bind_param("ii", $current_user_id, $conversation_id); $stmt_verify->execute(); $result_verify = $stmt_verify->get_result();
    if ($result_verify->num_rows === 1) {
        $current_conversation = $result_verify->fetch_assoc();
        
        if ($current_conversation['type'] === 'personal') {
            $stmt_other_user = $mysqli->prepare("SELECT user_id FROM conversation_members WHERE conversation_id = ? AND user_id != ?");
            $stmt_other_user->bind_param("ii", $conversation_id, $current_user_id);
            $stmt_other_user->execute();
            if ($other_user_row = $stmt_other_user->get_result()->fetch_assoc()) { $other_user_id = $other_user_row['user_id']; }
        }

        $stmt_check_member = $mysqli->prepare("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
        $stmt_check_member->bind_param("ii", $conversation_id, $current_user_id); $stmt_check_member->execute(); $is_member = $stmt_check_member->get_result()->num_rows > 0;
        
        if($is_member || ($current_conversation['type'] === 'group' && $current_conversation['visibility'] === 'public') || ($current_conversation['type'] === 'channel' && $current_conversation['visibility'] === 'public')) {
            $sql_messages = "
                SELECT
                    m.id, m.body, m.status, m.sent_at, m.read_at, m.message_type, 
                    m.file_path, m.reply_to, u.username AS sender_name, m.sender_id,
                    GROUP_CONCAT(DISTINCT CONCAT(mr.reaction_emoji, ':', (SELECT COUNT(*) FROM message_reactions WHERE message_id = m.id AND reaction_emoji = mr.reaction_emoji))) as reactions
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN message_reactions mr ON m.id = mr.message_id
                WHERE m.conversation_id = ?
                GROUP BY m.id
                ORDER BY m.sent_at ASC
            ";
            
            $stmt_messages = $mysqli->prepare($sql_messages); 
            if ($stmt_messages === false) { die("Error preparing statement: " . $mysqli->error); }
            $stmt_messages->bind_param("i", $conversation_id); $stmt_messages->execute(); 
            $messages = $stmt_messages->get_result()->fetch_all(MYSQLI_ASSOC);
            if (!empty($messages)) { $last_message_id = end($messages)['id']; }
        }
    } else { header("Location: conversations.php"); exit; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webchat</title>
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
    <link rel="stylesheet" href="CSS/basicstyles.css">
    <link rel="stylesheet" href="CSS/managegroup.css">
    <style>
        .pdf-card { display: flex; align-items: center; background: #232f3e; border-radius: 12px; padding: 10px 14px; margin: 0.5rem 0; max-width: 340px; box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
        .pdf-icon { margin-right: 12px; }
        .pdf-details { flex: 1; }
        .pdf-filename { font-size: 1rem; color: #fff; margin-bottom: 4px; word-break: break-all; }
        .pdf-view-btn { display: inline-block; background: #4a90e2; color: #fff; padding: 4px 12px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.95em; transition: background 0.2s; }
        .pdf-view-btn:hover { background: #357ab8; }
        @media (min-width: 768px) { .placeholder { display: flex; } }
        @media (max-width: 767px) {
            #conversations-panel { display: <?php echo $is_conversation_view ? 'none' : 'flex'; ?>; width: 100%; border: none; }
            #message-panel { display: <?php echo $is_conversation_view ? 'flex' : 'none'; ?>; width: 100%; }
        }
        .video-card { margin: 0.5rem 0; max-width: 340px; border-radius: 12px; overflow: hidden; background: #232f3e; box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
        /* --- NEW: Utility classes for buttons to reduce inline styles --- */
        .btn { padding: 0.75rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
        .btn-primary { background-color: var(--accent-blue); color: #fff; }
        .btn-primary:hover { background-color: #357ab8; }
        .btn-secondary { background-color: #4b5563; color: #fff; }
        .btn-secondary:hover { background-color: #5a6678; }
        .w-full { width: 100%; }
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .msg-bubble a {
            color: #e53e3e;           /* Red color for links */
            font-weight: 600;
            text-decoration: underline;
            word-break: break-all;
            transition: color 0.2s;
        }
        .msg-bubble a:hover {
            color: #b32d2d;           /* Darker red on hover */
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="chat-layout">
        <section id="conversations-panel">
            <!-- Header section is unchanged -->
            <header class="conv-header">
                <div class="conv-header-top">
                    <button id="openSidebarBtn" class="conv-header-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    </button>
                    <h1 class="header-title">WebChat</h1>
                </div>
                <form action="conversations.php" method="GET" class="search-form">
                    <input type="text" name="search" class="search-bar" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>" autocomplete="off">
                </form>
            </header>

            <div id="dropdownMenu">
                <a href="dashboard.php">Dashboard</a>
                <a href="settings.php">Settings</a>
                <a href="Login/logout.php" class="logout-link">Log Out</a>
            </div>
            
            <div class="conv-body">
                <?php if (empty($conversations)): ?>
                    <div style="text-align:center; padding: 2rem; color: var(--text-secondary);"><?php echo !empty($search_term) ? 'No results found.' : 'No conversations yet.'; ?></div>
                <?php else: ?>
                    <?php foreach($conversations as $convo):
                        $url = '#'; $result_label = ''; $icon = '💬'; $unread_count = $convo['unread_count'] ?? 0;
                        switch ($convo['result_type']) {
                            case 'existing_chat':
                                $url = "?conversation_id=" . $convo['action_id'];
                                if ($convo['type'] === 'personal') { $icon = '👤'; $result_label = 'Chat'; }
                                elseif ($convo['type'] === 'group') { $icon = '👥'; $result_label = 'Group'; }
                                elseif ($convo['type'] === 'channel') { $icon = '📢'; $result_label = 'Channel'; }
                                else { $icon = htmlspecialchars(strtoupper(substr($convo['display_name'], 0, 1))); }
                                break;
                            case 'joinable':
                                $url = "?conversation_id=" . $convo['action_id'];
                                $result_label = ($convo['type'] === 'channel' || $convo['type'] === 'group') ? 'View' : 'Join';
                                $icon = ($convo['type'] === 'channel') ? '📢' : '➕';
                                break;
                            case 'new_chat':
                                $url = "?action=start_dm&user_id=" . $convo['action_id'];
                                $result_label = 'New Chat';
                                $icon = '👤';
                                break;
                        }
                    ?>
                        <a href="<?php echo $url; ?>" class="conv-item-link" data-conv-id="<?php echo $convo['id']; ?>">
                            <div class="conv-item <?php echo ($conversation_id == $convo['id']) ? 'active' : ''; ?>">
                                <div class="avatar" style="font-size: 1.25rem; line-height: 1;"><?php echo $icon; ?></div>
                                <div class="conv-details">
                                    <div class="conv-name-time">
                                        <span class="conv-name"><?php echo htmlspecialchars($convo['display_name']); ?></span>
                                        <?php if(!empty($search_term)): ?>
                                            <span style="font-size: 0.75rem; color: var(--accent-blue); background-color: rgba(74, 147, 224, 0.2); padding: 2px 6px; border-radius: 10px; margin-left: auto; flex-shrink: 0;"><?php echo $result_label; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conv-preview-wrapper">
                                        <span class="conv-preview"><?php echo htmlspecialchars($convo['last_message'] ?? 'Start the conversation!'); ?></span>
                                        <?php if ($unread_count > 0): ?>
                                            <span class="unread-badge"><?php echo $unread_count; ?></span>
                                        <?php else: ?>
                                            <span class="unread-badge" style="display: none;"></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Floating menu is unchanged -->
            <button id="openNewMenuBtn" title="New">+</button>
            <div id="newMenuFloating">
                <button id="menuNewChatBtn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span>New Chat</span></button>
                <button id="menuNewGroupBtn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-7.5-2.962A3.75 3.75 0 0112 15v-2.253m0-2.253V8.25m0 0a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5M12 15a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5m-3.75 2.962c-.586.154-1.182.234-1.79.234a4.5 4.5 0 119 0c-.608 0-1.204-.08-1.79-.234m-7.42-2.962A3.75 3.75 0 004.5 15v-2.253m0-2.253V8.25m0 0a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5M4.5 15a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" /></svg><span>New Group</span></button>
                <button id="menuNewChannelBtn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 01-4.5-4.5v-4.5a4.5 4.5 0 014.5-4.5h7.5a4.5 4.5 0 014.5 4.5v4.5a4.5 4.5 0 01-4.5-4.5h-1.75a2.25 2.25 0 00-2.25 2.25v2.25a2.25 2.25 0 002.25 2.25h2.25M15.345 2.155a2.25 2.25 0 012.25 2.25v2.25h-2.25V4.405a.75.75 0 00-1.5 0v2.25h-2.25V4.405a2.25 2.25 0 012.25-2.25z" /></svg><span>New Channel</span></button>
            </div>
        </section>

        <section id="message-panel">
            <?php if ($is_conversation_view && $current_conversation): ?>
                <header class="msg-header">
                    <a href="conversations.php" class="back-to-list-btn" style="margin-right: 1rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    </a>
                    <div style="flex-grow:1; text-align:left;">
                        <div style="font-weight: 600;" class="manage-link-container">
                            <?php if (($current_conversation['type'] === 'group' || $current_conversation['type'] === 'channel') && $is_member): ?>
                                <a href="javascript:void(0);" class="manage-link" data-url="manage_group.php?id=<?php echo $conversation_id; ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($current_conversation['display_name']); ?></a>
                            <?php elseif ($current_conversation['type'] === 'personal'): ?>
                                <a href="javascript:void(0);" class="manage-link" data-url="manage_personal_chats.php?user_id=<?php echo $other_user_id; ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($current_conversation['display_name']); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($current_conversation['display_name']); ?>
                            <?php endif; ?>
                        </div>
                        <?php
                        $status_text = '';
                        if ($current_conversation['type'] === 'personal' && $other_user_id) {
                            $stmt_status = $mysqli->prepare("SELECT last_active FROM users WHERE id = ?");
                            $stmt_status->bind_param("i", $other_user_id);
                            $stmt_status->execute();
                            $result_status = $stmt_status->get_result();
                            if ($status_row = $result_status->fetch_assoc()) {
                                $last_active = strtotime($status_row['last_active']);
                                $time_diff = time() - $last_active;
                                if ($time_diff < 300) { $status_text = 'online';
                                } else if ($time_diff < 3600) { $status_text = 'last seen ' . round($time_diff / 60) . ' minutes ago';
                                } else if ($time_diff < 86400) { $status_text = 'last seen ' . round($time_diff / 3600) . ' hours ago';
                                } else { $status_text = 'last seen ' . date('M j, Y', $last_active); }
                            }
                        }
                        ?>
                        <?php if (!empty($status_text)): ?>
                            <div style="font-size:0.875rem; color:var(--text-secondary); margin-top:4px;"><?php echo $status_text; ?></div>
                        <?php endif; ?>
                    </div>
                </header>
                <div id="msg-body" class="msg-body"><div id="msg-space" class="msg-space">
                    <?php foreach($messages as $msg): ?>
                        <div class="msg-bubble-wrapper <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>"><div class="msg-bubble <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>" data-msg-id="<?php echo $msg['id']; ?>" data-sender-id="<?php echo $msg['sender_id']; ?>" data-sent="<?php echo htmlspecialchars($msg['sent_at']); ?>" data-read="<?php echo htmlspecialchars($msg['read_at'] ?? 'Not read'); ?>">
                            <?php if ($current_conversation['type'] !== 'personal' && $msg['sender_id'] != $current_user_id): ?><a href="?action=start_dm&user_id=<?php echo $msg['sender_id']; ?>" style="font-weight: 600; color: var(--accent-blue-active);"><?php echo htmlspecialchars($msg['sender_name']); ?></a><?php endif; ?>
                            <?php if (!empty($msg['reply_to'])):
                                $original_message_text = 'Deleted Message';
                                foreach ($messages as $orig) { if ($orig['id'] == $msg['reply_to']) { $original_message_text = $orig['body'] ?: '[Media]'; break; } }
                                echo '<div class="reply-preview-in-bubble">Replying to: ' . htmlspecialchars(mb_substr($original_message_text, 0, 60)) . '</div>';
                            endif; ?>
                            <?php if ($msg['file_path']): 
                                $file_basename = basename($msg['file_path']);
                                if ($msg['message_type'] === 'image'): ?>
                                    <a href="get_file.php?file=<?php echo rawurlencode($file_basename); ?>" target="_blank">
                                        <img src="get_file.php?file=<?php echo rawurlencode($file_basename); ?>" style="max-width:100%; border-radius:8px; margin-top:0.5rem; margin-bottom:0.5rem;">
                                    </a>
                                <?php elseif ($msg['message_type'] === 'pdf'): ?>
                                    <div class="pdf-card">
                                        <div class="pdf-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                                        <div class="pdf-details">
                                            <div class="pdf-filename"><?php echo rawurlencode($file_basename); ?></div>
                                            <a href="get_file.php?file=<?php echo rawurlencode($file_basename); ?>" target="_blank" class="pdf-view-btn">View PDF</a>
                                        </div>
                                    </div>
                                <?php elseif ($msg['message_type'] === 'video'): ?>
                                    <div class="video-card">
                                        <video controls style="max-width:100%;border-radius:12px;background:#222;"><source src="get_file.php?file=<?php echo htmlspecialchars($file_basename); ?>" type="video/mp4">Your browser does not support the video tag.</video>
                                    </div>
                                <?php else: ?>
                                    <a href="get_file.php?file=<?php echo rawurlencode($file_basename); ?>" target="_blank" style="display:inline-block;">
                                        <span style="display:inline-block;vertical-align:middle;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4a90e2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                                        <span style="margin-left:8px;"><?php echo rawurlencode($file_basename); ?></span>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- --- FIX: USE LINKIFY FUNCTION FOR CLICKABLE URLS --- -->
                            <?php if (!empty($msg['body'])): ?>
                                <p><?php echo linkify($msg['body']); ?></p>
                            <?php endif; ?>
                            <!-- --- END FIX --- -->

                            <div class="msg-actions" style="position: absolute; bottom: 4px; left: 8px;"><button class="forward-btn" title="Forward" data-msg-id="<?php echo $msg['id']; ?>" style="background:none;border:none;cursor:pointer;padding:0;"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width:1.2em;height:1.2em;color:var(--tick-color);'><path stroke-linecap='round' stroke-linejoin='round' d='M4.5 19.5l15-7.5-15-7.5v6.75l10.5.75-10.5.75v6.75z' /></svg></button></div>
                            <div class="msg-meta"><span class="msg-timestamp"><?php echo date('h:i A', strtotime($msg['sent_at'])); ?></span><?php if ($msg['sender_id'] == $current_user_id): ?><span class="msg-status"><?php echo ($msg['status'] === 'read') ? '✓✓' : '✓'; ?></span><?php endif; ?></div>
                            <div class="msg-reactions">
                                <?php if (!empty($msg['reactions'])): ?>
                                    <?php $reaction_groups = explode(',', $msg['reactions']); ?>
                                    <?php foreach($reaction_groups as $group): ?>
                                        <?php if(strpos($group, ':') === false) continue; list($emoji, $count) = explode(':', $group, 2); ?>
                                        <span class="reaction"><?php echo htmlspecialchars($emoji); ?><span class="reaction-count"><?php echo (int)$count; ?></span></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div></div>
                    <?php endforeach; ?>
                </div></div>

                <?php if (($current_conversation['type'] === 'channel' || $current_conversation['type'] === 'group') && $current_conversation['visibility'] === 'public' && !$is_member): ?>
                    <footer class="msg-footer">
                        <form action="conversations.php" method="POST" style="width: 100%;">
                            <input type="hidden" name="action" value="join_channel">
                            <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                            <button type="submit" class="send-btn" style="width: 100%; border-radius: 8px; font-weight: 600; text-transform: uppercase;">Join <?php echo htmlspecialchars($current_conversation['type']); ?></button>
                        </form>
                    </footer>
                <?php elseif ($is_member && !($current_conversation['type'] === 'channel' && $current_conversation['creator_id'] != $current_user_id)): ?>
                    <footer class="msg-footer">
                        <form class="msg-form" action="conversations.php?conversation_id=<?php echo $conversation_id; ?>" method="POST" enctype="multipart/form-data">
                            <div id="msgPreviews" style="width:100%;"></div>
                            <div id="replyPreviewContainer"></div>
                            <input type="hidden" name="action" value="send_message_or_file">
                            <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                            <input type="hidden" name="reply_to" id="replyToInput" value="">
                            <div class="msg-form-inner">
                                <button type="button" id="attachBtn" class="conv-header-btn" title="Attach File"><label for="fileUploadInput" style="cursor:pointer;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739c.216.03.433.051.65.064l1.828 1.828c1.536 1.536 1.536 4.026 0 5.562-.216.216-.47.398-.75.525A6.002 6.002 0 0112 21a6 6 0 01-5.657-3.413 6.002 6.002 0 01-.75-.525c-1.536-1.536-1.536-4.026 0-5.562l1.828-1.828c.03-.217.051-.433.064-.65z"></path></svg></label></button>
                                <input type="file" name="file_upload[]" id="fileUploadInput" style="display: none;" multiple />
                                <input type="text" name="message_body" class="msg-input" placeholder="Message..." autocomplete="off">
                                <button type="button" id="emojiBtn" class="conv-header-btn" title="Emoji"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 9.75h.008v.008H9V9.75zm6 0h.008v.008H15V9.75z" /></svg></button>
                                <button type="submit" class="send-btn" title="Send"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg></button>
                            </div>
                        </form>
                        <emoji-picker></emoji-picker>
                    </footer>
                <?php elseif (!$is_member): ?>
                    <div class="placeholder" style="display:flex; padding: 1rem; background-color: var(--bg-secondary);"><p>This is a private group. You must be invited to join.</p></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="placeholder"><h1 style="font-size: 1.25rem;">Select a chat to start messaging</h1></div>
            <?php endif; ?>
        </section>
    </div>
    
    <!-- Modals section with improved button classes -->
    <div id="newChatModal" class="modal-overlay <?php if ($show_modal_on_load && $modal_to_show === 'newChatModal') echo 'visible'; ?>"><div class="modal-content"><h2>Start a New Chat</h2><?php if ($modal_to_show === 'newChatModal' && !empty($modal_error)) echo '<p class="modal-error">'.htmlspecialchars($modal_error).'</p>'; ?><form action="conversations.php" method="POST"><input type="hidden" name="action" value="start_chat"><label for="public_id_modal" style="display:block; text-align:left; margin-bottom:0.5rem;">User's Public ID:</label><input type="text" id="public_id_modal" name="public_id" placeholder="@username" required style="width:100%; padding:0.75rem; background-color:#374151; border:1px solid #4b5563; border-radius:8px;"><button type="submit" class="btn btn-primary w-full mt-2">Start Chat</button><button type="button" class="close-modal-btn btn btn-secondary w-full mt-1">Cancel</button></form></div></div>
    <div id="newGroupModal" class="modal-overlay <?php if ($show_modal_on_load && $modal_to_show === 'newGroupModal') echo 'visible'; ?>"><div class="modal-content"><h2>Create New Group</h2><?php if ($modal_to_show === 'newGroupModal' && !empty($modal_error)) echo '<p class="modal-error">'.htmlspecialchars($modal_error).'</p>'; ?><form id="createGroupForm" action="conversations.php" method="POST"><input type="hidden" name="action" value="create_group"><input type="text" name="group_name" placeholder="Group Name" required style="width:100%; padding:0.75rem; background-color:#374151; border:1px solid #4b5563; border-radius:8px;"><select name="visibility" style="width:100%; margin-top:1rem; padding:0.75rem; background-color:#374151; border:1px solid #4b5563; border-radius:8px;"><option value="public">Public</option><option value="private">Private</option></select><div style="margin-top:1rem;"><label for="group_members" style="display:block; text-align:left; margin-bottom:0.5rem;">Add Members (optional, Public IDs, comma separated):</label><input type="text" id="group_members" name="group_members" placeholder="@user1, @user2" style="width:100%; padding:0.75rem; background-color:#374151; border:1px solid #4b5563; border-radius:8px;"></div><button type="submit" class="btn btn-primary w-full mt-2">Create Group</button><button type="button" class="close-modal-btn btn btn-secondary w-full mt-1">Cancel</button></form></div></div>
    <div id="newChannelModal" class="modal-overlay <?php if ($show_modal_on_load && $modal_to_show === 'newChannelModal') echo 'visible'; ?>"><div class="modal-content"><h2>Create New Channel</h2><?php if ($modal_to_show === 'newChannelModal' && !empty($modal_error)) echo '<p class="modal-error">'.htmlspecialchars($modal_error).'</p>'; ?><form action="conversations.php" method="POST"><input type="hidden" name="action" value="create_channel"><input type="text" name="channel_name" placeholder="Channel Name" required style="width:100%; padding:0.75rem; background-color:#374151; border:1px solid #4b5563; border-radius:8px;"><button type="submit" class="btn btn-primary w-full mt-2">Create Channel</button><button type="button" class="close-modal-btn btn btn-secondary w-full mt-1">Cancel</button></form></div></div>
    
    <!-- Context Menu and Forward Modal are unchanged -->
    <div id="msgContextMenu" style="display:none; position:absolute; z-index:9999; background:#232f3e; color:#fff; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.2); min-width:150px; padding: 4px;"><div id="ctxReply" class="ctx-item">Reply</div><div id="ctxInfo" class="ctx-item">Message Info</div><div id="ctxReact" class="ctx-item">React<span class="react-emoji" data-emoji="👍">👍</span><span class="react-emoji" data-emoji="❤️">❤️</span><span class="react-emoji" data-emoji="😂">😂</span><span class="react-emoji" data-emoji="😮">😮</span><span class="react-emoji" data-emoji="😢">😢</span><span class="react-emoji" data-emoji="🙏">🙏</span></div><style>.ctx-item{padding:8px 12px; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:8px;} .ctx-item:hover{background:var(--accent-blue);} .react-emoji{font-size:1.2em;cursor:pointer;padding:2px;border-radius:50%;} .react-emoji:hover{background:rgba(255,255,255,0.2);}</style><div id="ctxDelete" class="ctx-item" style="color: var(--danger-text);">Delete</div></div>
    <div id="forwardModal" class="modal-overlay"><div class="modal-content" style="max-width:350px;"><h2>Forward Message To...</h2><div id="forwardList" style="text-align:left; margin:1em 0;max-height:300px;overflow-y:auto;"></div><button type="button" class="close-modal-btn" style="background:#4b5563;width:100%;padding:0.5em;border-radius:8px;">Cancel</button></div></div>

<script>
    // --- START: CRITICAL FILE UPLOAD FIX ---
    let selectedFilesStore = [];
    // --- END: CRITICAL FILE UPLOAD FIX ---

    document.addEventListener('DOMContentLoaded', function () {
        const appData = {
            isConversationView: <?php echo json_encode($is_conversation_view); ?>,
            conversationId: <?php echo json_encode($conversation_id); ?>,
            lastMessageId: <?php echo json_encode($last_message_id); ?>,
            currentUserId: <?php echo json_encode($current_user_id); ?>,
            isSearch: <?php echo json_encode(!empty($search_term)); ?>,
            conversations: <?php echo json_encode($conversations); ?>,
            currentConversation: <?php echo json_encode($current_conversation); ?>
        };

        let lastReceivedId = appData.lastMessageId;
        let replyToMessageId = null;
        let originalMessagePanelState = { header: null, body: null, footer: null };
        let isManagementViewActive = false;

        const floatingMenu = document.getElementById('newMenuFloating');
        const ctxMenu = document.getElementById('msgContextMenu');
        const dropdownMenu = document.getElementById('dropdownMenu');
        const forwardModal = document.getElementById('forwardModal');
        const forwardList = document.getElementById('forwardList');
        const replyPreviewContainer = document.getElementById('replyPreviewContainer');
        const replyToInput = document.getElementById('replyToInput');

        const openSidebarBtn = document.getElementById('openSidebarBtn');
        if (openSidebarBtn && dropdownMenu) {
            openSidebarBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('visible');
            });
        }

        // --- Use the correct escapeHtml everywhere ---
        const escapeHtml = str =>
            String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

        const pollForMessages = async () => {
            if (!appData.isConversationView || !appData.conversationId || isManagementViewActive) {
                setTimeout(pollForMessages, 2500); return;
            }
            try {
                const response = await fetch(`fetch_updates.php?conversation_id=${appData.conversationId}&last_message_id=${lastReceivedId}`);
                if (!response.ok) { throw new Error('Network response was not ok'); }
                const messages = await response.json();
                if (messages.length > 0) {
                    const msgSpace = document.getElementById('msg-space');
                    const msgBody = document.getElementById('msg-body');
                    if (msgSpace && msgBody) {
                        const shouldScroll = msgBody.scrollHeight - msgBody.scrollTop - msgBody.clientHeight < 100;
                        messages.forEach(msg => {
                            if (document.querySelector(`[data-msg-id='${msg.id}']`)) return;
                            msgSpace.appendChild(createMessageBubble(msg));
                            lastReceivedId = msg.id;
                        });
                        if (shouldScroll) msgBody.scrollTop = msgBody.scrollHeight;
                    }
                }
            } catch (error) { console.error("Message poll error:", error); }
            finally { setTimeout(pollForMessages, 2500); }
        };

        const pollForSidebarUpdates = async () => {
            if (appData.isSearch) { setTimeout(pollForSidebarUpdates, 15000); return; }
            try {
                const response = await fetch(`fetch_sidebar_updates.php`);
                if (!response.ok) return;
                const updates = await response.json();
                if (updates.length > 0) {
                    updates.forEach(update => {
                        const convLink = document.querySelector(`.conv-item-link[data-conv-id='${update.conversation_id}']`);
                        if (convLink) {
                            const previewEl = convLink.querySelector('.conv-preview');
                            if (previewEl) previewEl.textContent = update.last_message || '...';
                            const badgeEl = convLink.querySelector('.unread-badge');
                            if (badgeEl) {
                                if (update.unread_count > 0 && appData.conversationId != update.conversation_id) {
                                    badgeEl.textContent = update.unread_count;
                                    badgeEl.style.display = 'inline-block';
                                } else {
                                    badgeEl.style.display = 'none';
                                }
                            }
                        }
                    });
                }
            } catch (error) { console.error("Sidebar poll error:", error); }
            finally { setTimeout(pollForSidebarUpdates, 7000); }
        };

        function createMessageBubble(msg) {
            const isSent = msg.sender_id == appData.currentUserId;
            const bubbleWrapper = document.createElement('div');
            bubbleWrapper.className = `msg-bubble-wrapper ${isSent ? 'sent' : 'received'}`;

            let senderNameHtml = '';
            if (appData.currentConversation && appData.currentConversation.type !== 'personal' && msg.sender_id != appData.currentUserId) {
                senderNameHtml = `<a href="?action=start_dm&user_id=${msg.sender_id}" style="font-weight: 600; color: var(--accent-blue-active);">${escapeHtml(msg.sender_name)}</a>`;
            }

            let replyPreviewHtml = '';
            if (msg.reply_to) {
                let original_message_text = 'Deleted Message';
                const originalMsgEl = document.querySelector(`.msg-bubble[data-msg-id='${msg.reply_to}']`);
                if (originalMsgEl) {
                    original_message_text = originalMsgEl.querySelector('p')?.textContent || '[Media]';
                }
                replyPreviewHtml = `<div class="reply-preview-in-bubble">Replying to: ${escapeHtml(original_message_text.substring(0, 60))}</div>`;
            }

            let contentHtml = '';
            if (msg.file_path) {
                const fileName = msg.file_path.split('/').pop();
                const safeFileName = encodeURIComponent(fileName);
                if (msg.message_type === 'image') {
                    contentHtml += `<a href="get_file.php?file=${safeFileName}" target="_blank"><img src="get_file.php?file=${safeFileName}" style="max-width:100%; border-radius:8px; margin-top:0.5rem; margin-bottom:0.5rem;"></a>`;
                } else if (msg.message_type === 'pdf') {
                    contentHtml += `<div class="pdf-card"><div class="pdf-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div class="pdf-details"><div class="pdf-filename">${safeFileName}</div><a href="get_file.php?file=${safeFileName}" target="_blank" class="pdf-view-btn">View PDF</a></div></div>`;
                } else if (msg.message_type === 'video') {
                    contentHtml += `<div class="video-card"><video controls style="max-width:100%;border-radius:12px;background:#222;"><source src="get_file.php?file=${safeFileName}" type="video/mp4">Your browser does not support the video tag.</video></div>`;
                } else {
                    contentHtml += `<a href="get_file.php?file=${safeFileName}" target="_blank" style="display:inline-block;"><span style="display:inline-block;vertical-align:middle;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4a90e2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span><span style="margin-left:8px;">${safeFileName}</span></a>`;
                }
            }

            // --- FIX: USE PRE-FORMATTED HTML FROM SERVER FOR LINKS ---
            if (msg.body) {
                contentHtml += `<p>${msg.body}</p>`;
            }
            // --- END FIX ---

            const sentTime = new Date(msg.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            let reactionsHtml = '';
            if (msg.reactions) {
                const reactionGroups = msg.reactions.split(',');
                reactionGroups.forEach(group => {
                    const parts = group.split(':');
                    if (parts.length === 2) {
                        const emoji = escapeHtml(parts[0]);
                        const count = parseInt(parts[1], 10);
                        if (count > 0) {
                            reactionsHtml += `<span class="reaction">${emoji}<span class="reaction-count">${count}</span></span>`;
                        }
                    }
                });
            }

            bubbleWrapper.innerHTML = `
                <div class="msg-bubble ${isSent ? 'sent' : 'received'}" data-msg-id="${msg.id}" data-sender-id="${msg.sender_id}" data-sent="${escapeHtml(msg.sent_at)}" data-read="${escapeHtml(msg.read_at ?? 'Not read')}">
                    ${senderNameHtml}
                    ${replyPreviewHtml}
                    ${contentHtml}
                    <div class="msg-actions" style="position: absolute; bottom: 4px; left: 8px;"><button class="forward-btn" title="Forward" data-msg-id="${msg.id}" style="background:none;border:none;cursor:pointer;padding:0;"><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width:1.2em;height:1.2em;color:var(--tick-color);'><path stroke-linecap='round' stroke-linejoin='round' d='M4.5 19.5l15-7.5-15-7.5v6.75l10.5.75-10.5.75v6.75z' /></svg></button></div>
                    <div class="msg-meta"><span class="msg-timestamp">${sentTime}</span>${isSent ? `<span class="msg-status">${msg.status === 'read' ? '✓✓' : '✓'}</span>` : ''}</div>
                    <div class="msg-reactions">${reactionsHtml}</div>
                </div>`;
            return bubbleWrapper;
        }

        function setupReplyPreview(msgId) {
            const msgBubble = document.querySelector(`.msg-bubble[data-msg-id='${msgId}']`);
            if (!msgBubble || !replyPreviewContainer || !replyToInput) return;
            let senderName = 'the other person';
            const senderId = msgBubble.dataset.senderId;
            if (parseInt(senderId) === appData.currentUserId) { senderName = 'Yourself';
            } else {
                const senderNameEl = msgBubble.querySelector('a[style*="font-weight: 600"]');
                senderName = senderNameEl ? senderNameEl.textContent : (document.querySelector('.msg-header .manage-link')?.textContent || senderName);
            }
            let bodySnippet = msgBubble.querySelector('p')?.textContent || '[Media]';
            replyToMessageId = msgId;
            replyToInput.value = msgId;
            replyPreviewContainer.style.display = 'block';
            replyPreviewContainer.innerHTML = `<div class="reply-to-name">Replying to ${escapeHtml(senderName)}</div><div class="reply-body-snippet">${escapeHtml(bodySnippet)}</div><button type="button" class="cancel-reply-btn" title="Cancel Reply">×</button>`;
            replyPreviewContainer.querySelector('.cancel-reply-btn').addEventListener('click', cancelReply);
            document.querySelector('.msg-input')?.focus();
        }

        function cancelReply() {
            replyToMessageId = null;
            if(replyToInput) replyToInput.value = '';
            if(replyPreviewContainer) {
                replyPreviewContainer.style.display = 'none';
                replyPreviewContainer.innerHTML = '';
            }
        }

        async function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message? This cannot be undone.')) return;
            try {
                const response = await fetch('delete_message.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message_id: messageId }) });
                const result = await response.json();
                if (result.success) {
                    const messageWrapper = document.querySelector(`.msg-bubble[data-msg-id='${messageId}']`)?.closest('.msg-bubble-wrapper');
                    if (messageWrapper) {
                        messageWrapper.style.transition = 'opacity 0.3s ease';
                        messageWrapper.style.opacity = '0';
                        setTimeout(() => messageWrapper.remove(), 300);
                    }
                } else { alert(`Error: ${result.message}`); }
            } catch (error) { console.error('Failed to delete message:', error); alert('An error occurred. Please try again.'); }
        }
        
        async function reactToMessage(messageId, emoji) {
            const bubble = document.querySelector(`.msg-bubble[data-msg-id='${messageId}']`);
            if (!bubble) return;
            const reactionsDiv = bubble.querySelector('.msg-reactions');
            if (!reactionsDiv) return;
            try {
                const response = await fetch('react_to_message.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message_id: messageId, emoji: emoji }) });
                const result = await response.json();
                if (result.success) {
                    let existingReaction = Array.from(reactionsDiv.children).find(r => r.textContent.startsWith(emoji));
                    if (existingReaction) {
                        if (result.new_count > 0) {
                            existingReaction.querySelector('.reaction-count').textContent = result.new_count;
                        } else {
                            existingReaction.remove();
                        }
                    } else if (result.new_count > 0) {
                        const reactionEl = document.createElement('span');
                        reactionEl.className = 'reaction';
                        reactionEl.innerHTML = `${emoji}<span class="reaction-count">${result.new_count}</span>`;
                        reactionsDiv.appendChild(reactionEl);
                    }
                } else { alert(`Error: ${result.message}`); }
            } catch (e) { console.error("Failed to save reaction:", e); alert("Could not save reaction."); }
        }

        async function forwardMessage(msgId, targetConvId) {
            try {
                const response = await fetch('forward_message.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message_id: msgId, target_conversation_id: targetConvId }) });
                const data = await response.json();
                if (data.success) {
                    alert('Message forwarded!');
                    document.querySelectorAll('.modal-overlay.visible').forEach(m => m.classList.remove('visible'));
                } else { alert('Failed to forward: ' + (data.message || 'Unknown error')); }
            } catch (e) { alert('An error occurred while forwarding.'); }
        }
        
        async function loadManagementPage(url) {
            const msgPanel = document.getElementById('message-panel');
            if (!msgPanel) return;
            const header = msgPanel.querySelector('.msg-header');
            const body = msgPanel.querySelector('#msg-body');
            const footer = msgPanel.querySelector('.msg-footer');
            if (!header || !body) return;
           
            if (!isManagementViewActive) { originalMessagePanelState = { header: header.innerHTML, body: body.innerHTML, footer: footer?.innerHTML }; }
            isManagementViewActive = true;
            body.innerHTML = '<div class="placeholder" style="display:flex;">Loading...</div>';
            if(footer) footer.style.display = 'none';
            try {
                const response = await fetch(url);
                const htmlText = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');
                const newContent = doc.body.innerHTML || 'Could not load content.';
                body.className = 'msg-body-content'; 
                body.innerHTML = newContent;
                if (!header.querySelector('.back-to-chat-btn')) {
                    const backButtonHtml = `<button class="conv-header-btn back-to-chat-btn" title="Back to Chat" style="margin-right: 1rem;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg></button>`;
                    header.insertAdjacentHTML('afterbegin', backButtonHtml);
                }
            } catch (err) {
                console.error("Error loading management page:", err);
                body.innerHTML = '<div class="placeholder" style="display:flex;">Error loading page.</div>';
            }
        }



        function restoreMessageView() {
            if (!isManagementViewActive || !originalMessagePanelState.header) return;
            const msgPanel = document.getElementById('message-panel');
            if (!msgPanel) return;
            const header = msgPanel.querySelector('.msg-header');
            const body = msgPanel.querySelector('.msg-body-content'); 
            const footer = msgPanel.querySelector('.msg-footer');
            if (!header || !body) return;
            const existingBackButton = header.querySelector('.back-to-chat-btn');
            if (existingBackButton) existingBackButton.remove();
            header.innerHTML = originalMessagePanelState.header;
            body.className = 'msg-body'; 
            body.innerHTML = originalMessagePanelState.body;
            if (footer) {
                footer.innerHTML = originalMessagePanelState.footer;
                footer.style.display = 'flex'; 
            }
            isManagementViewActive = false;
            attachAllListeners(); 
            const msgBodyEl = document.getElementById('msg-body');
            if (msgBodyEl) msgBodyEl.scrollTop = msgBodyEl.scrollHeight; 
        }

        function attachAllListeners() {
            const fileInput = document.getElementById('fileUploadInput');
            const msgPreviewsContainer = document.getElementById('msgPreviews');
            const msgForm = document.querySelector('.msg-form');

            const updateInputAndRenderPreviews = () => {
                if (!fileInput || !msgPreviewsContainer) return;

                const dataTransfer = new DataTransfer();
                selectedFilesStore.forEach(file => dataTransfer.items.add(file));
                
                fileInput.files = dataTransfer.files;

                msgPreviewsContainer.innerHTML = '';
                if (selectedFilesStore.length > 0) {
                    const previewWrapper = document.createElement('div');
                    previewWrapper.className = 'file-preview-container';
                    
                    selectedFilesStore.forEach((file, index) => {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'file-preview-item';
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'file-remove-btn';
                        removeBtn.innerHTML = '×';
                        removeBtn.onclick = (e) => {
                            e.stopPropagation();
                            selectedFilesStore.splice(index, 1);
                            updateInputAndRenderPreviews();
                        };

                        if (file.type.startsWith('image/')) {
                            const img = document.createElement('img');
                            img.className = 'file-preview-img';
                            const reader = new FileReader();
                            reader.onload = e => { img.src = e.target.result; };
                            reader.readAsDataURL(file);
                            previewItem.appendChild(img);
                        } else {
                            const fileIcon = document.createElement('div');
                            fileIcon.className = 'file-preview-img';
                            fileIcon.style.cssText = 'background-color:#4b5568; display:flex; align-items:center; justify-content:center; font-size:12px; color:white;';
                            fileIcon.textContent = file.name.split('.').pop().toUpperCase().substring(0, 4);
                            previewItem.appendChild(fileIcon);
                        }
                        
                        previewItem.appendChild(removeBtn);
                        previewWrapper.appendChild(previewItem);
                    });
                    msgPreviewsContainer.appendChild(previewWrapper);
                }
            };
            
            if (fileInput) {
                fileInput.onchange = () => {
                    selectedFilesStore.push(...Array.from(fileInput.files));
                    updateInputAndRenderPreviews();
                };
            }

            if (msgForm) {
                msgForm.addEventListener('submit', () => {
                    setTimeout(() => {
                        selectedFilesStore = [];
                        updateInputAndRenderPreviews();
                        cancelReply();
                    }, 100);
                }, { once: true });
            }
            
            const emojiBtn = document.getElementById('emojiBtn');
            const emojiPicker = document.querySelector('emoji-picker');
            const msgInput = document.querySelector('.msg-input');
            if (emojiBtn && emojiPicker) {
                emojiBtn.onclick = (e) => {
                    e.stopPropagation();
                    emojiPicker.style.display = emojiPicker.style.display === 'block' ? 'none' : 'block';
                };
            }
            if (emojiPicker && msgInput) {
                emojiPicker.addEventListener('emoji-click', event => {
                    msgInput.value += event.detail.unicode;
                    emojiPicker.style.display = 'none';
                    msgInput.focus();
                });
            }
        }

        document.body.addEventListener('click', function(e) {
            // Use the correct escapeHtml here as well
            const emojiPicker = document.querySelector('emoji-picker');
            if (emojiPicker && !e.target.closest('#emojiBtn') && !e.target.closest('emoji-picker')) { emojiPicker.style.display = 'none'; }
            if (ctxMenu && !e.target.closest('#msgContextMenu')) { ctxMenu.style.display = 'none'; }
            if (floatingMenu && !e.target.closest('#openNewMenuBtn') && !e.target.closest('#newMenuFloating')) { floatingMenu.classList.remove('visible'); }
            if (dropdownMenu && dropdownMenu.classList.contains('visible') && !e.target.closest('#dropdownMenu') && !e.target.closest('#openSidebarBtn')) { dropdownMenu.classList.remove('visible'); }
            if (e.target.classList.contains('modal-overlay') || e.target.closest('.close-modal-btn')) { document.querySelectorAll('.modal-overlay.visible').forEach(m => m.classList.remove('visible')); }
            
            if (e.target.closest('#openNewMenuBtn')) floatingMenu.classList.toggle('visible');
            if (e.target.closest('#menuNewChatBtn')) document.getElementById('newChatModal').classList.add('visible');
            if (e.target.closest('#menuNewGroupBtn')) document.getElementById('newGroupModal').classList.add('visible');
            if (e.target.closest('#menuNewChannelBtn')) document.getElementById('newChannelModal').classList.add('visible');

            const manageLink = e.target.closest('.manage-link');
            if (manageLink) { e.preventDefault(); loadManagementPage(manageLink.dataset.url); }
            const backToChatBtn = e.target.closest('.back-to-chat-btn');
            if (backToChatBtn) { e.preventDefault(); restoreMessageView(); }

            const forwardBtn = e.target.closest('.forward-btn');
            if (forwardBtn) {
                const msgId = forwardBtn.dataset.msgId;
                forwardModal.classList.add('visible');
                forwardList.innerHTML = '';
                appData.conversations.forEach(conv => {
                    if (conv.id == appData.conversationId) return;
                    const btn = document.createElement('button');
                    btn.className = 'forward-target-btn';
                    btn.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 12px;margin-bottom:4px;background:#232f3e;color:#fff;border:none;border-radius:6px;cursor:pointer;';
                    btn.innerHTML = `${conv.type === 'personal' ? '👤' : conv.type === 'group' ? '👥' : '📢'} ${escapeHtml(conv.display_name)}`;
                    btn.onclick = () => forwardMessage(msgId, conv.id);
                    forwardList.appendChild(btn);
                });
            }

            const ctxAction = e.target.closest('.ctx-item');
            if (ctxAction) {
                const msgId = ctxMenu.dataset.msgId;
                if(ctxAction.id === 'ctxReply') setupReplyPreview(msgId);
                if(ctxAction.id === 'ctxInfo') {
                    const msgEl = document.querySelector(`.msg-bubble[data-msg-id='${msgId}']`);
                    if (msgEl) alert(`Sent: ${msgEl.dataset.sent}\nRead: ${msgEl.dataset.read}`);
                }
                if(ctxAction.id === 'ctxDelete' && msgId) deleteMessage(msgId);
                ctxMenu.style.display = 'none';
            }

            const reactEmoji = e.target.closest('.react-emoji');
            if (reactEmoji) {
                const msgId = ctxMenu.dataset.msgId;
                const emoji = reactEmoji.dataset.emoji;
                if (msgId && emoji) reactToMessage(msgId, emoji);
                ctxMenu.style.display = 'none';
            }
        });

        const msgPanel = document.getElementById('message-panel');
        if (msgPanel) {
            msgPanel.addEventListener('contextmenu', function(e) {
                const bubble = e.target.closest('.msg-bubble');
                if (bubble) {
                    e.preventDefault();
                    ctxMenu.dataset.msgId = bubble.dataset.msgId;
                    const deleteOption = document.getElementById('ctxDelete');
                    deleteOption.style.display = (parseInt(bubble.dataset.senderId) === appData.currentUserId) ? 'flex' : 'none';
                    ctxMenu.style.top = `${e.pageY}px`;
                    ctxMenu.style.left = `${e.pageX}px`;
                    ctxMenu.style.display = 'block';
                }
            });
        }
        
        attachAllListeners();
        if (appData.isConversationView && document.getElementById('msg-body')) {
            document.getElementById('msg-body').scrollTop = document.getElementById('msg-body').scrollHeight;
        }
        pollForMessages();
        pollForSidebarUpdates();
    });
</script>
</body>
</html>