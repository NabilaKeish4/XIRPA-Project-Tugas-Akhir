<?php
session_start();
require_once '../Config/database.php';

// PROTEKSI LOGIN PELANGGAN
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$nama_user  = $_SESSION['nama_user'] ?? 'Pelanggan';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// =========================================================
// PARAMETER FILTER & SORTING
// =========================================================
$search     = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$kategori   = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$sortBy     = $_GET['sort'] ?? 'terbaru';
$hargaMin   = isset($_GET['harga_min']) && $_GET['harga_min'] !== '' ? (float)$_GET['harga_min'] : null;
$hargaMax   = isset($_GET['harga_max']) && $_GET['harga_max'] !== '' ? (float)$_GET['harga_max'] : null;
$hanyaReady = isset($_GET['ready']) && $_GET['ready'] === '1';

$allowedSort = ['terbaru', 'harga_asc', 'harga_desc', 'nama_asc', 'terlaris'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'terbaru';

// =========================================================
// BUILD QUERY
// =========================================================
$where = "WHERE 1=1";

if ($search !== '')  $where .= " AND p.nama_tanaman LIKE '%$search%'";
if ($kategori > 0)   $where .= " AND p.kategori_id = $kategori";
if ($hargaMin !== null) $where .= " AND p.harga_jual >= $hargaMin";
if ($hargaMax !== null) $where .= " AND p.harga_jual <= $hargaMax";
if ($hanyaReady)     $where .= " AND p.stok > 0";

// Sorting
switch ($sortBy) {
    case 'harga_asc':  $orderBy = 'p.harga_jual ASC'; break;
    case 'harga_desc': $orderBy = 'p.harga_jual DESC'; break;
    case 'nama_asc':   $orderBy = 'p.nama_tanaman ASC'; break;
    case 'terlaris':
        // Subquery total terjual
        $orderBy = '(SELECT COALESCE(SUM(td.jumlah), 0) FROM transaksi_detail td 
                     JOIN transaksi t ON td.transaksi_id = t.id 
                     WHERE td.produk_id = p.id AND t.status = "Selesai" AND t.jenis_transaksi = "penjualan") DESC, p.id DESC';
        break;
    default:           $orderBy = 'p.stok DESC, p.id DESC';
}

