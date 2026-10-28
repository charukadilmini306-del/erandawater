<?php

session_start();

require_once "config/database.php";

$error = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";

    if (!preg_match("/^[0-9]{10}$/", $phone)) {

        $error = "Please enter a valid 10-digit phone number.";

    } else {

        $stmt = $conn->prepare(
            "SELECT c.customer_id, c.full_name, ca.password_hash
             FROM customers c
             INNER JOIN customer_accounts ca
             ON c.customer_id = ca.customer_id
             WHERE c.phone = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $phone);
        $stmt->execute();

        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();

        $stmt->close();

        if (
            $customer &&
            password_verify($password, $customer["password_hash"])
        ) {

            session_regenerate_id(true);

            $_SESSION["customer_id"] = (int) $customer["customer_id"];
            $_SESSION["customer_name"] = $customer["full_name"];
            $_SESSION["customer_logged_in"] = true;

            header("Location: customer_dashboard.php");
            exit;

        } else {

            $error = "Invalid phone number or password.";

        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - Eranda Water</title>
</head>
<body>

    <h1>Eranda Water</h1>
    <h2>Customer Login</h2>

    <?php if ($error !== ""): ?>
        <p>
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="customer_login.php">

        <label>Phone Number:</label><br>

        <input
            type="tel"
            name="phone"
            pattern="[0-9]{10}"
            minlength="10"
            maxlength="10"
            inputmode="numeric"
            value="<?php echo htmlspecialchars($phone); ?>"
            required
        >

        <br><br>

        <label>Password:</label><br>

        <input
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <br><br>

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="customer_register.php">Register here</a>
    </p>

</body>
</html>