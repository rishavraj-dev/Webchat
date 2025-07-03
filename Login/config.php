<?php

// Ensure this is at the very top
date_default_timezone_set('Asia/Kolkata');

// Database Configuration
define('DB_HOST', 'use your ');  // This is correct based on your screenshot
define('DB_USERNAME', 'use your ');        // This is from your screenshot
define('DB_PASSWORD', 'use your');      // Your InfinityFree account password
define('DB_NAME', 'use your');    // The full name of your webchat_db
define('DB_PORT', 'use your ');                    // ** NEW: We are now adding the port **

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


?>