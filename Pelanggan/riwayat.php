<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$nama_user  = $_SESSION['nama_user'] ?? 'Pelanggan';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// =========================================================
// HELPER FUNCTIONS (dideklarasikan 1x saja di atas)
// =========================================================
if (!function_exists('badgeStatus')) {
    function badgeStatus($status) {
        switch ($status) {
            case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
            case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
            default:         return 'bg-stone-100 text-stone-600 border-stone-200';
        }
    }
}

if (!function_exists('buildRiwayatUrl')) {
    function buildRiwayatUrl($overrides = []) {
        $params = array_merge($_GET, $overrides);
        return 'riwayat.php?' . http_build_query($params);
    }
}

// =========================================================
// HANDLE BATALKAN PESANAN
// =========================================================
$batalMsg = '';
$batalErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'batalkan') {
    $trx_id = (int)($_POST['trx_id'] ?? 0);
    if ($trx_id > 0) {
        $qCek = mysqli_query($conn, "SELECT * FROM transaksi WHERE id = $trx_id AND user_id = $user_id AND jenis_transaksi = 'penjualan' LIMIT 1");
        if ($qCek && $row = mysqli_fetch_assoc($qCek)) {
            if ($row['status'] !== 'Diproses') {
                $batalErr = "Hanya pesanan dengan status 'Diproses' yang bisa dibatalkan.";
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $qDet = mysqli_query($conn, "SELECT produk_id, jumlah FROM transaksi_detail WHERE transaksi_id = $trx_id");
                    if ($qDet) {
                        while ($d = mysqli_fetch_assoc($qDet)) {
                            $pid = (int)$d['produk_id'];
                            $qty = (int)$d['jumlah'];
                            mysqli_query($conn, "UPDATE produk SET stok = stok + $qty WHERE id = $pid");
                        }
                    }
                    if (!mysqli_query($conn, "UPDATE transaksi SET status = 'Batal' WHERE id = $trx_id")) {
                        throw new Exception(mysqli_error($conn));
                    }
                    mysqli_commit($conn);
                    header("Location: riwayat.php?status=batal");
                    exit;
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $batalErr = "Gagal membatalkan: " . $e->getMessage();
                }
            }
        } else {
            $batalErr = "Pesanan tidak ditemukan.";
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'batal') {
    $batalMsg = "Pesanan berhasil dibatalkan. Stok produk telah dikembalikan.";
}

// =========================================================
// HANDLE BELI LAGI
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'beli_lagi') {
    $trx_id = (int)($_POST['trx_id'] ?? 0);
    if ($trx_id > 0) {
        $qCek = mysqli_query($conn, "SELECT id FROM transaksi WHERE id = $trx_id AND user_id = $user_id AND jenis_transaksi = 'penjualan' LIMIT 1");
        if ($qCek && mysqli_num_rows($qCek) > 0) {
            $added = 0;
            $skipped = [];

            $qDet = mysqli_query($conn, "
                SELECT td.produk_id, td.jumlah, p.nama_tanaman, p.stok 
                FROM transaksi_detail td
                LEFT JOIN produk p ON td.produk_id = p.id
                WHERE td.transaksi_id = $trx_id
            ");
            if ($qDet) {
                while ($d = mysqli_fetch_assoc($qDet)) {
                    $pid  = (int)$d['produk_id'];
                    $qty  = (int)$d['jumlah'];
                    $stok = (int)($d['stok'] ?? 0);
                    $nama = $d['nama_tanaman'] ?? 'Produk';

                    if ($pid <= 0) continue;

                    if ($stok <= 0) {
                        $skipped[] = "$nama (habis)";
                        continue;
                    }

                    $qtyFinal = min($qty, $stok);
                    if ($qtyFinal < $qty) {
                        $skipped[] = "$nama (stok kurang, dapat $qtyFinal dari $qty)";
                    }

                    $qtySekarang = isset($_SESSION['cart'][$pid]) ? (int)$_SESSION['cart'][$pid] : 0;
                    $_SESSION['cart'][$pid] = min($qtySekarang + $qtyFinal, $stok);
                    $added++;
                }
            }

            $_SESSION['beli_lagi_result'] = [
                'added'   => $added,
                'skipped' => $skipped,
            ];
            header("Location: cart.php?msg=beli_lagi");
            exit;
        }
    }
    header("Location: riwayat.php");
    exit;
}

// =========================================================
// PARAMETER FILTER
// =========================================================
$statusFilter = $_GET['status_filter'] ?? 'semua';
$allowedStatus = ['semua', 'Diproses', 'Dikirim', 'Selesai', 'Batal'];
if (!in_array($statusFilter, $allowedStatus)) $statusFilter = 'semua';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Filter tanggal
$range       = $_GET['range'] ?? 'all';
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
    $tgl_mulai = $tgl_selesai = null;
} elseif ($range === 'custom') {
    // pakai input dari user
}

