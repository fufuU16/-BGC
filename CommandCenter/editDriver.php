<?php
include 'db_connection.php'; // Ensure this file correctly sets up the database connection
date_default_timezone_set('Asia/Manila');

session_start();
include 'role_check.php';

// Check if the user has the required role
checkUserRole(['SuperAdmin', 'MidAdmin']);

$message = '';

// Function to log activity
function logActivity($conn, $user_id, $username, $action) {
    $logQuery = "INSERT INTO activity_logs (user_id, username, action, timestamp) VALUES (?, ?, ?, ?)";
    if ($stmt = $conn->prepare($logQuery)) {
        $timestamp = date('Y-m-d H:i:s'); // Current timestamp in the desired format
        $stmt->bind_param("isss", $user_id, $username, $action, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch driver details
$driverId = intval($_GET['driver_id']);
$driverQuery = "SELECT name, email, rfid_tag, image FROM drivers WHERE driver_id = ?";
$stmt = $conn->prepare($driverQuery);
$stmt->bind_param("i", $driverId);
$stmt->execute();
$stmt->bind_result($name, $email, $rfid_tag, $image);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $rfid_tag = trim($_POST['rfid_tag']);
    $image = $_FILES['driver_image']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($image);

    // Check if the uploads directory exists
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true); // Create the directory if it doesn't exist
    }

    // Server-side validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif (!preg_match("/^[A-Za-z\s]+$/", $name)) {
        $message = "Name must not contain numbers or special characters.";
    } elseif ($image && !move_uploaded_file($_FILES['driver_image']['tmp_name'], $target_file)) {
        $message = "Failed to upload image. Please check directory permissions.";
    } else {
        // Prepare and execute the SQL statement
        $updateQuery = "UPDATE drivers SET name = ?, email = ?, rfid_tag = ?, image = ? WHERE driver_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssi", $name, $email, $rfid_tag, $target_file, $driverId);

        if ($stmt->execute()) {
            $message = "Driver updated successfully!";
            // Log the driver update activity
            $currentUsername = $_SESSION['username'] ?? 'Unknown';
            $currentUserId = $_SESSION['user_id'] ?? null; // Ensure user_id is stored in session
            if ($currentUserId) {
                logActivity($conn, $currentUserId, $currentUsername, "Updated driver: $name");
            } else {
                $message = "Error: User ID not found in session.";
            }
        } else {
            $message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Driver</title>
    <link rel="stylesheet" href="addDriver.css">
</head>
<body>
<header>
    <?php
    if (!isset($_SESSION['username'])) {
        // Redirect to login page if not logged in
        header("Location:index.php");
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
        <h1>Edit Driver</h1>
    </div>
    <div class="card-container">
        <div class="card">
            <form action="editDriver.php?driver_id=<?php echo $driverId; ?>" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <div class="form-content">
                    <div class="form-left">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        
                        <label for="rfid_tag">RFID Tag:</label>
                        <input type="text" id="rfid_tag" name="rfid_tag" value="<?php echo htmlspecialchars($rfid_tag); ?>" required>
                    </div>
                    <div class="form-right">
                        <label for="driver_image">Driver Image:</label>
                        <input type="file" id="driver_image" name="driver_image" accept="image/*" onchange="previewImage(event)"><br><br>
                        <div class="image-container">
                            <img id="image_preview" src="<?php echo htmlspecialchars($image); ?>" alt="Image Preview" style="display: block;">
                        </div>
                        <button type="submit">Update Driver</button>
                        <button type="button" class="back-to-drivers" onclick="goToDrivers()">Back to Driver List</button>                  </div>
                </div>
            </form>
            <br> 
        </div>
    </div>
    <?php if ($message): ?>
    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
        <p><?php echo htmlspecialchars($message); ?></p>
    </div>
    <?php endif; ?>
</main>
<script>
function previewImage(event) {
    const imagePreview = document.getElementById('image_preview');
    imagePreview.src = URL.createObjectURL(event.target.files[0]);
    imagePreview.style.display = 'block';
}

function validateForm() {
    const name = document.getElementById('name').value;
    const namePattern = /^[A-Za-z\s]+$/; // Only letters and spaces

    if (!namePattern.test(name)) {
        alert('Name must not contain numbers or special characters.');
        return false;
    }

    return true;
}

function goToDrivers() {
    window.location.href = 'drivers.php';
}
</script>
</body>
</html>