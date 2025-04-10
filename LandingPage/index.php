<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BGC Landing Page</title>
    <link rel="stylesheet" href="Passenger.css">
</head>
<body>
<header>
    <nav>
        <button id="navToggle" class="nav-toggle">☰</button>
        <div id="navMenu" class="nav-menu">
            <a href="index.php"class="active">Home</a>
            <a href="Routes.php">Routes</a>
            <a href="AboutUs.php" >About Us</a>
            <a href="ContactUs.php">Contact Us</a>
        </div>
    </nav>
</header>
    
    <main>
        <div class="center-image">
            <img src="../image/bgc.PNG" alt="Center Image">
        </div>
       
    </main>
    <script>
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