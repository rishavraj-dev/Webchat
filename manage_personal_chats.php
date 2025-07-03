<?php
// --- 1. SETUP AND SECURITY ---
session_start();
require_once 'Login/config.php';

// Authenticate user
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Authentication required.");
}
$current_user_id = (int)$_SESSION['user_id'];
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Validate input
if ($target_user_id <= 0 || $target_user_id === $current_user_id) {
    die("Invalid user specified.");
}

// --- 2. FIND THE CONVERSATION ---
$conversation_id = null;
$stmt_find_convo = $mysqli->prepare("
    SELECT T1.conversation_id FROM conversation_members AS T1
    INNER JOIN conversation_members AS T2 ON T1.conversation_id = T2.conversation_id
    WHERE T1.user_id = ? AND T2.user_id = ? AND T1.conversation_id IN (SELECT id FROM conversations WHERE type = 'personal')
");
$stmt_find_convo->bind_param("ii", $current_user_id, $target_user_id);
$stmt_find_convo->execute();
$result_convo = $stmt_find_convo->get_result();
if ($convo_row = $result_convo->fetch_assoc()) {
    $conversation_id = (int)$convo_row['conversation_id'];
}
$stmt_find_convo->close();

if (!$conversation_id) {
    die("Conversation not found.");
}


// --- 3. HANDLE POST ACTIONS (AJAX from the page itself) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid action.'];

    switch ($action) {
        case 'toggle_auto_delete':
            $new_state = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? 1 : 0;
            $stmt = $mysqli->prepare("UPDATE conversations SET auto_delete_enabled = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_state, $conversation_id);
            if ($stmt->execute()) {
                $response = ['success' => true];
            }
            break;

        case 'clear_chat':
            // First, get file paths to unlink from server
            $stmt_files = $mysqli->prepare("SELECT file_path FROM messages WHERE conversation_id = ? AND file_path IS NOT NULL");
            $stmt_files->bind_param("i", $conversation_id);
            $stmt_files->execute();
            $result_files = $stmt_files->get_result();
            while ($row = $result_files->fetch_assoc()) {
                if (file_exists($row['file_path'])) {
                    @unlink($row['file_path']);
                }
            }
            $stmt_files->close();

            // Then, delete message records
            $stmt = $mysqli->prepare("DELETE FROM messages WHERE conversation_id = ?");
            $stmt->bind_param("i", $conversation_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Chat history cleared.'];
            }
            break;

        case 'block_user':
            $stmt = $mysqli->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $current_user_id, $target_user_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'User has been blocked.'];
            }
            break;
    }
    echo json_encode($response);
    exit;
}


// --- 4. FETCH DATA FOR DISPLAY ---
// Get target user info
$stmt_user = $mysqli->prepare("SELECT username, public_id, avatar_path FROM users WHERE id = ?");
$stmt_user->bind_param("i", $target_user_id);
$stmt_user->execute();
$target_user_info = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
$avatar_url = !empty($target_user_info['avatar_path']) ? htmlspecialchars($target_user_info['avatar_path']) : 'avatars/default.png';

// Get auto-delete status for the conversation
$stmt_convo_settings = $mysqli->prepare("SELECT auto_delete_enabled FROM conversations WHERE id = ?");
$stmt_convo_settings->bind_param("i", $conversation_id);
$stmt_convo_settings->execute();
$auto_delete_enabled = $stmt_convo_settings->get_result()->fetch_assoc()['auto_delete_enabled'] == 1;
$stmt_convo_settings->close();

// Get shared media (images)
$stmt_media = $mysqli->prepare("SELECT id, file_path, body FROM messages WHERE conversation_id = ? AND message_type = 'image' AND file_path IS NOT NULL ORDER BY sent_at DESC LIMIT 50");
$stmt_media->bind_param("i", $conversation_id);
$stmt_media->execute();
$shared_media = $stmt_media->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_media->close();

// Get shared files (non-images)
$stmt_files_shared = $mysqli->prepare("SELECT id, file_path, body, sent_at FROM messages WHERE conversation_id = ? AND message_type IN ('file', 'pdf') AND file_path IS NOT NULL ORDER BY sent_at DESC LIMIT 50");
$stmt_files_shared->bind_param("i", $conversation_id);
$stmt_files_shared->execute();
$shared_files = $stmt_files_shared->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_files_shared->close();

