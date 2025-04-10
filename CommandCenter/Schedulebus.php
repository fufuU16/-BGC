
<?php
include 'db_connection.php'; // Ensure this file correctly sets up the database connection
date_default_timezone_set('Asia/Manila');

// Fetch all buses and their schedules from the database
$query = "
    SELECT bd.bus_id, bd.plate_number, bs.day, bs.driver1, bs.driver1_shift, bs.driver2, bs.driver2_shift, bs.driver3, bs.driver3_shift, bs.driver4, bs.route
    FROM bus_details bd
    LEFT JOIN bus_schedule bs ON bd.bus_id = bs.bus_id
";
$result = $conn->query($query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Schedule</title>
    <link rel="stylesheet" href="Schedulebus.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
<header>
    <?php
    session_start();
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
        <h1>Bus Schedule</h1>
    </div>
    
    <!-- Controls for Search, Add Schedule, and Export Report -->
    <div class="controls">
        <input type="text" id="searchInput" placeholder="Search by Bus ID, Driver, or Route..." onkeyup="searchBusSchedule()">
        <button onclick="window.location.href='schedule.php'">Add Schedule</button>
        <button onclick="window.location.href='addbus.php'">Add Bus</button>
        <button onclick="exportReport()">Export Report</button>
    </div>
    
    <div class="schedule-table">
        <table id="scheduleTable">
            <thead>
                <tr>
                    <th>Bus Number</th>
                    <th>Day</th>
                    <th>Driver 1</th>
                    <th>Driver 1 Shift</th>
                    <th>Driver 2</th>
                    <th>Driver 2 Shift</th>
                    <th>Driver 3</th>
                    <th>Driver 3 Shift</th>
                    <th>Driver 4</th>
                    <th>Route</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr onclick="goToBusDetails('<?php echo $row['bus_id']; ?>')">
                        <td><?php echo htmlspecialchars($row['bus_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['day'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver1'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver1_shift'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver2'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver2_shift'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver3'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver3_shift'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['driver4'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['route'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
    function goToBusDetails(busId) {
        window.location.href = `Busdetails.php?bus_id=${busId}`;
    }

    function searchBusSchedule() {
        let input = document.getElementById("searchInput").value.toLowerCase();
        let table = document.getElementById("scheduleTable");
        let rows = table.getElementsByTagName("tr");

        for (let i = 1; i < rows.length; i++) {
            let row = rows[i];
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(input) ? "" : "none";
        }
    }

    function exportReport() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Define a template for the PDF
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();

    // Add a header
    doc.setFontSize(18);
    doc.text("Bus Schedule Report", pageWidth / 2, 20, { align: "center" });

    // Add timestamp and username
    const username = "<?php echo htmlspecialchars($_SESSION['username']); ?>";
    const timestamp = new Date().toLocaleString();
    doc.setFontSize(12);
    doc.text(`Exported by: ${username}`, 14, 30);
    doc.text(`Timestamp: ${timestamp}`, 14, 36);

    // Add a footer
    doc.setFontSize(10);
    doc.text("Confidential", pageWidth / 2, pageHeight - 10, { align: "center" });

    // Add table headers
    let headers = [];
    let table = document.getElementById("scheduleTable");
    let rows = table.getElementsByTagName("tr");
    let headerCells = rows[0].getElementsByTagName("th");
    for (let headerCell of headerCells) {
        headers.push(headerCell.innerText);
    }

    // Add table data
    let data = [];
    for (let i = 1; i < rows.length; i++) {
        let cells = rows[i].getElementsByTagName("td");
        if (cells.length > 0) {
            let rowData = [];
            for (let cell of cells) {
                rowData.push(cell.innerText);
            }
            data.push(rowData);
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

    // Save the PDF
    doc.save("Bus_Schedule_Report.pdf");
}
</script>
</body>
</html>
