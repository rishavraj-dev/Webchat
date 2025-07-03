<?php
session_start();
require_once 'Login/config.php';
if (!isset($_SESSION['loggedin'])) { header("Location: Login/login.php"); exit; }

$current_user_id = (int)$_SESSION['user_id'];
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify this user is an admin of this group
$stmt = $mysqli->prepare("SELECT name, creator_id FROM conversations WHERE id = ? AND type = 'group'");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group_result = $stmt->get_result();
if ($group_result->num_rows !== 1) { header("Location: conversations.php"); exit; }
$group = $group_result->fetch_assoc();

// Check if current user is a member of the group (not just admin)
$stmt_role = $mysqli->prepare("SELECT role FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
$stmt_role->bind_param("ii", $group_id, $current_user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result();
$my_role = $role_result->fetch_assoc()['role'] ?? '';
$is_member = !empty($my_role); // true if user is in conversation_members

if (!$is_member) { header("Location: conversations.php"); exit; }

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_public_id = trim($_POST['public_id'] ?? '');
    $target_user_id = 0;
    if ($target_public_id) {
        $stmt_user = $mysqli->prepare("SELECT id FROM users WHERE public_id = ?");
        $stmt_user->bind_param("s", $target_public_id);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        if ($user_result->num_rows === 1) {
            $target_user_id = $user_result->fetch_assoc()['id'];
        }
    }
    if ($action === 'add_member' && $target_user_id) {
        // Add as member if not already
        $stmt_add = $mysqli->prepare("INSERT IGNORE INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, 'member')");
        $stmt_add->bind_param("ii", $group_id, $target_user_id);
        $stmt_add->execute();
    } elseif ($action === 'remove_member' && $target_user_id && $target_user_id != $group['creator_id']) {
        // Remove member (cannot remove creator)
        $stmt_remove = $mysqli->prepare("DELETE FROM conversation_members WHERE conversation_id = ? AND user_id = ?");
        $stmt_remove->bind_param("ii", $group_id, $target_user_id);
        $stmt_remove->execute();
    } elseif ($action === 'make_admin' && $target_user_id && $target_user_id != $group['creator_id']) {
        // Promote to admin
        $stmt_admin = $mysqli->prepare("UPDATE conversation_members SET role = 'admin' WHERE conversation_id = ? AND user_id = ?");
        $stmt_admin->bind_param("ii", $group_id, $target_user_id);
        $stmt_admin->execute();
    } elseif ($action === 'remove_admin' && $target_user_id && $target_user_id != $group['creator_id']) {
        // Demote to member
        $stmt_demote = $mysqli->prepare("UPDATE conversation_members SET role = 'member' WHERE conversation_id = ? AND user_id = ?");
        $stmt_demote->bind_param("ii", $group_id, $target_user_id);
        $stmt_demote->execute();
    } elseif ($action === 'delete_group') {
        // Only creator can delete
        if ($group['creator_id'] == $current_user_id) {
            $mysqli->query("DELETE FROM messages WHERE conversation_id = $group_id");
            $mysqli->query("DELETE FROM conversation_members WHERE conversation_id = $group_id");
            $mysqli->query("DELETE FROM conversations WHERE id = $group_id");
            header("Location: conversations.php");
            exit;
        }
    }
    header("Location: manage_group.php?id=" . $group_id);
    exit;
}

// Fetch group members and their roles
$stmt_members = $mysqli->prepare("SELECT u.id, u.username, u.public_id, cm.role FROM users u JOIN conversation_members cm ON u.id = cm.user_id WHERE cm.conversation_id = ?");
$stmt_members->bind_param("i", $group_id);
$stmt_members->execute();
$members = $stmt_members->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate sharable group link
$group_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/conversations.php?conversation_id=" . $group_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($group['name']); ?> - Group Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/managegroup.css">
    <style>
       
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($group['name']); ?></h1>

        <div class="group-link-box">
            <span style="font-weight:600;">Sharable Group Link:</span>
            <input type="text" value="<?php echo htmlspecialchars($group_link); ?>" id="groupLink" readonly>
            <button onclick="navigator.clipboard.writeText(document.getElementById('groupLink').value);this.textContent='Copied!';setTimeout(()=>this.textContent='Copy',1000);">Copy</button>
        </div>

        <div class="section-card">
            <h2 style="margin-top:0;">Members</h2>
            <input type="text" id="memberSearchBar" placeholder="Search members by name or public ID...">
            <ul class="member-list" id="membersList">
                <?php foreach ($members as $member): ?>
                    <li data-username="<?php echo strtolower(htmlspecialchars($member['username'])); ?>" data-publicid="<?php echo strtolower(htmlspecialchars($member['public_id'])); ?>">
                        <span class="member-info">
                            <?php echo htmlspecialchars($member['username']); ?> (<?php echo htmlspecialchars($member['public_id']); ?>)
                            <?php if ($member['id'] == $group['creator_id']): ?>
                                <span class="owner-badge">Owner</span>
                            <?php elseif ($member['role'] === 'admin'): ?>
                                <span class="admin-badge">Admin</span>
                            <?php endif; ?>
                        </span>
                        <span class="member-actions">
                            <?php if ($my_role === 'admin' && $member['id'] != $group['creator_id']): ?>
                                <?php if ($member['role'] === 'admin'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="remove_admin">
                                        <input type="hidden" name="public_id" value="<?php echo htmlspecialchars($member['public_id']); ?>">
                                        <button type="submit" style="background:#374151;color:#fff;">Remove Admin</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="make_admin">
                                        <input type="hidden" name="public_id" value="<?php echo htmlspecialchars($member['public_id']); ?>">
                                        <button type="submit" style="background:#4a93e0;color:#fff;">Make Admin</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="public_id" value="<?php echo htmlspecialchars($member['public_id']); ?>">
                                    <button type="submit" style="background:#e04a4a;color:#fff;">Remove</button>
                                </form>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="section-card">
            <h2 style="margin-top:0;">Add New Member</h2>
            <?php
            $show_add_member = true;
            $stmt_vis = $mysqli->prepare("SELECT visibility FROM conversations WHERE id = ?");
            $stmt_vis->bind_param("i", $group_id);
            $stmt_vis->execute();
            $vis_result = $stmt_vis->get_result();
            $visibility = $vis_result->fetch_assoc()['visibility'] ?? 'public';
            if ($visibility === 'private' && $my_role !== 'admin' && $group['creator_id'] != $current_user_id) {
                $show_add_member = false;
            }
            ?>
            <?php if ($show_add_member): ?>
            <form class="add-member-form" action="manage_group.php?id=<?php echo $group_id; ?>" method="POST">
                <input type="hidden" name="action" value="add_member">
                <input type="text" name="public_id" placeholder="Enter user's Public ID" required>
                <button type="submit">Add Member</button>
            </form>
            <?php else: ?>
                <div style="color:var(--text-secondary);">Only admins can add members to a private group.</div>
            <?php endif; ?>
        </div>

        <div class="section-card shared-section">
            <h2 style="margin-top:0;">Shared Images</h2>
            <div class="shared-images-list">
            <?php
            $stmt_imgs = $mysqli->prepare("SELECT file_path, sender_id FROM messages WHERE conversation_id = ? AND message_type = 'image' AND file_path IS NOT NULL ORDER BY id DESC");
            $stmt_imgs->bind_param("i", $group_id);
            $stmt_imgs->execute();
            $img_result = $stmt_imgs->get_result();
            if ($img_result->num_rows > 0):
                while ($img = $img_result->fetch_assoc()):
            ?>
                <div class="shared-image">
                    <a href="get_file.php?file=<?php echo htmlspecialchars(basename($img['file_path'])); ?>" target="_blank">
                        <img src="get_file.php?file=<?php echo htmlspecialchars(basename($img['file_path'])); ?>" alt="Shared Image">
                    </a>
                </div>
            <?php endwhile; else: ?>
                <span class="no-shared">No images shared yet.</span>
            <?php endif; ?>
            </div>

            <h2>Shared Files</h2>
            <div class="shared-files-list">
            <?php
            $stmt_files = $mysqli->prepare("SELECT file_path, sender_id FROM messages WHERE conversation_id = ? AND message_type = 'pdf' AND file_path IS NOT NULL ORDER BY id DESC");
            $stmt_files->bind_param("i", $group_id);
            $stmt_files->execute();
            $file_result = $stmt_files->get_result();
            if ($file_result->num_rows > 0):
                while ($file = $file_result->fetch_assoc()):
            ?>
                <div class="shared-file">
                    <a href="get_file.php?file=<?php echo htmlspecialchars(basename($file['file_path'])); ?>" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5em;height:1.5em;vertical-align:middle;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <?php echo htmlspecialchars(substr(basename($file['file_path']), 14)); ?>
                    </a>
                </div>
            <?php endwhile; else: ?>
                <span class="no-shared">No files shared yet.</span>
            <?php endif; ?>
            </div>
        </div>

        <?php if ($group['creator_id'] == $current_user_id): ?>
        <div class="danger-zone">
            <h2 style="color:#e04a4a;margin-top:0;">Delete Group</h2>
            <form action="manage_group.php?id=<?php echo $group_id; ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this group? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_group">
                <button type="submit">Delete This Group Permanently</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <button onclick="window.location.reload()" style="margin-bottom:1em;">← Back to Chat</button>
    <script>
    // Member search filter
    document.getElementById('memberSearchBar').addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        document.querySelectorAll('#membersList li').forEach(li => {
            const username = li.getAttribute('data-username');
            const publicid = li.getAttribute('data-publicid');
            if (!val || username.includes(val) || publicid.includes(val)) {
                li.style.display = '';
            } else {
                li.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>