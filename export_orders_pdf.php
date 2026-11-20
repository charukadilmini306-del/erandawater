<?php

session_start();

require_once "config/database.php";
require_once __DIR__ . "/vendor/autoload.php";

// Allow Admin and Staff only
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff"], true)
) {
    header("Location: login.php");
    exit();
}

// Get date filters
$from_date = $_GET["from_date"] ?? date("Y-m-01");
$to_date = $_GET["to_date"] ?? date("Y-m-d");

// Validate dates
$date_pattern = '/^\d{4}-\d{2}-\d{2}$/';

if (
    !preg_match($date_pattern, $from_date) ||
    !preg_match($date_pattern, $to_date) ||
    $from_date > $to_date
) {
    $from_date = date("Y-m-01");
    $to_date = date("Y-m-d");
}

// Get Order Summary
$summary_sql = "
    SELECT
        COUNT(*) AS total_orders,
        SUM(order_status = 'Pending') AS pending_orders,
        SUM(order_status = 'Processing') AS processing_orders,
        SUM(order_status = 'Out for Delivery') AS out_for_delivery,
        SUM(order_status = 'Delivered') AS delivered_orders,
        SUM(order_status = 'Cancelled') AS cancelled_orders
    FROM orders
    WHERE order_date >= ?
      AND order_date < DATE_ADD(?, INTERVAL 1 DAY)
";

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("ss", $from_date, $to_date);
$summary_stmt->execute();

$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();

$summary_stmt->close();

// Get Order Details
$orders_sql = "
    SELECT
        o.order_id,
        c.full_name,
        o.order_date,
        o.total_amount,
        o.order_status
    FROM orders o
    INNER JOIN customers c
        ON o.customer_id = c.customer_id
    WHERE o.order_date >= ?
      AND o.order_date < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY o.order_date DESC
";

$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("ss", $from_date, $to_date);
$orders_stmt->execute();

$orders_result = $orders_stmt->get_result();

// Start PDF HTML
ob_start();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        h2, p {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #333;
            padding: 7px;
            text-align: left;
        }

        th {
            background-color: #eeeeee;
        }

        .summary {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<h2>Eranda Water</h2>
<h3>Orders Report</h3>

<p>
    Report Period:
    <?php echo htmlspecialchars($from_date); ?>
    to
    <?php echo htmlspecialchars($to_date); ?>
</p>

<h3 class="summary">Order Summary</h3>

<table>
    <tr>
        <th>Total Orders</th>
        <th>Pending</th>
        <th>Processing</th>
        <th>Out for Delivery</th>
        <th>Delivered</th>
        <th>Cancelled</th>
    </tr>
    <tr>
        <td><?php echo (int)$summary["total_orders"]; ?></td>
        <td><?php echo (int)$summary["pending_orders"]; ?></td>
        <td><?php echo (int)$summary["processing_orders"]; ?></td>
        <td><?php echo (int)$summary["out_for_delivery"]; ?></td>
        <td><?php echo (int)$summary["delivered_orders"]; ?></td>
        <td><?php echo (int)$summary["cancelled_orders"]; ?></td>
    </tr>
</table>

<h3>Order Details</h3>

<table>
    <tr>
        <th>Order ID</th>
        <th>Customer Name</th>
        <th>Order Date</th>
        <th>Total Amount (Rs.)</th>
        <th>Order Status</th>
    </tr>

    <?php if ($orders_result->num_rows > 0): ?>

        <?php while ($order = $orders_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo (int)$order["order_id"]; ?></td>
                <td><?php echo htmlspecialchars($order["full_name"]); ?></td>
                <td><?php echo htmlspecialchars($order["order_date"]); ?></td>
                <td><?php echo number_format((float)$order["total_amount"], 2); ?></td>
                <td><?php echo htmlspecialchars($order["order_status"]); ?></td>
            </tr>
        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="5">No orders found for this date range.</td>
        </tr>

    <?php endif; ?>

</table>

</body>
</html>

<?php
$html = ob_get_clean();
// Generate PDF
$dompdf = new \Dompdf\Dompdf();

$dompdf->loadHtml($html, 'UTF-8');

$dompdf->setPaper('A4', 'landscape');

$dompdf->render();

// Download PDF
$dompdf->stream(
    "Eranda_Water_Orders_Report.pdf",
    ["Attachment" => true]
);

exit();