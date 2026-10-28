<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();

// =====================================================
// AKSI (semua lewat POST + CSRF)
// =====================================================
if (is_post()) {
    csrf_require('cart.php');
    $op  = $_POST['op'] ?? ($_POST['action'] ?? '');
    $id  = (int)($_POST['id'] ?? 0);
    $ajax = is_ajax();

    if ($op === 'add') {
        $res = cart_add($id, (int)($_POST['qty'] ?? 1));
        $map = [
            'added'     => ['success', 'Ditambahkan ke keranjang.'],
            'overstock' => ['warning', 'Jumlah melebihi stok, disesuaikan dengan stok tersedia.'],
            'habis'     => ['error',   'Maaf, stok produk ini habis.'],
            'notfound'  => ['error',   'Produk tidak ditemukan.'],
            'invalid'   => ['error',   'Produk tidak valid.'],
        ];
        [$type, $msg] = $map[$res] ?? ['error', 'Gagal menambahkan produk.'];
        if ($ajax) {
            json_out(['ok' => in_array($res, ['added', 'overstock'], true), 'status' => $type, 'type' => $type, 'msg' => $msg, 'message' => $msg, 'count' => cart_count(), 'cart_count' => cart_count()]);
        }
        flash($type === 'success' ? 'success' : ($type === 'warning' ? 'warning' : 'error'), $msg);
        redirect('cart.php');
    }

    $cart = cart_get();
    switch ($op) {
        case 'inc':
            $r = cart_add($id, 1);
            if ($r === 'overstock') flash('warning', 'Sudah mencapai batas stok tersedia.');
            break;
        case 'dec':
            if (isset($cart[$id])) {
                $cart[$id]--;
                if ($cart[$id] <= 0) unset($cart[$id]);
                $_SESSION['cart'] = $cart;
            }
            break;
        case 'remove':
            unset($_SESSION['cart'][$id]);
            flash('info', 'Item dihapus dari keranjang.');
            break;
        case 'clear':
            $_SESSION['cart'] = [];
            flash('info', 'Keranjang dikosongkan.');
            break;
        case 'remove_empty':
            $n = 0;
            foreach (cart_lines($cart)['items'] as $it) if ($it['status'] === 'empty') { unset($_SESSION['cart'][$it['id']]); $n++; }
            foreach (cart_lines($cart)['missing'] as $pid) { unset($_SESSION['cart'][$pid]); $n++; }
            flash('success', "$n item habis dihapus dari keranjang.");
            break;
    }
    redirect('cart.php');
}

// Kompatibilitas tautan lama (?action=add&id=) - dialihkan ke katalog, tanpa mengubah data via GET
if (isset($_GET['action'])) {
    flash('info', 'Gunakan tombol "Keranjang" pada produk untuk menambahkan.');
    redirect('katalog.php');
}

// =====================================================
// DATA
// =====================================================
$cart  = cart_get();
$data  = cart_lines($cart);
// sinkronkan session dengan stok nyata
foreach ($data['items'] as $it) {
    if ($it['status'] === 'adjusted') $_SESSION['cart'][$it['id']] = $it['qty'];
}
foreach ($data['missing'] as $pid) unset($_SESSION['cart'][$pid]);

$items    = $data['items'];
$hasEmpty = !empty($data['empty']);
$subtotal = $data['subtotal'];
$tot      = hitung_total($subtotal);

