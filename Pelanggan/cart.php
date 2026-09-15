<?php
session_start();
require_once '../Config/database.php';

// Menangani aksi perubah jumlah item atau hapus dari keranjang
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

// Update Jumlah Item via Form Submisi (opsional)
if (isset($_POST['update_cart']) && isset($_POST['jumlah']) && is_array($_POST['jumlah'])) {
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
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Keranjang Belanja</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        planthub: {
                            bg: '#F4F6F3',
                            card: '#FFFFFF',
                            green: '#3B5E2B',
                            'green-hover': '#2e4a22',
                            'green-light': '#EBF2E8',
                            dark: '#1E291E',
                            muted: '#6B7280',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F4F6F3;
            color: #1E291E;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="antialiased min-h-screen flex p-4 lg:p-6 gap-6 text-stone-800">

    <!-- FLOATING SIDEBAR (Ukuran & Proporsi Identik Katalog/Dashboard) -->
    <aside class="w-64 bg-white rounded-2xl p-5 flex flex-col justify-between h-[calc(100vh-3rem)] sticky top-6 shrink-0 shadow-sm border border-stone-100/80 z-50">
        <div>
            <!-- LOGO PLANTHUB -->
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-8 px-1">
                <div class="w-9 h-9 rounded-xl bg-planthub-green flex items-center justify-center text-white shadow-sm shadow-emerald-950/20">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight text-planthub-dark leading-none">Plant<span class="text-planthub-green">Hub</span></h1>
                    <p class="text-[9px] font-extrabold tracking-widest text-stone-400 uppercase mt-0.5">STORE PORTAL</p>
                </div>
            </a>

            <!-- NAVIGASI SIDEBAR -->
            <nav class="flex flex-col space-y-1 text-xs font-medium text-stone-600">
                <a href="dashboard.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i> Beranda
                </a>
                <a href="katalog.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="store" class="w-4 h-4"></i> Katalog Shop
                </a>
                <a href="cart.php" class="bg-planthub-green text-white font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag" class="w-4 h-4"></i> Keranjang</span>
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </a>
                <a href="riwayat.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="history" class="w-4 h-4"></i> Riwayat Order
                </a>
                <a href="chat.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="message-square" class="w-4 h-4"></i> Konsultasi
                </a>
            </nav>
        </div>

        <!-- BOTTOM WIDGET & LOGOUT -->
        <div class="space-y-3 pt-3 border-t border-stone-100">
            <!-- TROLI BELANJA WIDGET -->
            <div class="bg-stone-50 p-3 rounded-xl flex items-center justify-between border border-stone-100">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-stone-500"></i>
                    <span class="text-xs font-semibold text-stone-700">Troli Belanja</span>
                </div>
                <span class="w-5 h-5 rounded-full bg-planthub-green text-white text-[10px] font-bold flex items-center justify-center">
                    <?= $cart_count ?>
                </span>
            </div>

            <!-- LOGOUT BUTTON -->
            <a href="../Auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center justify-center gap-1.5 text-xs font-bold text-red-500 hover:text-red-600 py-1 transition">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar Sesi
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA KERANJANG BELANJA -->
    <main class="flex-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-100/80 flex flex-col h-[calc(100vh-3rem)] overflow-y-auto custom-scrollbar">

        <!-- HEADER TOP BAR -->
        <header class="flex items-center justify-between pb-5 border-b border-stone-100 mb-6">
            <div>
                <h2 class="text-xl font-bold text-stone-900 flex items-center gap-2">
                    Keranjang Belanja <span class="text-lg">🛒</span>
                </h2>
                <p class="text-xs text-stone-400 mt-0.5">Kelola item pilihanmu sebelum melakukan proses pembayaran.</p>
            </div>
            <a href="katalog.php" class="text-xs font-bold text-planthub-green hover:underline flex items-center gap-1">
                &larr; Lanjut Belanja
            </a>
        </header>

        <!-- KONTEN KERANJANG -->
        <section class="flex-1">
            <?php if (empty($products_in_cart)): ?>
                <div class="bg-stone-50/50 rounded-2xl p-12 text-center border border-stone-100 my-4">
                    <i data-lucide="shopping-cart" class="w-12 h-12 text-stone-300 mx-auto mb-3"></i>
                    <p class="text-stone-500 font-semibold text-xs">Keranjang belanja Anda masih kosong.</p>
                    <a href="katalog.php" class="inline-block mt-4 bg-planthub-green hover:bg-planthub-green-hover text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-sm transition">
                        Belanja Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- DAFTAR ITEM KERANJANG -->
                    <div class="lg:col-span-2 bg-stone-50/50 rounded-2xl border border-stone-100 p-5 space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-stone-200/60">
                            <span class="text-xs font-bold text-stone-400 uppercase tracking-wider">Daftar Produk (<?= count($products_in_cart) ?>)</span>
                            <a href="cart.php?action=clear" onclick="return confirm('Kosongkan semua keranjang?');" class="text-xs font-bold text-red-500 hover:underline">
                                Kosongkan Keranjang
                            </a>
                        </div>

                        <div class="divide-y divide-stone-200/60">
                            <?php foreach ($products_in_cart as $item): ?>
                                <div class="py-3.5 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3.5">
                                        <img src="<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-14 h-14 object-cover rounded-xl border border-stone-100 bg-white">
                                        <div>
                                            <h4 class="font-bold text-xs text-stone-800"><?= htmlspecialchars($item['name']) ?></h4>
                                            <p class="text-xs font-black text-planthub-green mt-0.5">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center bg-white border border-stone-200 rounded-lg p-0.5 shadow-sm">
                                            <a href="cart.php?action=decrease&id=<?= $item['id'] ?>" class="w-6 h-6 rounded flex items-center justify-center font-bold text-xs text-stone-600 hover:bg-stone-100 transition">-</a>
                                            <span class="text-xs font-bold px-3 text-stone-800"><?= $item['qty'] ?></span>
                                            <a href="cart.php?action=increase&id=<?= $item['id'] ?>" class="w-6 h-6 rounded flex items-center justify-center font-bold text-xs text-stone-600 hover:bg-stone-100 transition">+</a>
                                        </div>
                                        
                                        <a href="cart.php?action=remove&id=<?= $item['id'] ?>" class="p-1.5 text-stone-400 hover:text-red-500 transition rounded-lg hover:bg-red-50" title="Hapus Item">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- RINGKASAN PEMBAYARAN -->
                    <div class="bg-stone-50/50 rounded-2xl border border-stone-100 p-5 space-y-4 h-fit">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-stone-400 border-b border-stone-200/60 pb-3">Ringkasan Pembayaran</h3>
                        
                        <div class="space-y-2 text-xs text-stone-600">
                            <div class="flex justify-between">
                                <span>Subtotal Produk</span>
                                <span class="font-bold text-stone-800">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Estimasi Ongkir</span>
                                <span class="font-medium text-emerald-600">Gratis</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center text-xs border-t border-stone-200/60 pt-3">
                            <span class="font-bold text-stone-800">Total Bayar:</span>
                            <span class="text-planthub-green text-base font-extrabold">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                        </div>

                        <a href="checkout.php" class="block w-full text-center py-2.5 bg-planthub-green hover:bg-planthub-green-hover text-white rounded-xl font-bold text-xs shadow-sm transition">
                            Lanjut ke Pembayaran
                        </a>
                    </div>

                </div>
            <?php endif; ?>
        </section>

        <!-- FOOTER HALAMAN KERANJANG -->
        <footer class="mt-8 pt-4 border-t border-stone-100 text-center text-[11px] text-stone-400 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>© 2026 PlantHub Store Portal. All rights reserved.</p>
            <div class="flex items-center gap-3">
                <a href="#" class="hover:underline">Privasi</a>
                <span>•</span>
                <a href="#" class="hover:underline">Bantuan</a>
            </div>
        </footer>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>