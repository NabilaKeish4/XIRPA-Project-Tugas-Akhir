<?php
session_start();

// Data Dummy Produk PlantHub (Sama dengan Dashboard)
$productsData = [
    [
        'id' => 1,
        'name' => 'Monstera Deliciosa',
        'category' => 'Indoor Plant',
        'price' => 120000,
        'image' => 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400',
        'out_of_stock' => false
    ],
    [
        'id' => 2,
        'name' => 'Calathea Orbifolia',
        'category' => 'Indoor Plant',
        'price' => 85000,
        'image' => 'https://images.unsplash.com/photo-1592150621744-aca64f48394a?auto=format&fit=crop&q=80&w=400',
        'out_of_stock' => false
    ],
    [
        'id' => 3,
        'name' => 'Aglaonema Suksom',
        'category' => 'Outdoor Plant',
        'price' => 150000,
        'image' => 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?auto=format&fit=crop&q=80&w=400',
        'out_of_stock' => true
    ]
];

// Fitur Pencarian & Filter Kategori
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$filteredProducts = array_filter($productsData, function($product) use ($search, $category) {
    $matchSearch = empty($search) || strpos(strtolower($product['name']), strtolower($search)) !== false;
    $matchCategory = empty($category) || strtolower($product['category']) === strtolower($category);
    return $matchSearch && $matchCategory;
});

