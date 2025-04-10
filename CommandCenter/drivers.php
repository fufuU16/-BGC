<?php
session_start();
include 'db_connection.php'; // Ensure this file correctly sets up the database connection
date_default_timezone_set('Asia/Manila');

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

// Handle archive or restore request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['archive_driver_id'])) {
        $driverId = intval($_POST['archive_driver_id']);
        $archiveQuery = "UPDATE drivers SET archived = 1 WHERE driver_id = ?";
        $actionDescription = "Archived driver with ID: $driverId";
    } elseif (isset($_POST['restore_driver_id'])) {
        $driverId = intval($_POST['restore_driver_id']);
        $archiveQuery = "UPDATE drivers SET archived = 0 WHERE driver_id = ?";
        $actionDescription = "Restored driver with ID: $driverId";
    }

    if (isset($archiveQuery)) {
        $stmt = $conn->prepare($archiveQuery);
        $stmt->bind_param("i", $driverId);
        if ($stmt->execute()) {
            $_SESSION['message'] = isset($_POST['archive_driver_id']) ? "Driver archived successfully." : "Driver restored successfully.";

            // Log the activity
            $currentUsername = $_SESSION['username'] ?? 'Unknown';
            $currentUserId = $_SESSION['user_id'] ?? null; // Ensure user_id is stored in session
            if ($currentUserId) {
                logActivity($conn, $currentUserId, $currentUsername, $actionDescription);
            }
        } else {
            $_SESSION['message'] = "Error updating driver status: " . $conn->error;
        }
        $stmt->close();
        header("Location: drivers.php");
        exit();
    }
}

// Query to fetch driver data based on archive state
$viewArchived = isset($_GET['view_archived']) && $_GET['view_archived'] == '1';
$driversQuery = "SELECT driver_id, name, email, rfid_tag FROM drivers WHERE archived = " . ($viewArchived ? "1" : "0") . " ORDER BY name ASC";
$driversResult = $conn->query($driversQuery);

// Check for query errors
if (!$driversResult) {
    die("SQL error: " . $conn->error);
}

// Fetch data into an array
$driversData = [];
if ($driversResult->num_rows > 0) {
    while ($row = $driversResult->fetch_assoc()) {
        $driversData[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver List</title>
    <link rel="stylesheet" href="drivers.css">
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
        <h1>Driver List</h1>
    </div>
    <?php if (isset($_SESSION['message'])): ?>
    <p class="message">
        <?php
        echo htmlspecialchars($_SESSION['message']);
        unset($_SESSION['message']);
        ?>
    </p>
<?php endif; ?>
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by Name, Email, or RFID Tag...">
        <button onclick="searchDrivers()">Search</button>
        <button id="addDriverButton" onclick="addNewDriver()">Add New Driver</button>
        <button id="exportButton" onclick="exportDriverList()">Export Driver List</button>
        <button onclick="window.location.href='drivers.php?view_archived=<?php echo $viewArchived ? '0' : '1'; ?>'">
            <?php echo $viewArchived ? 'View Active Drivers' : 'View Archived Drivers'; ?>
        </button>
    </div>
    <div class="drivers-table">
        <table id="driversTable">
            <thead>
                <tr>
                    <th>Driver ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>RFID Tag</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($driversData)): ?>
                <?php foreach ($driversData as $driver): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($driver['driver_id']); ?></td>
                        <td><?php echo htmlspecialchars($driver['name']); ?></td>
                        <td><?php echo htmlspecialchars($driver['email']); ?></td>
                        <td><?php echo htmlspecialchars($driver['rfid_tag']); ?></td>
                        <td>
                            <?php if (!$viewArchived): ?>
                                <button onclick="confirmArchive(<?php echo htmlspecialchars($driver['driver_id']); ?>)">Archive</button>
                                <button onclick="editDriver(<?php echo htmlspecialchars($driver['driver_id']); ?>)">Edit</button>
                            <?php else: ?>
                                <button onclick="confirmRestore(<?php echo htmlspecialchars($driver['driver_id']); ?>)">Restore</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No drivers found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="drivers.js"></script>
<script>
    function confirmArchive(driverId) {
        if (confirm("Are you sure you want to archive this driver?")) {
            // Create a form to submit the archive request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'archive_driver_id';
            input.value = driverId;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function confirmRestore(driverId) {
        if (confirm("Are you sure you want to restore this driver?")) {
            // Create a form to submit the restore request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'restore_driver_id';
            input.value = driverId;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function editDriver(driverId) {
        // Redirect to an edit page or open a modal for editing
        window.location.href = `editDriver.php?driver_id=${driverId}`;
    }

    async function exportDriverList() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Define a template for the PDF
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // Add a header
        doc.setFontSize(18);
        doc.text("Driver List Report", pageWidth / 2, 20, { align: "center" });

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
        let table = document.getElementById("driversTable");
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
        doc.save(`Driver_List_Report_${formattedDate}.pdf`);
    }
</script>
</body>
</html>