// Ambil produk
$produkList = [];
$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori,
           COALESCE((SELECT SUM(td.jumlah) FROM transaksi_detail td 
                     JOIN transaksi t ON td.transaksi_id = t.id 
                     WHERE td.produk_id = p.id AND t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'), 0) AS total_terjual
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    $where
    ORDER BY $orderBy
");
if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produkList[] = $r;

$totalProduk = count($produkList);

// Ambil kategori
$kategoriList = [];
$qKat = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id ASC");
if ($qKat) while ($r = mysqli_fetch_assoc($qKat)) $kategoriList[] = $r;

// Cari best seller untuk badge (limit 5 terlaris)
$bestSellers = [];
$qBest = mysqli_query($conn, "
    SELECT td.produk_id, SUM(td.jumlah) AS total 
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    WHERE t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'
    GROUP BY td.produk_id
    HAVING total >= 3
    ORDER BY total DESC
    LIMIT 5
");
if ($qBest) while ($r = mysqli_fetch_assoc($qBest)) $bestSellers[(int)$r['produk_id']] = (int)$r['total'];

function gambarKatalog($nama) {
    if (!$nama || $nama === 'default.jpg') {
        return "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400";
    }
    if (file_exists("../assets/img/" . $nama)) return "../assets/img/" . $nama;
    return "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400";
}

// Helper URL
function buildKatalogUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return 'katalog.php?' . http_build_query($params);
}

// Ada filter aktif?
$adaFilterAktif = ($search !== '' || $kategori > 0 || $hargaMin !== null || $hargaMax !== null || $hanyaReady);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Katalog Shop</title>
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

            <form method="GET" action="katalog.php" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
                <?php if ($kategori > 0): ?><input type="hidden" name="kategori" value="<?= $kategori ?>"><?php endif; ?>
                <?php if ($sortBy !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
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
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => true],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => false],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => false],
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

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Katalog Produk</h1>
                    <p class="text-sm text-stone-500 mt-0.5">
                        Menampilkan <b class="text-stone-700"><?= $totalProduk ?></b> produk
                        <?= $adaFilterAktif ? 'dari filter aktif' : 'di katalog' ?>
                    </p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm space-y-3">

                <!-- Search + Filter Harga (mobile friendly) -->
                <form method="GET" action="katalog.php" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-5 relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama tanaman..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div class="md:col-span-2">
                        <input type="number" name="harga_min" value="<?= $hargaMin !== null ? (int)$hargaMin : '' ?>" placeholder="Harga min" min="0" step="1000" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>
                    <div class="md:col-span-2">
                        <input type="number" name="harga_max" value="<?= $hargaMax !== null ? (int)$hargaMax : '' ?>" placeholder="Harga max" min="0" step="1000" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div class="md:col-span-3 flex gap-2">
                        <button type="submit" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition inline-flex items-center justify-center gap-1.5">
                            <i data-lucide="search" class="w-4 h-4"></i> Cari
                        </button>
                        <?php if ($adaFilterAktif): ?>
                            <a href="katalog.php" class="px-3 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl text-sm font-semibold transition inline-flex items-center justify-center" title="Reset">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($kategori > 0): ?><input type="hidden" name="kategori" value="<?= $kategori ?>"><?php endif; ?>
                    <?php if ($sortBy !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
                    <?php if ($hanyaReady): ?><input type="hidden" name="ready" value="1"><?php endif; ?>
                </form>

                <!-- Kategori Chips -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <a href="<?= buildKatalogUrl(['kategori' => null, 'search' => $search ?: null]) ?>" 
                       class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori === 0 ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Semua Kategori
                    </a>
                    <?php foreach ($kategoriList as $k): ?>
                        <a href="<?= buildKatalogUrl(['kategori' => (int)$k['id']]) ?>"
                           class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori === (int)$k['id'] ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                            <?= htmlspecialchars($k['nama_kategori']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Sorting + Filter Ready -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-3 border-t border-stone-100">
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 flex-1">
                        <span class="text-xs font-bold text-stone-400 uppercase tracking-wider whitespace-nowrap">Urutkan:</span>
                        <?php
                        $sortOptions = [
                            'terbaru'    => 'Terbaru',
                            'terlaris'   => 'Terlaris',
                            'harga_asc'  => 'Harga Terendah',
                            'harga_desc' => 'Harga Tertinggi',
                            'nama_asc'   => 'Nama A-Z',
                        ];
                        foreach ($sortOptions as $key => $label):
                        ?>
                            <a href="<?= buildKatalogUrl(['sort' => $key]) ?>"
                               class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $sortBy === $key ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?= buildKatalogUrl(['ready' => $hanyaReady ? null : '1']) ?>"
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition border <?= $hanyaReady ? 'bg-emerald-50 text-[#2E7D32] border-emerald-200' : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50' ?>">
                        <span class="w-4 h-4 rounded border-2 flex items-center justify-center <?= $hanyaReady ? 'bg-[#2E7D32] border-[#2E7D32]' : 'border-stone-300' ?>">
                            <?php if ($hanyaReady): ?>
                                <i data-lucide="check" class="w-3 h-3 text-white"></i>
                            <?php endif; ?>
                        </span>
                        Hanya yang tersedia
                    </a>
                </div>

                <!-- Info filter aktif -->
                <?php if ($adaFilterAktif): ?>
                    <div class="pt-3 border-t border-stone-100 flex items-center flex-wrap gap-2 text-xs">
                        <span class="text-stone-500">Filter aktif:</span>
                        <?php if ($search): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 text-stone-700 rounded-md">
                                <i data-lucide="search" class="w-3 h-3"></i>
                                "<?= htmlspecialchars($search) ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($kategori > 0): 
                            $namaKatAktif = 'Kategori';
                            foreach ($kategoriList as $kk) if ((int)$kk['id'] === $kategori) $namaKatAktif = $kk['nama_kategori'];
                        ?>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 text-stone-700 rounded-md">
                                <i data-lucide="tag" class="w-3 h-3"></i>
                                <?= htmlspecialchars($namaKatAktif) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($hargaMin !== null || $hargaMax !== null): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 text-stone-700 rounded-md">
                                <i data-lucide="wallet" class="w-3 h-3"></i>
                                Rp <?= number_format($hargaMin ?? 0, 0, ',', '.') ?> - Rp <?= $hargaMax ? number_format($hargaMax, 0, ',', '.') : '∞' ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($hanyaReady): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-50 text-[#2E7D32] rounded-md">
                                <i data-lucide="check-circle" class="w-3 h-3"></i>
                                Tersedia
                            </span>
                        <?php endif; ?>
                        <a href="katalog.php" class="text-[#2E7D32] font-bold hover:underline ml-auto">Reset semua</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($produkList)): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 p-12 text-center">
                    <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="package-search" class="w-6 h-6 text-stone-400"></i>
                    </div>
                    <p class="text-sm font-semibold text-stone-700">Produk tidak ditemukan</p>
                    <p class="text-xs text-stone-500 mt-1">Coba ubah kata kunci atau reset filter.</p>
                    <a href="katalog.php" class="inline-block mt-4 text-xs font-semibold text-[#2E7D32] hover:underline">Reset pencarian</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach ($produkList as $p):
                        $imgSrc = gambarKatalog($p['gambar']);
                        $stok = (int)$p['stok'];
                        $isHabis = $stok <= 0;
                        $isKritis = $stok > 0 && $stok <= (int)$p['stok_minimal'];
                        $isBestSeller = isset($bestSellers[(int)$p['id']]);
                    ?>
                        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                            <div class="relative bg-stone-100 h-44 overflow-hidden">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($p['nama_tanaman']) ?>" class="w-full h-full object-cover">

                                <!-- Badge kiri atas: status stok -->
                                <?php if ($isHabis): ?>
                                    <span class="absolute top-2.5 left-2.5 bg-rose-600 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase">Stok Habis</span>
                                <?php elseif ($isKritis): ?>
                                    <span class="absolute top-2.5 left-2.5 bg-[#D97706] text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase">Stok Terbatas</span>
                                <?php endif; ?>

                                <!-- Badge kanan atas: terlaris -->
                                <?php if ($isBestSeller): ?>
                                    <span class="absolute top-2.5 right-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase inline-flex items-center gap-1 shadow-sm">
                                        <i data-lucide="flame" class="w-3 h-3"></i>
                                        Terlaris
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 flex-1 flex flex-col">
                                <p class="text-[10px] font-semibold uppercase text-stone-400 tracking-wider"><?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?></p>
                                <h3 class="text-sm font-bold text-stone-800 mt-0.5 leading-snug line-clamp-2 min-h-[2.5rem]"><?= htmlspecialchars($p['nama_tanaman']) ?></h3>
                                <p class="text-base font-bold text-[#2E7D32] mt-2">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>

                                <div class="mt-1 flex items-center justify-between text-[11px]">
                                    <span class="text-stone-500">Stok: <?= $stok ?> unit</span>
                                    <?php if ((int)$p['total_terjual'] > 0): ?>
                                        <span class="text-stone-400 inline-flex items-center gap-1">
                                            <i data-lucide="shopping-bag" class="w-3 h-3"></i>
                                            <?= (int)$p['total_terjual'] ?> terjual
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3 pt-3 border-t border-stone-100 grid grid-cols-2 gap-2">
                                    <a href="detail.php?id=<?= (int)$p['id'] ?>" class="text-center text-xs font-semibold text-stone-700 bg-stone-100 hover:bg-stone-200 py-2 rounded-lg transition">
                                        Detail
                                    </a>
                                    <?php if ($isHabis): ?>
                                        <button disabled class="text-center text-xs font-semibold text-stone-400 bg-stone-100 py-2 rounded-lg cursor-not-allowed">
                                            Habis
                                        </button>
                                    <?php else: ?>
                                        <a href="cart.php?action=add&id=<?= (int)$p['id'] ?>" class="text-center text-xs font-semibold text-white bg-[#2E7D32] hover:bg-emerald-800 py-2 rounded-lg transition">
                                            + Keranjang
                                        </a>
                                    <?php endif; ?>
                                </div>
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