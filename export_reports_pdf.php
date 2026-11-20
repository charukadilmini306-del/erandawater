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

// Escape output
function pdfEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Get filters
$report_type = $_GET["report_type"] ?? "orders";
$from_date = $_GET["from_date"] ?? date("Y-m-01");
$to_date = $_GET["to_date"] ?? date("Y-m-d");

// Validate report type
$allowed_reports = [
    "orders",
    "sales",
    "payments",
    "stock",
    "maintenance",
    "delivery"
];

if (!in_array($report_type, $allowed_reports, true)) {
    exit("Invalid report type.");
}

// Validate dates
$date_pattern = '/^\d{4}-\d{2}-\d{2}$/';

$from_check = DateTime::createFromFormat("!Y-m-d", $from_date);
$to_check = DateTime::createFromFormat("!Y-m-d", $to_date);

if (
    !preg_match($date_pattern, $from_date) ||
    !preg_match($date_pattern, $to_date) ||
    !$from_check ||
    $from_check->format("Y-m-d") !== $from_date ||
    !$to_check ||
    $to_check->format("Y-m-d") !== $to_date ||
    $from_date > $to_date
) {
    exit("Invalid date range.");
}

// Report titles
$report_titles = [
    "orders" => "Orders Report",
    "sales" => "Sales Report",
    "payments" => "Payments Report",
    "stock" => "Stock Report",
    "maintenance" => "Maintenance Report",
    "delivery" => "Delivery Report"
];

$report_title = $report_titles[$report_type];

$summary = [];
$details_result = null;

// ------------------------------------
// ORDERS REPORT
// ------------------------------------

if ($report_type === "orders") {

    $summary_sql = "
        SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(order_status = 'Pending'), 0) AS pending_orders,
            COALESCE(SUM(order_status = 'Processing'), 0) AS processing_orders,
            COALESCE(SUM(order_status = 'Out for Delivery'), 0) AS out_for_delivery,
            COALESCE(SUM(order_status = 'Delivered'), 0) AS delivered_orders,
            COALESCE(SUM(order_status = 'Cancelled'), 0) AS cancelled_orders
        FROM orders
        WHERE order_date >= ?
          AND order_date < DATE_ADD(?, INTERVAL 1 DAY)
    ";

    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $details_sql = "
        SELECT
            o.order_id,
            c.full_name AS customer_name,
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

    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $details_result = $stmt->get_result();
}

// ------------------------------------
// SALES REPORT
// ------------------------------------

elseif ($report_type === "sales") {

    $summary_sql = "
        SELECT
            COUNT(*) AS total_sales_orders,
            COALESCE(SUM(total_amount), 0) AS total_sales,
            COALESCE(AVG(total_amount), 0) AS average_sales
        FROM orders o
        INNER JOIN (
            SELECT
                order_id,
                MAX(delivered_at) AS delivered_at
            FROM deliveries
            WHERE delivered_at IS NOT NULL
            GROUP BY order_id
        ) d ON o.order_id = d.order_id
        WHERE o.order_status = 'Delivered'
          AND d.delivered_at >= ?
          AND d.delivered_at < DATE_ADD(?, INTERVAL 1 DAY)
    ";

    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $details_sql = "
        SELECT
            o.order_id,
            c.full_name AS customer_name,
            d.delivered_at,
            o.total_amount
        FROM orders o
        INNER JOIN customers c
            ON o.customer_id = c.customer_id
        INNER JOIN (
            SELECT
                order_id,
                MAX(delivered_at) AS delivered_at
            FROM deliveries
            WHERE delivered_at IS NOT NULL
            GROUP BY order_id
        ) d ON o.order_id = d.order_id
        WHERE o.order_status = 'Delivered'
          AND d.delivered_at >= ?
          AND d.delivered_at < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY d.delivered_at DESC
    ";

    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $details_result = $stmt->get_result();
}

// ------------------------------------
// PAYMENTS REPORT
// ------------------------------------

elseif ($report_type === "payments") {

    $summary_sql = "
        SELECT
            COUNT(*) AS total_payments,
            COALESCE(SUM(amount), 0) AS total_paid_amount,
            COALESCE(AVG(amount), 0) AS average_payment
        FROM payments
        WHERE payment_status = 'Paid'
          AND payment_date >= ?
          AND payment_date < DATE_ADD(?, INTERVAL 1 DAY)
    ";

    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $details_sql = "
        SELECT
            p.payment_id,
            p.order_id,
            c.full_name AS customer_name,
            p.payment_date,
            p.amount,
            p.payment_method,
            p.payment_status
        FROM payments p
        INNER JOIN orders o
            ON p.order_id = o.order_id
        INNER JOIN customers c
            ON o.customer_id = c.customer_id
        WHERE p.payment_status = 'Paid'
          AND p.payment_date >= ?
          AND p.payment_date < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY p.payment_date DESC
    ";

    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $details_result = $stmt->get_result();
}

