<?php
session_start();
require_once '../Config/database.php';

if (empty($_SESSION['cart'])) {
    header('Location: dashboard.php');
    exit;
}

// Proses Simpan Transaksi
if (isset($_POST['proses_checkout'])) {
    $nama_penerima = mysqli_real_escape_string($conn, $_POST['nama']);
    $alamat        = mysqli_real_escape_string($conn, $_POST['alamat']);
    $telepon       = mysqli_real_escape_string($conn, $_POST['telepon']);
    $metode_bayar  = $_POST['metode_pembayaran'];
    $id_user       = $_SESSION['id_user'] ?? 1; // Fallback ID jika tanpa auth
    $tanggal       = date('Y-m-d H:i:s');

    // Hitung Total
    $total_bayar = 0;
    foreach ($_SESSION['cart'] as $id => $jumlah) {
        $res = mysqli_query($conn, "SELECT harga FROM produk WHERE id_produk = '$id'");
        $p = mysqli_fetch_assoc($res);
        $total_bayar += $p['harga'] * $jumlah;
    }

    // Insert ke tabel Transaksi
    $query_tx = "INSERT INTO transaksi (id_user, tanggal, total, nama_penerima, alamat, telepon, metode_pembayaran, status) 
                 VALUES ('$id_user', '$tanggal', '$total_bayar', '$nama_penerima', '$alamat', '$telepon', '$metode_bayar', 'Pending')";
    
    if (mysqli_query($conn, $query_tx)) {
        $id_transaksi = mysqli_insert_id($conn);

        // Insert ke Detail Transaksi
        foreach ($_SESSION['cart'] as $id => $jumlah) {
            $res = mysqli_query($conn, "SELECT harga FROM produk WHERE id_produk = '$id'");
            $p = mysqli_fetch_assoc($res);
            $harga = $p['harga'];

            mysqli_query($conn, "INSERT INTO detail_transaksi (id_transaksi, id_produk, jumlah, harga) 
                                 VALUES ('$id_transaksi', '$id', '$jumlah', '$harga')");
        }

        // Kosongkan Keranjang
        unset($_SESSION['cart']);
        header("Location: nota.php?id=$id_transaksi");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Checkout - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 text-gray-800">
    <div class="container mx-auto p-6 max-w-2xl">
        <h1 class="text-2xl font-bold text-emerald-900 mb-6">Formulir Checkout</h1>

        <form method="POST" class="bg-white p-6 rounded-xl shadow-md space-y-4">
            <div>
                <label class="block text-sm font-semibold mb-1">Nama Penerima</label>
                <input type="text" name="nama" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Nomor Telepon/WA</label>
                <input type="text" name="telepon" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Alamat Pengiriman Lengkap</label>
                <textarea name="alamat" required rows="3" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Metode Pembayaran</label>
                <select name="metode_pembayaran" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-emerald-500">
                    <option value="Transfer Bank">Transfer Bank (BCA / Mandiri)</option>
                    <option value="E-Wallet">E-Wallet (Gopay / OVO / Dana)</option>
                    <option value="COD">Bayar di Tempat (COD)</option>
                </select>
            </div>

            <button type="submit" name="proses_checkout" class="w-full bg-emerald-600 text-white font-bold py-3 rounded-lg hover:bg-emerald-700 transition">Selesaikan Pesanan</button>
        </form>
    </div>
</body>
</html>