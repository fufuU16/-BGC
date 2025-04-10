<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
include 'db_connection.php';

session_start();
if (!isset($_SESSION['username'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

// Set the correct timezone
date_default_timezone_set('Asia/Manila');

// Function to log activity
function logActivity($conn, $user_id, $username, $action) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $timestamp = date('Y-m-d H:i:s'); // Current timestamp in the desired format

    $logQuery = "INSERT INTO activity_logs (user_id, username, action, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($logQuery)) {
        $stmt->bind_param("isssss", $user_id, $username, $action, $ip_address, $user_agent, $timestamp);
        $stmt->execute();
        $stmt->close();
    }
}

$feedback_id = $_GET['feedback_id'] ?? $_POST['feedback_id'] ?? null;
$replyMessage = '';
$feedbackDetails = null;

// Fetch the original feedback message
if ($feedback_id) {
    $stmt = $conn->prepare("SELECT name, email, message, submitted_at FROM feedback WHERE id = ?");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $feedbackDetails = $result->fetch_assoc();
    } else {
        $replyMessage = "No feedback found for the given ID.";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reply = trim($_POST['reply']);
    $feedback_id = intval($_POST['feedback_id']);

    // Insert reply into the database
    $stmt = $conn->prepare("INSERT INTO feedback_replies (feedback_id, reply, replied_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $feedback_id, $reply);

    if ($stmt->execute()) {
        $replyMessage = "Reply submitted successfully!";

        // Update the feedback status to replied
        $updateStmt = $conn->prepare("UPDATE feedback SET replied = 1 WHERE id = ?");
        $updateStmt->bind_param("i", $feedback_id);
        $updateStmt->execute();
        $updateStmt->close();

        // Log the reply submission activity
        $currentUsername = $_SESSION['username'] ?? 'Unknown';
        $currentUserId = $_SESSION['user_id'] ?? null; // Ensure user_id is stored in session
        if ($currentUserId) {
            logActivity($conn, $currentUserId, $currentUsername, "Replied to feedback ID: $feedback_id");
        } else {
            $replyMessage .= " Error: User ID not found in session.";
        }

        // Check if email is valid before sending
        if (!empty($feedbackDetails['email']) && filter_var($feedbackDetails['email'], FILTER_VALIDATE_EMAIL)) {
            // Send email notification to the user
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

                $mail->setFrom('bgcbus2025capstone@gmail.com', 'BGC Feedback System');
                $mail->addAddress($feedbackDetails['email']); // Send to the user's email

                $mail->isHTML(true);
                $mail->Subject = 'Reply to Your Feedback';
                $mail->Body = "
                    <p>Dear " . htmlspecialchars($feedbackDetails['name'] ?: 'User') . ",</p>
                    <p>Thank you for your feedback. Here is our reply:</p>
                    <p><strong>Your Feedback:</strong> " . nl2br(htmlspecialchars($feedbackDetails['message'])) . "</p>
                    <p><strong>Our Reply:</strong> " . nl2br(htmlspecialchars($reply)) . "</p>
                    <p>Best regards,</p>
                    <p>BGC Feedback Team</p>
                ";

                $mail->send();
                $replyMessage .= " Notification email sent to user.";
            } catch (Exception $e) {
                $replyMessage .= " Failed to send email notification. Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            $replyMessage .= " Invalid or missing email address.";
        }
    } else {
        $replyMessage = "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply to Feedback</title>
    <link rel="stylesheet" href="feedback.css">
    <style>
        .reply-container {
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-width: 800px;
            margin: 20px auto;
            overflow-wrap: break-word; /* Ensures long words break to fit the container */
        }
        .reply-message {
            color: #28a745;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            color: #333;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            resize: vertical;
        }
        button {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #575757;
        }
        .feedback-details {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
            overflow-wrap: break-word; /* Ensures long words break to fit the container */
        }
    </style>
</head>
<body>
<header>
    <nav>
        <a href="Dashboard.php">Dashboard</a>
        <a href="Feedback.php">Feedback</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <div class="reply-container">
        <h1>Reply to Feedback</h1>
        <?php if ($replyMessage): ?>
            <div class="reply-message"><?php echo htmlspecialchars($replyMessage); ?></div>
        <?php endif; ?>
        
        <?php if ($feedbackDetails): ?>
            <div class="feedback-details">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($feedbackDetails['name'] ?: 'Anonymous'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($feedbackDetails['email'] ?: ''); ?></p>
                <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($feedbackDetails['message'])); ?></p>
                <small>Submitted on: <?php echo htmlspecialchars($feedbackDetails['submitted_at']); ?></small>
            </div>
        <?php else: ?>
            <p>No feedback details available.</p>
        <?php endif; ?>

        <form action="Reply.php" method="post">
            <input type="hidden" name="feedback_id" value="<?php echo htmlspecialchars($feedback_id); ?>">
            <div class="form-group">
                <label for="reply">Your Reply:</label>
                <textarea id="reply" name="reply" rows="5" required></textarea>
            </div>
            <button type="submit">Submit Reply</button>
        </form>
    </div>
</main>
</body>
</html>