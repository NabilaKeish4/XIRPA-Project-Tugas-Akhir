<?php
require_once __DIR__ . '/customer_init.php';

$uid  = user_id();
$nama = nama_user();

// ---- Statistik (pesanan batal tidak dihitung) ----
$s = db_one("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(CASE WHEN status IN ('Diproses','Dikirim') THEN 1 ELSE 0 END),0) AS aktif,
           COALESCE(SUM(CASE WHEN status = 'Selesai' THEN total_harga ELSE 0 END),0) AS belanja
    FROM transaksi
    WHERE user_id = ? AND jenis_transaksi = 'penjualan' AND status <> 'Batal'
", 'i', [$uid]) ?: ['total' => 0, 'aktif' => 0, 'belanja' => 0];

$cartN     = cart_count();
$unread    = unread_chat_count($uid);
$wishCount = count(wishlist_ids($uid));

// ---- Pesanan terakhir + thumbnail item ----
$last = db_one("
    SELECT t.*,
           (SELECT COUNT(*) FROM transaksi_detail d WHERE d.transaksi_id = t.id) AS jml_item,
           (SELECT COALESCE(SUM(jumlah),0) FROM transaksi_detail d WHERE d.transaksi_id = t.id) AS total_qty
    FROM transaksi t
    WHERE t.user_id = ? AND t.jenis_transaksi = 'penjualan'
    ORDER BY t.created_at DESC, t.id DESC LIMIT 1
", 'i', [$uid]);
$lastItems = $last ? db_all("
    SELECT p.nama_tanaman, p.gambar, d.jumlah
    FROM transaksi_detail d JOIN produk p ON p.id = d.produk_id
    WHERE d.transaksi_id = ? LIMIT 4
", 'i', [(int)$last['id']]) : [];

// ---- Produk ----
$wish = wishlist_ids($uid);
$best = best_seller_ids();
$cols = "p.*, k.nama_kategori, (SELECT COALESCE(SUM(d.jumlah),0) FROM transaksi_detail d JOIN transaksi t ON t.id=d.transaksi_id WHERE d.produk_id=p.id AND t.status<>'Batal') AS total_terjual";

$terbaru = db_all("SELECT $cols FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id WHERE p.stok > 0 ORDER BY p.id DESC LIMIT 4");

$terlaris = [];
if ($best) {
    $ids = array_map('intval', array_keys($best));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $terlaris = db_all("SELECT $cols FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id WHERE p.stok > 0 AND p.id IN ($in) LIMIT 4", str_repeat('i', count($ids)), $ids);
}

$kategori = db_all("
    SELECT k.id, k.nama_kategori, COUNT(p.id) AS jml
    FROM kategori k LEFT JOIN produk p ON p.kategori_id = k.id AND p.stok > 0
    GROUP BY k.id, k.nama_kategori ORDER BY jml DESC, k.nama_kategori LIMIT 8
");

// ---- Salam ----
$jam = (int)date('H');
$salam = $jam < 11 ? 'Selamat Pagi' : ($jam < 15 ? 'Selamat Siang' : ($jam < 19 ? 'Selamat Sore' : 'Selamat Malam'));

$tips = [
    ['sun', 'Cahaya cukup', 'Kebanyakan tanaman hias suka cahaya terang tidak langsung. Hindari terik siang hari.'],
    ['droplets', 'Siram secukupnya', 'Cek kelembapan tanah dengan jari dulu. Daun layu bisa jadi tanda terlalu basah, bukan kurang air.'],
    ['sprout', 'Ganti media tanam', 'Ganti media dan pot setiap 6–12 bulan agar akar tidak sesak dan nutrisi tetap terjaga.'],
    ['bug', 'Cek hama rutin', 'Periksa bagian bawah daun seminggu sekali. Hama ditangani lebih cepat bila ketahuan dini.'],
];
$tip = $tips[(int)date('z') % count($tips)];

layout_start(['title' => 'Dashboard', 'active' => 'dashboard', 'width' => 'max-w-6xl']);
?>

<!-- WELCOME -->
<div class="fade-up">
    <h1 class="text-2xl font-bold text-stone-800 tracking-tight"><?= $salam ?>, <?= e($nama) ?> 👋</h1>
    <p class="text-sm text-stone-500 mt-0.5">Ringkasan aktivitas belanja dan pesanan Anda di PlantHub. <span class="text-stone-400">· <?= hari_lengkap(time()) ?></span></p>
</div>

<!-- BANNER -->
<?php if ($cartN > 0): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 fade-up">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-amber-100 text-[#D97706] flex items-center justify-center shrink-0"><i data-lucide="shopping-bag" class="w-5 h-5"></i></div>
        <div>
            <p class="text-sm font-bold text-amber-900">Keranjangmu menunggu!</p>
            <p class="text-xs text-amber-700">Ada <b><?= $cartN ?> item</b> yang belum di-checkout.</p>
        </div>
    </div>
    <a href="cart.php" class="inline-flex items-center gap-2 bg-[#D97706] hover:bg-amber-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition shrink-0">Lihat Keranjang <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
</div>
<?php endif; ?>
<?php if ($unread > 0): ?>
<div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 fade-up">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i data-lucide="message-square" class="w-5 h-5"></i></div>
        <div>
            <p class="text-sm font-bold text-blue-900">Admin membalas chat kamu!</p>
            <p class="text-xs text-blue-700"><b><?= $unread ?></b> pesan belum dibaca.</p>
        </div>
    </div>
    <a href="chat.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition shrink-0">Buka Chat <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
</div>
<?php endif; ?>

<!-- STAT -->
<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <?php
    $stats = [
        ['cart.php',                       'Keranjang',     $cartN . ' Item',                   'shopping-bag', 'border-t-[#2E7D32]', 'bg-emerald-50 text-[#2E7D32]'],
        ['riwayat.php?status=Diproses',    'Pesanan Aktif', (int)$s['aktif'] . ' Pesanan',      'truck',        'border-t-[#D97706]', 'bg-amber-50 text-[#D97706]'],
        ['wishlist.php',                   'Wishlist',      $wishCount . ' Tanaman',            'heart',        'border-t-rose-500',  'bg-rose-50 text-rose-500'],
        ['riwayat.php',                    'Total Belanja', rupiah($s['belanja']),              'wallet',       'border-t-[#2E7D32]', 'bg-emerald-50 text-[#2E7D32]'],
    ];
    foreach ($stats as [$href, $label, $val, $icon, $border, $ic]): ?>
    <a href="<?= $href ?>" class="bg-white rounded-2xl p-4 sm:p-5 border border-stone-200/80 shadow-sm border-t-4 <?= $border ?> hover:shadow-md hover:-translate-y-0.5 transition fade-up">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500"><?= $label ?></p>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center <?= $ic ?>"><i data-lucide="<?= $icon ?>" class="w-4 h-4"></i></div>
        </div>
        <p class="text-lg sm:text-xl font-bold text-stone-800 tracking-tight truncate"><?= e($val) ?></p>
    </a>
    <?php endforeach; ?>
</section>

<!-- PESANAN TERAKHIR -->
<?php if ($last): ?>
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5 sm:p-6 fade-up">
    <?= ui_section_head('Pesanan Terakhir', 'Status terkini pesanan terbaru kamu', 'riwayat.php') ?>
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
        <div class="space-y-3 flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <p class="font-mono text-sm font-bold"><?= e($last['kode_transaksi']) ?></p>
                <?= ui_status_badge($last['status']) ?>
            </div>
            <p class="text-xs text-stone-500"><?= tgl_id($last['created_at'], true) ?> · <?= (int)$last['jml_item'] ?> produk · <?= (int)$last['total_qty'] ?> item</p>
            <div class="flex items-center gap-2">
                <?php foreach ($lastItems as $it): ?>
                    <img src="<?= e(produk_img($it['gambar'], 120)) ?>" title="<?= e($it['nama_tanaman']) ?> ×<?= (int)$it['jumlah'] ?>" class="w-12 h-12 rounded-xl object-cover border border-stone-200">
                <?php endforeach; ?>
                <?php if ((int)$last['jml_item'] > 4): ?><span class="text-xs font-semibold text-stone-400">+<?= (int)$last['jml_item'] - 4 ?></span><?php endif; ?>
            </div>
            <?= ui_order_tracker($last['status']) ?>
        </div>
        <div class="lg:border-l lg:border-stone-100 lg:pl-6 flex lg:flex-col items-center lg:items-end justify-between gap-3 shrink-0">
            <div class="lg:text-right">
                <p class="text-[10px] font-bold uppercase text-stone-400 tracking-wider">Total Bayar</p>
                <p class="text-xl font-bold text-[#2E7D32]"><?= rupiah($last['total_harga']) ?></p>
            </div>
            <div class="flex gap-2">
                <a href="note.php?id=<?= (int)$last['id'] ?>" class="inline-flex items-center gap-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold px-3.5 py-2.5 rounded-xl text-xs transition"><i data-lucide="receipt" class="w-3.5 h-3.5"></i> Nota</a>
                <a href="riwayat.php" class="inline-flex items-center gap-1.5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-semibold px-3.5 py-2.5 rounded-xl text-xs transition"><i data-lucide="search" class="w-3.5 h-3.5"></i> Lacak</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- KATEGORI -->
<?php if ($kategori): ?>
<section>
    <?= ui_section_head('Belanja per Kategori', 'Pilih jenis tanaman yang kamu cari') ?>
    <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
        <?php foreach ($kategori as $k): ?>
            <a href="katalog.php?kategori=<?= (int)$k['id'] ?>" class="shrink-0 bg-white border border-stone-200 hover:border-[#2E7D32] hover:bg-emerald-50 hover:text-[#2E7D32] rounded-xl px-4 py-2.5 text-sm font-semibold text-stone-700 transition">
                <?= e($k['nama_kategori']) ?> <span class="text-[11px] text-stone-400 font-medium">(<?= (int)$k['jml'] ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- TERLARIS -->
<?php if ($terlaris): ?>
<section>
    <?= ui_section_head('🔥 Paling Laris', 'Favorit pelanggan PlantHub', 'katalog.php?sort=terlaris') ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($terlaris as $p) echo ui_product_card($p, $wish, $best); ?>
    </div>
</section>
<?php endif; ?>

<!-- TERBARU -->
<section>
    <?= ui_section_head('Produk Terbaru', 'Koleksi tanaman terbaru di katalog', 'katalog.php?sort=terbaru') ?>
    <?php if (!$terbaru): ?>
        <?= ui_empty('sprout', 'Belum ada produk', 'Produk akan muncul di sini begitu admin menambahkannya.') ?>
    <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($terbaru as $p) echo ui_product_card($p, $wish, $best); ?>
        </div>
    <?php endif; ?>
</section>

<!-- TIPS -->
<section class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 flex items-start gap-4 fade-up">
    <div class="w-11 h-11 rounded-xl bg-white text-[#2E7D32] flex items-center justify-center shrink-0 shadow-sm"><i data-lucide="<?= $tip[0] ?>" class="w-5 h-5"></i></div>
    <div>
        <p class="text-[10px] font-bold uppercase tracking-widest text-[#2E7D32]">Tips Perawatan Hari Ini</p>
        <p class="text-sm font-bold text-stone-800 mt-0.5"><?= e($tip[1]) ?></p>
        <p class="text-xs text-stone-600 mt-1"><?= e($tip[2]) ?></p>
    </div>
</section>

<?php layout_end(); ?>
