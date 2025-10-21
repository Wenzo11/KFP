<?php
session_start();
include 'connect.php';

// If you want to log logout time, you can keep the code above, but it's optional

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