// Menangani Tambah ke Keranjang / Beli Langsung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $id_produk = (int)$_POST['id_produk'];
    $jumlah = (int)($_POST['jumlah'] ?? 1);
    $action_type = $_POST['action_type'] ?? 'cart';

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$id_produk])) {
        $_SESSION['cart'][$id_produk] += $jumlah;
    } else {
        $_SESSION['cart'][$id_produk] = $jumlah;
    }

    if ($action_type === 'buy_now') {
        header('Location: checkout.php');
        exit;
    }

    header('Location: katalog.php?added=1');
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Katalog Shop</title>
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

    <!-- FLOATING SIDEBAR (Ukuran & Proporsi Identik Dashboard) -->
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
                <a href="katalog.php" class="bg-planthub-green text-white font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5"><i data-lucide="store" class="w-4 h-4"></i> Katalog Shop</span>
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </a>
                <a href="cart.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center justify-between transition">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag" class="w-4 h-4"></i> Keranjang</span>
                    <span class="text-stone-400 text-[11px] font-semibold">(<?= $cart_count ?>)</span>
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

    <!-- KONTEN UTAMA KATALOG -->
    <main class="flex-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-100/80 flex flex-col h-[calc(100vh-3rem)] overflow-y-auto custom-scrollbar">

        <!-- HEADER TOP BAR -->
        <header class="flex items-center justify-between pb-5 border-b border-stone-100 mb-5">
            <div>
                <h2 class="text-xl font-bold text-stone-900 flex items-center gap-1.5">
                    Katalog Shop <span class="text-lg">🌿</span>
                </h2>
                <p class="text-xs text-stone-400 mt-0.5">Temukan koleksi tanaman terbaik untuk mempercantik ruanganmu.</p>
            </div>
            
            <a href="cart.php" class="w-9 h-9 rounded-xl border border-stone-200/80 flex items-center justify-center text-stone-600 hover:bg-stone-50 transition shadow-sm relative">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-planthub-green text-white text-[8px] font-bold rounded-full flex items-center justify-center">
                        <?= $cart_count ?>
                    </span>
                <?php endif; ?>
            </a>
        </header>

        <!-- SEARCH & FILTER BAR -->
        <section class="mb-6">
            <form method="GET" action="katalog.php" class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-stone-50/80 p-2.5 rounded-xl border border-stone-100">
                <div class="relative w-full sm:w-72">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-stone-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama tanaman..." class="w-full pl-8 pr-3 py-2 bg-white rounded-lg border border-stone-200/80 text-xs font-medium focus:outline-none focus:border-planthub-green transition">
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <select name="category" onchange="this.form.submit()" class="w-full sm:w-auto px-3 py-2 bg-white rounded-lg border border-stone-200/80 text-xs font-medium text-stone-700 focus:outline-none focus:border-planthub-green cursor-pointer">
                        <option value="">Semua Kategori</option>
                        <option value="Indoor Plant" <?= $category === 'Indoor Plant' ? 'selected' : '' ?>>Indoor Plant</option>
                        <option value="Outdoor Plant" <?= $category === 'Outdoor Plant' ? 'selected' : '' ?>>Outdoor Plant</option>
                    </select>

                    <?php if (!empty($search) || !empty($category)): ?>
                        <a href="katalog.php" class="px-2.5 py-2 text-xs font-bold text-red-500 hover:bg-red-50 rounded-lg transition">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- ALERT STATUS -->
        <?php if (isset($_GET['added'])): ?>
            <div class="mb-5 bg-planthub-green-light border border-emerald-200 text-planthub-green px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span>Produk berhasil ditambahkan ke keranjang belanja!</span>
            </div>
        <?php endif; ?>

        <!-- GRID PRODUK -->
        <section class="flex-1">
            <?php if (empty($filteredProducts)): ?>
                <div class="bg-stone-50/50 rounded-2xl p-10 text-center border border-stone-100 my-2">
                    <i data-lucide="package-search" class="w-10 h-10 text-stone-300 mx-auto mb-2"></i>
                    <p class="text-stone-500 text-xs font-medium">Produk tidak ditemukan.</p>
                    <a href="katalog.php" class="mt-2 inline-block text-xs font-bold text-planthub-green hover:underline">Reset pencarian</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($filteredProducts as $row): ?>
                        <div class="bg-white rounded-2xl p-3.5 border border-stone-100 shadow-sm hover:shadow-md transition flex flex-col justify-between group relative">
                            <div>
                                <div class="bg-stone-50 rounded-xl p-3 flex justify-center items-center h-48 relative overflow-hidden">
                                    <img src="<?= $row['image'] ?>" alt="<?= htmlspecialchars($row['name']) ?>" class="h-36 object-cover rounded-lg group-hover:scale-105 transition duration-300">
                                    
                                    <!-- BADGE KATEGORI -->
                                    <span class="absolute top-2.5 left-2.5 bg-white/90 text-stone-700 text-[9px] font-bold px-2 py-0.5 rounded-md uppercase shadow-sm">
                                        <?= htmlspecialchars($row['category']) ?>
                                    </span>

                                    <!-- BADGE STOK -->
                                    <?php if ($row['out_of_stock']): ?>
                                        <span class="absolute top-2.5 right-2.5 bg-red-100 text-red-600 text-[9px] font-bold px-2 py-0.5 rounded-md uppercase">
                                            Habis
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3 px-1">
                                    <h3 class="font-bold text-stone-800 text-xs"><?= htmlspecialchars($row['name']) ?></h3>
                                    <div class="mt-1.5 flex items-center justify-between">
                                        <div>
                                            <span class="text-[9px] line-through text-stone-400 font-medium block">Rp <?= number_format($row['price'] * 1.15, 0, ',', '.') ?></span>
                                            <span class="text-sm font-extrabold text-planthub-green">Rp <?= number_format($row['price'], 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- DUA FORM TERPISAH UNTUK AKSI '+ CART' DAN 'BELI' -->
                            <div class="mt-4 pt-2.5 border-t border-stone-100 grid grid-cols-2 gap-2">
                                <!-- Form Tambah ke Keranjang -->
                                <form method="POST" action="katalog.php">
                                    <input type="hidden" name="id_produk" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="jumlah" value="1">
                                    <input type="hidden" name="action_type" value="cart">
                                    <button type="submit" name="add_to_cart" <?= $row['out_of_stock'] ? 'disabled' : '' ?> class="w-full <?= $row['out_of_stock'] ? 'bg-stone-100 text-stone-400 cursor-not-allowed' : 'bg-planthub-green-light text-planthub-green hover:bg-planthub-green hover:text-white' ?> font-bold py-2 rounded-xl text-[11px] transition flex items-center justify-center gap-1">
                                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                                        <span>+ Cart</span>
                                    </button>
                                </form>

                                <!-- Form Beli Langsung -->
                                <form method="POST" action="katalog.php">
                                    <input type="hidden" name="id_produk" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="jumlah" value="1">
                                    <input type="hidden" name="action_type" value="buy_now">
                                    <button type="submit" name="add_to_cart" <?= $row['out_of_stock'] ? 'disabled' : '' ?> class="w-full <?= $row['out_of_stock'] ? 'bg-stone-200 text-stone-400 cursor-not-allowed' : 'bg-planthub-green hover:bg-planthub-green-hover text-white shadow-sm' ?> font-bold py-2 rounded-xl text-[11px] transition">
                                        Beli
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- FOOTER HALAMAN KATALOG -->
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