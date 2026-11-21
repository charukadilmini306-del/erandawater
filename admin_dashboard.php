
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin- Eranda Water</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <img src="assets/images/eranda 2.jpeg" alt="Eranda Water Logo">
            <h2>Eranda Water</h2>
            
        </div>

        <nav class="sidebar-nav">

            <a href="admin_dashboard.php" class="active">
                Dashboard
            </a>

            <a href="customers.php">
                Customer Management
            </a>

            <a href="products.php">
                Product Management
            </a>

            <a href="order.php">
                Place New Order
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

            <a href="manage_maintenance.php">
                Maintenance Management
            </a>

            <a href="notifications.php">
                Notifications
            </a>

            <a href="admin_password_reset_requests.php">
                Password Reset Requests
            </a>

            <a href="create_staff.php">
                Staff Management
            </a>

            <a href="admin.php">
                Admin Account
            </a>

        </nav>

        <div class="sidebar-footer">
            <a href="logout.php">Logout</a>
        </div>

    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <header class="topbar">

            
                
            

            <div class="admin-profile">
                Administrator
            </div>
        </header>

        <section class="welcome-card">
            <h2>Welcome, Eranda Water Admin!</h2>
            <p>
                Manage customers, products, orders, deliveries,
                payments and other business activities from here.
            </p>
        </section>

        <section class="dashboard-section">

            <h2>Management Overview</h2>

            <div class="dashboard-grid">

                <a href="customers.php" class="dashboard-card">
                    <h3>Customer Management</h3>
                    <p>Manage customer information and accounts.</p>
                </a>

                <a href="products.php" class="dashboard-card">
                    <h3>Product Management</h3>
                    <p>Manage products and stock information.</p>
                </a>

                <a href="manage_orders.php" class="dashboard-card">
                    <h3>Order Management</h3>
                    <p>View and manage customer orders.</p>
                </a>

                <a href="manage_deliveries.php" class="dashboard-card">
                    <h3>Delivery Management</h3>
                    <p>Assign and manage deliveries.</p>
                </a>

                <a href="manage_payments.php" class="dashboard-card">
                    <h3>Payment Management</h3>
                    <p>Manage and verify customer payments.</p>
                </a>

                <a href="reports.php" class="dashboard-card">
                    <h3>Reports</h3>
                    <p>View business and operational reports.</p>
                </a>

                <a href="manage_maintenance.php" class="dashboard-card">
                    <h3>Maintenance</h3>
                    <p>Manage equipment maintenance activities.</p>
                </a>

                <a href="notifications.php" class="dashboard-card">
                    <h3>Notifications</h3>
                    <p>View system notifications.</p>
                </a>

                <a href="admin_password_reset_requests.php" class="dashboard-card">
                    <h3>Password Reset Requests</h3>
                    <p>Manage customer, staff and delivery reset requests.</p>
                </a>

                <a href="create_staff.php" class="dashboard-card">
                    <h3>Staff Management</h3>
                    <p>Create and manage staff accounts.</p>
                </a>

                <a href="admin.php" class="dashboard-card">
                    <h3>Admin Account</h3>
                    <p>Manage administrator account details.</p>
                </a>

                <a href="order.php" class="dashboard-card">
                    <h3>Place New Order</h3>
                    <p>Create an order for a customer.</p>
                </a>

            </div>

        </section>

    </main>

</div>

</body>
</html>
