<?php
// Hubungkan ke file database
include '../Config/database.php';

// Navigasi Utama (disesuaikan dengan dashboard.php)
$nav_items = [
    ['name' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => 'dashboard.php', 'active' => false],
    ['name' => 'Sales (POS)', 'icon' => 'shopping-bag', 'url' => 'pos.php', 'active' => false],
    ['name' => 'Purchasing', 'icon' => 'truck', 'url' => 'restock.php', 'active' => false],
    ['name' => 'Products', 'icon' => 'sprout', 'url' => 'stok.php', 'active' => false],
    ['name' => 'Customers', 'icon' => 'users', 'url' => 'data_master.php', 'active' => true],
    ['name' => 'Reports', 'icon' => 'bar-chart-3', 'url' => 'laporan.php', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Data Pelanggan & Master</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

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
                            amber: '#F59E0B',
                            'amber-light': '#FEF3C7',
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
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- Top Navbar -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 lg:px-8 py-3 flex items-center justify-between gap-4">
            <!-- Brand & Mobile Toggle -->
            <div class="flex items-center gap-3">
                <button id="mobile-menu-btn" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <div class="flex items-center gap-2.5">
                    <div class="bg-brand-primary p-2 rounded-xl text-white shadow-sm shadow-emerald-900/20">
                        <i data-lucide="leaf" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-brand-primary">Shop</span></span>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="hidden md:flex flex-1 max-w-md mx-4">
                <div class="relative w-full">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    <input type="text" placeholder="Cari pelanggan..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition-all">
                </div>
            </div>

            <!-- Profile & Notifications -->
            <div class="flex items-center gap-3">
                <button class="relative p-2 rounded-xl text-stone-600 hover:bg-stone-100 transition-colors">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
                </button>
                <div class="h-6 w-[1px] bg-stone-200 hidden sm:block"></div>
                <div class="flex items-center gap-3 pl-1">
                    <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=120" alt="Nabila" class="w-9 h-9 rounded-xl object-cover ring-2 ring-brand-primary/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight">Nabila</p>
                        <p class="text-xs text-stone-500 font-medium">Store Admin</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- Sidebar Navigation -->
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-20 w-64 bg-white border-r border-stone-200/80 -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out flex flex-col justify-between pt-16 lg:pt-0">
            <div class="p-4 space-y-1.5">
                <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-stone-400">Navigasi Utama</div>
                <?php foreach ($nav_items as $item): ?>
                    <a href="<?= $item['url'] ?>" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= $item['active'] ? 'bg-brand-primary text-white shadow-sm shadow-emerald-900/20' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' ?>">
                        <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4"></i>
                        <span><?= $item['name'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="p-4 m-4 rounded-2xl bg-brand-primary-light/60 border border-emerald-100">
                <div class="flex items-center gap-2 text-brand-primary font-semibold text-xs mb-1">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Toko Buka</span>
                </div>
                <p class="text-xs text-stone-600">Sistem POS online & tersinkronisasi otomatis.</p>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 p-4 lg:p-8 space-y-6 max-w-7xl mx-auto w-full">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Data Master Pelanggan</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Kelola data pelanggan terdaftar dan riwayat keanggotaan toko.</p>
                </div>
                <button class="inline-flex items-center gap-2 bg-brand-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-brand-primary-hover shadow-sm shadow-emerald-900/20 transition-all">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Tambah Pelanggan</span>
                </button>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl border border-stone-200/70 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-stone-100 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-stone-800">Daftar Pelanggan</h2>
                    <span class="text-xs font-semibold text-stone-500 bg-stone-100 px-3 py-1 rounded-full">Total: 5 Pelanggan</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-100 bg-stone-50/50 text-[11px] font-semibold text-stone-400 uppercase tracking-wider">
                                <th class="py-3 px-6">Pelanggan</th>
                                <th class="py-3 px-4">No. Telepon</th>
                                <th class="py-3 px-4">Alamat</th>
                                <th class="py-3 px-4">Total Belanja</th>
                                <th class="py-3 px-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-sm font-medium text-stone-700">
                            <tr class="hover:bg-stone-50/80 transition-colors">
                                <td class="py-4 px-6 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-brand-primary-light text-brand-primary font-bold flex items-center justify-center text-xs">
                                        BS
                                    </div>
                                    <div>
                                        <p class="font-semibold text-stone-800">Budi Santoso</p>
                                        <p class="text-xs text-stone-400">budi@gmail.com</p>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-stone-600">0812-3456-7890</td>
                                <td class="py-4 px-4 text-stone-600">Jl. Soekarno Hatta No. 12, Malang</td>
                                <td class="py-4 px-4 font-semibold text-brand-primary">Rp 2.450.000</td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <button class="p-2 text-stone-400 hover:text-brand-primary transition-colors"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                                    <button class="p-2 text-stone-400 hover:text-rose-600 transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                            <tr class="hover:bg-stone-50/80 transition-colors">
                                <td class="py-4 px-6 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-brand-primary-light text-brand-primary font-bold flex items-center justify-center text-xs">
                                        SR
                                    </div>
                                    <div>
                                        <p class="font-semibold text-stone-800">Siti Rahma</p>
                                        <p class="text-xs text-stone-400">siti.rahma@gmail.com</p>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-stone-600">0857-1122-3344</td>
                                <td class="py-4 px-4 text-stone-600">Jl. Panglima Sudirman No. 45, Batu</td>
                                <td class="py-4 px-4 font-semibold text-brand-primary">Rp 1.250.000</td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <button class="p-2 text-stone-400 hover:text-brand-primary transition-colors"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                                    <button class="p-2 text-stone-400 hover:text-rose-600 transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        mobileBtn?.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });
    </script>
</body>
</html>