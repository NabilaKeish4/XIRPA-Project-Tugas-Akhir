<?php
session_start();
require_once '../Config/database.php';

// =========================================================
// PROTEKSI LOGIN ADMIN
// =========================================================
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_id   = (int)$_SESSION['user_id'];
$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

$current_page = basename($_SERVER['PHP_SELF']);

// =========================================================
// 1. STATISTIK HARI INI
// =========================================================
$stat = [
    'penjualan_hari'  => 0,
    'transaksi_hari'  => 0,
    'pembelian_hari'  => 0,
    'stok_kritis'     => 0,
];

// Penjualan & transaksi hari ini
$qHari = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_transaksi='penjualan' THEN total_harga ELSE 0 END),0) AS penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi='penjualan' THEN 1 ELSE 0 END),0) AS trx_jual,
        COALESCE(SUM(CASE WHEN jenis_transaksi='pembelian' THEN total_harga ELSE 0 END),0) AS pembelian
    FROM transaksi
    WHERE DATE(created_at) = CURDATE()
");
if ($qHari) {
    $r = mysqli_fetch_assoc($qHari);
    $stat['penjualan_hari'] = (float)$r['penjualan'];
    $stat['transaksi_hari'] = (int)$r['trx_jual'];
    $stat['pembelian_hari'] = (float)$r['pembelian'];
}

// Stok kritis
$qKritis = mysqli_query($conn, "SELECT COUNT(*) AS total FROM produk WHERE stok > 0 AND stok <= stok_minimal");
if ($qKritis) $stat['stok_kritis'] = (int)mysqli_fetch_assoc($qKritis)['total'];

// Stok habis (tambahan)
$qHabis = mysqli_query($conn, "SELECT COUNT(*) AS total FROM produk WHERE stok = 0");
$stokHabis = $qHabis ? (int)mysqli_fetch_assoc($qHabis)['total'] : 0;

// =========================================================
// 2. CHART: PENDAPATAN 30 HARI TERAKHIR
// =========================================================
$chart_labels = [];
$chart_data   = [];

$qChart = mysqli_query($conn, "
    SELECT DATE(created_at) AS tgl, SUM(total_harga) AS total
    FROM transaksi
    WHERE jenis_transaksi = 'penjualan'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");
$chartRaw = [];
if ($qChart) {
    while ($r = mysqli_fetch_assoc($qChart)) {
        $chartRaw[$r['tgl']] = (float)$r['total'];
    }
}

// Isi tanggal yang kosong supaya chart tetap konsisten
for ($i = 29; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($tgl));
    $chart_data[]   = $chartRaw[$tgl] ?? 0;
}

