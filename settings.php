<?php
// --- 1. SETUP AND SECURITY ---
session_start();
require_once 'Login/config.php';
if (!isset($_SESSION['loggedin'])) { header("Location: Login/login.php"); exit; }
$current_user_id = (int)$_SESSION['user_id'];
// ... (Standard database session validation check goes here) ...

$message = '';
$message_type = '';

// --- 2. HANDLE ALL FORM SUBMISSIONS ON THIS PAGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- LOGIC TO UPDATE PROFILE INFO (Name & Public ID) ---
    if ($action === 'update_profile') {
        $new_username = trim($_POST['username'] ?? '');
        $new_public_id = trim($_POST['public_id'] ?? '');

        // Basic validation
        if (empty($new_username) || empty($new_public_id)) {
            $message = "Display Name and Public ID cannot be empty.";
            $message_type = 'error';
        } else {
            // Check if public ID is already taken by another user
            $stmt_check = $mysqli->prepare("SELECT id FROM users WHERE public_id = ? AND id != ?");
            $stmt_check->bind_param("si", $new_public_id, $current_user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $message = "This Public ID is already taken.";
                $message_type = 'error';
            } else {
                // Update user's profile
                $stmt_update = $mysqli->prepare("UPDATE users SET username = ?, public_id = ? WHERE id = ?");
                $stmt_update->bind_param("ssi", $new_username, $new_public_id, $current_user_id);
                if ($stmt_update->execute()) {
                    $message = "Profile updated successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update profile.";
                    $message_type = 'error';
                }
                $stmt_update->close();
            }
            $stmt_check->close();
        }
    }

    // --- LOGIC TO UPDATE PASSWORD ---
    elseif ($action === 'update_password') {
        // ... (This logic is unchanged)
    }

    // --- LOGIC TO UPDATE PROFILE PICTURE ---
    elseif ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            // Find user's old avatar to delete it
            $stmt_old = $mysqli->prepare("SELECT avatar_path FROM users WHERE id = ?");
            $stmt_old->bind_param("i", $current_user_id);
            $stmt_old->execute();
            $old_avatar = $stmt_old->get_result()->fetch_assoc()['avatar_path'];
            if (!empty($old_avatar) && file_exists($old_avatar)) {
                unlink($old_avatar); // Delete the old file
            }
            $stmt_old->close();
            
            // Process and save the new file
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png'];
            if (in_array($file['type'], $allowed_types) && $file['size'] < 2097152) {
                $upload_dir = 'avatars/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $filename = 'user_' . $current_user_id . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $destination = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $stmt_update = $mysqli->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $destination, $current_user_id);
                    $stmt_update->execute();
                    $message = "Profile picture updated!";
                    $message_type = 'success';
                } else { $message = "Failed to move uploaded file."; $message_type = 'error'; }
            } else { $message = "Invalid file type or size (Max 2MB)."; $message_type = 'error'; }
        }
    }
    
    // --- NEW LOGIC TO REMOVE PROFILE PICTURE ---
    elseif ($action === 'remove_avatar') {
        // 1. Find the path to the current avatar file
        $stmt_find = $mysqli->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt_find->bind_param("i", $current_user_id);
        $stmt_find->execute();
        $result = $stmt_find->get_result();
        if($result->num_rows === 1){
            $user = $result->fetch_assoc();
            $current_avatar_path = $user['avatar_path'];

            // 2. Delete the physical file from the server if it exists
            if (!empty($current_avatar_path) && file_exists($current_avatar_path)) {
                unlink($current_avatar_path);
            }
        }
        $stmt_find->close();

        // 3. Update the database record to remove the path (set it to NULL)
        $stmt_remove = $mysqli->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?");
        $stmt_remove->bind_param("i", $current_user_id);
        if($stmt_remove->execute()){
            $message = "Profile picture removed.";
            $message_type = 'success';
        } else {
            $message = "Error removing profile picture.";
            $message_type = 'error';
        }
        $stmt_remove->close();
    }
}

// --- 3. FETCH CURRENT USER DATA FOR DISPLAY ---
$stmt_user = $mysqli->prepare("SELECT username, public_id, email, avatar_path FROM users WHERE id = ?");
$stmt_user->bind_param("i", $current_user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Webchat</title>
    <style>
        :root { --bg-main: #17212b; --bg-primary: #1f2937; --bg-secondary: #242f3d; --border-color: #374151; --text-primary: #e5e7eb; --text-secondary: #9ca3af; --accent-blue: #4a93e0; --success-bg: #059669; --error-bg: #be123c; --danger-color: #ef4444;}
        body { font-family: -apple-system, sans-serif; background-color: var(--bg-main); color: var(--text-primary); padding: 2rem; }
        .container { max-width: 700px; margin: auto; }
        .card { background-color: var(--bg-primary); padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        h1, h2 { border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 0.75rem; background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); }
        .btn { display: inline-block; padding: 0.6rem 1.2rem; background-color: var(--accent-blue); color: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; border:none; }
        .btn.danger { background-color: var(--danger-color); }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; color: #fff; }
        .message.success { background-color: var(--success-bg); }
        .message.error { background-color: var(--error-bg); }
        .avatar-section { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .avatar-preview { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background-color: var(--accent-blue); }
        .avatar-actions { display: flex; flex-direction: column; gap: 0.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Settings</h1>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Profile Picture Section -->
        <div class="card">
            <h2>Profile Picture</h2>
            <div class="avatar-section">
                <img src="<?php echo !empty($user_data['avatar_path']) ? htmlspecialchars($user_data['avatar_path']) : 'avatars/default.png'; ?>" alt="Your Avatar" class="avatar-preview">
                <div class="avatar-actions">
                    <form action="settings.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_avatar">
                        <input type="file" name="avatar" required>
                        <button type="submit" class="btn" style="margin-top: 0.5rem;">Upload New Picture</button>
                    </form>
                    
                    <!-- NEW REMOVE BUTTON FORM -->
                    <?php if (!empty($user_data['avatar_path'])): ?>
                    <form action="settings.php" method="POST" onsubmit="return confirm('Are you sure you want to remove your profile picture?');">
                        <input type="hidden" name="action" value="remove_avatar">
                        <button type="submit" class="btn danger">Remove Picture</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Info Section -->
        <div class="card">
            <h2>Profile Information</h2>
            <form action="settings.php" method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group"><label for="username">Display Name</label><input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required></div>
                <div class="form-group"><label for="public_id">Public ID</label><input type="text" id="public_id" name="public_id" value="<?php echo htmlspecialchars($user_data['public_id']); ?>" required></div>
                <div class="form-group"><label>Email (cannot be changed)</label><input type="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" disabled></div>
                <button type="submit" class="btn">Save Profile Changes</button>
            </form>
        </div>

        <!-- Change Password Section -->
        <div class="card">
            <h2>Change Password</h2>
            <form action="settings.php" method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group"><label for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" required></div>
                <div class="form-group"><label for="new_password">New Password</label><input type="password" id="new_password" name="new_password" required></div>
                <div class="form-group"><label for="confirm_password">Confirm New Password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        </div>
        <a href="conversations.php" style="color:var(--accent-blue); font-weight:600;">← Back to Chats</a>
    </div>
</body>
</html>