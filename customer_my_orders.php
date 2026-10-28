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

// Get orders belonging to the logged-in customer
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

// Get products belonging to each order
$item_sql = "SELECT
                p.product_name,
                oi.quantity,
                oi.unit_price
             FROM order_items oi
             LEFT JOIN products p
                ON oi.product_id = p.product_id
             WHERE oi.order_id = ?";

$items_stmt = $conn->prepare($item_sql);

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

        <?php while ($order = $result->fetch_assoc()): ?>

            <section>
                <hr>

                <h3>
                    Order #<?php echo (int) $order["order_id"]; ?>
                </h3>

                <p>
                    <strong>Order Date:</strong>
                    <?php echo htmlspecialchars($order["order_date"]); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?php echo htmlspecialchars($order["order_status"]); ?>
                </p>

                <p>
                    <strong>Delivery Address:</strong>
                    <?php echo htmlspecialchars($order["delivery_address"]); ?>
                </p>

                <h4>Products Ordered</h4>

                <table border="1" cellpadding="8">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php
                        $order_id = (int) $order["order_id"];

                        $items_stmt->bind_param("i", $order_id);
                        $items_stmt->execute();

                        $items_result = $items_stmt->get_result();

                        if ($items_result->num_rows > 0):
                            while ($item = $items_result->fetch_assoc()):
                                $quantity = (int) $item["quantity"];
                                $unit_price = (float) $item["unit_price"];
                                $subtotal = $quantity * $unit_price;
                        ?>

                            <tr>
                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $item["product_name"] ?? "Product unavailable"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php echo $quantity; ?>
                                </td>
                                <td>
                                    Rs. <?php echo number_format($unit_price, 2); ?>
                                </td>

                                <td>
                                    Rs. <?php echo number_format($subtotal, 2); ?>
                                </td>
                            </tr>

                        <?php
                            endwhile;
                        else:
                        ?>

                            <tr>
                                <td colspan="4">No product details found.</td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

                <p>
                    <strong>Order Total:</strong>
                    Rs. <?php echo number_format(
                        (float) $order["total_amount"],
                        2
                    ); ?>
                    </p>

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
                            value="<?php echo htmlspecialchars(
                                $_SESSION["csrf_token"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >
                        <button type="submit">Cancel Order</button>
                    </form>

                <?php else: ?>

                    <p>Cancellation is not available for this order.</p>

                <?php endif; ?>

            </section>

        <?php endwhile; ?>

    <?php else: ?>

        <p>You have not placed any orders yet.</p>

    <?php endif; ?>

    <hr>

    <p><a href="customer_dashboard.php">Back to Dashboard</a></p>

    <p><a href="order.php">Place a Quick Order</a></p>

    <p><a href="customer_logout.php">Logout</a></p>

</body>
</html>

<?php

$items_stmt->close();
$stmt->close();

?>