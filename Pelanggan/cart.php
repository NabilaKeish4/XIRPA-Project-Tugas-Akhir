<?php
session_start();
require_once '../Config/database.php';

// Hapus Item
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    unset($_SESSION['cart'][$id_hapus]);
    header('Location: cart.php');
    exit;
}

// Update Jumlah Item
if (isset($_POST['update_cart']) && isset($_POST['jumlah'])) {
    foreach ($_POST['jumlah'] as $id => $qty) {
        $id = (int)$id;
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
    }
    header('Location: cart.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Keranjang Belanja - PlantShop</title>
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

    <!-- Sidebar Navbar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6">
        <div>
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">HOME</a>
                <a href="chat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Konsultasi</a>
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>Keranjang</span>
                    <span class="bg-brand-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?></span>
                </a>
            </nav>
        </div>
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Lanjut Belanja</a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto my-4">
            <h1 class="text-2xl font-black uppercase text-gray-900 mb-6">Keranjang Belanja 🛒</h1>

            <?php if (!empty($_SESSION['cart'])): ?>
                <form method="POST">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b">
                                    <th class="p-4">Produk</th>
                                    <th class="p-4">Harga</th>
                                    <th class="p-4">Jumlah</th>
                                    <th class="p-4">Subtotal</th>
                                    <th class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-sm">
                                <?php 
                                $grand_total = 0;
                                foreach ($_SESSION['cart'] as $id => $jumlah): 
                                    $id_clean = (int)$id;
                                    $res = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk = '$id_clean'");
                                    $p = mysqli_fetch_assoc($res);
                                    if (!$p) continue;
                                    $subtotal = $p['harga'] * $jumlah;
                                    $grand_total += $subtotal;
                                ?>
                                <tr>
                                    <td class="p-4 font-bold text-gray-900"><?= htmlspecialchars($p['nama_tanaman']) ?></td>
                                    <td class="p-4">Rp <?= number_format($p['harga'], 0, ',', '.') ?></td>
                                    <td class="p-4">
                                        <input type="number" name="jumlah[<?= $id_clean ?>]" value="<?= (int)$jumlah ?>" min="1" class="w-16 border rounded-lg p-1 text-center text-xs font-bold">
                                    </td>
                                    <td class="p-4 font-bold text-gray-900">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                    <td class="p-4 text-center">
                                        <a href="cart.php?hapus=<?= $id_clean ?>" class="text-red-500 hover:underline text-xs font-bold uppercase">Hapus</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-between items-center bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <div>
                            <span class="text-xs text-gray-400 uppercase font-bold block">Total Pembayaran</span>
                            <span class="text-2xl font-black text-gray-900">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
                        </div>
                        <div class="space-x-2">
                            <button type="submit" name="update_cart" class="bg-gray-100 text-gray-700 px-4 py-2.5 rounded-xl text-xs font-bold uppercase">Update Cart</button>
                            <a href="checkout.php" class="bg-brand-500 hover:bg-brand-600 text-white px-6 py-2.5 rounded-xl text-xs font-bold uppercase transition inline-block">Checkout &rarr;</a>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="bg-white p-12 text-center rounded-2xl border border-gray-100 shadow-sm">
                    <p class="text-gray-400 mb-4 text-sm">Keranjang belanja Anda masih kosong.</p>
                    <a href="dashboard.php" class="bg-brand-500 text-white px-6 py-2.5 rounded-xl text-xs font-bold uppercase hover:bg-brand-600 inline-block">Mulai Belanja</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>