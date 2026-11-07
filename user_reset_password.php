<?php

session_start();

require_once "config/database.php";

$error = "";
$success = "";

$token = trim($_GET["token"] ?? "");

if ($token === "" || !preg_match("/^[a-f0-9]{64}$/", $token)) {
    $error = "Invalid or missing password reset link.";
}

$token_hash = hash("sha256", $token);

$user_id = 0;

if ($error === "") {

    $stmt = $conn->prepare("
        SELECT user_id
        FROM user_password_reset_tokens
        WHERE token_hash = ?
        AND expires_at > NOW()
        AND used_at IS NULL
        LIMIT 1
    ");

    $stmt->bind_param("s", $token_hash);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $row = $result->fetch_assoc();

        $user_id = (int) $row["user_id"];

    } else {

        $error = "This password reset link is invalid or expired.";

    }

    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (strlen($password) < 8) {

        $error = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        $new_password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $conn->begin_transaction();

        try {

            $check_stmt = $conn->prepare("
                SELECT token_id
                FROM user_password_reset_tokens
                WHERE token_hash = ?
                AND user_id = ?
                AND expires_at > NOW()
                AND used_at IS NULL
                FOR UPDATE
            ");

            $check_stmt->bind_param(
                "si",
                $token_hash,
                $user_id
            );

            $check_stmt->execute();

            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows !== 1) {
                throw new Exception("Invalid or expired token.");
            }

            $token_row = $check_result->fetch_assoc();
            $token_id = (int) $token_row["token_id"];

            $check_stmt->close();

            $update_stmt = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE user_id = ?
                AND role IN ('staff', 'delivery')
            ");

            $update_stmt->bind_param(
                "si",
                $new_password_hash,
                $user_id
            );

            if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                throw new Exception("Password update failed.");
            }

            $update_stmt->close();

            $used_stmt = $conn->prepare("
                UPDATE user_password_reset_tokens
                SET used_at = NOW()
                WHERE token_id = ?
            ");

            $used_stmt->bind_param("i", $token_id);
            $used_stmt->execute();
            $used_stmt->close();

            $conn->commit();

            $success = "Your password has been changed successfully.";

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
    <title>Reset Password</title>
</head>

<body>

    <h2>Staff / Delivery Password Reset</h2>

    <?php if ($error !== "") { ?>

        <p style="color:red;">
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php } ?>

    <?php if ($success !== "") { ?>

        <p style="color:green;">
            <?php echo htmlspecialchars($success); ?>
        </p>

        <a href="login.php">Go to Login</a>

    <?php } ?>

    <?php if ($user_id > 0 && $success === "") { ?>

        <form method="POST">

            <label>New Password:</label>
            <input
                type="password"
                name="password"
                minlength="8"
                required
            >

            <br><br>

            <label>Confirm Password:</label>
            <input
                type="password"
                name="confirm_password"
                minlength="8"
                required
            >

            <br><br>

            <button type="submit">Reset Password</button>

        </form>

    <?php } ?>

</body>

</html>