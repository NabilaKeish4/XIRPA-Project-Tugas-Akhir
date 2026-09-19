<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// FILTER TANGGAL
// =========================================================
$range       = $_GET['range'] ?? '';
$tgl_mulai   = $_GET['tgl_mulai']   ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

if ($range === 'today') {
    $tgl_mulai = $tgl_selesai = date('Y-m-d');
} elseif ($range === '7days') {
    $tgl_mulai   = date('Y-m-d', strtotime('-7 days'));
    $tgl_selesai = date('Y-m-d');
} elseif ($range === '30days') {
    $tgl_mulai   = date('Y-m-d', strtotime('-30 days'));
    $tgl_selesai = date('Y-m-d');
}

$tgl_mulai_esc   = mysqli_real_escape_string($conn, $tgl_mulai);
$tgl_selesai_esc = mysqli_real_escape_string($conn, $tgl_selesai);

// =========================================================
// 1. RINGKASAN
// =========================================================
$summary = [
    'total_penjualan'  => 0,
    'jumlah_penjualan' => 0,
    'total_pembelian'  => 0,
    'jumlah_pembelian' => 0,
];
$qSummary = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penjualan' THEN total_harga ELSE 0 END), 0) AS total_penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penjualan' THEN 1 ELSE 0 END), 0) AS jumlah_penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'pembelian' THEN total_harga ELSE 0 END), 0) AS total_pembelian,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'pembelian' THEN 1 ELSE 0 END), 0) AS jumlah_pembelian
    FROM transaksi
    WHERE DATE(created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
");
if ($qSummary) $summary = mysqli_fetch_assoc($qSummary);

// =========================================================
// 2. ESTIMASI LABA
// =========================================================
$estimasiLaba = 0;
$qLaba = mysqli_query($conn, "
    SELECT COALESCE(SUM(td.jumlah * (td.harga_satuan - COALESCE(p.harga_beli, 0))), 0) AS estimasi
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE t.jenis_transaksi = 'penjualan'
      AND DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
");
if ($qLaba) $estimasiLaba = (float)mysqli_fetch_assoc($qLaba)['estimasi'];

$omset       = (float)$summary['total_penjualan'];
$pengeluaran = (float)$summary['total_pembelian'];

// =========================================================
// 3. TOP 5 PRODUK TERLARIS
// =========================================================
$topProduk = [];
$qTop = mysqli_query($conn, "
    SELECT 
        p.nama_tanaman,
        SUM(td.jumlah) AS total_terjual,
        SUM(td.subtotal) AS total_pendapatan
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE t.jenis_transaksi = 'penjualan'
      AND DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
    GROUP BY td.produk_id, p.nama_tanaman
    ORDER BY total_terjual DESC
    LIMIT 5
");
if ($qTop) while ($r = mysqli_fetch_assoc($qTop)) $topProduk[] = $r;

// =========================================================
// 4. RIWAYAT TRANSAKSI PADA PERIODE
// =========================================================
$trxList = [];
$qTrx = mysqli_query($conn, "
    SELECT t.*, 
           COALESCE(u.nama_lengkap, 'Walk-in / Supplier') AS pelanggan
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE DATE(t.created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
    ORDER BY t.created_at DESC
    LIMIT 50
");
if ($qTrx) while ($r = mysqli_fetch_assoc($qTrx)) $trxList[] = $r;

// =========================================================
// 5. CHART OMZET HARIAN
// =========================================================
$chartRaw = [];
$qChart = mysqli_query($conn, "
    SELECT DATE(created_at) AS tgl, SUM(total_harga) AS total
    FROM transaksi
    WHERE jenis_transaksi = 'penjualan'
      AND DATE(created_at) BETWEEN '$tgl_mulai_esc' AND '$tgl_selesai_esc'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");
if ($qChart) while ($r = mysqli_fetch_assoc($qChart)) $chartRaw[$r['tgl']] = (float)$r['total'];

// Isi tanggal kosong
$chart_labels = [];
$chart_data   = [];
$start = strtotime($tgl_mulai);
$end   = strtotime($tgl_selesai);
for ($t = $start; $t <= $end; $t += 86400) {
    $key = date('Y-m-d', $t);
    $chart_labels[] = date('d M', $t);
    $chart_data[]   = $chartRaw[$key] ?? 0;
}

function badgeStatusLaporan($status) {
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Laporan Keuangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }

        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; }
            main { padding: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm no-print">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Laporan Keuangan</span>
            </div>

            <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_nama) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                    <p class="text-xs text-stone-500">Administrator</p>
                </div>
            </a>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4 no-print">
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
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="settings" class="w-5 h-5 text-stone-500"></i> Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="help-circle" class="w-5 h-5 text-stone-500"></i> Bantuan
                    </a>
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

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Laporan Keuangan Toko</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Analisis arus kas, omzet, dan performa penjualan.</p>
                </div>
                <div class="flex items-center gap-2 no-print">
                    <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-sm font-medium text-stone-700 hover:bg-stone-50 shadow-sm">
                        <i data-lucide="printer" class="w-4 h-4 text-stone-500"></i> Cetak
                    </button>
                    <a href="pos.php" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i> Transaksi Baru
                    </a>
                </div>
            </div>

            <!-- FILTER TANGGAL -->
            <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm space-y-4 no-print">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Filter Cepat</p>
                    <div class="flex items-center gap-2">
                        <a href="laporan.php?range=today" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='today' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Hari Ini</a>
                        <a href="laporan.php?range=7days" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='7days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">7 Hari</a>
                        <a href="laporan.php?range=30days" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='30days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">30 Hari</a>
                    </div>
                </div>

                <form method="GET" action="laporan.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-stone-100">
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 mb-1">Mulai Dari</label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="w-full px-3 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 mb-1">Sampai Dengan</label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="w-full px-3 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-[#2E7D32] text-white py-2 rounded-xl text-sm font-semibold hover:bg-emerald-800">Terapkan</button>
                        <a href="laporan.php" class="px-4 py-2 bg-stone-100 text-stone-600 rounded-xl text-sm font-medium hover:bg-stone-200">Reset</a>
                    </div>
                </form>
            </div>

            <!-- METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Omset</p>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                            <i data-lucide="wallet" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($omset, 0, ',', '.') ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100 text-xs">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i> <?= (int)$summary['jumlah_penjualan'] ?> transaksi
                    </span>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Pengeluaran Restock</p>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                            <i data-lucide="truck" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($pengeluaran, 0, ',', '.') ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100 text-xs">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i> <?= (int)$summary['jumlah_pembelian'] ?> restock
                    </span>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Estimasi Laba</p>
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($estimasiLaba, 0, ',', '.') ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100 text-xs">
                        <i data-lucide="check-circle-2" class="w-3 h-3"></i> Margin Keuntungan
                    </span>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706]">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Periode Laporan</p>
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-[#D97706] flex items-center justify-center">
                            <i data-lucide="calendar" class="w-5 h-5"></i>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-stone-800 tracking-tight mb-2"><?= date('d/m/Y', strtotime($tgl_mulai)) ?></p>
                    <span class="inline-flex items-center gap-1 font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100 text-xs">
                        <i data-lucide="arrow-right" class="w-3 h-3"></i> <?= date('d/m/Y', strtotime($tgl_selesai)) ?>
                    </span>
                </div>
            </div>

            <!-- CHART + TOP PRODUK -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-stone-800">Grafik Omzet Penjualan</h2>
                            <p class="text-xs text-stone-500">Visualisasi omzet harian pada periode terpilih</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-600 bg-stone-100 px-3 py-1.5 rounded-lg">
                            <span class="w-2 h-2 rounded-full bg-[#2E7D32]"></span>
                            Omzet
                        </span>
                    </div>
                    <div class="relative w-full h-72 sm:h-80 flex-1">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-stone-800">5 Produk Terlaris</h2>
                            <p class="text-xs text-stone-500">Berdasarkan kuantitas</p>
                        </div>
                        <span class="p-1.5 bg-emerald-50 text-[#2E7D32] rounded-lg">
                            <i data-lucide="trophy" class="w-4 h-4"></i>
                        </span>
                    </div>

                    <?php if (empty($topProduk)): ?>
                        <p class="text-center text-xs text-stone-400 py-8">Belum ada penjualan di periode ini.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php $rank = 1; foreach ($topProduk as $tp): ?>
                                <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-stone-50 transition">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0
                                        <?= $rank === 1 ? 'bg-amber-100 text-amber-700' : ($rank === 2 ? 'bg-stone-200 text-stone-700' : ($rank === 3 ? 'bg-orange-100 text-orange-700' : 'bg-stone-100 text-stone-500')) ?>">
                                        <?= $rank++ ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-stone-800 truncate"><?= htmlspecialchars($tp['nama_tanaman'] ?? 'Produk') ?></p>
                                        <p class="text-[11px] text-stone-500">Rp <?= number_format($tp['total_pendapatan'], 0, ',', '.') ?></p>
                                    </div>
                                    <span class="text-xs font-bold text-[#2E7D32] bg-emerald-50 px-2 py-1 rounded-lg shrink-0">
                                        <?= (int)$tp['total_terjual'] ?>x
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- TABEL TRANSAKSI -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-stone-100">
                    <h2 class="text-base font-bold text-stone-800">Rincian Transaksi</h2>
                    <p class="text-xs text-stone-500 mt-0.5">Transaksi pada periode <?= date('d M Y', strtotime($tgl_mulai)) ?> — <?= date('d M Y', strtotime($tgl_selesai)) ?></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">Kode</th>
                                <th class="py-3.5 px-4">Tanggal</th>
                                <th class="py-3.5 px-4">Pelanggan</th>
                                <th class="py-3.5 px-4">Jenis</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-6 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($trxList)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-stone-400">Tidak ada transaksi pada periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trxList as $t):
                                    $isJual = ($t['jenis_transaksi'] === 'penjualan');
                                ?>
                                    <tr class="hover:bg-stone-50/60 transition">
                                        <td class="py-3.5 px-6 font-mono text-[11px] font-semibold text-stone-700"><?= htmlspecialchars($t['kode_transaksi']) ?></td>
                                        <td class="py-3.5 px-4 text-stone-500"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                                        <td class="py-3.5 px-4 font-semibold text-stone-800"><?= htmlspecialchars($t['pelanggan']) ?></td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase <?= $isJual ? 'bg-emerald-50 text-[#2E7D32] border border-emerald-100' : 'bg-amber-50 text-[#D97706] border border-amber-100' ?>">
                                                <?= htmlspecialchars($t['jenis_transaksi']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border <?= badgeStatusLaporan($t['status']) ?>">
                                                <?= htmlspecialchars($t['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-6 text-right font-bold text-stone-800">Rp <?= number_format($t['total_harga'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

        const ctx = document.getElementById('salesChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(46, 125, 50, 0.35)');
        gradient.addColorStop(1, 'rgba(46, 125, 50, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Omzet (Rp)',
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
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10 }, color: '#9CA3AF', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }
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