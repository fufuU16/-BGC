<?php
include 'db_connection.php'; // Ensure this file exists and is correct

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$range = isset($_GET['range']) ? $_GET['range'] : '7days';

switch ($range) {
    case '3months':
        $interval = '3 MONTH';
        break;
    case '6months':
        $interval = '6 MONTH';
        break;
    case 'year':
        $interval = '1 YEAR';
        break;
    case '7days':
    default:
        $interval = '7 DAY';
        break;
}

$query = "
    SELECT DATE(date) as date, 
           route, 
           SUM(passengers) as total_passengers
    FROM passenger_data 
    WHERE date >= CURDATE() - INTERVAL $interval
    GROUP BY DATE(date), route
    ORDER BY DATE(date) ASC
";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(['error' => 'SQL error: ' . $conn->error]);
    exit();
}

$passengerData = [];
while ($row = $result->fetch_assoc()) {
    $passengerData[] = $row;
}

$conn->close();

echo json_encode($passengerData);
?>