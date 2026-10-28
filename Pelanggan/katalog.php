<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();

// ---- Parameter ----
$search   = trim((string)($_GET['search'] ?? ''));
$kategori = (int)($_GET['kategori'] ?? 0);
$sortBy   = $_GET['sort'] ?? 'terbaru';
$hargaMin = (isset($_GET['harga_min']) && $_GET['harga_min'] !== '') ? max(0, (float)$_GET['harga_min']) : null;
$hargaMax = (isset($_GET['harga_max']) && $_GET['harga_max'] !== '') ? max(0, (float)$_GET['harga_max']) : null;
$ready    = ($_GET['ready'] ?? '') === '1';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;

$sorts = [
    'terbaru'    => 'Terbaru',
    'terlaris'   => 'Terlaris',
    'harga_asc'  => 'Harga Terendah',
    'harga_desc' => 'Harga Tertinggi',
    'nama_asc'   => 'Nama A-Z',
];
if (!isset($sorts[$sortBy])) $sortBy = 'terbaru';

// ---- Query (prepared) ----
$where = ['1=1']; $types = ''; $params = [];
if ($search !== '')     { $where[] = 'p.nama_tanaman LIKE ?'; $types .= 's'; $params[] = '%' . addcslashes($search, '%_\\') . '%'; }
if ($kategori > 0)      { $where[] = 'p.kategori_id = ?';     $types .= 'i'; $params[] = $kategori; }
if ($hargaMin !== null) { $where[] = 'p.harga_jual >= ?';     $types .= 'd'; $params[] = $hargaMin; }
if ($hargaMax !== null) { $where[] = 'p.harga_jual <= ?';     $types .= 'd'; $params[] = $hargaMax; }
if ($ready)             { $where[] = 'p.stok > 0'; }
$whereSql = implode(' AND ', $where);

$terjualSql = "COALESCE((SELECT SUM(td.jumlah) FROM transaksi_detail td JOIN transaksi t ON td.transaksi_id = t.id
                WHERE td.produk_id = p.id AND t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'),0)";
$orderBy = [
    'terbaru'    => 'p.stok > 0 DESC, p.id DESC',
    'terlaris'   => 'total_terjual DESC, p.id DESC',
    'harga_asc'  => 'p.harga_jual ASC',
    'harga_desc' => 'p.harga_jual DESC',
    'nama_asc'   => 'p.nama_tanaman ASC',
][$sortBy];

