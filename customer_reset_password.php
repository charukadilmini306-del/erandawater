<?php

session_start();

require_once "config/database.php";

// Get reset token
$token = $_GET["token"] ?? "";

// Check token format
if (!preg_match("/^[a-f0-9]{64}$/", $token)) {
    die("Invalid or expired password reset link.");
}

// Hash token
$token_hash = hash("sha256", $token);

// Find valid token
$sql = "
    SELECT token_id, customer_id
    FROM password_reset_tokens
    WHERE token_hash = ?
    AND expires_at > NOW()
    AND used_at IS NULL
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $token_hash);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("This password reset link is invalid, expired, or already used.");
}

$reset_data = $result->fetch_assoc();

$token_id = (int) $reset_data["token_id"];

$customer_id = (int) $reset_data["customer_id"];

$stmt->close();

// Create CSRF token
if (empty($_SESSION["reset_csrf_token"])) {
    $_SESSION["reset_csrf_token"] = bin2hex(random_bytes(32));
}

$success = "";
$error = "";

// Handle password reset form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["reset_csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error = "Invalid request. Please try again.";

    } elseif (strlen($new_password) < 8) {

        $error = "Password must contain at least 8 characters.";

    } elseif ($new_password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        $conn->begin_transaction();

        try {

            // Lock and verify the reset token again
            $check_sql = "
                SELECT token_id, customer_id
                FROM password_reset_tokens
                WHERE token_hash = ?
                AND expires_at > NOW()
                AND used_at IS NULL
                FOR UPDATE
            ";

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $token_hash);
            $check_stmt->execute();

            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows !== 1) {
                throw new Exception("Reset link is invalid or expired.");
            }

            $check_stmt->close();

            // Hash new password
            $password_hash = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            // Update customer password
            $update_sql = "
                UPDATE customer_accounts
                SET password_hash = ?,
                    must_change_password = 0
                WHERE customer_id = ?
            ";

            $update_stmt = $conn->prepare($update_sql);

            $update_stmt->bind_param(
                "si",
                $password_hash,
                $customer_id
            );

            if (!$update_stmt->execute() ||
                $update_stmt->affected_rows !== 1) {
                throw new Exception("Password update failed.");
            }

            $update_stmt->close();

            // Mark token as used
            $used_sql = "
                UPDATE password_reset_tokens
                SET used_at = NOW()
                WHERE token_id = ?
                AND used_at IS NULL
            ";

            $used_stmt = $conn->prepare($used_sql);
            $used_stmt->bind_param("i", $token_id);

            if (!$used_stmt->execute() ||
                $used_stmt->affected_rows !== 1) {
                throw new Exception("Could not complete reset.");
            }

            $used_stmt->close();

            $conn->commit();

            $success = "Password changed successfully. You can now log in.";

            unset($_SESSION["reset_csrf_token"]);

        } catch (Throwable $e) {

            $conn->rollback();

            $error = "Password reset failed. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Customer Password</title>
</head>

<body>

    <h1>Reset Your Password</h1>

    <?php if (!empty($success)) { ?>

        <p style="color: green;">
            <?php echo htmlspecialchars($success); ?>
        </p>

        <a href="customer_login.php">Go to Customer Login</a>

    <?php } else { ?>

        <?php if (!empty($error)) { ?>

            <p style="color: red;">
                <?php echo htmlspecialchars($error); ?>
            </p>

        <?php } ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION["reset_csrf_token"]); ?>"
            >

            <label>New Password</label><br>

            <input
                type="password"
                name="new_password"
                required
                minlength="8"
            >

            <br><br>

            <label>Confirm New Password</label><br>

            <input
                type="password"
                name="confirm_password"
                required
                minlength="8"
            >

            <br><br>

            <button type="submit">
                Reset Password
            </button>

        </form>

    <?php } ?>

</body>

</html>
