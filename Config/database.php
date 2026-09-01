<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "plant_hub";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>