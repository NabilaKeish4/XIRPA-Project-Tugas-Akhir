<?php
session_start();
require_once '../Config/database.php';

// Data Pengguna
$user_id = $_SESSION['user_id'] ?? null;
$nama_user = $_SESSION['nama_user'] ?? 'Pelanggan';

// Hitung Item Keranjang
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Query Statistik Pelanggan dari Database
$total_orders = 0;
$pending_orders = 0;

if ($user_id && isset($conn)) {
    // Total Transaksi
    $q_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM pesanan WHERE user_id = '$user_id'");
    if ($q_total) {
        $total_orders = (int)(mysqli_fetch_assoc($q_total)['total'] ?? 0);
    }

    // Pesanan Aktif / Diproses
    $q_pending = mysqli_query($conn, "SELECT COUNT(*) as pending FROM pesanan WHERE user_id = '$user_id' AND status IN ('pending', 'diproses', 'dikirim')");
    if ($q_pending) {
        $pending_orders = (int)(mysqli_fetch_assoc($q_pending)['pending'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Dashboard</title>
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

    <!-- FLOATING SIDEBAR (Presisi & Sejajar) -->
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
                <a href="dashboard.php" class="bg-planthub-green text-white font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5"><i data-lucide="layout-grid" class="w-4 h-4"></i> Beranda</span>
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </a>
                <a href="katalog.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="store" class="w-4 h-4"></i> Katalog Shop
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

    <!-- KONTEN UTAMA DASHBOARD -->
    <main class="flex-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-100/80 flex flex-col h-[calc(100vh-3rem)] overflow-y-auto custom-scrollbar">

        <!-- HEADER TOP BAR -->
        <header class="flex items-center justify-between pb-5 border-b border-stone-100 mb-6">
            <div>
                <h2 class="text-xl font-bold text-stone-900 flex items-center gap-2">
                    <span>Halo,</span> 
                    <span class="text-planthub-green"><?= htmlspecialchars($nama_user) ?></span> 👋
                </h2>
                <p class="text-xs text-stone-400 mt-0.5">Hadirkan suasana segar dan asri di setiap sudut ruanganmu.</p>
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

        <!-- DASHBOARD BODY CONTENT -->
        <div class="space-y-6 flex-1">

            <!-- HERO BANNER GREEN CLEAN -->
            <section class="bg-planthub-green rounded-2xl p-6 lg:p-8 relative overflow-hidden text-white flex flex-col md:flex-row items-center justify-between shadow-sm">
                <div class="max-w-md space-y-3 z-10">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-white/10 text-white backdrop-blur-sm border border-white/20">
                        <i data-lucide="sparkles" class="w-3 h-3 text-yellow-300"></i>
                        Pilihan Koleksi Terbaik
                    </span>
                    
                    <h1 class="text-2xl lg:text-3xl font-extrabold leading-tight tracking-tight">
                        Verdantness & Green Vibe
                    </h1>
                    
                    <p class="text-white/80 text-xs leading-relaxed font-light">
                        Temukan keindahan tanaman hias pilihan untuk menciptakan atmosfer alami yang menenangkan.
                    </p>

                    <div class="pt-2 flex items-center gap-3">
                        <a href="katalog.php" class="bg-white text-planthub-green hover:bg-stone-100 font-bold px-5 py-2.5 rounded-xl text-xs transition shadow-sm flex items-center gap-2">
                            <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
                            <span>Belanja Sekarang</span>
                        </a>
                    </div>
                </div>

                <!-- HERO IMAGE & BADGES -->
                <div class="mt-6 md:mt-0 relative z-10 flex items-center justify-center">
                    <img src="https://images.unsplash.com/photo-1485955900006-10f4d324d411?q=80&w=600&auto=format&fit=crop" 
                         alt="Hero Plant" 
                         class="w-48 h-48 lg:w-56 lg:h-56 object-cover rounded-2xl border-4 border-white/20 shadow-lg rotate-2 hover:rotate-0 transition duration-500">

                    <div class="absolute -bottom-3 -left-4 bg-white/95 text-stone-800 p-2.5 rounded-xl shadow-md backdrop-blur-md flex items-center gap-2.5 border border-stone-100 hidden sm:flex">
                        <div class="w-8 h-8 rounded-lg bg-planthub-green-light text-planthub-green flex items-center justify-center">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-stone-400 uppercase">Garansi Segar</p>
                            <p class="text-xs font-extrabold">100% Quality Plant</p>
                        </div>
                    </div>
                </div>

                <!-- Background Decor -->
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
            </section>

            <!-- STATS CARDS WHITE CLEAN -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-5">
                
                <!-- Stat Card 1 -->
                <div class="bg-stone-50/50 p-4 rounded-2xl border border-stone-100 flex items-center justify-between group hover:shadow-sm transition">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Troli Belanja</p>
                        <h3 class="text-xl font-extrabold text-stone-800"><?= $cart_count ?> <span class="text-xs font-medium text-stone-400">Item</span></h3>
                        <a href="cart.php" class="text-xs font-bold text-planthub-green hover:underline inline-flex items-center gap-1 pt-1">
                            <span>Lihat Keranjang</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-white text-planthub-green flex items-center justify-center shadow-sm group-hover:bg-planthub-green group-hover:text-white transition border border-stone-100">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Stat Card 2 -->
                <div class="bg-stone-50/50 p-4 rounded-2xl border border-stone-100 flex items-center justify-between group hover:shadow-sm transition">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Pesanan Diproses</p>
                        <h3 class="text-xl font-extrabold text-stone-800"><?= $pending_orders ?> <span class="text-xs font-medium text-stone-400">Pesanan</span></h3>
                        <a href="riwayat.php" class="text-xs font-bold text-planthub-green hover:underline inline-flex items-center gap-1 pt-1">
                            <span>Lacak Status</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-white text-amber-600 flex items-center justify-center shadow-sm group-hover:bg-amber-600 group-hover:text-white transition border border-stone-100">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Stat Card 3 -->
                <div class="bg-stone-50/50 p-4 rounded-2xl border border-stone-100 flex items-center justify-between group hover:shadow-sm transition">
                    <div class="space-y-1">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Total Transaksi</p>
                        <h3 class="text-xl font-extrabold text-stone-800"><?= $total_orders ?> <span class="text-xs font-medium text-stone-400">Selesai</span></h3>
                        <a href="riwayat.php" class="text-xs font-bold text-planthub-green hover:underline inline-flex items-center gap-1 pt-1">
                            <span>Riwayat Order</span>
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-white text-blue-600 flex items-center justify-center shadow-sm group-hover:bg-blue-600 group-hover:text-white transition border border-stone-100">
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                    </div>
                </div>

            </section>

            <!-- FEATURED PRODUCTS GRID -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-stone-800 tracking-tight flex items-center gap-2">
                        <i data-lucide="leaf" class="w-4 h-4 text-planthub-green"></i>
                        <span>Produk Populer</span>
                    </h3>
                    <a href="katalog.php" class="text-xs font-bold text-planthub-green hover:underline flex items-center gap-1">
                        <span>Lihat Semua</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- Card 1 -->
                    <div class="bg-white rounded-2xl p-3.5 border border-stone-100 shadow-sm hover:shadow-md transition flex flex-col justify-between group relative">
                        <div>
                            <div class="bg-stone-50 rounded-xl p-2 flex justify-center items-center h-40 relative overflow-hidden mb-3">
                                <img src="https://images.unsplash.com/photo-1614594975525-e45190c55d0b?q=80&w=500&auto=format&fit=crop" 
                                     alt="Monstera Deliciosa" 
                                     class="h-32 object-cover rounded-lg group-hover:scale-105 transition duration-300">
                                <span class="absolute top-2.5 left-2.5 bg-white/90 backdrop-blur-md px-2 py-0.5 rounded-md text-[9px] font-bold text-planthub-green shadow-sm flex items-center gap-1">
                                    <i data-lucide="star" class="w-3 h-3 fill-amber-400 text-amber-400"></i> 4.9
                                </span>
                            </div>
                            <div class="px-1">
                                <p class="text-[9px] font-bold text-stone-400 uppercase tracking-wider">Indoor Plant</p>
                                <h4 class="font-bold text-stone-800 text-xs">Monstera Deliciosa</h4>
                                <div class="flex items-center justify-between pt-2">
                                    <span class="text-sm font-extrabold text-planthub-green">Rp 120.000</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-stone-100">
                            <a href="katalog.php" class="w-full bg-planthub-green hover:bg-planthub-green-hover text-white font-bold py-2 rounded-xl text-[11px] transition flex items-center justify-center gap-1">
                                <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
                                <span>Lihat di Katalog</span>
                            </a>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-white rounded-2xl p-3.5 border border-stone-100 shadow-sm hover:shadow-md transition flex flex-col justify-between group relative">
                        <div>
                            <div class="bg-stone-50 rounded-xl p-2 flex justify-center items-center h-40 relative overflow-hidden mb-3">
                                <img src="https://images.unsplash.com/photo-1592150621744-aca64f48394a?q=80&w=500&auto=format&fit=crop" 
                                     alt="Calathea Orbifolia" 
                                     class="h-32 object-cover rounded-lg group-hover:scale-105 transition duration-300">
                                <span class="absolute top-2.5 left-2.5 bg-white/90 backdrop-blur-md px-2 py-0.5 rounded-md text-[9px] font-bold text-planthub-green shadow-sm flex items-center gap-1">
                                    <i data-lucide="star" class="w-3 h-3 fill-amber-400 text-amber-400"></i> 4.8
                                </span>
                            </div>
                            <div class="px-1">
                                <p class="text-[9px] font-bold text-stone-400 uppercase tracking-wider">Indoor Plant</p>
                                <h4 class="font-bold text-stone-800 text-xs">Calathea Orbifolia</h4>
                                <div class="flex items-center justify-between pt-2">
                                    <span class="text-sm font-extrabold text-planthub-green">Rp 85.000</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-stone-100">
                            <a href="katalog.php" class="w-full bg-planthub-green hover:bg-planthub-green-hover text-white font-bold py-2 rounded-xl text-[11px] transition flex items-center justify-center gap-1">
                                <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
                                <span>Lihat di Katalog</span>
                            </a>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white rounded-2xl p-3.5 border border-stone-100 shadow-sm hover:shadow-md transition flex flex-col justify-between group relative">
                        <div>
                            <div class="bg-stone-50 rounded-xl p-2 flex justify-center items-center h-40 relative overflow-hidden mb-3">
                                <img src="https://images.unsplash.com/photo-1509423350716-97f9360b4e09?q=80&w=500&auto=format&fit=crop" 
                                     alt="Aglaonema Suksom" 
                                     class="h-32 object-cover rounded-lg group-hover:scale-105 transition duration-300">
                                <span class="absolute top-2.5 left-2.5 bg-white/90 backdrop-blur-md px-2 py-0.5 rounded-md text-[9px] font-bold text-planthub-green shadow-sm flex items-center gap-1">
                                    <i data-lucide="star" class="w-3 h-3 fill-amber-400 text-amber-400"></i> 5.0
                                </span>
                            </div>
                            <div class="px-1">
                                <p class="text-[9px] font-bold text-stone-400 uppercase tracking-wider">Outdoor Plant</p>
                                <h4 class="font-bold text-stone-800 text-xs">Aglaonema Suksom</h4>
                                <div class="flex items-center justify-between pt-2">
                                    <span class="text-sm font-extrabold text-planthub-green">Rp 150.000</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-stone-100">
                            <a href="katalog.php" class="w-full bg-planthub-green hover:bg-planthub-green-hover text-white font-bold py-2 rounded-xl text-[11px] transition flex items-center justify-center gap-1">
                                <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
                                <span>Lihat di Katalog</span>
                            </a>
                        </div>
                    </div>

                    <!-- Card 4 (Consultation Card) -->
                    <a href="chat.php" class="bg-planthub-green-light p-4 rounded-2xl border border-emerald-200/60 flex flex-col justify-between group hover:bg-planthub-green hover:text-white transition duration-300">
                        <div class="w-10 h-10 rounded-xl bg-planthub-green text-white flex items-center justify-center group-hover:bg-white group-hover:text-planthub-green transition shadow-sm">
                            <i data-lucide="message-square" class="w-5 h-5"></i>
                        </div>
                        <div class="space-y-1.5 mt-4">
                            <h4 class="font-bold text-xs">Butuh Rekomendasi?</h4>
                            <p class="text-[11px] font-normal text-stone-600 group-hover:text-white/90 leading-relaxed">Konsultasi dengan tim botanis kami untuk memilih tanaman yang cocok.</p>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-planthub-green group-hover:text-white pt-2">
                                <span>Konsultasi Gratis</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </span>
                        </div>
                    </a>

                </div>
            </section>

            <!-- PROMO BANNER -->
            <section class="bg-planthub-green text-white rounded-2xl p-6 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm relative overflow-hidden">
                <div class="space-y-1.5 max-w-xl z-10">
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-emerald-200">
                        <i data-lucide="percent" class="w-3 h-3"></i>
                        Promo Mingguan
                    </span>
                    <h3 class="text-lg font-extrabold">Dapatkan Penawaran Khusus Untuk Pesananmu</h3>
                    <p class="text-white/80 text-xs leading-relaxed font-light">
                        Pengiriman aman bergaransi dengan kemasan ekstra protektif untuk setiap pembelian minggu ini.
                    </p>
                </div>
                <a href="katalog.php" class="bg-white text-planthub-green hover:bg-stone-100 font-bold px-5 py-2.5 rounded-xl text-xs shrink-0 transition shadow-sm flex items-center gap-2 z-10">
                    <i data-lucide="shopping-bag" class="w-3.5 h-3.5"></i>
                    <span>Belanja Sekarang</span>
                </a>
            </section>

        </div>

        <!-- FOOTER DASHBOARD -->
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