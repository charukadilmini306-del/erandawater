<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "config/database.php";

$error = "";
$order = null;

$order_id = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $order_id = trim($_POST["order_id"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if (
        !preg_match("/^[0-9]+$/", $order_id) ||
        (int)$order_id < 1
    ) {
        $error = "Please enter a valid Order ID.";

    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Please enter a valid 10-digit phone number.";

    } else {

        $sql = "SELECT
                    o.order_id,
                    o.order_date,
                    o.order_status,
                    o.total_amount,
                    o.delivery_address
                FROM orders o
                INNER JOIN customers c
                    ON o.customer_id = c.customer_id
                WHERE o.order_id = ?
                  AND c.phone = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "is",
            $order_id,
            $phone
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $order = $result->fetch_assoc();

        $stmt->close();

        if (!$order) {
            $error = "Order not found. Please check your Order ID and phone number.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track My Order - Eranda Water</title>
</head>
<body>

    <h1>Eranda Water</h1>
    <h2>Track My Order</h2>

    <?php if ($error !== ""): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="track_order.php">

        <label for="order_id">Order ID:</label><br>

        <input
            type="number"
            id="order_id"
            name="order_id"
            min="1"
        
            required
        >

        <br><br>

        <label for="phone">Phone Number:</label><br>

        <input
            type="tel"
            id="phone"
            name="phone"
            pattern="[0-9]{10}"
            minlength="10"
            maxlength="10"
            inputmode="numeric"
        
            required
        >

        <br><br>

        <button type="submit">Track Order</button>

    </form>

    <?php if ($order): ?>

        <hr>

        <h3>Order Details</h3>

        <p>
            <strong>Order ID:</strong>
            <?php echo (int)$order["order_id"]; ?>
        </p>

        <p>
            <strong>Order Date:</strong>
            <?php echo htmlspecialchars($order["order_date"]); ?>
        </p>

        <p>
            <strong>Status:</strong>
            <?php echo htmlspecialchars($order["order_status"]); ?>
        </p>

        <p>
            <strong>Total Amount:</strong>
            Rs. <?php echo number_format((float)$order["total_amount"], 2); ?>
        </p>

        <p>
            <strong>Delivery Address:</strong>
            <?php echo htmlspecialchars($order["delivery_address"]); ?>
        </p>

    <?php endif; ?>

    <p>
        <a href="order.php">Place a Quick Order</a>
    </p>

    <p>
        <a href="customer_login.php">Customer Login</a>
    </p>

</body>
</html>