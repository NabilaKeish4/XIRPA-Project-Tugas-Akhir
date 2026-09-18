<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// FILTER
// =========================================================
$search       = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$filterJenis  = $_GET['jenis']  ?? 'semua';
$filterStatus = $_GET['status'] ?? 'semua';

$allowedJenis  = ['semua', 'penjualan', 'pembelian'];
$allowedStatus = ['semua', 'Diproses', 'Dikirim', 'Selesai', 'Batal'];
if (!in_array($filterJenis, $allowedJenis))   $filterJenis  = 'semua';
if (!in_array($filterStatus, $allowedStatus)) $filterStatus = 'semua';

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
    $where .= " AND (t.kode_transaksi LIKE '%$search%' OR t.nama_penerima LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%')";
}

// =========================================================
// AMBIL TRANSAKSI
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
    LIMIT 100
");
if ($qTrx) while ($r = mysqli_fetch_assoc($qTrx)) $transaksi[] = $r;

// =========================================================
// STATISTIK RINGKAS
// =========================================================
$stat = ['penjualan' => 0, 'pembelian' => 0, 'trx_jual' => 0, 'trx_beli' => 0];
$qStat = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_transaksi='penjualan' THEN total_harga ELSE 0 END),0) AS penjualan,
        COALESCE(SUM(CASE WHEN jenis_transaksi='pembelian' THEN total_harga ELSE 0 END),0) AS pembelian,
        COALESCE(SUM(CASE WHEN jenis_transaksi='penjualan' THEN 1 ELSE 0 END),0) AS trx_jual,
        COALESCE(SUM(CASE WHEN jenis_transaksi='pembelian' THEN 1 ELSE 0 END),0) AS trx_beli
    FROM transaksi
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

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Riwayat Transaksi</h1>
                <p class="text-sm text-stone-500 mt-0.5">Semua transaksi penjualan & pembelian dari pelanggan dan supplier.</p>
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
                    <p class="text-lg font-bold text-stone-800 mt-1"><?= count($transaksi) ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Setelah filter</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-blue-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Filter Aktif</p>
                    <p class="text-sm font-bold text-stone-800 mt-1 capitalize"><?= htmlspecialchars($filterJenis) ?> / <?= htmlspecialchars($filterStatus) ?></p>
                    <a href="transaksi.php" class="text-[11px] text-[#2E7D32] font-semibold hover:underline mt-1 inline-block">Reset filter</a>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-4">
                <form method="GET" action="transaksi.php" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2 relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari kode transaksi / penerima..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>
                    <select name="jenis" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        <option value="semua" <?= $filterJenis === 'semua' ? 'selected' : '' ?>>Semua Jenis</option>
                        <option value="penjualan" <?= $filterJenis === 'penjualan' ? 'selected' : '' ?>>Penjualan</option>
                        <option value="pembelian" <?= $filterJenis === 'pembelian' ? 'selected' : '' ?>>Pembelian</option>
                    </select>
                    <select name="status" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        <option value="semua" <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="Diproses" <?= $filterStatus === 'Diproses' ? 'selected' : '' ?>>Diproses</option>
                        <option value="Dikirim"  <?= $filterStatus === 'Dikirim'  ? 'selected' : '' ?>>Dikirim</option>
                        <option value="Selesai"  <?= $filterStatus === 'Selesai'  ? 'selected' : '' ?>>Selesai</option>
                        <option value="Batal"    <?= $filterStatus === 'Batal'    ? 'selected' : '' ?>>Batal</option>
                    </select>
                    <button type="submit" class="md:col-span-4 bg-[#2E7D32] hover:bg-emerald-800 text-white text-sm font-semibold py-2.5 rounded-xl transition">
                        Terapkan Filter
                    </button>
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
                                        <p class="text-xs text-stone-500 mt-1">Transaksi pelanggan akan muncul di sini setelah mereka checkout.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transaksi as $t): 
                                    $isJual = ($t['jenis_transaksi'] === 'penjualan');
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
                    </table>
                </div>

                <div class="p-4 bg-stone-50/60 border-t border-stone-100 flex items-center justify-between text-xs text-stone-500">
                    <span>Menampilkan <b class="text-stone-700"><?= count($transaksi) ?></b> transaksi terbaru</span>
                    <span class="text-stone-400">Maksimal 100 baris terakhir</span>
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