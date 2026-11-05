<?php

require_once "config/database.php";

$message = "";
$error = "";

$full_name = "";
$phone = "";
$email = "";
$address = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $error = "Phone number must contain exactly 10 digits.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Please enter a valid email address.";
} elseif (strlen($password) < 8) {
    $error = "Password must contain at least 8 characters.";

} elseif ($password !== $confirm_password) {
    $error = "Passwords do not match.";

} else {

    $stmt = $conn->prepare(
            "SELECT customer_id FROM customers WHERE phone = ? LIMIT 1" );

        $stmt->bind_param("s", $phone);
        $stmt->execute();

        $result = $stmt->get_result();
$phone_exists = ($result->num_rows > 0);

$stmt->close();
$stmt = $conn->prepare(
    "SELECT customer_id FROM customers WHERE email = ? LIMIT 1"
);

$stmt->bind_param("s", $email);
$stmt->execute();

$email_result = $stmt->get_result();
$email_exists = ($email_result->num_rows > 0);

$stmt->close();

if ($phone_exists) {
    $error = "This phone number already exists in business records. Please contact the administrator to verify your details.";

} elseif ($email_exists) {
    $error = "This email address is already registered. Please use another email.";

} else {



            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $conn->begin_transaction();

            try {

                $stmt = $conn->prepare(
                    "INSERT INTO customers
                    (full_name, phone,email, address, customer_type)
                    VALUES (?, ?, ?, ?,'Home Delivery')"
                );

                $stmt->bind_param("ssss", $full_name, $phone,$email, $address);
                $stmt->execute();

                $customer_id = $conn->insert_id;
                $stmt->close();

                $stmt = $conn->prepare(
                    "INSERT INTO customer_accounts
                    (customer_id, password_hash)
                    VALUES (?, ?)"
                );

                $stmt->bind_param("is", $customer_id, $password_hash);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                $message = "Account created successfully!";

                $full_name = "";
                $phone = "";
                $email = "";
                $address = "";

            } catch (Throwable $e) {

                $conn->rollback();

                $error = "Unable to create account. Please try again.";

                error_log($e->getMessage());
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
    <title>Customer Registration - Eranda Water</title>
</head>
<body>

    <h1>Eranda Water</h1>
    <h2>Create Customer Account</h2>
    <?php if ($error !== ""): ?>
    <p><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if ($message !== ""): ?>
    <p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

    <form method="POST" action="customer_register.php">

        <label>Full Name:</label><br>
        <input type="text" name="full_name" maxlength="100" required>
        <br><br>

        <label>Phone Number:</label><br>
        <input
            type="tel"
            name="phone"
            pattern="[0-9]{10}"
            minlength="10"
            maxlength="10"
            inputmode="numeric"
            required
        >
        <br><br>
        <label>Email Address:</label><br>
          <input
              type="email"
              name="email"
              maxlength="150"
              autocomplete="email"
              required
            >
        <br><br>

        <label>Address:</label><br>
        <textarea name="address" required></textarea>
        <br><br>

        <label>Password:</label><br>
        <input
            type="password"
            name="password"
            minlength="8"
            autocomplete="new-password"
            required
        >
        <br><br>

        <label>Confirm Password:</label><br>
        <input
            type="password"
            name="confirm_password"
            minlength="8"
            autocomplete="new-password"
            required
        >
        <br><br>

        <button type="submit">Create Account</button>

    </form>

</body>
</html>