<?php

session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$is_admin = isset($_SESSION["role"])
    && $_SESSION["role"] === "admin";

$message = "";
$error = "";
$edit_product = null;
// Only Admin can add or update products
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    (isset($_POST["add_product"]) || isset($_POST["update_product"])) &&
    !$is_admin
) {
    http_response_code(403);
    exit("Access denied. Admin only.");
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_product"])) {

    $product_name = trim($_POST["product_name"]);
    $description = trim($_POST["description"]);
    $price = $_POST["price"];
    $stock_quantity = $_POST["stock_quantity"];
    $status = $_POST["status"];

    if (
        $product_name == "" ||
        !is_numeric($price) ||
        $price < 0 ||
        filter_var($stock_quantity, FILTER_VALIDATE_INT) === false ||
        $stock_quantity < 0 ||
        !in_array($status, ["Available", "Unavailable"], true)
    ) {
        $error = "Please enter valid product details.";
    } else {

        $sql = "INSERT INTO products
                (product_name, description, price, stock_quantity, status)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssdis",
            $product_name,
            $description,
            $price,
            $stock_quantity,
            $status
        );

        if ($stmt->execute()) {
            header("Location: products.php?added=1");
            exit();
        } else {
            $error = "Failed to add product.";
        }

        $stmt->close();
    }
}

if (isset($_GET["added"])) {
    $message = "Product added successfully!";
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_product"])) {

    $product_id = filter_var($_POST["product_id"], FILTER_VALIDATE_INT);
    $product_name = trim($_POST["product_name"]);
    $description = trim($_POST["description"]);
    $price = $_POST["price"];
    $stock_quantity = $_POST["stock_quantity"];
    $status = $_POST["status"];

    if (
        $product_id === false ||
        $product_id === null ||
        $product_id <= 0 ||
        $product_name == "" ||
        !is_numeric($price) ||
        $price < 0 ||
        filter_var($stock_quantity, FILTER_VALIDATE_INT) === false ||
        $stock_quantity < 0 ||
        !in_array($status, ["Available", "Unavailable"], true)
    ) {
        $error = "Please enter valid product details.";
    } else {

        $sql = "UPDATE products
                SET product_name = ?,
                    description = ?,
                    price = ?,
                    stock_quantity = ?,
                    status = ?
                WHERE product_id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssdisi",
            $product_name,
            $description,
            $price,
            $stock_quantity,
            $status,
            $product_id
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: products.php?updated=1");
            exit();
        } else {
            $error = "Failed to update product.";
            $stmt->close();
        }
    }
}

if (isset($_GET["updated"])) {
    $message = "Product updated successfully!";
}
if ($is_admin && isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {

    $edit_id = (int) $_GET["edit"];

    $sql = "SELECT product_id, product_name, description,
                   price, stock_quantity, status
            FROM products
            WHERE product_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $edit_result = $stmt->get_result();

    if ($edit_result->num_rows == 1) {
        $edit_product = $edit_result->fetch_assoc();
    } else {
        $error = "Product not found.";
    }

    $stmt->close();
}
// Get all products
$sql = "SELECT product_id, product_name, description,
               price, stock_quantity, status, created_at
        FROM products
        ORDER BY product_id DESC";

$result = $conn->query($sql);

if (!$result) {
    $error = "Unable to load products.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Eranda Water - Products</title>
    
</head>
<body>

    <h1>Product Management</h1>


 <?php if ($is_admin && $edit_product !== null): ?> 


    <h2>Edit Product</h2>

    <form method="POST" action="products.php">

        <input type="hidden" name="product_id"
               value="<?php echo (int) $edit_product['product_id']; ?>">

        <label>Product Name:</label><br>
        <input type="text" name="product_name"
               value="<?php echo htmlspecialchars($edit_product['product_name']); ?>"
               required maxlength="150">
        <br><br>

        <label>Description:</label><br>
        <textarea name="description"><?php echo htmlspecialchars($edit_product['description'] ?? ""); ?></textarea>
        <br><br>

        <label>Price:</label><br>
        <input type="number" name="price" min="0" step="0.01"
               value="<?php echo htmlspecialchars((string) $edit_product['price']); ?>"
               required>
        <br><br>

        <label>Stock Quantity:</label><br>
        <input type="number" name="stock_quantity" min="0" step="1"
               value="<?php echo (int) $edit_product['stock_quantity']; ?>"
               required>
        <br><br>

        <label>Status:</label><br>
        <select name="status" required>
            <option value="Available"
                <?php if ($edit_product['status'] == 'Available') echo 'selected'; ?>>
                Available
            </option>
            <option value="Unavailable"
                <?php if ($edit_product['status'] == 'Unavailable') echo 'selected'; ?>>
                Unavailable
            </option>
        </select>
        <br><br>

        <button type="submit" name="update_product">
            Update Product
        </button>

        <a href="products.php">Cancel</a>

    </form>

<?php elseif ($is_admin): ?>

    <h2>Add New Product</h2>

<?php if ($message != ""): ?>
    <p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<form method="POST" action="products.php">

    <label>Product Name:</label><br>
    <input type="text" name="product_name" required maxlength="150">
    <br><br>

    <label>Description:</label><br>
    <textarea name="description"></textarea>
    <br><br>

    <label>Price:</label><br>
    <input type="number" name="price" min="0" step="0.01" required>
    <br><br>

    <label>Stock Quantity:</label><br>
    <input type="number" name="stock_quantity" min="0" step="1" required>
    <br><br>

    <label>Status:</label><br>
    <select name="status" required>
        <option value="Available">Available</option>
        <option value="Unavailable">Unavailable</option>
    </select>
    <br><br>

    <button type="submit" name="add_product">
        Add Product
    </button>

</form>
<?php endif;?>
<hr>

    <p>
        <a href="dashboard.php">Back to Dashboard</a>
    </p>

    <?php if ($error != ""): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <h2>Available Products</h2>

    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>Product ID</th>
                <th>Product Name</th>
                <th>Description</th>
                <th>Price</th>
                <th>Stock Quantity</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($product = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo (int) $product["product_id"]; ?></td>

                        <td><?php echo htmlspecialchars($product["product_name"]); ?></td>

                        <td><?php echo htmlspecialchars($product["description"] ?? ""); ?></td>

                        <td><?php echo number_format((float) $product["price"], 2); ?></td>

                        <td><?php echo (int) $product["stock_quantity"]; ?></td>

                        <td><?php echo htmlspecialchars($product["status"]); ?></td>

                        <td><?php echo htmlspecialchars($product["created_at"]); ?></td>
                        <td><?php if ($is_admin): ?><a href="products.php?edit=<?php echo (int) $product['product_id']; ?>"> Edit</a><?php else: ?>View only<?php endif; ?></td> 
                        </tr><?php endwhile; ?> <?php else: ?>
                <tr>
                    <td colspan="8">No products found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>