$totalProduk = (int)db_val("SELECT COUNT(*) FROM produk p WHERE $whereSql", $types, $params, 0);
$pages  = max(1, (int)ceil($totalProduk / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$produk = db_all("
    SELECT p.*, k.nama_kategori, $terjualSql AS total_terjual
    FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id
    WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset
", $types, $params);

$kategoriList = db_all("
    SELECT k.id, k.nama_kategori, COUNT(p.id) AS jml
    FROM kategori k LEFT JOIN produk p ON p.kategori_id = k.id
    GROUP BY k.id, k.nama_kategori ORDER BY k.id
");
$wish = wishlist_ids($uid);
$best = best_seller_ids();

$filterAktif = ($search !== '' || $kategori > 0 || $hargaMin !== null || $hargaMax !== null || $ready);
$namaKat = '';
foreach ($kategoriList as $k) if ((int)$k['id'] === $kategori) $namaKat = $k['nama_kategori'];

layout_start(['title' => 'Katalog Shop', 'active' => 'katalog', 'width' => 'max-w-7xl']);
?>

<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2 fade-up">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Katalog Produk</h1>
        <p class="text-sm text-stone-500 mt-0.5">
            <b class="text-stone-700"><?= $totalProduk ?></b> produk <?= $filterAktif ? 'sesuai filter' : 'tersedia di katalog' ?>
            <?php if ($pages > 1): ?> · halaman <?= $page ?>/<?= $pages ?><?php endif; ?>
        </p>
    </div>
</div>

<!-- FILTER -->
<div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm space-y-3 fade-up">
    <form method="get" class="grid grid-cols-2 md:grid-cols-12 gap-3">
        <div class="col-span-2 md:col-span-5 relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari nama tanaman…"
                   class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100">
        </div>
        <input type="number" name="harga_min" value="<?= $hargaMin !== null ? (int)$hargaMin : '' ?>" placeholder="Harga min" min="0" step="1000"
               class="md:col-span-2 px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
        <input type="number" name="harga_max" value="<?= $hargaMax !== null ? (int)$hargaMax : '' ?>" placeholder="Harga max" min="0" step="1000"
               class="md:col-span-2 px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
        <div class="col-span-2 md:col-span-3 flex gap-2">
            <button class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition inline-flex items-center justify-center gap-1.5"><i data-lucide="search" class="w-4 h-4"></i> Cari</button>
            <?php if ($filterAktif): ?><a href="katalog.php" class="px-3 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl inline-flex items-center" title="Reset"><i data-lucide="x" class="w-4 h-4"></i></a><?php endif; ?>
        </div>
        <?php if ($kategori > 0): ?><input type="hidden" name="kategori" value="<?= $kategori ?>"><?php endif; ?>
        <?php if ($sortBy !== 'terbaru'): ?><input type="hidden" name="sort" value="<?= e($sortBy) ?>"><?php endif; ?>
        <?php if ($ready): ?><input type="hidden" name="ready" value="1"><?php endif; ?>
    </form>

    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
        <a href="<?= e(url_with(['kategori' => null])) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori === 0 ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Semua</a>
        <?php foreach ($kategoriList as $k): $on = $kategori === (int)$k['id']; ?>
            <a href="<?= e(url_with(['kategori' => (int)$k['id']])) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $on ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                <?= e($k['nama_kategori']) ?> <span class="opacity-60">(<?= (int)$k['jml'] ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-3 border-t border-stone-100">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1 flex-1">
            <span class="text-[11px] font-bold text-stone-400 uppercase tracking-wider whitespace-nowrap">Urutkan</span>
            <?php foreach ($sorts as $key => $label): ?>
                <a href="<?= e(url_with(['sort' => $key === 'terbaru' ? null : $key])) ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $sortBy === $key ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <a href="<?= e(url_with(['ready' => $ready ? null : '1'])) ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap border transition <?= $ready ? 'bg-emerald-50 text-[#2E7D32] border-emerald-200' : 'bg-white text-stone-600 border-stone-200 hover:bg-stone-50' ?>">
            <span class="w-4 h-4 rounded border-2 flex items-center justify-center <?= $ready ? 'bg-[#2E7D32] border-[#2E7D32]' : 'border-stone-300' ?>"><?php if ($ready): ?><i data-lucide="check" class="w-3 h-3 text-white"></i><?php endif; ?></span>
            Hanya yang tersedia
        </a>
    </div>

    <?php if ($filterAktif): ?>
    <div class="pt-3 border-t border-stone-100 flex items-center flex-wrap gap-2 text-xs">
        <span class="text-stone-500">Filter aktif:</span>
        <?php if ($search !== ''): ?><span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 rounded-md"><i data-lucide="search" class="w-3 h-3"></i>"<?= e($search) ?>"</span><?php endif; ?>
        <?php if ($namaKat !== ''): ?><span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 rounded-md"><i data-lucide="tag" class="w-3 h-3"></i><?= e($namaKat) ?></span><?php endif; ?>
        <?php if ($hargaMin !== null || $hargaMax !== null): ?><span class="inline-flex items-center gap-1 px-2 py-1 bg-stone-100 rounded-md"><i data-lucide="wallet" class="w-3 h-3"></i><?= rupiah($hargaMin ?? 0) ?> – <?= $hargaMax !== null ? rupiah($hargaMax) : '∞' ?></span><?php endif; ?>
        <?php if ($ready): ?><span class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-50 text-[#2E7D32] rounded-md"><i data-lucide="check-circle" class="w-3 h-3"></i>Tersedia</span><?php endif; ?>
        <a href="katalog.php" class="text-[#2E7D32] font-bold hover:underline ml-auto">Reset semua</a>
    </div>
    <?php endif; ?>
</div>

<!-- HASIL -->
<?php if (!$produk): ?>
    <?= ui_empty('package-search', 'Produk tidak ditemukan', 'Coba ubah kata kunci atau reset filter.', 'katalog.php', 'Reset pencarian', 'rotate-ccw') ?>
<?php else: ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <?php foreach ($produk as $p) echo ui_product_card($p, $wish, $best); ?>
    </div>
    <?= ui_pagination($page, $pages) ?>
<?php endif; ?>

<?php layout_end(); ?>
