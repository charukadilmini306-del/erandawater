<?php

session_start();

require_once "config/database.php";

// Admin and Staff access only
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

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$success = "";
$error = "";
// Get payment success message after redirect
if (isset($_SESSION["payment_success"])) {

    $success = $_SESSION["payment_success"];

    unset($_SESSION["payment_success"]);

}
// Get verification success message after redirect
if (isset($_SESSION["verification_success"])) {

    $success = $_SESSION["verification_success"];

    unset($_SESSION["verification_success"]);

}
// Verify collected payment
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["verify_payment"])
) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Invalid security token.");
    }

    $payment_id = (int)($_POST["payment_id"] ?? 0);

    if ($payment_id <= 0) {

        $error = "Invalid payment record.";

    } else {

        try {

            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT payment_id, payment_status
                 FROM payments
                 WHERE payment_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$payment) {
                throw new Exception("Payment record not found.");
            }

            if ($payment["payment_status"] !== "Collected") {
                throw new Exception(
                    "This payment has already been verified or is not awaiting verification."
                );
            }

            $verified_by = (int)$_SESSION["user_id"];

            $stmt = $conn->prepare(
                "UPDATE payments
                 SET payment_status = 'Paid',
                     verified_by = ?,
                     verified_at = NOW()
                 WHERE payment_id = ?
                 AND payment_status = 'Collected'"
            );

            $stmt->bind_param(
                "ii",
                $verified_by,
                $payment_id
            );

            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new Exception("Payment verification failed.");
            }

            $stmt->close();

            $conn->commit();

            $_SESSION["verification_success"] =
                "Payment verified successfully.";

            header("Location: manage_payments.php");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();
            $error = $e->getMessage();

        }

    }
}
// Save new payment
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_payment"])
) {

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

            $stmt = $conn->prepare(
                "SELECT total_amount FROM orders
                 WHERE order_id = ? FOR UPDATE"
            );

            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(amount), 0) AS received_amount
                 FROM payments
                 WHERE order_id = ?
                 AND payment_status IN ('Paid', 'Collected')"
            );

            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $payment_total = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $balance = (float)$order["total_amount"]
                     - (float)$payment_total["received_amount"];

            if ($amount > $balance) {

                throw new Exception(
                    "Payment amount exceeds the remaining balance."
                );

            }

            $status = "Paid";

            $stmt = $conn->prepare(
                "INSERT INTO payments
                (order_id, amount, payment_method, payment_status)
                VALUES (?, ?, ?, ?)"
            );

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

            $_SESSION["payment_success"] = "Payment saved successfully.";

            header("Location: manage_payments.php");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();
            $error = $e->getMessage();

        }

    }
}

// Get payments awaiting verification
$pending_sql = "
SELECT
    p.payment_id,
    p.order_id,
    p.payment_date,
    p.amount,
    p.payment_method,
    p.collected_by,
    p.collection_note,
    c.full_name,
    c.phone,
    u.full_name AS delivery_person_name

FROM payments p

INNER JOIN orders o
    ON p.order_id = o.order_id

INNER JOIN customers c
    ON o.customer_id = c.customer_id

LEFT JOIN users u
    ON p.collected_by = u.user_id

WHERE p.payment_status = 'Collected'

ORDER BY p.payment_date ASC
";

$pending_result = $conn->query($pending_sql);

if (!$pending_result) {
    die("Error loading pending payments: " . $conn->error);
}
// Get order payment details
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
    ) AS paid_amount,

    COALESCE(
        SUM(
            CASE
                WHEN p.payment_status = 'Collected'
                THEN p.amount
                ELSE 0
            END
        ), 0
    ) AS collected_amount,

    COALESCE(
        SUM(
            CASE
                WHEN p.payment_status IN ('Paid', 'Collected')
                THEN p.amount
                ELSE 0
            END
        ), 0
    ) AS received_amount

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
<hr>

<h3>Payments Awaiting Verification</h3>

<table border="1" cellpadding="10" cellspacing="0">

<tr>
    <th>Payment ID</th>
    <th>Order ID</th>
    <th>Customer Name</th>
    <th>Phone</th>
    <th>Delivery Person</th>
    <th>Payment Date</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Collection Note</th>
    <th>Action</th>
</tr>

