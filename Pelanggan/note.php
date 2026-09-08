<?php
session_start();
require_once '../Config/database.php';

$id_transaksi = (int)($_GET['id'] ?? 0);

$query_tx = "SELECT * FROM transaksi WHERE id_transaksi = '$id_transaksi'";
$res_tx = mysqli_query($conn, $query_tx);
$tx = mysqli_fetch_assoc($res_tx);

if (!$tx) {
    header('Location: dashboard.php');
    exit;
}

$query_detail = "SELECT d.*, p.nama_produk FROM detail_transaksi d 
                 JOIN produk p ON d.id_produk = p.id_produk 
                 WHERE d.id_transaksi = '$id_transaksi'";
$res_detail = mysqli_query($conn, $query_detail);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Pesanan #<?= (int)$tx['id_transaksi'] ?> - TPLANT</title>
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
    <div class="max-w-xl mx-auto p-6 my-12">
        <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
            <div class="text-center border-b pb-4 mb-6">
                <h1 class="text-3xl font-black tracking-wider text-gray-900">TPLANT</h1>
                <p class="text-xs text-gray-400 uppercase tracking-widest mt-1">Nota Pembelian Produk</p>
            </div>

            <div class="text-xs space-y-1.5 mb-6 text-gray-600">
                <p><strong>No. Transaksi:</strong> #<?= (int)$tx['id_transaksi'] ?></p>
                <p><strong>Tanggal:</strong> <?= htmlspecialchars($tx['tanggal']) ?></p>
                <p><strong>Penerima:</strong> <?= htmlspecialchars($tx['nama_penerima']) ?> (<?= htmlspecialchars($tx['telepon']) ?>)</p>
                <p><strong>Alamat:</strong> <?= htmlspecialchars($tx['alamat']) ?></p>
                <p><strong>Metode Pembayaran:</strong> <?= htmlspecialchars($tx['metode_pembayaran']) ?></p>
            </div>

            <table class="w-full text-left text-xs border-t border-b border-gray-100 mb-6">
                <thead>
                    <tr class="py-3 text-gray-400 uppercase tracking-wider">
                        <th class="py-3">Item</th>
                        <th class="py-3 text-center">Qty</th>
                        <th class="py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php while ($row = mysqli_fetch_assoc($res_detail)): ?>
                    <tr>
                        <td class="py-3 font-bold text-gray-800"><?= htmlspecialchars($row['nama_produk']) ?></td>
                        <td class="py-3 text-center"><?= (int)$row['jumlah'] ?></td>
                        <td class="py-3 text-right font-bold">Rp <?= number_format($row['harga'] * $row['jumlah'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="flex justify-between font-black text-lg text-gray-900 border-b border-gray-100 pb-6 mb-6">
                <span>Total Bayar:</span>
                <span>Rp <?= number_format($tx['total'], 0, ',', '.') ?></span>
            </div>

            <div class="text-center space-x-2">
                <button onclick="window.print()" class="bg-gray-100 text-gray-700 px-6 py-2.5 rounded-xl text-xs font-bold uppercase">Cetak Nota</button>
                <a href="dashboard.php" class="bg-brand-500 text-white px-6 py-2.5 rounded-xl text-xs font-bold uppercase transition inline-block">Kembali ke Katalog</a>
            </div>
        </div>
    </div>
</body>
</html>