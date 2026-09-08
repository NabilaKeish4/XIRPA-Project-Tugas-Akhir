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
    <title>Nota Pesanan #<?= (int)$tx['id_transaksi'] ?> - PlantShop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#63B745', 600: '#529E38', light: '#E2F2DC' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex min-h-screen">

    <!-- Sidebar Navbar (Samping) -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6 print:hidden">
        <div>
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">HOME</a>
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Shop</a>
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Keranjang</a>
            </nav>
        </div>
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Kembali ke Katalog</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 p-8 overflow-y-auto">
        <div class="max-w-xl mx-auto my-4">
            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                <div class="text-center border-b pb-4 mb-6">
                    <h1 class="text-3xl font-black tracking-wider text-gray-900">PlantShop</h1>
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

                <div class="text-center space-x-2 print:hidden">
                    <button onclick="window.print()" class="bg-gray-100 text-gray-700 px-6 py-2.5 rounded-xl text-xs font-bold uppercase">Cetak Nota</button>
                    <a href="dashboard.php" class="bg-brand-500 text-white px-6 py-2.5 rounded-xl text-xs font-bold uppercase transition inline-block">Kembali ke Katalog</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>