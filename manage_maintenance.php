<?php

session_start();

require_once "config/database.php";

// Admin and Staff access only
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["admin", "staff"], true)
) {
    header("Location: login.php");
    exit();
}

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$success = $_SESSION["maintenance_success"] ?? "";
unset($_SESSION["maintenance_success"]);
$error = "";

// Save Maintenance Record - Admin Only
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_maintenance"])
) {
    if ($_SESSION["role"] !== "admin") {
        $error = "You do not have permission to add maintenance records.";
    } elseif (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $maintenance_type = trim($_POST["maintenance_type"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $maintenance_date = $_POST["maintenance_date"] ?? "";
        $next_due_date = $_POST["next_due_date"] ?? "";

        $allowed_types = [
            "Filter Cleaning",
            "Filter Replacement",
            "Water Tank Cleaning",
            "Water Filtration Process"
        ];

        if (
            !in_array($maintenance_type, $allowed_types, true) ||
            $maintenance_date === ""
        ) {
            $error = "Please enter valid maintenance details.";
        } elseif (
            $next_due_date !== "" &&
            $next_due_date < $maintenance_date
        ) {
            $error = "Next due date cannot be before maintenance date.";
        } else {

            $next_due_value = $next_due_date === ""
                ? null
                : $next_due_date;

            $stmt = $conn->prepare(
                "INSERT INTO maintenance
                (maintenance_type, description, maintenance_date, next_due_date, status)
                VALUES (?, ?, ?, ?, 'Pending')"
            );

            $stmt->bind_param(
                "ssss",
                $maintenance_type,
                $description,
                $maintenance_date,
                $next_due_value
            );

            if ($stmt->execute()) {
                $stmt->close();
                $_SESSION["maintenance_success"]="Maintenance record saved successfully.";

                 header("Location: manage_maintenance.php");
                  exit();

            } else {
                $error = "Unable to save maintenance record.";
                  $stmt->close();
            }

            
        }
    }
}

// Mark Maintenance as Completed - Admin Only
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["complete_maintenance"])
) {
    if ($_SESSION["role"] !== "admin") {
        $error = "You do not have permission to complete maintenance.";
    } elseif (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";
    } else {

        $maintenance_id = (int)($_POST["maintenance_id"] ?? 0);

        if ($maintenance_id > 0) {

            $stmt = $conn->prepare(
                "UPDATE maintenance
                 SET status = 'Completed'
                 WHERE maintenance_id = ?
                 AND status != 'Completed'"
            );

            $stmt->bind_param("i", $maintenance_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION["maintenance_success"] =
                    "Maintenance marked as completed.";

                $stmt->close();

                header("Location: manage_maintenance.php");
                exit();
            } else {
                $error = "Unable to complete maintenance record.";
                $stmt->close();
            }

        } else {
            $error = "Invalid maintenance record.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Management</title>
</head>
<body>

<h2>Maintenance Management</h2>

<?php if ($success !== ""): ?>
    <p style="color:green;">
        <?php echo htmlspecialchars($success); ?>
    </p>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <p style="color:red;">
        <?php echo htmlspecialchars($error); ?>
    </p>
<?php endif; ?>

<?php if ($_SESSION["role"] === "admin"): ?>

    <h3>Add New Maintenance</h3>

    <form method="POST" action="">

        <input type="hidden" name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

        <label>Maintenance Type</label><br>

        <select name="maintenance_type" required>
            <option value="">Select Maintenance Type</option>
            <option value="Filter Cleaning">Filter Cleaning</option>
            <option value="Filter Replacement">Filter Replacement</option>
            <option value="Water Tank Cleaning">Water Tank Cleaning</option>
            <option value="Water Filtration Process">Water Filtration Process</option>
        </select>

        <br><br>

        <label>Description</label><br>
        <textarea name="description" rows="4"></textarea>

        <br><br>

        <label>Maintenance Date</label><br>
        <input type="date" name="maintenance_date" required>

        <br><br>

        <label>Next Due Date</label><br>
        <input type="date" name="next_due_date">

        <br><br>

        <button type="submit" name="save_maintenance">
            Save Maintenance
        </button>

    </form>

<?php endif; ?>
<hr>

<h3>Maintenance Records</h3>

<?php
$today = date("Y-m-d");
$records = $conn->query(
    "SELECT * FROM maintenance
     ORDER BY maintenance_date DESC, maintenance_id DESC"
);

?>

<table border="1" cellpadding="8" cellspacing="0">

    <tr>
        <th>ID</th>
        <th>Maintenance Type</th>
        <th>Description</th>
        <th>Maintenance Date</th>
        <th>Next Due Date</th>
        <th>Status</th>
        <?php if ($_SESSION["role"] === "admin"): ?>
        <th>Action</th>
         <?php endif; ?>
    </tr>

    <?php if ($records && $records->num_rows > 0): ?>

        <?php while ($row = $records->fetch_assoc()): ?>
            <?php
    if ($row["status"] === "Completed") {
        $reminder_status = "Completed";
    } elseif (empty($row["next_due_date"])) {
        $reminder_status = "Not Scheduled";
    } elseif ($row["next_due_date"] < $today) {
        $reminder_status = "Overdue";
    } elseif ($row["next_due_date"] === $today) {
        $reminder_status = "Due Today";
    } else {
        $reminder_status = "Upcoming";
    }
    ?>

            <tr>
                <td><?php echo $row["maintenance_id"]; ?></td>

                <td>
                    <?php echo htmlspecialchars($row["maintenance_type"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["description"] ?? ""); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["maintenance_date"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["next_due_date"] ?? "Not Set"); ?>
                </td>

                <td>
                     <?php echo htmlspecialchars($reminder_status); ?>
                </td>
                <?php if ($_SESSION["role"] === "admin"): ?>
                 <td>
                  <?php if ($row["status"] === "Completed"): ?>

            Completed

        <?php else: ?>

            <form method="POST" action="">

                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                <input type="hidden" name="maintenance_id"
                    value="<?php echo (int)$row["maintenance_id"]; ?>">

                <button type="submit" name="complete_maintenance">
                    Mark as Completed
                </button>

            </form>

        <?php endif; ?>
    </td>
    <?php endif; ?>

        </tr>

    <?php endwhile; ?>

<?php else: ?>

    <tr>
        <td colspan="7">No maintenance records found.</td>
    </tr>

<?php endif; ?>

</table>

</body>
</html>
