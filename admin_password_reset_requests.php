<?php

session_start();

require_once "config/database.php";

// Allow Admin only
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Get messages
$success = $_SESSION["reset_success"] ?? "";
$error = $_SESSION["reset_error"] ?? "";
$temporary_password = $_SESSION["reset_temp_password"] ?? "";

unset($_SESSION["reset_success"]);
unset($_SESSION["reset_error"]);
unset($_SESSION["reset_temp_password"]);
// Handle password reset request
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["reset_password"])
) {

    // Check CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";

    } else {

        $request_id = (int) ($_POST["request_id"] ?? 0);

        $conn->begin_transaction();

        try {

            // Find pending request
            $check_sql = "
                SELECT customer_id
                FROM password_reset_requests
                WHERE request_id = ?
                AND request_status = 'Pending'
                FOR UPDATE
            ";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $request_id);
            $check_stmt->execute();

            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows !== 1) {
                throw new Exception("Invalid request.");
            }

            $request = $check_result->fetch_assoc();
            $customer_id = (int) $request["customer_id"];

            $check_stmt->close();

            // Generate temporary password
            $temporary_password = bin2hex(random_bytes(6));

            $password_hash = password_hash(
                $temporary_password,
                PASSWORD_DEFAULT
            );

            // Update customer password
            $update_sql = "
                UPDATE customer_accounts
                SET password_hash = ?,
                    must_change_password = 1
                WHERE customer_id = ?
            ";

            $update_stmt = $conn->prepare($update_sql);

            $update_stmt->bind_param(
                "si",
                $password_hash,
                $customer_id
            );

            if (
                !$update_stmt->execute() ||
                $update_stmt->affected_rows !== 1
            ) {
                throw new Exception("Password update failed.");
            }

            $update_stmt->close();

            // Mark request as completed
            $request_sql = "
                UPDATE password_reset_requests
                SET request_status = 'Completed',
                    handled_by = ?,
                    handled_at = NOW()
                WHERE request_id = ?
                AND request_status = 'Pending'
            ";

            $request_stmt = $conn->prepare($request_sql);

            $admin_id = (int) $_SESSION["user_id"];

            $request_stmt->bind_param(
                "ii",
                $admin_id,
                $request_id
            );

            if (
                !$request_stmt->execute() ||
                $request_stmt->affected_rows !== 1
            ) {
                throw new Exception("Request update failed.");
            }

            $request_stmt->close();

            $conn->commit();

            $_SESSION["reset_temp_password"] = $temporary_password;
            $_SESSION["reset_success"] = "Password reset successfully.";

        } catch (Throwable $e) {

            $conn->rollback();

            $_SESSION["reset_error"] =
                "Password reset failed. Please try again.";
        }

        // Refresh page after processing
        header("Location: admin_password_reset_requests.php");
        exit();
    }
}
// Get pending password reset requests
$sql = "
    SELECT
        r.request_id,
        r.phone,
        r.requested_at,
        c.full_name
    FROM password_reset_requests r
    INNER JOIN customers c
        ON r.customer_id = c.customer_id
    WHERE r.request_status = 'Pending'
    ORDER BY r.requested_at ASC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database query failed: " . $conn->error);
}

$pending_count = $result->num_rows;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Password Reset Requests</title>
</head>

<body>

    <h1>Password Reset Requests</h1>

    <h2>
        Pending Requests:
        <?php echo $pending_count; ?>
    </h2>

    <?php if (!empty($success)) { ?>
        <p style="color: green;">
            <?php echo htmlspecialchars($success); ?>
        </p>
    <?php } ?>

    <?php if (!empty($error)) { ?>
        <p style="color: red;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php } ?>

    <?php if (!empty($temporary_password)) { ?>
        <h3>Temporary Password</h3>

        <p>
            <?php echo htmlspecialchars($temporary_password); ?>
        </p>

        <p>
            Contact the customer using their verified phone number
            and provide this temporary password securely.
        </p>
    <?php } ?>
    <table border="1" cellpadding="10">

        <tr>
            <th>Request ID</th>
            <th>Customer Name</th>
            <th>Phone Number</th>
            <th>Requested Date</th>
            <th>Action</th>
        </tr>

        <?php if ($result->num_rows > 0) { ?>

            <?php while ($row = $result->fetch_assoc()) { ?>

                <tr>

                    <td>
                        <?php echo $row["request_id"]; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["full_name"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["phone"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($row["requested_at"]); ?>
                    </td>

                    <td>

                        <a href="tel:<?php echo htmlspecialchars($row["phone"]); ?>">
                            Call
                        </a>
                        <br>

                             <a href="sms:<?php echo htmlspecialchars($row["phone"]); ?>">
                                         Message
                            </a>

                        <br>

                        <form method="POST"
                              onsubmit="return confirm('Have you verified the customer identity by phone? Reset the password now?');">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                            <input type="hidden"
                                   name="request_id"
                                   value="<?php echo (int) $row["request_id"]; ?>">

                            <button type="submit" name="reset_password">
                                Reset Password
                            </button>

                        </form>

                    </td>

                </tr>

            <?php } ?>

        <?php } else { ?>

            <tr>
                <td colspan="5">No pending requests found.</td>
            </tr>

        <?php } ?>

    </table>
    <br>

    <a href="dashboard.php">Back to Dashboard</a>

</body>
</html>