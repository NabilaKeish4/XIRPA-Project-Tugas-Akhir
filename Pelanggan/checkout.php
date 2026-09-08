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
    $metode_bayar  = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);
    $id_user       = (int)($_SESSION['id_user'] ?? 1);
    $tanggal       = date('Y-m-d H:i:s');

    $total_bayar = 0;
    foreach ($_SESSION['cart'] as $id => $jumlah) {
        $id_clean = (int)$id;
        $res = mysqli_query($conn, "SELECT harga FROM produk WHERE id_produk = '$id_clean'");
        $p = mysqli_fetch_assoc($res);
        if ($p) {
            $total_bayar += $p['harga'] * (int)$jumlah;
        }
    }

    $query_tx = "INSERT INTO transaksi (id_user, tanggal, total, nama_penerima, alamat, telepon, metode_pembayaran, status) 
                 VALUES ('$id_user', '$tanggal', '$total_bayar', '$nama_penerima', '$alamat', '$telepon', '$metode_bayar', 'Pending')";
    
    if (mysqli_query($conn, $query_tx)) {
        $id_transaksi = mysqli_insert_id($conn);

        foreach ($_SESSION['cart'] as $id => $jumlah) {
            $id_clean = (int)$id;
            $qty = (int)$jumlah;
            $res = mysqli_query($conn, "SELECT harga FROM produk WHERE id_produk = '$id_clean'");
            $p = mysqli_fetch_assoc($res);
            if ($p) {
                $harga = $p['harga'];
                mysqli_query($conn, "INSERT INTO detail_transaksi (id_transaksi, id_produk, jumlah, harga) 
                                     VALUES ('$id_transaksi', '$id_clean', '$qty', '$harga')");
            }
        }

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
    <title>Checkout - TPLANT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#63B745', 600: '#529E38' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">
    <header class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900">TPLANT</a>
            <a href="cart.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase">&larr; Kembali ke Keranjang</a>
        </div>
    </header>

    <div class="max-w-2xl mx-auto p-6 my-8">
        <h1 class="text-2xl font-black uppercase text-gray-900 mb-6">Formulir Checkout</h1>

        <form method="POST" class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">Nama Penerima</label>
                <input type="text" name="nama" required class="w-full border p-3 rounded-xl text-sm focus:outline-none focus:border-brand-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">Nomor Telepon/WA</label>
                <input type="text" name="telepon" required class="w-full border p-3 rounded-xl text-sm focus:outline-none focus:border-brand-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">Alamat Pengiriman Lengkap</label>
                <textarea name="alamat" required rows="3" class="w-full border p-3 rounded-xl text-sm focus:outline-none focus:border-brand-500"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1">Metode Pembayaran</label>
                <select name="metode_pembayaran" class="w-full border p-3 rounded-xl text-sm focus:outline-none focus:border-brand-500">
                    <option value="Transfer Bank">Transfer Bank (BCA / Mandiri)</option>
                    <option value="E-Wallet">E-Wallet (Gopay / OVO / Dana)</option>
                    <option value="COD">Bayar di Tempat (COD)</option>
                </select>
            </div>

            <button type="submit" name="proses_checkout" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-3.5 rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-brand-500/20">
                Selesaikan Pesanan
            </button>
        </form>
    </div>
</body>
</html>