?>
<!-- This is an HTML fragment, not a full page. It will be loaded into the main chat window. -->
<style>
    .profile-info-container { padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color); }
    .profile-info-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; }
    .profile-info-name { font-size: 1.5rem; font-weight: 600; margin: 0; }
    .profile-info-id { color: var(--text-secondary); font-size: 0.9rem; }
    .profile-actions, .profile-settings { padding: 1rem; border-bottom: 1px solid var(--border-color); }
    .profile-actions button, .profile-settings .setting-item { display: block; width: 100%; padding: 0.75rem 1rem; background: none; border: none; color: var(--text-primary); text-align: left; font-size: 1rem; border-radius: 8px; cursor: pointer; }
    .profile-actions button:hover { background-color: var(--bg-tertiary); }
    .profile-actions .btn-danger { color: var(--danger-text); }
    .setting-item { display: flex; justify-content: space-between; align-items: center; }
    .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #555; transition: .4s; border-radius: 26px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--accent-blue); }
    input:checked + .slider:before { transform: translateX(24px); }
    .shared-content-container { padding: 1rem; }
    .tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 1rem; }
    .tab-button { padding: 0.5rem 1rem; cursor: pointer; border-bottom: 3px solid transparent; }
    .tab-button.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 5px; }
    .media-grid-item img { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; }
    .file-list-item { display: flex; align-items: center; padding: 0.5rem; border-radius: 6px; }
    .file-list-item:hover { background-color: var(--bg-tertiary); }
    .file-icon { font-size: 1.5rem; margin-right: 1rem; }
    .file-details { flex-grow: 1; }
    .file-name { font-weight: 500; }
    .file-meta { font-size: 0.8rem; color: var(--text-secondary); }
</style>

<div class="profile-info-container">
    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="profile-info-avatar">
    <h2 class="profile-info-name"><?php echo htmlspecialchars($target_user_info['username']); ?></h2>
    <p class="profile-info-id">@<?php echo htmlspecialchars($target_user_info['public_id']); ?></p>
</div>

<div class="profile-settings">
    <div class="setting-item">
        <span>Auto-Delete Messages</span>
        <label class="toggle-switch">
            <input type="checkbox" id="autoDeleteToggle" <?php echo $auto_delete_enabled ? 'checked' : ''; ?>>
            <span class="slider"></span>
        </label>
    </div>
</div>

<div class="profile-actions">
    <button id="clearChatBtn" class="btn-danger">Clear Chat</button>
    <button id="blockUserBtn" class="btn-danger">Block User</button>
</div>

<div class="shared-content-container">
    <div class="tabs">
        <button class="tab-button active" data-tab="media">Media</button>
        <button class="tab-button" data-tab="files">Files</button>
    </div>
    <div id="media" class="tab-content active">
        <div class="media-grid">
            <?php if (empty($shared_media)): ?>
                <p style="grid-column: 1 / -1; color: var(--text-secondary);">No media shared yet.</p>
            <?php else: ?>
                <?php foreach ($shared_media as $media): ?>
                    <a href="get_file.php?file=<?php echo basename($media['file_path']); ?>" target="_blank" class="media-grid-item">
                        <img src="get_file.php?file=<?php echo basename($media['file_path']); ?>" alt="Shared Media">
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div id="files" class="tab-content">
        <div class="file-list">
             <?php if (empty($shared_files)): ?>
                <p style="color: var(--text-secondary);">No files shared yet.</p>
            <?php else: ?>
                <?php foreach ($shared_files as $file): ?>
                    <a href="get_file.php?file=<?php echo basename($file['file_path']); ?>" target="_blank" class="file-list-item">
                        <div class="file-icon">📄</div>
                        <div class="file-details">
                            <div class="file-name"><?php echo htmlspecialchars(basename($file['file_path'])); ?></div>
                            <div class="file-meta"><?php echo date('M j, Y', strtotime($file['sent_at'])); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const pageUrl = `manage_personal_chats.php?user_id=<?php echo $target_user_id; ?>`;

    // --- Tab Switching Logic ---
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const tabId = button.dataset.tab;
            tabContents.forEach(content => {
                content.id === tabId ? content.classList.add('active') : content.classList.remove('active');
            });
        });
    });

    // --- Action Handlers ---
    const autoDeleteToggle = document.getElementById('autoDeleteToggle');
    if(autoDeleteToggle) {
        autoDeleteToggle.addEventListener('change', function() {
            const formData = new FormData();
            formData.append('action', 'toggle_auto_delete');
            formData.append('enabled', this.checked ? '1' : '0');
            fetch(pageUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) alert('Failed to update setting.');
                });
        });
    }

    const clearChatBtn = document.getElementById('clearChatBtn');
    if(clearChatBtn) {
        clearChatBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete all messages in this chat? This cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'clear_chat');
                fetch(pageUrl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Refresh the entire page to show the empty chat
                            window.location.href = 'conversations.php?conversation_id=<?php echo $conversation_id; ?>';
                        } else {
                            alert('Failed to clear chat.');
                        }
                    });
            }
        });
    }

    const blockUserBtn = document.getElementById('blockUserBtn');
    if(blockUserBtn) {
        blockUserBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to block this user? You will no longer be able to send or receive messages from them.')) {
                const formData = new FormData();
                formData.append('action', 'block_user');
                fetch(pageUrl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            alert(data.message);
                            blockUserBtn.disabled = true;
                            blockUserBtn.textContent = 'User Blocked';
                        } else {
                            alert('Failed to block user.');
                        }
                    });
            }
        });
    }
})();
</script>