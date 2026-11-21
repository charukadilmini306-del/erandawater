
<?php

session_start();

require_once "config/database.php";

$message = "";
$error = "";

$full_name = "";
$phone = "";
$address = "";
$customer_type = "Home Delivery";

$products = [];
$selected_products = [];
$quantities = [];

$total_amount = 0;

$customer_data = null;
$transaction_started = false;


// ==================================================
// 1. CHECK LOGIN ACCESS
// ==================================================

// Customer login check
$is_customer = (
    isset($_SESSION["customer_logged_in"]) &&
    $_SESSION["customer_logged_in"] === true &&
    isset($_SESSION["customer_id"])
);

// Admin / Staff login check
$is_employee = (
    isset($_SESSION["user_id"]) &&
    isset($_SESSION["role"]) &&
    in_array($_SESSION["role"], ["admin", "staff"], true)
);

// Deny unauthorized users
if (!$is_customer && !$is_employee) {
    header("Location: login.php");
    exit();
}


// ==================================================
// 2. FORCE CUSTOMER PASSWORD CHANGE
// ==================================================

if (
    $is_customer &&
    isset($_SESSION["customer_must_change_password"]) &&
    (int) $_SESSION["customer_must_change_password"] === 1
) {
    header("Location: customer_change_password.php");
    exit();
}


// ==================================================
// 3. CREATE CSRF TOKEN
// ==================================================

if (empty($_SESSION["order_csrf_token"])) {
    $_SESSION["order_csrf_token"] = bin2hex(random_bytes(32));
}


// ==================================================
// 4. LOAD LOGGED-IN CUSTOMER DETAILS
// ==================================================

if ($is_customer) {

    $customer_id = (int) $_SESSION["customer_id"];

    $sql = "SELECT full_name, phone, address, customer_type
            FROM customers
            WHERE customer_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("i", $customer_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        die("Customer account not found.");
    }

    $customer_data = $result->fetch_assoc();

    $full_name = $customer_data["full_name"];
    $phone = $customer_data["phone"];
    $address = $customer_data["address"];
    $customer_type = $customer_data["customer_type"];

    $stmt->close();
}


// ==================================================
// 5. LOAD AVAILABLE PRODUCTS
// ==================================================

$sql = "SELECT product_id, product_name, price, stock_quantity
        FROM products
        WHERE status = 'Available'
        AND stock_quantity > 0
        ORDER BY product_name";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}


