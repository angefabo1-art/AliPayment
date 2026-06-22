<?php
session_start();

// Destroy the session
session_destroy();

// Redirect to admin login page
header('Location: logadmin.php');
exit();
?>