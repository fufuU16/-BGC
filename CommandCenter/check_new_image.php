<?php
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "u537987570_judymalahay", "Malahayj123", "u537987570_bgc_database");

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode(['error' => 'Database connection failed.']));
}

// Retrieve the latest image from the images table
$stmt = $conn->prepare("SELECT image_path FROM images ORDER BY id DESC LIMIT 1");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $latestImage = $result->fetch_assoc();
    $stmt->close();
} else {
    error_log("Failed to prepare statement: " . $conn->error);
    echo json_encode(['error' => 'Failed to prepare statement.']);
    exit();
}

$conn->close();

if ($latestImage) {
    error_log("Latest image path: " . $latestImage['image_path']);
    echo json_encode(['newImage' => $latestImage['image_path']]);
} else {
    error_log("No images found.");
    echo json_encode(['newImage' => null]);
}
?>