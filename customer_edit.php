<?php

session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: customers.php");
    exit();
}

$customer_id = $_GET["id"];

$sql = "SELECT * FROM customers WHERE customer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Customer not found.";
    exit();
}

$customer = $result->fetch_assoc();

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = $_POST["full_name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];
    $customer_type = $_POST["customer_type"];

    $sql = "UPDATE customers 
            SET full_name = ?, phone = ?, address = ?, customer_type = ?
            WHERE customer_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssi",
        $full_name,
        $phone,
        $address,
        $customer_type,
        $customer_id
    );

    if ($stmt->execute()) {
        header("Location: customers.php");
        exit();
    } else {
        $message = "Error: " . $stmt->error;
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Customer - Eranda Water</title>
</head>

<body>

    <h1>Edit Customer</h1>

    <a href="customers.php">Back to Customer List</a>

    <hr>

    <?php if ($message != ""): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Full Name:</label><br>

        <input 
            type="text" 
            name="full_name"
            value="<?php echo htmlspecialchars($customer["full_name"]); ?>"
            required
        >

        <br><br>

        <label>Phone:</label><br>

        <input 
            type="text" 
            name="phone"
            value="<?php echo htmlspecialchars($customer["phone"]); ?>"
            required
        >

        <br><br>

        <label>Address:</label><br>

        <textarea 
            name="address"
            required
        ><?php echo htmlspecialchars($customer["address"]); ?></textarea>

        <br><br>

        <label>Customer Type:</label><br>

        <select name="customer_type" required>

            <option value="Home Delivery"
                <?php if ($customer["customer_type"] == "Home Delivery") echo "selected"; ?>>
                Home Delivery
            </option>

            <option value="Walk-in"
                <?php if ($customer["customer_type"] == "Walk-in") echo "selected"; ?>>
                Walk-in
            </option>

            <option value="Bulk"
                <?php if ($customer["customer_type"] == "Bulk") echo "selected"; ?>>
                Bulk
            </option>

        </select>

        <br><br>

        <button type="submit">Update Customer</button>

    </form>

</body>

</html>