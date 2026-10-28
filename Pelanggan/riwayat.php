<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();

// =====================================================
// AKSI: BATALKAN & BELI LAGI (POST + CSRF)
// =====================================================
if (is_post()) {
    csrf_require('riwayat.php');
    $action = $_POST['action'] ?? '';
    $trx    = (int)($_POST['trx_id'] ?? 0);
    $back   = 'riwayat.php';

    if ($action === 'batalkan' && $trx > 0) {
        try {
            db_begin();
            $o = db_one("SELECT id, status FROM transaksi WHERE id = ? AND user_id = ? AND jenis_transaksi = 'penjualan' FOR UPDATE", 'ii', [$trx, $uid]);
            if (!$o) throw new UserError('Pesanan tidak ditemukan.');
            if ($o['status'] !== 'Diproses') throw new UserError("Hanya pesanan berstatus 'Diproses' yang bisa dibatalkan.");

            foreach (db_all('SELECT produk_id, jumlah FROM transaksi_detail WHERE transaksi_id = ?', 'i', [$trx]) as $d) {
                db_exec('UPDATE produk SET stok = stok + ? WHERE id = ?', 'ii', [(int)$d['jumlah'], (int)$d['produk_id']]);
            }
            db_exec("UPDATE transaksi SET status = 'Batal' WHERE id = ? AND status = 'Diproses'", 'i', [$trx]);
            db_commit();
            flash('success', 'Pesanan berhasil dibatalkan. Stok produk telah dikembalikan.');
        } catch (UserError $e) {
            db_rollback(); flash('error', $e->getMessage());
        } catch (Throwable $e) {
            db_rollback(); error_log('BATAL GAGAL: ' . $e->getMessage()); flash('error', 'Gagal membatalkan pesanan, coba lagi.');
        }
        redirect($back);
    }

    if ($action === 'beli_lagi' && $trx > 0) {
        $own = db_val("SELECT COUNT(*) FROM transaksi WHERE id = ? AND user_id = ? AND jenis_transaksi = 'penjualan'", 'ii', [$trx, $uid], 0);
        if (!$own) { flash('error', 'Pesanan tidak ditemukan.'); redirect($back); }

        $added = 0; $skipped = [];
        $rows = db_all("SELECT td.produk_id, td.jumlah, p.nama_tanaman, p.stok FROM transaksi_detail td LEFT JOIN produk p ON p.id = td.produk_id WHERE td.transaksi_id = ?", 'i', [$trx]);
        foreach ($rows as $d) {
            $pid = (int)$d['produk_id']; $qty = (int)$d['jumlah']; $stok = (int)($d['stok'] ?? 0);
            $nama = $d['nama_tanaman'] ?? 'Produk';
            if ($pid <= 0 || $d['nama_tanaman'] === null) { $skipped[] = 'Produk sudah tidak tersedia'; continue; }
            if ($stok <= 0) { $skipped[] = "$nama (habis)"; continue; }
            $cart = cart_get();
            $now  = $cart[$pid] ?? 0;
            $new  = min($now + $qty, $stok);
            if ($new < $now + $qty) $skipped[] = "$nama (stok terbatas, jadi $new)";
            $_SESSION['cart'][$pid] = $new;
            $added++;
        }
        if ($added > 0) flash('success', "$added produk dimasukkan ke keranjang.");
        if ($skipped)   flash('warning', 'Catatan: ' . implode('; ', $skipped));
        if ($added === 0) { flash('error', 'Tidak ada produk yang bisa dipesan ulang.'); redirect($back); }
        redirect('cart.php');
    }
    redirect($back);
}

// =====================================================
// FILTER
// =====================================================
$allowed = ['semua', 'Diproses', 'Dikirim', 'Selesai', 'Batal'];
$statusFilter = $_GET['status_filter'] ?? ($_GET['status'] ?? 'semua');
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'semua';

$search = trim((string)($_GET['search'] ?? ''));
$range  = $_GET['range'] ?? 'all';
if (!in_array($range, ['all', 'today', '7days', '30days', 'custom'], true)) $range = 'all';

