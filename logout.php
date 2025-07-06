<?php
session_start();

// Hapus semua variabel session
$_SESSION = array();


session_destroy();


header("Location: /SIMPRAK/login.php"); 
exit;
?>
