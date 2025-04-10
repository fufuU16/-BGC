<?php
// Set the default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Database Configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
$host = 'localhost'; // Usually 'localhost'
$username = 'u537987570_judymalahay'; // Your database username
$password = 'Malahayj123'; // Your database password
$database = 'u537987570_bgc_database';
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "❌ Database connection failed: " . $conn->connect_error]));
}

// Get Data from ESP32 (Supports POST and GET)
$bus_id = $_REQUEST['bus_id'] ?? '';
$latitude = $_REQUEST['latitude'] ?? '';
$longitude = $_REQUEST['longitude'] ?? '';
$current_stop = $_REQUEST['current_stop'] ?? 'On Route';
$next_stop = $_REQUEST['next_stop'] ?? 'Unknown';
$eta = $_REQUEST['eta'] ?? '7'; // Placeholder ETA if not provided

// Validate Data
if (!empty($bus_id) && is_numeric($latitude) && is_numeric($longitude)) {
    // Check if the bus_id exists in `bus_details`
    $checkStmt = $conn->prepare("SELECT bus_id FROM bus_details WHERE bus_id = ?");
    $checkStmt->bind_param("s", $bus_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        // Since bus_id is the correct reference, use it directly
        $bus_number = $bus_id;

        // Fetch the last known stop if the current stop is "On Route"
        if ($current_stop === 'On Route') {
            $lastStopStmt = $conn->prepare("SELECT current_stop FROM bus_stop_details WHERE bus_no = ? ORDER BY timestamp DESC LIMIT 1");
            $lastStopStmt->bind_param("s", $bus_number);
            $lastStopStmt->execute();
            $lastStopResult = $lastStopStmt->get_result();
            if ($lastStopResult->num_rows > 0) {
                $lastStopRow = $lastStopResult->fetch_assoc();
                $current_stop = $lastStopRow['current_stop'];
            }
            $lastStopStmt->close();
        }

        // Capture the current timestamp
        $current_timestamp = date('Y-m-d H:i:s');

        // Insert or Update Data in `bus_stop_details`
        $stmt = $conn->prepare("
            INSERT INTO bus_stop_details 
            (bus_no, latitude, longitude, current_stop, next_stop, eta, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                current_stop = VALUES(current_stop),
                next_stop = VALUES(next_stop),
                eta = VALUES(eta),
                timestamp = VALUES(timestamp)
        ");
        $stmt->bind_param("sddssss", $bus_number, $latitude, $longitude, $current_stop, $next_stop, $eta, $current_timestamp);

        if ($stmt->execute()) {
            // If the next stop is known, update the passenger_data table
            if ($next_stop !== 'Unknown') {
                // Get the current passenger count from bus_stop_details
                $passengerCountStmt = $conn->prepare("SELECT passenger_count FROM bus_stop_details WHERE bus_no = ?");
                $passengerCountStmt->bind_param("s", $bus_number);
                $passengerCountStmt->execute();
                $passengerCountResult = $passengerCountStmt->get_result();

                if ($passengerCountResult->num_rows > 0) {
                    $passengerCountRow = $passengerCountResult->fetch_assoc();
                    $passengerCount = $passengerCountRow['passenger_count'];

                    // Update the passenger_data table
                    $updatePassengerStmt = $conn->prepare("
                        INSERT INTO passenger_data (date, route, passengers)
                        VALUES (CURDATE(), ?, ?)
                        ON DUPLICATE KEY UPDATE passengers = passengers + VALUES(passengers)
                    ");
                    $updatePassengerStmt->bind_param("si", $next_stop, $passengerCount);

                    if ($updatePassengerStmt->execute()) {
                        echo json_encode(["status" => "success", "message" => "✅ GPS Data and Passenger Count Successfully Updated"]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "❌ Error Updating Passenger Data: " . $updatePassengerStmt->error]);
                    }
                    $updatePassengerStmt->close();
                } else {
                    echo json_encode(["status" => "error", "message" => "❗ Passenger count not found for bus"]);
                }
                $passengerCountStmt->close();
            } else {
                echo json_encode(["status" => "success", "message" => "✅ GPS Data Successfully Updated"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "❌ Error Updating Data: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "❗ Invalid Bus ID"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "❗ Missing or Invalid GPS Data"]);
}

$conn->close();
?>