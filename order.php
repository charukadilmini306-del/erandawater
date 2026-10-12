<?php

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $customer_type = $_POST["customer_type"] ?? "";

    $selected_products = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

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

    } else {

        $conn->begin_transaction();

        try {
            $sql = "SELECT customer_id, full_name, address,
                           customer_type
                    FROM customers
                    WHERE phone = ?
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param("s", $phone);

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows > 0) {


                $customer = $result->fetch_assoc();

                $customer_id = (int) $customer["customer_id"];

            

                $delivery_address = ($address !== "")
                    ? $address
                    : $customer["address"];

            } else {
                if ($full_name === "") {
                    throw new Exception(
                        "Please enter your full name."
                    );
                }

                if ($address === "") {
                    throw new Exception(
                        "Please enter your address."
                    );
                }

                $sql = "INSERT INTO customers
                        (full_name, phone, address, customer_type)
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

            $order_items = [];

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


                if (
                    $product_id === false ||
                    $product_id === null ||
                    $product_id < 1
                ) {
                    throw new Exception(
                        "Please select a valid product."
                    );
                }


                if (
                    $quantity === false ||
                    $quantity === null ||
                    $quantity < 1
                ) {
                    throw new Exception(
                        "Quantity must be at least 1."
                    );
                }


                if (isset($used_products[$product_id])) {
                    throw new Exception(
                        "Please select each product only once."
                    );
                }

                $used_products[$product_id] = true;


                // CHECK PRODUCT AND STOCK

                $sql = "SELECT product_id, product_name,
                               price, stock_quantity, status
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


                if ($product["status"] !== "Available") {
                    throw new Exception(
                        $product["product_name"] .
                        " is not available."
                    );
                }


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


                $order_items[] = [
                    "product_id" => (int) $product["product_id"],
                    "quantity" => $quantity,
                    "unit_price" => $unit_price
                ];
            }


            // SAVE ORDER

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


            // SAVE ORDER ITEMS AND UPDATE STOCK

            foreach ($order_items as $item) {

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


                // REDUCE STOCK

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
            }


            // SAVE ALL CHANGES

            $conn->commit();

            $message = "Order placed successfully! " .
                       "Order ID: " . $order_id .
                       " | Total: Rs. " .
                       number_format($total_amount, 2);


            // CLEAR FORM VALUES

            $full_name = "";
            $phone = "";
            $address = "";
            $customer_type = "Home Delivery";

            $selected_products = [];
            $quantities = [];


            // RELOAD PRODUCTS AFTER STOCK UPDATE

            $products = [];

            $sql = "SELECT product_id, product_name,
                           price, stock_quantity
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


        } catch (Throwable $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eranda Water - Place Order</title>
</head>
<body><h1>Eranda Water</h1>
<h2>Place Your Water Order</h2>

<?php if ($message != ""): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($error != ""): ?>
    <p><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" action="order.php">

    <h3>Customer Details</h3>

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

    <label for="address">Delivery Address:</label><br>
    <textarea
        id="address"
        name="address"
        rows="3"
    ><?= htmlspecialchars($address) ?></textarea>
    <br><br>

    <label for="customer_type">Customer Type:</label><br>
    <select id="customer_type" name="customer_type" required>
        <option value="Home Delivery">Home Delivery</option>
        <option value="Bulk">Bulk</option>
        <option value="Walk-in">Walk-in</option>
    </select>

    <br><br>

    <hr>    <h3>Select Water Products</h3>

    <div id="product-container">

        <div class="product-row">

            <label for="product_0">Select Product:</label><br>

            <select name="product_id[]" id="product_0" required>

                <option value="">-- Select a Product --</option>

                <?php foreach ($products as $product): ?>

                    <option value="<?= (int)$product["product_id"] ?>">

                        <?= htmlspecialchars($product["product_name"]) ?>
                        - Rs. <?= number_format((float)$product["price"], 2) ?>
                        (Stock: <?= (int)$product["stock_quantity"] ?>)

                    </option>

                <?php endforeach; ?>

            </select>

            <br><br>

            <label for="quantity_0">Quantity:</label><br>

            <input
                type="number"
                name="quantity[]"
                id="quantity_0"
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
