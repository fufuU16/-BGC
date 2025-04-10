<?php
include 'db_connection.php';
date_default_timezone_set('Asia/Manila');

session_start();
$successMessage = '';
$errorMessage = '';

// Function to log activity
function logActivity($conn, $user_id, $username, $action) {
    $logQuery = "INSERT INTO activity_logs (user_id, username, action, timestamp) VALUES (?, ?, ?, ?)";
    if ($stmt = $conn->prepare($logQuery)) {
        $timestamp = date('Y-m-d H:i:s');
        $stmt->bind_param("isss", $user_id, $username, $action, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}

// Check if the admin ID is provided
if (!isset($_GET['id'])) {
    header("Location: admin_list.php");
    exit();
}

$adminId = $_GET['id'];

// Fetch admin details
$adminQuery = "SELECT username, email, role FROM users WHERE id = ?";
if ($stmt = $conn->prepare($adminQuery)) {
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->bind_result($username, $email, $role);
    $stmt->fetch();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($username && $email && $role) {
        // Update user details
        $updateQuery = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
        if ($updateStmt = $conn->prepare($updateQuery)) {
            $updateStmt->bind_param("sssi", $username, $email, $role, $adminId);
            if ($updateStmt->execute()) {
                $successMessage = "Admin details updated successfully!";
                // Log the update activity
                $currentUserId = $_SESSION['user_id'] ?? null;
                $currentUsername = $_SESSION['username'] ?? 'Unknown';
                if ($currentUserId) {
                    $actionDescription = "Updated admin details for user ID: $adminId";
                    logActivity($conn, $currentUserId, $currentUsername, $actionDescription);
                }
            } else {
                $errorMessage = "Error updating admin details: " . $updateStmt->error;
            }
            $updateStmt->close();
        }
    } else {
        $errorMessage = "All fields are required.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <link rel="stylesheet" href="register.css">
</head>
<body>
<header>
    <?php
    if (!isset($_SESSION['username'])) {
        header("Location: index.php");
        exit();
    }

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
        <h1>Edit Admin</h1>
    </div>
    <div class="form-container">
        <form action="edit_admin.php?id=<?php echo $adminId; ?>" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="SuperAdmin" <?php echo $role == 'SuperAdmin' ? 'selected' : ''; ?>>SuperAdmin</option>
                    <option value="MidAdmin" <?php echo $role == 'MidAdmin' ? 'selected' : ''; ?>>MidAdmin</option>
                    <option value="Admin" <?php echo $role == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <button type="submit">Update</button>
        </form>
        <?php if ($successMessage): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>