<?php
session_start();
require_once '../Config/database.php';

// --- LOGIK FILTER TANGGAL ---
$range = isset($_GET['range']) ? $_GET['range'] : '';
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// Quick Filter Range
if ($range === 'today') {
    $tgl_mulai = date('Y-m-d');
    $tgl_selesai = date('Y-m-d');
} elseif ($range === '7days') {
    $tgl_mulai = date('Y-m-d', strtotime('-7 days'));
    $tgl_selesai = date('Y-m-d');
} elseif ($range === '30days') {
    $tgl_mulai = date('Y-m-d', strtotime('-30 days'));
    $tgl_selesai = date('Y-m-d');
}

// --- QUERY METRIK RINGKASAN ---
$query_summary = "
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penjualan' THEN total_harga ELSE 0 END), 0) AS total_penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'penjualan' THEN 1 ELSE 0 END), 0) AS jumlah_penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'pembelian' THEN total_harga ELSE 0 END), 0) AS total_pembelian,
        COALESCE(SUM(CASE WHEN jenis_transaksi = 'pembelian' THEN 1 ELSE 0 END), 0) AS jumlah_pembelian
    FROM transaksi 
    WHERE DATE(created_at) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
";
$res_summary = mysqli_query($conn, $query_summary);
$summary     = mysqli_fetch_assoc($res_summary);

// Estimation Margin Keuntungan
$query_laba = "
    SELECT 
        COALESCE(SUM(td.jumlah * (td.harga_satuan - p.harga_beli)), 0) AS estimasi_keuntungan
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN produk p ON td.produk_id = p.id
    WHERE t.jenis_transaksi = 'penjualan'
      AND DATE(t.created_at) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
";
$res_laba = mysqli_query($conn, $query_laba);
$laba     = mysqli_fetch_assoc($res_laba);

$omset       = $summary['total_penjualan'];
$pengeluaran = $summary['total_pembelian'];
$laba_bersih = $laba['estimasi_keuntungan'];

$metrics = [
    [
        'title' => 'Total Omset Penjualan',
        'value' => 'Rp ' . number_format($omset, 0, ',', '.'),
        'icon' => 'wallet',
        'type' => 'success',
        'badge' => $summary['jumlah_penjualan'] . ' Transaksi Selesai',
        'sub' => null
    ],
    [
        'title' => 'Pengeluaran Restock',
        'value' => 'Rp ' . number_format($pengeluaran, 0, ',', '.'),
        'icon' => 'truck',
        'type' => 'success',
        'badge' => $summary['jumlah_pembelian'] . ' Restock PO',
        'sub' => null
    ],
    [
        'title' => 'Estimasi Laba Bersih',
        'value' => 'Rp ' . number_format($laba_bersih, 0, ',', '.'),
        'icon' => 'trending-up',
        'type' => 'success',
        'badge' => 'Margin Keuntungan Toko',
        'sub' => null
    ],
    [
        'title' => 'Periode Laporan',
        'value' => date('d/m/Y', strtotime($tgl_mulai)),
        'icon' => 'calendar',
        'type' => 'warning',
        'badge' => 's/d ' . date('d/m/Y', strtotime($tgl_selesai)),
        'sub' => null
    ],
];

// --- QUERY TOP 5 TANAMAN TERLARIS ---
$query_terlaris = "
    SELECT 
        p.nama_tanaman,
        SUM(td.jumlah) AS total_terjual,
        SUM(td.subtotal) AS total_pendapatan
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN produk p ON td.produk_id = p.id
    WHERE t.jenis_transaksi = 'penjualan'
      AND DATE(t.created_at) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    GROUP BY p.id, p.nama_tanaman
    ORDER BY total_terjual DESC
    LIMIT 5
";
$res_terlaris = mysqli_query($conn, $query_terlaris);

// --- QUERY RIWAYAT TRANSAKSI DILAPORKAN ---
$query_trx = "
    SELECT t.id, t.created_at, t.total_harga, t.jenis_transaksi, COALESCE(pd.nama_lengkap, 'Umum/Kasir') AS pelanggan
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN pelanggan_detail pd ON pd.user_id = u.id
    WHERE DATE(t.created_at) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    ORDER BY t.created_at DESC
    LIMIT 10
