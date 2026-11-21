
<?php

session_start();

require_once "config/database.php";

// Allow only Staff and Delivery users
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["staff", "delivery"], true)
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$error = "";
$success = "";

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrf_token = $_POST["csrf_token"] ?? "";

    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {

        $error = "Invalid request. Please try again.";

    } elseif (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {

        $error = "Please fill in all fields.";

    } elseif (strlen($new_password) < 8) {

        $error = "New password must contain at least 8 characters.";

    } elseif ($new_password !== $confirm_password) {

        $error = "New passwords do not match.";

    } else {

        // Get current password hash
        $stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE user_id = ?
            AND role IN ('staff', 'delivery')
            LIMIT 1
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if (!$user || !password_verify($current_password, $user["password"])) {

            $error = "Current password is incorrect.";

        } elseif (password_verify($new_password, $user["password"])) {

            $error = "New password must be different from current password.";

        } else {

            $new_password_hash = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $update = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE user_id = ?
                AND role IN ('staff', 'delivery')
            ");

            $update->bind_param(
                "si",
                $new_password_hash,
                $user_id
            );

            if ($update->execute() && $update->affected_rows === 1) {

                $success = "Password changed successfully.";

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            } else {

                $error = "Password change failed. Please try again.";

            }

            $update->close();
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

    <h2>Change Password</h2>

    <?php if ($error !== ""): ?>

        <p style="color:red;">
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <p style="color:green;">
            <?php echo htmlspecialchars($success); ?>
        </p>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
        >

        <label>Current Password:</label><br>
        <input type="password" name="current_password" required>

        <br><br>

        <label>New Password:</label><br>
        <input type="password" name="new_password" minlength="8" required>

        <br><br>

        <label>Confirm New Password:</label><br>
        <input type="password" name="confirm_password" minlength="8" required>

        <br><br>

        <button type="submit">Change Password</button>

    </form>

    <br>

    <?php if ($_SESSION["role"] === "delivery"): ?>

        <a href="delivery_dashboard.php">Back to Dashboard</a>

    <?php else: ?>

        <a href="dashboard.php">Back to Dashboard</a>

    <?php endif; ?>

</body>

</html>
