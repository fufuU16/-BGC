<?php
date_default_timezone_set('Asia/Manila');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database configuration
$host = 'localhost'; // Usually 'localhost'
$username = 'u537987570_judymalahay'; // Your database username
$password = 'Malahayj123'; // Your database password
$database = 'u537987570_bgc_database';

// Create database connection
$conn = new mysqli($host, $username, $password, $database);

// Debugging: Output received POST data
echo "Received POST data: ";
print_r($_POST);

// Debugging: Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
} else {
    echo "Database connected successfully.<br>";
}

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rfid = $_POST['rfid'] ?? '';
    $status = $_POST['status'] ?? ''; // "Time In" or "Time Out"

    if (!empty($rfid) && !empty($status)) {
        // Query the database to find the driver with the given RFID tag
        $stmt = $conn->prepare("SELECT * FROM drivers WHERE rfid_tag = ?");
        $stmt->bind_param("s", $rfid);
        $stmt->execute();
        $result = $stmt->get_result();

        // If a match is found
        if ($result->num_rows > 0) {
            $driver = $result->fetch_assoc();

            // Extract required values
            $driver_id = $driver['driver_id'];
            $driver_name = $driver['name'];
            $bus_id = $driver['bus_id'];
            $route = $driver['route'];
            $driver_email = $driver['email']; // Assuming email is stored in the drivers table

            // Debugging: Check retrieved values
            echo "Driver ID: $driver_id, Bus ID: $bus_id, Route: $route, Email: $driver_email<br>";

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
                    echo "Invalid status transition. Current status: $lastStatus<br>";
                }
            } else {
                // No previous record, allow "Time In" only
                if ($status === "Time In") {
                    $canLog = true;
                } else {
                    echo "Cannot log 'Time Out' without a 'Time In' record.<br>";
                }
            }

            if ($canLog) {
                // Capture the current timestamp
                $current_time = date('Y-m-d H:i:s');

                // Log attendance into the new table without foreign key constraints
                $logStmt = $conn->prepare("INSERT INTO shiftlogs (driver_id, bus_id, shift_date, status, route) VALUES (?, ?, ?, ?, ?)");
                $logStmt->bind_param("iisss", $driver_id, $bus_id, $current_time, $status, $route);

                if ($logStmt->execute()) {
                    echo "Attendance ($status) logged for " . $driver_name;

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
                        echo "Notification email sent to driver.";
                    } catch (Exception $e) {
                        echo "Failed to send email notification. Mailer Error: " . $mail->ErrorInfo;
                    }
                } else {
                    echo "Error logging attendance: " . $conn->error;
                }

                $logStmt->close();
            }

            $lastStatusStmt->close();
        } else {
            echo "Invalid RFID";
        }

        $stmt->close();
    } else {
        echo "RFID or Status not provided";
    }
} else {
    echo "Invalid request method";
}

// Close the database connection
$conn->close();
?>