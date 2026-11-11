<?php
session_start();
// Remove all session data
session_unset();
// Destroy the admin session
session_destroy();
// Redirect to admin login page
header("Location: login.php");
exit();
?>