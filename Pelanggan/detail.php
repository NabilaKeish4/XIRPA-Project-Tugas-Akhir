<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();
$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('katalog.php');

$p = db_one("SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE p.id = ? LIMIT 1", 'i', [$id]);
if (!$p) { flash('error', 'Produk tidak ditemukan.'); redirect('katalog.php'); }

$stok    = (int)$p['stok'];
$habis   = $stok <= 0;
$kritis  = $stok > 0 && $stok <= (int)$p['stok_minimal'];

// ---- Beli Sekarang: simpan di session terpisah, keranjang TIDAK diubah ----
if (is_post() && ($_POST['action'] ?? '') === 'beli_sekarang') {
    csrf_require("detail.php?id=$id");
    if ($habis) { flash('error', 'Produk sudah habis, tidak dapat dibeli.'); redirect("detail.php?id=$id"); }
    $qty = min($stok, max(1, (int)($_POST['qty'] ?? 1)));
    $_SESSION['buy_now'] = [$id => $qty];
    redirect('checkout.php?mode=now');
}

$terjual = (int)db_val("
    SELECT COALESCE(SUM(td.jumlah),0) FROM transaksi_detail td JOIN transaksi t ON td.transaksi_id = t.id
    WHERE td.produk_id = ? AND t.status = 'Selesai' AND t.jenis_transaksi = 'penjualan'", 'i', [$id], 0);
$best   = best_seller_ids();
$wish   = wishlist_ids($uid);
$isBest = isset($best[$id]);
$wished = isset($wish[$id]);
$inCart = cart_get()[$id] ?? 0;

$terkait = db_all("
    SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id
    WHERE p.kategori_id = ? AND p.id <> ? AND p.stok > 0 ORDER BY RAND() LIMIT 4
", 'ii', [(int)$p['kategori_id'], $id]);

layout_start(['title' => $p['nama_tanaman'], 'active' => 'katalog', 'width' => 'max-w-6xl']);
?>

<nav class="flex items-center gap-1.5 text-xs text-stone-500 flex-wrap">
    <a href="dashboard.php" class="hover:text-[#2E7D32]">Beranda</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="katalog.php" class="hover:text-[#2E7D32]">Katalog</a>
    <?php if (!empty($p['kategori_id'])): ?>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <a href="katalog.php?kategori=<?= (int)$p['kategori_id'] ?>" class="hover:text-[#2E7D32]"><?= e($p['nama_kategori']) ?></a>
    <?php endif; ?>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-stone-800 font-semibold truncate"><?= e($p['nama_tanaman']) ?></span>
</nav>

<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden fade-up">
    <div class="grid md:grid-cols-2">
        <!-- GAMBAR -->
        <div class="relative bg-stone-100 aspect-square md:aspect-auto md:min-h-[480px] overflow-hidden group">
            <img src="<?= e(produk_img($p['gambar'], 800)) ?>" alt="<?= e($p['nama_tanaman']) ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 <?= $habis ? 'grayscale opacity-70' : '' ?>">
            <div class="absolute top-4 left-4 flex flex-col gap-2 items-start">
                <?php if ($isBest): ?><span class="bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg uppercase inline-flex items-center gap-1.5 shadow-md"><i data-lucide="flame" class="w-3.5 h-3.5"></i> Terlaris</span><?php endif; ?>
                <?php if ($habis): ?><span class="bg-rose-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg uppercase shadow-md">Stok Habis</span><?php endif; ?>
            </div>
            <button type="button" data-wish="<?= $id ?>" class="wish-btn absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 backdrop-blur shadow flex items-center justify-center text-stone-500 hover:text-rose-500 transition <?= $wished ? 'is-wished' : '' ?>" aria-label="Wishlist">
                <i data-lucide="heart" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- INFO -->
        <div class="p-6 lg:p-8 flex flex-col">
            <p class="text-[11px] font-bold uppercase text-stone-400 tracking-wider"><?= e($p['nama_kategori'] ?? 'Tanaman') ?></p>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mt-1"><?= e($p['nama_tanaman']) ?></h1>

            <div class="mt-3 flex items-center gap-2 flex-wrap text-xs">
                <?php if ($terjual > 0): ?><span class="inline-flex items-center gap-1 font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-100"><i data-lucide="shopping-bag" class="w-3 h-3"></i><?= $terjual ?> terjual</span><?php endif; ?>
                <?php if ($habis): ?>
                    <span class="inline-flex items-center gap-1 font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-md border border-rose-100"><i data-lucide="x-circle" class="w-3 h-3"></i> Stok Habis</span>
                <?php elseif ($kritis): ?>
                    <span class="inline-flex items-center gap-1 font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-100"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Stok Terbatas</span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-100"><i data-lucide="check-circle" class="w-3 h-3"></i> Tersedia</span>
                <?php endif; ?>
                <span class="text-stone-500">Sisa stok: <b class="text-stone-800"><?= $stok ?> unit</b></span>
            </div>

            <div class="mt-5 pb-5 border-b border-stone-100">
                <p class="text-3xl font-bold text-[#2E7D32] tracking-tight"><?= rupiah($p['harga_jual']) ?></p>
                <p class="text-[11px] text-stone-400 mt-1">Belum termasuk PPN <?= (int)(PPN_RATE * 100) ?>% (dihitung saat checkout)</p>
            </div>

            <?php if (!empty($p['deskripsi'])): ?>
            <div class="mt-5">
                <h3 class="text-xs font-bold uppercase text-stone-500 tracking-wider mb-2">Deskripsi</h3>
                <p class="text-sm text-stone-700 leading-relaxed"><?= nl2br(e($p['deskripsi'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($p['cara_perawatan'])): ?>
            <div class="mt-5 bg-emerald-50/60 border border-emerald-100 rounded-xl p-4">
                <h3 class="text-xs font-bold uppercase text-[#2E7D32] tracking-wider mb-2 flex items-center gap-1.5"><i data-lucide="droplets" class="w-3.5 h-3.5"></i> Cara Perawatan</h3>
                <p class="text-sm text-stone-700 leading-relaxed"><?= nl2br(e($p['cara_perawatan'])) ?></p>
            </div>
            <?php endif; ?>

            <div class="mt-6 pt-5 border-t border-stone-100 mt-auto">
                <?php if ($habis): ?>
                    <button disabled class="w-full bg-stone-200 text-stone-400 font-semibold py-3 rounded-xl text-sm cursor-not-allowed">Stok Habis — Tidak Dapat Dibeli</button>
                <?php else: ?>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Jumlah</label>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex items-center border border-stone-200 rounded-xl overflow-hidden bg-stone-50">
                            <button type="button" onclick="ubahQty(-1)" class="px-4 py-2.5 text-stone-600 hover:bg-stone-100 font-bold">−</button>
                            <input type="number" id="qtyInput" value="1" min="1" max="<?= $stok ?>" class="w-16 py-2.5 text-center text-sm font-bold bg-white border-0 focus:outline-none" oninput="klemQty()">
                            <button type="button" onclick="ubahQty(1)" class="px-4 py-2.5 text-stone-600 hover:bg-stone-100 font-bold">+</button>
                        </div>
                        <span class="text-xs text-stone-500">Maks. <?= $stok ?> unit<?= $inCart ? " · di keranjang: $inCart" : '' ?></span>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-2">
                        <button type="button" class="btn-add-cart border-2 border-[#2E7D32] text-[#2E7D32] hover:bg-emerald-50 font-bold py-3 rounded-xl text-sm transition flex items-center justify-center gap-2 active:scale-95"
                                data-id="<?= $id ?>" data-qty-input="#qtyInput">
                            <i data-lucide="shopping-cart" class="w-4 h-4"></i> Tambah ke Keranjang
                        </button>
                        <form method="post" action="detail.php?id=<?= $id ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="beli_sekarang">
                            <input type="hidden" name="qty" id="qtyNow" value="1">
                            <button type="submit" onclick="document.getElementById('qtyNow').value=document.getElementById('qtyInput').value"
                                    class="w-full bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 rounded-xl text-sm transition flex items-center justify-center gap-2 shadow-sm active:scale-95">
                                <i data-lucide="zap" class="w-4 h-4"></i> Beli Sekarang
                            </button>
                        </form>
                    </div>
                    <p class="text-[11px] text-stone-400 text-center mt-2"><i data-lucide="info" class="w-3 h-3 inline-block"></i> Beli Sekarang membeli produk ini saja — isi keranjangmu tetap aman.</p>
                <?php endif; ?>

                <a href="chat.php?produk=<?= $id ?>" class="mt-3 w-full border border-stone-200 text-stone-700 hover:bg-stone-50 font-semibold py-3 rounded-xl text-sm transition flex items-center justify-center gap-2">
                    <i data-lucide="message-circle" class="w-4 h-4 text-stone-500"></i> Tanya Admin tentang Tanaman Ini
                </a>

                <div class="grid grid-cols-3 gap-2 mt-4 text-center text-[10px] font-semibold text-stone-500">
                    <div class="bg-stone-50 rounded-xl py-2.5"><i data-lucide="package-check" class="w-4 h-4 mx-auto mb-1 text-[#2E7D32]"></i>Dikemas Aman</div>
                    <div class="bg-stone-50 rounded-xl py-2.5"><i data-lucide="shield-check" class="w-4 h-4 mx-auto mb-1 text-[#2E7D32]"></i>Tanaman Sehat</div>
                    <div class="bg-stone-50 rounded-xl py-2.5"><i data-lucide="headset" class="w-4 h-4 mx-auto mb-1 text-[#2E7D32]"></i>Konsultasi Gratis</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($terkait): ?>
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5 sm:p-6">
    <?= ui_section_head('Produk Serupa', 'Produk lain dengan kategori sama', 'katalog.php?kategori=' . (int)$p['kategori_id']) ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($terkait as $r) echo ui_product_card($r, $wish, $best); ?>
    </div>
</section>
<?php endif; ?>

<?php $GLOBALS['__extra_js'] = <<<JS
<script>
const qi = document.getElementById('qtyInput');
function klemQty(){ if(!qi) return; const m=parseInt(qi.max)||1; let v=parseInt(qi.value)||1; qi.value=Math.min(m,Math.max(1,v)); }
function ubahQty(d){ if(!qi) return; qi.value=(parseInt(qi.value)||1)+d; klemQty(); }
</script>
JS;
layout_end(); ?>
