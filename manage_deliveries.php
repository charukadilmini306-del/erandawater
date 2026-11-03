<?php
session_start();

require_once "config/database.php";

// Allow Admin and Staff only
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff"], true)
) {
    header("Location: login.php");
    exit();
}

$message = "";
// Handle Delivery Assignment
if (isset($_POST["assign_delivery"])) {

    $order_id = intval($_POST["order_id"]);
    $delivery_person_id = intval($_POST["delivery_person_id"]);
    $delivery_date = $_POST["delivery_date"];

    // Check whether the selected order exists
    $order_check = $conn->prepare(
        "SELECT order_id FROM orders
         WHERE order_id = ? AND order_status != 'Cancelled'"
    );

    $order_check->bind_param("i", $order_id);
    $order_check->execute();
    $order_result_check = $order_check->get_result();

    // Check whether the selected delivery person exists
    $person_check = $conn->prepare(
        "SELECT full_name FROM users
         WHERE user_id = ? AND role = 'delivery'"
    );

    $person_check->bind_param("i", $delivery_person_id);
    $person_check->execute();
    $person_result_check = $person_check->get_result();

    if ($order_result_check->num_rows === 0) {

        $message = "Invalid or cancelled order.";

    } elseif ($person_result_check->num_rows === 0) {

        $message = "Invalid delivery person.";

    } else {

        $person = $person_result_check->fetch_assoc();
        $delivery_person = $person["full_name"];

        // Check whether order is already assigned
        $duplicate_check = $conn->prepare(
            "SELECT delivery_id FROM deliveries
             WHERE order_id = ?"
        );

        $duplicate_check->bind_param("i", $order_id);
        $duplicate_check->execute();

        $duplicate_result = $duplicate_check->get_result();

        if ($duplicate_result->num_rows > 0) {

            $message = "This order is already assigned.";

        } else {

            // Save delivery assignment
            $insert = $conn->prepare(
    "INSERT INTO deliveries
    (order_id, delivery_person_id, delivery_person,
     delivery_date, delivery_status)
    VALUES (?, ?, ?, ?, 'Pending')"
);

$insert->bind_param(
    "iiss",
    $order_id,
    $delivery_person_id,
    $delivery_person,
    $delivery_date
);

            if ($insert->execute()) {

                $message = "Delivery assigned successfully!";

            } else {

                $message = "Error assigning delivery: " . $insert->error;

            }

            $insert->close();
        }

        $duplicate_check->close();
    }

    $order_check->close();
    $person_check->close();
}

// Get all delivery persons
$sql = "SELECT user_id, full_name
        FROM users
        WHERE role = 'delivery'
        ORDER BY full_name ASC";

$delivery_result = $conn->query($sql);

// Get orders that are not assigned yet
$sql = "SELECT
            o.order_id,
            o.delivery_address,
            o.order_date,
            c.full_name AS customer_name
        FROM orders o
        INNER JOIN customers c
            ON o.customer_id = c.customer_id
        WHERE NOT EXISTS (
            SELECT 1
            FROM deliveries d
            WHERE d.order_id = o.order_id
        )
        AND o.order_status != 'Cancelled'
        ORDER BY o.order_id DESC";

$order_result = $conn->query($sql);
    // Get all assigned deliveries
$assigned_sql = "
    SELECT
        d.delivery_id,
        d.order_id,
        d.delivery_date,
        d.delivery_status,
        d.delivered_at,
        COALESCE(u.full_name, d.delivery_person, 'Not Assigned')
            AS delivery_person_name,
        c.full_name AS customer_name,
        o.delivery_address
    FROM deliveries d
    INNER JOIN orders o
        ON d.order_id = o.order_id
    INNER JOIN customers c
        ON o.customer_id = c.customer_id
    LEFT JOIN users u
        ON d.delivery_person_id = u.user_id
    ORDER BY d.delivery_id DESC
";

$assigned_result = $conn->query($assigned_sql);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Deliveries - Eranda Water</title>
</head>

<body>

    <h1>Eranda Water Management System</h1>

    <h2>Manage Deliveries</h2>

    <p>
        Welcome,
        <?php echo htmlspecialchars($_SESSION["full_name"]); ?>
    </p>

    <h3>Assign Delivery</h3>
        <?php if (!empty($message)): ?>
    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>
     <?php endif; ?>

    <form method="POST">

        <label>Select Order:</label><br>

        <select name="order_id" required>

            <option value="">Select Order</option>

            <?php while ($order = $order_result->fetch_assoc()): ?>

                <option value="<?php echo $order["order_id"]; ?>">

                    Order #<?php echo $order["order_id"]; ?>
                    -
                    <?php echo htmlspecialchars($order["customer_name"]); ?>

                </option>

            <?php endwhile; ?>

        </select>

        <br><br>

        <label>Select Delivery Person:</label><br>

        <select name="delivery_person_id" required>

            <option value="">Select Delivery Person</option>

            <?php while ($person = $delivery_result->fetch_assoc()): ?>

                <option value="<?php echo $person["user_id"]; ?>">

                    <?php echo htmlspecialchars($person["full_name"]); ?>

                </option>

            <?php endwhile; ?>

        </select>

        <br><br>

        <label>Delivery Date:</label><br>

        <input
            type="date"
            name="delivery_date"
            min="<?php echo date('Y-m-d'); ?>"
            required
        >

        <br><br>

        <button type="submit" name="assign_delivery">
            Assign Delivery
        </button>

    </form>

    <br>

    <a href="dashboard.php">Back to Dashboard</a>
    <hr>

<h3>All Assigned Deliveries</h3>

<?php if ($assigned_result && $assigned_result->num_rows > 0): ?>

<table border="1" cellpadding="10">

    <tr>
        <th>Delivery ID</th>
        <th>Order ID</th>
        <th>Customer</th>
        <th>Delivery Address</th>
        <th>Delivery Person</th>
        <th>Delivery Date</th>
        <th>Status</th>
        <th>Delivered At</th>
    </tr>

    <?php while ($delivery = $assigned_result->fetch_assoc()): ?>

    <tr>

        <td>
            <?php echo $delivery["delivery_id"]; ?>
        </td>

        <td>
            <?php echo $delivery["order_id"]; ?>
        </td>

        <td>
            <?php echo htmlspecialchars($delivery["customer_name"]); ?>
        </td>

        <td>
            <?php echo htmlspecialchars($delivery["delivery_address"]); ?>
        </td>

        <td>
            <?php echo htmlspecialchars($delivery["delivery_person_name"]); ?>
        </td>

        <td>
            <?php echo htmlspecialchars($delivery["delivery_date"] ?? "Not Set"); ?>
        </td>

        <td>
            <?php echo htmlspecialchars($delivery["delivery_status"]); ?>
        </td>

        <td>
            <?php echo htmlspecialchars($delivery["delivered_at"] ?? "Not Delivered"); ?>
        </td>

    </tr>

    <?php endwhile; ?>

</table>

<?php else: ?>

<p>No assigned deliveries found.</p>

<?php endif; ?>

    <br><br>

    <a href="logout.php">Logout</a>

</body>
</html>