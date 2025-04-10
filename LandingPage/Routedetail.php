<?php
include 'db_connection.php'; // Include the database connection

// Query to fetch bus stop details
$busStopQuery = "
    SELECT bus_no, passenger_count, eta, bus_number, route, current_stop, next_stop, end_point, timestamp
    FROM bus_stop_details
    WHERE route = 'Central Route'
    ORDER BY bus_no
";

$busStopResult = $conn->query($busStopQuery);

// Check for query errors
if (!$busStopResult) {
    die("SQL error: " . $conn->error);
}

// Fetch data into an array
$busStopData = [];
if ($busStopResult->num_rows > 0) {
    while ($row = $busStopResult->fetch_assoc()) {
        $busStopData[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Details - BGC Landing Page</title>
    <link rel="stylesheet" href="Routedetail.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
<header>
    <nav>
        <button id="navToggle" class="nav-toggle">☰</button>
        <div id="navMenu" class="nav-menu">
            <a href="index.php.php">Home</a>
            <a href="Routes.php">Routes</a>
            <a href="AboutUs.php" class="active">About Us</a>
            <a href="ContactUs.php">Contact Us</a>
        </div>
    </nav>
</header>
    
<main>
    <h1 id="route-title">CENTRAL ROUTE</h1>
    <div id="map" style="height: 500px; width: 100%; margin-top: 20px;"></div>
    
    <section class="bus-stop-details">
        <?php if (!empty($busStopData)): ?>
            <?php foreach ($busStopData as $bus): ?>
                <div class="bus-stop-card">
                    <h2>Bus No. <?php echo htmlspecialchars($bus['bus_no']); ?></h2>
                    <p><strong>Passenger Count:</strong> <?php echo htmlspecialchars($bus['passenger_count']); ?></p>
                    <p><strong>ETA:</strong> <?php echo htmlspecialchars($bus['eta']); ?></p>
                    <p><strong>Bus Number:</strong> <?php echo htmlspecialchars($bus['bus_number']); ?></p>
                    <p><strong>Current Stop:</strong> <?php echo htmlspecialchars($bus['current_stop']); ?></p>
                    <p><strong>Next Stop:</strong> <?php echo htmlspecialchars($bus['next_stop']); ?></p>
                    <p><strong>End Point:</strong> <?php echo htmlspecialchars($bus['end_point']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No bus stop details available for the Central Route.</p>
        <?php endif; ?>
    </section>
</main>

<script>
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
        { name: "The Globe Tower", lat: 14.553002437123753, lng: 121.05016289604174 },
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

    document.addEventListener('DOMContentLoaded', function() {
        const navToggle = document.querySelector('button.nav-toggle');
        const navMenu = document.querySelector('.nav-menu');

        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('open');
        });

        // Ensure nav-menu is visible in full screen
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                navMenu.classList.remove('open');
                navMenu.style.display = 'flex'; // Ensure it's visible in full screen
            } else {
                navMenu.style.display = ''; // Reset to default for mobile
            }
        });
    });
</script>

</body>
</html>