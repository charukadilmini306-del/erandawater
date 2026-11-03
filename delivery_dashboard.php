<?php
session_start();

require_once "config/database.php";

// Check login and delivery role
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "delivery"
) {
    header("Location: login.php");
    exit();
}

$delivery_person_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"];

$message = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Handle delivery status update
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {

    $delivery_id = intval($_POST["delivery_id"] ?? 0);
    $new_status = $_POST["new_status"] ?? "";
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $csrf_token)) {

        $message = "Invalid request. Please try again.";

    } else {

        if ($new_status === "Out for Delivery") {
            $current_status = "Pending";

        } elseif ($new_status === "Delivered") {
            $current_status = "Out for Delivery";

        } else {
            $current_status = "";
        }

        if ($current_status === "") {

            $message = "Invalid delivery status.";

        } else {

            try {

                $conn->begin_transaction();

                // Verify assigned delivery and lock the order
                $check = $conn->prepare(
                    "SELECT d.order_id
                     FROM deliveries d
                     INNER JOIN orders o
                        ON d.order_id = o.order_id
                     WHERE d.delivery_id = ?
                     AND d.delivery_person_id = ?
                     AND d.delivery_status = ?
                     AND o.order_status != 'Cancelled'
                     FOR UPDATE"
                );

                $check->bind_param(
                    "iis",
                    $delivery_id,
                    $delivery_person_id,
                    $current_status
                );

                $check->execute();

                $check_result = $check->get_result();
                $delivery = $check_result->fetch_assoc();

                $check->close();

                if (!$delivery) {
                    throw new Exception(
                        "Delivery not found or status cannot be changed."
                    );
                }

                $order_id = (int) $delivery["order_id"];

                // Update deliveries table
                $update_delivery = $conn->prepare(
                    "UPDATE deliveries
                     SET delivery_status = ?,
                         delivered_at = CASE
                             WHEN ? = 'Delivered' THEN NOW()
                             ELSE delivered_at
                         END
                     WHERE delivery_id = ?
                     AND delivery_person_id = ?
                     AND delivery_status = ?"
                );

                $update_delivery->bind_param(
                    "ssiis",
                    $new_status,
                    $new_status,
                    $delivery_id,
                    $delivery_person_id,
                    $current_status
                );

                $update_delivery->execute();

                if ($update_delivery->affected_rows !== 1) {

                    $update_delivery->close();

                    throw new Exception("Delivery update failed.");
                }

                $update_delivery->close();

                // Update orders table
                $update_order = $conn->prepare(
                    "UPDATE orders
                     SET order_status = ?
                     WHERE order_id = ?
                     AND order_status != 'Cancelled'"
                );

                $update_order->bind_param(
                    "si",
                    $new_status,
                    $order_id
                );

                $update_order->execute();

                if ($update_order->affected_rows !== 1) {
                    $update_order->close();

                    throw new Exception("Order update failed.");
                }

                $update_order->close();

                // Save both updates together
                $conn->commit();

                $message = "Delivery and order status updated successfully!";

            } catch (Throwable $e) {

                $conn->rollback();

                $message = "Status update failed. Please try again.";
            }
        }
    }
}
// Get assigned deliveries
$sql = "SELECT
            d.delivery_id,
            d.order_id,
            d.delivery_date,
            d.delivery_status,
            d.delivered_at,
            o.delivery_address,
            o.total_amount,
            c.full_name AS customer_name,
            c.phone AS customer_phone
        FROM deliveries d
        INNER JOIN orders o
            ON d.order_id = o.order_id
        INNER JOIN customers c
            ON o.customer_id = c.customer_id
        WHERE d.delivery_person_id = ?
        ORDER BY d.delivery_date DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $delivery_person_id);

$stmt->execute();

$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html>

<head>
    <title>Delivery Person Dashboard</title>
</head>

<body>

    <h1>Eranda Water Management System</h1>

    <h2>Delivery Dashboard</h2>

    <h3>
        Welcome,
        <?php echo htmlspecialchars($full_name); ?>!
    </h3>

    <p>You are logged in as a Delivery Person.</p>

    <h3>My Assigned Deliveries</h3>

    <!-- Display message -->
    <?php if (!empty($message)): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>

        <table border="1" cellpadding="10">
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Delivery Address</th>
                <th>Delivery Date</th>
                <th>Status</th>
                <th>Delivered At</th>
                <th>Amount</th>
                <th>Action</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?php echo $row["order_id"]; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["customer_name"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["customer_phone"]); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($row["delivery_address"]); ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $row["delivery_date"] ?? "Not Set"
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["delivery_status"]); ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $row["delivered_at"] ?? "Not Delivered"
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo number_format(
                            (float) $row["total_amount"],
                            2
                        );
                        ?>
                    </td>
                    <td>

                        <?php if ($row["delivery_status"] === "Pending"): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="delivery_id"
                                    value="<?php echo (int) $row["delivery_id"]; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="new_status"
                                    value="Out for Delivery"
                                >

                                <button
                                    type="submit"
                                    name="update_status"
                                >
                                    Start Delivery
                                </button>

                            </form>
                            <?php elseif ($row["delivery_status"] === "Out for Delivery"): ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="delivery_id"
                                    value="<?php echo (int) $row["delivery_id"]; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="new_status"
                                    value="Delivered"
                                >

                                <button
                                    type="submit"
                                    name="update_status"
                                >
                                    Mark as Delivered
                                </button>

                            </form>
                            <?php elseif ($row["delivery_status"] === "Delivered"): ?>

                            <strong>Completed</strong>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endwhile; ?>

        </table>

    <?php else: ?>

        <p>No deliveries assigned to you yet.</p>

    <?php endif; ?>

    <?php $stmt->close(); ?>

    <br>

    <a href="logout.php">Logout</a>

</body>

</html>