";
$res_trx = mysqli_query($conn, $query_trx);

// --- QUERY CHART OMSET ---
$query_chart = "
    SELECT DATE(created_at) as tgl, SUM(total_harga) as total
    FROM transaksi
    WHERE jenis_transaksi = 'penjualan'
      AND DATE(created_at) BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
";
$res_chart = mysqli_query($conn, $query_chart);

$chart_labels = [];
$chart_data   = [];
while ($r = mysqli_fetch_assoc($res_chart)) {
    $chart_labels[] = date('d M', strtotime($r['tgl']));
    $chart_data[]   = (float)$r['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Laporan Keuangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; }
            main { max-width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- TOP NAVBAR (Persis Dashboard) -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm no-print">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-btn" type="button" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Shop</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" placeholder="Cari laporan atau transaksi..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all placeholder:text-stone-400">
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full transition-colors">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                </button>
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
                <div class="flex items-center gap-3 pl-1">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120" alt="Nabila" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight">Nabila</p>
                        <p class="text-xs text-stone-500">Administrator</p>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- SIDEBAR NAVIGASI (Persis Dashboard) -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 no-print">
            <div class="p-4 space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Main Menu</p>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 hover:text-stone-900 transition-colors">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                        Dashboard
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
                    <!-- Active Page: Laporan -->
                    <a href="laporan.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-[#2E7D32] bg-[#2E7D32]/10 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                            Laporan
                        </div>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span>
                    </a>
                </nav>

                <hr class="border-stone-100">

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
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Laporan Keuangan Toko</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Analisis arus kas omzet, restock, dan performa penjualan.</p>
                </div>
                <div class="flex items-center gap-2 no-print">
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-sm font-medium text-stone-700 hover:bg-stone-50 shadow-sm transition-all cursor-pointer">
                        <i data-lucide="printer" class="w-4 h-4 text-stone-500"></i>
                        <span>Cetak Laporan</span>
                    </button>
                    <a href="pos.php" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm shadow-emerald-900/20 transition-all">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Transaksi Baru</span>
                    </a>
                </div>
            </div>

            <!-- FITUR TAMBAHAN: Quick Date Range & Filter -->
            <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-sm space-y-4 no-print">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Filter Cepat Tanggal</p>
                    <div class="flex items-center gap-2">
                        <a href="laporan.php?range=today" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='today'?'bg-[#2E7D32] text-white border-[#2E7D32]':'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Hari Ini</a>
                        <a href="laporan.php?range=7days" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='7days'?'bg-[#2E7D32] text-white border-[#2E7D32]':'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">7 Hari Terakhir</a>
                        <a href="laporan.php?range=30days" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='30days'?'bg-[#2E7D32] text-white border-[#2E7D32]':'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">30 Hari Terakhir</a>
                    </div>
                </div>

                <form method="GET" action="laporan.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-stone-100">
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 mb-1">Mulai Dari</label>
                        <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="w-full px-3 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-stone-500 mb-1">Sampai Dengan</label>
                        <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="w-full px-3 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-[#2E7D32] text-white py-2 rounded-xl text-sm font-semibold hover:bg-emerald-800 transition-all">Terapkan Filter</button>
                        <a href="laporan.php" class="px-4 py-2 bg-stone-100 text-stone-600 rounded-xl text-sm font-medium hover:bg-stone-200">Reset</a>
                    </div>
                </form>
            </div>

            <!-- METRIC CARDS (4 Kolom Sesuai Dashboard) -->
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
                            <span class="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                                <i data-lucide="check-circle-2" class="w-3 h-3"></i>
                                <?= $m['badge'] ?>
                            </span>
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
                            <h2 class="text-lg font-bold text-stone-800">Grafik Omzet Penjualan</h2>
                            <p class="text-xs text-stone-500">Visualisasi omzet harian pada periode terpilih</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-stone-600 bg-stone-100 px-3 py-1.5 rounded-lg">
                            <span class="w-2 h-2 rounded-full bg-[#2E7D32]"></span>
                            Omzet Bersih
                        </span>
                    </div>
                    
                    <div class="relative w-full h-72 sm:h-80 flex-1">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Right Side: Top 5 Tanaman Terlaris (1 Column) -->
                <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h2 class="text-lg font-bold text-stone-800">5 Produk Terlaris</h2>
                                <p class="text-xs text-stone-500">Berdasarkan kuantitas penjualan</p>
                            </div>
                            <span class="p-1.5 bg-emerald-50 text-[#2E7D32] rounded-lg">
                                <i data-lucide="trophy" class="w-4 h-4"></i>
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-stone-200 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                        <th class="pb-3 pr-2">Tanaman</th>
                                        <th class="pb-3 px-2 text-center">Terjual</th>
                                        <th class="pb-3 pl-2 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100 text-xs font-medium text-stone-700">
                                    <?php if (mysqli_num_rows($res_terlaris) > 0): ?>
                                        <?php while ($r = mysqli_fetch_assoc($res_terlaris)): ?>
                                            <tr class="hover:bg-stone-50/80 transition-colors">
                                                <td class="py-3 pr-2 font-semibold text-stone-800"><?= htmlspecialchars($r['nama_tanaman']) ?></td>
                                                <td class="py-3 px-2 text-center font-bold text-emerald-800 bg-emerald-50 rounded-lg"><?= $r['total_terjual'] ?></td>
                                                <td class="py-3 pl-2 text-right font-semibold text-[#2E7D32]">Rp <?= number_format($r['total_pendapatan'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="py-6 text-center text-stone-400">Tidak ada data transaksi.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-stone-100 mt-4 flex justify-end no-print">
                        <a href="stok.php" class="inline-flex items-center gap-1 text-xs font-semibold text-[#2E7D32] hover:underline transition-all">
                            <span>Kelola Stok Tanaman</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>

            </div>

            <!-- TABEL DETAIL RIWAYAT TRANSAKSI DILAPORKAN -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-stone-800">Rincian Transaksi Masuk</h2>
                        <p class="text-xs text-stone-500">Daftar transaksi penjualan & pembelian pada rentang tanggal aktif</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">ID Transaksi</th>
                                <th class="py-3.5 px-6">Tanggal & Waktu</th>
                                <th class="py-3.5 px-6">Tipe</th>
                                <th class="py-3.5 px-6">Pelanggan / Keterangan</th>
                                <th class="py-3.5 px-6 text-right">Total Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs font-medium text-stone-700">
                            <?php if (mysqli_num_rows($res_trx) > 0): ?>
                                <?php while ($t = mysqli_fetch_assoc($res_trx)): ?>
                                    <tr class="hover:bg-stone-50/80 transition-colors">
                                        <td class="py-3.5 px-6 font-mono text-stone-500">#TRX-<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td class="py-3.5 px-6 text-stone-500"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?> WIB</td>
                                        <td class="py-3.5 px-6">
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase <?= $t['jenis_transaksi'] === 'penjualan' ? 'bg-emerald-100 text-[#2E7D32]' : 'bg-amber-100 text-[#D97706]' ?>">
                                                <?= $t['jenis_transaksi'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-6 font-semibold text-stone-800"><?= htmlspecialchars($t['pelanggan']) ?></td>
                                        <td class="py-3.5 px-6 text-right font-bold text-stone-800">Rp <?= number_format($t['total_harga'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-stone-400">Tidak ada riwayat transaksi pada rentang ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- SCRIPT AKSI & INTERAKSI TOMBOL -->
    <script>
        lucide.createIcons();

        // Navigasi Mobile Sidebar Toggle (Fungsi Aktif)
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');

        mobileBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('fixed');
            sidebar.classList.toggle('inset-y-0');
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('z-40');
        });

        // Initialize Chart.js Omzet Penjualan
        const ctx = document.getElementById('salesChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(46, 125, 50, 0.35)');
        gradient.addColorStop(1, 'rgba(46, 125, 50, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Omzet Penjualan (Rp)',
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
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 11 }, color: '#9CA3AF' }
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