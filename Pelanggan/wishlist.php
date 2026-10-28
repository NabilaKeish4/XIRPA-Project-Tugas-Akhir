<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();
ensure_wishlist_table();

// ---- Endpoint toggle (AJAX / form) ----
if (is_post()) {
    csrf_require('wishlist.php');
    $op = $_POST['op'] ?? 'toggle';
    $id = (int)($_POST['id'] ?? 0);

    if ($op === 'clear') {
        db_exec('DELETE FROM wishlist WHERE user_id = ?', 'i', [$uid]);
        flash('info', 'Wishlist dikosongkan.');
        redirect('wishlist.php');
    }

    $ok = false; $wished = false; $msg = '';
    if ($id > 0 && db_val('SELECT COUNT(*) FROM produk WHERE id = ?', 'i', [$id], 0) > 0) {
        if (db_val('SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND produk_id = ?', 'ii', [$uid, $id], 0) > 0) {
            db_exec('DELETE FROM wishlist WHERE user_id = ? AND produk_id = ?', 'ii', [$uid, $id]);
        } else {
            db_exec('INSERT IGNORE INTO wishlist (user_id, produk_id) VALUES (?, ?)', 'ii', [$uid, $id]);
            $wished = true;
        }
        $ok = true;
    } else {
        $msg = 'Produk tidak ditemukan.';
    }
    $count = count(wishlist_ids($uid));
    if (is_ajax()) json_out(['ok' => $ok, 'wished' => $wished, 'msg' => $msg, 'count' => $count]);
    redirect($_SERVER['HTTP_REFERER'] ?? 'wishlist.php');
}

$list = db_all("
    SELECT p.*, k.nama_kategori,
           COALESCE((SELECT SUM(td.jumlah) FROM transaksi_detail td JOIN transaksi t ON t.id = td.transaksi_id
                     WHERE td.produk_id = p.id AND t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'),0) AS total_terjual
    FROM wishlist w
    JOIN produk p ON p.id = w.produk_id
    LEFT JOIN kategori k ON k.id = p.kategori_id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC, w.id DESC
", 'i', [$uid]);
$wish = wishlist_ids($uid);
$best = best_seller_ids();

layout_start(['title' => 'Wishlist', 'active' => 'wishlist', 'width' => 'max-w-6xl']);
?>
<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 fade-up">
    <div>
        <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2"><i data-lucide="heart" class="w-6 h-6 text-rose-500 fill-rose-500"></i> Wishlist</h1>
        <p class="text-sm text-stone-500 mt-0.5"><?= count($list) ?> tanaman yang kamu simpan untuk nanti.</p>
    </div>
    <?php if ($list): ?>
    <form method="post" data-confirm="Kosongkan seluruh wishlist?"><?= csrf_field() ?><input type="hidden" name="op" value="clear">
        <button class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 px-3 py-2 rounded-xl transition"><i data-lucide="trash-2" class="w-4 h-4"></i> Kosongkan</button></form>
    <?php endif; ?>
</div>

<?php if (!$list): ?>
    <?= ui_empty('heart', 'Wishlist masih kosong', 'Tekan ikon hati pada tanaman yang kamu suka, dan temukan lagi di sini.', 'katalog.php', 'Jelajahi Katalog') ?>
<?php else: ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <?php foreach ($list as $p) echo ui_product_card($p, $wish, $best, ['remove_on_unwish' => true]); ?>
    </div>
<?php endif; ?>
<?php layout_end(); ?>
