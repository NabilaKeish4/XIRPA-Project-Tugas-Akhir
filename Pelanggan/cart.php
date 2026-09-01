<?php
session_start();
require_once '../Config/database.php';

// Hapus Item
if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    unset($_SESSION['cart'][$id_hapus]);
    header('Location: cart.php');
    exit;
}

// Update Jumlah Item
if (isset($_POST['update_cart'])) {
    foreach ($_POST['jumlah'] as $id => $qty) {
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
    <title>Keranjang Belanja - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 text-gray-800">
    <div class="container mx-auto p-6 max-w-4xl">
        <h1 class="text-2xl font-bold text-emerald-900 mb-6">Keranjang Belanja Anda 🛒</h1>

        <?php if (!empty($_SESSION['cart'])): ?>
            <form method="POST">
                <div class="bg-white rounded-xl shadow border overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-emerald-100 text-emerald-900 text-sm">
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
                                $res = mysqli_query($conn, "SELECT * FROM produk WHERE id_produk = '$id'");
                                $p = mysqli_fetch_assoc($res);
                                $subtotal = $p['harga'] * $jumlah;
                                $grand_total += $subtotal;
                            ?>
                            <tr>
                                <td class="p-4 font-semibold"><?= $p['nama_produk'] ?></td>
                                <td class="p-4">Rp <?= number_format($p['harga'], 0, ',', '.') ?></td>
                                <td class="p-4">
                                    <input type="number" name="jumlah[<?= $id ?>]" value="<?= $jumlah ?>" min="1" class="w-16 border rounded p-1 text-center">
                                </td>
                                <td class="p-4 font-semibold">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                <td class="p-4 text-center">
                                    <a href="cart.php?hapus=<?= $id ?>" class="text-red-500 hover:underline">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-between items-center bg-white p-4 rounded-xl shadow">
                    <div>
                        <span class="text-gray-600">Total Pembayaran: </span>
                        <span class="text-2xl font-bold text-emerald-600">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
                    </div>
                    <div class="space-x-2">
                        <button type="submit" name="update_cart" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">Update Cart</button>
                        <a href="checkout.php" class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700 font-semibold">Lanjut Checkout &rarr;</a>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-white p-8 text-center rounded-xl shadow">
                <p class="text-gray-500 mb-4">Keranjang belanja Anda masih kosong.</p>
                <a href="dashboard.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">Mulai Belanja</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>