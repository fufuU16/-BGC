<?php
// register.php
include 'db_connection.php';
date_default_timezone_set('Asia/Manila');

session_start();
$successMessage = '';
$errorMessage = '';

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Function to log activity
function logActivity($conn, $user_id, $username, $action) {
    $logQuery = "INSERT INTO activity_logs (user_id, username, action) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($logQuery)) {
        $stmt->bind_param("iss", $user_id, $username, $action);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';

    // Password validation: minimum 12 characters, at least one number, one uppercase letter, and one special character
    $passwordPattern = '/^(?=.*\d)(?=.*[A-Z])(?=.*\W).{12,}$/';

    if ($username && $password && $email && $role) {
        if (!preg_match($passwordPattern, $password)) {
            $errorMessage = "Password must be at least 12 characters long and include at least one number, one uppercase letter, and one special character.";
        } else {
            // Check if the username already exists
            $checkQuery = "SELECT id FROM users WHERE username = ?";
            if ($checkStmt = $conn->prepare($checkQuery)) {
                $checkStmt->bind_param("s", $username);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $errorMessage = "Username already exists. Please choose a different username.";
                } else {
                    // Hash the password for security
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user details
                    $insertQuery = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)";
                    if ($insertStmt = $conn->prepare($insertQuery)) {
                        $insertStmt->bind_param("ssss", $username, $hashedPassword, $email, $role);
                        if ($insertStmt->execute()) {
                            $successMessage = "User registered successfully!";
                            // Log the registration activity using the current session user ID
                            $currentUserId = $_SESSION['user_id'] ?? null; // Ensure user_id is stored in session
                            $currentUsername = $_SESSION['username'] ?? 'Unknown';
                            if ($currentUserId) {
                                $actionDescription = "Registered a new user: $username";
                                logActivity($conn, $currentUserId, $currentUsername, $actionDescription);
                            } else {
                                $errorMessage = "Error: User ID not found in session.";
                            }

                            // Send confirmation email
                            // Generate a secure token for password reset
// Send confirmation email
$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->SMTPDebug = 0; // Set to 2 for debugging
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'bgcbus2025capstone@gmail.com'; // Your email address
    $mail->Password = 'qxmauupgbiczqaci'; // Your email password or app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Recipients
    $mail->setFrom('bgcbus2025capstone@gmail.com', 'BGC Bus');
    $mail->addAddress($email, $username);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Welcome to BGC Bus';
    $mail->Body    = "<p>Dear $username,</p>
                      <p>Welcome to BGC Bus! Your account has been successfully created.</p>
                      <p>Your role is: <strong>$role</strong></p>
                      <p>Your username is: <strong>$username</strong></p>
                      <p>Your password is: <strong>$password</strong></p>
                      <p>Please keep this information secure.</p>";
    $mail->AltBody = "Dear $username,\n\nWelcome to BGC Bus! Your account has been successfully created.\nYour role is: $role\nYour username is: $username\nYour password is: $password\nPlease keep this information secure.";

    // Send the email
    $mail->send();
    $successMessage .= " Confirmation email sent.";
} catch (Exception $e) {
    $errorMessage .= " Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
                        } else {
                            $errorMessage = "Error registering user: " . $insertStmt->error;
                        }
                        $insertStmt->close();
                    }
                }
                $checkStmt->close();
            }
        }
    } else {
        $errorMessage = "All fields are required.";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <link rel="stylesheet" href="register.css">
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
        <h1>User Registration</h1>
    </div>
    <div class="form-container">
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
           <div class="form-group">
    <label for="password">Password:</label>
    <div class="password-container">
        <input type="password" id="password" name="password" required>
        <button type="button" class="toggle-password-btn" onclick="togglePassword('password', this)">Show</button>
    </div>
</div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="SuperAdmin">SuperAdmin</option>
                    <option value="MidAdmin">MidAdmin</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            <button type="submit">Register</button>
        </form>
        <?php if ($successMessage): ?>
            <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="message error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
    </div>
    
<script>
    function togglePassword(inputId, button) {
        const passwordInput = document.getElementById(inputId);
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            button.textContent = 'Hide';
        } else {
            passwordInput.type = 'password';
            button.textContent = 'Show';
        }
    }
</script>

<style>
   .password-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    .password-container input[type="password"],
    .password-container input[type="text"] {
        width: calc(100% - 60px); /* Adjust width to make space for the button */
        padding-right: 60px; /* Add padding to make space for the button */
    }
    .toggle-password-btn {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        user-select: none;
        background: none;
        border: none;
        font-size: 14px;
        color: #007bff;
        width: 50px; /* Set a specific width for the button */
    }
</style>



</main>
</body>
</html>