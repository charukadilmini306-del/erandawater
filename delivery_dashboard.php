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

$delivery_person_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"];
// Get assigned deliveries
$sql = "SELECT 
            d.delivery_id,
            d.order_id,
            d.delivery_date,
            d.delivery_status,
            o.delivery_address,
            o.total_amount,
            c.full_name AS customer_name,
            c.phone AS customer_phone
        FROM deliveries d
        INNER JOIN orders o ON d.order_id = o.order_id
        INNER JOIN customers c ON o.customer_id = c.customer_id
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
        Welcome, <?php echo htmlspecialchars($full_name); ?>!
    </h3>

    <p>You are logged in as a Delivery Person.</p>

    <h3>My Assigned Deliveries</h3>

    <?php if ($result->num_rows > 0): ?>

    <table border="1" cellpadding="10">

        <tr>
            <th>Order ID</th>
            <th>Customer Name</th>
            <th>Phone</th>
            <th>Delivery Address</th>
            <th>Delivery Date</th>
            <th>Status</th>
            <th>Amount</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()): ?>

        <tr>
            <td><?php echo $row["order_id"]; ?></td>

            <td><?php echo htmlspecialchars($row["customer_name"]); ?></td>

            <td><?php echo htmlspecialchars($row["customer_phone"]); ?></td>

            <td><?php echo htmlspecialchars($row["delivery_address"]); ?></td>

            <td><?php echo htmlspecialchars($row["delivery_date"] ?? "Not Set"); ?></td>

            <td><?php echo htmlspecialchars($row["delivery_status"]); ?></td>

            <td><?php echo number_format($row["total_amount"], 2); ?></td>
        </tr>

        <?php endwhile; ?>

    </table>

<?php else: ?>

    <p>No deliveries assigned to you yet.</p>

<?php endif; ?>

<?php $stmt->close(); ?>

    <a href="logout.php">Logout</a>

</body>
</html>