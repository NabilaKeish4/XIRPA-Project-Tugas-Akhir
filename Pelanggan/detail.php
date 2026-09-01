<?php
session_start();
require_once '../Config/database.php';

$id = $_GET['id'] ?? 0;
$query = "SELECT * FROM produk WHERE id_produk = '$id'";
$result = mysqli_query($conn, $query);
$produk = mysqli_fetch_assoc($result);

if (!$produk) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= $produk['nama_produk'] ?> - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 text-gray-800">
    <div class="container mx-auto p-6 max-w-4xl">
        <a href="dashboard.php" class="text-emerald-600 font-semibold mb-4 inline-block">&larr; Kembali ke Dashboard</a>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
            <img src="../assets/img/<?= $produk['foto_produk'] ?? 'default.jpg' ?>" class="w-full h-80 object-cover rounded-lg">
            
            <div class="flex flex-col justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-emerald-900"><?= $produk['nama_produk'] ?></h1>
                    <p class="text-2xl font-semibold text-emerald-600 my-3">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></p>
                    <p class="text-sm text-gray-600 mb-4">Stok Tersedia: <span class="font-bold"><?= $produk['stok'] ?></span></p>
                    
                    <h3 class="font-semibold text-gray-700 border-b pb-1 mb-2">Deskripsi Produk:</h3>
                    <p class="text-gray-600 text-sm mb-4"><?= nl2br($produk['deskripsi']) ?></p>

                    <h3 class="font-semibold text-gray-700 border-b pb-1 mb-2">Tips Perawatan 💧:</h3>
                    <p class="text-gray-600 text-sm mb-4"><?= nl2br($produk['cara_perawatan'] ?? 'Siram 1-2 kali sehari dan beri pencahayaan cukup.') ?></p>
                </div>

                <form action="dashboard.php" method="POST" class="flex gap-2">
                    <input type="hidden" name="id_produk" value="<?= $produk['id_produk'] ?>">
                    <input type="number" name="jumlah" value="1" min="1" max="<?= $produk['stok'] ?>" class="w-20 border rounded-lg p-2 text-center">
                    <button type="submit" name="add_to_cart" class="flex-1 bg-emerald-600 text-white font-semibold py-2 rounded-lg hover:bg-emerald-700">Tambah ke Keranjang</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>