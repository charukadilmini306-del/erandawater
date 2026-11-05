<?php

session_start();

require_once "config/database.php";

// Delivery Person login check
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Delivery role only
if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "delivery"
) {
    header("Location: login.php");
    exit();
}

$delivery_person_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"];

$success = "";
$error = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Save collected payment
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

    $delivery_id = (int)($_POST["delivery_id"] ?? 0);
    $amount = (float)($_POST["amount"] ?? 0);
    $payment_method = $_POST["payment_method"] ?? "";
    $collection_note = trim($_POST["collection_note"] ?? "");

    $allowed_methods = ["Cash", "Card"];

    if ($delivery_id <= 0 || $amount <= 0) {

        $error = "Please enter a valid delivery and payment amount.";

    } elseif (!in_array($payment_method, $allowed_methods, true)) {

        $error = "Invalid payment method.";

    } else {

        try {

            $conn->begin_transaction();

            // Check assigned delivery belongs to logged-in person
            $stmt = $conn->prepare(
                "SELECT delivery_id, order_id, delivery_status,
                        collection_status
                 FROM deliveries
                 WHERE delivery_id = ?
                 AND delivery_person_id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param(
                "ii",
                $delivery_id,
                $delivery_person_id
            );

            $stmt->execute();
            $delivery = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$delivery) {
                throw new Exception("Delivery record not found.");
            }

            if (
                !in_array(
                    $delivery["delivery_status"],
                    ["Out for Delivery", "Delivered"],
                    true
                )
            ) {
                throw new Exception(
                    "Payment can only be recorded for an active or completed delivery."
                );
            }

            if ($delivery["collection_status"] === "Collected") {
                throw new Exception(
                    "Payment has already been recorded for this delivery."
                );
            }

            $order_id = (int)$delivery["order_id"];

            // Lock order before checking payment balance
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

            // Calculate already received payments
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

            if ($balance <= 0) {
                throw new Exception("This order has no remaining balance.");
            }

            if ($amount > $balance) {
                throw new Exception(
                    "Payment amount exceeds the remaining balance."
                );
            }

            // Insert payment as Collected
            $status = "Collected";

            $stmt = $conn->prepare(
                "INSERT INTO payments
                (order_id, amount, payment_method, payment_status,
                 collected_by, collection_note)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "idssis",
                $order_id,
                $amount,
                $payment_method,
                $status,
                $delivery_person_id,
                $collection_note
            );

            if (!$stmt->execute()) {
                throw new Exception("Payment could not be recorded.");
            }

            $stmt->close();

            // Update delivery collection status
            if ($amount >= $balance) {
                $collection_status = "Collected";
                    } else {
                  $collection_status = "Partially Collected";
                }

            $stmt = $conn->prepare(
                "UPDATE deliveries
                 SET collection_status = ?,
                     collection_note = ?
                 WHERE delivery_id = ?
                 AND delivery_person_id = ?"
            );

            $stmt->bind_param(
                "ssii",
                $collection_status,
                $collection_note,
                $delivery_id,
                $delivery_person_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Delivery record could not be updated.");
            }

            $stmt->close();

            $conn->commit();

            $_SESSION["payment_success"] =
                "Customer payment recorded successfully.";

            header("Location: delivery_payment.php");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();
            $error = $e->getMessage();

        }
    }
}
// Get success message after redirect
if (isset($_SESSION["payment_success"])) {

    $success = $_SESSION["payment_success"];

    unset($_SESSION["payment_success"]);

}


// Get assigned deliveries
$sql = "
SELECT
    d.delivery_id,
    d.order_id,
    d.delivery_date,
    d.delivery_status,
    d.collection_status,
    o.order_date,
    o.total_amount,
    o.delivery_address,
    c.full_name,
    c.phone,

    COALESCE(
        (
            SELECT SUM(p.amount)
            FROM payments p
            WHERE p.order_id = o.order_id
            AND p.payment_status IN ('Paid', 'Collected')
        ), 0
    ) AS received_amount

FROM deliveries d

INNER JOIN orders o
    ON d.order_id = o.order_id

INNER JOIN customers c
    ON o.customer_id = c.customer_id

WHERE d.delivery_person_id = ?

ORDER BY d.delivery_id DESC
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $delivery_person_id);

$stmt->execute();

$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer Payment Collection</title>
</head>

<body>

<h2>Customer Payment Collection</h2>

<p>
    Delivery Person:
    <?php echo htmlspecialchars($full_name); ?>
</p>

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


<h3>My Assigned Deliveries</h3>

<table border="1" cellpadding="10" cellspacing="0">

<tr>
    <th>Delivery ID</th>
    <th>Order ID</th>
    <th>Customer Name</th>
    <th>Phone</th>
    <th>Delivery Address</th>
    <th>Total Amount</th>
    <th>Received Amount</th>
    <th>Remaining Balance</th>
    <th>Collection Status</th>
    <th>Record Payment</th>
</tr>

<?php if ($result->num_rows > 0): ?>

    <?php while ($row = $result->fetch_assoc()): ?>

        <?php
        $balance = (float)$row["total_amount"]
                 - (float)$row["received_amount"];
        ?>

        <tr>

            <td><?php echo $row["delivery_id"]; ?></td>

            <td><?php echo $row["order_id"]; ?></td>

            <td>
                <?php echo htmlspecialchars($row["full_name"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($row["phone"]); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($row["delivery_address"]); ?>
            </td>

            <td>
                Rs. <?php echo number_format($row["total_amount"], 2); ?>
            </td>

            <td>
                Rs. <?php echo number_format($row["received_amount"], 2); ?>
            </td>

            <td>
                Rs. <?php echo number_format($balance, 2); ?>
            </td>

            <td>
                <?php echo htmlspecialchars($row["collection_status"]); ?>
            </td>

            <td>

                <?php if (
                    $row["collection_status"] !== "Collected" &&
                    $balance > 0 &&
                    in_array(
                        $row["delivery_status"],
                        ["Out for Delivery", "Delivered"],
                        true
                    )
                ): ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                        >

                        <input
                            type="hidden"
                            name="delivery_id"
                            value="<?php echo $row["delivery_id"]; ?>"
                        >

                        <label>Amount Received:</label>

                        <input
                            type="number"
                            name="amount"
                            min="0.01"
                            max="<?php echo number_format($balance, 2, '.', ''); ?>"
                            step="0.01"
                            required
                        >

                        <br><br>

                        <label>Payment Method:</label>

                        <select name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                        </select>

                        <br><br>

                        <label>Collection Note:</label>

                        <input
                            type="text"
                            name="collection_note"
                            maxlength="255"
                        >

                        <br><br>

                        <button type="submit" name="save_payment">
                            Save Payment
                        </button>

                    </form>

                     <?php elseif ($row["collection_status"] === "Collected"): ?>

                    Payment Already Recorded

                <?php elseif ($balance <= 0): ?>

                    Fully Paid

                <?php else: ?>

                    Delivery Not Started

                <?php endif; ?>

            </td>

        </tr>

    <?php endwhile; ?>

<?php else: ?>

    <tr>
        <td colspan="10">No assigned deliveries found.</td>
    </tr>

<?php endif; ?>

</table>

</body>
</html