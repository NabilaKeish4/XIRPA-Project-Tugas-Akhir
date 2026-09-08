<?php
session_start();
require_once '../Config/database.php';

if (isset($_GET['action'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($_GET['action'] === 'increase') {
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
    } elseif ($_GET['action'] === 'decrease') {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }
    } elseif ($_GET['action'] === 'remove') {
        unset($_SESSION['cart'][$id]);
    } elseif ($_GET['action'] === 'clear') {
        $_SESSION['cart'] = [];
    }
    header('Location: cart.php');
    exit;
}

<<<<<<< HEAD
// Update Jumlah Item
if (isset($_POST['update_cart']) && isset($_POST['jumlah']) && is_array($_POST['jumlah'])) {
    foreach ($_POST['jumlah'] as $id => $qty) {
        $id = (int)$id;
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
=======
$cart_items = $_SESSION['cart'] ?? [];
$cart_count = array_sum($cart_items);

// Ambil Detail Produk di Keranjang dari DB
$products_in_cart = [];
$total_bayar = 0;

if (!empty($cart_items)) {
    $ids = implode(',', array_map('intval', array_keys($cart_items)));
    $query = "SELECT * FROM produk WHERE id IN ($ids)";
    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $qty = $cart_items[$row['id']];
            $subtotal = $row['harga_jual'] * $qty;
            $total_bayar += $subtotal;

            $products_in_cart[] = [
                'id' => $row['id'],
                'name' => $row['nama_tanaman'],
                'price' => $row['harga_jual'],
                'image' => !empty($row['gambar']) ? "../assets/img/" . $row['gambar'] : 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400',
                'qty' => $qty,
                'subtotal' => $subtotal
            ];
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PlantShop - Keranjang Belanja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { primary: '#2E7D32', 'primary-hover': '#236327', 'primary-light': '#E8F5E9' } } } } }
    </script>
</head>
<body class="bg-[#F9F8F6] flex min-h-screen font-['Plus_Jakarta_Sans'] text-stone-800">

    <aside class="w-64 bg-white border-r border-stone-200 flex flex-col justify-between h-screen sticky top-0 shrink-0 p-6">
        <div>
<<<<<<< HEAD
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">HOME</a>
                <a href="dashboard.php#katalog" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Shop</a>
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>Keranjang</span>
                    <span class="bg-brand-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?></span>
=======
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-10">
                <div class="w-9 h-9 rounded-xl bg-brand-primary flex items-center justify-center text-white"><i data-lucide="sprout"></i></div>
                <span class="text-xl font-extrabold text-stone-800">Plant<span class="text-brand-primary">Shop</span></span>
            </a>
            <nav class="flex flex-col space-y-2 text-xs font-bold uppercase text-stone-500">
                <a href="dashboard.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="layout-dashboard"></i> Beranda</a>
                <a href="katalog.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="store"></i> Katalog Shop</a>
                <a href="cart.php" class="text-brand-primary font-extrabold bg-brand-primary-light px-4 py-3 rounded-xl flex items-center justify-between">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag"></i> Keranjang</span>
                    <span class="bg-brand-primary text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $cart_count ?></span>
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
                </a>
                <a href="riwayat.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="history"></i> Riwayat Order</a>
                <a href="chat.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="message-square"></i> Konsultasi</a>
            </nav>
        </div>
<<<<<<< HEAD
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php#katalog" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Lanjut Belanja</a>
        </div>
=======
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
    </aside>

    <main class="flex-1 p-8 max-w-5xl">
        <h1 class="text-2xl font-black text-stone-900 mb-6 uppercase flex items-center gap-2">
            <i data-lucide="shopping-bag" class="text-brand-primary"></i> Keranjang Belanja Anda
        </h1>

        <?php if (empty($products_in_cart)): ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-stone-200">
                <i data-lucide="shopping-cart" class="w-12 h-12 text-stone-300 mx-auto mb-3"></i>
                <p class="text-stone-500 font-semibold text-sm">Keranjang belanja Anda masih kosong.</p>
                <a href="katalog.php" class="inline-block mt-4 bg-brand-primary text-white font-bold text-xs uppercase px-6 py-3 rounded-xl">Belanja Sekarang</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white rounded-2xl border border-stone-200 p-6 space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-stone-100">
                        <span class="text-xs font-bold text-stone-400 uppercase">Daftar Produk</span>
                        <a href="cart.php?action=clear" class="text-xs text-red-500 hover:underline">Kosongkan Keranjang</a>
                    </div>

                    <div class="divide-y divide-stone-100">
                        <?php foreach ($products_in_cart as $item): ?>
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= $item['image'] ?>" class="w-12 h-12 object-cover rounded-lg">
                                    <div>
                                        <h4 class="font-bold text-xs text-stone-800 uppercase"><?= htmlspecialchars($item['name']) ?></h4>
                                        <p class="text-xs font-black text-brand-primary">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="cart.php?action=decrease&id=<?= $item['id'] ?>" class="w-6 h-6 bg-stone-100 rounded flex items-center justify-center font-bold text-xs">-</a>
                                    <span class="text-xs font-bold px-2"><?= $item['qty'] ?></span>
                                    <a href="cart.php?action=increase&id=<?= $item['id'] ?>" class="w-6 h-6 bg-stone-100 rounded flex items-center justify-center font-bold text-xs">+</a>
                                    <a href="cart.php?action=remove&id=<?= $item['id'] ?>" class="text-red-400 hover:text-red-600 ml-2"><i data-lucide="trash-2" class="w-4 h-4"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-stone-200 p-6 space-y-4 h-fit">
                    <h3 class="text-sm font-bold uppercase text-stone-800">Ringkasan Pembayaran</h3>
                    <div class="flex justify-between text-xs font-bold border-t pt-3">
                        <span>Total Bayar:</span>
                        <span class="text-brand-primary text-sm font-black">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                    </div>
                    <a href="checkout.php" class="block w-full text-center py-3 bg-brand-primary hover:bg-brand-primary-hover text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-md">
                        Lanjut ke Pembayaran
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>