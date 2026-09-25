<?php

session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_customer"])) {

    $full_name = trim($_POST["full_name"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $customer_type = $_POST["customer_type"];


    /* Check same phone + same address */

    $sql = "SELECT customer_id, full_name, phone, address, customer_type
            FROM customers
            WHERE phone = ? AND address = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $phone, $address);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $existing = $result->fetch_assoc();

        $message =
            "This customer already exists. Customer ID: "
            . $existing["customer_id"]
            . " | Name: "
            . $existing["full_name"];

        $message_type = "duplicate";

    } else {


        /* Check same phone */

        $sql = "SELECT customer_id, full_name, phone, address
                FROM customers
                WHERE phone = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $phone);
        $stmt->execute();

        $phone_result = $stmt->get_result();


        if ($phone_result->num_rows > 0) {

            $existing = $phone_result->fetch_assoc();

            $message =
                "This phone number is already registered. "
                . "Customer ID: "
                . $existing["customer_id"]
                . " | Name: "
                . $existing["full_name"]
                . " | Address: "
                . $existing["address"];

            $message_type = "warning";

        } else {


            /* Check same name */

            $sql = "SELECT customer_id, full_name, phone, address
                    FROM customers
                    WHERE LOWER(full_name) = LOWER(?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $full_name);
            $stmt->execute();

            $name_result = $stmt->get_result();


            if ($name_result->num_rows > 0) {

                $existing = $name_result->fetch_assoc();

                $message =
                    "A customer with this name already exists. "
                    . "Customer ID: "
                    . $existing["customer_id"]
                    . " | Name: "
                    . $existing["full_name"]
                    . " | Phone: "
                    . $existing["phone"]
                    . " | Address: "
                    . $existing["address"];

                $message_type = "warning";

            } else {


                /* Add new customer */

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


                if ($stmt->execute()) {

                    $new_customer_id = $conn->insert_id;

                    $message =
                        "Customer added successfully. Customer ID: "
                        . $new_customer_id;

                    $message_type = "success";

                } else {

                    $message =
                        "Error: " . $stmt->error;

                    $message_type = "error";
                }
            }
        }
    }
}
$search = "";

if (isset($_GET["search"])) {

    $search = trim($_GET["search"]);
}


if ($search != "") {

    $sql = "SELECT *
            FROM customers
            WHERE CAST(customer_id AS CHAR) LIKE ?
               OR full_name LIKE ?
               OR phone LIKE ?
               OR address LIKE ?
            ORDER BY customer_id DESC";

    $stmt = $conn->prepare($sql);

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "ssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $sql = "SELECT *
            FROM customers
            ORDER BY customer_id DESC";

    $result = $conn->query($sql);
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Eranda Water - Customers</title>

</head>

<body>

<h1>Customer Management</h1>

<a href="dashboard.php">Back to Dashboard</a>

<hr>


<!-- MESSAGE -->

<?php if ($message != ""): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

<?php endif; ?>


<!-- ADD CUSTOMER -->

<h2>Add New Customer</h2>

<form method="POST">

    <input
        type="hidden"
        name="add_customer"
        value="1"
    >

    <label>Full Name:</label><br>

    <input
        type="text"
        name="full_name"
        required
    >

    <br><br>


    <label>Phone:</label><br>

    <input
        type="text"
        name="phone"
        required
    >

    <br><br>


    <label>Address:</label><br>

    <textarea
        name="address"
        required
    ></textarea>

    <br><br>


    <label>Customer Type:</label><br>

    <select name="customer_type" required>

        <option value="Home Delivery">
            Home Delivery
        </option>

        <option value="Walk-in">
            Walk-in
        </option>

        <option value="Bulk">
            Bulk
        </option>

    </select>

    <br><br>


    <button type="submit">
        Add Customer
    </button>

</form>


<hr>


<!-- SEARCH -->

<h2>Search Customer</h2>

<form method="GET">

    <input
        type="text"
        name="search"
        placeholder="Customer ID / Name / Phone / Address"
        value="<?php echo htmlspecialchars($search); ?>"
    >

    <button type="submit">
        Search
    </button>

    <a href="customers.php">
        Clear
    </a>

</form>


<hr>
!-- CUSTOMER LIST -->

<h2>Customer List</h2>

<table border="1" cellpadding="10">

    <tr>

        <th>Customer ID</th>

        <th>Full Name</th>

        <th>Phone</th>

        <th>Address</th>

        <th>Customer Type</th>

        <th>Action</th>

    </tr>


    <?php if ($result->num_rows > 0): ?>

        <?php while ($row = $result->fetch_assoc()): ?>

            <tr>

                <td>
                    <?php echo $row["customer_id"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["full_name"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["phone"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["address"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["customer_type"]); ?>
                </td>

                <td>

                    <a href="customer_edit.php?id=<?php echo $row["customer_id"]; ?>">
                        Edit
                    </a>

                    |

                    <a
                        href="customer_delete.php?id=<?php echo $row["customer_id"]; ?>"
                        onclick="return confirm('Are you sure you want to delete this customer?');"
                    >
                        Delete
                    </a>

                </td>

            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>

            <td colspan="6">
                No customers found.
            </td>

        </tr>

    <?php endif; ?>

</table>

</body>

</html>