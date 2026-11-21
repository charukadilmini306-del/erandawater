<?php

session_start();
require_once "config/database.php";

// Only Admin and Staff can access Order Management.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff"], true)
) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION["role"];
$message = "";
$error = "";

// Create a CSRF token.
if (empty($_SESSION["order_status_csrf"])) {
    $_SESSION["order_status_csrf"] = bin2hex(random_bytes(32));
}

// Update order status.
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {
    $order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
    $new_status = $_POST["order_status"] ?? "";
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["order_status_csrf"], $csrf_token)
    ) {
        $error = "Invalid security token. Please try again.";
    } elseif (!$order_id) {
        $error = "Invalid order ID.";
    } else {
        try {
            $conn->begin_transaction();

            // Lock the order while checking and updating it.
            $stmt = $conn->prepare(
                "SELECT order_status
                 FROM orders
                 WHERE order_id = ?
                 FOR UPDATE"
            );
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $current_status = $order["order_status"];

            // Delivered and Cancelled are final statuses.
            if (in_array($current_status, ["Delivered", "Cancelled"], true)) {
                throw new Exception(
                    "Delivered or cancelled orders cannot be changed."
                );
            }

            // Staff can only move Pending orders to Processing.
            if ($user_role === "staff") {
                if (
                    $current_status !== "Pending" ||
                    $new_status !== "Processing"
                ) {
                    throw new Exception(
                        "Staff can only change Pending orders to Processing."
                    );
                }
            }

            // Admin can process or cancel orders.
            if ($user_role === "admin") {
                $admin_transitions = [
                    "Pending" => ["Processing", "Cancelled"],
                    "Processing" => ["Cancelled"]
                ];

                if (
                    !isset($admin_transitions[$current_status]) ||
                    !in_array(
                        $new_status,
                        $admin_transitions[$current_status],
                        true
                    )
                ) {
                    throw new Exception(
                        "Invalid status change. Delivery status must be updated by the assigned delivery person."
                    );
                }
            }

            // Restore product stock if the order is cancelled.
            if ($new_status === "Cancelled") {
                $stmt = $conn->prepare(
                    "SELECT product_id, quantity
                     FROM order_items
                     WHERE order_id = ?"
                );
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $items_result = $stmt->get_result();

                $items = [];
                while ($item = $items_result->fetch_assoc()) {
                    $items[] = $item;
                }
                $stmt->close();

                foreach ($items as $item) {
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

            // Save the new order status.
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET order_status = ?
                 WHERE order_id = ?"
            );
            $stmt->bind_param("si", $new_status, $order_id);
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

// Retrieve all orders and customer details.
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eranda Water - Manage Orders</title>
</head>
<body>

    <h1>Eranda Water</h1>
    <h2>Order Management</h2>

    <p>
        <?php if ($user_role === "admin"): ?>
            <a href="admin_dashboard.php">Back to Dashboard</a>
        <?php else: ?>
            <a href="dashboard.php">Back to Dashboard</a>
        <?php endif; ?>
    </p>

    <?php if ($message !== ""): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
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
                <?php echo number_format((float)$order["total_amount"], 2); ?>
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
                    $item_stmt = $conn->prepare(
                        "SELECT
                            p.product_name,
                            oi.quantity,
                            oi.unit_price
                         FROM order_items oi
                         INNER JOIN products p
                            ON oi.product_id = p.product_id
                         WHERE oi.order_id = ?"
                    );

                    $item_stmt->bind_param("i", $order["order_id"]);
                    $item_stmt->execute();
                    $item_result = $item_stmt->get_result();
                    ?>

                    <?php while ($item = $item_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($item["product_name"]); ?>
                            </td>
                            <td><?php echo (int)$item["quantity"]; ?></td>
                            <td>
                                Rs.
                                <?php echo number_format((float)$item["unit_price"], 2); ?>
                            </td>
                            <td>
                                Rs.
                                <?php
                                echo number_format(
                                    (float)$item["quantity"] *
                                    (float)$item["unit_price"],
                                    2
                                );
                                ?>
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

                <?php
                $available_statuses = [];

                if ($user_role === "staff" && $order["order_status"] === "Pending") {
                    $available_statuses = ["Processing"];
                } elseif ($user_role === "admin") {
                    if ($order["order_status"] === "Pending") {
                        $available_statuses = ["Processing", "Cancelled"];
                    } elseif ($order["order_status"] === "Processing") {
                        $available_statuses = ["Cancelled"];
                    }
                }
                ?>

                <?php if (!empty($available_statuses)): ?>
                    <form method="POST" action="manage_orders.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION["order_status_csrf"]); ?>"
                        >

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?php echo (int)$order["order_id"]; ?>"
                        >

                        <select name="order_status" required>
                            <option value="">Select Status</option>

                            <?php foreach ($available_statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>">
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
                        Delivery status is updated by the assigned delivery person.
                    </p>
                <?php endif; ?>

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
