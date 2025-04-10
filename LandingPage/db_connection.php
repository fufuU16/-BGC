<?php
// Database configuration
$host = 'localhost'; // Usually 'localhost'
$username = 'u537987570_judymalahay'; // Your database username
$password = 'Malahayj123'; // Your database password
$database = 'u537987570_bgc_database'; // Your database name

// Create a connection
$conn = new mysqli($host, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>