<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - BGC Landing Page</title>
    <link rel="stylesheet" href="AboutUs.css">
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
        <h1>About Us</h1>
        <section class="about-content">
            <p>Welcome to BGC Landing Page! We are dedicated to providing efficient and reliable transportation services across various routes in the city. Our mission is to ensure that every passenger enjoys a safe and comfortable journey.</p>
            
            <h2>Our Vision</h2>
            <p>To be the leading provider of public transportation services, known for our commitment to safety, reliability, and customer satisfaction.</p>
            
            <h2>Our Mission</h2>
            <p>To deliver high-quality transportation services that meet the needs of our community, while continuously improving our operations and embracing innovation.</p>
            
            <h2>Our Values</h2>
            <ul>
                <li><strong>Safety:</strong> Ensuring the safety of our passengers and staff is our top priority.</li>
                <li><strong>Reliability:</strong> Providing dependable services that our customers can count on.</li>
                <li><strong>Customer Satisfaction:</strong> Striving to exceed customer expectations with every journey.</li>
                <li><strong>Innovation:</strong> Embracing new technologies and ideas to enhance our services.</li>
            </ul>
        </section>
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