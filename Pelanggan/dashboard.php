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

    // Jika memilih "Beli Langsung"
    if (isset($_POST['buy_now'])) {
        header('Location: checkout.php');
        exit;
    }

    header('Location: cart.php');
    exit;
}

// Fitur Pencarian & Filter Produk
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$query = "SELECT * FROM produk WHERE nama_tanaman LIKE '%$search%' OR kategori LIKE '%$search%'";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Catalog & Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#63B745',
                            600: '#529E38',
                            light: '#E2F2DC'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex min-h-screen">

    <!-- Sidebar Navbar (Samping - Konsisten) -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6">
        <div>
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
            
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>HOME / SHOP</span>
                    <span class="w-1.5 h-1.5 bg-brand-500 rounded-full"></span>
                </a>
                <a href="chat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50 flex items-center justify-between">
                    <span>Konsultasi</span>
                </a>
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50 flex items-center justify-between">
                    <span>Keranjang</span>
                    <?php if (isset($_SESSION['cart']) && array_sum($_SESSION['cart']) > 0): ?>
                        <span class="bg-brand-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= array_sum($_SESSION['cart']) ?></span>
                    <?php else: ?>
                        <span class="text-gray-400">(0)</span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>

        <div class="border-t border-gray-100 pt-6 space-y-4">
            <a href="cart.php" class="flex items-center space-x-3 p-3 rounded-xl bg-gray-50 hover:bg-brand-light/30 transition text-gray-700 hover:text-brand-500">
                <div class="relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    <?php if (isset($_SESSION['cart']) && array_sum($_SESSION['cart']) > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-brand-500 text-white text-[9px] w-3.5 h-3.5 rounded-full flex items-center justify-center font-bold"><?= array_sum($_SESSION['cart']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider">Troli Belanja</span>
            </a>
            <a href="../auth/login.php" class="block text-center text-xs font-bold text-red-500 hover:underline py-1">Logout</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto">
        
        <!-- Hero Section -->
        <section class="max-w-7xl mx-auto px-8 py-12 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <h1 class="text-4xl lg:text-5xl font-black text-gray-900 leading-tight uppercase tracking-tight">
                    PLANT TREE CREATE A <span class="text-brand-500">GREEN FUTURE</span>
                </h1>
                <p class="text-gray-500 text-sm mt-4 leading-relaxed max-w-lg">
                    Trees absorb carbon dioxide, a greenhouse gas that contributes to climate change, and release oxygen, which we need for every breath.
                </p>

                <div class="mt-8 flex items-center space-x-4">
                    <a href="#katalog" class="bg-brand-500 hover:bg-brand-600 text-white font-bold px-8 py-3 rounded-full text-sm transition shadow-lg shadow-brand-500/30">Buy Now</a>
                    <a href="chat.php" class="border border-brand-500 text-brand-500 hover:bg-brand-light font-bold px-6 py-3 rounded-full text-sm transition">Tanya Admin</a>
                </div>
            </div>

            <div class="relative flex justify-center">
                <div class="w-72 h-72 lg:w-80 lg:h-80 bg-brand-light rounded-full absolute -z-10 blur-xl opacity-70"></div>
                <img src="https://images.unsplash.com/photo-1545241047-6083a3684587?q=80&w=800&auto=format&fit=crop" class="w-72 lg:w-80 object-contain drop-shadow-2xl rounded-3xl" alt="Plant Tree">
            </div>
        </section>

        <!-- Search Section -->
        <section class="max-w-xl mx-auto px-8 mb-12">
            <form method="GET" class="flex gap-2 bg-white p-2 rounded-full shadow-sm border border-gray-100">
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Cari tanaman atau kategori..." class="w-full px-6 py-2 text-sm bg-transparent focus:outline-none">
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white px-6 py-2 rounded-full text-xs font-bold uppercase transition">Search</button>
            </form>
        </section>

        <!-- Section Title -->
        <section id="katalog" class="max-w-7xl mx-auto px-8 pt-4 pb-8 text-center">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400">KATALOG PLANT SHOP</p>
            <h2 class="text-3xl font-black uppercase text-gray-900 mt-1">TANAMAN HIAS SIAP JUAL</h2>
        </section>

        <!-- Grid Katalog Tanaman -->
        <main class="max-w-7xl mx-auto px-8 pb-24">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 hover:shadow-xl transition group flex flex-col justify-between">
                            <div>
                                <div class="bg-brand-light/50 rounded-xl p-6 flex justify-center items-center h-64 relative overflow-hidden">
                                    <img src="../assets/img/<?= htmlspecialchars($row['foto_produk'] ?? 'default.jpg') ?>" alt="<?= htmlspecialchars($row['nama_tanaman']) ?>" class="h-52 object-contain group-hover:scale-105 transition duration-300">
                                    <span class="absolute top-3 left-3 bg-white/90 text-gray-700 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">
                                        <?= htmlspecialchars($row['kategori'] ?? 'Tanaman Hias') ?>
                                    </span>
                                </div>

                                <div class="text-center mt-4">
                                    <h3 class="font-black uppercase text-gray-900 text-lg tracking-wide"><?= htmlspecialchars($row['nama_tanaman']) ?></h3>
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2 px-2"><?= htmlspecialchars($row['deskripsi'] ?? 'Tanaman hias segar berkualitas.') ?></p>
                                    <div class="mt-3 flex items-center justify-center space-x-2">
                                        <span class="text-base font-black text-gray-900">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Opsi POS Sales & Keranjang -->
                            <div class="mt-6 space-y-2">
                                <form method="POST" class="flex flex-col gap-2">
                                    <input type="hidden" name="id_produk" value="<?= (int)$row['id_produk'] ?>">
                                    <input type="hidden" name="jumlah" value="1">
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="submit" name="add_to_cart" class="w-full bg-brand-light text-brand-600 hover:bg-brand-500 hover:text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition">
                                            + Cart
                                        </button>
                                        <button type="submit" name="add_to_cart" value="1" onclick="this.form.action='dashboard.php'; this.name='buy_now';" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5 rounded-xl text-xs uppercase tracking-wider transition shadow-sm">
                                            Beli Langsung
                                        </button>
                                    </div>
                                </form>
                                <a href="detail.php?id=<?= (int)$row['id_produk'] ?>" class="block text-center text-xs font-bold text-gray-500 hover:text-gray-900 py-1">Lihat Detail & Perawatan</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="col-span-full text-center text-gray-400 py-12">Tanaman tidak ditemukan.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

</body>
</html>