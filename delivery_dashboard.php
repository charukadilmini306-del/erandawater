
<?php
session_start();

require_once "config/database.php";

// Check delivery person login
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "delivery"
) {
    header("Location: login.php");
    exit();
}

$delivery_person_id = (int) $_SESSION["user_id"];
$full_name = htmlspecialchars($_SESSION["full_name"] ?? "Delivery Person");

$message = "";
$error = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Update delivery status
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $delivery_id = filter_input(INPUT_POST, "delivery_id", FILTER_VALIDATE_INT);
        $new_status = $_POST["new_status"] ?? "";

        if (!$delivery_id || !in_array($new_status, ["Out for Delivery", "Delivered"], true)) {
            $error = "Invalid delivery information.";
        } else {

            $conn->begin_transaction();

            try {
                // Get assigned delivery and current status
                $stmt = $conn->prepare(
                    "SELECT d.order_id, d.delivery_status, o.order_status
                     FROM deliveries d
                     INNER JOIN orders o ON d.order_id = o.order_id
                     WHERE d.delivery_id = ?
                     AND d.delivery_person_id = ?
                     AND o.order_status != 'Cancelled'
                     FOR UPDATE"
                );

                $stmt->bind_param("ii", $delivery_id, $delivery_person_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $delivery = $result->fetch_assoc();
                $stmt->close();

                if (!$delivery) {
                    throw new Exception("Delivery not found or not assigned to you.");
                }

                $order_id = (int) $delivery["order_id"];
                $current_status = $delivery["delivery_status"];

                // Validate allowed status transition
                $valid_transition =
                    ($current_status === "Pending" && $new_status === "Out for Delivery") ||
                    ($current_status === "Out for Delivery" && $new_status === "Delivered");

                if (!$valid_transition || $delivery["order_status"] !== $current_status) {
                    throw new Exception("This delivery status cannot be updated.");
                }

                // Update delivery record
                if ($new_status === "Delivered") {
                    $update_delivery = $conn->prepare(
                        "UPDATE deliveries
                         SET delivery_status = ?, delivered_at = NOW()
                         WHERE delivery_id = ?
                         AND delivery_person_id = ?
                         AND delivery_status = ?"
                    );
                } else {
                    $update_delivery = $conn->prepare(
                        "UPDATE deliveries
                         SET delivery_status = ?
                         WHERE delivery_id = ?
                         AND delivery_person_id = ?
                         AND delivery_status = ?"
                    );
                }

                $update_delivery->bind_param(
                    "siis",
                    $new_status,
                    $delivery_id,
                    $delivery_person_id,
                    $current_status
                );

                $update_delivery->execute();

                if ($update_delivery->affected_rows !== 1) {
                    throw new Exception("Delivery update failed.");
                }

                $update_delivery->close();

                // Update related order status
                $update_order = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?
                     WHERE order_id = ?
                     AND order_status = ?"
                );

                $update_order->bind_param(
                    "sis",
                    $new_status,
                    $order_id,
                    $current_status
                );

                $update_order->execute();

                if ($update_order->affected_rows !== 1) {
                    throw new Exception("Order status update failed.");
                }

                $update_order->close();

                $conn->commit();

                $message = "Delivery status updated successfully.";

            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get assigned deliveries
$stmt = $conn->prepare(
    "SELECT
        d.delivery_id,
        d.order_id,
        d.delivery_status,
        d.delivery_date,
        d.delivered_at,
        c.full_name,
        c.phone,
        c.address,
        o.total_amount
     FROM deliveries d
     INNER JOIN orders o ON d.order_id = o.order_id
     INNER JOIN customers c ON o.customer_id = c.customer_id
     WHERE d.delivery_person_id = ?
     ORDER BY d.delivery_date DESC"
);

$stmt->bind_param("i", $delivery_person_id);
$stmt->execute();
$deliveries = $stmt->get_result();

$total_deliveries = $deliveries->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard | Eranda Water</title>

    <link rel="stylesheet" href="assets/css/delivery.css">
</head>

<body>

<div class="app-layout">

    <aside class="sidebar">

        <div class="sidebar-brand">
            <img src="assets/images/eranda 2.jpeg" alt="Eranda Water Logo">
            <h2>Eranda Water</h2>
            <p>Delivery Panel</p>
        </div>

        <nav class="sidebar-nav">
            <a href="delivery_dashboard.php" class="active">Delivery Dashboard</a>
            <a href="delivery_payment.php">Payment Collection</a>
            <a href="user_change_password.php">Change Password</a>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php">Logout</a>
        </div>

    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h2>Delivery Dashboard</h2>
                <p>Eranda Water Delivery Management</p>
            </div>

            <div class="admin-profile">
                <span><?php echo $full_name; ?></span>
            </div>
        </header>

        <section class="welcome-card">
            <h2>Welcome, Delivery Person!</h2>
            <p>Manage your assigned deliveries and update delivery statuses.</p>
        </section>

        <?php if ($message !== ""): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <section class="dashboard-section">

            <div class="section-heading">
                <h2>My Assigned Deliveries</h2>
                <p>Total assigned deliveries: <?php echo $total_deliveries; ?></p>
            </div>

            <div class="table-container">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Delivery Address</th>
                            <th>Delivery Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($total_deliveries > 0): ?>

                        <?php while ($row = $deliveries->fetch_assoc()): ?>

                            <tr>
                                <td>#<?php echo (int) $row["order_id"]; ?></td>

                                <td>
                                    <?php echo htmlspecialchars($row["full_name"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["phone"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["address"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["delivery_date"] ?? "Not set"); ?>
                                </td>

                                <td>
                                    Rs. <?php echo number_format((float) $row["total_amount"], 2); ?>
                                </td>

                                <td>
                                    <span class="status-badge">
                                        <?php echo htmlspecialchars($row["delivery_status"]); ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if ($row["delivery_status"] === "Pending"): ?>

                                        <form method="POST">
                                            <input type="hidden" name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                                            <input type="hidden" name="delivery_id"
                                                value="<?php echo (int) $row["delivery_id"]; ?>">

                                            <input type="hidden" name="new_status"
                                                value="Out for Delivery">

                                            <button type="submit" name="update_status"
                                                class="btn-primary">
                                                Start Delivery
                                            </button>
                                        </form>

                                    <?php elseif ($row["delivery_status"] === "Out for Delivery"): ?>

                                        <form method="POST">
                                            <input type="hidden" name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                                            <input type="hidden" name="delivery_id"
                                                value="<?php echo (int) $row["delivery_id"]; ?>">

                                            <input type="hidden" name="new_status"
                                                value="Delivered">

                                            <button type="submit" name="update_status"
                                                class="btn-success">
                                                Mark as Delivered
                                            </button>
                                        </form>

                                    <?php elseif ($row["delivery_status"] === "Delivered"): ?>

                                        <span class="completed-label">Completed</span>

                                        <?php if (!empty($row["delivered_at"])): ?>
                                            <p class="small-text">
                                                <?php echo htmlspecialchars($row["delivered_at"]); ?>
                                            </p>
                                        <?php endif; ?>

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-message">
                                No deliveries have been assigned to you yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>

<?php
$stmt->close();
?>
