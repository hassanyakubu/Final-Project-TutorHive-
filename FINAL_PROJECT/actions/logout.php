<?php
session_start();

// Destroy session to log the user out
session_unset();
session_destroy();

// Redirect to the index page (or login page, depending on your app)
header("Location: ../actions/login.php");
exit();
?>
