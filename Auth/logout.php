<?php
session_start();

// Hapus semua session login
session_unset();
session_destroy();

// Kembalikan user ke portal login di folder Auth
header("Location: index.php");
exit;
?>