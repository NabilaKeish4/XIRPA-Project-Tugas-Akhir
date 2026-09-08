<?php
session_start();
require_once '../Config/database.php';

// Menangani Tambah ke Keranjang / POS
if (isset($_POST['add_to_cart'])) {
    $id_produk = (int)$_POST['id_produk'];
    $jumlah = (int)($_POST['jumlah'] ?? 1);

    if ($jumlah < 1) {
        $jumlah = 1;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$id_produk])) {
        $_SESSION['cart'][$id_produk] += $jumlah;
    } else {
        $_SESSION['cart'][$id_produk] = $jumlah;
    }

    if (isset($_POST['buy_now'])) {
        header('Location: cart.php');
        exit;
    }

    header('Location: dashboard.php?added=1#katalog');
    exit;
}

// Query mengambil produk + JOIN nama kategori
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$query = "SELECT p.*, k.nama_kategori 
          FROM produk p 
          LEFT JOIN kategori k ON p.kategori_id = k.id 
          WHERE p.nama_tanaman LIKE '%$search%'
          ORDER BY p.id DESC";
$result = mysqli_query($conn, $query);

$productsData = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $productsData[] = [
            'id' => (int)$row['id'],
            'name' => $row['nama_tanaman'],
            'category' => $row['nama_kategori'] ?? 'Tanaman',
            'price' => (int)$row['harga_jual'],
            'stock' => (int)($row['stok'] ?? 0),
            'image' => !empty($row['gambar']) ? "../assets/img/" . $row['gambar'] : 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400',
            'out_of_stock' => ((int)($row['stok'] ?? 0) <= 0)
        ];
    }
}

