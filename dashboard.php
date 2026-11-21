
<?php
session_start();

// Allow only logged-in Staff users
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "staff"
) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$user_id = (int) $_SESSION["user_id"];

$staff_name = htmlspecialchars(
    $_SESSION["full_name"] ?? "Staff",
    ENT_QUOTES,
    "UTF-8"
);

// Get unread notification count
$unread_count = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE user_id = ?
     AND is_read = 0"
);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $unread_count = (int) ($row["unread_count"] ?? 0);

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard - Eranda Water</title>

    <link rel="stylesheet" href="assets/css/staff.css">
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
            <p>Staff Panel</p>

        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="active">
                Staff Dashboard
            </a>

            <a href="customers.php">
                Customer Management
            </a>

            <a href="products.php">
                Product Management
            </a>

            <a href="manage_orders.php">
                Order Management
            </a>

            <a href="manage_deliveries.php">
                Delivery Management
            </a>

            <a href="manage_payments.php">
                Payment Management
            </a>

            <a href="reports.php">
                Reports
            </a>

            <a href="notifications.php">
                Notifications (<?php echo $unread_count; ?>)
            </a>

            <a href="order.php">
                Place New Order
            </a>

            <a href="user_change_password.php">
                Change Password
            </a>

        </nav>

        <div class="sidebar-footer">

            <a href="logout.php">
                Logout
            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOP BAR -->
        <header class="topbar">

            <div>
                <h2>Staff Dashboard</h2>
                <p>Eranda Water Management System</p>
            </div>

            <div class="admin-profile">
                <?php echo $staff_name; ?>
            </div>

        </header>


        <!-- WELCOME SECTION -->
        <section class="welcome-card">

            <h2>Welcome, <?php echo $staff_name; ?>!</h2>

            <p>
                Manage customers, products, orders, deliveries and payments
                through the Eranda Water Management System.
            </p>

        </section>


        <!-- MANAGEMENT SECTION -->
        <section class="dashboard-section">

            <div class="section-heading">

                <h2>Management</h2>

                <p>
                    Select a section to manage your daily activities.
                </p>

            </div>


            <div class="dashboard-grid">

                <a href="customers.php" class="dashboard-card">
                    <h3>Customer Management</h3>
                    <p>View and manage customer information.</p>
                </a>


                <a href="products.php" class="dashboard-card">
                    <h3>Product Management</h3>
                    <p>View available products and stock details.</p>
                </a>


                <a href="manage_orders.php" class="dashboard-card">
                    <h3>Order Management</h3>
                    <p>View and process customer orders.</p>
                </a>


                <a href="manage_deliveries.php" class="dashboard-card">
                    <h3>Delivery Management</h3>
                    <p>View delivery assignments and information.</p>
                </a>


                <a href="manage_payments.php" class="dashboard-card">
                    <h3>Payment Management</h3>
                    <p>View and manage customer payments.</p>
                </a>


                <a href="reports.php" class="dashboard-card">
                    <h3>Reports</h3>
                    <p>View management reports.</p>
                </a>


                <a href="notifications.php" class="dashboard-card">
                    <h3>Notifications</h3>
                    <p>
                        You have
                        <?php echo $unread_count; ?>
                        unread notifications.
                    </p>
                </a>


                <a href="order.php" class="dashboard-card">
                    <h3>Place New Order</h3>
                    <p>Create a new customer order.</p>
                </a>


                <a href="user_change_password.php" class="dashboard-card">
                    <h3>Change Password</h3>
                    <p>Update your account password securely.</p>
                </a>

            </div>

        </section>

    </main>

</div>

</body>
</html>