// =========================================================
// 3. 5 TRANSAKSI TERAKHIR (dari PELANGGAN)
// =========================================================
$recent_transactions = [];
$qRecent = mysqli_query($conn, "
    SELECT 
        t.id, t.kode_transaksi, t.total_harga, t.status, t.created_at,
        t.nama_penerima,
        COALESCE(u.nama_lengkap, 'Walk-in') AS pelanggan
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.jenis_transaksi = 'penjualan'
    ORDER BY t.created_at DESC
    LIMIT 5
");
if ($qRecent) {
    while ($r = mysqli_fetch_assoc($qRecent)) $recent_transactions[] = $r;
}

// =========================================================
// 4. NOTIFIKASI STOK KRITIS (untuk dropdown bell)
// =========================================================
$notifItems = [];
$qNotif = mysqli_query($conn, "SELECT id, nama_tanaman, stok, stok_minimal FROM produk WHERE stok <= stok_minimal ORDER BY stok ASC LIMIT 5");
if ($qNotif) while ($r = mysqli_fetch_assoc($qNotif)) $notifItems[] = $r;
$notifCount = count($notifItems);

// Helper format tanggal relatif
function tanggalRelatif($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    if ($diff < 172800) return 'Kemarin';
    return date('d M Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Dashboard Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
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
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <form action="stok.php" method="GET" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <div class="flex items-center gap-3 relative">
                <!-- Notifikasi -->
                <div class="relative">
                    <button onclick="toggleNotifications()" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <?php if ($notifCount > 0): ?>
                            <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-stone-100 p-4 space-y-3 z-50">
                        <div class="flex items-center justify-between border-b border-stone-100 pb-2">
                            <h4 class="font-bold text-sm text-stone-800">Notifikasi Stok</h4>
                            <span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-semibold"><?= $notifCount ?> Item</span>
                        </div>
                        <div class="space-y-2 max-h-60 overflow-y-auto text-xs">
                            <?php if ($notifCount > 0): ?>
                                <?php foreach ($notifItems as $item): 
                                    $stokHabisItem = ((int)$item['stok'] === 0);
                                ?>
                                    <a href="stok.php" class="block p-2 rounded-xl border <?= $stokHabisItem ? 'bg-rose-50/70 border-rose-100' : 'bg-amber-50/70 border-amber-100' ?>">
                                        <p class="font-semibold <?= $stokHabisItem ? 'text-rose-900' : 'text-amber-900' ?>">
                                            <?= $stokHabisItem ? 'Stok Habis: ' : 'Stok Kritis: ' ?><?= htmlspecialchars($item['nama_tanaman']) ?>
                                        </p>
                                        <p class="<?= $stokHabisItem ? 'text-rose-700' : 'text-amber-700' ?> text-[11px] mt-0.5">Sisa <?= (int)$item['stok'] ?> unit</p>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-stone-400 text-center py-2">Semua stok aman.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>

                <!-- Profile -->
                <div class="relative">
                    <button onclick="toggleProfileMenu()" class="flex items-center gap-3 pl-1 focus:outline-none">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_nama) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                            <p class="text-xs text-stone-500">Administrator</p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                    </button>
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-stone-100 p-2 space-y-1 z-50">
                        <a href="pengaturan.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                            <i data-lucide="settings" class="w-4 h-4 text-stone-500"></i> Pengaturan Toko
                        </a>
                        <hr class="border-stone-100 my-1">
                        <a href="../Auth/logout.php" onclick="return confirm('Keluar dari sistem?');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl">
                            <i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- SIDEBAR -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MAIN MENU</p>
                    <?php
                    $menu = [
    ['url' => 'dashboard.php',  'icon' => 'layout-grid',    'label' => 'Dashboard',        'active' => false],
    ['url' => 'pos.php',        'icon' => 'shopping-bag',   'label' => 'Kasir (POS)',      'active' => false],
    ['url' => 'restock.php',    'icon' => 'truck',          'label' => 'Pembelian',        'active' => false],
    ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'active' => false],
    ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],  // ← BARU
    ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],  // ← BARU
    ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => false],
    ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => false],
    ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','active' => false],
    ['url' => 'laporan.php',    'icon' => 'bar-chart-2',    'label' => 'Laporan',          'active' => false],
];
                    foreach ($menu as $m):
                        $cls = $m['active'] ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                            <div class="flex items-center gap-3">
                                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $m['active'] ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                                <span><?= $m['label'] ?></span>
                            </div>
                            <?php if ($m['active']): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>

                    <hr class="border-stone-100 my-3">

                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">PENGATURAN</p>
                    <?php
                    $menu2 = [
                        ['url' => 'pengaturan.php', 'icon' => 'settings',     'label' => 'Pengaturan Toko', 'active' => false],
                        ['url' => 'bantuan.php',    'icon' => 'help-circle',  'label' => 'Bantuan',         'active' => false],
                    ];
                    foreach ($menu2 as $m):
                        $cls = $m['active'] ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                            <div class="flex items-center gap-3">
                                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $m['active'] ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                                <span><?= $m['label'] ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-auto">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight">PlantHub Admin</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5">Sistem Online</p>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Ringkasan Dashboard</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Pantau arus kas, stok tanaman, dan penjualan toko hari ini.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="laporan.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-sm font-medium text-stone-700 hover:bg-stone-50 shadow-sm">
                        <i data-lucide="calendar" class="w-4 h-4 text-stone-500"></i>
                        <span>Laporan Lanjutan</span>
                    </a>
                    <a href="pos.php" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Transaksi Baru</span>
                    </a>
                </div>
            </div>

            <!-- 4 METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="laporan.php" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Penjualan Hari Ini</p>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($stat['penjualan_hari'], 0, ',', '.') ?></p>
                    </div>
                    <div class="pt-2 text-xs">
                        <span class="font-medium text-stone-400">Dari <?= $stat['transaksi_hari'] ?> transaksi</span>
                    </div>
                </a>

                <a href="transaksi.php" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Transaksi Hari Ini</p>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2"><?= $stat['transaksi_hari'] ?> Transaksi</p>
                    </div>
                    <div class="pt-2 text-xs">
                        <span class="font-medium text-stone-400">Penjualan pelanggan</span>
                    </div>
                </a>

                <a href="restock.php" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Pembelian Supplier</p>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                                <i data-lucide="package-check" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($stat['pembelian_hari'], 0, ',', '.') ?></p>
                    </div>
                    <div class="pt-2 text-xs">
                        <span class="font-medium text-stone-400">Restock hari ini</span>
                    </div>
                </a>

                <a href="stok.php" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706] flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Stok Kritis</p>
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-[#D97706] flex items-center justify-center">
                                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2"><?= $stat['stok_kritis'] ?> Item Kritis</p>
                    </div>
                    <div class="pt-2 text-xs flex justify-between">
                        <span class="font-medium text-stone-400"><?= $stokHabis ?> item habis</span>
                        <span class="inline-flex items-center gap-1 font-semibold text-[#D97706] hover:underline">
                            Lihat <i data-lucide="chevron-right" class="w-3 h-3"></i>
                        </span>
                    </div>
                </a>
            </div>

            <!-- CHART + RECENT TRANSACTIONS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- CHART -->
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-stone-800">Tren Pendapatan Penjualan</h2>
                            <p class="text-xs text-stone-500">Performa pendapatan 30 hari terakhir</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-600 bg-stone-100 px-3 py-1.5 rounded-lg">
                            <span class="w-2 h-2 rounded-full bg-[#2E7D32]"></span>
                            Total Omset
                        </span>
                    </div>
                    <div class="relative w-full h-72 sm:h-80 flex-1">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- RECENT TRANSACTIONS -->
                <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-bold text-stone-800">Transaksi Terakhir</h2>
                                <p class="text-xs text-stone-500">5 pesanan terbaru</p>
                            </div>
                            <span class="p-1.5 bg-stone-100 rounded-lg text-stone-500">
                                <i data-lucide="history" class="w-4 h-4"></i>
                            </span>
                        </div>

                        <?php if (empty($recent_transactions)): ?>
                            <p class="text-center text-xs text-stone-400 py-8">Belum ada transaksi.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                            <th class="pb-3 pr-2">Kode</th>
                                            <th class="pb-3 px-2">Pelanggan</th>
                                            <th class="pb-3 pl-2 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100 text-xs">
                                        <?php foreach ($recent_transactions as $trx): ?>
                                            <tr class="hover:bg-stone-50/80 cursor-pointer" onclick="window.location.href='transaksi.php';">
                                                <td class="py-3 pr-2 font-mono text-stone-500 text-[10px]"><?= htmlspecialchars($trx['kode_transaksi']) ?></td>
                                                <td class="py-3 px-2">
                                                    <p class="font-semibold text-stone-800 truncate"><?= htmlspecialchars($trx['pelanggan']) ?></p>
                                                    <p class="text-stone-400 text-[10px]"><?= tanggalRelatif($trx['created_at']) ?></p>
                                                </td>
                                                <td class="py-3 pl-2 text-right font-semibold text-[#2E7D32]">Rp <?= number_format($trx['total_harga'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pt-4 border-t border-stone-100 mt-4 flex justify-end">
                        <a href="transaksi.php" class="inline-flex items-center gap-1 text-xs font-semibold text-[#2E7D32] hover:underline">
                            Lihat Semua <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();

        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }

        function toggleNotifications() {
            document.getElementById('notificationDropdown')?.classList.toggle('hidden');
            document.getElementById('profileDropdown')?.classList.add('hidden');
        }

        function toggleProfileMenu() {
            document.getElementById('profileDropdown')?.classList.toggle('hidden');
            document.getElementById('notificationDropdown')?.classList.add('hidden');
        }

        document.addEventListener('click', (e) => {
            const n = document.getElementById('notificationDropdown');
            const p = document.getElementById('profileDropdown');
            if (!e.target.closest('#notificationDropdown') && !e.target.closest('button[onclick="toggleNotifications()"]')) n?.classList.add('hidden');
            if (!e.target.closest('#profileDropdown') && !e.target.closest('button[onclick="toggleProfileMenu()"]')) p?.classList.add('hidden');
        });

        // =========================================================
        // CHART
        // =========================================================
        const ctx = document.getElementById('salesChart').getContext('2d');
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
                    pointRadius: 3,
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
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10 }, color: '#9CA3AF', maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F3F4F6' },
                        ticks: {
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            color: '#9CA3AF',
                            callback: function(value) {
                                if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                                if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
                                return 'Rp ' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>