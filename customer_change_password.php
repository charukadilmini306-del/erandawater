
<?php

session_start();

require_once "config/database.php";

// CHECK CUSTOMER LOGIN
if (
    !isset($_SESSION["customer_logged_in"]) ||
    $_SESSION["customer_logged_in"] !== true ||
    !isset($_SESSION["customer_id"])
) {
    header("Location: customer_login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

$error = "";
$success = "";

// CSRF TOKEN
if (empty($_SESSION["change_password_csrf"])) {
    $_SESSION["change_password_csrf"] = bin2hex(random_bytes(32));
}

// PROCESS PASSWORD CHANGE
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["change_password_csrf"], $csrf_token)
    ) {
        $error = "Invalid request. Please try again.";

    } else {

        $current_password = $_POST["current_password"] ?? "";
        $new_password = $_POST["new_password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if (
            $current_password === "" ||
            $new_password === "" ||
            $confirm_password === ""
        ) {
            $error = "Please fill in all password fields.";

        } elseif (strlen($new_password) < 8) {

            $error = "New password must contain at least 8 characters.";

        } elseif ($new_password !== $confirm_password) {

            $error = "New passwords do not match.";

        } elseif ($current_password === $new_password) {

            $error = "New password must be different from current password.";

        } else {

            // GET CURRENT PASSWORD HASH
            $stmt = $conn->prepare(
                "SELECT password_hash
                 FROM customer_accounts
                 WHERE customer_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $customer_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $account = $result->fetch_assoc();

            $stmt->close();

            if (
                !$account ||
                !password_verify(
                    $current_password,
                    $account["password_hash"]
                )
            ) {
                $error = "Current password is incorrect.";

            } else {

                // HASH NEW PASSWORD
                $new_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                // UPDATE PASSWORD
                $update_stmt = $conn->prepare(
                    "UPDATE customer_accounts
                     SET password_hash = ?,
                         must_change_password = 0
                     WHERE customer_id = ?"
                );

                $update_stmt->bind_param(
                    "si",
                    $new_hash,
                    $customer_id
                );

                if ($update_stmt->execute()) {

                    $_SESSION["customer_must_change_password"] = 0;

                    // REGENERATE CSRF TOKEN
                    $_SESSION["change_password_csrf"] =
                        bin2hex(random_bytes(32));

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
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Eranda Water</title>
</head>

<body>

    <h1>Eranda Water</h1>

    <h2>Change Your Password</h2>

    <p>Enter your current password and choose a new password.</p>

    <?php if ($error !== ""): ?>
        <p style="color:red;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="customer_change_password.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["change_password_csrf"]); ?>"
        >

        <label>Current Password:</label><br>
        <input
            type="password"
            name="current_password"
            required
        >

        <br><br>

        <label>New Password:</label><br>
        <input
            type="password"
            name="new_password"
            minlength="8"
            required
        >

        <br><br>

        <label>Confirm New Password:</label><br>
        <input
            type="password"
            name="confirm_password"
            minlength="8"
            required
        >

        <br><br>

        <button type="submit">Change Password</button>

    </form>

    <p>
        <a href="customer_dashboard.php">Back to Dashboard</a>
    </p>

</body>
</html>