<?php if ($pending_result->num_rows > 0): ?>

    <?php while ($pending = $pending_result->fetch_assoc()): ?>

        <tr>

            <td>
                <?php echo $pending["payment_id"]; ?>
            </td>

            <td>
                <?php echo $pending["order_id"]; ?>
            </td>

            <td>
                <?php echo htmlspecialchars($pending["full_name"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($pending["phone"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars(
                    $pending["delivery_person_name"] ?? "Not Available"
                ); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($pending["payment_date"]); ?>
            </td>

            <td>
                Rs. <?php echo number_format($pending["amount"], 2); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($pending["payment_method"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars(
                    $pending["collection_note"] ?? "-"
                ); ?>
            </td>

            <td>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                    >

                    <input
                        type="hidden"
                        name="payment_id"
                        value="<?php echo $pending["payment_id"]; ?>"
                    >

                    <button
                        type="submit"
                        name="verify_payment"
                        onclick="return confirm('Are you sure you want to verify this payment?');"
                    >
                        Verify Payment
                    </button>

                </form>

            </td>

        </tr>

    <?php endwhile; ?>

<?php else: ?>

    <tr>
        <td colspan="10">
            No payments awaiting verification.
        </td>
    </tr>

<?php endif; ?>

</table>

<hr>

<h3>Record New Payment</h3>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
    >

    <label>Order ID:</label>

    <select name="order_id" required>

        <option value="">Select Order</option>

        <?php
        $order_options = $conn->query(
            "SELECT order_id FROM orders ORDER BY order_id DESC"
        );

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

<h3>Order Payment Details</h3>
<table border="1" cellpadding="10" cellspacing="0">

<tr>
    <th>Order ID</th>
    <th>Customer Name</th>
    <th>Phone</th>
    <th>Order Date</th>
    <th>Total Amount</th>
    <th>Received Amount</th>
    <th>Collected - Awaiting Verification</th>
    <th>Remaining Balance</th>
    <th>Payment History</th>
</tr>

<?php if ($result->num_rows > 0): ?>

    <?php while ($row = $result->fetch_assoc()): ?>

        <?php
        $balance = (float)$row["total_amount"]
                 - (float)$row["received_amount"];
        ?>

        <tr>

            <td><?php echo $row["order_id"]; ?></td>

            <td>
                <?php echo htmlspecialchars($row["full_name"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($row["phone"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($row["order_date"]); ?>
            </td>

            <td>
                Rs. <?php echo number_format($row["total_amount"], 2); ?>
            </td>

            <td>
                Rs. <?php echo number_format($row["received_amount"], 2); ?>
            </td>

            <td>
                Rs. <?php echo number_format($row["collected_amount"], 2); ?>
            </td>

            <td>
                Rs. <?php echo number_format($balance, 2); ?>
            </td>

            <td>

                <?php
                $history_order_id = (int)$row["order_id"];

                $history_stmt = $conn->prepare(
                    "SELECT payment_id, payment_date, amount,
                    payment_method, payment_status, collected_by,
                    verified_by, verified_at
                    FROM payments
                    WHERE order_id = ?
                    ORDER BY payment_id DESC"
                );

                $history_stmt->bind_param("i", $history_order_id);
                $history_stmt->execute();

                $history_result = $history_stmt->get_result();
                ?>

                <?php if ($history_result->num_rows > 0): ?>

                    <?php while ($payment = $history_result->fetch_assoc()): ?>

                        <p>
                            Payment ID:
                            <?php echo $payment["payment_id"]; ?>
                            <br>

                            Date:
                            <?php echo htmlspecialchars($payment["payment_date"]); ?>
                            <br>

                            Amount:
                            Rs. <?php echo number_format($payment["amount"], 2); ?>
                            <br>

                            Method:
                            <?php echo htmlspecialchars($payment["payment_method"]); ?>
                            <br>

                            Status:
                            <?php echo htmlspecialchars($payment["payment_status"]); ?>

                            <?php if ($payment["payment_status"] === "Collected"): ?>

                                <br>
                                Collected By User ID:
                                <?php echo htmlspecialchars($payment["collected_by"] ?? "Not Available"); ?>

                                <br>
                                Verification:
                                Awaiting Admin/Staff Verification

                            <?php elseif ($payment["payment_status"] === "Paid"): ?>

                                <br>
                                Verification: Confirmed

                            <?php endif; ?>

                        </p>

                        <hr>

                    <?php endwhile; ?>

                <?php else: ?>

                    No Payments Yet

                <?php endif; ?>

                <?php $history_stmt->close(); ?>

            </td>

        </tr>

    <?php endwhile; ?>

<?php else: ?>

    <tr>
        <td colspan="9">No orders found.</td>
    </tr>

<?php endif; ?>

</table>

</body>
</html>
