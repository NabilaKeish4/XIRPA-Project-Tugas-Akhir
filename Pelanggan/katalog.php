<?php
session_start();

// Data Dummy Produk (Tanpa perlu koneksi database)
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

// Menangani Tambah ke Keranjang / Beli Langsung
if (isset($_POST['add_to_cart'])) {
    $id_produk = (int)$_POST['id_produk'];
    $jumlah = (int)($_POST['jumlah'] ?? 1);

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$id_produk])) {
        $_SESSION['cart'][$id_produk] += $jumlah;
    } else {
        $_SESSION['cart'][$id_produk] = $jumlah;
    }

    if (isset($_POST['action_type']) && $_POST['action_type'] === 'buy_now') {
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
    <title>PlantShop - Katalog Shop</title>
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
                        brand: {
                            bg: '#F9F8F6',
                            text: '#2D3748',
                            primary: '#2E7D32',
                            'primary-hover': '#236327',
                            'primary-light': '#E8F5E9',
                            sage: '#81C784',
                            danger: '#E53E3E',
                            'danger-light': '#FFF5F5',
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
            background-color: #F9F8F6;
            color: #2D3748;
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
<body class="antialiased min-h-screen flex text-stone-800">

    <!-- SIDEBAR NAVBAR -->
    <aside class="w-64 bg-white border-r border-stone-200 flex flex-col justify-between h-screen sticky top-0 shrink-0 p-6 z-50">
        <div>
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-10">
                <div class="w-9 h-9 rounded-xl bg-brand-primary flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                    <i data-lucide="sprout"></i>
                </div>
                <span class="text-xl font-extrabold text-stone-800">Plant<span class="text-brand-primary">Shop</span></span>
            </a>
            <nav class="flex flex-col space-y-2 text-xs font-bold uppercase text-stone-500">
                <a href="dashboard.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5">
                    <i data-lucide="layout-dashboard"></i> Beranda
                </a>
                <a href="katalog.php" class="text-brand-primary font-extrabold bg-brand-primary-light px-4 py-3 rounded-xl flex items-center justify-between">
                    <span class="flex items-center gap-2.5"><i data-lucide="store"></i> Katalog Shop</span>
                    <span class="w-2 h-2 bg-brand-primary rounded-full"></span>
                </a>
                <a href="cart.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center justify-between">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag"></i> Keranjang</span>
                    <span class="bg-brand-primary text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $cart_count ?></span>
                </a>
                <a href="riwayat.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5">
                    <i data-lucide="history"></i> Riwayat Order
                </a>
                <a href="chat.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5">
                    <i data-lucide="message-square"></i> Konsultasi
                </a>
            </nav>
        </div>
        <div class="border-t border-stone-100 pt-6">
            <a href="../Auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center justify-center gap-2 text-xs font-bold text-red-500 hover:underline py-2">
                <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA KATALOG -->
    <main class="flex-1 flex flex-col h-screen overflow-y-auto custom-scrollbar bg-brand-bg">

        <!-- Section Title -->
        <section id="katalog" class="max-w-6xl mx-auto px-8 pt-8 pb-6 text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-primary">KATALOG PLANT SHOP</p>
            <h2 class="text-2xl font-black uppercase text-stone-900 mt-1">TANAMAN HIAS SIAP JUAL</h2>
        </section>

        <!-- Product Cards -->
        <main class="max-w-6xl mx-auto px-8 pb-16 w-full">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($productsData as $row): ?>
                    <div class="bg-white rounded-2xl p-4 border border-stone-200/80 shadow-sm hover:shadow-xl transition duration-300 flex flex-col justify-between group">
                        <div>
                            <div class="bg-emerald-50/60 rounded-xl p-4 flex justify-center items-center h-56 relative overflow-hidden">
                                <img src="<?= $row['image'] ?>" alt="<?= htmlspecialchars($row['name']) ?>" class="h-44 object-cover rounded-lg group-hover:scale-105 transition duration-300">
                                
                                <span class="absolute top-2.5 left-2.5 bg-white/90 text-stone-700 text-[10px] font-bold px-2.5 py-0.5 rounded-md uppercase shadow-sm border border-stone-200">
                                    <?= htmlspecialchars($row['category']) ?>
                                </span>
                            </div>

                            <div class="text-center mt-4">
                                <h3 class="font-extrabold uppercase text-stone-800 text-sm tracking-wide"><?= htmlspecialchars($row['name']) ?></h3>
                                <div class="mt-2 flex items-center justify-center gap-2">
                                    <span class="text-xs line-through text-stone-300 font-semibold">Rp <?= number_format($row['price'] * 1.15, 0, ',', '.') ?></span>
                                    <span class="text-sm font-black text-brand-primary">Rp <?= number_format($row['price'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 pt-3 border-t border-stone-100">
                            <form method="POST" action="katalog.php" class="grid grid-cols-2 gap-2">
                                <input type="hidden" name="id_produk" value="<?= $row['id'] ?>">
                                <input type="hidden" name="jumlah" value="1">
                                <input type="hidden" name="action_type" value="cart">
                                
                                <button type="submit" name="add_to_cart" <?= $row['out_of_stock'] ? 'disabled' : '' ?> class="<?= $row['out_of_stock'] ? 'bg-stone-200 text-stone-400 cursor-not-allowed' : 'bg-brand-primary-light text-brand-primary hover:bg-brand-primary hover:text-white' ?> font-bold py-2 rounded-xl text-[11px] uppercase tracking-wider transition text-center flex items-center justify-center gap-1">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    <span>Cart</span>
                                </button>

                                <button type="submit" name="add_to_cart" value="1" onclick="this.form.action_type.value='buy_now';" <?= $row['out_of_stock'] ? 'disabled' : '' ?> class="<?= $row['out_of_stock'] ? 'bg-stone-200 text-stone-400 cursor-not-allowed' : 'bg-brand-primary hover:bg-brand-primary-hover text-white shadow-sm' ?> font-bold py-2 rounded-xl text-[11px] uppercase tracking-wider transition text-center">
                                    Beli
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>