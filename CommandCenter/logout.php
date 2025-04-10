<?php
include 'db_connection.php';
session_start();

// Set the correct timezone
date_default_timezone_set('Asia/Manila');

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Log the logout action
    $action = "User logged out";
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    // Optional: capture logout time
    $logout_time = date('Y-m-d H:i:s');

    // Prepare and execute the insert log
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, ?)");

    if (!$logStmt) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $logStmt->bind_param("isssss", $user_id, $username, $action, $ip_address, $user_agent, $logout_time);

    if (!$logStmt->execute()) {
        die("Execute failed: " . htmlspecialchars($logStmt->error));
    }

    $logStmt->close();
} else {
    echo "No session data to log.";
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>
