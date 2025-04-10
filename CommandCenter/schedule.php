<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
date_default_timezone_set('Asia/Manila');

// Include PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

include 'db_connection.php'; // Ensure this file correctly sets up the database connection

session_start();
$message = '';
$messageClass = '';

// Function to log activity
function logActivity($conn, $user_id, $username, $action) {
    $logQuery = "INSERT INTO activity_logs (user_id, username, action, timestamp) VALUES (?, ?, ?, NOW())";
    if ($stmt = $conn->prepare($logQuery)) {
        $stmt->bind_param("iss", $user_id, $username, $action);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle AJAX request to fetch schedule and driver assignments
if (isset($_GET['bus_id'])) {
    $bus_id = $_GET['bus_id'];

    // Fetch the current schedule and driver assignments for the selected bus
    $scheduleQuery = "SELECT * FROM bus_schedule WHERE bus_id = ?";
    $scheduleStmt = $conn->prepare($scheduleQuery);
    $scheduleStmt->bind_param("s", $bus_id);
    $scheduleStmt->execute();
    $scheduleResult = $scheduleStmt->get_result();

    $scheduleData = [];
    if ($scheduleResult->num_rows > 0) {
        $scheduleData = $scheduleResult->fetch_assoc();
    }

    echo json_encode($scheduleData);
    exit();
}

// Fetch available buses
$busQuery = "SELECT bus_id FROM bus_details";
$busResult = $conn->query($busQuery);
$buses = [];
if ($busResult->num_rows > 0) {
    while ($row = $busResult->fetch_assoc()) {
        $buses[] = $row['bus_id'];
    }
}

// Fetch all drivers who are not archived
$driverQuery = "SELECT name, email FROM drivers WHERE archived = 0";
$driverResult = $conn->query($driverQuery);
$allDrivers = [];
$driverEmails = [];
if ($driverResult->num_rows > 0) {
    while ($row = $driverResult->fetch_assoc()) {
        $allDrivers[] = $row['name'];
        $driverEmails[$row['name']] = $row['email'];
    }
}

$availableDrivers = $allDrivers; // Initialize available drivers with all drivers

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['reset']) && $_POST['reset'] == "1") {
        // Reset the schedule and driver assignments
        $bus_id = $_POST['bus_number'];

        // Reset the schedule for the selected bus
        $resetScheduleStmt = $conn->prepare("DELETE FROM bus_schedule WHERE bus_id = ?");
        $resetScheduleStmt->bind_param("s", $bus_id);
        $resetScheduleStmt->execute();
        $resetScheduleStmt->close();

        // Reset driver assignments for the selected bus
        $resetDriversStmt = $conn->prepare("UPDATE drivers SET bus_id = NULL, route = NULL, shift = NULL, additional_bus_id = NULL WHERE bus_id = ?");
        $resetDriversStmt->bind_param("s", $bus_id);
        $resetDriversStmt->execute();
        $resetDriversStmt->close();

        $message = "Schedule and driver assignments reset successfully!";
        $messageClass = "success";
    } else {
        $bus_id = $_POST['bus_number'];
        $days = isset($_POST['days']) ? $_POST['days'] : [];
        $driver1_name = $_POST['driver1_name'];
        $driver1_shift = $_POST['driver1_shift'];
        $driver2_name = $_POST['driver2_name'];
        $driver2_shift = $_POST['driver2_shift'];
        $driver3_name = $_POST['driver3_name'];
        $driver3_shift = $_POST['driver3_shift'];
        $driver4_name = $_POST['driver4_name'];
        $route = $_POST['route'];

        if (!empty($days)) {
            // Convert days to initials
            $dayInitials = array(
                "Monday" => "M",
                "Tuesday" => "T",
                "Wednesday" => "W",
                "Thursday" => "Th",
                "Friday" => "F",
                "Saturday" => "Sa",
                "Sunday" => "Su"
            );
            $daysString = implode(',', array_map(function($day) use ($dayInitials) {
                return $dayInitials[$day];
            }, $days));

            // Check if the bus_id exists in the bus_schedule table
            $scheduleCheckStmt = $conn->prepare("SELECT * FROM bus_schedule WHERE bus_id = ?");
            $scheduleCheckStmt->bind_param("s", $bus_id);
            $scheduleCheckStmt->execute();
            $scheduleResult = $scheduleCheckStmt->get_result();

            if ($scheduleResult->num_rows > 0) {
                // Update the existing schedule
                $updateStmt = $conn->prepare("UPDATE bus_schedule SET driver1 = ?, driver1_shift = ?, driver2 = ?, driver2_shift = ?, driver3 = ?, driver3_shift = ?, driver4 = ?, route = ?, day = ? WHERE bus_id = ?");
                $updateStmt->bind_param("ssssssssss", $driver1_name, $driver1_shift, $driver2_name, $driver2_shift, $driver3_name, $driver3_shift, $driver4_name, $route, $daysString, $bus_id);
            } else {
                // Insert a new schedule
                $updateStmt = $conn->prepare("INSERT INTO bus_schedule (bus_id, day, driver1, driver1_shift, driver2, driver2_shift, driver3, driver3_shift, driver4, route) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $updateStmt->bind_param("ssssssssss", $bus_id, $daysString, $driver1_name, $driver1_shift, $driver2_name, $driver2_shift, $driver3_name, $driver3_shift, $driver4_name, $route);
            }

            if ($updateStmt->execute()) {
                $message = "Schedule updated successfully!";
                $messageClass = "success";

                // Update the drivers table to reflect the bus, route, and shift assignment
                $driverUpdateStmt = $conn->prepare("UPDATE drivers SET bus_id = ?, route = ?, shift = ?, additional_bus_id = NULL WHERE name = ?");

                // Use variables for binding parameters
                $shift1 = $driver1_shift;
                $shift2 = $driver2_shift;
                $shift3 = $driver3_shift;

                // Bind parameters using variables for primary drivers
                $driverUpdateStmt->bind_param("ssss", $bus_id, $route, $shift1, $driver1_name);
                $driverUpdateStmt->execute();
                $driverUpdateStmt->bind_param("ssss", $bus_id, $route, $shift2, $driver2_name);
                $driverUpdateStmt->execute();
                $driverUpdateStmt->bind_param("ssss", $bus_id, $route, $shift3, $driver3_name);
                $driverUpdateStmt->execute();

                // Update the additional driver with a separate statement to set additional_bus_id
                $driverUpdateStmt = $conn->prepare("UPDATE drivers SET additional_bus_id = ? WHERE name = ?");
                $driverUpdateStmt->bind_param("ss", $bus_id, $driver4_name);
                $driverUpdateStmt->execute();

                $driverUpdateStmt->close();

                // Log the schedule update activity
                $currentUsername = $_SESSION['username'] ?? 'Unknown';
                $currentUserId = $_SESSION['user_id'] ?? null; // Ensure user_id is stored in session
                if ($currentUserId) {
                    logActivity($conn, $currentUserId, $currentUsername, "Updated schedule for bus $bus_id on $daysString");
                } else {
                    $message = "Error: User ID not found in session.";
                    $messageClass = "error";
                }

                // Send email notifications to drivers
                $mail = new PHPMailer(true);
                try {
                    //Server settings
                    $mail->SMTPDebug = 0; // Set to 2 for debugging
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'bgcbus2025capstone@gmail.com';
                    $mail->Password = 'qxmauupgbiczqaci'; // Updated App Password (without spaces)
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    //Recipients
                    $mail->setFrom('bgcbus2025capstone@gmail.com', 'Bus Schedule');
                    $mail->addAddress($driverEmails[$driver1_name]); // Add a recipient
                    $mail->addAddress($driverEmails[$driver2_name]);
                    $mail->addAddress($driverEmails[$driver3_name]);
                    $mail->addAddress($driverEmails[$driver4_name]);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Bus Schedule';
                    $mail->Body    = "Dear Driver,<br><br>Your schedule has been updated. You are assigned to bus number $bus_id on the following days: $daysString.<br>Route: $route.<br><br>Best regards,<br>Bus Management Team";

                    $mail->send();
                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                // Debugging: Output SQL error
                $message = "Error: " . $updateStmt->error;
                $messageClass = "error";
            }

            $updateStmt->close();
            $scheduleCheckStmt->close();
        } else {
            $message = "Error: No days selected.";
            $messageClass = "error";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Bus and Drivers</title>
    <link rel="stylesheet" href="schedule.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#bus_number').change(function() {
                var busId = $(this).val();
                if (busId) {
                    $.ajax({
                        url: 'schedule.php',
                        type: 'GET',
                        data: { bus_id: busId },
                        dataType: 'json',
                        success: function(data) {
                            if (data) {
                                $('#driver1_name').val(data.driver1);
                                $('#driver2_name').val(data.driver2);
                                $('#driver3_name').val(data.driver3);
                                $('#driver4_name').val(data.driver4);
                                $('#route').val(data.route);
                            }
                        }
                    });
                }
            });
        });

        function confirmReset() {
            if (confirm("Are you sure you want to reset the schedule? This will clear all current assignments.")) {
                // Set a hidden input to indicate reset action
                document.getElementById("resetAction").value = "1";
                document.getElementById("scheduleForm").submit();
            }
        }
    </script>
