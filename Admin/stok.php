<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Kelola Stok & Produk</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F9F8F6;
            color: #2D3748;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

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

    <div class="flex flex-1 relative">
        <!-- SIDEBAR -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 absolute lg:relative z-20 h-full">
            <div class="p-4 space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Main Menu</p>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </a>
                    <a href="pos.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Kasir (POS)
                    </a>
                    <a href="restock.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="truck" class="w-4 h-4"></i> Pembelian (Restock)
                    </a>
                    <!-- Active Page -->
                    <a href="stok.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-[#2E7D32] bg-[#2E7D32]/10 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <i data-lucide="package" class="w-4 h-4"></i> Stok & Produk
                        </div>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span>
                    </a>
                    <a href="pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="users" class="w-4 h-4"></i> Pelanggan
                    </a>
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Laporan
                    </a>
                </nav>

                <hr class="border-stone-100">

                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Pengaturan</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="settings" class="w-4 h-4"></i> Pengaturan Toko
                    </a>
                </nav>
            </div>

            <div class="p-4 m-4 rounded-xl bg-stone-50 border border-stone-200/60">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 text-[#2E7D32] rounded-lg">
                        <i data-lucide="store" class="w-4 h-4"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-semibold text-stone-800 truncate">Cabang Batu Central</p>
                        <p class="text-[11px] text-stone-500 truncate">Sistem Online Active</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <!-- PAGE HEADER & ACTION BAR -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Kelola Stok & Produk</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Atur inventaris tanaman, pot, media tanam, serta sesuaikan harga jual POS</p>
                </div>
                
                <div class="flex items-center gap-2.5 self-start sm:self-auto">
                    <button onclick="exportDataCSV()" title="Eksport Excel/CSV" class="p-2.5 bg-white border border-stone-300 text-stone-700 hover:bg-stone-50 rounded-xl shadow-sm transition-all flex items-center justify-center">
                        <i data-lucide="download" class="w-4 h-4"></i>
                    </button>
                    
                    <button onclick="openImportModal()" class="px-4 py-2.5 bg-white border border-stone-300 text-stone-700 hover:bg-stone-50 text-sm font-semibold rounded-xl shadow-sm transition-all flex items-center gap-2">
                        <i data-lucide="file-up" class="w-4 h-4 text-stone-500"></i>
                        <span>Import Data</span>
                    </button>
                    
                    <button onclick="openAddProductModal()" class="px-4 py-2.5 bg-[#2E7D32] hover:bg-emerald-800 text-white text-sm font-semibold rounded-xl shadow-sm shadow-emerald-900/20 transition-all flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Tambah Produk Baru</span>
                    </button>
                </div>
            </div>

            <!-- METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div onclick="filterStatus('all')" class="cursor-pointer bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] flex items-center justify-between hover:scale-[1.02] transition-transform">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Total Item POS</p>
                        <p class="text-2xl font-bold text-stone-800">6 <span class="text-sm font-medium text-stone-500">Produk</span></p>
                        <p class="text-xs text-emerald-600 flex items-center gap-1 font-medium"><i data-lucide="check" class="w-3.5 h-3.5"></i> Terhubung ke Kasir</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                        <i data-lucide="leaf" class="w-6 h-6"></i>
                    </div>
                </div>

                <div onclick="filterStatus('safe')" class="cursor-pointer bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-emerald-500 flex items-center justify-between hover:scale-[1.02] transition-transform">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Stok Aman</p>
                        <p class="text-2xl font-bold text-stone-800">4 <span class="text-sm font-medium text-stone-500">Produk</span></p>
                        <p class="text-xs text-stone-400 font-medium">Melampaui batas minimum</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                    </div>
                </div>

                <div onclick="filterStatus('critical')" class="cursor-pointer bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706] flex items-center justify-between hover:scale-[1.02] transition-transform">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Stok Kritis</p>
                        <p class="text-2xl font-bold text-stone-800">1 <span class="text-sm font-medium text-stone-500">Item Kritis</span></p>
                        <span class="text-xs text-[#D97706] font-semibold flex items-center gap-1 hover:underline">
                            Filter Kritis <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </span>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-[#D97706] flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                </div>

                <div onclick="filterStatus('empty')" class="cursor-pointer bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-rose-700 flex items-center justify-between hover:scale-[1.02] transition-transform">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Stok Habis</p>
                        <p class="text-2xl font-bold text-stone-800">1 <span class="text-sm font-medium text-stone-500">Produk</span></p>
                        <p class="text-xs text-rose-700 font-medium">Restock secepatnya</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center">
                        <i data-lucide="package-x" class="w-6 h-6"></i>
                    </div>
                </div>
            </div>

            <!-- CONTROL TOOLBAR (FILTER & SEARCH) -->
            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari tanaman, pot, media tanam, atau SKU..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all placeholder:text-stone-400">
                    </div>

                    <div class="flex items-center gap-2.5 self-end lg:self-auto">
                        <div class="relative">
                            <select id="statusFilter" onchange="filterTable()" class="appearance-none bg-stone-50 border border-stone-200 text-stone-700 text-sm font-medium py-2.5 pl-3.5 pr-9 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] cursor-pointer">
                                <option value="all">Semua Status</option>
                                <option value="safe">Stok Aman</option>
                                <option value="critical">Stok Kritis</option>
                                <option value="empty">Stok Habis</option>
                            </select>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Quick Filter Tabs Sesuai Kategori POS -->
                <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pt-1 border-t border-stone-100" id="categoryTabs">
                    <button onclick="setCategoryFilter('all', this)" class="category-btn px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-[#2E7D32] text-white whitespace-nowrap shadow-sm">
                        Semua Kategori
                    </button>
                    <button onclick="setCategoryFilter('Indoor', this)" class="category-btn px-3.5 py-1.5 text-xs font-medium rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200/70 whitespace-nowrap transition-colors">
                        Indoor
                    </button>
                    <button onclick="setCategoryFilter('Outdoor', this)" class="category-btn px-3.5 py-1.5 text-xs font-medium rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200/70 whitespace-nowrap transition-colors">
                        Outdoor
                    </button>
                    <button onclick="setCategoryFilter('Pot', this)" class="category-btn px-3.5 py-1.5 text-xs font-medium rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200/70 whitespace-nowrap transition-colors">
                        Pot
                    </button>
                    <button onclick="setCategoryFilter('Media Tanam', this)" class="category-btn px-3.5 py-1.5 text-xs font-medium rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200/70 whitespace-nowrap transition-colors">
                        Media Tanam
                    </button>
                </div>
            </div>

            <!-- MAIN INVENTORY TABLE CONTAINER -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="inventoryTable">
                        <thead>
                            <tr class="bg-stone-50/80 border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="p-4 w-10 text-center"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded border-stone-300 text-[#2E7D32] focus:ring-[#2E7D32] cursor-pointer"></th>
                                <th class="py-4 px-4">Produk POS</th>
                                <th class="py-4 px-4">Kategori</th>
                                <th class="py-4 px-4">Harga Jual</th>
                                <th class="py-4 px-4 min-w-[190px]">Stok Saat Ini (Klik Ubah)</th>
                                <th class="py-4 px-4">Stok Min.</th>
                                <th class="py-4 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-sm">

                            <!-- Item 1: Monstera Deliciosa -->
                            <tr class="hover:bg-stone-50/60 transition-colors product-row" data-category="Indoor" data-status="safe">
                                <td class="p-4 text-center"><input type="checkbox" onclick="updateBulkBar()" class="row-checkbox rounded border-stone-300 text-[#2E7D32]"></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400" alt="Monstera" class="w-11 h-11 rounded-xl object-cover bg-stone-100 border border-stone-200">
                                        <div>
                                            <p class="font-semibold text-stone-800 product-name">Monstera Deliciosa</p>
                                            <p class="text-xs text-stone-400 font-mono product-sku">SKU: PLN-MNS-001</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60">Indoor</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-800">Rp 125.000</td>
                                <td class="py-3.5 px-4 cursor-pointer hover:bg-emerald-50/60 p-2 rounded-xl transition-all group" onclick="openStockModal('PLN-MNS-001', 'Monstera Deliciosa', 8, 5)">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-emerald-700 group-hover:underline flex items-center gap-1">
                                                <span class="stock-val">8</span> Unit <i data-lucide="edit-2" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </span>
                                            <span class="text-[10px] text-stone-400 font-medium">Aman</span>
                                        </div>
                                        <div class="w-full bg-stone-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-emerald-600 h-full rounded-full" style="width: 80%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-stone-500 font-medium">5 Unit</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="openEditProductModal('PLN-MNS-001', 'Monstera Deliciosa', 'Indoor', '125000', 5)" title="Edit Detail Produk" class="p-1.5 text-stone-500 hover:text-[#2E7D32] hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openStockModal('PLN-MNS-001', 'Monstera Deliciosa', 8, 5)" title="Adjustment Stok" class="p-1.5 text-stone-500 hover:text-emerald-700 hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteProduct(this)" title="Hapus Produk" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Item 2: Snake Plant (Sansevieria) -->
                            <tr class="hover:bg-stone-50/60 transition-colors product-row" data-category="Indoor" data-status="safe">
                                <td class="p-4 text-center"><input type="checkbox" onclick="updateBulkBar()" class="row-checkbox rounded border-stone-300 text-[#2E7D32]"></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://images.unsplash.com/photo-1509423350716-97f9360b4e09?auto=format&fit=crop&q=80&w=400" alt="Snake Plant" class="w-11 h-11 rounded-xl object-cover bg-stone-100 border border-stone-200">
                                        <div>
                                            <p class="font-semibold text-stone-800 product-name">Snake Plant (Sansevieria)</p>
                                            <p class="text-xs text-stone-400 font-mono product-sku">SKU: PLN-SNK-002</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60">Indoor</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-800">Rp 45.000</td>
                                <td class="py-3.5 px-4 cursor-pointer hover:bg-emerald-50/60 p-2 rounded-xl transition-all group" onclick="openStockModal('PLN-SNK-002', 'Snake Plant (Sansevieria)', 15, 5)">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-emerald-700 group-hover:underline flex items-center gap-1">
                                                <span class="stock-val">15</span> Unit <i data-lucide="edit-2" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </span>
                                            <span class="text-[10px] text-stone-400 font-medium">Aman</span>
                                        </div>
                                        <div class="w-full bg-stone-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-emerald-600 h-full rounded-full" style="width: 90%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-stone-500 font-medium">5 Unit</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="openEditProductModal('PLN-SNK-002', 'Snake Plant (Sansevieria)', 'Indoor', '45000', 5)" title="Edit Detail Produk" class="p-1.5 text-stone-500 hover:text-[#2E7D32] hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openStockModal('PLN-SNK-002', 'Snake Plant (Sansevieria)', 15, 5)" title="Adjustment Stok" class="p-1.5 text-stone-500 hover:text-emerald-700 hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteProduct(this)" title="Hapus Produk" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Item 3: Fiddle Leaf Fig -->
                            <tr class="bg-amber-50/50 hover:bg-amber-50/80 transition-colors border-l-4 border-l-[#D97706] product-row" data-category="Indoor" data-status="critical">
                                <td class="p-4 text-center"><input type="checkbox" onclick="updateBulkBar()" class="row-checkbox rounded border-stone-300 text-[#2E7D32]"></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://images.unsplash.com/photo-1545241047-6083a3684587?auto=format&fit=crop&q=80&w=400" alt="Fiddle Leaf" class="w-11 h-11 rounded-xl object-cover bg-stone-100 border border-stone-200">
                                        <div>
                                            <p class="font-semibold text-stone-800 product-name">Fiddle Leaf Fig</p>
                                            <p class="text-xs text-stone-400 font-mono product-sku">SKU: PLN-FLF-003</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60">Indoor</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-800">Rp 210.000</td>
                                <td class="py-3.5 px-4 cursor-pointer hover:bg-amber-100/60 p-2 rounded-xl transition-all group" onclick="openStockModal('PLN-FLF-003', 'Fiddle Leaf Fig', 3, 5)">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-[#D97706] group-hover:underline flex items-center gap-1">
                                                <span class="stock-val">3</span> Unit <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                            </span>
                                            <span class="text-[10px] text-[#D97706] font-medium">Kritis</span>
                                        </div>
                                        <div class="w-full bg-stone-200 h-2 rounded-full overflow-hidden">
                                            <div class="bg-[#D97706] h-full rounded-full" style="width: 35%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-stone-600 font-semibold">5 Unit</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="openEditProductModal('PLN-FLF-003', 'Fiddle Leaf Fig', 'Indoor', '210000', 5)" title="Edit Detail Produk" class="p-1.5 text-stone-500 hover:text-[#2E7D32] hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openStockModal('PLN-FLF-003', 'Fiddle Leaf Fig', 3, 5)" title="Adjustment Stok" class="p-1.5 text-[#D97706] hover:bg-amber-100 rounded-lg">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteProduct(this)" title="Hapus Produk" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Item 4: Calathea Orbifolia -->
                            <tr class="hover:bg-stone-50/60 transition-colors product-row" data-category="Indoor" data-status="empty">
                                <td class="p-4 text-center"><input type="checkbox" onclick="updateBulkBar()" class="row-checkbox rounded border-stone-300 text-[#2E7D32]"></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://images.unsplash.com/photo-1599598425947-320f323c683b?auto=format&fit=crop&q=80&w=400" alt="Calathea" class="w-11 h-11 rounded-xl object-cover bg-stone-100 border border-stone-200 grayscale opacity-75">
                                        <div>
                                            <p class="font-semibold text-stone-800 product-name">Calathea Orbifolia</p>
                                            <p class="text-xs text-stone-400 font-mono product-sku">SKU: PLN-CLT-004</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/60">Indoor</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-800">Rp 85.000</td>
                                <td class="py-3.5 px-4 cursor-pointer hover:bg-rose-50/60 p-2 rounded-xl transition-all group" onclick="openStockModal('PLN-CLT-004', 'Calathea Orbifolia', 0, 5)">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-rose-700 group-hover:underline flex items-center gap-1">
                                                <span class="stock-val">0</span> Unit <i data-lucide="edit-2" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </span>
                                            <span class="text-[10px] text-rose-700 font-semibold">Habis</span>
                                        </div>
                                        <div class="w-full bg-stone-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-rose-600 h-full rounded-full" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-stone-500 font-medium">5 Unit</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="openEditProductModal('PLN-CLT-004', 'Calathea Orbifolia', 'Indoor', '85000', 5)" title="Edit Detail Produk" class="p-1.5 text-stone-500 hover:text-[#2E7D32] hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openStockModal('PLN-CLT-004', 'Calathea Orbifolia', 0, 5)" title="Adjustment Stok" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteProduct(this)" title="Hapus Produk" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Item 5: Pot Terakota Minimalis 20cm -->
                            <tr class="hover:bg-stone-50/60 transition-colors product-row" data-category="Pot" data-status="safe">
                                <td class="p-4 text-center"><input type="checkbox" onclick="updateBulkBar()" class="row-checkbox rounded border-stone-300 text-[#2E7D32]"></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://images.unsplash.com/photo-1485955900006-10f4d324d411?auto=format&fit=crop&q=80&w=400" alt="Pot Terakota" class="w-11 h-11 rounded-xl object-cover bg-stone-100 border border-stone-200">
                                        <div>
                                            <p class="font-semibold text-stone-800 product-name">Pot Terakota Minimalis 20cm</p>
                                            <p class="text-xs text-stone-400 font-mono product-sku">SKU: POT-TRK-005</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200/60">Pot</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-800">Rp 35.000</td>
                                <td class="py-3.5 px-4 cursor-pointer hover:bg-emerald-50/60 p-2 rounded-xl transition-all group" onclick="openStockModal('POT-TRK-005', 'Pot Terakota Minimalis 20cm', 24, 10)">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-emerald-700 group-hover:underline flex items-center gap-1">
                                                <span class="stock-val">24</span> Unit <i data-lucide="edit-2" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </span>
                                            <span class="text-[10px] text-stone-400 font-medium">Aman</span>
                                        </div>
                                        <div class="w-full bg-stone-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-emerald-600 h-full rounded-full" style="width: 85%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-stone-500 font-medium">10 Unit</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="openEditProductModal('POT-TRK-005', 'Pot Terakota Minimalis 20cm', 'Pot', '35000', 10)" title="Edit Detail Produk" class="p-1.5 text-stone-500 hover:text-[#2E7D32] hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openStockModal('POT-TRK-005', 'Pot Terakota Minimalis 20cm', 24, 10)" title="Adjustment Stok" class="p-1.5 text-stone-500 hover:text-emerald-700 hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteProduct(this)" title="Hapus Produk" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Item 6: Media Tanam Organik Premium 5kg -->
                            <tr class="hover:bg-stone-50/60 transition-colors product-row" data-category="Media Tanam" data-status="safe">
                                <td class="p-4 text-center"><input type="checkbox" onclick="updateBulkBar()" class="row-checkbox rounded border-stone-300 text-[#2E7D32]"></td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <img src="https://images.unsplash.com/photo-1416879595882-3373a0480b5b?auto=format&fit=crop&q=80&w=400" alt="Media Tanam" class="w-11 h-11 rounded-xl object-cover bg-stone-100 border border-stone-200">
                                        <div>
                                            <p class="font-semibold text-stone-800 product-name">Media Tanam Organik Premium 5kg</p>
                                            <p class="text-xs text-stone-400 font-mono product-sku">SKU: MDT-ORG-006</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-800 border border-blue-200/60">Media Tanam</span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-stone-800">Rp 28.000</td>
                                <td class="py-3.5 px-4 cursor-pointer hover:bg-emerald-50/60 p-2 rounded-xl transition-all group" onclick="openStockModal('MDT-ORG-006', 'Media Tanam Organik Premium 5kg', 40, 15)">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-emerald-700 group-hover:underline flex items-center gap-1">
                                                <span class="stock-val">40</span> Unit <i data-lucide="edit-2" class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </span>
                                            <span class="text-[10px] text-stone-400 font-medium">Aman</span>
                                        </div>
                                        <div class="w-full bg-stone-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-emerald-600 h-full rounded-full" style="width: 95%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-stone-500 font-medium">15 Unit</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="openEditProductModal('MDT-ORG-006', 'Media Tanam Organik Premium 5kg', 'Media Tanam', '28000', 15)" title="Edit Detail Produk" class="p-1.5 text-stone-500 hover:text-[#2E7D32] hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="openStockModal('MDT-ORG-006', 'Media Tanam Organik Premium 5kg', 40, 15)" title="Adjustment Stok" class="p-1.5 text-stone-500 hover:text-emerald-700 hover:bg-stone-100 rounded-lg">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteProduct(this)" title="Hapus Produk" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>

                <!-- BOTTOM PAGINATION BAR -->
                <div class="p-4 bg-stone-50/80 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <p class="text-xs text-stone-500 font-medium">
                        Menampilkan <span class="font-bold text-stone-700">1-6</span> dari <span class="font-bold text-stone-700">6</span> Produk POS
                    </p>

                    <div class="flex items-center gap-1.5">
                        <button onclick="showToast('Halaman Pertama')" class="px-3 py-1.5 text-xs font-semibold text-stone-500 bg-white border border-stone-200 rounded-lg hover:bg-stone-100 transition-colors flex items-center gap-1">
                            <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Sebelumnya
                        </button>
                        <div class="flex items-center gap-1">
                            <button class="w-8 h-8 text-xs font-bold bg-[#2E7D32] text-white rounded-lg shadow-sm">1</button>
                        </div>
                        <button onclick="showToast('Sudah di halaman terakhir')" class="px-3 py-1.5 text-xs font-semibold text-stone-700 bg-white border border-stone-200 rounded-lg hover:bg-stone-100 transition-colors flex items-center gap-1">
                            Selanjutnya <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- BULK ACTION FLOATING BAR -->
    <div id="bulkBar" class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-stone-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 hidden z-40 transition-all">
        <span class="text-xs font-semibold" id="bulkCount">0 Item Dipilih</span>
        <div class="h-4 w-px bg-stone-700"></div>
        <button onclick="bulkDelete()" class="text-xs font-medium text-rose-400 hover:text-rose-300 flex items-center gap-1.5">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Terpilih
        </button>
    </div>

    <!-- MODAL POPUP UPDATE STOK -->
    <div id="stockModal" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-100 space-y-4">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <h3 class="text-lg font-bold text-stone-800 flex items-center gap-2">
                    <i data-lucide="layers" class="w-5 h-5 text-[#2E7D32]"></i> Update Stok Produk
                </h3>
                <button onclick="closeStockModal()" class="text-stone-400 hover:text-stone-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="bg-stone-50 p-3 rounded-xl border border-stone-100">
                    <p class="text-xs text-stone-400">Nama Produk</p>
                    <p id="modalProductName" class="text-base font-bold text-stone-800">-</p>
                    <div class="flex items-center justify-between mt-1">
                        <p id="modalProductSKU" class="text-xs font-mono text-stone-500">-</p>
                        <p id="modalMinStock" class="text-xs text-amber-700 font-medium">Batas Min: -</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-stone-600">Jumlah Stok Baru</label>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="adjustStock(-10)" class="px-3 py-2 bg-stone-100 hover:bg-stone-200 rounded-xl font-bold text-xs text-stone-700">-10</button>
                        <button type="button" onclick="adjustStock(-1)" class="w-10 h-10 bg-stone-100 hover:bg-stone-200 rounded-xl font-bold text-stone-700 flex items-center justify-center">-</button>
                        <input type="number" id="newStockInput" min="0" class="w-full text-center py-2 text-xl font-bold bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                        <button type="button" onclick="adjustStock(1)" class="w-10 h-10 bg-stone-100 hover:bg-stone-200 rounded-xl font-bold text-stone-700 flex items-center justify-center">+</button>
                        <button type="button" onclick="adjustStock(10)" class="px-3 py-2 bg-stone-100 hover:bg-stone-200 rounded-xl font-bold text-xs text-stone-700">+10</button>
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="stockReason" class="text-xs font-semibold text-stone-600">Alasan Penyesuaian</label>
                    <select id="stockReason" class="w-full text-sm bg-stone-50 border border-stone-200 rounded-xl p-2.5 text-stone-700 focus:outline-none focus:border-[#2E7D32]">
                        <option value="Restock Masuk">Restock Barang Masuk</option>
                        <option value="Hasil Opname">Hasil Inventaris Opname</option>
                        <option value="Tanaman Layu/Mati">Tanaman Rusak / Layu / Mati</option>
                        <option value="Retur Pelanggan">Retur Penjualan</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-stone-100">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                <button type="button" onclick="saveStockUpdate()" class="px-4 py-2 text-sm font-semibold bg-[#2E7D32] hover:bg-emerald-800 text-white rounded-xl shadow-sm">Simpan Stok</button>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH / EDIT PRODUK -->
    <div id="productFormModal" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-stone-100 space-y-4">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <h3 class="text-lg font-bold text-stone-800" id="productModalTitle">Tambah Produk Baru</h3>
                <button onclick="closeProductModal()" class="text-stone-400 hover:text-stone-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="productForm" onsubmit="handleProductSubmit(event)" class="space-y-3 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-semibold text-stone-600 block mb-1">Kode SKU</label>
                        <input type="text" id="prodSku" required placeholder="PLN-XXX-000" class="w-full p-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:border-[#2E7D32] focus:outline-none">
                    </div>
                    <div>
                        <label class="font-semibold text-stone-600 block mb-1">Kategori</label>
                        <select id="prodCategory" class="w-full p-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:border-[#2E7D32] focus:outline-none">
                            <option value="Indoor">Indoor</option>
                            <option value="Outdoor">Outdoor</option>
                            <option value="Pot">Pot</option>
                            <option value="Media Tanam">Media Tanam</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="font-semibold text-stone-600 block mb-1">Nama Produk</label>
                    <input type="text" id="prodName" required placeholder="Contoh: Aglaonema Red Anjamani" class="w-full p-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:border-[#2E7D32] focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-semibold text-stone-600 block mb-1">Harga Jual (Rp)</label>
                        <input type="number" id="prodPrice" required placeholder="125000" class="w-full p-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:border-[#2E7D32] focus:outline-none">
                    </div>
                    <div>
                        <label class="font-semibold text-stone-600 block mb-1">Batas Minimum Stok</label>
                        <input type="number" id="prodMin" required placeholder="5" class="w-full p-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:border-[#2E7D32] focus:outline-none">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-stone-100">
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-semibold bg-[#2E7D32] hover:bg-emerald-800 text-white rounded-xl shadow-sm">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL IMPORT FILE -->
    <div id="importModal" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-200">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-100 space-y-4">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <h3 class="text-lg font-bold text-stone-800">Import Data Produk</h3>
                <button onclick="closeImportModal()" class="text-stone-400 hover:text-stone-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="border-2 border-dashed border-stone-200 rounded-2xl p-6 text-center space-y-2 bg-stone-50">
                <i data-lucide="upload-cloud" class="w-10 h-10 text-[#2E7D32] mx-auto"></i>
                <p class="text-xs font-semibold text-stone-700">Pilih file CSV atau Excel (.xlsx)</p>
                <p class="text-[11px] text-stone-400">Maksimal ukuran file 5MB</p>
                <input type="file" id="importFile" class="hidden" onchange="processImportFile(this)">
                <button onclick="document.getElementById('importFile').click()" class="px-3.5 py-1.5 bg-white border border-stone-300 text-stone-700 text-xs font-semibold rounded-xl shadow-sm hover:bg-stone-100 transition-colors">Pilih File</button>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                <button type="button" onclick="closeImportModal()" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Tutup</button>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="toast" class="fixed bottom-5 right-5 z-50 bg-stone-900 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 hidden opacity-0 transition-opacity">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400"></i>
        <span id="toastMsg" class="text-sm font-medium">Berhasil disimpan</span>
    </div>

    <!-- JAVASCRIPT HANDLERS -->
    <script>
        lucide.createIcons();

        let activeSku = null;
        let selectedCategory = 'all';

        // Toggle Sidebar Mobile
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
        }

        // Toggle Dropdowns Header
        function toggleNotifications() {
            document.getElementById('notificationDropdown').classList.toggle('hidden');
            document.getElementById('profileDropdown').classList.add('hidden');
        }

        function toggleProfileMenu() {
            document.getElementById('profileDropdown').classList.toggle('hidden');
            document.getElementById('notificationDropdown').classList.add('hidden');
        }

        // Toast Handler
        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.remove('opacity-0'), 10);
            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.classList.add('hidden'), 200);
            }, 3000);
        }

        // Modal Stock Update
        function openStockModal(sku, name, currentStock, minStock) {
            activeSku = sku;
            document.getElementById('modalProductName').innerText = name;
            document.getElementById('modalProductSKU').innerText = 'SKU: ' + sku;
            document.getElementById('modalMinStock').innerText = 'Min. Stok: ' + minStock + ' Unit';
            document.getElementById('newStockInput').value = currentStock;

            const modal = document.getElementById('stockModal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
        }

        function closeStockModal() {
            const modal = document.getElementById('stockModal');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }

        function adjustStock(amount) {
            const input = document.getElementById('newStockInput');
            let val = parseInt(input.value) || 0;
            val = Math.max(0, val + amount);
            input.value = val;
        }

        function saveStockUpdate() {
            const newStock = document.getElementById('newStockInput').value;
            const reason = document.getElementById('stockReason').value;
            
            const rows = document.querySelectorAll('.product-row');
            rows.forEach(row => {
                if (row.querySelector('.product-sku').innerText.includes(activeSku)) {
                    const stockValElem = row.querySelector('.stock-val');
                    if (stockValElem) stockValElem.innerText = newStock;
                }
            });

            showToast(`Stok ${activeSku} berhasil diperbarui ke ${newStock} unit (${reason})`);
            closeStockModal();
        }

        // Modal Tambah / Edit Produk
        function openAddProductModal() {
            document.getElementById('productModalTitle').innerText = 'Tambah Produk Baru';
            document.getElementById('productForm').reset();
            const modal = document.getElementById('productFormModal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
        }

        function openEditProductModal(sku, name, category, price, minStock) {
            document.getElementById('productModalTitle').innerText = 'Edit Detail Produk';
            document.getElementById('prodSku').value = sku;
            document.getElementById('prodName').value = name;
            document.getElementById('prodCategory').value = category;
            document.getElementById('prodPrice').value = price;
            document.getElementById('prodMin').value = minStock;

            const modal = document.getElementById('productFormModal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
        }

        function closeProductModal() {
            const modal = document.getElementById('productFormModal');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }

        function handleProductSubmit(e) {
            e.preventDefault();
            const name = document.getElementById('prodName').value;
            showToast(`Produk "${name}" berhasil disimpan`);
            closeProductModal();
        }

        // Modal Import
        function openImportModal() {
            const modal = document.getElementById('importModal');
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
        }

        function closeImportModal() {
            const modal = document.getElementById('importModal');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }

        function processImportFile(input) {
            if (input.files.length > 0) {
                showToast(`File "${input.files[0].name}" berhasil diunggah`);
                closeImportModal();
            }
        }

        // Download CSV
        function exportDataCSV() {
            const csvContent = "data:text/csv;charset=utf-8,SKU,Nama Produk,Kategori,Harga,Stok\nPLN-MNS-001,Monstera Deliciosa,Indoor,125000,8\nPLN-SNK-002,Snake Plant (Sansevieria),Indoor,45000,15\nPLN-FLF-003,Fiddle Leaf Fig,Indoor,210000,3\nPLN-CLT-004,Calathea Orbifolia,Indoor,85000,0\nPOT-TRK-005,Pot Terakota Minimalis 20cm,Pot,35000,24\nMDT-ORG-006,Media Tanam Organik Premium 5kg,Media Tanam,28000,40";
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "stok_pos_plantshop.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast("Mengunduh data stok CSV...");
        }

        // Filter Functionality
        function filterTable() {
            const searchVal = document.getElementById('searchInput').value.toLowerCase();
            const statusVal = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.product-row');

            rows.forEach(row => {
                const name = row.querySelector('.product-name').innerText.toLowerCase();
                const sku = row.querySelector('.product-sku').innerText.toLowerCase();
                const category = row.dataset.category;
                const status = row.dataset.status;

                const matchSearch = name.includes(searchVal) || sku.includes(searchVal);
                const matchCategory = (selectedCategory === 'all' || category === selectedCategory);
                const matchStatus = (statusVal === 'all' || status === statusVal);

                if (matchSearch && matchCategory && matchStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function setCategoryFilter(category, btn) {
            selectedCategory = category;
            document.querySelectorAll('.category-btn').forEach(b => {
                b.className = 'category-btn px-3.5 py-1.5 text-xs font-medium rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200/70 whitespace-nowrap transition-colors';
            });
            btn.className = 'category-btn px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-[#2E7D32] text-white whitespace-nowrap shadow-sm';
            filterTable();
        }

        function filterStatus(status) {
            document.getElementById('statusFilter').value = status;
            filterTable();
        }

        function syncGlobalSearch(val) {
            document.getElementById('searchInput').value = val;
            filterTable();
        }

        // Delete Row
        function deleteProduct(btn) {
            if (confirm("Apakah Anda yakin ingin menghapus produk ini?")) {
                const row = btn.closest('tr');
                row.remove();
                showToast("Produk berhasil dihapus");
            }
        }

        // Bulk Actions
        function toggleSelectAll() {
            const master = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateBulkBar();
        }

        function updateBulkBar() {
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            const bulkBar = document.getElementById('bulkBar');
            if (checkedCount > 0) {
                document.getElementById('bulkCount').innerText = `${checkedCount} Item Dipilih`;
                bulkBar.classList.remove('hidden');
            } else {
                bulkBar.classList.add('hidden');
            }
        }

        function bulkDelete() {
            if (confirm("Hapus seluruh produk yang dipilih?")) {
                document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
                    cb.closest('tr').remove();
                });
                updateBulkBar();
                showToast("Item terpilih berhasil dihapus");
            }
        }
    </script>
</body>
</html>