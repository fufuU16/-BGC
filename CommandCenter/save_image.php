<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Database connection
$conn = new mysqli("localhost", "u537987570_judymalahay", "Malahayj123", "u537987570_bgc_database");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$uploadDir = "uploads_passenger/";
$imageLimit = 10;

// Fetch distinct bus numbers from the database
$busNumbers = $conn->query("SELECT DISTINCT bus_id FROM images")->fetch_all(MYSQLI_ASSOC);

// Function to handle image upload and passenger count
function handleImageUpload($conn, $uploadDir, $imageLimit) {
    // Ensure the uploads folder exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Check and delete oldest images if limit is exceeded
    $images = glob($uploadDir . "*.jpg");
    if (count($images) >= $imageLimit) {
        // Sort images by modification time, oldest first
        usort($images, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Delete oldest images to maintain the limit
        $imagesToDelete = count($images) - $imageLimit + 1;
        for ($i = 0; $i < $imagesToDelete; $i++) {
            $imagePath = $images[$i];
            if (unlink($imagePath)) {
                // Delete the corresponding database entry
                $stmt = $conn->prepare("DELETE FROM images WHERE image_path = ?");
                $stmt->bind_param("s", $imagePath);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Generate unique filename
    $imageFile = $uploadDir . uniqid() . ".jpg";

    // Save the file
    $imageData = file_get_contents("php://input");
    if (!$imageData) {
        echo "Error: No image data received.";
        return;
    }

    if (file_put_contents($imageFile, $imageData) !== false) {
        echo "Image successfully saved!";

        // Retrieve and sanitize bus_id
        $busId = isset($_GET['bus_id']) ? htmlspecialchars($_GET['bus_id']) : '';
        if (empty($busId)) {
            echo " Invalid bus_id.";
            return;
        }

        // Insert path into the database with passenger count placeholder
        $stmt = $conn->prepare("INSERT INTO images (image_path, passenger_count, bus_id) VALUES (?, ?, ?)");
        $passengerCount = 0; // Placeholder, will be updated after detection
        $stmt->bind_param("sis", $imageFile, $passengerCount, $busId);

        if ($stmt->execute()) {
            echo " Image path saved to database.";
            // Trigger passenger detection for the newly uploaded image
            // Call a server-side script to perform detection
            $passengerCount = performDetection($imageFile);
            updatePassengerCount($conn, $imageFile, $passengerCount);
        } else {
            echo " Error saving image path to database.";
        }
        $stmt->close();
    } else {
        echo " Error saving image file.";
    }

    // Debugging output
    echo " Image data size: " . strlen($imageData);
}

// Function to perform detection (placeholder for actual implementation)
function performDetection($imagePath) {
    $image = imagecreatefromjpeg($imagePath);
    if (!$image) {
        return 0;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $threshold = 200; // Brightness threshold
    $brightPixelCount = 0;

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $brightness = ($r + $g + $b) / 3;

            if ($brightness > $threshold) {
                $brightPixelCount++;
            }
        }
    }

    imagedestroy($image);

    // Return a random number for demonstration purposes
    return rand(0, 5);
}

// Function to update passenger count in the database
function updatePassengerCount($conn, $imagePath, $count) {
    $stmt = $conn->prepare("UPDATE images SET passenger_count = ? WHERE image_path = ?");
    $stmt->bind_param("is", $count, $imagePath);
    $stmt->execute();
    $stmt->close();
}

// Handle POST request for image upload
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    handleImageUpload($conn, $uploadDir, $imageLimit);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latest Image Display</title>
    <link rel="stylesheet" href="save_image.css">
    <style>
        body { text-align: center; font-family: Arial, sans-serif; }
        img { max-width: 100%; height: auto; }
        .image-container { position: relative; display: inline-block; }
        canvas { position: absolute; top: 0; left: 0; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd"></script>
    <script>
        let model;

        async function loadModel() {
            model = await cocoSsd.load();
            console.log("Model Loaded!");
        }

        async function detectHumans(imagePath) {
            const img = new Image();
            img.src = imagePath;
            img.onload = async () => {
                const canvas = document.getElementById('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0, img.width, img.height);

                const predictions = await model.detect(img);
                let humanCount = 0;

                predictions.forEach(prediction => {
                    if (prediction.class === "person" && prediction.score > 0.5) {
                        humanCount++;
                        const [x, y, width, height] = prediction.bbox;
                        ctx.strokeStyle = "red";
                        ctx.lineWidth = 2;
                        ctx.strokeRect(x, y, width, height);
                        ctx.fillStyle = "red";
                        ctx.fillText(`Person: ${(prediction.score * 100).toFixed(1)}%`, x, y > 10 ? y - 5 : 10);
                    }
                });

                console.log(`Detected ${humanCount} passengers.`);
                updatePassengerCount(imagePath, humanCount);
            };
        }

        function updatePassengerCount(imagePath, count) {
            fetch('update_passenger_count.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ imagePath, count })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Passenger count updated:', data);
            })
            .catch(error => console.error('Error updating passenger count:', error));
        }

        function updateLatestImage() {
            const busNumber = document.getElementById('busNumberDropdown').value;
            if (busNumber) {
                fetch(`check_new_image.php?bus_id=${busNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.newImage) {
                            const imgElement = document.getElementById('latestImage');
                            if (imgElement) {
                                imgElement.src = data.newImage + '?' + new Date().getTime();
                                detectHumans(data.newImage);
                            }
                        } else {
                            console.log('No images found for this bus.');
                        }
                    })
                    .catch(error => console.error('Error fetching latest image:', error));
            }
        }

        setInterval(updateLatestImage, 5000);

        loadModel();
    </script>
</head>
<header>
    <?php
    if (!isset($_SESSION['username'])) {
        // Redirect to login page if not logged in
        header("Location: Login.php");
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
<body>
<div class="Title">
<h1>Passenger Count Camera</h1>
    </div>
    
    <div class="container">
    <h1>Passenger Count Camera</h1>
    <select id="busNumberDropdown" onchange="updateLatestImage()">
        <option value="">Select Bus Number</option>
        <?php foreach ($busNumbers as $bus): ?>
            <option value="<?php echo htmlspecialchars($bus['bus_id']); ?>">
                <?php echo htmlspecialchars($bus['bus_id']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <h1>Latest Image</h1>
    <div class="image-container">
        <img id="latestImage" src="" alt="Latest Image">
        <canvas id="canvas"></canvas>
    </div>
</div>
</body>
</html>