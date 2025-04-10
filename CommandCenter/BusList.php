<?php
include 'db_connection.php'; // Ensure this file correctly sets up the database connection
date_default_timezone_set('Asia/Manila');

// Fetch all buses from the database
$query = "SELECT bus_id, bus_number, capacity, status FROM buses";
$result = $conn->query($query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus List</title>
    <link rel="stylesheet" href="BusList.css">
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
    ?>
    <div class="header-content">
        <div class="username-display">
            <?php if (isset($_SESSION['username'])): ?>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
            <?php endif; ?>
        </div>
        <nav>
            <a href="Dashboard.php">Dashboard</a>
            <a href="Schedulebus.php">Bus Schedule</a>
            <a href="BusList.php" class="active">Bus List</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>
<main>
    <div class="Title">
        <h1>Bus List</h1>
    </div>
    
    <!-- Controls for Search -->
    <div class="controls">
        <input type="text" id="searchInput" placeholder="Search by Bus ID or Number..." onkeyup="searchBusList()">
    </div>
    
    <div class="bus-table">
        <table id="busTable">
            <thead>
                <tr>
                    <th>Bus ID</th>
                    <th>Bus Number</th>
                    <th>Capacity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['bus_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['bus_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['capacity']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
    function searchBusList() {
        let input = document.getElementById("searchInput").value.toLowerCase();
        let table = document.getElementById("busTable");
        let rows = table.getElementsByTagName("tr");

        for (let i = 1; i < rows.length; i++) {
            let row = rows[i];
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(input) ? "" : "none";
        }
    }
</script>
</body>
</html>