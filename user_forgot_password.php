<?php

session_start();

require_once "config/database.php";

$error = "";
$success = "";

$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");

    if ($username === "") {

        $error = "Please enter your username.";

    } else {

        $stmt = $conn->prepare("
    SELECT user_id
    FROM users
    WHERE username = ?
    AND role IN ('staff', 'delivery')
    LIMIT 1
");

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1){

    $user = $result->fetch_assoc();
    $user_id = $user["user_id"];

    $check = $conn->prepare("
    SELECT request_id
    FROM user_password_reset_requests
    WHERE user_id = ?
    AND request_status = 'Pending'
    LIMIT 1
");

$check->bind_param("i", $user_id);
$check->execute();

$existing = $check->get_result();

if ($existing->num_rows === 0) {

    $insert = $conn->prepare("
        INSERT INTO user_password_reset_requests (user_id)
        VALUES (?)
    ");

    $insert->bind_param("i", $user_id);
    $insert->execute();

    $insert->close();
}

$check->close();

}

    $success = "If your account exists, a password reset request has been submitted. Please contact the administrator.";
 $stmt->close();
}

}
 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Staff Password Reset</title>
</head>

<body>

    <h2>Staff / Delivery Password Reset</h2>

    <p>Enter your username to request a password reset.</p>

    <?php if ($error !== "") { ?>
        <p style="color:red;">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php } ?>

    <?php if ($success !== "") { ?>
        <p style="color:green;">
            <?php echo htmlspecialchars($success); ?>
        </p>
    <?php } ?>

    <form method="POST">

        <label>Username:</label>

        <input
            type="text"
            name="username"
            value="<?php echo htmlspecialchars($username); ?>"
            required
        >

        <button type="submit">Submit Request</button>

    </form>

</body>
</html>
                                                                                                                        
                                                                                                                        