// ------------------------------------
// STOCK REPORT
// ------------------------------------

elseif ($report_type === "stock") {

    $summary_sql = "
        SELECT
            COUNT(*) AS total_products,
            COALESCE(SUM(
                COALESCE(s.quantity, p.stock_quantity, 0)
            ), 0) AS total_stock,
            COALESCE(SUM(
                CASE
                    WHEN COALESCE(s.quantity, p.stock_quantity, 0)
                         <= COALESCE(s.minimum_level, 10)
                    THEN 1 ELSE 0
                END
            ), 0) AS low_stock_products,
            COALESCE(SUM(
                p.price * COALESCE(s.quantity, p.stock_quantity, 0)
            ), 0) AS total_stock_value,
            COALESCE(SUM(
                CASE WHEN s.product_id IS NULL
                     THEN 1 ELSE 0
                END
            ), 0) AS missing_stock_records
        FROM products p
        LEFT JOIN (
            SELECT
                product_id,
                SUM(quantity) AS quantity,
                MIN(minimum_level) AS minimum_level
            FROM stock
            GROUP BY product_id
        ) s ON p.product_id = s.product_id
    ";

    $result = $conn->query($summary_sql);
    $summary = $result->fetch_assoc();

    $details_sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.price,
            p.stock_quantity AS product_stock,
            COALESCE(s.quantity, p.stock_quantity, 0) AS current_stock,
            COALESCE(s.minimum_level, 10) AS minimum_level,
            CASE
                WHEN COALESCE(s.quantity, p.stock_quantity, 0)
                     <= COALESCE(s.minimum_level, 10)
                THEN 'Low Stock'
                ELSE 'Available Stock'
            END AS stock_status,
            p.status AS product_status
        FROM products p
        LEFT JOIN (
            SELECT
                product_id,
                SUM(quantity) AS quantity,
                MIN(minimum_level) AS minimum_level
            FROM stock
            GROUP BY product_id
        ) s ON p.product_id = s.product_id
        ORDER BY p.product_name ASC
    ";

    $details_result = $conn->query($details_sql);
}

// ------------------------------------
// MAINTENANCE REPORT
// ------------------------------------

elseif ($report_type === "maintenance") {

    $summary_sql = "
        SELECT
            COUNT(*) AS total_maintenance,
            COALESCE(SUM(
                LOWER(status) IN ('completed', 'complete')
            ), 0) AS completed_maintenance,
            COALESCE(SUM(
                LOWER(status) = 'pending'
            ), 0) AS pending_maintenance,
            COALESCE(SUM(
                next_due_date < CURDATE()
                AND LOWER(status) NOT IN ('completed', 'complete')
            ), 0) AS overdue_maintenance
        FROM maintenance
        WHERE maintenance_date >= ?
          AND maintenance_date <= ?
    ";

    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $details_sql = "
        SELECT
            maintenance_id,
            maintenance_type,
            description,
            maintenance_date,
            next_due_date,
            status,
            CASE
                WHEN next_due_date < CURDATE()
                     AND LOWER(status) NOT IN ('completed', 'complete')
                THEN 'Overdue'
                ELSE 'Not Overdue'
            END AS due_status
        FROM maintenance
        WHERE maintenance_date >= ?
          AND maintenance_date <= ?
        ORDER BY maintenance_date DESC
    ";

    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $details_result = $stmt->get_result();
}

// ------------------------------------
// DELIVERY REPORT
// ------------------------------------

