<?php
require_once __DIR__ . '/../Config/database.php';

// Ambil data admin / pengguna (contoh query)
// $queryUser = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin' LIMIT 1");
// $user = mysqli_fetch_assoc($queryUser);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Profil Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col justify-between">

    <!-- Header Navbar -->
    <?php include_once __DIR__ . '/header.php'; ?>

    <div class="flex flex-1">
        <!-- Sidebar Navigation -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0">
            <div class="p-4 space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Main Menu</p>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </a>
                    <a href="pos.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Penjualan (POS)
                    </a>
                    <a href="transaksi.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="receipt" class="w-4 h-4"></i> Riwayat Transaksi
                    </a>
                    <a href="restock.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="truck" class="w-4 h-4"></i> Pembelian (Restock)
                    </a>
                    <a href="stok.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="package" class="w-4 h-4"></i> Stok & Produk
                    </a>
                    <a href="pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="users" class="w-4 h-4"></i> Pelanggan
                    </a>
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Laporan
                    </a>
                </nav>
                <hr class="border-stone-100">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Pengaturan</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="settings" class="w-4 h-4"></i> Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="help-circle" class="w-4 h-4"></i> Bantuan
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Profil Saya</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Detail informasi akun administrator sistem.</p>
                </div>
                <a href="pengaturan.php" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 transition-all shadow-sm">
                    <i data-lucide="edit-3" class="w-4 h-4"></i> Ubah Pengaturan
                </a>
            </div>

            <!-- Profile Info Card (Read Only) -->
            <div class="bg-white rounded-2xl border border-stone-200/80 p-6 shadow-sm space-y-6">
                <div class="flex items-center gap-5 border-b border-stone-100 pb-6">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120" alt="Nabila" class="w-20 h-20 rounded-2xl object-cover ring-4 ring-[#2E7D32]/20">
                    <div>
                        <h2 class="text-xl font-bold text-stone-800">Nabila Keisha Putri Azzahra</h2>
                        <p class="text-xs font-semibold text-[#2E7D32] bg-emerald-50 px-2.5 py-1 rounded-lg inline-block mt-1">Administrator Utama</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <label class="text-xs font-semibold text-stone-400 uppercase tracking-wider">Nama Lengkap</label>
                        <p class="mt-1 font-semibold text-stone-800 bg-stone-50 p-3 rounded-xl border border-stone-100">Nabila Keisha Putri Azzahra</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-stone-400 uppercase tracking-wider">Email</label>
                        <p class="mt-1 font-semibold text-stone-800 bg-stone-50 p-3 rounded-xl border border-stone-100">hello8delapan@gmail.com</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-stone-400 uppercase tracking-wider">Role / Hak Akses</label>
                        <p class="mt-1 font-semibold text-stone-800 bg-stone-50 p-3 rounded-xl border border-stone-100">Super Admin (Full Access)</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-stone-400 uppercase tracking-wider">Status Akun</label>
                        <p class="mt-1 font-semibold text-emerald-600 bg-emerald-50 p-3 rounded-xl border border-emerald-100 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Aktif
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>