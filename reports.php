
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

// Escape output
function e($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Validate date
function isValidDate($date)
{
    $d = DateTime::createFromFormat("!Y-m-d", $date);

    return $d && $d->format("Y-m-d") === $date;
}

// Date Filters
$from_date = $_GET["from_date"] ?? date("Y-m-01");
$to_date = $_GET["to_date"] ?? date("Y-m-d");

// Validate Date Range
if (
    !isValidDate($from_date) ||
    !isValidDate($to_date) ||
    $from_date > $to_date
) {
    $from_date = date("Y-m-01");
    $to_date = date("Y-m-d");
}

// Report Type
$report_type = $_GET["report_type"] ?? "orders";

$allowed_reports = [
    "orders",
    "sales",
    "payments",
    "stock",
    "maintenance",
    "delivery"
];

if (!in_array($report_type, $allowed_reports, true)) {
    $report_type = "orders";
}

// Initialize report data
$summary = [];
$rows = [];
$columns = [];
$report_title = "";
$report_note = "";

// Common date condition
$start_datetime = $from_date . " 00:00:00";
$end_datetime = date(
    "Y-m-d H:i:s",
    strtotime($to_date . " +1 day")
);

// ==================================================
// 1. ORDERS REPORT
// ==================================================

if ($report_type === "orders") {

    $report_title = "Orders Report";

    // Orders Summary
    $sql = "
        SELECT
            COUNT(*) AS total_orders,

            COALESCE(SUM(order_status = 'Pending'), 0)
                AS pending_orders,

            COALESCE(SUM(order_status = 'Processing'), 0)
                AS processing_orders,

            COALESCE(SUM(order_status = 'Out for Delivery'), 0)
                AS out_for_delivery,

            COALESCE(SUM(order_status = 'Delivered'), 0)
                AS delivered_orders,

            COALESCE(SUM(order_status = 'Cancelled'), 0)
                AS cancelled_orders

        FROM orders

        WHERE order_date >= ?
          AND order_date < ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    // Orders Details
    $sql = "
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
          AND o.order_date < ?

        ORDER BY o.order_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $result = $stmt->get_result();

    $columns = [
        "Order ID" => "order_id",
        "Customer Name" => "full_name",
        "Order Date" => "order_date",
        "Total Amount (Rs.)" => "total_amount",
        "Status" => "order_status"
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

// ==================================================
// 2. SALES REPORT
// ==================================================

elseif ($report_type === "sales") {

    $report_title = "Sales Report";

    // Sales Summary
    $sql = "
        SELECT
            COUNT(*) AS total_sales_orders,

            COALESCE(SUM(o.total_amount), 0)
                AS total_sales

        FROM orders o

        INNER JOIN deliveries d
            ON o.order_id = d.order_id

        WHERE o.order_status = 'Delivered'
          AND d.delivered_at >= ?
          AND d.delivered_at < ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    // Sales Details
    $sql = "
        SELECT
            o.order_id,
            c.full_name,
            d.delivered_at,
            o.total_amount

        FROM orders o

        INNER JOIN customers c
            ON o.customer_id = c.customer_id

        INNER JOIN deliveries d
            ON o.order_id = d.order_id

        WHERE o.order_status = 'Delivered'
          AND d.delivered_at >= ?
          AND d.delivered_at < ?

        ORDER BY d.delivered_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $result = $stmt->get_result();

    $columns = [
        "Order ID" => "order_id",
        "Customer Name" => "full_name",
        "Delivered Date" => "delivered_at",
        "Sales Amount (Rs.)" => "total_amount"
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

// ==================================================
// 3. PAYMENTS REPORT
// ==================================================

elseif ($report_type === "payments") {

    $report_title = "Payments Report";

    // Payment Summary
    $sql = "
        SELECT
            COUNT(*) AS total_payments,

            COALESCE(SUM(amount), 0)
                AS total_paid_amount,

            COALESCE(AVG(amount), 0)
                AS average_payment

        FROM payments

        WHERE payment_status = 'Paid'
          AND payment_date >= ?
          AND payment_date < ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    // Payment Details
    $sql = "
        SELECT
            p.payment_id,
            p.order_id,
            c.full_name,
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
          AND p.payment_date < ?

        ORDER BY p.payment_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $result = $stmt->get_result();

    $columns = [
        "Payment ID" => "payment_id",
        "Order ID" => "order_id",
        "Customer Name" => "full_name",
        "Payment Date" => "payment_date",
        "Amount (Rs.)" => "amount",
        "Payment Method" => "payment_method",
        "Status" => "payment_status"
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

// ==================================================
// 4. STOCK REPORT
// ==================================================


    
elseif ($report_type === "stock") {

    $report_title = "Stock Report";

    $report_note = "Current stock position. Date filters do not apply to this report.";

    // Stock Summary
    $sql = "
        SELECT
            COUNT(*) AS total_products,

            COALESCE(SUM(stock_quantity), 0)
                AS total_stock,

            COALESCE(SUM(
                CASE
                    WHEN stock_quantity <= 10
                    THEN 1 ELSE 0
                END
            ), 0) AS low_stock_products,

            COALESCE(SUM(
                stock_quantity * price
            ), 0) AS total_stock_value

        FROM products
    ";

    $result = $conn->query($sql);

    $summary = $result->fetch_assoc();

    // Stock Details
    $sql = "
        SELECT
            product_id,
            product_name,
            price,
            stock_quantity,

            CASE
                WHEN stock_quantity <= 10
                    THEN 'Low Stock'
                ELSE 'Sufficient Stock'
            END AS stock_status,

            (stock_quantity * price) AS stock_value,

            status

        FROM products

        ORDER BY product_name ASC
    ";

    $result = $conn->query($sql);

    $columns = [
        "Product ID" => "product_id",
        "Product Name" => "product_name",
        "Price (Rs.)" => "price",
        "Stock Quantity" => "stock_quantity",
        "Stock Status" => "stock_status",
        "Stock Value (Rs.)" => "stock_value",
        "Product Status" => "status"
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

    

// ==================================================
// 5. MAINTENANCE REPORT
// ==================================================

elseif ($report_type === "maintenance") {

    $report_title = "Maintenance Report";

    // Maintenance Summary
    $sql = "
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

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    // Maintenance Details
    $sql = "
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

                WHEN LOWER(status) IN ('completed', 'complete')
                    THEN 'Completed'

                ELSE 'Not Overdue'
            END AS due_status

        FROM maintenance

        WHERE maintenance_date >= ?
          AND maintenance_date <= ?

        ORDER BY maintenance_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $from_date, $to_date);
    $stmt->execute();

    $result = $stmt->get_result();

    $columns = [
        "Maintenance ID" => "maintenance_id",
        "Maintenance Type" => "maintenance_type",
        "Description" => "description",
        "Maintenance Date" => "maintenance_date",
        "Next Due Date" => "next_due_date",
        "Status" => "status",
        "Due Status" => "due_status"
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

// ==================================================
// 6. DELIVERY REPORT
// ==================================================

elseif ($report_type === "delivery") {

    $report_title = "Delivery Report";

    // Delivery Summary
    $sql = "
        SELECT
            COUNT(*) AS total_deliveries,

            COALESCE(SUM(
                d.delivery_status = 'Pending'
            ), 0) AS pending_deliveries,

            COALESCE(SUM(
                d.delivered_at IS NOT NULL
            ), 0) AS completed_deliveries,

            COALESCE(SUM(
                d.delivery_person_id IS NOT NULL
            ), 0) AS assigned_deliveries,

            COALESCE(SUM(
                d.collection_status = 'Collected'
                OR d.collection_status LIKE 'Collected %'
            ), 0) AS collected_deliveries,

            COALESCE(SUM(
                d.collection_status LIKE 'Partially%'
            ), 0) AS partially_collected

        FROM deliveries d

        WHERE COALESCE(
            d.delivered_at,
            d.delivery_date
        ) >= ?

          AND COALESCE(
            d.delivered_at,
            d.delivery_date
        ) < ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $summary = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    // Delivery Details
    $sql = "
        SELECT
            d.delivery_id,
            d.order_id,
            c.full_name,
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

        WHERE COALESCE(
            d.delivered_at,
            d.delivery_date
        ) >= ?

          AND COALESCE(
            d.delivered_at,
            d.delivery_date
        ) < ?

        ORDER BY
            COALESCE(d.delivered_at, d.delivery_date) DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();

    $result = $stmt->get_result();

    $columns = [
        "Delivery ID" => "delivery_id",
        "Order ID" => "order_id",
        "Customer Name" => "full_name",
        "Delivery Person" => "delivery_person",
        "Delivery Date" => "delivery_date",
        "Delivered At" => "delivered_at",
        "Delivery Status" => "delivery_status",
        "Collection Status" => "collection_status",
        "Collection Note" => "collection_note"
    ];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports Management - Eranda Water</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        .report-filters {
            margin: 20px 0;
            padding: 20px;
            background: #f5f7fa;
            border-radius: 8px;
        }

        .report-filters label {
            display: inline-block;
            margin: 8px 5px;
        }

        .report-filters input,
        .report-filters select {
            padding: 8px;
            margin: 5px;
        }

        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .summary-card {
            padding: 18px;
            background: #eef4fa;
            border-radius: 8px;
            border-left: 4px solid #2874a6;
        }

        .summary-card h4 {
            margin: 0 0 10px;
        }

        .summary-card p {
            font-size: 21px;
            font-weight: bold;
            margin: 0;
        }

        .report-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #ccc;
            padding: 9px;
            text-align: left;
        }

        .report-table th {
            background: #e9eef4;
        }

        .report-actions {
            margin: 20px 0;
        }

        .report-actions button,
        .report-actions a {
            display: inline-block;
            padding: 10px 16px;
            margin: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        .report-note {
            padding: 12px;
            background: #fff8df;
            margin: 15px 0;
        }

        @media print {

            .report-filters,
            .report-actions,
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .container {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .report-table {
                font-size: 9px;
            }

            .report-table th,
            .report-table td {
                padding: 5px;
            }

            .summary-card {
                background: white !important;
                border: 1px solid #999;
            }

            @page {
                size: landscape;
                margin: 10mm;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h2>Eranda Water</h2>

    <h2>Reports Management</h2>

    <!-- REPORT FILTERS -->

    <form method="GET" action="reports.php" class="report-filters no-print">

        <label>Report Type:</label>

        <select name="report_type" required>

            <option value="orders"
                <?php if ($report_type === "orders") echo "selected"; ?>>
                Orders Report
            </option>

            <option value="sales"
                <?php if ($report_type === "sales") echo "selected"; ?>>
                Sales Report
            </option>

            <option value="payments"
                <?php if ($report_type === "payments") echo "selected"; ?>>
                Payments Report
            </option>

            <option value="stock"
                <?php if ($report_type === "stock") echo "selected"; ?>>
                Stock Report
            </option>

            <option value="maintenance"
                <?php if ($report_type === "maintenance") echo "selected"; ?>>
                Maintenance Report
            </option>

            <option value="delivery"
                <?php if ($report_type === "delivery") echo "selected"; ?>>
                Delivery Report
            </option>

        </select>

        <br><br>

        <label>From Date:</label>

        <input
            type="date"
            name="from_date"
            value="<?php echo e($from_date); ?>"
            required
        >

        <label>To Date:</label>

        <input
            type="date"
            name="to_date"
            value="<?php echo e($to_date); ?>"
            required
        >

        <br><br>

        <button type="submit">Generate Report</button>

    </form>

    <hr>

    <!-- REPORT HEADER -->

    <div class="report-header">

        <h2><?php echo e($report_title); ?></h2>

        <p>
            Report Period:
            <?php echo e($from_date); ?>
            to
            <?php echo e($to_date); ?>
        </p>

        <p>
            Generated On:
            <?php echo date("Y-m-d H:i:s"); ?>
        </p>

        <?php if ($report_note !== "") { ?>

            <p class="report-note">
                <?php echo e($report_note); ?>
            </p>

        <?php } ?>

    </div>

    <!-- SUMMARY SECTION -->

    <h3>Report Summary</h3>

    <div class="report-summary">

        <?php foreach ($summary as $label => $value) { ?>

            <div class="summary-card">

                <h4>
                    <?php
                    echo e(
                        ucwords(str_replace("_", " ", $label))
                    );
                    ?>
                </h4>

                <p>

                    <?php
                    if (in_array($label, [
                                 "total_sales",
                                  "total_paid_amount",
                                     "average_payment",
                                   "total_stock_value"
                                                       ], true)) {
                         echo "Rs. " . number_format((float)$value, 2);
                                 } else {
                                    echo number_format((float)$value);
                               }

                    ?>

                </p>

            </div>

        <?php } ?>

    </div>

    <!-- DETAILS SECTION -->

    <h3>Report Details</h3>

    <div class="report-table-wrapper">

        <table class="report-table">

            <thead>

                <tr>

                    <?php foreach ($columns as $heading => $field) { ?>

                        <th><?php echo e($heading); ?></th>

                    <?php } ?>

                </tr>

            </thead>

            <tbody>

                <?php if (count($rows) > 0) { ?>

                    <?php foreach ($rows as $row) { ?>

                        <tr>

                            <?php foreach ($columns as $heading => $field) { ?>

                                <td>

                                    <?php

                                    $value = $row[$field] ?? "";

                                    if (
                                        strpos($field, "amount") !== false ||
                                        strpos($field, "price") !== false ||
                                        strpos($field, "value") !== false
                                    ) {

                                        echo number_format((float)$value, 2);

                                    } else {

                                        echo e($value);

                                    }

                                    ?>

                                </td>

                            <?php } ?>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>

                        <td colspan="<?php echo count($columns); ?>">

                            No records found for this report.

                        </td>

                    </tr>

                <?php } ?>

            </tbody>

        </table>

    </div>

    <!-- REPORT ACTIONS -->

    <div class="report-actions no-print">

        <button type="button" onclick="window.print()">

            Print Report

        </button>
        <a href="export_reports_pdf.php?report_type=<?php echo urlencode($report_type); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
   class="btn btn-primary"
   target="_blank">
    Download PDF
          </a>


        <?php if ($report_type === "orders") { ?>

            <a
                href="export_orders_pdf.php?from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>"
                target="_blank"
            >

                Download Orders PDF

            </a>

        <?php } ?>

    </div>

    <p class="no-print">

        Report generated by Eranda Water Management System.

    </p>

</div>

</body>

</html>
