<?php
session_start();
require_once '../Config/database.php';

// Menangani Tambah ke Keranjang
if (isset($_POST['add_to_cart'])) {
    $id_produk = $_POST['id_produk'];
    $jumlah = (int)$_POST['jumlah'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$id_produk])) {
        $_SESSION['cart'][$id_produk] += $jumlah;
    } else {
        $_SESSION['cart'][$id_produk] = $jumlah;
    }

    header('Location: cart.php');
    exit;
}

// Fitur Pencarian Produk
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM produk WHERE nama_produk LIKE '%$search%'";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 text-gray-800">

    <!-- Navbar -->
    <nav class="bg-emerald-700 text-white shadow-md p-4 sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold tracking-wide">PlantHub 🌱</h1>
            <div class="space-x-4 flex items-center">
                <a href="dashboard.php" class="hover:text-emerald-200">Katalog</a>
                <a href="cart.php" class="hover:text-emerald-200">Keranjang (<?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?>)</a>
                <a href="riwayat.php" class="hover:text-emerald-200">Riwayat Pesanan</a>
                <a href="../auth/login.php" class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="container mx-auto p-6">
        <!-- Hero & Search -->
        <div class="mb-8 text-center">
            <h2 class="text-3xl font-extrabold text-emerald-900 mb-2">Temukan Tanaman Hias Impianmu</h2>
            <p class="text-emerald-700 mb-4">Segarkan ruanganmu dengan koleksi tanaman hias terbaik dari kami.</p>
            
            <form method="GET" class="max-w-md mx-auto flex gap-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari tanaman (misal: Monstera)..." class="w-full px-4 py-2 border border-emerald-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <button type="submit" class="bg-emerald-600 text-white px-5 py-2 rounded-lg hover:bg-emerald-700">Cari</button>
            </form>
        </div>

        <!-- Grid Produk -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition flex flex-col justify-between">
                        <img src="../assets/img/<?= $row['foto_produk'] ?? 'default.jpg' ?>" alt="<?= $row['nama_produk'] ?>" class="h-48 w-full object-cover">
                        <div class="p-4 flex-grow">
                            <h3 class="text-lg font-bold text-gray-900"><?= $row['nama_produk'] ?></h3>
                            <p class="text-emerald-600 font-bold mt-1">Rp <?= number_format($row['harga'], 0, ',', '.') ?></p>
                            <p class="text-gray-500 text-xs mt-2 line-clamp-2"><?= $row['deskripsi'] ?></p>
                        </div>
                        <div class="p-4 pt-0 space-y-2">
                            <a href="detail.php?id=<?= $row['id_produk'] ?>" class="block text-center border border-emerald-600 text-emerald-600 py-1.5 rounded-lg text-sm hover:bg-emerald-50">Detail</a>
                            
                            <form method="POST">
                                <input type="hidden" name="id_produk" value="<?= $row['id_produk'] ?>">
                                <input type="hidden" name="jumlah" value="1">
                                <button type="submit" name="add_to_cart" class="w-full bg-emerald-600 text-white py-1.5 rounded-lg text-sm hover:bg-emerald-700">+ Keranjang</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="col-span-full text-center text-gray-500">Tanaman tidak ditemukan.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>