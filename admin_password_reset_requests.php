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
$reset_link = $_SESSION["reset_link"] ?? "";

// Clear old messages
unset($_SESSION["reset_success"]);
unset($_SESSION["reset_error"]);
unset($_SESSION["reset_link"]);
// Handle password reset request
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["reset_password"])
) {

    // Validate CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $_SESSION["reset_error"] =
            "Invalid request. Please try again.";

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
                throw new Exception("Invalid reset request.");
            }

            $request = $check_result->fetch_assoc();

            $customer_id = (int) $request["customer_id"];

            $check_stmt->close();
            // Generate secure random token
$reset_token = bin2hex(random_bytes(32));

// Store only the token hash
$token_hash = hash("sha256", $reset_token);


// Invalidate previous unused tokens
$invalidate_sql = "
    UPDATE password_reset_tokens
    SET used_at = NOW()
    WHERE customer_id = ?
    AND used_at IS NULL
";

$invalidate_stmt = $conn->prepare($invalidate_sql);

$invalidate_stmt->bind_param("i", $customer_id);

if (!$invalidate_stmt->execute()) {
    throw new Exception("Could not invalidate old tokens.");
}

$invalidate_stmt->close();

// Save new token
$token_sql = "
INSERT INTO password_reset_tokens
(customer_id, token_hash, expires_at)
VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
";

$token_stmt = $conn->prepare($token_sql);

$token_stmt->bind_param(
    "is",
    $customer_id,
    $token_hash,
    
);

if (!$token_stmt->execute()) {
    throw new Exception("Could not create reset token.");
}

$token_stmt->close();
// Create reset link
$protocol = (
    !empty($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
) ? "https" : "http";

$host = $_SERVER["HTTP_HOST"];

$project_path = rtrim(
    dirname($_SERVER["SCRIPT_NAME"]),
    "/\\"
);

$reset_link = $protocol . "://" . $host .
    $project_path .
    "/customer_reset_password.php?token=" .
    urlencode($reset_token);
    // Mark reset request as completed
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

// Complete database transaction
$conn->commit();

// Save reset link for admin
$_SESSION["reset_link"] = $reset_link;

$_SESSION["reset_success"] =
    "Reset link created successfully.";

} catch (Throwable $e) {

    // Undo database changes if an error occurs
    $conn->rollback();

    $_SESSION["reset_error"] =
        "Reset link creation failed. Please try again.";
}

// Refresh page
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
    <?php if (!empty($reset_link)) { ?>

    <h3>Customer Password Reset Link</h3>

    <p>Copy this link and send it to the verified customer.</p>

    <input
        type="text"
        id="resetLink"
        value="<?php echo htmlspecialchars($reset_link); ?>"
        readonly
        style="width: 80%;"
    >

    <button type="button" onclick="copyResetLink()">
        Copy Link
    </button>

    <p>
        This link expires in 15 minutes and can only be used once.
    </p>

<?php } ?>

<script>
function copyResetLink() {
    const link = document.getElementById("resetLink");

    link.select();
    link.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(link.value)
        .then(function () {
            alert("Reset link copied successfully.");
        })
        .catch(function () {
            alert("Copy failed. Please copy the link manually.");
        });
}
</script>
<h2>Pending Password Reset Requests</h2>

<?php if ($pending_count > 0) { ?>

    <table border="1" cellpadding="8">

        <tr>
            <th>Request ID</th>
            <th>Customer Name</th>
            <th>Phone Number</th>
            <th>Requested Date</th>
            <th>Action</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()) { ?>

            <tr>
                <td>
                    <?php echo (int) $row["request_id"]; ?>
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
                    <form method="POST"
                        onsubmit="return confirm('Have you verified the customer identity? Generate a reset link?');">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
                        >

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int) $row["request_id"]; ?>"
                        >

                        <button type="submit" name="reset_password">
                            Generate Reset Link
                        </button>

                    </form>
                </td>
            </tr>

        <?php } ?>

    </table>

<?php } else { ?>

    <p>No pending password reset requests.</p>

<?php } ?>
<br>

<a href="dashboard.php">Back to Dashboard</a>

</body>
</html>