<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

  require_once "config/database.php";

$user_id = (int) $_SESSION["user_id"];

$unread_count = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS unread_count
     FROM notifications
     WHERE user_id = ?
     AND is_read = 0"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$unread_count = (int) $row["unread_count"];

$stmt->close();
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
    <p>
    <a href="notifications.php">
        Notifications
        (<?php echo $unread_count; ?> Unread)
    </a>
     </p>

    <a href="logout.php">Logout</a>

</body>
</html>