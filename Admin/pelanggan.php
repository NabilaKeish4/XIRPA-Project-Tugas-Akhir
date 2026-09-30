<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// HANDLE RESET PASSWORD
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    header('Content-Type: application/json');
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit;
    }

    // Cek user ada & role customer
    $qCek = mysqli_query($conn, "SELECT id, nama_lengkap, username, email FROM users WHERE id = $user_id AND role = 'customer' LIMIT 1");
    if (!$qCek || mysqli_num_rows($qCek) === 0) {
        echo json_encode(['success' => false, 'message' => 'Pelanggan tidak ditemukan.']);
        exit;
    }
    $user = mysqli_fetch_assoc($qCek);

    // Generate password acak 8 karakter (huruf + angka, mudah dibaca)
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $newPassword = '';
    for ($i = 0; $i < 8; $i++) {
        $newPassword .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $hashEsc = mysqli_real_escape_string($conn, $hash);

    if (mysqli_query($conn, "UPDATE users SET password = '$hashEsc' WHERE id = $user_id")) {
        echo json_encode([
            'success'  => true,
            'message'  => 'Password berhasil direset.',
            'password' => $newPassword,
            'nama'     => $user['nama_lengkap'],
            'username' => $user['username'],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal reset: ' . mysqli_error($conn)]);
    }
    exit;
}

// =========================================================
// FILTER & SORTING
// =========================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$tab    = $_GET['tab'] ?? 'semua';   // semua | aktif | baru | belum
$sortBy = $_GET['sort'] ?? 'terbaru'; // terbaru | nama | belanja | transaksi

$allowedTab  = ['semua', 'aktif', 'baru', 'belum'];
$allowedSort = ['terbaru', 'nama', 'belanja', 'transaksi'];
if (!in_array($tab, $allowedTab))   $tab = 'semua';
if (!in_array($sortBy, $allowedSort)) $sortBy = 'terbaru';

$where = "WHERE u.role = 'customer'";
if ($search !== '') {
    $where .= " AND (u.nama_lengkap LIKE '%$search%' OR u.email LIKE '%$search%' OR u.username LIKE '%$search%' OR u.no_hp LIKE '%$search%')";
}

// Filter tab
if ($tab === 'aktif') {
    $where .= " AND (SELECT COUNT(*) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi='penjualan') > 0";
} elseif ($tab === 'baru') {
    // Daftar 30 hari terakhir
    $where .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($tab === 'belum') {
    $where .= " AND (SELECT COUNT(*) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi='penjualan') = 0";
}

// Sorting
$orderBy = match($sortBy) {
    'nama'      => 'u.nama_lengkap ASC',
    'belanja'   => 'total_belanja DESC',
    'transaksi' => 'total_order DESC',
    default     => 'u.created_at DESC',
};

// =========================================================
// AMBIL DAFTAR PELANGGAN
// =========================================================
$pelanggan = [];
$qPel = mysqli_query($conn, "
    SELECT 
        u.id, u.nama_lengkap, u.username, u.email, u.no_hp, u.alamat, u.created_at,
        COALESCE((SELECT COUNT(*) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi = 'penjualan'), 0) AS total_order,
        COALESCE((SELECT SUM(total_harga) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi = 'penjualan' AND t.status = 'Selesai'), 0) AS total_belanja,
        COALESCE((SELECT COUNT(*) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi = 'penjualan' AND t.status IN ('Diproses','Dikirim')), 0) AS order_aktif,
        (SELECT MAX(t.created_at) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi = 'penjualan') AS last_order
    FROM users u
    $where
    ORDER BY $orderBy
");
if ($qPel) while ($r = mysqli_fetch_assoc($qPel)) $pelanggan[] = $r;

// =========================================================
// STATISTIK GLOBAL (per tab)
// =========================================================
$stat = ['total' => 0, 'aktif' => 0, 'baru' => 0, 'belum' => 0, 'total_belanja' => 0];

$qStat = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN (SELECT COUNT(*) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi='penjualan') > 0 THEN 1 ELSE 0 END), 0) AS aktif,
        COALESCE(SUM(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS baru,
        COALESCE(SUM(CASE WHEN (SELECT COUNT(*) FROM transaksi t WHERE t.user_id = u.id AND t.jenis_transaksi='penjualan') = 0 THEN 1 ELSE 0 END), 0) AS belum
    FROM users u
    WHERE u.role = 'customer'
");
if ($qStat) {
    $s = mysqli_fetch_assoc($qStat);
    $stat['total'] = (int)$s['total'];
    $stat['aktif'] = (int)$s['aktif'];
    $stat['baru']  = (int)$s['baru'];
    $stat['belum'] = (int)$s['belum'];
}

$qTotalBelanja = mysqli_query($conn, "
    SELECT COALESCE(SUM(total_harga), 0) AS total 
    FROM transaksi 
    WHERE jenis_transaksi = 'penjualan' AND status = 'Selesai'
");
if ($qTotalBelanja) $stat['total_belanja'] = (float)mysqli_fetch_assoc($qTotalBelanja)['total'];

// Data transaksi untuk modal (per pelanggan)
$dataTransaksi = [];
if (!empty($pelanggan)) {
    $ids = implode(',', array_map(fn($p) => (int)$p['id'], $pelanggan));
    if ($ids !== '') {
        $qTrx = mysqli_query($conn, "
            SELECT t.id, t.kode_transaksi, t.user_id, t.total_harga, t.status, t.created_at
            FROM transaksi t
            WHERE t.user_id IN ($ids) AND t.jenis_transaksi = 'penjualan'
            ORDER BY t.created_at DESC
        ");
        if ($qTrx) {
            while ($r = mysqli_fetch_assoc($qTrx)) {
                $dataTransaksi[$r['user_id']][] = $r;
            }
        }
    }
}

// Helper URL
function buildUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return 'pelanggan.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Data Pelanggan</title>
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
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <form method="GET" action="pelanggan.php" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, email, username, no HP..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
            </form>

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
                        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => true],
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
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Data Pelanggan</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Daftar pelanggan terdaftar di PlantHub beserta riwayat transaksi mereka.</p>
                </div>
            </div>

            <!-- STATISTIK -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="<?= buildUrl(['tab' => 'semua']) ?>" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 <?= $tab==='semua' ? 'border-t-[#2E7D32]' : 'border-t-stone-300' ?> hover:shadow-md transition-shadow">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Pelanggan</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $stat['total'] ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Akun terdaftar</p>
                </a>
                <a href="<?= buildUrl(['tab' => 'aktif']) ?>" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 <?= $tab==='aktif' ? 'border-t-emerald-500' : 'border-t-stone-300' ?> hover:shadow-md transition-shadow">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Pelanggan Aktif</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $stat['aktif'] ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Pernah bertransaksi</p>
                </a>
                <a href="<?= buildUrl(['tab' => 'baru']) ?>" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 <?= $tab==='baru' ? 'border-t-blue-500' : 'border-t-stone-300' ?> hover:shadow-md transition-shadow">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Pelanggan Baru</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $stat['baru'] ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">30 hari terakhir</p>
                </a>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-stone-300">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Belanja</p>
                    <p class="text-lg font-bold text-stone-800 mt-1">Rp <?= number_format($stat['total_belanja'], 0, ',', '.') ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Dari transaksi selesai</p>
                </div>
            </div>

            <!-- TAB FILTER + SORTING -->
            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm space-y-3">

                <!-- Tab -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <a href="<?= buildUrl(['tab' => 'semua']) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'semua' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Semua <span class="opacity-70">(<?= $stat['total'] ?>)</span>
                    </a>
                    <a href="<?= buildUrl(['tab' => 'aktif']) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'aktif' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Aktif <span class="opacity-70">(<?= $stat['aktif'] ?>)</span>
                    </a>
                    <a href="<?= buildUrl(['tab' => 'baru']) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'baru' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Baru <span class="opacity-70">(<?= $stat['baru'] ?>)</span>
                    </a>
                    <a href="<?= buildUrl(['tab' => 'belum']) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'belum' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Belum Beli <span class="opacity-70">(<?= $stat['belum'] ?>)</span>
                    </a>
                </div>

                <!-- Sorting -->
                <div class="flex items-center gap-2 overflow-x-auto pt-3 border-t border-stone-100">
                    <span class="text-xs font-bold text-stone-400 uppercase tracking-wider whitespace-nowrap">Urutkan:</span>
                    <a href="<?= buildUrl(['sort' => 'terbaru']) ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $sortBy === 'terbaru' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Terbaru</a>
                    <a href="<?= buildUrl(['sort' => 'nama']) ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $sortBy === 'nama' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Nama A–Z</a>
                    <a href="<?= buildUrl(['sort' => 'belanja']) ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $sortBy === 'belanja' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Total Belanja</a>
                    <a href="<?= buildUrl(['sort' => 'transaksi']) ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $sortBy === 'transaksi' ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Jumlah Order</a>
                </div>
            </div>

            <!-- SEARCH BAR (mobile) -->
            <form method="GET" action="pelanggan.php" class="md:hidden bg-white p-3 rounded-2xl border border-stone-200/80 shadow-sm">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, email, no HP..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                </div>
            </form>

            <!-- TABEL PELANGGAN -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">Pelanggan</th>
                                <th class="py-3.5 px-4">Kontak</th>
                                <th class="py-3.5 px-4">Alamat</th>
                                <th class="py-3.5 px-4 text-center">Transaksi</th>
                                <th class="py-3.5 px-4 text-right">Total Belanja</th>
                                <th class="py-3.5 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($pelanggan)): ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center">
                                        <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i data-lucide="<?= $search ? 'search-x' : 'users' ?>" class="w-6 h-6 text-stone-400"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-stone-700">
                                            <?= $search ? 'Tidak ada pelanggan yang cocok' : 'Belum ada pelanggan' ?>
                                        </p>
                                        <p class="text-xs text-stone-500 mt-1">
                                            <?php if ($search): ?>
                                                Coba kata kunci lain atau <a href="pelanggan.php" class="text-[#2E7D32] font-bold hover:underline">reset pencarian</a>.
                                            <?php else: ?>
                                                Pelanggan yang mendaftar via Auth akan muncul di sini.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pelanggan as $p):
                                    $initial = strtoupper(substr($p['nama_lengkap'] ?? 'P', 0, 1));
                                ?>
                                    <tr class="hover:bg-stone-50/60 transition-colors">
                                        <td class="py-3.5 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-xs shrink-0">
                                                    <?= htmlspecialchars($initial) ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-stone-800 truncate"><?= htmlspecialchars($p['nama_lengkap'] ?? 'Pelanggan') ?></p>
                                                    <p class="text-[11px] text-stone-400">@<?= htmlspecialchars($p['username']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <p class="text-stone-700"><?= htmlspecialchars($p['email'] ?? '-') ?></p>
                                            <p class="text-[11px] text-stone-400 mt-0.5"><?= htmlspecialchars($p['no_hp'] ?? '-') ?></p>
                                        </td>
                                        <td class="py-3.5 px-4 text-stone-500 max-w-xs truncate">
                                            <?= htmlspecialchars($p['alamat'] ?? '-') ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <span class="px-2.5 py-1 rounded-lg font-bold border <?= (int)$p['total_order'] > 0 ? 'bg-emerald-50 text-[#2E7D32] border-emerald-100' : 'bg-stone-100 text-stone-500 border-stone-200' ?>">
                                                <?= (int)$p['total_order'] ?> order
                                            </span>
                                            <?php if ((int)$p['order_aktif'] > 0): ?>
                                                <p class="text-[10px] text-[#D97706] font-semibold mt-1"><?= (int)$p['order_aktif'] ?> aktif</p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-bold <?= (float)$p['total_belanja'] > 0 ? 'text-[#2E7D32]' : 'text-stone-400' ?>">
                                            Rp <?= number_format($p['total_belanja'], 0, ',', '.') ?>
                                        </td>
                                        <td class="py-3.5 px-6 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button onclick='openDetail(<?= json_encode([
                                                    "id" => $p['id'], "nama" => $p["nama_lengkap"], "username" => $p["username"],
                                                    "email" => $p["email"], "no_hp" => $p["no_hp"], "alamat" => $p["alamat"],
                                                    "created_at" => $p["created_at"], "total_order" => $p["total_order"],
                                                    "total_belanja" => $p["total_belanja"],
                                                    "transaksi" => $dataTransaksi[$p["id"]] ?? []
                                                ]) ?>)' class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-[#2E7D32] hover:bg-emerald-50 rounded-lg transition">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detail
                                                </button>
                                                <button onclick='openResetPassword(<?= (int)$p["id"] ?>, <?= json_encode($p["nama_lengkap"]) ?>)' class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Reset Password">
                                                    <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-stone-50/60 border-t border-stone-100 text-xs text-stone-500">
                    Menampilkan <b class="text-stone-700"><?= count($pelanggan) ?></b> pelanggan
                    <?= $search ? 'dari pencarian <b class="text-stone-700">"' . htmlspecialchars($search) . '"</b>' : '' ?>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL DETAIL PELANGGAN -->
    <div id="modalDetail" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full my-8">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div id="dAvatar" class="w-12 h-12 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-base">?</div>
                    <div>
                        <h3 id="dNama" class="text-lg font-bold text-stone-800">-</h3>
                        <p id="dUsername" class="text-xs text-stone-500">-</p>
                    </div>
                </div>
                <button onclick="closeDetail()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-5 space-y-5 max-h-[70vh] overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-stone-50 border border-stone-100 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Email</p>
                        <p id="dEmail" class="text-sm font-semibold text-stone-800 mt-0.5">-</p>
                    </div>
                    <div class="bg-stone-50 border border-stone-100 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">No. HP</p>
                        <p id="dNoHp" class="text-sm font-semibold text-stone-800 mt-0.5">-</p>
                    </div>
                    <div class="bg-stone-50 border border-stone-100 rounded-xl p-3 md:col-span-2">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Alamat</p>
                        <p id="dAlamat" class="text-sm text-stone-700 mt-0.5">-</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 text-center">
                        <p class="text-[10px] font-bold text-[#2E7D32] uppercase">Total Order</p>
                        <p id="dTotalOrder" class="text-lg font-bold text-[#2E7D32] mt-0.5">0</p>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3 text-center">
                        <p class="text-[10px] font-bold text-[#2E7D32] uppercase">Total Belanja</p>
                        <p id="dTotalBelanja" class="text-sm font-bold text-[#2E7D32] mt-0.5">Rp 0</p>
                    </div>
                    <div class="bg-stone-50 border border-stone-100 rounded-xl p-3 text-center">
                        <p class="text-[10px] font-bold text-stone-400 uppercase">Bergabung</p>
                        <p id="dJoin" class="text-xs font-bold text-stone-700 mt-0.5">-</p>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-bold text-stone-500 uppercase tracking-wider mb-2">Riwayat Transaksi</h4>
                    <div id="dRiwayat" class="space-y-2 max-h-64 overflow-y-auto pr-1"></div>
                </div>
            </div>

            <div class="p-4 border-t border-stone-100 flex justify-end">
                <button onclick="closeDetail()" class="px-5 py-2 text-sm font-semibold bg-stone-100 hover:bg-stone-200 text-stone-700 rounded-xl">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL RESET PASSWORD -->
    <div id="modalReset" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i data-lucide="key-round" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-stone-800">Reset Password</h3>
                        <p class="text-[11px] text-stone-500">Untuk <b id="rNama" class="text-stone-700">-</b></p>
                    </div>
                </div>
                <button onclick="closeReset()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div id="rKonfirmasi">
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex gap-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                        <div class="text-xs text-amber-800 leading-relaxed">
                            <p class="font-bold mb-1">Password akan di-reset</p>
                            <p>Sistem akan membuat password baru secara acak. Password lama tidak akan berlaku lagi. Pastikan kamu memberikan password baru ke pelanggan.</p>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4">
                        <button onclick="closeReset()" class="flex-1 px-4 py-2.5 text-sm font-semibold text-stone-600 bg-stone-100 hover:bg-stone-200 rounded-xl">Batal</button>
                        <button onclick="konfirmasiReset()" id="btnKonfirmasiReset" class="flex-1 px-4 py-2.5 text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-xl inline-flex items-center justify-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            Ya, Reset
                        </button>
                    </div>
                </div>

                <div id="rHasil" class="hidden space-y-3">
                    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex gap-3">
                        <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0 mt-0.5"></i>
                        <div class="text-xs text-emerald-800 leading-relaxed">
                            <p class="font-bold mb-1">Password berhasil di-reset!</p>
                            <p>Berikan password baru ini ke pelanggan. Password hanya ditampilkan sekali.</p>
                        </div>
                    </div>

                    <div class="bg-stone-50 border border-stone-200 rounded-xl p-4 space-y-3">
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Username</p>
                            <p id="rUsername" class="text-sm font-bold font-mono text-stone-800">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Password Baru</p>
                            <div class="flex items-center gap-2">
                                <p id="rPassword" class="text-base font-bold font-mono text-[#2E7D32] bg-white border border-emerald-200 px-3 py-2 rounded-lg flex-1 tracking-wider">-</p>
                                <button onclick="salinPassword()" class="p-2.5 bg-[#2E7D32] hover:bg-emerald-800 text-white rounded-lg transition" title="Salin">
                                    <i data-lucide="copy" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <p id="rCopied" class="text-[10px] text-[#2E7D32] font-semibold mt-1 hidden">✓ Tersalin ke clipboard</p>
                        </div>
                    </div>

                    <button onclick="closeReset()" class="w-full px-4 py-2.5 text-sm font-bold bg-[#2E7D32] hover:bg-emerald-800 text-white rounded-xl">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
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

        function formatRp(v) { return 'Rp ' + Number(v).toLocaleString('id-ID'); }

        function badgeStatus(status) {
            const map = {
                'Diproses': 'bg-amber-50 text-amber-700 border-amber-200',
                'Dikirim':  'bg-blue-50 text-blue-700 border-blue-200',
                'Selesai':  'bg-emerald-50 text-emerald-700 border-emerald-200',
                'Batal':    'bg-rose-50 text-rose-700 border-rose-200'
            };
            return map[status] || 'bg-stone-100 text-stone-600 border-stone-200';
        }

        // ========================================================
        // DETAIL PELANGGAN
        // ========================================================
        function openDetail(data) {
            document.getElementById('dAvatar').innerText = (data.nama || 'P').charAt(0).toUpperCase();
            document.getElementById('dNama').innerText = data.nama || '-';
            document.getElementById('dUsername').innerText = '@' + (data.username || '-');
            document.getElementById('dEmail').innerText = data.email || '-';
            document.getElementById('dNoHp').innerText = data.no_hp || '-';
            document.getElementById('dAlamat').innerText = data.alamat || '-';
            document.getElementById('dTotalOrder').innerText = data.total_order || 0;
            document.getElementById('dTotalBelanja').innerText = formatRp(data.total_belanja || 0);

            const join = data.created_at ? new Date(data.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
            document.getElementById('dJoin').innerText = join;

            const riwayat = data.transaksi || [];
            const container = document.getElementById('dRiwayat');
            if (riwayat.length === 0) {
                container.innerHTML = '<p class="text-xs text-stone-400 text-center py-4 italic">Belum ada transaksi.</p>';
            } else {
                container.innerHTML = riwayat.map(t => `
                    <div class="bg-white border border-stone-200 rounded-xl p-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold font-mono text-stone-800 truncate">${t.kode_transaksi}</p>
                            <p class="text-[10px] text-stone-400 mt-0.5">${new Date(t.created_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-bold text-[#2E7D32]">${formatRp(t.total_harga)}</p>
                            <span class="inline-block text-[9px] font-bold uppercase px-1.5 py-0.5 rounded border mt-0.5 ${badgeStatus(t.status)}">${t.status}</span>
                        </div>
                    </div>
                `).join('');
            }

            const modal = document.getElementById('modalDetail');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDetail() {
            const modal = document.getElementById('modalDetail');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('modalDetail').addEventListener('click', (e) => {
            if (e.target.id === 'modalDetail') closeDetail();
        });

        // ========================================================
        // RESET PASSWORD
        // ========================================================
        let resetUserId = null;
        let generatedPassword = '';

        function openResetPassword(userId, nama) {
            resetUserId = userId;
            generatedPassword = '';

            document.getElementById('rNama').innerText = nama;
            document.getElementById('rKonfirmasi').classList.remove('hidden');
            document.getElementById('rHasil').classList.add('hidden');
            document.getElementById('rPassword').innerText = '-';
            document.getElementById('rUsername').innerText = '-';
            document.getElementById('rCopied').classList.add('hidden');

            const modal = document.getElementById('modalReset');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeReset() {
            const modal = document.getElementById('modalReset');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            resetUserId = null;
            generatedPassword = '';
        }

        function konfirmasiReset() {
            if (!resetUserId) return;

            const btn = document.getElementById('btnKonfirmasiReset');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Memproses...';
            lucide.createIcons();

            const fd = new FormData();
            fd.append('action', 'reset_password');
            fd.append('user_id', resetUserId);

            fetch('pelanggan.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        generatedPassword = data.password;
                        document.getElementById('rUsername').innerText = data.username || '-';
                        document.getElementById('rPassword').innerText = data.password;
                        document.getElementById('rKonfirmasi').classList.add('hidden');
                        document.getElementById('rHasil').classList.remove('hidden');
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4"></i> Ya, Reset';
                    lucide.createIcons();
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4"></i> Ya, Reset';
                    lucide.createIcons();
                });
        }

        function salinPassword() {
            if (!generatedPassword) return;
            navigator.clipboard.writeText(generatedPassword).then(() => {
                document.getElementById('rCopied').classList.remove('hidden');
                setTimeout(() => document.getElementById('rCopied').classList.add('hidden'), 2000);
            }).catch(err => {
                alert('Gagal menyalin. Silakan copy manual: ' + generatedPassword);
            });
        }

        document.getElementById('modalReset').addEventListener('click', (e) => {
            if (e.target.id === 'modalReset') closeReset();
        });
    </script>
</body>
</html>