elseif ($report_type === "delivery") {

    $summary_sql = "
        SELECT
            COUNT(*) AS total_deliveries,
            COALESCE(SUM(delivery_status = 'Pending'), 0) AS pending_deliveries,
            COALESCE(SUM(delivered_at IS NOT NULL), 0) AS completed_deliveries,
            COALESCE(SUM(delivery_person_id IS NOT NULL), 0) AS assigned_deliveries,
            COALESCE(SUM(
                collection_status = 'Collected'
                OR collection_status LIKE 'Collected %'
            ), 0) AS collected_deliveries,
            COALESCE(SUM(
                collection_status LIKE 'Partially%'
            ), 0) AS partially_collected
        FROM deliveries
        WHERE COALESCE(delivered_at, delivery_date) >= ?
          AND COALESCE(delivered_at, delivery_date)
              < DATE_ADD(?, INTERVAL 1 DAY)
    ";

    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $details_sql = "
        SELECT
            d.delivery_id,
            d.order_id,
            c.full_name AS customer_name,
            d.delivery_person,
            d.delivery_date,
            d.delivered_at,
            d.delivery_status,
            d.collection_status,
            d.collection_note
        FROM deliveries d
        INNER JOIN orders o
            ON d.order_id = o.order_id
        INNER JOIN customers c
            ON o.customer_id = c.customer_id
        WHERE COALESCE(d.delivered_at, d.delivery_date) >= ?
          AND COALESCE(d.delivered_at, d.delivery_date)
              < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY d.delivery_id DESC
    ";

    $stmt = $conn->prepare($details_sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $details_result = $stmt->get_result();
}

// ------------------------------------
// GENERATE PDF HTML
// ------------------------------------

ob_start();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        h2, h3, p {
            text-align: center;
        }

        h2 {
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }

        th {
            background-color: #eeeeee;
        }

        .summary {
            margin-top: 20px;
        }

        .summary-table th {
            background-color: #dddddd;
        }
    </style>
</head>

<body>

<h2>Eranda Water</h2>

<h3><?php echo pdfEscape($report_title); ?></h3>

<?php if ($report_type !== "stock"): ?>
    <p>
        Report Period:
        <?php echo pdfEscape($from_date); ?>
        to
        <?php echo pdfEscape($to_date); ?>
    </p>
<?php else: ?>
    <p>Current Stock Summary</p>
<?php endif; ?>

<h3 class="summary">Summary</h3>

<table class="summary-table">
    <tr>
        <?php foreach ($summary as $label => $value): ?>
            <th>
                <?php echo pdfEscape(
                    ucwords(str_replace("_", " ", $label))
                ); ?>
            </th>
        <?php endforeach; ?>
    </tr>

    <tr>
        <?php foreach ($summary as $label => $value): ?>
            <td>
                <?php
                if (in_array($label, [
                    "total_sales",
                    "average_sales",
                    "total_paid_amount",
                    "average_payment",
                    "total_stock_value"
                ], true)) {
                    echo "Rs. " . number_format((float)$value, 2);
                } else {
                    echo number_format((float)$value);
                }
                ?>
            </td>
        <?php endforeach; ?>
    </tr>
</table>

<h3>Details</h3>

<table>
    <tr>
        <?php
        if ($details_result && $details_result->num_rows > 0) {
            $first_row = $details_result->fetch_assoc();

            foreach (array_keys($first_row) as $column) {
                echo "<th>" . pdfEscape(
                    ucwords(str_replace("_", " ", $column))
                ) . "</th>";
            }

            echo "</tr><tr>";

            foreach ($first_row as $column => $value) {
                if (
                    in_array($column, [
                        "total_amount",
                        "amount",
                        "price"
                    ], true)
                ) {
                    echo "<td>Rs. " .
                        number_format((float)$value, 2) .
                        "</td>";
                } else {
                    echo "<td>" . pdfEscape($value) . "</td>";
                }
            }

            echo "</tr>";

            while ($row = $details_result->fetch_assoc()) {
                echo "<tr>";

                foreach ($row as $column => $value) {
                    if (
                        in_array($column, [
                            "total_amount",
                            "amount",
                            "price"
                        ], true)
                    ) {
                        echo "<td>Rs. " .
                            number_format((float)$value, 2) .
                            "</td>";
                    } else {
                        echo "<td>" . pdfEscape($value) . "</td>";
                    }
                }

                echo "</tr>";
            }
        } else {
            echo '<th>No records found</th></tr>';
            echo '<tr><td>No records found for this report.</td></tr>';
        }
        ?>
    </tr>
</table>

</body>
</html>

<?php

$html = ob_get_clean();

// Generate PDF
$dompdf = new \Dompdf\Dompdf();

$dompdf->loadHtml($html, "UTF-8");

$dompdf->setPaper("A4", "landscape");

$dompdf->render();

// Download PDF
$filename = "Eranda_Water_" .
    str_replace(" ", "_", $report_title) . ".pdf";

$dompdf->stream(
    $filename,
    ["Attachment" => true]
);

exit();