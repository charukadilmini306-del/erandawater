<?php

session_start();

require_once "config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Allow Admin, Staff and Delivery only
if (
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff", "delivery"], true)
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];
$full_name = $_SESSION["full_name"];

// Mark notification as read
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["mark_as_read"])
) {

    $notification_id = (int) ($_POST["notification_id"] ?? 0);
    $csrf_token = $_POST["csrf_token"] ?? "";

    if (
        !isset($_SESSION["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $csrf_token)
    ) {

        die("Invalid request.");

    }

    $update = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1
         WHERE notification_id = ?
         AND user_id = ?
         AND is_read = 0"
    );

    $update->bind_param(
        "ii",
        $notification_id,
        $user_id
    );

    $update->execute();
    $update->close();

    header("Location: notifications.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Get notifications for the logged-in user
$sql = "SELECT
            notification_id,
            notification_type,
            message,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$unread_count = 0;

while ($notification = $result->fetch_assoc()) {

    if ((int) $notification["is_read"] === 0) {
        $unread_count++;
    }

}

$stmt->close();

$result->data_seek(0);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Eranda Water - Notifications</title>
</head>

<body>

    <h1>Notifications</h1>

    <h3>
        Welcome, <?php echo htmlspecialchars($full_name); ?>!
    </h3>

    <p>
        Unread Notifications:
        <?php echo $unread_count; ?>
    </p>

    <h3>My Notifications</h3>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($notification = $result->fetch_assoc()): ?>

            <div>

                <h4>
                    <?php echo htmlspecialchars($notification["notification_type"]); ?>
                </h4>

                <p>
                    <?php echo htmlspecialchars($notification["message"]); ?>
                </p>

                <p>
                    <?php echo htmlspecialchars($notification["created_at"]); ?>
                </p>

                <p>
                    Status:
                    <?php
                    echo ((int) $notification["is_read"] === 1)
                        ? "Read"
                        : "Unread";
                    ?>
                </p>
                      <?php if ((int) $notification["is_read"] === 0): ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"
        >

        <input
            type="hidden"
            name="notification_id"
            value="<?php echo (int) $notification["notification_id"]; ?>"
        >

        <button type="submit" name="mark_as_read">
            Mark as Read
        </button>

          </form>

              <?php endif; ?>
                <hr>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No notifications available.</p>

    <?php endif; ?>

    <a href="<?php echo ($role === "delivery") ? "delivery_dashboard.php" : "dashboard.php"; ?>">
        Back to Dashboard
    </a>

</body>
</html>