<?php

session_start();

// Remove customer session data only
unset($_SESSION["customer_id"]);
unset($_SESSION["customer_name"]);
unset($_SESSION["customer_logged_in"]);

// Keep the existing Admin/Staff session intact
header("Location: customer_login.php");
exit;

?>