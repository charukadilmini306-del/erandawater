<?php

session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = $_POST["full_name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];
    $customer_type = $_POST["customer_type"];

    $sql = "INSERT INTO customers (full_name, phone, address, customer_type)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $full_name, $phone, $address, $customer_type);

    if ($stmt->execute()) {
        $message = "Customer added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Eranda Water - Customers</title>
</head>

<body>

    <h1>Customer Management</h1>

    <a href="dashboard.php">Back to Dashboard</a>

    <hr>

    <h2>Add New Customer</h2>

    <?php if ($message != ""): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Full Name:</label><br>
        <input type="text" name="full_name" required>

        <br><br>

        <label>Phone:</label><br>
        <input type="text" name="phone" required>

        <br><br>

        <label>Address:</label><br>
        <textarea name="address" required></textarea>

        <br><br>

        <label>Customer Type:</label><br>
        <select name="customer_type" required>
            <option value="Home Delivery">Home Delivery</option>
            <option value="Walk-in">Walk-in</option>
            <option value="Bulk">Bulk</option>
        </select>

        <br><br>

        <button type="submit">Add Customer</button>

    </form>
    <hr>

<h2>Customer List</h2>

<table border="1" cellpadding="10">

    <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Phone</th>
        <th>Address</th>
        <th>Customer Type</th>
    </tr>

    <?php

    $sql = "SELECT * FROM customers ORDER BY customer_id DESC";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
    ?>

        <tr>
            <td><?php echo $row["customer_id"]; ?></td>
            <td><?php echo $row["full_name"]; ?></td>
            <td><?php echo $row["phone"]; ?></td>
            <td><?php echo $row["address"]; ?></td>
            <td><?php echo $row["customer_type"]; ?></td>
        </tr>

    <?php
    }
    ?>

</table>

</body>
</html>