// Fallback Dummy Data
if (empty($productsData) && empty($search)) {
    $productsData = [
        ['id' => 1, 'name' => 'Monstera Deliciosa', 'category' => 'Indoor', 'price' => 125000, 'stock' => 8, 'image' => 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 2, 'name' => 'Snake Plant (Sansevieria)', 'category' => 'Indoor', 'price' => 45000, 'stock' => 15, 'image' => 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 3, 'name' => 'Fiddle Leaf Fig', 'category' => 'Indoor', 'price' => 210000, 'stock' => 3, 'image' => 'https://images.unsplash.com/photo-1545241047-6083a3684587?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 4, 'name' => 'Calathea Orbifolia', 'category' => 'Indoor', 'price' => 85000, 'stock' => 0, 'image' => 'https://images.unsplash.com/photo-1599598425947-320f323c683b?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => true],
        ['id' => 5, 'name' => 'Pot Terakota Minimalis 20cm', 'category' => 'Pot', 'price' => 35000, 'stock' => 24, 'image' => 'https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
        ['id' => 6, 'name' => 'Media Tanam Organik Premium 5kg', 'category' => 'Media Tanam', 'price' => 28000, 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?auto=format&fit=crop&q=80&w=400', 'out_of_stock' => false],
    ];
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Dashboard Pelanggan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
                            danger: '#E53E3E',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="antialiased flex min-h-screen bg-[#F9F8F6] text-stone-800">

<<<<<<< HEAD
    <!-- Sidebar Navbar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6">
=======
    <!-- SIDE NAVBAR -->
    <aside class="w-64 bg-white border-r border-stone-200 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6">
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
        <div>
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-10">
                <div class="w-9 h-9 rounded-xl bg-brand-primary flex items-center justify-center text-white shadow-md shadow-emerald-900/20">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <span class="text-xl font-extrabold tracking-tight text-stone-800">Plant<span class="text-brand-primary">Shop</span></span>
            </a>
            
<<<<<<< HEAD
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>HOME</span>
                    <span class="w-1.5 h-1.5 bg-brand-500 rounded-full"></span>
                </a>
                <a href="dashboard.php#katalog" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50 flex items-center justify-between">
                    <span>Shop</span>
=======
            <nav class="flex flex-col space-y-2 text-xs font-bold tracking-wider uppercase text-stone-500">
                <a href="dashboard.php" class="text-brand-primary font-extrabold bg-brand-primary-light px-4 py-3 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        <span>Beranda</span>
                    </span>
                    <span class="w-2 h-2 bg-brand-primary rounded-full"></span>
                </a>

                <a href="katalog.php" class="hover:text-brand-primary hover:bg-stone-50 px-4 py-3 rounded-xl flex items-center gap-2.5 transition">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Katalog Shop</span>
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
                </a>

                <a href="cart.php" class="hover:text-brand-primary hover:bg-stone-50 px-4 py-3 rounded-xl flex items-center justify-between transition">
                    <span class="flex items-center gap-2.5">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        <span>Keranjang</span>
                    </span>
                    <?php if ($cart_count > 0): ?>
                        <span class="bg-brand-primary text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $cart_count ?></span>
                    <?php else: ?>
                        <span class="text-stone-400 font-normal">(0)</span>
                    <?php endif; ?>
                </a>

                <a href="riwayat.php" class="hover:text-brand-primary hover:bg-stone-50 px-4 py-3 rounded-xl flex items-center gap-2.5 transition">
                    <i data-lucide="history" class="w-4 h-4"></i>
                    <span>Riwayat Order</span>
                </a>

                <a href="chat.php" class="hover:text-brand-primary hover:bg-stone-50 px-4 py-3 rounded-xl flex items-center gap-2.5 transition">
                    <i data-lucide="message-square" class="w-4 h-4"></i>
                    <span>Konsultasi</span>
                </a>
            </nav>
        </div>

        <div class="border-t border-stone-100 pt-6 space-y-3">
            <a href="cart.php" class="flex items-center justify-between p-3 rounded-xl bg-stone-50 hover:bg-brand-primary-light transition text-stone-700 hover:text-brand-primary">
                <div class="flex items-center gap-2.5">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                    <span class="text-xs font-bold uppercase tracking-wider">Troli Belanja</span>
                </div>
                <span class="bg-brand-primary text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $cart_count ?></span>
            </a>
            <a href="../auth/login.php" class="flex items-center justify-center gap-2 text-xs font-bold text-red-500 hover:underline py-2">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                <span>Keluar</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 overflow-y-auto">

        <!-- Hero Section -->
        <section class="max-w-6xl mx-auto px-8 py-12 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-brand-primary-light text-brand-primary mb-4 border border-emerald-200">
                    🌱 PlantShop Customer Dashboard
                </span>
                <h1 class="text-4xl lg:text-5xl font-black text-stone-900 leading-tight uppercase tracking-tight">
                    PLANT TREE CREATE A <br><span class="text-brand-primary">GREEN FUTURE</span>
                </h1>
                <p class="text-stone-500 text-xs mt-4 leading-relaxed max-w-md">
                    Trees absorb carbon dioxide, a greenhouse gas that contributes to climate change, and release oxygen, which we need for every breath.
                </p>

                <div class="mt-8 flex items-center gap-4">
                    <a href="katalog.php" class="bg-brand-primary hover:bg-brand-primary-hover text-white font-bold px-7 py-3 rounded-2xl text-xs uppercase tracking-wider transition shadow-md shadow-emerald-900/20">
                        Lihat Semua Katalog
                    </a>
                    <a href="chat.php" class="flex items-center gap-2.5 text-xs font-bold text-stone-800 hover:text-brand-primary transition">
                        <span class="w-8 h-8 rounded-full bg-stone-900 text-white flex items-center justify-center">
                            <i data-lucide="play" class="w-3.5 h-3.5 fill-current ml-0.5"></i>
                        </span>
                        <span>Panduan Perawatan</span>
                    </a>
                </div>
            </div>

            <div class="relative flex justify-center items-center">
                <div class="w-72 h-72 bg-brand-primary-light rounded-full absolute -z-10 blur-2xl"></div>
                <img src="https://images.unsplash.com/photo-1545241047-6083a3684587?q=80&w=800&auto=format&fit=crop" class="w-72 object-contain drop-shadow-2xl rounded-3xl" alt="Plant Tree">
            </div>
        </section>

        <!-- Search Bar -->
        <section class="max-w-xl mx-auto px-8 mb-8">
            <form method="GET" action="dashboard.php" class="flex gap-2 bg-white p-2 rounded-2xl shadow-sm border border-stone-200">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Cari tanaman hias..." class="w-full px-4 py-2 text-xs bg-transparent focus:outline-none">
                <button type="submit" class="bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2 rounded-xl text-xs font-bold uppercase transition">
                    Cari
                </button>
            </form>
        </section>

        <!-- Section Title -->
        <section id="katalog" class="max-w-6xl mx-auto px-8 pt-4 pb-6 text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-primary">KATALOG PLANT SHOP</p>
            <h2 class="text-2xl font-black uppercase text-stone-900 mt-1">TANAMAN HIAS SIAP JUAL</h2>
        </section>

        <!-- Product Cards -->
        <main class="max-w-6xl mx-auto px-8 pb-16">
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

<<<<<<< HEAD
                            <!-- Opsi POS Sales & Keranjang -->
                            <div class="mt-6 space-y-2">
                                <form method="POST" action="dashboard.php" class="flex flex-col gap-2">
                                    <input type="hidden" name="id_produk" value="<?= (int)$row['id_produk'] ?>">
                                    <input type="hidden" name="jumlah" value="1">
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="submit" name="add_to_cart" class="w-full bg-brand-light text-brand-600 hover:bg-brand-500 hover:text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition">
                                            + Cart
                                        </button>
                                        <button type="submit" name="buy_now" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-sm">
                                            Beli Langsung
                                        </button>
                                    </div>
                                </form>
                                <a href="detail.php?id=<?= (int)$row['id_produk'] ?>" class="block text-center text-xs font-bold text-gray-500 hover:text-gray-900 py-1">Lihat Detail & Perawatan</a>
=======
                            <div class="text-center mt-4">
                                <h3 class="font-extrabold uppercase text-stone-800 text-sm tracking-wide"><?= htmlspecialchars($row['name']) ?></h3>
                                <div class="mt-2 flex items-center justify-center gap-2">
                                    <span class="text-xs line-through text-stone-300 font-semibold">Rp <?= number_format($row['price'] * 1.15, 0, ',', '.') ?></span>
                                    <span class="text-sm font-black text-brand-primary">Rp <?= number_format($row['price'], 0, ',', '.') ?></span>
                                </div>
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
                            </div>
                        </div>

                        <div class="mt-5 pt-3 border-t border-stone-100">
                            <form method="POST" action="dashboard.php" class="grid grid-cols-2 gap-2">
                                <input type="hidden" name="id_produk" value="<?= $row['id'] ?>">
                                <input type="hidden" name="jumlah" value="1">
                                
                                <button type="submit" name="add_to_cart" <?= $row['out_of_stock'] ? 'disabled' : '' ?> class="<?= $row['out_of_stock'] ? 'bg-stone-200 text-stone-400 cursor-not-allowed' : 'bg-brand-primary-light text-brand-primary hover:bg-brand-primary hover:text-white' ?> font-bold py-2 rounded-xl text-[11px] uppercase tracking-wider transition text-center flex items-center justify-center gap-1">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    <span>Cart</span>
                                </button>

                                <button type="submit" name="add_to_cart" value="1" onclick="this.name='buy_now';" <?= $row['out_of_stock'] ? 'disabled' : '' ?> class="<?= $row['out_of_stock'] ? 'bg-stone-200 text-stone-400 cursor-not-allowed' : 'bg-brand-primary hover:bg-brand-primary-hover text-white shadow-sm' ?> font-bold py-2 rounded-xl text-[11px] uppercase tracking-wider transition text-center">
                                    Beli
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>