<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// FILTER & SEARCH
// =========================================================
$search       = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterJenis  = $_GET['jenis']  ?? 'semua';
$filterStatus = $_GET['status'] ?? 'semua';

// Filter tanggal
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
} elseif ($range === 'all') {
    // Tanpa filter tanggal
    $tgl_mulai = $tgl_selesai = null;
} elseif ($range === 'custom') {
    // Pakai tgl_mulai & tgl_selesai dari input
} else {
    // Default: bulan ini
    $tgl_mulai   = date('Y-m-01');
    $tgl_selesai = date('Y-m-d');
}

$allowedJenis  = ['semua', 'penjualan', 'pembelian'];
$allowedStatus = ['semua', 'Diproses', 'Dikirim', 'Selesai', 'Batal'];
if (!in_array($filterJenis, $allowedJenis))   $filterJenis  = 'semua';
if (!in_array($filterStatus, $allowedStatus)) $filterStatus = 'semua';

// =========================================================
// PAGINATION
// =========================================================
$perPage     = (int)($_GET['per_page'] ?? 25);
$allowedPer  = [10, 25, 50, 100];
if (!in_array($perPage, $allowedPer)) $perPage = 25;

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// =========================================================
// BUILD WHERE
// =========================================================
$where = "WHERE 1=1";

if ($filterJenis !== 'semua') {
    $jenisEsc = mysqli_real_escape_string($conn, $filterJenis);
    $where .= " AND t.jenis_transaksi = '$jenisEsc'";
}
if ($filterStatus !== 'semua') {
    $statusEsc = mysqli_real_escape_string($conn, $filterStatus);
    $where .= " AND t.status = '$statusEsc'";
}
if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (t.kode_transaksi LIKE '%$searchEsc%' OR t.nama_penerima LIKE '%$searchEsc%' OR u.nama_lengkap LIKE '%$searchEsc%')";
}
if ($tgl_mulai !== null && $tgl_selesai !== null) {
    $mulaiEsc   = mysqli_real_escape_string($conn, $tgl_mulai);
    $selesaiEsc = mysqli_real_escape_string($conn, $tgl_selesai);
    $where .= " AND DATE(t.created_at) BETWEEN '$mulaiEsc' AND '$selesaiEsc'";
}

// =========================================================
// EXPORT CSV
// =========================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $qExport = mysqli_query($conn, "
        SELECT t.kode_transaksi, 
               COALESCE(u.nama_lengkap, 'Walk-in / Supplier') AS pelanggan,
               COALESCE(t.nama_penerima, '-') AS penerima,
               t.created_at, t.jenis_transaksi, t.status, 
               (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item,
               t.metode_pembayaran, t.total_harga
        FROM transaksi t
        LEFT JOIN users u ON t.user_id = u.id
        $where
        ORDER BY t.created_at DESC
    ");

    $filename = 'transaksi_planthub_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM UTF-8 biar Excel baca bener
    fputs($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Kode Transaksi', 'Pelanggan', 'Penerima', 'Tanggal', 'Jenis',
        'Status', 'Jumlah Item', 'Metode', 'Total (Rp)'
    ]);

    while ($r = mysqli_fetch_assoc($qExport)) {
        fputcsv($out, [
            $r['kode_transaksi'],
            $r['pelanggan'],
            $r['penerima'],
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['jenis_transaksi'],
            $r['status'],
            (int)$r['jml_item'],
            $r['metode_pembayaran'] ?? '-',
            (float)$r['total_harga'],
        ]);
    }

    fclose($out);
    exit;
}

// =========================================================
// HITUNG TOTAL ROW (untuk pagination)
// =========================================================
$qCount = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    $where
");
$totalRows  = $qCount ? (int)mysqli_fetch_assoc($qCount)['total'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// =========================================================
// AMBIL TRANSAKSI (dengan pagination)
// =========================================================
$transaksi = [];
$qTrx = mysqli_query($conn, "
    SELECT t.*, 
           COALESCE(u.nama_lengkap, 'Walk-in / Supplier') AS pelanggan,
           (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    $where
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET $offset
");
if ($qTrx) while ($r = mysqli_fetch_assoc($qTrx)) $transaksi[] = $r;

// =========================================================
// STATISTIK (dari filter aktif, bukan semua)
// =========================================================
$stat = ['penjualan' => 0, 'pembelian' => 0, 'trx_jual' => 0, 'trx_beli' => 0];
$qStat = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN t.jenis_transaksi='penjualan' THEN t.total_harga ELSE 0 END),0) AS penjualan,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi='pembelian' THEN t.total_harga ELSE 0 END),0) AS pembelian,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi='penjualan' THEN 1 ELSE 0 END),0) AS trx_jual,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi='pembelian' THEN 1 ELSE 0 END),0) AS trx_beli
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    $where
");
if ($qStat) $stat = mysqli_fetch_assoc($qStat);

function badgeStatus($status) {
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}

