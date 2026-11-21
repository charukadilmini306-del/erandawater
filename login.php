
<?php
session_start();
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {

                header("Location: admin_dashboard.php");

            } elseif ($user["role"] === "staff") {

                header("Location: dashboard.php");

            } elseif ($user["role"] === "delivery") {

                header("Location: delivery_dashboard.php");

            } else {

                session_unset();
                session_destroy();

                header("Location: login.php");
            }

            exit();

        } else {

            $message = "Invalid username or password.";
        }

    } else {

        $message = "Login error. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Eranda Water - Login</title>
</head>

<body>

    <h2>Eranda Water Management System</h2>

    <h3>Login</h3>

    <?php if ($message !== ""): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Username:</label><br>
        <input type="text" name="username" required>

        <br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required>

        <br><br>

        <button type="submit">Login</button>

    </form>
        

    <p>
        <a href="user_forgot_password.php">
            Forgot Password?
        </a>
    </p>

</body>
</html>


