<?php

session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff"], true)
) {
    header("Location: login.php");
    exit();
}
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
// Save payment
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_payment"])) {
    if (
    !isset($_POST["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    die("Invalid security token.");
}

    $order_id = (int)($_POST["order_id"] ?? 0);
    $amount = (float)($_POST["amount"] ?? 0);
    $payment_method = $_POST["payment_method"] ?? "";

    $allowed_methods = ["Cash", "Bank Transfer", "Card"];

    if ($order_id <= 0 || $amount <= 0) {
        $error = "Please enter a valid order and payment amount.";
    } elseif (!in_array($payment_method, $allowed_methods, true)) {
        $error = "Invalid payment method.";
    } else {

        try {
            $conn->begin_transaction();

            // Lock the order while checking its balance
            $stmt = $conn->prepare("
                SELECT
                    o.total_amount,
                    COALESCE(SUM(
                        CASE
                            WHEN p.payment_status = 'Paid'
                            THEN p.amount
                            ELSE 0
                        END
                    ), 0) AS paid_amount
                FROM orders o
                LEFT JOIN payments p ON o.order_id = p.order_id
                WHERE o.order_id = ?
                GROUP BY o.order_id, o.total_amount
                FOR UPDATE
            ");

            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $balance = (float)$order["total_amount"]
                     - (float)$order["paid_amount"];

            if ($amount > $balance) {
                throw new Exception("Payment amount exceeds the remaining balance.");
            }

            // Insert confirmed payment
            $status = "Paid";

            $stmt = $conn->prepare("
                INSERT INTO payments
                (order_id, amount, payment_method, payment_status)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "idss",
                $order_id,
                $amount,
                $payment_method,
                $status
            );

            if (!$stmt->execute()) {
                throw new Exception("Payment could not be saved.");
            }

            $stmt->close();

            $conn->commit();

            $success = "Payment saved successfully.";

        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
// Get orders and payment totals
$sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.total_amount,
        c.full_name,
        c.phone,
        COALESCE(
            SUM(
                CASE
                    WHEN p.payment_status = 'Paid'
                    THEN p.amount
                    ELSE 0
                END
            ), 0
        ) AS paid_amount
    FROM orders o
    INNER JOIN customers c
        ON o.customer_id = c.customer_id
    LEFT JOIN payments p
        ON o.order_id = p.order_id
    GROUP BY
        o.order_id,
        o.order_date,
        o.total_amount,
        c.full_name,
        c.phone
    ORDER BY o.order_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Error loading orders: " . $conn->error);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Management</title>
</head>
<body>

<h2>Payment Management</h2>
<?php if ($success !== ""): ?>
    <p style="color: green;">
        <?php echo htmlspecialchars($success); ?>
    </p>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <p style="color: red;">
        <?php echo htmlspecialchars($error); ?>
    </p>
<?php endif; ?>

<h3>Record New Payment</h3>

<form method="POST">
    <input type="hidden" name="csrf_token"
           value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

    <label>Order ID:</label>

    <select name="order_id" required>
        <option value="">Select Order</option>

        <?php
        $order_options = $conn->query("
            SELECT order_id
            FROM orders
            ORDER BY order_id DESC
        ");

        while ($option = $order_options->fetch_assoc()):
        ?>
            <option value="<?php echo $option["order_id"]; ?>">
                Order #<?php echo $option["order_id"]; ?>
            </option>
        <?php endwhile; ?>

    </select>

    <br><br>

    <label>Payment Amount:</label>

    <input
        type="number"
        name="amount"
        min="0.01"
        step="0.01"
        required
    >

    <br><br>

    <label>Payment Method:</label>

    <select name="payment_method" required>
        <option value="">Select Method</option>
        <option value="Cash">Cash</option>
        <option value="Bank Transfer">Bank Transfer</option>
        <option value="Card">Card (POS)</option>
    </select>

    <br><br>

    <button type="submit" name="save_payment">
        Save Payment
    </button>

</form>

<hr>
<table border="1" cellpadding="10" cellspacing="0">

    <tr>
        <th>Order ID</th>
        <th>Customer Name</th>
        <th>Phone</th>
        <th>Order Date</th>
        <th>Total Amount</th>
        <th>Paid Amount</th>
        <th>Balance</th>
        <th>Payment History</th>
    </tr>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($row = $result->fetch_assoc()): ?>

            <?php
                $balance = $row["total_amount"] - $row["paid_amount"];
            ?>

            <tr>
                <td><?php echo $row["order_id"]; ?></td>

                <td>
                    <?php echo htmlspecialchars($row["full_name"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["phone"]); ?>
                </td>

                <td><?php echo $row["order_date"]; ?></td>

                <td>
                    Rs. <?php echo number_format($row["total_amount"], 2); ?>
                </td>

                <td>
                    Rs. <?php echo number_format($row["paid_amount"], 2); ?>
                </td>

                <td>
                    Rs. <?php echo number_format($balance, 2); ?>
                </td>
                <td>
                   <?php
                      $history_order_id = $row["order_id"];

                        $history_stmt = $conn->prepare("
                          SELECT payment_id, payment_date, amount,
                                  payment_method, payment_status
                                      FROM payments
                                     WHERE order_id = ?
                                    ORDER BY payment_id DESC
                     ");

    $history_stmt->bind_param("i", $history_order_id);
    $history_stmt->execute();

    $history_result = $history_stmt->get_result();

    if ($history_result->num_rows > 0):

        while ($payment = $history_result->fetch_assoc()):
    ?>

        <p>
            Payment ID: <?php echo $payment["payment_id"]; ?>
            <br>
            Date: <?php echo htmlspecialchars($payment["payment_date"]); ?>
            <br>
            Amount: Rs. <?php echo number_format($payment["amount"], 2); ?>
            <br>
            Method: <?php echo htmlspecialchars($payment["payment_method"]); ?>
            <br>
            Status: <?php echo htmlspecialchars($payment["payment_status"]); ?>
        </p>

        <hr>

    <?php
        endwhile;

    else:
        echo "No Payments Yet";
    endif;

    $history_stmt->close();
    ?>
</td>
            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="7">No orders found.</td>
        </tr>

    <?php endif; ?>

</table>

</body>
</html>