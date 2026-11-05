<?php

session_start();

require_once "config/database.php";

$error = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $phone = trim($_POST["phone"] ?? "");

    if (!preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Please enter a valid 10-digit phone number.";

    } else {

        $sql = "
            SELECT c.customer_id
            FROM customers c
            INNER JOIN customer_accounts ca
            ON c.customer_id = ca.customer_id
            WHERE c.phone = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $phone);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $customer = $result->fetch_assoc();
            $customer_id = $customer["customer_id"];

            $stmt->close();

            $check_sql = "
                SELECT request_id
                FROM password_reset_requests
                WHERE customer_id = ?
                AND request_status = 'Pending'
                LIMIT 1
            ";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $customer_id);
            $check_stmt->execute();

            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {

                $message = "Your password reset request is already pending.";

            } else {

                $insert_sql = "
                    INSERT INTO password_reset_requests
                    (customer_id, phone)
                    VALUES (?, ?)
                ";

                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("is", $customer_id, $phone);

                if ($insert_stmt->execute()) {
                    $message = "Your request has been sent to the administrator.";
                } else {
                    $error = "Unable to submit your request. Please try again.";
                }

                $insert_stmt->close();
            }

            $check_stmt->close();

        } else {

            $stmt->close();

            $message = "If an account exists for this phone number, a request will be sent to the administrator.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
</head>

<body>

    <h2>Forgot Password</h2>

    <p>Enter your registered phone number to request a password reset.</p>

    <?php if (!empty($error)): ?>
        <p style="color:red;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <p style="color:green;">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label>Phone Number</label>

        <input
            type="text"
            name="phone"
            maxlength="10"
            pattern="[0-9]{10}"
            placeholder="Enter 10-digit phone number"
            required
        >

        <button type="submit">Send Reset Request</button>

    </form>

    <p>
        <a href="customer_login.php">Back to Login</a>
    </p>

</body>
</html>
