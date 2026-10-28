<?php

session_start();

if (
    !isset($_SESSION["customer_logged_in"]) ||
    $_SESSION["customer_logged_in"] !== true ||
    !isset($_SESSION["customer_id"])
) {
    header("Location: customer_login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: customer_my_orders.php");
    exit;
}

if (
    !isset($_SESSION["csrf_token"]) ||
    !isset($_POST["csrf_token"]) ||
    !hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"]
    )
) {
    header("Location: customer_my_orders.php?error=invalid_request");
    exit;
}

require_once "config/database.php";

$customer_id = (int) $_SESSION["customer_id"];

$order_id = filter_input(
    INPUT_POST,
    "order_id",
    FILTER_VALIDATE_INT
);

if (!$order_id || $order_id < 1) {
    header("Location: customer_my_orders.php?error=invalid_order");
    exit;
}

try {

    $conn->begin_transaction();

    // Find and lock this customer's order
    $stmt = $conn->prepare(
        "SELECT order_status
         FROM orders
         WHERE order_id = ?
           AND customer_id = ?
         FOR UPDATE"
    );

    $stmt->bind_param(
        "ii",
        $order_id,
        $customer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    $stmt->close();

    if (!$order) {
        throw new Exception("Order not found.");
    }

    if ($order["order_status"] !== "Pending") {
        throw new Exception("Order cannot be cancelled.");
    }

    // Get ordered products and quantities
    $stmt = $conn->prepare(
        "SELECT product_id, quantity
         FROM order_items
         WHERE order_id = ?"
    );

    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $items = [];

    while ($item = $result->fetch_assoc()) {
        $items[] = $item;
    }

    $stmt->close();

    if (count($items) === 0) {
        throw new Exception("Order items not found.");
    }

    // Restore product stock
    $stmt = $conn->prepare(
        "UPDATE products
         SET stock_quantity = stock_quantity + ?
         WHERE product_id = ?"
    );

    foreach ($items as $item) {

        $quantity = (int) $item["quantity"];
        $product_id = (int) $item["product_id"];

        $stmt->bind_param(
            "ii",
            $quantity,
            $product_id
        );

        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            throw new Exception("Unable to restore product stock.");
        }
    }

    $stmt->close();

    // Change the order status
    $stmt = $conn->prepare(
        "UPDATE orders
         SET order_status = 'Cancelled'
         WHERE order_id = ?
           AND customer_id = ?
           AND order_status = 'Pending'"
    );

    $stmt->bind_param(
        "ii",
        $order_id,
        $customer_id
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("Unable to cancel order.");
    }

    $stmt->close();

    $conn->commit();

    header("Location: customer_my_orders.php?cancelled=1");
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    header("Location: customer_my_orders.php?error=cannot_cancel");
    exit;
}