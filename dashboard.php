<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Eranda Water - Dashboard</title>
</head>

<body>

    <h1>Eranda Water Management System</h1>

    <h2>Welcome, <?php echo $_SESSION["full_name"]; ?>!</h2>

    <p>Login successful.</p>

    <a href="logout.php">Logout</a>

</body>
</html>