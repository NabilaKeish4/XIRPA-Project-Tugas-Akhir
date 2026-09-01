<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama     = mysqli_real_escape_string($conn, $_POST['nama']);
    $wa       = mysqli_real_escape_string($conn, $_POST['wa']);
    $alamat   = mysqli_real_escape_string($conn, $_POST['alamat']);

    // Simpan ke database Data Akun
    $query_user = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', 'pelanggan')";
    if (mysqli_query($conn, $query_user)) {
        $user_id = mysqli_insert_id($conn);
        $query_detail = "INSERT INTO pelanggan_detail (user_id, nama_lengkap, no_wa, alamat_lengkap) 
                         VALUES ('$user_id', '$nama', '$wa', '$alamat')";
        mysqli_query($conn, $query_detail);
        
        header("Location: login.php");
        exit();
    }
}
?>

<form method="POST">
    <h2>Register Pelanggan</h2>
    <input type="text" name="username" placeholder="Username" required><br>
    <input type="password" name="password" placeholder="Password" required><br>
    <input type="text" name="nama" placeholder="Nama Lengkap" required><br>
    <input type="text" name="wa" placeholder="No. WhatsApp" required><br>
    <textarea name="alamat" placeholder="Alamat Lengkap" required></textarea><br>
    <button type="submit">Daftar</button>
</form>