// ==================================================
// 6. PROCESS ORDER FORM
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF validation
    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($_SESSION["order_csrf_token"], $submitted_token)
    ) {

        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        // ------------------------------------------
        // CUSTOMER ORDER DETAILS
        // ------------------------------------------

        if ($is_customer) {

            // Customer details must come from database
            $full_name = $customer_data["full_name"];
            $phone = $customer_data["phone"];
            $customer_type = $customer_data["customer_type"];

            // Customer can enter a different delivery address
            $address = trim($_POST["address"] ?? "");

            if ($address === "") {
                $address = $customer_data["address"];
            }

        } else {

            // Admin and Staff can enter customer details
            $full_name = trim($_POST["full_name"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $address = trim($_POST["address"] ?? "");
            $customer_type = $_POST["customer_type"] ?? "";

        }


        $selected_products = $_POST["product_id"] ?? [];
        $quantities = $_POST["quantity"] ?? [];


        // ------------------------------------------
        // VALIDATE CUSTOMER DETAILS
        // ------------------------------------------

        if (!preg_match("/^[0-9]{10}$/", $phone)) {

            $error = "Please enter a valid 10-digit phone number.";

        } elseif (
            !in_array(
                $customer_type,
                ["Home Delivery", "Bulk", "Walk-in"],
                true
            )
        ) {

            $error = "Please select a valid customer type.";

        } elseif (
            !is_array($selected_products) ||
            !is_array($quantities) ||
            empty($selected_products) ||
            count($selected_products) !== count($quantities)
        ) {

            $error = "Please select a product and quantity.";

        } elseif (
            $address === ""
        ) {

            $error = "Please enter the delivery address.";

        } else {


            // ======================================
            // 7. START DATABASE TRANSACTION
            // ======================================

            try {

                $conn->begin_transaction();

                $transaction_started = true;


                // ==================================
                // 8. FIND OR IDENTIFY CUSTOMER
                // ==================================

                if ($is_customer) {

                    // Use logged-in customer's ID
                    // Never identify a customer by submitted phone number

                    $customer_id = (int) $_SESSION["customer_id"];

                    $delivery_address = $address;

                } else {

                    // Admin / Staff: Find customer by phone

                    $sql = "SELECT customer_id, address
                            FROM customers
                            WHERE phone = ?
                            LIMIT 1
                            FOR UPDATE";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param("s", $phone);

                    $stmt->execute();

                    $result = $stmt->get_result();


                    if ($result->num_rows > 0) {

                        // Existing customer

                        $customer = $result->fetch_assoc();

                        $customer_id = (int) $customer["customer_id"];

                        $delivery_address = ($address !== "")
                            ? $address
                            : $customer["address"];

                    } else {

                        // Create new customer

                        if ($full_name === "") {
                            throw new Exception(
                                "Please enter the customer's full name."
                            );
                        }

                        if ($address === "") {
                            throw new Exception(
                                "Please enter the customer's address."
                            );
                        }

                        $sql = "INSERT INTO customers
                                (
                                    full_name,
                                    phone,
                                    address,
                                    customer_type
                                )
                                VALUES (?, ?, ?, ?)";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "ssss",
                            $full_name,
                            $phone,
                            $address,
                            $customer_type
                        );

                        $stmt->execute();

                        $customer_id = $conn->insert_id;

                        $delivery_address = $address;

                    }

                    $stmt->close();

                }


                // ==================================
                // 9. CHECK PRODUCTS AND STOCK
                // ==================================

                $order_items = [];

                $low_stock_products = [];

                $total_amount = 0;

                $used_products = [];


                foreach ($selected_products as $index => $value) {

                    $product_id = filter_var(
                        $value,
                        FILTER_VALIDATE_INT
                    );

                    $quantity = filter_var(
                        $quantities[$index] ?? null,
                        FILTER_VALIDATE_INT
                    );


                    // Validate product ID
                    if (
                        $product_id === false ||
                        $product_id === null ||
                        $product_id < 1
                    ) {
                        throw new Exception(
                            "Please select a valid product."
                        );
                    }


                    // Validate quantity
                    if (
                        $quantity === false ||
                        $quantity === null ||
                        $quantity < 1
                    ) {
                        throw new Exception(
                            "Quantity must be at least 1."
                        );
                    }


                    // Prevent duplicate product selection
                    if (isset($used_products[$product_id])) {

                        throw new Exception(
                            "Please select each product only once."
                        );

                    }

                    $used_products[$product_id] = true;


                    // Get product details and lock stock row
                    $sql = "SELECT
                                product_id,
                                product_name,
                                price,
                                stock_quantity,
                                status
                            FROM products
                            WHERE product_id = ?
                            FOR UPDATE";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param("i", $product_id);

                    $stmt->execute();

                    $result = $stmt->get_result();


                    if ($result->num_rows === 0) {

                        throw new Exception(
                            "Selected product was not found."
                        );

                    }


                    $product = $result->fetch_assoc();


                    // Check product availability
                    if ($product["status"] !== "Available") {

                        throw new Exception(
                            $product["product_name"] .
                            " is not available."
                        );

                    }


                    // Check available stock
                    if (
                        $quantity > (int) $product["stock_quantity"]
                    ) {

                        throw new Exception(
                            "Insufficient stock for " .
                            $product["product_name"] . "."
                        );

                    }


                    $unit_price = (float) $product["price"];

                    $item_total = $unit_price * $quantity;

                    $total_amount += $item_total;


                    // Store order item details
                    $order_items[] = [

                        "product_id" => (int) $product["product_id"],

                        "product_name" => $product["product_name"],

                        "quantity" => $quantity,

                        "unit_price" => $unit_price,

                        "stock_before" => (int) $product["stock_quantity"]

                    ];

                }


                // ==================================
                // 10. SAVE ORDER
                // ==================================

                $order_status = "Pending";

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            order_date,
                            delivery_address,
                            order_status,
                            total_amount
                        )
                        VALUES (?, NOW(), ?, ?, ?)";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "issd",
                    $customer_id,
                    $delivery_address,
                    $order_status,
                    $total_amount
                );

                $stmt->execute();

                $order_id = $conn->insert_id;

                $stmt->close();


                // ==================================
                // 11. SAVE ORDER ITEMS AND UPDATE STOCK
                // ==================================

                foreach ($order_items as $item) {

                    // Save order item
                    $sql = "INSERT INTO order_items
                            (
                                order_id,
                                product_id,
                                quantity,
                                unit_price
                            )
                            VALUES (?, ?, ?, ?)";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param(
                        "iiid",
                        $order_id,
                        $item["product_id"],
                        $item["quantity"],
                        $item["unit_price"]
                    );

                    $stmt->execute();

                    $stmt->close();


                    // Reduce product stock
                    $sql = "UPDATE products
                            SET stock_quantity = stock_quantity - ?
                            WHERE product_id = ?
                            AND stock_quantity >= ?";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param(
                        "iii",
                        $item["quantity"],
                        $item["product_id"],
                        $item["quantity"]
                    );

                    $stmt->execute();


                    if ($stmt->affected_rows !== 1) {

                        throw new Exception(
                            "Stock update failed. Please try again."
                        );

                    }

                    $stmt->close();


                    // Check remaining stock
                    $remaining_stock =
                        $item["stock_before"] - $item["quantity"];


                    // Identify low stock products
                    if (
                        $item["stock_before"] > 10 &&
                        $remaining_stock <= 10
                    ) {

                        $low_stock_products[] = [

                            "product_id" => $item["product_id"],

                            "product_name" => $item["product_name"],

                            "stock_quantity" => $remaining_stock

                        ];

                    }

                }


                // ==================================
                // 12. COMMIT DATABASE CHANGES
                // ==================================

                $conn->commit();

                $transaction_started = false;


                // ==================================
                // 13. SEND LOW STOCK NOTIFICATIONS
                // ==================================

                try {

                    foreach ($low_stock_products as $low_product) {

                        $low_stock_message =
                            $low_product["product_name"] .
                            " is running low. Remaining stock: " .
                            $low_product["stock_quantity"];


                        $users_sql = "SELECT user_id
                                      FROM users
                                      WHERE role IN ('admin', 'staff')";

                        $users_result = $conn->query($users_sql);


                        if ($users_result) {

                            $notification_sql = "INSERT INTO notifications
                                                 (
                                                     user_id,
                                                     notification_type,
                                                     message,
                                                     is_read
                                                 )
                                                 VALUES (?, 'Low Stock', ?, 0)";

                            $notification_stmt = $conn->prepare(
                                $notification_sql
                            );


                            while ($user = $users_result->fetch_assoc()) {

                                $notification_user_id =
                                    (int) $user["user_id"];

                                $notification_stmt->bind_param(
                                    "is",
                                    $notification_user_id,
                                    $low_stock_message
                                );

                                $notification_stmt->execute();

                            }


                            $notification_stmt->close();

                            $users_result->free();

                        }

                    }

                } catch (Throwable $notification_error) {

                    error_log(
                        "Low Stock Notification Error: " .
                        $notification_error->getMessage()
                    );

                }


                // ==================================
                // 14. SEND NEW ORDER NOTIFICATIONS
                // ==================================

                try {

                    $notification_message =
                        "New Order #" . $order_id .
                        " has been placed. Total: Rs. " .
                        number_format($total_amount, 2);


                    $users_sql = "SELECT user_id
                                  FROM users
                                  WHERE role IN ('admin', 'staff')";

                    $users_result = $conn->query($users_sql);


                    if ($users_result) {

                        $notification_sql = "INSERT INTO notifications
                                             (
                                                 user_id,
                                                 notification_type,
                                                 message,
                                                 is_read
                                             )
                                             VALUES (?, 'New Order', ?, 0)";

                        $notification_stmt = $conn->prepare(
                            $notification_sql
                        );


                        while ($user = $users_result->fetch_assoc()) {

                            $notification_user_id =
                                (int) $user["user_id"];

                            $notification_stmt->bind_param(
                                "is",
                                $notification_user_id,
                                $notification_message
                            );

                            $notification_stmt->execute();

                        }


                        $notification_stmt->close();

                        $users_result->free();

                    }

                } catch (Throwable $notification_error) {

                    error_log(
                        "Notification Error: " .
                        $notification_error->getMessage()
                    );

                }


                // ==================================
                // 15. SUCCESS MESSAGE
                // ==================================

                $message = "Order placed successfully! " .
                           "Order ID: " . $order_id .
                           " | Total: Rs. " .
                           number_format($total_amount, 2);


                // Clear product selections
                $selected_products = [];

                $quantities = [];


                // Clear employee customer details
                if ($is_employee) {

                    $full_name = "";
                    $phone = "";
                    $address = "";
                    $customer_type = "Home Delivery";

                } else {

                    // Keep logged-in customer's details
                    $full_name = $customer_data["full_name"];
                    $phone = $customer_data["phone"];
                    $address = $customer_data["address"];
                    $customer_type = $customer_data["customer_type"];

                }


                // ==================================
                // 16. RELOAD PRODUCTS AFTER STOCK UPDATE
                // ==================================

                $products = [];

                $sql = "SELECT
                            product_id,
                            product_name,
                            price,
                            stock_quantity
                        FROM products
                        WHERE status = 'Available'
                        AND stock_quantity > 0
                        ORDER BY product_name";

                $result = $conn->query($sql);


                if ($result) {

                    while ($row = $result->fetch_assoc()) {
                        $products[] = $row;
                    }

                }


                // Generate a new CSRF token after successful order
                $_SESSION["order_csrf_token"] = bin2hex(random_bytes(32));


            } catch (Throwable $e) {

                // Roll back only if transaction is still active
                if ($transaction_started) {

                    $conn->rollback();

                    $transaction_started = false;

                }

                $error = $e->getMessage();

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

    <title>Eranda Water - Place Order</title>

</head>

<body>

<h1>Eranda Water</h1>

<h2>Place Your Water Order</h2>


<?php if ($message !== ""): ?>

    <p><?= htmlspecialchars($message) ?></p>

<?php endif; ?>


<?php if ($error !== ""): ?>

    <p><?= htmlspecialchars($error) ?></p>

<?php endif; ?>


<form method="POST" action="order.php">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION["order_csrf_token"]) ?>"
    >


    <h3>Customer Details</h3>


    <?php if ($is_customer): ?>

        <!-- Customer account details -->

        <label>Full Name:</label><br>

        <input
            type="text"
            value="<?= htmlspecialchars($full_name) ?>"
            readonly
        >

        <br><br>


        <label>Phone Number:</label><br>

        <input
            type="tel"
            value="<?= htmlspecialchars($phone) ?>"
            readonly
        >

        <br><br>


        <label>Customer Type:</label><br>

        <input
            type="text"
            value="<?= htmlspecialchars($customer_type) ?>"
            readonly
        >

        <br><br>


    <?php else: ?>

        <!-- Admin / Staff customer details -->

        <label for="full_name">Full Name:</label><br>

        <input
            type="text"
            id="full_name"
            name="full_name"
            value="<?= htmlspecialchars($full_name) ?>"
            maxlength="100"
        >

        <br><br>


        <label for="phone">Phone Number:</label><br>

        <input
            type="tel"
            id="phone"
            name="phone"
            value="<?= htmlspecialchars($phone) ?>"
            pattern="[0-9]{10}"
            minlength="10"
            maxlength="10"
            inputmode="numeric"
            required
        >

        <br><br>


        <label for="customer_type">Customer Type:</label><br>

        <select id="customer_type" name="customer_type" required>

            <option
                value="Home Delivery"
                <?= $customer_type === "Home Delivery" ? "selected" : "" ?>
            >
                Home Delivery
            </option>

            <option
                value="Bulk"
                <?= $customer_type === "Bulk" ? "selected" : "" ?>
            >
                Bulk
            </option>

            <option
                value="Walk-in"
                <?= $customer_type === "Walk-in" ? "selected" : "" ?>
            >
                Walk-in
            </option>

        </select>

        <br><br>

    <?php endif; ?>


    <label for="address">Delivery Address:</label><br>

    <textarea
        id="address"
        name="address"
        rows="3"
        required
    ><?= htmlspecialchars($address) ?></textarea>

    <br><br>


    <hr>

    <h3>Select Water Products</h3>


    <div id="product-container">

        <div class="product-row">

            <label>Select Product:</label><br>

            <select name="product_id[]" required>

                <option value="">-- Select a Product --</option>

                <?php foreach ($products as $product): ?>

                    <option value="<?= (int) $product["product_id"] ?>">

                        <?= htmlspecialchars($product["product_name"]) ?>

                        - Rs. <?= number_format(
                            (float) $product["price"],
                            2
                        ) ?>

                        (Stock: <?= (int) $product["stock_quantity"] ?>)

                    </option>

                <?php endforeach; ?>

            </select>

            <br><br>


            <label>Quantity:</label><br>

            <input
                type="number"
                name="quantity[]"
                min="1"
                step="1"
                required
            >

            <br><br>


            <button type="button" onclick="removeProduct(this)">
                Remove Product
            </button>

            <br><br>

        </div>

    </div>


    <button type="button" onclick="addProduct()">
        Add Another Product
    </button>

    <br><br>


    <button type="submit">
        Place Order
    </button>


</form>


<script>

function addProduct() {

    const container = document.getElementById("product-container");

    const firstRow = container.querySelector(".product-row");

    const newRow = firstRow.cloneNode(true);

    newRow.querySelector("select").selectedIndex = 0;

    newRow.querySelector('input[name="quantity[]"]').value = "";

    container.appendChild(newRow);

}


function removeProduct(button) {

    const container = document.getElementById("product-container");

    const rows = container.querySelectorAll(".product-row");

    if (rows.length > 1) {

        button.closest(".product-row").remove();

    } else {

        alert("At least one product row is required.");

    }

}

</script>


</body>

</html>