</head>
<body>
<header>
    <?php
    if (!isset($_SESSION['username'])) {
        // Redirect to login page if not logged in
        header("Location: index.php");
        exit();
    }

    // Assuming the user's role is stored in the session
    $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    ?>
    <div class="header-content">
        <div class="username-display">
            <?php if (isset($_SESSION['username'])): ?>
                <span> <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <?php endif; ?>
        </div>
        <nav>
            <a href="Dashboard.php" class="<?php echo $current_page == 'Dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <div class="dropdown">
                <a href="#" class="dropbtn <?php echo in_array($current_page, ['Shiftlogs.php', 'activity_logs.php', 'drivers.php']) ? 'active-dropdown' : ''; ?>">Logs</a>
                <div class="dropdown-content">
                    <a href="Shiftlogs.php" class="<?php echo $current_page == 'Shiftlogs.php' ? 'active' : ''; ?>">Shift Logs</a>
                    <?php if ($userRole == 'SuperAdmin'): ?>
                        <a href="activity_logs.php" class="<?php echo $current_page == 'activity_logs.php' ? 'active' : ''; ?>">Activity Logs</a>
                    <?php endif; ?>
                    <?php if (in_array($userRole, ['MidAdmin', 'SuperAdmin'])): ?>
                        <a href="drivers.php" class="<?php echo $current_page == 'drivers.php' ? 'active' : ''; ?>">Driver List</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <a href="#" class="dropbtn <?php echo in_array($current_page, ['Maintenance.php', 'Schedulebus.php']) ? 'active-dropdown' : ''; ?>">Bus</a>
                <div class="dropdown-content">
                    <a href="Maintenance.php" class="<?php echo $current_page == 'Maintenance.php' ? 'active' : ''; ?>">Maintenance</a>
                    <?php if (in_array($userRole, ['MidAdmin', 'SuperAdmin'])): ?>
                        <a href="Schedulebus.php" class="<?php echo $current_page == 'Schedulebus.php' ? 'active' : ''; ?>">Bus Schedule</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <a href="#" class="dropbtn <?php echo in_array($current_page, ['Passenger.php', 'Feedback.php']) ? 'active-dropdown' : ''; ?>">Passenger</a>
                <div class="dropdown-content">
                    <a href="Passenger.php" class="<?php echo $current_page == 'Passenger.php' ? 'active' : ''; ?>">Passenger Details</a>
                    <a href="Feedback.php" class="<?php echo $current_page == 'Feedback.php' ? 'active' : ''; ?>">Feedback</a>
                </div>
            </div>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main>
    <div class="Title">
        <h1>Schedule Bus and Drivers</h1>
    </div>
    <div class="form-container">
        <form id="scheduleForm" action="schedule.php" method="POST">
            <input type="hidden" id="resetAction" name="reset" value="0">
            <div class="form-group">
                <label for="bus_number">Bus Number:</label>
                <select id="bus_number" name="bus_number" required>
                    <option value="" disabled selected>Choose Bus</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?php echo htmlspecialchars($bus); ?>"><?php echo htmlspecialchars($bus); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="days">Days:</label>
                <div class="days-checkboxes">
                    <label><input type="checkbox" name="days[]" value="Monday"> Monday</label>
                    <label><input type="checkbox" name="days[]" value="Tuesday"> Tuesday</label>
                    <label><input type="checkbox" name="days[]" value="Wednesday"> Wednesday</label>
                    <label><input type="checkbox" name="days[]" value="Thursday"> Thursday</label>
                    <label><input type="checkbox" name="days[]" value="Friday"> Friday</label>
                    <label><input type="checkbox" name="days[]" value="Saturday"> Saturday</label>
                    <label><input type="checkbox" name="days[]" value="Sunday"> Sunday</label>
                </div>
            </div>
            <div class="form-group">
                <label for="driver1_name">Driver 1 Name (Morning):</label>
                <select id="driver1_name" name="driver1_name" required>
                    <option value="" disabled selected>Choose Driver</option>
                    <?php foreach ($availableDrivers as $driver): ?>
                        <option value="<?php echo htmlspecialchars($driver); ?>"><?php echo htmlspecialchars($driver); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="driver1_shift" value="morning">
            </div>
            <div class="form-group">
                <label for="driver2_name">Driver 2 Name (Afternoon):</label>
                <select id="driver2_name" name="driver2_name" required>
                    <option value="" disabled selected>Choose Driver</option>
                    <?php foreach ($availableDrivers as $driver): ?>
                        <option value="<?php echo htmlspecialchars($driver); ?>"><?php echo htmlspecialchars($driver); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="driver2_shift" value="afternoon">
            </div>
            <div class="form-group">
                <label for="driver3_name">Driver 3 Name (Evening):</label>
                <select id="driver3_name" name="driver3_name" required>
                    <option value="" disabled selected>Choose Driver</option>
                    <?php foreach ($availableDrivers as $driver): ?>
                        <option value="<?php echo htmlspecialchars($driver); ?>"><?php echo htmlspecialchars($driver); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="driver3_shift" value="evening">
            </div>
            <div class="form-group">
                <label for="driver4_name">Additional Driver:</label>
                <select id="driver4_name" name="driver4_name" required>
                    <option value="" disabled selected>Choose Driver</option>
                    <?php foreach ($allDrivers as $driver): ?>
                        <option value="<?php echo htmlspecialchars($driver); ?>"><?php echo htmlspecialchars($driver); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="route">Route:</label>
                <select id="route" name="route" required>
                    <option value="" disabled selected>Choose Route</option>
                    <option value="ARCA South Route">ARCA South Route</option>
                    <option value="Central Route">Central Route</option>
                    <option value="East Route">East Route</option>
                    <option value="North Route">North Route</option>
                    <option value="Weekend Route">Weekend Route</option>
                    <option value="West Route">West Route</option>
                </select>
            </div>
            <button type="submit">Update Schedule</button>
            <button type="button" onclick="confirmReset()">Reset Schedule</button>
            <button type="button" onclick="window.location.href='Schedulebus.php'">Go to Schedulebus</button>
        </form>
        <?php if ($message): ?>
            <div class="message <?php echo $messageClass; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>