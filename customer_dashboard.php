<?php

session_start();

if (
    !isset($_SESSION["customer_logged_in"]) ||
    $_SESSION["customer_logged_in"] !== true ||
    !isset($_SESSION["customer_id"])
) {
    header("Location: customer_login.php");
    exit;
}

$customer_name = $_SESSION["customer_name"] ?? "Customer";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Eranda Water</title>
</head>
<body>

    <h1>Eranda Water</h1>

    <h2>
        Welcome,
        <?php echo htmlspecialchars($customer_name); ?>!
    </h2>

    <p>You are successfully logged in to your customer account.</p>

    <p>Welcome to the Eranda Water Customer Portal.</p>
    <p>
    <a href="customer_my_orders.php">My Orders</a>
    </p>

    <a href="customer_logout.php">Logout</a>

</body>
</html>