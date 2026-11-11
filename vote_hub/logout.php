<?php
session_start();
// Clear all session variables
session_unset();
// Destroy the session
session_destroy();
// Redirect to student login page
header("Location: login.php");
exit();
?>