$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$tglMulai = $tglSelesai = null;
if ($range === 'today')      { $tglMulai = $tglSelesai = date('Y-m-d'); }
elseif ($range === '7days')  { $tglMulai = date('Y-m-d', strtotime('-7 days'));  $tglSelesai = date('Y-m-d'); }
elseif ($range === '30days') { $tglMulai = date('Y-m-d', strtotime('-30 days')); $tglSelesai = date('Y-m-d'); }
elseif ($range === 'custom') {
    $m = $_GET['tgl_mulai'] ?? ''; $s = $_GET['tgl_selesai'] ?? '';
    if ($isDate($m) && $isDate($s)) { $tglMulai = min($m, $s); $tglSelesai = max($m, $s); }
    else $range = 'all';
}

$where = ["t.user_id = ?", "t.jenis_transaksi = 'penjualan'"]; $types = 'i'; $params = [$uid];
if ($statusFilter !== 'semua') { $where[] = 't.status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($search !== '') { $where[] = 't.kode_transaksi LIKE ?'; $types .= 's'; $params[] = '%' . addcslashes($search, '%_\\') . '%'; }
if ($tglMulai !== null) { $where[] = 'DATE(t.created_at) BETWEEN ? AND ?'; $types .= 'ss'; $params[] = $tglMulai; $params[] = $tglSelesai; }
$whereSql = implode(' AND ', $where);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$totalRows = (int)db_val("SELECT COUNT(*) FROM transaksi t WHERE $whereSql", $types, $params, 0);
$pages  = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$orders = db_all("
    SELECT t.*,
           (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item,
           (SELECT COALESCE(SUM(jumlah),0) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS total_qty
    FROM transaksi t WHERE $whereSql
    ORDER BY t.created_at DESC, t.id DESC LIMIT $perPage OFFSET $offset
", $types, $params);

// thumbnail item untuk order di halaman ini
$thumbs = [];
if ($orders) {
    $ids = array_map(fn($o) => (int)$o['id'], $orders);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT td.transaksi_id, td.jumlah, p.nama_tanaman, p.gambar FROM transaksi_detail td LEFT JOIN produk p ON p.id = td.produk_id WHERE td.transaksi_id IN ($ph) ORDER BY td.id", str_repeat('i', count($ids)), $ids) as $r)
        $thumbs[(int)$r['transaksi_id']][] = $r;
}

// statistik (pesanan batal tidak dihitung ke belanja)
$st = db_one("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(status IN ('Diproses','Dikirim')),0) AS aktif,
           COALESCE(SUM(status = 'Selesai'),0) AS selesai,
           COALESCE(SUM(status = 'Batal'),0) AS batal,
           COALESCE(SUM(CASE WHEN status <> 'Batal' THEN total_harga ELSE 0 END),0) AS belanja
    FROM transaksi WHERE user_id = ? AND jenis_transaksi = 'penjualan'", 'i', [$uid]) ?: ['total'=>0,'aktif'=>0,'selesai'=>0,'batal'=>0,'belanja'=>0];

$tabCount = ['semua' => (int)$st['total'], 'Diproses' => 0, 'Dikirim' => 0, 'Selesai' => (int)$st['selesai'], 'Batal' => (int)$st['batal']];
foreach (db_all("SELECT status, COUNT(*) c FROM transaksi WHERE user_id = ? AND jenis_transaksi='penjualan' GROUP BY status", 'i', [$uid]) as $r) $tabCount[$r['status']] = (int)$r['c'];

$success = isset($_GET['success']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_GET['kode'] ?? '')) : null;
$filterAktif = ($search !== '' || $range !== 'all');

$tabs = ['semua' => 'Semua', 'Diproses' => 'Diproses', 'Dikirim' => 'Dikirim', 'Selesai' => 'Selesai', 'Batal' => 'Batal'];
$quick = ['today' => 'Hari Ini', '7days' => '7 Hari', '30days' => '30 Hari', 'all' => 'Semua Waktu'];

layout_start(['title' => 'Riwayat Order', 'active' => 'riwayat', 'width' => 'max-w-6xl']);
?>

<div class="fade-up">
    <h1 class="text-2xl font-bold tracking-tight">Riwayat Pesanan</h1>
    <p class="text-sm text-stone-500 mt-0.5">Pantau status, lihat nota, dan pesan ulang tanaman favoritmu.</p>
</div>

<?php if ($success !== null): ?>
<div class="relative overflow-hidden bg-gradient-to-r from-emerald-50 to-white border border-emerald-200 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 fade-up">
    <div class="w-12 h-12 rounded-2xl bg-[#2E7D32] text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-200"><i data-lucide="party-popper" class="w-6 h-6"></i></div>
    <div class="flex-1">
        <p class="text-sm font-bold text-emerald-900">Pesanan berhasil dibuat! 🎉</p>
        <p class="text-xs text-emerald-800 mt-0.5">Kode pesanan <b class="font-mono"><?= e($success) ?></b>. Admin akan segera memproses dan mengonfirmasi pesananmu.</p>
    </div>
    <?php $newId = $success !== '' ? (int)db_val('SELECT id FROM transaksi WHERE kode_transaksi = ? AND user_id = ?', 'si', [$success, $uid], 0) : 0;
    if ($newId): ?><a href="note.php?id=<?= $newId ?>" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition shrink-0"><i data-lucide="receipt" class="w-4 h-4"></i> Lihat Nota</a><?php endif; ?>
</div>
<?php endif; ?>

<!-- STATISTIK -->
<section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <?php foreach ([
        ['Total Pesanan', (int)$st['total'], 'Semua transaksi', 'border-t-[#2E7D32]'],
        ['Sedang Berjalan', (int)$st['aktif'], 'Diproses / dikirim', 'border-t-[#D97706]'],
        ['Selesai', (int)$st['selesai'], 'Transaksi sukses', 'border-t-emerald-500'],
        ['Total Belanja', rupiah($st['belanja']), 'Tanpa pesanan batal', 'border-t-blue-500'],
    ] as [$l, $v, $sub, $b]): ?>
    <div class="bg-white rounded-2xl p-4 sm:p-5 border border-stone-200/80 shadow-sm border-t-4 <?= $b ?> fade-up">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500"><?= $l ?></p>
        <p class="text-xl font-bold tracking-tight mt-1 truncate"><?= e((string)$v) ?></p>
        <p class="text-[11px] text-stone-400 mt-1"><?= $sub ?></p>
    </div>
    <?php endforeach; ?>
</section>

<!-- FILTER -->
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-4 space-y-3 fade-up">
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
        <?php foreach ($tabs as $key => $label): $on = $statusFilter === $key; ?>
            <a href="<?= e(url_with(['status_filter' => $key === 'semua' ? null : $key, 'status' => null])) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $on ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                <?= $label ?> <span class="opacity-70">(<?= $tabCount[$key] ?? 0 ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="grid grid-cols-2 md:grid-cols-12 gap-3 pt-3 border-t border-stone-100">
        <?php if ($statusFilter !== 'semua'): ?><input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>"><?php endif; ?>
        <input type="hidden" name="range" value="custom">
        <div class="col-span-2 md:col-span-5">
            <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Cari Kode</label>
            <div class="relative"><i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="INV-20260101-XXXXXX" class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"></div>
        </div>
        <div class="md:col-span-2"><label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Mulai</label>
            <input type="date" name="tgl_mulai" value="<?= e($tglMulai ?? date('Y-m-01')) ?>" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"></div>
        <div class="md:col-span-2"><label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Sampai</label>
            <input type="date" name="tgl_selesai" value="<?= e($tglSelesai ?? date('Y-m-d')) ?>" class="w-full px-3 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"></div>
        <div class="col-span-2 md:col-span-3 flex gap-2 items-end">
            <button class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition inline-flex items-center justify-center gap-1.5"><i data-lucide="filter" class="w-4 h-4"></i> Terapkan</button>
            <?php if ($filterAktif): ?><a href="<?= e(url_with(['search' => null, 'range' => null, 'tgl_mulai' => null, 'tgl_selesai' => null])) ?>" class="px-3 py-2.5 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl" title="Reset"><i data-lucide="x" class="w-4 h-4"></i></a><?php endif; ?>
        </div>
    </form>

    <div class="flex items-center gap-2 flex-wrap pt-3 border-t border-stone-100">
        <span class="text-[11px] font-bold text-stone-400 uppercase tracking-wider">Cepat</span>
        <?php foreach ($quick as $k => $l): ?>
            <a href="<?= e(url_with(['range' => $k === 'all' ? null : $k, 'tgl_mulai' => null, 'tgl_selesai' => null])) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg border <?= $range === $k ? 'bg-[#2E7D32] text-white border-[#2E7D32]' : 'bg-stone-50 text-stone-600 hover:bg-stone-100 border-stone-200' ?>"><?= $l ?></a>
        <?php endforeach; ?>
        <?php if ($filterAktif || $statusFilter !== 'semua'): ?><span class="text-xs text-stone-500 ml-auto">→ <b class="text-stone-700"><?= $totalRows ?></b> pesanan</span><?php endif; ?>
    </div>
</section>

<!-- DAFTAR -->
<?php if (!$orders): ?>
    <?= ($filterAktif || $statusFilter !== 'semua')
        ? ui_empty('search-x', 'Tidak ada pesanan yang cocok', 'Coba ubah filter atau reset pencarian.', 'riwayat.php', 'Reset Filter', 'rotate-ccw')
        : ui_empty('receipt', 'Belum ada pesanan', 'Kamu belum melakukan transaksi apa pun.', 'katalog.php', 'Mulai Belanja') ?>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($orders as $o):
        $oid = (int)$o['id']; $its = $thumbs[$oid] ?? []; ?>
    <article class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden hover:shadow-md transition fade-up">
        <div class="p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div class="min-w-0 space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-mono text-sm font-bold"><?= e($o['kode_transaksi']) ?></p>
                        <?= ui_status_badge($o['status']) ?>
                    </div>
                    <p class="text-xs text-stone-500"><?= tgl_id($o['created_at'], true) ?> · <?= (int)$o['jml_item'] ?> produk · <?= (int)$o['total_qty'] ?> item · <span class="font-semibold text-stone-700"><?= e(metode_label($o['metode_pembayaran'])) ?></span></p>
                </div>
                <div class="sm:text-right shrink-0">
                    <p class="text-[10px] font-bold uppercase text-stone-400 tracking-wider">Total Bayar</p>
                    <p class="text-lg font-bold <?= $o['status'] === 'Batal' ? 'text-stone-400 line-through' : 'text-[#2E7D32]' ?>"><?= rupiah($o['total_harga']) ?></p>
                </div>
            </div>

            <?php if ($its): ?>
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                <?php foreach (array_slice($its, 0, 5) as $it): ?>
                    <div class="flex items-center gap-2 bg-stone-50 border border-stone-100 rounded-xl p-1.5 pr-3 shrink-0">
                        <img src="<?= e(produk_img($it['gambar'] ?? '', 100)) ?>" alt="" class="w-10 h-10 rounded-lg object-cover bg-stone-100">
                        <div class="max-w-[130px]"><p class="text-[11px] font-semibold truncate"><?= e($it['nama_tanaman'] ?? 'Produk dihapus') ?></p><p class="text-[10px] text-stone-400">× <?= (int)$it['jumlah'] ?></p></div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($its) > 5): ?><span class="text-xs font-semibold text-stone-400 shrink-0">+<?= count($its) - 5 ?> lagi</span><?php endif; ?>
            </div>
            <?php endif; ?>

            <?= ui_order_tracker($o['status']) ?>
        </div>

        <div class="px-5 py-3 bg-stone-50/60 border-t border-stone-100 flex items-center justify-end gap-2 flex-wrap">
            <?php if ($o['status'] === 'Diproses'): ?>
            <form method="post" data-confirm="Batalkan pesanan ini? Stok produk akan dikembalikan." class="inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="batalkan"><input type="hidden" name="trx_id" value="<?= $oid ?>">
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-600 bg-white hover:bg-rose-50 border border-rose-200 rounded-lg transition"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Batalkan</button>
            </form>
            <?php endif; ?>
            <a href="note.php?id=<?= $oid ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-stone-700 bg-white hover:bg-stone-100 border border-stone-200 rounded-lg transition"><i data-lucide="file-text" class="w-3.5 h-3.5"></i> Lihat Nota</a>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="beli_lagi"><input type="hidden" name="trx_id" value="<?= $oid ?>">
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-[#2E7D32] hover:bg-emerald-800 rounded-lg transition"><i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Beli Lagi</button></form>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?= ui_pagination($page, $pages) ?>
<?php endif; ?>

<?php layout_end(); ?>
