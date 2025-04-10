<?php
date_default_timezone_set('Asia/Manila');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
if (!isset($_SESSION['username'])) {
    // Redirect to login page if not logged in
    header("Location: Login.php");
    exit();
}

include 'db_connection.php';

// Check if the request is for fetching maintenance data
if (isset($_GET['fetch_maintenance'])) {
    // Query to fetch maintenance data with all possible maintenance types
    $maintenanceQuery = "
        SELECT bd.bus_id, bd.plate_number, bd.next_scheduled_maintenance, bd.current_status AS status, 
               md.TypeofMaintenance, md.odometer_at_maintenance, bd.TotalOdometer
        FROM bus_details bd
        LEFT JOIN maintenance_data md ON bd.bus_id = md.bus_id
        ORDER BY bd.next_scheduled_maintenance DESC
    ";

    $maintenanceResult = $conn->query($maintenanceQuery);

    // Check for query errors
    if (!$maintenanceResult) {
        die("SQL error: " . $conn->error);
    }

    // Fetch data into an array
    $maintenanceData = [];
    if ($maintenanceResult->num_rows > 0) {
        while ($row = $maintenanceResult->fetch_assoc()) {
            $maintenanceData[] = $row;
        }
    }

    // Close the database connection
    $conn->close();

    // Output JSON
    header('Content-Type: application/json');
    echo json_encode($maintenanceData);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance</title>
    <link rel="stylesheet" href="Maintenance.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
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
        <h1>Maintenance</h1>
    </div>
    
    <!-- Search bar and export button -->
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by Bus ID, Plate Number, or Status..." onkeyup="searchLogs()">
        <button id="exportButton" onclick="exportReport()">Export Report</button>     
    </div>
    
    <div class="logs-table">
        <table id="busTable">
            <thead>
                <tr>
                    <th>Bus Number</th>
                    <th>Next Scheduled Maintenance</th>
                    <th>Last Change/Check</th>
                    <th>Maintenance Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <!-- Table body will be populated by JavaScript -->
            </tbody>
        </table>
    </div>
</main>

<script>
   document.addEventListener('DOMContentLoaded', function () {
    fetchMaintenanceData();
    setInterval(fetchMaintenanceData, 5000); // Poll every 5 seconds
});

function fetchMaintenanceData() {
    // Fetch maintenance data from the server using Fetch API
    fetch('Maintenance.php?fetch_maintenance=true') // Fetch data directly from this file
        .then(response => response.json())
        .then(data => {
            const tableBody = document.querySelector('#busTable tbody');
            tableBody.innerHTML = ''; // Clear existing rows

            data.forEach(bus => {
                const maintenanceIntervals = {
                    'Oil Change': 10000,
                    'Tire Replacement': 40000,
                    'Brake Replacement': 30000
                };

                let maintenanceNeeded = false;
                if (maintenanceIntervals[bus.TypeofMaintenance]) {
                    const nextMaintenanceOdometer = bus.odometer_at_maintenance + maintenanceIntervals[bus.TypeofMaintenance];
                    maintenanceNeeded = bus.TotalOdometer >= nextMaintenanceOdometer;
                }

                const row = document.createElement('tr');
                row.setAttribute('data-bus-id', bus.bus_id);
                row.innerHTML = `
                    <td>${bus.bus_id}</td>
                    <td>${bus.next_scheduled_maintenance}</td>
                    <td>${bus.odometer_at_maintenance}</td>
                    <td>${bus.TypeofMaintenance}</td>
                    <td>${maintenanceNeeded ? 'Required' : 'Done'}</td>
                `;
                tableBody.appendChild(row);

                // Add click event listener to each row
                row.addEventListener('click', function() {
                    const busId = this.getAttribute('data-bus-id');
                    if (busId && busId.trim() !== "") {
                        window.location.href = `Busdetails.php?bus_id=${encodeURIComponent(busId)}`;
                    }
                });
            });
        })
        .catch(error => console.error('Error fetching maintenance data:', error));
}

    function searchLogs() {
        let input = document.getElementById("searchInput").value.toLowerCase();
        let table = document.getElementById("busTable");
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
        doc.text("Maintenance Report", pageWidth / 2, 20, { align: "center" });

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
        let table = document.getElementById("busTable");
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
        doc.save(`Maintenance_Report_${formattedDate}.pdf`);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('#busTable tbody tr');

        rows.forEach(row => {
            row.addEventListener('click', function() {
                const busId = this.getAttribute('data-bus-id');
                if (busId && busId.trim() !== "") {
                    window.location.href = `Busdetails.php?bus_id=${encodeURIComponent(busId)}`;
                }
            });
        });
    });
</script>
</body>
</html>