<?php
// Start the session so we can access it
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session completely
session_destroy();

// Redirect the user to the login page
header("Location: login.php");
exit;
?>