<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Admin Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F9F8F6;
            color: #2D3748;
        }
        /* Custom Scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <?php
    // --- Mock Data Layer ---
    $metrics = [
        [
            'title' => 'Penjualan Hari Ini',
            'value' => 'Rp 2.450.000',
            'icon' => 'wallet',
            'type' => 'success',
            'badge' => '+12.5% dari kemarin',
            'sub' => null
        ],
        [
            'title' => 'Transaksi Hari Ini',
            'value' => '18 Transaksi',
            'icon' => 'shopping-cart',
            'type' => 'success',
            'badge' => '+4 transaksi',
            'sub' => null
        ],
        [
            'title' => 'Pembelian Supplier',
            'value' => 'Rp 850.000',
            'icon' => 'package-check',
            'type' => 'success',
            'badge' => '2 PO Selesai',
            'sub' => null
        ],
        [
            'title' => 'Stok Kritis',
            'value' => '4 Item Kritis',
            'icon' => 'alert-triangle',
            'type' => 'warning',
            'badge' => null,
            'sub' => 'Lihat Detail'
        ],
    ];

    $recent_transactions = [
        ['id' => 'TRX-1092', 'customer' => 'Budi Santoso', 'time' => '10:42 WIB', 'total' => 'Rp 340.000'],
        ['id' => 'TRX-1091', 'customer' => 'Siti Rahma', 'time' => '09:15 WIB', 'total' => 'Rp 1.250.000'],
        ['id' => 'TRX-1090', 'customer' => 'Dewi Lestari', 'time' => '08:50 WIB', 'total' => 'Rp 180.000'],
        ['id' => 'TRX-1089', 'customer' => 'Andi Wijaya', 'time' => 'Kemarin', 'total' => 'Rp 420.000'],
        ['id' => 'TRX-1088', 'customer' => 'Rina Marlina', 'time' => 'Kemarin', 'total' => 'Rp 260.000'],
    ];

    $chart_labels = ['1 Jul', '5 Jul', '10 Jul', '15 Jul', '20 Jul', '25 Jul', '30 Jul'];
    $chart_data = [1200000, 1800000, 1400000, 2900000, 2100000, 3100000, 2450000];
    ?>

    <!-- TOP NAVBAR -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-btn" onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="#" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Shop</span></span>
                </a>
            </div>

            <!-- Global Search Bar -->
            <div class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" id="globalSearch" onkeyup="syncGlobalSearch(this.value)" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all placeholder:text-stone-400">
            </div>

            <!-- Right Utilities & Profile -->
            <div class="flex items-center gap-3 relative">
                <!-- Notifications Button -->
                <div class="relative">
                    <button onclick="toggleNotifications()" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full transition-colors">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-stone-100 p-4 space-y-3 z-50">
                        <div class="flex items-center justify-between border-b border-stone-100 pb-2">
                            <h4 class="font-bold text-sm text-stone-800">Notifikasi</h4>
                            <span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-semibold">2 Baru</span>
                        </div>
                        <div class="space-y-2 max-h-60 overflow-y-auto text-xs">
                            <div class="p-2 rounded-xl bg-amber-50/70 border border-amber-100">
                                <p class="font-semibold text-amber-900">Stok Kritis: Fiddle Leaf Fig</p>
                                <p class="text-amber-700 text-[11px] mt-0.5">Sisa stok 3 unit. Segera pesan ke supplier.</p>
                            </div>
                            <div class="p-2 rounded-xl bg-rose-50/70 border border-rose-100">
                                <p class="font-semibold text-rose-900">Stok Habis: Calathea Orbifolia</p>
                                <p class="text-rose-700 text-[11px] mt-0.5">Produk bernilai stok 0 dan dinonaktifkan di POS.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>

                <!-- Profile Dropdown Button -->
                <div class="relative">
                    <button onclick="toggleProfileMenu()" class="flex items-center gap-3 pl-1 focus:outline-none">
                        <div class="relative">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120" alt="Nabila" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-stone-800 leading-tight">Nabila</p>
                            <p class="text-xs text-stone-500">Administrator</p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                    </button>

                    <!-- Profile Dropdown Menu -->
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-stone-100 p-2 space-y-1 z-50">
                        <a href="profil.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl transition-colors">
                            <i data-lucide="user" class="w-4 h-4 text-stone-500"></i> Profil Saya
                        </a>
                        <a href="pengaturan.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl transition-colors">
                            <i data-lucide="settings" class="w-4 h-4 text-stone-500"></i> Pengaturan Toko
                        </a>
                        <hr class="border-stone-100 my-1">
                        <a href="logout.php" onclick="event.preventDefault(); alert('Logout Berhasil');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- SIDEBAR NAVIGASI (DISAMAKAN DENGAN HALAMAN STOK & PRODUK) -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0">
            <div class="p-4 space-y-6">
                <!-- Navigation Menu -->
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Main Menu</p>
                    
                    <!-- Active Page: Dashboard -->
                    <a href="dashboard.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-[#2E7D32] bg-[#2E7D32]/10 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                            Dashboard
                        </div>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span>
                    </a>
                    
                    <a href="pos.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Penjualan (POS)
                    </a>
                    
                    <a href="restock.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="truck" class="w-4 h-4"></i>
                        Pembelian (Restock)
                    </a>
                    
                    <a href="stok.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="package" class="w-4 h-4"></i>
                        Stok & Produk
                    </a>
                    
                    <a href="pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        Pelanggan
                    </a>
                    
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                        Laporan
                    </a>
                </nav>

                <hr class="border-stone-100">

                <!-- System Secondary Menu -->
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Pengaturan</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="help-circle" class="w-4 h-4"></i>
                        Bantuan
                    </a>
                </nav>
            </div>

            <!-- Quick Store Info Badge -->
            <div class="p-4 m-4 rounded-xl bg-stone-50 border border-stone-200/60">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 text-[#2E7D32] rounded-lg">
                        <i data-lucide="store" class="w-4 h-4"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-semibold text-stone-800 truncate">Cabang Bandung Central</p>
                        <p class="text-[11px] text-stone-500 truncate">Sistem Online Active</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            
            <!-- Page Title Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Ringkasan Dashboard</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Pantau arus kas, stok tanaman, dan penjualan toko Anda hari ini.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-sm font-medium text-stone-700 hover:bg-stone-50 shadow-sm transition-all">
                        <i data-lucide="calendar" class="w-4 h-4 text-stone-500"></i>
                        <span>Hari Ini</span>
                    </button>
                    <a href="pos.php" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm shadow-emerald-900/20 transition-all">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Transaksi Baru</span>
                    </a>
                </div>
            </div>

            <!-- 4 METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($metrics as $m): ?>
                    <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 <?= $m['type'] === 'warning' ? 'border-t-[#D97706]' : 'border-t-[#2E7D32]' ?> flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-stone-500"><?= $m['title'] ?></p>
                                <div class="w-10 h-10 rounded-xl <?= $m['type'] === 'warning' ? 'bg-amber-50 text-[#D97706]' : 'bg-emerald-50 text-[#2E7D32]' ?> flex items-center justify-center">
                                    <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2"><?= $m['value'] ?></p>
                        </div>

                        <div class="pt-2 flex items-center justify-between text-xs">
                            <?php if ($m['badge']): ?>
                                <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                                    <i data-lucide="trending-up" class="w-3 h-3"></i>
                                    <?= $m['badge'] ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($m['sub']): ?>
                                <a href="stok.php" class="inline-flex items-center gap-1 font-semibold text-[#D97706] hover:underline ml-auto">
                                    <span><?= $m['sub'] ?></span>
                                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- MAIN CONTENT BODY (TWO COLUMNS) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Side: Sales Revenue Trend Chart (2 Columns) -->
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-stone-800">Tren Pendapatan Penjualan</h2>
                            <p class="text-xs text-stone-500">Performa pendapatan 30 hari terakhir</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-600 bg-stone-100 px-3 py-1.5 rounded-lg">
                                <span class="w-2 h-2 rounded-full bg-[#2E7D32]"></span>
                                Total Omset
                            </span>
                        </div>
                    </div>
                    
                    <!-- Chart Wrapper -->
                    <div class="relative w-full h-72 sm:h-80 flex-1">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Right Side: Recent Transactions Table (1 Column) -->
                <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-bold text-stone-800">Transaksi Terakhir</h2>
                                <p class="text-xs text-stone-500">5 Penjualan terbaru hari ini</p>
                            </div>
                            <span class="p-1.5 bg-stone-100 rounded-lg text-stone-500">
                                <i data-lucide="history" class="w-4 h-4"></i>
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                        <th class="pb-3 pr-2">ID</th>
                                        <th class="pb-3 px-2">Pelanggan</th>
                                        <th class="pb-3 px-2">Waktu</th>
                                        <th class="pb-3 pl-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100 text-xs font-medium text-stone-700">
                                    <?php foreach ($recent_transactions as $trx): ?>
                                        <tr class="hover:bg-stone-50/80 transition-colors">
                                            <td class="py-3 pr-2 font-mono text-stone-400 text-[11px]"><?= $trx['id'] ?></td>
                                            <td class="py-3 px-2 font-semibold text-stone-800"><?= $trx['customer'] ?></td>
                                            <td class="py-3 px-2 text-stone-400 text-[11px]"><?= $trx['time'] ?></td>
                                            <td class="py-3 pl-2 text-right font-semibold text-[#2E7D32]"><?= $trx['total'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="pt-4 border-t border-stone-100 mt-4 flex justify-end">
                        <a href="laporan.php" class="inline-flex items-center gap-1 text-xs font-semibold text-[#2E7D32] hover:underline transition-all">
                            <span>Lihat Semua Transaksi</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Initialization & Script -->
    <script>
        // Render Lucide Icons
        lucide.createIcons();

        // Mobile Sidebar Toggle
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar?.classList.toggle('hidden');
            sidebar?.classList.toggle('fixed');
            sidebar?.classList.toggle('inset-y-0');
            sidebar?.classList.toggle('left-0');
            sidebar?.classList.toggle('z-40');
        }

        // Toggle Dropdowns
        function toggleNotifications() {
            const notif = document.getElementById('notificationDropdown');
            const profile = document.getElementById('profileDropdown');
            profile?.classList.add('hidden');
            notif?.classList.toggle('hidden');
        }

        function toggleProfileMenu() {
            const profile = document.getElementById('profileDropdown');
            const notif = document.getElementById('notificationDropdown');
            notif?.classList.add('hidden');
            profile?.classList.toggle('hidden');
        }

        // Global Search Dummy Function
        function syncGlobalSearch(val) {
            console.log("Mencari:", val);
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            const notifDropdown = document.getElementById('notificationDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (!e.target.closest('#notificationDropdown') && !e.target.closest('button[onclick="toggleNotifications()"]')) {
                notifDropdown?.classList.add('hidden');
            }
            if (!e.target.closest('#profileDropdown') && !e.target.closest('button[onclick="toggleProfileMenu()"]')) {
                profileDropdown?.classList.add('hidden');
            }
        });

        // Initialize Chart.js
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Green Gradient Area setup
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(46, 125, 50, 0.35)');
        gradient.addColorStop(1, 'rgba(46, 125, 50, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= json_encode($chart_data) ?>,
                    borderColor: '#2E7D32',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2E7D32',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#2D3748',
                        titleFont: { family: 'Plus Jakarta Sans', size: 12 },
                        bodyFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            color: '#9CA3AF'
                        }
                    },
                    y: {
                        grid: { color: '#F3F4F6' },
                        ticks: {
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            color: '#9CA3AF',
                            callback: function(value) {
                                return 'Rp ' + (value / 1000000) + ' Jt';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>