// Rekomendasi
$wish = wishlist_ids($uid);
$best = best_seller_ids();
$rekom = [];
if ($items) {
    $ids = array_map(fn($i) => (int)$i['id'], $items);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $rekom = db_all("SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON k.id=p.kategori_id
                     WHERE p.stok > 0 AND p.id NOT IN ($ph) ORDER BY RAND() LIMIT 4", str_repeat('i', count($ids)), $ids);
}

layout_start(['title' => 'Keranjang Belanja', 'active' => 'cart', 'width' => 'max-w-6xl']);
?>

<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 fade-up">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Keranjang Belanja</h1>
        <p class="text-sm text-stone-500 mt-0.5">Tinjau item yang akan kamu beli sebelum checkout.</p>
    </div>
    <?php if ($items): ?>
    <form method="post" data-confirm="Kosongkan seluruh keranjang?">
        <?= csrf_field() ?><input type="hidden" name="op" value="clear">
        <button class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 px-3 py-2 rounded-xl transition"><i data-lucide="trash-2" class="w-4 h-4"></i> Kosongkan Keranjang</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($data['adjusted'] || $hasEmpty): ?>
<div class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-5 space-y-3 fade-up">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5"></i></div>
        <div>
            <p class="text-sm font-bold text-amber-900">Stok produk berubah</p>
            <p class="text-xs text-amber-700 mt-0.5">Beberapa item di keranjangmu mengalami perubahan stok. Mohon periksa kembali.</p>
        </div>
    </div>
    <?php if ($data['adjusted']): ?>
    <div class="bg-white/70 border border-amber-200 rounded-xl p-3 space-y-1.5">
        <p class="text-[11px] font-bold text-amber-800 uppercase tracking-wider">Jumlah disesuaikan (<?= count($data['adjusted']) ?>)</p>
        <?php foreach ($data['adjusted'] as $a): ?>
            <div class="flex items-center justify-between text-xs"><span class="font-semibold"><?= e($a['nama']) ?></span>
            <span><b class="text-rose-600 line-through"><?= (int)$a['dari'] ?></b> → <b class="text-emerald-700"><?= (int)$a['jadi'] ?></b></span></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($hasEmpty): ?>
    <div class="bg-white/70 border border-rose-200 rounded-xl p-3 space-y-1.5">
        <p class="text-[11px] font-bold text-rose-800 uppercase tracking-wider">Stok habis (<?= count($data['empty']) ?>)</p>
        <?php foreach ($data['empty'] as $n): ?><p class="text-xs"><b><?= e($n) ?></b> — tidak tersedia</p><?php endforeach; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="remove_empty">
            <button class="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 px-3 py-2 rounded-lg transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Semua Item Habis</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$items): ?>
    <?= ui_empty('shopping-cart', 'Keranjang masih kosong', 'Tambahkan tanaman dari katalog untuk memulai belanja.', 'katalog.php', 'Buka Katalog') ?>
<?php else: ?>
<div class="grid lg:grid-cols-3 gap-6 items-start">
    <section class="lg:col-span-2 bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden fade-up">
        <div class="px-5 py-4 border-b border-stone-100 flex items-center justify-between">
            <h2 class="text-sm font-bold uppercase tracking-wider">Item di Keranjang</h2>
            <span class="text-[11px] text-stone-500"><?= count($items) ?> produk</span>
        </div>
        <div class="divide-y divide-stone-100">
        <?php foreach ($items as $it):
            $empty = $it['status'] === 'empty'; $adj = $it['status'] === 'adjusted';
            $kritis = !$empty && $it['stok'] <= $it['stok_minimal']; ?>
            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4 <?= $empty ? 'bg-rose-50/40' : ($adj ? 'bg-amber-50/40' : '') ?>">
                <div class="flex gap-4 flex-1 min-w-0">
                    <a href="detail.php?id=<?= $it['id'] ?>" class="relative shrink-0">
                        <img src="<?= e($it['gambar']) ?>" alt="" class="w-20 h-20 rounded-xl object-cover bg-stone-100 <?= $empty ? 'opacity-50 grayscale' : '' ?>">
                        <?php if ($empty): ?><span class="absolute top-1 left-1 bg-rose-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded uppercase">Habis</span>
                        <?php elseif ($adj): ?><span class="absolute top-1 left-1 bg-amber-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded uppercase">Disesuaikan</span><?php endif; ?>
                    </a>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase text-stone-400 tracking-wider"><?= e($it['kategori']) ?></p>
                        <a href="detail.php?id=<?= $it['id'] ?>" class="text-sm font-bold hover:text-[#2E7D32] block truncate"><?= e($it['nama']) ?></a>
                        <p class="text-xs text-stone-500 mt-1"><?= rupiah($it['harga']) ?> ·
                            <?php if ($empty): ?><span class="text-rose-600 font-semibold">Stok habis</span>
                            <?php elseif ($kritis): ?><span class="text-amber-600 font-semibold">Sisa <?= $it['stok'] ?> unit!</span>
                            <?php else: ?>Stok: <?= $it['stok'] ?><?php endif; ?></p>
                    </div>
                </div>
                <div class="flex items-center justify-between sm:justify-end gap-4 shrink-0">
                    <?php if ($empty): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="remove"><input type="hidden" name="id" value="<?= $it['id'] ?>">
                            <button class="text-xs font-semibold text-rose-600 hover:bg-rose-100 px-3 py-2 rounded-lg transition inline-flex items-center gap-1.5"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button></form>
                    <?php else: ?>
                        <form method="post" class="flex items-center border border-stone-200 rounded-xl overflow-hidden bg-stone-50">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $it['id'] ?>">
                            <button name="op" value="dec" class="px-3 py-2 text-stone-600 hover:bg-stone-100 font-bold text-sm" aria-label="Kurangi">−</button>
                            <span class="px-3 py-2 text-sm font-bold bg-white min-w-[40px] text-center"><?= $it['qty'] ?></span>
                            <button name="op" value="inc" class="px-3 py-2 text-stone-600 hover:bg-stone-100 font-bold text-sm disabled:opacity-40" aria-label="Tambah" <?= $it['qty'] >= $it['stok'] ? 'disabled' : '' ?>>+</button>
                        </form>
                        <div class="text-right min-w-[100px]">
                            <p class="text-sm font-bold text-[#2E7D32]"><?= rupiah($it['subtotal']) ?></p>
                            <form method="post" data-confirm="Hapus item ini?"><?= csrf_field() ?><input type="hidden" name="op" value="remove"><input type="hidden" name="id" value="<?= $it['id'] ?>">
                                <button class="inline-flex items-center gap-1 text-[11px] text-stone-400 hover:text-rose-600 mt-0.5"><i data-lucide="trash-2" class="w-3 h-3"></i> Hapus</button></form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="px-5 py-4 bg-stone-50/60 border-t border-stone-100">
            <a href="katalog.php" class="inline-flex items-center gap-2 text-xs font-semibold text-[#2E7D32] hover:underline"><i data-lucide="arrow-left" class="w-4 h-4"></i> Lanjut Belanja</a>
        </div>
    </section>

    <aside class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 lg:sticky lg:top-24 fade-up">
        <h2 class="text-sm font-bold uppercase tracking-wider border-b border-stone-100 pb-3">Ringkasan Belanja</h2>
        <?= ui_totals($subtotal, $data['count']) ?>
        <?php if ($hasEmpty): ?>
            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-[11px] text-rose-700 flex items-start gap-2"><i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span>Hapus item yang habis dulu sebelum checkout.</span></div>
            <button disabled class="w-full inline-flex items-center justify-center gap-2 bg-stone-200 text-stone-400 font-bold py-3 rounded-xl text-sm cursor-not-allowed"><i data-lucide="x-circle" class="w-4 h-4"></i> Tidak Bisa Checkout</button>
        <?php else: ?>
            <a href="checkout.php" class="w-full inline-flex items-center justify-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 rounded-xl text-sm transition shadow-sm"><i data-lucide="arrow-right" class="w-4 h-4"></i> Lanjut ke Checkout</a>
        <?php endif; ?>
        <p class="text-[11px] text-stone-400 text-center flex items-center justify-center gap-1"><i data-lucide="shield-check" class="w-3 h-3"></i> Stok dicek ulang saat pembayaran</p>
    </aside>
</div>

<?php if ($rekom): ?>
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5 sm:p-6">
    <?= ui_section_head('Mungkin Kamu Suka', 'Teman yang pas untuk tanamanmu') ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4"><?php foreach ($rekom as $p) echo ui_product_card($p, $wish, $best); ?></div>
</section>
<?php endif; ?>
<?php endif; ?>

<?php layout_end(); ?>
