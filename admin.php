<?php

session_start();
require_once "config/database.php";

// Allow only logged-in Admin users
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    http_response_code(403);
    exit("Access denied. Admin only.");
}

echo "Admin account is already managed by the system.";

?>