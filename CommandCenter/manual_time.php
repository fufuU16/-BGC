<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
include 'db_connection.php';

session_start();
date_default_timezone_set('Asia/Manila'); // Set the time zone to Philippine Time

// Check if the user is logged in and if the username is "Manual12345"
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'Manual12345') {
    // Redirect to unauthorized page or login page
    header("Location: unauthorized.php"); // or "index.php"
    exit();
}

$message = ""; // Initialize a message variable

// Array to map day abbreviations to full names
$dayMap = [
    'Mon' => 'M',
    'Tue' => 'T',
    'Wed' => 'W',
    'Thu' => 'Th',
    'Fri' => 'F',
    'Sat' => 'Sa',
    'Sun' => 'Su'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rfid_tag = $_POST['rfid_tag'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!empty($rfid_tag) && !empty($status)) {
        // Fetch driver details using RFID tag
        $stmt = $conn->prepare("SELECT * FROM drivers WHERE rfid_tag = ?");
        $stmt->bind_param("s", $rfid_tag);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $driver = $result->fetch_assoc();
            $driver_id = $driver['driver_id'];
            $driver_name = $driver['name'];
            $bus_id = $driver['bus_id'];
            $route = $driver['route'];
            $driver_email = $driver['email']; // Assuming there's an email field
            $shift = $driver['shift'];

            // Get current day abbreviation
            $current_day = date('D'); // e.g., Mon, Tue, Wed, etc.
            $current_day_abbr = $dayMap[$current_day]; // Map to database abbreviation
            // Check if the driver is scheduled for today
            $scheduleStmt = $conn->prepare("SELECT * FROM bus_schedule WHERE bus_id = ? AND FIND_IN_SET(?, day) > 0 AND (driver1 = ? OR driver2 = ? OR driver3 = ? OR driver4 = ?)");
            $scheduleStmt->bind_param("ssssss", $bus_id, $current_day_abbr, $driver_name, $driver_name, $driver_name, $driver_name);
            $scheduleStmt->execute();
            $scheduleResult = $scheduleStmt->get_result();

            if ($scheduleResult->num_rows > 0) {
                // Check the last status for the driver
                $lastStatusStmt = $conn->prepare("SELECT status FROM shiftlogs WHERE driver_id = ? ORDER BY shift_date DESC LIMIT 1");
                $lastStatusStmt->bind_param("i", $driver_id);
                $lastStatusStmt->execute();
                $lastStatusResult = $lastStatusStmt->get_result();

                $canLog = false;
                if ($lastStatusResult->num_rows > 0) {
                    $lastStatus = $lastStatusResult->fetch_assoc()['status'];
                    if ($status === "Time In" && $lastStatus === "Time Out") {
                        $canLog = true;
                    } elseif ($status === "Time Out" && $lastStatus === "Time In") {
                        $canLog = true;
                    } else {
                        $message = "Invalid status transition. Current status: $lastStatus";
                    }
                } else {
                    // No previous record, allow "Time In" only
                    if ($status === "Time In") {
                        $canLog = true;
                    } else {
                        $message = "Cannot log 'Time Out' without a 'Time In' record.";
                    }
                }

                if ($canLog) {
                    // Capture the current timestamp
                    $current_time = date('Y-m-d H:i:s');

                    // Log attendance
                    $logStmt = $conn->prepare("INSERT INTO shiftlogs (driver_id, bus_id, shift_date, status, route) VALUES (?, ?, ?, ?, ?)");
                    $logStmt->bind_param("iisss", $driver_id, $bus_id, $current_time, $status, $route);

                    if ($logStmt->execute()) {
                        $message = "Attendance ($status) logged for " . $driver_name;

                        // Send email notification to the driver
                        $mail = new PHPMailer(true);
                        try {
                            $mail->SMTPDebug = 0; // Set to 2 for debugging
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'bgcbus2025capstone@gmail.com';
                            $mail->Password = 'qxmauupgbiczqaci'; // Updated App Password (without spaces)
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('bgcbus2025capstone@gmail.com', 'BGC Bus');
                            $mail->addAddress($driver_email);

                            $mail->isHTML(true);
                            $mail->Subject = 'Attendance Logged';
                            $mail->Body = "
                                <p>Dear $driver_name,</p>
                                <p>Your attendance has been successfully logged as <strong>$status</strong> for bus <strong>$bus_id</strong> on assigned route <strong>$route</strong>.</p>
                                <p>Thank you,</p>
                                <p>BGC Bus Management</p>
                            ";

                            $mail->send();
                            $message .= " Notification email sent to driver.";
                        } catch (Exception $e) {
                            $message .= " Failed to send email notification. Mailer Error: " . $mail->ErrorInfo;
                        }
                    } else {
                        $message = "Error logging attendance: " . $conn->error;
                    }

                    $logStmt->close();
                }

                $lastStatusStmt->close();
            } else {
                $message = "Driver does not have a schedule for today.";
            }

            $scheduleStmt->close();
        } else {
            $message = "Invalid RFID tag.";
        }

        $stmt->close();
    } else {
        $message = "RFID tag or Status not provided.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Time In/Out</title>
    <link rel="stylesheet" href="Login.css">
    <style>
   .logout-containerr {
    position: fixed;
    top: 10px;
    right: 10px;
    z-index: 1000; /* Ensure it stays on top */
}

.logout-containerr a {
    padding: 8px 16px;
    background-color: transparent; /* No background color */
    color: #ffffff; /* White text color */
    text-decoration: none;
    font-weight: bold;
    position: relative;
    margin: 0 30px;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.logout-containerr a:hover {
    background-color: #575757; /* Slightly darker background on hover */
}
    </style>
</head>
<body>
    <div class="logout-containerr">
    <a href="logout.php">Logout</a>
    </div>
    <main>
        <div class="login-container">
            <?php if (!empty($message)): ?>
                <div class="message-box error"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="bgclogo">
                    <img src="../image/bgc.PNG" alt="Bgc Logo">
                </div>
                <label for="rfid_tag">RFID Tag:</label>
                <input type="text" id="rfid_tag" name="rfid_tag" required>
                
                <label for="status">Status:</label>
                <select name="status" id="status" required>
                    <option value="Time In">Time In</option>
                    <option value="Time Out">Time Out</option>
                </select>
                
                <button type="submit">Log Time</button>
            </form>
        </div>
    </main> 
</html>