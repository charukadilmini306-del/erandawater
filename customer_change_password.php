<?php

session_start();

require_once "config/database.php";

// Check customer login
if (
    !isset($_SESSION["customer_logged_in"]) ||
    $_SESSION["customer_logged_in"] !== true ||
    !isset($_SESSION["customer_id"])
) {
    header("Location: customer_login.php");
    exit();
}

// Check whether password change is required
if (
    !isset($_SESSION["customer_must_change_password"]) ||
    $_SESSION["customer_must_change_password"] !== 1
) {
    header("Location: customer_dashboard.php");
    exit();
}

$error = "";
$success = "";

// Generate CSRF token
if (empty($_SESSION["change_password_csrf"])) {
    $_SESSION["change_password_csrf"] = bin2hex(random_bytes(32));
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["change_password_csrf"],
            $csrf_token
        )
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $new_password = $_POST["new_password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if (strlen($new_password) < 8) {

            $error = "Password must contain at least 8 characters.";

        } elseif ($new_password !== $confirm_password) {

            $error = "Passwords do not match.";

        } else {

            $new_hash = password_hash(
    $new_password,
    PASSWORD_DEFAULT
);

$customer_id = (int) $_SESSION["customer_id"];

$update_sql = "
    UPDATE customer_accounts
    SET password_hash = ?,
        must_change_password = 0
    WHERE customer_id = ?
";

$update_stmt = $conn->prepare($update_sql);

$update_stmt->bind_param(
    "si",
    $new_hash,
    $customer_id
);

if ($update_stmt->execute()) {

    $_SESSION["customer_must_change_password"] = 0;

    $update_stmt->close();

    header("Location: customer_dashboard.php");
    exit();

} else {

    $error = "Password update failed. Please try again.";
    $update_stmt->close();
}

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
</head>

<body>

    <h2>Change Your Password</h2>

    <p>Your temporary password must be changed before continuing.</p>

    <?php if ($error !== ""): ?>
        <p style="color:red;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["change_password_csrf"]); ?>"
        >

        <label>New Password:</label><br>
        <input
            type="password"
            name="new_password"
            required
        >
        <br><br>

        <label>Confirm New Password:</label><br>
        <input
            type="password"
            name="confirm_password"
            required
        >
        <br><br>

        <button type="submit">Change Password</button>

    </form>

</body>
</html>