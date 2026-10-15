<?php

session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

// Update order status
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_status"])) {

    $order_id = filter_input(
        INPUT_POST,
        "order_id",
        FILTER_VALIDATE_INT
    );

    $new_status = $_POST["order_status"] ?? "";

    $allowed_statuses = [
        "Pending",
        "Processing",
        "Out for Delivery",
        "Delivered",
        "Cancelled"
    ];

    if (!$order_id || !in_array(
        $new_status,
        $allowed_statuses,
        true
    )) {
        $error = "Invalid order details.";
    } else {

        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare(
                "SELECT order_status
                 FROM orders
                 WHERE order_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $order = $result->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $current_status = $order["order_status"];

            // Do not change a completed or cancelled order
            if (
                $current_status === "Delivered" ||
                $current_status === "Cancelled"
            ) {
                throw new Exception(
                    "Delivered or cancelled orders cannot be changed."
                );
            }

            // Restore stock when an order is cancelled
            if ($new_status === "Cancelled") {

                $stmt = $conn->prepare(
                    "SELECT product_id, quantity
                     FROM order_items
                     WHERE order_id = ?"
                );

                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                $items = $stmt->get_result();

                $stock_items = [];

                while ($item = $items->fetch_assoc()) {
                    $stock_items[] = $item;
                }

                $stmt->close();

                foreach ($stock_items as $item) {

                    $stmt = $conn->prepare(
                        "UPDATE products
                         SET stock_quantity = stock_quantity + ?
                         WHERE product_id = ?"
                    );

                    $stmt->bind_param(
                        "ii",
                        $item["quantity"],
                        $item["product_id"]
                    );

                    $stmt->execute();
                    $stmt->close();
                }
            }
            // Update order status
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET order_status = ?
                 WHERE order_id = ?"
            );

            $stmt->bind_param(
                "si",
                $new_status,
                $order_id
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $message = "Order status updated successfully.";

        } catch (Throwable $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
}

// Retrieve orders and customer details
$sql = "
    SELECT
        o.order_id,
        o.customer_id,
        o.order_date,
        o.delivery_address,
        o.order_status,
        o.total_amount,
        c.full_name,
        c.phone
    FROM orders o
    INNER JOIN customers c
        ON o.customer_id = c.customer_id
    ORDER BY o.order_id DESC
";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Eranda Water - Manage Orders</title>
</head>

<body>

    <h1>Eranda Water</h1>

    <h2>Order Management</h2>

    <p>
        <a href="dashboard.php">Back to Dashboard</a>
    </p>

    <?php if ($message !== ""): ?>
        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p>
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($order = $result->fetch_assoc()): ?>

            <hr>

            <h3>
                Order ID:
                <?php echo (int)$order["order_id"]; ?>
            </h3>

            <p>
                <strong>Customer:</strong>
                <?php echo htmlspecialchars($order["full_name"]); ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?php echo htmlspecialchars($order["phone"]); ?>
            </p>

            <p>
                <strong>Delivery Address:</strong>
                <?php echo htmlspecialchars($order["delivery_address"]); ?>
            </p>

            <p>
                <strong>Order Date:</strong>
                <?php echo htmlspecialchars($order["order_date"]); ?>
            </p>

            <p>
                <strong>Total Amount:</strong>
                Rs.
                <?php echo number_format(
                    (float)$order["total_amount"],
                    2
                ); ?>
            </p>

            <p>
                <strong>Current Status:</strong>
                <?php echo htmlspecialchars($order["order_status"]); ?>
            </p>

            <h4>Ordered Products</h4>

            <table border="1" cellpadding="8">

                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                <?php

                $item_stmt = $conn->prepare("
                    SELECT
                        p.product_name,
                        oi.quantity,
                        oi.unit_price
                    FROM order_items oi
                    INNER JOIN products p
                        ON oi.product_id = p.product_id
                    WHERE oi.order_id = ?
                ");

                $item_stmt->bind_param(
                    "i",
                    $order["order_id"]
                );

                $item_stmt->execute();

                $item_result = $item_stmt->get_result();

                ?>

                <?php while ($item = $item_result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo htmlspecialchars(
                                $item["product_name"]
                            ); ?>
                            </td>

                            <td>
                                <?php echo (int)$item["quantity"]; ?>
                        </td>

                        <td>
                            Rs.
                            <?php echo number_format(
                                (float)$item["unit_price"],
                                2
                            ); ?>
                        </td>

                        <td>
                            Rs.
                            <?php echo number_format(
                                (float)$item["quantity"]
                                * (float)$item["unit_price"],
                                2
                            ); ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

            <?php $item_stmt->close(); ?>

            <h4>Update Order Status</h4>

            <?php
            $is_final_status = in_array(
                $order["order_status"],
                ["Delivered", "Cancelled"],
                true
            );
            ?>

            <?php if (!$is_final_status): ?>

                <form method="POST"
                      action="manage_orders.php">

                    <input
                        type="hidden"
                        name="order_id"
                        value="<?php echo (int)$order["order_id"]; ?>"
                    >

                    <select name="order_status" required>

                        <?php
                        $statuses = [
                            "Pending",
                            "Processing",
                            "Out for Delivery",
                            "Delivered",
                            "Cancelled"
                        ];
                        ?>
                        <?php foreach ($statuses as $status): ?>

                            <option
                                value="<?php echo htmlspecialchars($status); ?>"
                                <?php
                                echo (
                                    $order["order_status"] === $status
                                ) ? "selected" : "";
                                ?>
                            >
                                <?php echo htmlspecialchars($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button
                        type="submit"
                        name="update_status"
                        value="1"
                    >
                        Update Status
                    </button>

                </form>

            <?php else: ?>

                <p>
                    This order is already
                    <?php echo htmlspecialchars($order["order_status"]); ?>.
                </p>

            <?php endif; ?>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No orders found.</p>

    <?php endif; ?>

</body>
</html>