<?php

session_start();

if (
    !isset($_SESSION["customer_logged_in"]) ||
    $_SESSION["customer_logged_in"] !== true ||
    !isset($_SESSION["customer_id"])
) {
    header("Location: customer_login.php");
    exit;
}

require_once "config/database.php";

$customer_id = (int) $_SESSION["customer_id"];

// Create CSRF token for secure cancellation requests
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$sql = "SELECT
            order_id,
            order_date,
            order_status,
            total_amount,
            delivery_address
        FROM orders
        WHERE customer_id = ?
        ORDER BY order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Eranda Water</title>
</head>
<body>

    <h1>Eranda Water</h1>
    <h2>My Orders</h2>

    <?php if (isset($_GET["cancelled"]) && $_GET["cancelled"] === "1"): ?>
        <p>Order cancelled successfully.</p>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <p>Unable to cancel the order. Please refresh the page and try again.</p>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <table border="1" cellpadding="8">
            <tr>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Status</th>
                <th>Total Amount</th>
                <th>Delivery Address</th>
                <th>Action</th>
            </tr>

            <?php while ($order = $result->fetch_assoc()): ?>

                <tr>
                    <td>
                        <?php echo (int) $order["order_id"]; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($order["order_date"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($order["order_status"]); ?>
                    </td>

                    <td>
                        Rs. <?php echo number_format(
                            (float) $order["total_amount"],
                            2
                        ); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($order["delivery_address"]); ?>
                    </td>

                    <td>
                        <?php if ($order["order_status"] === "Pending"): ?>

                            <form
                                method="POST"
                                action="customer_cancel_order.php"
                                onsubmit="return confirm('Are you sure you want to cancel this order?');"
                            >
                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?php echo (int) $order["order_id"]; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                                >

                                <button type="submit">
                                    Cancel Order
                                </button>
                            </form>

                        <?php else: ?>

                            Not available

                        <?php endif; ?>
                    </td>
                </tr>

            <?php endwhile; ?>

        </table>

    <?php else: ?>

        <p>You have not placed any orders yet.</p>

    <?php endif; ?>

    <p><a href="customer_dashboard.php">Back to Dashboard</a></p>

    <p><a href="order.php">Place a Quick Order</a></p>

    <p><a href="customer_logout.php">Logout</a></p>

</body>
</html>

<?php

$stmt->close();

?>