// =========================================================
// BUILD WHERE
// =========================================================
$where = "WHERE t.user_id = $user_id AND t.jenis_transaksi = 'penjualan'";

if ($statusFilter !== 'semua') {
    $statusEsc = mysqli_real_escape_string($conn, $statusFilter);
    $where .= " AND t.status = '$statusEsc'";
}
if ($search !== '') {
    $where .= " AND t.kode_transaksi LIKE '%$search%'";
}
if ($tgl_mulai !== null && $tgl_selesai !== null) {
    $mulaiEsc   = mysqli_real_escape_string($conn, $tgl_mulai);
    $selesaiEsc = mysqli_real_escape_string($conn, $tgl_selesai);
    $where .= " AND DATE(t.created_at) BETWEEN '$mulaiEsc' AND '$selesaiEsc'";
}

// =========================================================
// QUERY TRANSAKSI
// =========================================================
$orders = [];
$qOrders = mysqli_query($conn, "
    SELECT t.*, 
           (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item,
           (SELECT COALESCE(SUM(jumlah), 0) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS total_qty
    FROM transaksi t
    $where
    ORDER BY t.created_at DESC
");
if ($qOrders) while ($row = mysqli_fetch_assoc($qOrders)) $orders[] = $row;

// =========================================================
// STATISTIK GLOBAL
// =========================================================
$statTotal   = 0;
$statPending = 0;
$statSelesai = 0;
$statBelanja = 0;

$qStat = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status IN ('Diproses','Dikirim') THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END), 0) AS selesai,
        COALESCE(SUM(total_harga), 0) AS total_belanja
    FROM transaksi
    WHERE user_id = $user_id AND jenis_transaksi = 'penjualan'
");
if ($qStat) {
    $s = mysqli_fetch_assoc($qStat);
    $statTotal   = (int)$s['total'];
    $statPending = (int)$s['pending'];
    $statSelesai = (int)$s['selesai'];
    $statBelanja = (float)$s['total_belanja'];
}

$successMsg = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $kode = htmlspecialchars($_GET['kode'] ?? '');
    $successMsg = "Pesanan berhasil dibuat" . ($kode ? " dengan kode <b>$kode</b>" : "") . ". Silakan tunggu konfirmasi admin.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Riwayat Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <form action="katalog.php" method="GET" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <div class="flex items-center gap-3">
                <a href="cart.php" class="relative p-2 text-stone-600 hover:bg-stone-100 rounded-full">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
                <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($nama_user) ?></p>
                        <p class="text-xs text-stone-500">Pelanggan</p>
                    </div>
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => false],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => false],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => true],
                        ['url' => 'chat.php',      'icon' => 'message-square', 'label' => 'Konsultasi',     'active' => false],
                        ['url' => 'profil.php',    'icon' => 'user',           'label' => 'Profil Saya',    'active' => false],
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
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 leading-tight">Troli Belanja</p>
                    <p class="text-[11px] text-stone-400 mt-0.5"><?= $cart_count ?> item</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Riwayat Order</h1>
                <p class="text-sm text-stone-500 mt-0.5">Daftar pesanan Anda beserta statusnya.</p>
            </div>

            <?php if ($successMsg): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                    <span><?= $successMsg ?></span>
                </div>
            <?php endif; ?>
            <?php if ($batalMsg): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                    <span><?= htmlspecialchars($batalMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($batalErr): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                    <span><?= htmlspecialchars($batalErr) ?></span>
                </div>
            <?php endif; ?>

            <!-- STATISTIK -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Pesanan</p>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mt-1"><?= $statTotal ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Transaksi Anda</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Diproses</p>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mt-1"><?= $statPending ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Menunggu / dikirim</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-emerald-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Selesai</p>
                    <p class="text-2xl font-bold text-stone-800 tracking-tight mt-1"><?= $statSelesai ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Transaksi sukses</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-blue-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Belanja</p>
                    <p class="text-lg font-bold text-stone-800 tracking-tight mt-1">Rp <?= number_format($statBelanja, 0, ',', '.') ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Kumulatif</p>
                </div>
            </div>

            <!-- TAB STATUS + SEARCH + FILTER TANGGAL -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-4 space-y-3">

                <!-- Tab Status -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <?php
                    $tabs = ['semua' => 'Semua', 'Diproses' => 'Diproses', 'Dikirim' => 'Dikirim', 'Selesai' => 'Selesai', 'Batal' => 'Batal'];
                    foreach ($tabs as $key => $label):
                        $active = ($statusFilter === $key);
                        $cls = $active ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200';
                    ?>
                        <a href="<?= buildRiwayatUrl(['status_filter' => $key]) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $cls ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search + Filter Tanggal -->
                <form method="GET" action="riwayat.php" class="grid grid-cols-1 md:grid-cols-12 gap-3 pt-3 border-t border-stone-100">
                    <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">

                    <div class="md:col-span-5 relative">
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Cari Kode</label>
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="INV-20240101-XXXXX" class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Mulai</label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai ?? '') ?>" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Sampai</label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai ?? '') ?>" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div class="md:col-span-3 flex gap-2 items-end">
                        <button type="submit" name="range" value="custom" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition inline-flex items-center justify-center gap-1.5">
                            <i data-lucide="filter" class="w-4 h-4"></i> Terapkan
                        </button>
                        <?php if ($search || $range === 'custom'): ?>
                            <a href="<?= buildRiwayatUrl(['search' => null, 'range' => 'all']) ?>" class="px-3 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl text-sm font-semibold transition" title="Reset">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Filter Tanggal Cepat -->
                <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-stone-100">
                    <span class="text-xs font-bold text-stone-400 uppercase tracking-wider">Filter cepat:</span>
                    <a href="<?= buildRiwayatUrl(['range' => 'today', 'search' => $search ?: null]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='today' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Hari Ini</a>
                    <a href="<?= buildRiwayatUrl(['range' => '7days', 'search' => $search ?: null]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='7days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">7 Hari</a>
                    <a href="<?= buildRiwayatUrl(['range' => '30days', 'search' => $search ?: null]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='30days' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">30 Hari</a>
                    <a href="<?= buildRiwayatUrl(['range' => 'all', 'search' => $search ?: null]) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range==='all' ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>">Semua</a>
                </div>

                <!-- Info hasil filter -->
                <?php if ($search || $range !== 'all'): ?>
                    <div class="flex items-center gap-2 flex-wrap text-xs pt-2 border-t border-stone-100">
                        <span class="text-stone-500">Filter aktif:</span>
                        <?php if ($search): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 text-stone-700 rounded-md">
                                <i data-lucide="search" class="w-3 h-3"></i>
                                "<?= htmlspecialchars($search) ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($range !== 'all'): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 text-stone-700 rounded-md">
                                <i data-lucide="calendar" class="w-3 h-3"></i>
                                <?= $range==='today' ? 'Hari Ini' : ($range==='7days' ? '7 Hari Terakhir' : ($range==='30days' ? '30 Hari Terakhir' : date('d/m/y', strtotime($tgl_mulai)).' - '.date('d/m/y', strtotime($tgl_selesai)))) ?>
                            </span>
                        <?php endif; ?>
                        <span class="text-stone-500">→ <b class="text-stone-700"><?= count($orders) ?></b> pesanan</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- HASIL -->
            <?php if (empty($orders)): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-4 text-stone-400">
                        <i data-lucide="<?= ($search || $range !== 'all') ? 'search-x' : 'receipt' ?>" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-base font-bold text-stone-800">
                        <?= ($search || $range !== 'all') ? 'Tidak ada pesanan yang cocok' : 'Belum ada pesanan' ?>
                    </h3>
                    <p class="text-sm text-stone-500 mt-1">
                        <?php if ($search || $range !== 'all'): ?>
                            Coba ubah filter atau <a href="riwayat.php" class="text-[#2E7D32] font-bold hover:underline">reset</a>.
                        <?php else: ?>
                            Anda belum melakukan transaksi apapun.
                        <?php endif; ?>
                    </p>
                    <?php if (!$search && $range === 'all'): ?>
                        <a href="katalog.php" class="inline-flex items-center gap-2 mt-5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                            <i data-lucide="store" class="w-4 h-4"></i>
                            <span>Mulai Belanja</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($orders as $o): 
                        $bisaBatal = ($o['status'] === 'Diproses');
                    ?>
                        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                            <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-mono text-sm font-bold text-stone-800"><?= htmlspecialchars($o['kode_transaksi']) ?></p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border <?= badgeStatus($o['status']) ?>">
                                            <?= htmlspecialchars($o['status']) ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-stone-500">
                                        <i data-lucide="calendar" class="w-3 h-3 inline-block mr-1"></i>
                                        <?= date('d M Y, H:i', strtotime($o['created_at'])) ?> WIB
                                    </p>
                                    <p class="text-xs text-stone-500">
                                        <i data-lucide="package" class="w-3 h-3 inline-block mr-1"></i>
                                        <?= (int)$o['jml_item'] ?> jenis produk &middot; <?= (int)$o['total_qty'] ?> item &middot;
                                        Metode: <span class="font-semibold text-stone-700"><?= htmlspecialchars(ucfirst($o['metode_pembayaran'])) ?></span>
                                    </p>
                                </div>

                                <div class="flex items-center gap-4 shrink-0">
                                    <div class="text-right">
                                        <p class="text-[10px] font-bold uppercase text-stone-400 tracking-wider">Total Bayar</p>
                                        <p class="text-lg font-bold text-[#2E7D32]">Rp <?= number_format($o['total_harga'], 0, ',', '.') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="px-5 pb-4 pt-3 bg-stone-50/60 border-t border-stone-100 flex items-center justify-end gap-2 flex-wrap">
                                <?php if ($bisaBatal): ?>
                                    <form method="POST" action="riwayat.php" onsubmit="return confirm('Batalkan pesanan ini? Stok produk akan dikembalikan.');" class="inline">
                                        <input type="hidden" name="action" value="batalkan">
                                        <input type="hidden" name="trx_id" value="<?= (int)$o['id'] ?>">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-600 bg-white hover:bg-rose-50 border border-rose-200 rounded-lg transition">
                                            <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Batalkan
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="note.php?id=<?= (int)$o['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-stone-700 bg-white hover:bg-stone-100 border border-stone-200 rounded-lg transition">
                                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Lihat Nota
                                </a>

                                <form method="POST" action="riwayat.php" class="inline">
                                    <input type="hidden" name="action" value="beli_lagi">
                                    <input type="hidden" name="trx_id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-[#2E7D32] hover:bg-emerald-800 rounded-lg transition">
                                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Beli Lagi
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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