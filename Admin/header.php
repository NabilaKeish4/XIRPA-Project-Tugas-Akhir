<?php
require_once __DIR__ . '/../Config/database.php';

// Ambil Notifikasi Stok Kritis & Stok Habis langsung dari Database
$notifItems = [];
$notifCount = 0;

if (isset($conn)) {
    $queryNotif = "SELECT id_produk, nama_produk, stok FROM produk WHERE stok <= 5 ORDER BY stok ASC LIMIT 5";
    $resultNotif = mysqli_query($conn, $queryNotif);
    
    if ($resultNotif) {
        while ($row = mysqli_fetch_assoc($resultNotif)) {
            $notifItems[] = $row;
            $notifCount++;
        }
    }
}
?>

<header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
    <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button id="mobile-menu-btn" onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <a href="dashboard.php" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Shop</span></span>
            </a>
        </div>

        <!-- Global Search Bar yang Berfungsi -->
        <form action="stok.php" method="GET" class="hidden md:flex flex-1 max-w-md relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="text" name="search" placeholder="Cari tanaman, pot, media tanam (tekan Enter)..." 
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                   class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all placeholder:text-stone-400">
        </form>

        <!-- Right Utilities & Profile -->
        <div class="flex items-center gap-3 relative">
            <!-- Notifications Button -->
            <div class="relative">
                <button onclick="toggleNotifications()" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full transition-colors">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                    <?php endif; ?>
                </button>

                <!-- Notifications Dropdown -->
                <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-stone-100 p-4 space-y-3 z-50">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-2">
                        <h4 class="font-bold text-sm text-stone-800">Notifikasi Stok</h4>
                        <span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-semibold"><?= $notifCount ?> Kritis/Habis</span>
                    </div>
                    <div class="space-y-2 max-h-60 overflow-y-auto text-xs">
                        <?php if ($notifCount > 0): ?>
                            <?php foreach ($notifItems as $item): ?>
                                <a href="stok.php" class="block p-2 rounded-xl <?= $item['stok'] == 0 ? 'bg-rose-50/70 border-rose-100' : 'bg-amber-50/70 border-amber-100' ?> border transition-colors">
                                    <p class="font-semibold <?= $item['stok'] == 0 ? 'text-rose-900' : 'text-amber-900' ?>">
                                        <?= $item['stok'] == 0 ? 'Stok Habis: ' : 'Stok Kritis: ' ?><?= htmlspecialchars($item['nama_produk']) ?>
                                    </p>
                                    <p class="<?= $item['stok'] == 0 ? 'text-rose-700' : 'text-amber-700' ?> text-[11px] mt-0.5">Sisa stok <?= $item['stok'] ?> unit. Segera restock.</p>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-stone-400 text-center py-2">Semua stok tanaman aman.</p>
                        <?php endif; ?>
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
                    <a href="../Auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>