<?php

// Development error reporting (enable during debugging)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure this is at the very top
date_default_timezone_set('Asia/Kolkata');

// Database Configuration
define('DB_HOST', '');  // This is correct based on your screenshot
define('DB_USERNAME', '');        // This is from your screenshot
define('DB_PASSWORD', '');      // Your InfinityFree account password
define('DB_NAME', '');    // The full name of your webchat_db
define('DB_PORT', '');                    // ** NEW: We are now adding the port **

// Create a new database connection object, now including the port
$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

// Check for a connection error
if ($mysqli->connect_error) {
    // If there is an error, stop the script and display a generic error message.
    die('Connection Error: Could not connect to the database. Error: ' . $mysqli->connect_error);
}
// Set the timezone FOR THIS CONNECTION to Indian Standard Time (+05:30)
$mysqli->query("SET time_zone = '+05:30'");

// Optional: Set the character set to utf8mb4 for full emoji and language support
$mysqli->set_charset("utf8mb4");


// === SMTP CONFIG (shared) ===
// Fill with your Brevo (Sendinblue) SMTP details. Keep password empty in repo.
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp-relay.brevo.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', ''); // your Brevo login email
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', ''); // your Brevo SMTP key (NOT account password)
if (!defined('SMTP_FROM')) define('SMTP_FROM', '');         // verified sender in Brevo
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'WebChat');

?>