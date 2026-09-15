<?php
session_start();
include '../config/koneksi.php'; // Sesuaikan koneksi database

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 1. Handling Aksi Kuantitas & Hapus Item via GET
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'increase') {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]++;
        } else {
            $_SESSION['cart'][$id] = 1;
        }
    } elseif ($action === 'decrease') {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][$id]);
    }

    header("Location: cart.php");
    exit;
}

// 2. Fetch Data Produk Berdasarkan Session Keranjang
$cart_items = $_SESSION['cart'];
$products_in_cart = [];
$total_bayar = 0;

if (!empty($cart_items)) {
    $ids = implode(',', array_map('intval', array_keys($cart_items)));
    
    // Query fleksibel mendukung kolom 'id' atau 'id_produk'
    $query = "SELECT * FROM produk WHERE id IN ($ids) OR id_produk IN ($ids)";
    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $p_id = $row['id_produk'] ?? $row['id'];
            
            if (isset($cart_items[$p_id])) {
                $qty = $cart_items[$p_id];
                $subtotal = $row['harga_jual'] * $qty;
                $total_bayar += $subtotal;

                $products_in_cart[] = [
                    'id' => $p_id,
                    'name' => $row['nama_tanaman'],
                    'price' => $row['harga_jual'],
                    'image' => !empty($row['gambar']) ? "../assets/img/" . $row['gambar'] : 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400',
                    'qty' => $qty,
                    'subtotal' => $subtotal
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'planthub-green': '#2D5A27',
                        'planthub-green-hover': '#1E3E1A',
                        'planthub-cream': '#F9F8F3',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-planthub-cream text-gray-800 font-sans min-h-screen flex flex-col justify-between">

    <!-- Header -->
    <header class="bg-white/80 backdrop-blur-md sticky top-0 z-40 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="katalog.php" class="flex items-center gap-2">
                    <div class="bg-planthub-green text-white p-2 rounded-xl">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="font-bold text-xl text-gray-900 tracking-tight">PlantHub</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Konten Keranjang -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow w-full">
        <h1 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
            <i data-lucide="shopping-bag" class="text-planthub-green"></i> Keranjang Belanja
        </h1>

        <?php if (empty($products_in_cart)) : ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400">
                    <i data-lucide="shopping-cart" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-1">Keranjang kamu masih kosong</h3>
                <p class="text-gray-500 text-sm mb-6">Yuk, temukan berbagai tanaman segar untuk mempercantik ruanganmu!</p>
                <a href="katalog.php" class="inline-flex items-center gap-2 bg-planthub-green text-white font-semibold px-6 py-3 rounded-xl hover:bg-planthub-green-hover transition text-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Lihat Katalog Tanaman
                </a>
            </div>
        <?php else : ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
                <div class="divide-y divide-gray-100">
                    <?php foreach ($products_in_cart as $item) : ?>
                        <div class="p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-xl bg-gray-50">
                                <div>
                                    <h4 class="font-bold text-gray-900 text-base mb-1"><?= htmlspecialchars($item['name']) ?></h4>
                                    <p class="text-sm text-gray-500">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between w-full sm:w-auto gap-6 pt-2 sm:pt-0 border-t sm:border-0 border-gray-50">
                                <!-- Kontrol Kuantitas -->
                                <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
                                    <a href="cart.php?action=decrease&id=<?= $item['id'] ?>" class="px-3 py-1.5 hover:bg-gray-200 text-gray-600 font-bold transition">-</a>
                                    <span class="px-4 py-1.5 text-sm font-semibold text-gray-800 bg-white"><?= $item['qty'] ?></span>
                                    <a href="cart.php?action=increase&id=<?= $item['id'] ?>" class="px-3 py-1.5 hover:bg-gray-200 text-gray-600 font-bold transition">+</a>
                                </div>

                                <div class="text-right min-w-[100px]">
                                    <p class="font-bold text-planthub-green">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></p>
                                </div>

                                <!-- Tombol Hapus -->
                                <a href="cart.php?action=remove&id=<?= $item['id'] ?>" class="text-gray-400 hover:text-red-500 p-1 transition" title="Hapus Item">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Ringkasan Total -->
                <div class="bg-gray-50/50 p-6 flex items-center justify-between border-t border-gray-100">
                    <span class="font-bold text-gray-700">Total Pembayaran:</span>
                    <span class="text-2xl font-bold text-planthub-green">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Navigasi Aksi -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="katalog.php" class="text-planthub-green font-semibold hover:underline flex items-center gap-2 text-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Tambah Tanaman Lain
                </a>
                <a href="checkout.php" class="w-full sm:w-auto bg-planthub-green text-white font-bold px-8 py-3.5 rounded-xl hover:bg-planthub-green-hover transition text-center shadow-md">
                    Lanjut ke Checkout
                </a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-100 mt-12">
        <div class="max-w-7xl mx-auto px-4 py-6 text-center text-xs text-gray-400">
            &copy; <?= date('Y') ?> PlantHub. All rights reserved.
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>