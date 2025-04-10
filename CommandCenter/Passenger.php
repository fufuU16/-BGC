<?php
// Include the database connection
include 'db_connection.php'; // Ensure this file exists and is correct

session_start();
if (!isset($_SESSION['username'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit();
}

// Initialize search term
$searchTerm = isset($_GET['route_search']) ? $_GET['route_search'] : '';

// Fetch bus stop details
$busStopQuery = "
    SELECT bus_no, passenger_count, eta, bus_number, route, current_stop, next_stop, end_point, timestamp
    FROM bus_stop_details
    ORDER BY route, bus_no
";
$busStopResult = $conn->query($busStopQuery);
if (!$busStopResult) {
    die("SQL error: " . $conn->error);
}
$busStopData = $busStopResult->fetch_all(MYSQLI_ASSOC);

// Fetch route data
$routeQuery = "
    SELECT route, bus_id, current_passengers, 
           SUM(current_passengers) OVER (PARTITION BY route) as overall_count
    FROM bus_passenger_data
    WHERE date = CURDATE()
";
if ($searchTerm) {
    $routeQuery .= " AND route LIKE '%" . $conn->real_escape_string($searchTerm) . "%'";
}
$routeQuery .= " ORDER BY route, bus_id";
$routeResult = $conn->query($routeQuery);
if (!$routeResult) {
    die("SQL error: " . $conn->error);
}
$routeData = [];
while ($row = $routeResult->fetch_assoc()) {
    $routeData[$row['route']][] = $row;
}

// Fetch historical data for the past 7 days
$historyQuery = "
    SELECT date, 
           route, 
           SUM(passengers) as total_passengers
    FROM passenger_data 
    WHERE date >= CURDATE() - INTERVAL 7 DAY
    GROUP BY date, route
    ORDER BY date ASC
";
$historyResult = $conn->query($historyQuery);
if (!$historyResult) {
    die("SQL error: " . $conn->error);
}
$historyData = $historyResult->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Details</title>
    <link rel="stylesheet" href="Passenger.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Include jsPDF library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <script src="timeRangeSelector.js"></script> <!-- Ensure this path is correct -->
</head>
<body>
<header>
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
        <h1>Passenger Details</h1>
    </div>
    <div id="MapContainer">
        <h2>Bus Location Tracker</h2>
        <div id="map" style="height: 400px; width: 100%;"></div>
    </div>
    <div id="BusStopDetails">
        <h2>Bus Stop Details</h2>
        <form method="GET" action="Passenger.php">
            <input type="text" name="route_search" placeholder="Search by route" value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button type="submit">Search</button>
            <button type="button" class="button-link" onclick="window.location.href='save_image.php'">Passenger Count View</button>
        </form>
        <table class="busStopDetails">
            <thead>
                <tr>
                    <th>Bus No.</th>
                    <th>Passenger Count</th>
                    <th>ETA</th>
                    <th>Bus Number</th>
                    <th>Route</th>
                    <th>Current Stop</th>
                    <th>Next Stop</th>
                    <th>End Point</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($busStopData)): ?>
                    <?php foreach ($busStopData as $bus): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bus['bus_no']); ?></td>
                            <td><?php echo htmlspecialchars($bus['passenger_count']); ?></td>
                            <td><?php echo htmlspecialchars($bus['eta']); ?></td>
                            <td><?php echo htmlspecialchars($bus['bus_number']); ?></td>
                            <td><?php echo htmlspecialchars($bus['route']); ?></td>
                            <td><?php echo htmlspecialchars($bus['current_stop']); ?></td>
                            <td><?php echo htmlspecialchars($bus['next_stop']); ?></td>
                            <td><?php echo htmlspecialchars($bus['end_point']); ?></td>
                            <td><?php echo htmlspecialchars($bus['timestamp']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No bus stop details available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="PassengerHistory">
        <h2>Passenger Counts History</h2>
        <div class="controls">
            <select id="timeRangeSelector" class="button-link">
                <option value="7days">Past 7 Days</option>
                <option value="3months">Past 3 Months</option>
                <option value="6months">Past 6 Months</option>
                <option value="year">Past Year</option>
            </select>
            <button id="exportButton" class="button-link">Export to PDF</button>
        </div>
        <table class="historyTable">
            <thead>
                <tr id="historyTableHeader">
                    <th>Date</th>
                    <th>ARCA South Route</th>
                    <th>Central Route</th>
                    <th>East Route</th>
                    <th>North Route</th>
                    <th>Weekend Route</th>
                    <th>West Route</th>
                    <th>Overall</th>
                </tr>
            </thead>
            <tbody id="historyTableBody">
                <tr>
                    <td colspan="8">No data available.</td>
                </tr>
            </tbody>
        </table>
    </div>
    <script>
   

    function exportHistoryDataToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Define a template for the PDF
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // Add a header
        doc.setFontSize(18);
        doc.text("Passenger Counts History", pageWidth / 2, 20, { align: "center" });

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
        let table = document.querySelector(".historyTable");
        let headerCells = table.getElementsByTagName("th");
        for (let headerCell of headerCells) {
            headers.push(headerCell.innerText);
        }

        // Add only visible table data
        let data = [];
        let rows = historyTableBody.getElementsByTagName("tr");
        for (let i = 0; i < rows.length; i++) {
            let row = rows[i];
            let cells = row.getElementsByTagName("td");
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


        // Format the current date for the filename
        const date = new Date();
        const formattedDate = date.toISOString().slice(0, 10); // YYYY-MM-DD format

        // Save the PDF with the export date in the filename
        doc.save(`Passenger_Counts_History_${formattedDate}.pdf`);
    }

    // Initialize the map
    var map = L.map('map').setView([14.5531, 121.0180], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Image Icons for Bus and Stops
    const redIcon = L.icon({
        iconUrl: '../image/busmarkk.png', 
        iconSize: [30, 30]
    });

    const blueIcon = L.icon({
        iconUrl: '../image/busiconn.PNG',
        iconSize: [30, 30]
    });

    const busStops = [
        { name: "Market! Market!", lat: 14.548847458449865, lng: 121.05636632081664 },
        { name: "Nutriasia", lat: 14.551637863137922, lng: 121.05127157262918 },
        { name: "The Fort", lat: 14.54930152073757, lng: 121.04741660735782 },
        { name: "One/NEO", lat: 14.550270155719662, lng: 121.04542703040072 },
        { name: "Bonifacio Stopover", lat: 14.554152587939072, lng: 121.04603459326133 },
        { name: "Crescent Park West", lat: 14.554424695275497, lng: 121.04404419730557 },
        { name: "The Globe Tower", lat: 14.5543430, lng: 121.0440980 },
        { name: "One Parkade", lat: 14.550050520728623, lng: 121.0499049573256 },
        { name: "University Parkway", lat: 14.551577178911622, lng: 121.05723490011086 }
    ];

    // Bus Stop Markers
    busStops.forEach(stop => {
        L.marker([stop.lat, stop.lng], { icon: redIcon })
            .addTo(map)
            .bindPopup(`
                <img src="../image/busmarkk.png" alt="Bus Stop Icon" width="20" height="20">
                <b>${stop.name}</b>
            `);
    });

    let busMarkers = [];

    async function fetchBusData() {
        try {
            const response = await fetch('fetch_buses.php');
            const data = await response.json();

            if (!Array.isArray(data)) {
                console.error("Invalid data format:", data);
                return;
            }

            // Clear old markers before adding new ones
            busMarkers.forEach(marker => map.removeLayer(marker));
            busMarkers = [];

            // Collect all coordinates for bounds calculation
            const allCoordinates = [];

            // Add new markers with valid coordinates
            data.forEach(bus => {
                const lat = parseFloat(bus.latitude);
                const lng = parseFloat(bus.longitude);

                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.marker([lat, lng], { icon: blueIcon })
                        .addTo(map)
                        .bindPopup(`
                            <img src="../image/busiconn.PNG" alt="Bus Icon" width="20" height="20"><br>
                            <b>Bus No:</b> ${bus.bus_no || 'N/A'}<br>
                            <b>Next Stop:</b> ${bus.next_stop || 'N/A'}<br>
                            <b>ETA:</b> ${bus.eta || 'Unknown'} mins
                        `);
                    busMarkers.push(marker);
                    allCoordinates.push([lat, lng]);
                }
            });

            // Add bus stop coordinates to the bounds
            busStops.forEach(stop => {
                allCoordinates.push([stop.lat, stop.lng]);
            });

            // Fit map to bounds if there are any coordinates
            if (allCoordinates.length > 0) {
                const bounds = L.latLngBounds(allCoordinates);
                map.fitBounds(bounds);
            }

        } catch (error) {
            console.error("Error fetching bus data:", error);
        }
    }

    fetchBusData();
    setInterval(fetchBusData, 10000);
    </script>
</main>
</body>
</html>