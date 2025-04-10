<?php
include 'db_connection.php'; // Ensure this file correctly sets up the database connection

session_start();
include 'role_check.php';

// Set the default timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Check if the user has the required role
checkUserRole(['SuperAdmin']);

// Function to log activity
function logActivity($conn, $user_id, $username, $action) {
    $current_time = date('Y-m-d H:i:s'); // Get the current time in PHT
    $logQuery = "INSERT INTO activity_logs (user_id, username, action, timestamp) VALUES (?, ?, ?, ?)";
    if ($stmt = $conn->prepare($logQuery)) {
        $stmt->bind_param("isss", $user_id, $username, $action, $current_time);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle form submission for archiving or restoring
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['archive_id'])) {
        $userId = $_POST['archive_id'];
        $archiveQuery = "UPDATE users SET archived = 1 WHERE id = ?";
        $actionDescription = "Archived admin with ID: $userId";
    } elseif (isset($_POST['restore_id'])) {
        $userId = $_POST['restore_id'];
        $archiveQuery = "UPDATE users SET archived = 0 WHERE id = ?";
        $actionDescription = "Restored admin with ID: $userId";
    }

    if (isset($archiveQuery)) {
        $stmt = $conn->prepare($archiveQuery);
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            $archiveMessage = isset($_POST['archive_id']) ? "Admin archived successfully." : "Admin restored successfully.";

            // Log the activity
            $currentUsername = $_SESSION['username'] ?? 'Unknown';
            $currentUserId = $_SESSION['user_id'] ?? null; // Ensure user_id is stored in session
            if ($currentUserId) {
                logActivity($conn, $currentUserId, $currentUsername, $actionDescription);
            }
        } else {
            $archiveMessage = "Failed to update admin status.";
        }

        $stmt->close();
    }
}

// Check if the toggle is set to view archived admins
$viewArchived = isset($_GET['view_archived']) && $_GET['view_archived'] == '1';

// Query to fetch admins based on toggle state
$adminQuery = "
    SELECT id, username, role, email
    FROM users
    WHERE role IN ('Admin', 'SuperAdmin', 'MidAdmin') AND archived = " . ($viewArchived ? "1" : "0") . "
    ORDER BY username ASC
";

$adminResult = $conn->query($adminQuery);

// Check for query errors
if (!$adminResult) {
    die("SQL error: " . $conn->error);
}

// Fetch data into an array
$adminData = [];
if ($adminResult->num_rows > 0) {
    while ($row = $adminResult->fetch_assoc()) {
        $adminData[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin List</title>
    <link rel="stylesheet" href="admin_list.css">
    <!-- Include jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
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
        <h1>Admin List</h1>
    </div>
    
    <!-- Display archive message if set -->
    <?php if (isset($archiveMessage)): ?>
        <p><?php echo $archiveMessage; ?></p>
    <?php endif; ?>
    
    <!-- Search bar -->
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by Username, Role, or Email..." onkeyup="searchAdmins()">
        <button onclick="window.location.href='register.php'">Add Admin</button>
        <button onclick="exportAdminReport()">Export Admin Report</button>
        <button onclick="window.location.href='admin_list.php?view_archived=<?php echo $viewArchived ? '0' : '1'; ?>'">
            <?php echo $viewArchived ? 'View Active Admins' : 'View Archived Admins'; ?>
        </button>
    </div>
    
    <div class="logs-table">
        <table id="adminTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($adminData)): ?>
                <?php foreach ($adminData as $admin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($admin['id']); ?></td>
                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td><?php echo htmlspecialchars($admin['role']); ?></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td>
                            <?php if (!$viewArchived): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to archive this admin?');" style="display:inline;">
                                    <input type="hidden" name="archive_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit">Archive</button>
                                </form>
                                <form method="GET" action="edit_admin.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" class="green-button">Edit</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to restore this admin?');" style="display:inline;">
                                    <input type="hidden" name="restore_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit">Restore</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No admins found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
    async function exportAdminReport() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Define a template for the PDF
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // Add a header
        doc.setFontSize(18);
        doc.text("Admin List Report", pageWidth / 2, 20, { align: "center" });

        // Add timestamp and username
        const username = "<?php echo htmlspecialchars($_SESSION['username']); ?>";
        const timestamp = new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' });
        doc.setFontSize(12);
        doc.text(`Exported by: ${username}`, 14, 30);
        doc.text(`Timestamp: ${timestamp}`, 14, 36);

        // Add a footer
        doc.setFontSize(10);
        doc.text("Confidential", pageWidth / 2, pageHeight - 10, { align: "center" });

        // Add table headers
        let headers = [];
        let table = document.getElementById("adminTable");
        let headerCells = table.getElementsByTagName("th");

        for (let headerCell of headerCells) {
            headers.push(headerCell.innerText);
        }

        // Add only visible table data
        let data = [];
        let rows = table.getElementsByTagName("tr");

        for (let i = 1; i < rows.length; i++) {
            let row = rows[i];
            if (row.style.display !== 'none') { // Check if the row is visible
                let cells = row.getElementsByTagName("td");
                if (cells.length > 0) {
                    let rowData = [];
                    for (let cell of cells) {
                        rowData.push(cell.innerText);
                    }
                    data.push(rowData);
                }
            }
        }

        // Add table to PDF
        doc.autoTable({
            startY: 40,
            head: [headers],
            body: data,
            theme: 'grid',
            styles: { fontSize: 10 },
            headStyles: { fillColor: [100, 100, 100] },
            alternateRowStyles: { fillColor: [240, 240, 240] },
        });

        // Add a watermark image with reduced opacity
        const imgData = "../image/bgcwatermark.PNG"; // Replace with actual Base64 encoded image data
        const imgWidth = pageWidth * 0.8; // Adjust the size as needed
        const imgHeight = pageHeight * 0.8; // Adjust the size as needed
        const imgX = (pageWidth - imgWidth) / 2;
        const imgY = (pageHeight - imgHeight) / 2;
        doc.addImage(imgData, 'PNG', imgX, imgY, imgWidth, imgHeight, '', 'FAST');

        // Format the current date for the filename
        const date = new Date();
        const formattedDate = date.toISOString().slice(0, 10); // YYYY-MM-DD format

        // Save the PDF with the export date in the filename
        doc.save(`Admin_List_Report_${formattedDate}.pdf`);
    }
</script>
</body>
</html>