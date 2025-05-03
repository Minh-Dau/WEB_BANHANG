<?php
session_start();
unset($_SESSION['username']);
unset($_SESSION['logged_in']);
session_destroy();
header('Location: trangchinh.php');
exit();
?>