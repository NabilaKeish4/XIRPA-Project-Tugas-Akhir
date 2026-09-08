<?php
session_start();
require_once '../Config/database.php';

$id = (int)($_GET['id'] ?? 0);
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
    <title><?= htmlspecialchars($produk['nama_tanaman']) ?> - PlantShop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#63B745', 600: '#529E38', light: '#E2F2DC' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex min-h-screen">

    <!-- Sidebar Navbar (Samping) -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6">
        <div>
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">HOME</a>
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Shop</a>
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50 flex items-center justify-between">
                    <span>Keranjang</span>
                    <span class="text-gray-400">(<?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?>)</span>
                </a>
            </nav>
        </div>
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Back to Shop</a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto my-8">
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div class="bg-brand-light/50 rounded-2xl p-8 flex justify-center items-center h-96">
                    <img src="../assets/img/<?= htmlspecialchars($produk['foto_produk'] ?? 'default.jpg') ?>" class="max-h-80 object-contain drop-shadow-md">
                </div>

                <div>
                    <p class="text-xs font-bold text-brand-500 uppercase tracking-widest">Top Products</p>
                    <h1 class="text-3xl font-black uppercase text-gray-900 mt-1"><?= htmlspecialchars($produk['nama_tanaman']) ?></h1>
                    <p class="text-2xl font-black text-gray-900 my-4">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></p>
                    
                    <p class="text-xs text-gray-500 mb-6">Stok Tersedia: <span class="font-bold text-gray-800"><?= (int)$produk['stok'] ?></span></p>

                    <div class="space-y-4 text-xs text-gray-600">
                        <div>
                            <h3 class="font-bold uppercase text-gray-900 mb-1">Deskripsi</h3>
                            <p class="leading-relaxed"><?= nl2br(htmlspecialchars($produk['deskripsi'])) ?></p>
                        </div>

                        <div>
                            <h3 class="font-bold uppercase text-gray-900 mb-1">Tips Perawatan 💧</h3>
                            <p class="leading-relaxed"><?= nl2br(htmlspecialchars($produk['cara_perawatan'] ?? 'Siram 1-2 kali sehari dan beri pencahayaan cukup.')) ?></p>
                        </div>
                    </div>

                    <form action="dashboard.php" method="POST" class="mt-8 flex gap-3">
                        <input type="hidden" name="id_produk" value="<?= (int)$produk['id_produk'] ?>">
                        <input type="number" name="jumlah" value="1" min="1" max="<?= (int)$produk['stok'] ?>" class="w-20 border rounded-xl p-3 text-center text-sm font-bold focus:outline-none border-gray-200">
                        <button type="submit" name="add_to_cart" class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-brand-500/20">
                            Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>