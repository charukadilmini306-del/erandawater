
<?php

require_once "config/database.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Eranda Water | Fresh & Mineral Filter Water</title>

    <link rel="stylesheet" href="assets/css/home.css">
</head>

<body>

    <!-- HEADER -->
    <header class="header">

        <div class="header-container">

            <a href="index.php" class="logo">
                <img
                    src="assets/images/eranda 2.jpeg"
                    alt="Eranda Water Logo"
                >
            </a>

            <nav class="navigation">
                <a href="#home">Home</a>
                <a href="#about">About Us</a>
                <a href="#products">Our Products</a>
                <a href="#contact">Contact Us</a>
            </nav>

            <div class="header-buttons">
                <a href="customer_login.php" class="login-btn">
                    Customer Login
                </a>

                <a href="customer_register.php" class="register-btn">
                    Register
                </a>
            </div>

        </div>

    </header>


    <!-- HERO SECTION -->
    <section class="hero" id="home">

        <div class="hero-content">

            <p class="hero-subtitle">WELCOME TO ERANDA WATER</p>

            <h1>
                Fresh & Mineral
                <span>Filter Water</span>
            </h1>

            <p class="hero-description">
                Welcome to Eranda Water.
                Your trusted destination for fresh and mineral filter water.
                Explore our products and place your orders online.
            </p>

            <div class="hero-buttons">

                <a href="customer_login.php" class="primary-btn">
                    Order Now
                </a>

                <a href="#products" class="secondary-btn">
                    Explore Products
                </a>

            </div>

        </div>

    </section>


    <!-- ABOUT SECTION -->
    <section class="about-section" id="about">

        <div class="section-heading">
            <p>ABOUT US</p>
            <h2>Welcome to Eranda Water</h2>
        </div>

        <div class="about-content">

            <h3>Your Water, Your Convenience</h3>

            <p>
                Eranda Water provides fresh and mineral filter water
                products with a convenient ordering experience.
                Customers can explore available products, place orders
                online and check their order details through their
                customer accounts.
            </p>

        </div>

    </section>


    <!-- PRODUCTS SECTION -->
    <section class="products-section" id="products">

        <div class="section-heading">
            <p>OUR PRODUCTS</p>
            <h2>Explore Our Water Products</h2>
        </div>

        <div class="product-grid">

            <div class="product-card">

                <div class="product-number">01</div>

                <h3>Small Water Bottle</h3>

                <p>
                    Small bottle option for convenient everyday use.
                </p>

                <a href="customer_login.php">
                    Order Now →
                </a>

            </div>


            <div class="product-card">

                <div class="product-number">02</div>

                <h3>Large Water Bottle</h3>

                <p>
                    Large bottle option for your drinking water needs.
                </p>

                <a href="customer_login.php">
                    Order Now →
                </a>

            </div>


            <div class="product-card">

                <div class="product-number">03</div>

                <h3>Bulk Water Can</h3>

                <p>
                    Bulk water can option for your water requirements.
                </p>

                <a href="customer_login.php">
                    Order Now →
                </a>

            </div>

        </div>

    </section>


    <!-- CONTACT SECTION -->

<div class="contact-content">

   <div class="section-heading">
        <p>CONTACT US</p>
        <h2>Get in Touch with Us</h2>
    </div>

    <div class="contact-card">
        <h3>WhatsApp</h3>
        <p>077 244 9100</p>
        <a href="https://wa.me/94772449100" target="_blank" rel="noopener noreferrer">
            Chat on WhatsApp
        </a>
    </div>

    <div class="contact-card">
        <h3>Phone</h3>
        <p>070 124 6900</p>
        <a href="tel:+94701246900">Call Us</a>
    </div>

    <div class="contact-card">
        <h3>Email</h3>
        <p>erandarox402@gmail.com</p>
        <a href="mailto:erandarox402@gmail.com">Send Email</a>
    </div>

</div>









    <!-- FOOTER -->
    <footer class="footer">

        <h3>Eranda Water</h3>

        <p>Fresh & Mineral Filter Water</p>
        <p>
    Email:
    <a href="mailto:erandarox402@gmail.com">
        erandarox402@gmail.com
    </a>
</p>

        <p>
            © <?php echo date("Y"); ?> Eranda Water.
            All Rights Reserved.
        </p>

    </footer>

</body>
</html>
