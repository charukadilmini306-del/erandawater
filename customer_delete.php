<?php

session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET["id"])) {

    $customer_id = $_GET["id"];

    $sql = "DELETE FROM customers WHERE customer_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {
        header("Location: customers.php");
        exit();
    } else {
        echo "Error deleting customer: " . $stmt->error;
    }

} else {
    header("Location: customers.php");
    exit();
}
?>