<?php
// Start the session to store messages
session_start();
// Make sure you have your database connection file
require_once 'config.php';

// --- FIXED: Updated redirect functions to use 'message' and 'message_type' ---
function redirect_with_error($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'error'; // Set type to 'error' for styling
    header("Location: register.php");
    exit();
}

function redirect_with_success($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'success'; // Set type to 'success' for styling
    header("Location: register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- 1. Get All User Input ---
    $username = trim($_POST['username']);
    $public_id = trim($_POST['public_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = $_POST['dob'];

    // --- 2. Full Validation ---
    if (empty($username) || empty($public_id) || empty($email) || empty($password) || empty($dob)) {
        redirect_with_error("All fields are required.");
    }
    if ($password !== $confirm_password) {
        redirect_with_error("Passwords do not match.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_with_error("Invalid email format.");
    }

    // --- 3. Age Verification (Must be 13 or older) ---
    $today = new DateTime();
    $birthDate = new DateTime($dob);
    $age = $today->diff($birthDate)->y;
    if ($age < 13) {
        redirect_with_error("You must be at least 13 years old to register.");
    }

    // --- 4. Check for Duplicates (Public ID and Email) ---
    // Use prepared statements to prevent SQL Injection
    $stmt_check_id = $mysqli->prepare("SELECT id FROM users WHERE public_id = ?");
    $stmt_check_id->bind_param("s", $public_id);
    $stmt_check_id->execute();
    $stmt_check_id->store_result();
    if ($stmt_check_id->num_rows > 0) {
        redirect_with_error("This Public ID is already taken.");
    }
    $stmt_check_id->close();

    $stmt_check_email = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check_email->bind_param("s", $email);
    $stmt_check_email->execute();
    $stmt_check_email->store_result();
    if ($stmt_check_email->num_rows > 0) {
        redirect_with_error("This email address is already registered.");
    }
    $stmt_check_email->close();

    // --- 5. Hash the Password ---
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // --- 6. Insert the New User into the Database ---
    $sql_insert = "INSERT INTO users (username, public_id, email, password_hash, dob) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $mysqli->prepare($sql_insert);
    // 'sssss' means we are binding 5 string variables
    $stmt_insert->bind_param("sssss", $username, $public_id, $email, $password_hash, $dob);

    if ($stmt_insert->execute()) {
        redirect_with_success("Registration successful! You can now log in.");
    } else {
        // For debugging, you might want to log the actual error: error_log($stmt_insert->error);
        redirect_with_error("An unexpected error occurred. Please try again.");
    }

    $stmt_insert->close();
    $mysqli->close();

} else {
    // If accessed directly, redirect back
    header("Location: register.php");
    exit();
}
?>