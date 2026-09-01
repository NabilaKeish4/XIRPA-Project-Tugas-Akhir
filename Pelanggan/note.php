<?php
session_start();
require_once '../Config/database.php';

$id_transaksi = $_GET['id'] ?? 0;

// Ambil data transaksi
$query_tx = "SELECT * FROM transaksi WHERE id_transaksi = '$id_transaksi'";
$res_tx = mysqli_query($conn, $query_tx);
$tx = mysqli_fetch_assoc($res_tx);

if (!$tx) {
    header('Location: dashboard.php');
    exit;
}

// Ambil item pesanan
$query_detail = "SELECT d.*, p.nama_produk FROM detail_transaksi d 
                 JOIN produk p ON d.id_produk = p.id_produk 
                 WHERE d.id_transaksi = '$id_transaksi'";
$res_detail = mysqli_query($conn, $query_detail);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Pesanan #<?= $tx['id_transaksi'] ?> - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 text-gray-800">
    <div class="container mx-auto p-6 max-w-xl">
        <div class="bg-white p-6 rounded-xl shadow-lg border border-emerald-100">
            <div class="text-center border-b pb-4 mb-4">
                <h1 class="text-2xl font-bold text-emerald-800">PlantHub</h1>
                <p class="text-xs text-gray-500">Nota Pembelian Produk Tanaman Hias</p>
            </div>

            <div class="text-sm space-y-1 mb-4">
                <p><strong>No. Transaksi:</strong> #<?= $tx['id_transaksi'] ?></p>
                <p><strong>Tanggal:</strong> <?= $tx['tanggal'] ?></p>
                <p><strong>Penerima:</strong> <?= $tx['nama_penerima'] ?> (<?= $tx['telepon'] ?>)</p>
                <p><strong>Alamat:</strong> <?= $tx['alamat'] ?></p>
                <p><strong>Metode Pembayaran:</strong> <?= $tx['metode_pembayaran'] ?></p>
            </div>

            <table class="w-full text-left text-sm border-t border-b mb-4">
                <thead>
                    <tr class="py-2 text-gray-600">
                        <th class="py-2">Item</th>
                        <th class="py-2 text-center">Qty</th>
                        <th class="py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php while ($row = mysqli_fetch_assoc($res_detail)): ?>
                    <tr>
                        <td class="py-2"><?= $row['nama_produk'] ?></td>
                        <td class="py-2 text-center"><?= $row['jumlah'] ?></td>
                        <td class="py-2 text-right">Rp <?= number_format($row['harga'] * $row['jumlah'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="flex justify-between font-bold text-lg text-emerald-900 border-b pb-4 mb-4">
                <span>Total Bayar:</span>
                <span>Rp <?= number_format($tx['total'], 0, ',', '.') ?></span>
            </div>

            <div class="text-center space-x-2">
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">Cetak Nota</button>
                <a href="dashboard.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm inline-block">Kembali ke Katalog</a>
            </div>
        </div>
    </div>
</body>
</html>