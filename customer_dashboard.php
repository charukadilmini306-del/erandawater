
<?php

session_start();

// CHECK CUSTOMER LOGIN
if (
    !isset($_SESSION["customer_logged_in"]) ||
    $_SESSION["customer_logged_in"] !== true ||
    !isset($_SESSION["customer_id"])
) {
    header("Location: customer_login.php");
    exit();
}

// CHECK FORCED PASSWORD CHANGE
if (
    isset($_SESSION["customer_must_change_password"]) &&
    (int) $_SESSION["customer_must_change_password"] === 1
) {
    header("Location: customer_change_password.php");
    exit();
}

// CUSTOMER NAME
$customer_name = htmlspecialchars(
    $_SESSION["customer_name"] ?? "Customer",
    ENT_QUOTES,
    "UTF-8"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Dashboard - Eranda Water</title>

    <link rel="stylesheet" href="assets/css/customer.css">
</head>

<body>

    <div class="app-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-brand">

                <img
                    src="assets/images/eranda 2.jpeg"
                    alt="Eranda Water Logo"
                >

                <h2>Eranda Water</h2>

                <p>Customer Portal</p>

            </div>

            <nav class="sidebar-nav">

                <a href="customer_dashboard.php" class="active">
                    Dashboard
                </a>

                <a href="customer_my_orders.php">
                    My Orders
                </a>

                <a href="customer_change_password.php">
                    Change Password
                </a>

            </nav>
                        <!-- SIDEBAR FOOTER -->
            <div class="sidebar-footer">
                <a href="logout.php">Logout</a>
            </div>

        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <!-- TOP BAR -->
            <header class="topbar">

                <div>
                    <h2>Customer Dashboard</h2>
                    <p>Eranda Water Management System</p>
                </div>

                <div class="customer-profile">
                    <span class="profile-icon">C</span>

                    <span>
                        <?php echo $customer_name; ?>
                    </span>
                </div>

            </header>

            <!-- WELCOME SECTION -->
            <section class="welcome-card">

                <h1>
                    Welcome, <?php echo $customer_name; ?>!
                </h1>

                <p>
                    You are successfully logged in to your customer account.
                </p>

                <p>
                    Welcome to the Eranda Water Customer Portal.
                </p>

            </section>

            <!-- DASHBOARD SECTION -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <h2>My Account</h2>
                    <p>Manage your orders and account details.</p>
                </div>

                <div class="dashboard-grid">

                    <!-- MY ORDERS -->
                    <a href="customer_my_orders.php" class="dashboard-card">

                        <div class="card-icon">
                            <span>01</span>
                        </div>

                        <h3>My Orders</h3>

                        <p>
                            View your orders, order status and payment details.
                        </p>

                        <span class="card-link">View Orders →</span>

                    </a>

                    <!-- CHANGE PASSWORD -->
                    <a href="customer_change_password.php" class="dashboard-card">

                        <div class="card-icon">
                            <span>02</span>
                        </div>

                        <h3>Change Password</h3>

                        <p>
                            Update your account password securely.
                        </p>

                        <span class="card-link">Change Password →</span>

                    </a>

                </div>

            </section>

            <footer class="footer">
                <p>© <?php echo date("Y"); ?> Eranda Water. All Rights Reserved.</p>
            </footer>

        </main>

    </div>

</body>
</html>
