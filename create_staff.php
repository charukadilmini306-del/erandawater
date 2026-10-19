<?php
session_start();
require_once "config/database.php";

// Only Admin can create a Staff account
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    exit("Access denied. Admin only.");
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $full_name = trim($_POST["full_name"]);
    $password = $_POST["password"];

    if ($username === "" || $full_name === "" || strlen($password) < 8) {
        $message = "Enter all details. Password must have at least 8 characters.";
    } else {

        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE username = ?"
        );
        $check->bind_param("s", $username);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "Username already exists.";
        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $role = "staff";

            $stmt = $conn->prepare(
                "INSERT INTO users (username, full_name, password, role)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $username,
                $full_name,
                $hashed_password,
                $role
            );

            if ($stmt->execute()) {
                $message = "Staff account created successfully.";
            } else {
                $message = "Unable to create Staff account.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Staff Account</title>
</head>
<body>

<h2>Create Staff Account</h2>

<?php if ($message !== ""): ?>
    <p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<form method="POST">
    <label>Username:</label><br>
    <input type="text" name="username" required>
    <br><br>

    <label>Full Name:</label><br>
    <input type="text" name="full_name" required>
    <br><br>

    <label>Password (minimum 8 characters):</label><br>
    <input type="password" name="password" minlength="8" required>
    <br><br>

    <button type="submit">Create Staff Account</button>
</form>

</body>
</html>