// Helper untuk build URL pagination (pertahankan semua filter)
function buildUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return 'transaksi.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Riwayat Transaksi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- HEADER -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
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
                <span class="text-xs text-stone-500 self-center">Riwayat Transaksi</span>
            </div>

            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_nama) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                        <p class="text-xs text-stone-500">Administrator</p>
                    </div>
                </a>
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
                        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],
                        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],
                        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => false],
                        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => false],
                        ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','active' => true],
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
                        ['url' => 'pengaturan.php', 'icon' => 'settings',    'label' => 'Pengaturan Toko', 'active' => false],
                        ['url' => 'bantuan.php',    'icon' => 'help-circle', 'label' => 'Bantuan',         'active' => false],
                    ];
                    foreach ($menu2 as $m):
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100 transition-colors">
                            <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 text-stone-500"></i>
                            <span><?= $m['label'] ?></span>
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

        <!-- MAIN -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Riwayat Transaksi</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Semua transaksi penjualan & pembelian dari pelanggan dan supplier.</p>
                </div>
                <a href="<?= buildUrl(['export' => 'csv']) ?>" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-4 py-2.5 rounded-xl text-sm font-semibold text-stone-700 hover:bg-stone-50 shadow-sm self-start sm:self-auto">
                    <i data-lucide="download" class="w-4 h-4 text-stone-500"></i>
                    Export CSV
                </a>
            </div>

            <!-- STATISTIK -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Penjualan</p>
                    <p class="text-lg font-bold text-stone-800 mt-1">Rp <?= number_format($stat['penjualan'], 0, ',', '.') ?></p>
                    <p class="text-[11px] text-stone-400 mt-1"><?= (int)$stat['trx_jual'] ?> transaksi</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Pembelian</p>
                    <p class="text-lg font-bold text-stone-800 mt-1">Rp <?= number_format($stat['pembelian'], 0, ',', '.') ?></p>
                    <p class="text-[11px] text-stone-400 mt-1"><?= (int)$stat['trx_beli'] ?> restock</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-emerald-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Transaksi Tampil</p>
                    <p class="text-lg font-bold text-stone-800 mt-1"><?= $totalRows ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Setelah filter</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-blue-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Periode</p>
                    <p class="text-sm font-bold text-stone-800 mt-1">
                        <?= $tgl_mulai ? date('d/m/y', strtotime($tgl_mulai)) : '—' ?> s.d. <?= $tgl_selesai ? date('d/m/y', strtotime($tgl_selesai)) : '—' ?>
                    </p>
                    <a href="transaksi.php?range=all" class="text-[11px] text-[#2E7D32] font-semibold hover:underline mt-1 inline-block">Lihat semua</a>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5 space-y-4">

                <!-- FILTER TANGGAL CEPAT -->
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <p class="text-xs font-bold text-stone-400 uppercase tracking-wider">Filter Cepat</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="<?= buildUrl(['range' => 'today',  'page' => 1]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='today'  ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Hari Ini</a>
                        <a href="<?= buildUrl(['range' => '7days',  'page' => 1]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='7days'  ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">7 Hari</a>
                        <a href="<?= buildUrl(['range' => '30days', 'page' => 1]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='30days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">30 Hari</a>
                        <a href="<?= buildUrl(['range' => 'all',    'page' => 1]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='all'    ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Semua</a>
                    </div>
                </div>

                <!-- FORM FILTER -->
                <form method="GET" action="transaksi.php" class="grid grid-cols-1 md:grid-cols-6 gap-3 pt-3 border-t border-stone-100">
                    <input type="hidden" name="range" value="custom">
                    <input type="hidden" name="per_page" value="<?= $perPage ?>">

                    <div class="md:col-span-2 relative">
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Cari</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Kode / penerima..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Mulai</label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai ?? '') ?>" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Sampai</label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai ?? '') ?>" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Jenis</label>
                        <select name="jenis" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            <option value="semua" <?= $filterJenis === 'semua' ? 'selected' : '' ?>>Semua</option>
                            <option value="penjualan" <?= $filterJenis === 'penjualan' ? 'selected' : '' ?>>Penjualan</option>
                            <option value="pembelian" <?= $filterJenis === 'pembelian' ? 'selected' : '' ?>>Pembelian</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            <option value="semua" <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua</option>
                            <option value="Diproses" <?= $filterStatus === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                            <option value="Dikirim"  <?= $filterStatus === 'Dikirim'  ? 'selected' : '' ?>>Dikirim</option>
                            <option value="Selesai"  <?= $filterStatus === 'Selesai'  ? 'selected' : '' ?>>Selesai</option>
                            <option value="Batal"    <?= $filterStatus === 'Batal'    ? 'selected' : '' ?>>Batal</option>
                        </select>
                    </div>

                    <div class="md:col-span-6 flex items-center gap-2 justify-end">
                        <a href="transaksi.php" class="px-4 py-2.5 bg-stone-100 text-stone-600 rounded-xl text-sm font-semibold hover:bg-stone-200 transition">Reset</a>
                        <button type="submit" class="bg-[#2E7D32] hover:bg-emerald-800 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition inline-flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4"></i> Terapkan
                        </button>
                    </div>
                </form>
            </div>

            <!-- TABEL -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">Kode</th>
                                <th class="py-3.5 px-4">Pelanggan / Supplier</th>
                                <th class="py-3.5 px-4">Tanggal</th>
                                <th class="py-3.5 px-4">Jenis</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-center">Item</th>
                                <th class="py-3.5 px-4 text-right">Total</th>
                                <th class="py-3.5 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($transaksi)): ?>
                                <tr>
                                    <td colspan="8" class="py-12 text-center">
                                        <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i data-lucide="receipt" class="w-6 h-6 text-stone-400"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-stone-700">Belum ada transaksi</p>
                                        <p class="text-xs text-stone-500 mt-1">Coba ubah filter atau periode tanggal.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $sumPage = 0;
                                foreach ($transaksi as $t): 
                                    $isJual = ($t['jenis_transaksi'] === 'penjualan');
                                    $sumPage += (float)$t['total_harga'];
                                ?>
                                    <tr class="hover:bg-stone-50/60 transition-colors">
                                        <td class="py-3.5 px-6 font-mono text-[11px] font-semibold text-stone-700"><?= htmlspecialchars($t['kode_transaksi']) ?></td>
                                        <td class="py-3.5 px-4 font-semibold text-stone-800"><?= htmlspecialchars($t['pelanggan']) ?></td>
                                        <td class="py-3.5 px-4 text-stone-500"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase <?= $isJual ? 'bg-emerald-50 text-[#2E7D32] border border-emerald-100' : 'bg-amber-50 text-[#D97706] border border-amber-100' ?>">
                                                <?= htmlspecialchars($t['jenis_transaksi']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border <?= badgeStatus($t['status']) ?>">
                                                <?= htmlspecialchars($t['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-semibold text-stone-700"><?= (int)$t['jml_item'] ?></td>
                                        <td class="py-3.5 px-4 text-right font-bold text-stone-800">Rp <?= number_format($t['total_harga'], 0, ',', '.') ?></td>
                                        <td class="py-3.5 px-6 text-center">
                                            <a href="detail_transaksi.php?id=<?= (int)$t['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-[#2E7D32] hover:bg-emerald-50 rounded-lg transition">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($transaksi)): ?>
                            <tfoot>
                                <tr class="bg-stone-50 border-t-2 border-stone-200 text-xs">
                                    <td colspan="6" class="py-3 px-6 font-bold text-stone-600 text-right">Total Halaman Ini:</td>
                                    <td class="py-3 px-4 text-right font-bold text-[#2E7D32] text-sm">Rp <?= number_format($sumPage, 0, ',', '.') ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- PAGINATION -->
                <div class="p-4 bg-stone-50/60 border-t border-stone-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-stone-500">
                        <?php 
                        $start = $totalRows > 0 ? $offset + 1 : 0;
                        $end   = min($offset + $perPage, $totalRows);
                        ?>
                        Menampilkan <b class="text-stone-700"><?= $start ?>–<?= $end ?></b> dari <b class="text-stone-700"><?= $totalRows ?></b> transaksi
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Per page selector -->
                        <form method="GET" action="transaksi.php" class="inline-flex items-center gap-1">
                            <?php foreach ($_GET as $k => $v): if ($k !== 'per_page' && $k !== 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                            <?php endif; endforeach; ?>
                            <label class="text-xs text-stone-500">Per halaman:</label>
                            <select name="per_page" onchange="this.form.submit()" class="px-2 py-1 text-xs bg-white border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32]">
                                <?php foreach ($allowedPer as $n): ?>
                                    <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <!-- Prev / Next -->
                        <div class="flex items-center gap-1">
                            <a href="<?= $page > 1 ? buildUrl(['page' => $page - 1]) : '#' ?>" 
                               class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $page > 1 ? 'bg-white text-stone-700 hover:bg-stone-100 border-stone-200' : 'bg-stone-100 text-stone-400 border-stone-100 cursor-not-allowed' ?>">
                                <i data-lucide="chevron-left" class="w-3.5 h-3.5 inline"></i> Prev
                            </a>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage   = min($totalPages, $page + 2);
                            if ($startPage > 1) echo '<span class="text-xs text-stone-400 px-1">...</span>';
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="<?= buildUrl(['page' => $i]) ?>" 
                                   class="px-3 py-1.5 text-xs font-bold rounded-lg border <?= $i === $page ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-white text-stone-700 hover:bg-stone-100 border-stone-200' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor;
                            if ($endPage < $totalPages) echo '<span class="text-xs text-stone-400 px-1">...</span>';
                            ?>

                            <a href="<?= $page < $totalPages ? buildUrl(['page' => $page + 1]) : '#' ?>" 
                               class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $page < $totalPages ? 'bg-white text-stone-700 hover:bg-stone-100 border-stone-200' : 'bg-stone-100 text-stone-400 border-stone-100 cursor-not-allowed' ?>">
                                Next <i data-lucide="chevron-right" class="w-3.5 h-3.5 inline"></i>
                            </a>
                        </div>
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
    </script>
</body>
</html>