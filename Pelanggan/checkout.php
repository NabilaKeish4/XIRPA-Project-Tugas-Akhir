<?php
require_once __DIR__ . '/customer_init.php';

$uid     = user_id();
$modeNow = ($_GET['mode'] ?? $_POST['mode'] ?? '') === 'now';
$self    = 'checkout.php' . ($modeNow ? '?mode=now' : '');

// Sumber item: "Beli Sekarang" (terpisah dari keranjang) atau keranjang
if ($modeNow) {
    $source = $_SESSION['buy_now'] ?? [];
    if (!is_array($source) || !$source) { flash('info', 'Sesi "Beli Sekarang" berakhir, silakan pilih produk lagi.'); redirect('katalog.php'); }
} else {
    $source = cart_get();
    if (!$source) { flash('info', 'Keranjangmu masih kosong.'); redirect('cart.php'); }
}

$data   = cart_lines($source);
$notes  = [];   // pemberitahuan penyesuaian stok
foreach ($data['adjusted'] as $a) $notes[] = "<b>" . e($a['nama']) . "</b> — jumlah disesuaikan {$a['dari']} → {$a['jadi']} (stok terbatas)";
foreach ($data['empty'] as $n)    $notes[] = "<b>" . e($n) . "</b> — stok habis, tidak ikut dipesan";

$items = array_values(array_filter($data['items'], fn($i) => $i['status'] !== 'empty'));
if (!$items) { flash('error', 'Semua item sudah habis stok.'); redirect($modeNow ? 'katalog.php' : 'cart.php'); }

$subtotal = 0.0; $qtyAll = 0;
foreach ($items as $i) { $subtotal += $i['subtotal']; $qtyAll += $i['qty']; }
$tot = hitung_total($subtotal);

$profil = current_user(true);
$metodeList = [
    'transfer' => ['Transfer Bank', 'landmark',   'BCA / Mandiri / BRI — instruksi dikirim admin'],
    'ewallet'  => ['E-Wallet',      'smartphone', 'GoPay / OVO / Dana'],
    'qris'     => ['QRIS',          'qr-code',    'Scan satu kode untuk semua aplikasi'],
    'cod'      => ['Bayar di Tempat (COD)', 'hand-coins', 'Bayar tunai saat tanaman tiba'],
];

$error = '';
$old = ['nama' => $profil['nama_lengkap'] ?? nama_user(), 'telepon' => $profil['no_hp'] ?? '', 'alamat' => $profil['alamat'] ?? '', 'metode' => 'transfer', 'catatan' => ''];

if (is_post() && isset($_POST['proses_checkout'])) {
    csrf_require($self);
    $old = [
        'nama'    => trim($_POST['nama'] ?? ''),
        'telepon' => trim($_POST['telepon'] ?? ''),
        'alamat'  => trim($_POST['alamat'] ?? ''),
        'metode'  => $_POST['metode_pembayaran'] ?? 'transfer',
        'catatan' => trim($_POST['catatan'] ?? ''),
    ];
    $simpan = isset($_POST['simpan_alamat']);
    $digits = preg_replace('/\D/', '', $old['telepon']);

    if ($old['nama'] === '' || $old['telepon'] === '' || $old['alamat'] === '') $error = 'Nama, telepon, dan alamat wajib diisi.';
    elseif (mb_strlen($old['nama']) > 100)   $error = 'Nama penerima terlalu panjang.';
    elseif (strlen($digits) < 8 || strlen($digits) > 15 || !preg_match('/^[0-9+\-\s()]+$/', $old['telepon'])) $error = 'Nomor telepon tidak valid (8–15 digit).';
    elseif (mb_strlen($old['alamat']) < 10)  $error = 'Alamat terlalu singkat, mohon lengkapi.';
    elseif (mb_strlen($old['catatan']) > 500) $error = 'Catatan maksimal 500 karakter.';
    elseif (!isset($metodeList[$old['metode']])) $error = 'Metode pembayaran tidak valid.';

    if ($error === '') {
        $kode = 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            db_begin();
            $total = 0.0; $rows = [];
            foreach ($items as $it) {
                // kunci baris produk, baca harga & stok TERBARU
                $r = db_one('SELECT id, nama_tanaman, harga_jual, stok FROM produk WHERE id = ? FOR UPDATE', 'i', [$it['id']]);
                if (!$r) throw new UserError('Produk "' . $it['nama'] . '" sudah tidak tersedia.');
                if ((int)$r['stok'] < $it['qty']) throw new UserError('Stok "' . $r['nama_tanaman'] . '" tidak mencukupi (sisa ' . (int)$r['stok'] . '). Silakan perbarui jumlah.');
                $sub = (float)$r['harga_jual'] * $it['qty'];
                $total += $sub;
                $rows[] = [$it['id'], $it['qty'], (float)$r['harga_jual'], $sub];
            }
            $t = hitung_total($total);

            $tid = db_insert(
                "INSERT INTO transaksi (kode_transaksi, user_id, supplier_id, jenis_transaksi, nama_penerima, alamat, telepon, total_harga, metode_pembayaran, status, catatan, created_at)
                 VALUES (?, ?, NULL, 'penjualan', ?, ?, ?, ?, ?, 'Diproses', ?, NOW())",
                'sisssdss', [$kode, $uid, $old['nama'], $old['alamat'], $old['telepon'], $t['total'], $old['metode'], $old['catatan']]
            );
            foreach ($rows as [$pid, $qty, $harga, $sub]) {
                db_exec('INSERT INTO transaksi_detail (transaksi_id, produk_id, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)', 'iiidd', [$tid, $pid, $qty, $harga, $sub]);
                if (db_exec('UPDATE produk SET stok = stok - ? WHERE id = ? AND stok >= ?', 'iii', [$qty, $pid, $qty]) < 1)
                    throw new UserError('Stok berubah saat diproses, silakan coba lagi.');
            }
            if ($simpan) db_exec('UPDATE users SET alamat = ?, no_hp = ? WHERE id = ?', 'ssi', [$old['alamat'], $old['telepon'], $uid]);
            db_commit();

            if ($modeNow) { unset($_SESSION['buy_now']); }
            else {
                foreach ($rows as $r) unset($_SESSION['cart'][$r[0]]);   // hanya item yang dipesan
            }
            redirect('riwayat.php?success=1&kode=' . urlencode($kode));
        } catch (UserError $e) {
            db_rollback();
            $error = $e->getMessage();
        } catch (Throwable $e) {
            db_rollback();
            error_log('CHECKOUT GAGAL: ' . $e->getMessage());
            $error = 'Maaf, pesanan belum berhasil dibuat. Silakan coba lagi beberapa saat.';
        }
    }
}

layout_start(['title' => 'Checkout', 'active' => 'cart', 'width' => 'max-w-6xl']);
?>

<div class="fade-up">
    <h1 class="text-2xl font-bold tracking-tight">Checkout Pesanan</h1>
    <p class="text-sm text-stone-500 mt-0.5"><?= $modeNow ? 'Pembelian langsung — isi keranjangmu tidak terpengaruh.' : 'Lengkapi data pengiriman dan pilih metode pembayaran.' ?></p>
    <ol class="flex items-center gap-2 mt-4 text-[11px] font-bold uppercase tracking-wide">
        <li class="text-[#2E7D32] flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-[#2E7D32] text-white flex items-center justify-center">✓</span> Keranjang</li>
        <li class="h-px w-6 bg-[#2E7D32]"></li>
        <li class="text-[#2E7D32] flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-[#2E7D32] text-white flex items-center justify-center">2</span> Checkout</li>
        <li class="h-px w-6 bg-stone-300"></li>
        <li class="text-stone-400 flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-stone-200 flex items-center justify-center">3</span> Selesai</li>
    </ol>
</div>

<?php if ($notes): ?>
<div class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-5 flex items-start gap-3">
    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0"><i data-lucide="alert-triangle" class="w-5 h-5"></i></div>
    <div><p class="text-sm font-bold text-amber-900">Stok produk berubah</p>
        <ul class="mt-2 space-y-1 text-xs text-amber-800"><?php foreach ($notes as $n) echo "<li>$n</li>"; ?></ul></div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2"><i data-lucide="alert-circle" class="w-5 h-5 text-rose-500 shrink-0"></i><span><?= e($error) ?></span></div>
<?php endif; ?>

<form id="formCheckout" method="post" action="<?= e($self) ?>" class="grid lg:grid-cols-3 gap-6 items-start" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="proses_checkout" value="1">
    <?php if ($modeNow): ?><input type="hidden" name="mode" value="now"><?php endif; ?>

    <div class="lg:col-span-2 space-y-6">
        <section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5 sm:p-6 space-y-4 fade-up">
            <h2 class="text-sm font-bold uppercase tracking-wider border-b border-stone-100 pb-3 flex items-center gap-2"><i data-lucide="map-pin" class="w-4 h-4 text-[#2E7D32]"></i> Data Pengiriman</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Penerima <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" id="inNama" required maxlength="100" value="<?= e($old['nama']) ?>" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Telepon / WhatsApp <span class="text-rose-500">*</span></label>
                    <input type="tel" name="telepon" id="inTelepon" required value="<?= e($old['telepon']) ?>" placeholder="081234567890" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat Lengkap <span class="text-rose-500">*</span></label>
                <textarea name="alamat" id="inAlamat" required rows="3" placeholder="Jl. ... No. ..., Kelurahan, Kecamatan, Kota, Kode Pos" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100"><?= e($old['alamat']) ?></textarea>
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-stone-600 cursor-pointer"><input type="checkbox" name="simpan_alamat" value="1" class="rounded border-stone-300 text-[#2E7D32] focus:ring-[#2E7D32]"> Simpan alamat & telepon ini ke profil</label>
            </div>
            <div>
                <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Catatan (opsional)</label>
                <textarea name="catatan" id="inCatatan" rows="2" maxlength="500" placeholder="Contoh: Titip ke resepsionis, bungkus ekstra aman." class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100"><?= e($old['catatan']) ?></textarea>
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5 sm:p-6 space-y-4 fade-up">
            <h2 class="text-sm font-bold uppercase tracking-wider border-b border-stone-100 pb-3 flex items-center gap-2"><i data-lucide="credit-card" class="w-4 h-4 text-[#2E7D32]"></i> Metode Pembayaran</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                <?php foreach ($metodeList as $key => [$label, $icon, $desc]): ?>
                <label class="relative cursor-pointer">
                    <input type="radio" name="metode_pembayaran" value="<?= $key ?>" class="peer sr-only" <?= $old['metode'] === $key ? 'checked' : '' ?>>
                    <div class="flex items-start gap-3 p-4 rounded-xl border-2 border-stone-200 bg-white peer-checked:border-[#2E7D32] peer-checked:bg-emerald-50 hover:border-stone-300 transition">
                        <div class="w-10 h-10 rounded-lg bg-stone-100 text-stone-500 flex items-center justify-center shrink-0"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></div>
                        <div><p class="text-sm font-bold"><?= e($label) ?></p><p class="text-[11px] text-stone-500 mt-0.5"><?= e($desc) ?></p></div>
                    </div>
                    <span class="absolute top-3 right-3 w-5 h-5 rounded-full bg-[#2E7D32] text-white items-center justify-center hidden peer-checked:flex"><i data-lucide="check" class="w-3 h-3"></i></span>
                </label>
                <?php endforeach; ?>
            </div>
        </section>

        <a href="<?= $modeNow ? 'katalog.php' : 'cart.php' ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-stone-500 hover:text-stone-800"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> <?= $modeNow ? 'Kembali ke Katalog' : 'Kembali ke Keranjang' ?></a>
    </div>

    <aside class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 lg:sticky lg:top-24 fade-up">
        <h2 class="text-sm font-bold uppercase tracking-wider border-b border-stone-100 pb-3">Ringkasan Pesanan</h2>
        <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
            <?php foreach ($items as $it): ?>
            <div class="flex gap-3 items-center">
                <img src="<?= e($it['gambar']) ?>" alt="" class="w-12 h-12 rounded-lg object-cover bg-stone-100 shrink-0">
                <div class="flex-1 min-w-0"><p class="text-xs font-bold truncate"><?= e($it['nama']) ?></p><p class="text-[11px] text-stone-500"><?= $it['qty'] ?> × <?= rupiah($it['harga']) ?></p></div>
                <p class="text-xs font-bold shrink-0"><?= rupiah($it['subtotal']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-stone-100 pt-3"><?= ui_totals($subtotal, $qtyAll) ?></div>
        <button type="button" onclick="bukaKonfirmasi()" class="w-full bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 rounded-xl text-sm transition inline-flex items-center justify-center gap-2 shadow-sm active:scale-95"><i data-lucide="check-circle" class="w-4 h-4"></i> Selesaikan Pesanan</button>
        <p class="text-[10px] text-stone-400 text-center leading-relaxed">Dengan menyelesaikan pesanan, kamu menyetujui syarat & ketentuan PlantHub.</p>
    </aside>
</form>

<!-- MODAL KONFIRMASI -->
<div id="modalKonfirmasi" class="no-print fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-[70] hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full my-8">
        <div class="p-5 border-b border-stone-100 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center"><i data-lucide="clipboard-check" class="w-4 h-4"></i></div>
                <div><h3 class="text-base font-bold">Konfirmasi Pesanan</h3><p class="text-[11px] text-stone-500">Periksa kembali sebelum membuat pesanan</p></div>
            </div>
            <button type="button" onclick="tutupKonfirmasi()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-5 space-y-3 max-h-[65vh] overflow-y-auto">
            <div class="bg-stone-50 border border-stone-100 rounded-xl p-4"><p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Dikirim ke</p>
                <p id="kfNama" class="text-sm font-bold"></p><p id="kfTelepon" class="text-xs text-stone-600 mt-0.5"></p><p id="kfAlamat" class="text-xs text-stone-600 mt-1 leading-relaxed"></p></div>
            <div class="bg-stone-50 border border-stone-100 rounded-xl p-4"><p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Metode Pembayaran</p><p id="kfMetode" class="text-sm font-bold"></p></div>
            <div id="kfCatatanWrap" class="bg-stone-50 border border-stone-100 rounded-xl p-4 hidden"><p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Catatan</p><p id="kfCatatan" class="text-xs text-stone-600 italic"></p></div>
            <div class="bg-stone-50 border border-stone-100 rounded-xl p-4 space-y-1.5"><p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Item (<?= $qtyAll ?>)</p>
                <?php foreach ($items as $it): ?><div class="flex justify-between text-xs"><span class="truncate pr-2"><?= e($it['nama']) ?> <b class="text-stone-500">× <?= $it['qty'] ?></b></span><span class="font-bold shrink-0"><?= rupiah($it['subtotal']) ?></span></div><?php endforeach; ?></div>
            <div class="bg-[#E8F5E9] border-2 border-[#2E7D32]/30 rounded-xl p-4"><?= ui_totals($subtotal, $qtyAll) ?></div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 flex items-start gap-2"><i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                <p class="text-[11px] text-amber-800 leading-relaxed">Data pengiriman tidak dapat diubah setelah pesanan dibuat. Pesanan bisa dibatalkan selama status masih <b>Diproses</b>.</p></div>
        </div>
        <div class="p-4 border-t border-stone-100 flex gap-2">
            <button type="button" onclick="tutupKonfirmasi()" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold py-2.5 rounded-xl text-sm transition">Kembali</button>
            <button type="button" id="btnKonfirmasi" onclick="submitCheckout()" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-2.5 rounded-xl text-sm inline-flex items-center justify-center gap-2 transition"><i data-lucide="check" class="w-4 h-4"></i> Ya, Buat Pesanan</button>
        </div>
    </div>
</div>

<?php
$metodeJs = json_encode(array_map(fn($m) => $m[0], $metodeList), JSON_UNESCAPED_UNICODE);
$GLOBALS['__extra_js'] = <<<JS
<script>
const METODE = $metodeJs;
const \$ = (id) => document.getElementById(id);
function bukaKonfirmasi() {
    const nama = \$('inNama').value.trim(), tel = \$('inTelepon').value.trim(), alamat = \$('inAlamat').value.trim(), cat = \$('inCatatan').value.trim();
    const metode = document.querySelector('input[name="metode_pembayaran"]:checked')?.value || 'transfer';
    if (!nama)  { toast('Nama penerima wajib diisi.', 'error'); \$('inNama').focus(); return; }
    if (tel.replace(/\\D/g, '').length < 8) { toast('Nomor telepon minimal 8 digit.', 'error'); \$('inTelepon').focus(); return; }
    if (alamat.length < 10) { toast('Alamat pengiriman belum lengkap.', 'error'); \$('inAlamat').focus(); return; }
    \$('kfNama').textContent = nama; \$('kfTelepon').textContent = tel; \$('kfAlamat').textContent = alamat;
    \$('kfMetode').textContent = METODE[metode] || metode;
    \$('kfCatatan').textContent = cat; \$('kfCatatanWrap').classList.toggle('hidden', !cat);
    const m = \$('modalKonfirmasi'); m.classList.remove('hidden'); m.classList.add('flex');
}
function tutupKonfirmasi() { const m = \$('modalKonfirmasi'); m.classList.add('hidden'); m.classList.remove('flex'); }
function submitCheckout() {
    const b = \$('btnKonfirmasi'); b.textContent = 'Memproses…'; b.style.pointerEvents = 'none'; b.style.opacity = '.7';
    \$('formCheckout').submit();
}
\$('modalKonfirmasi').addEventListener('click', (e) => { if (e.target.id === 'modalKonfirmasi') tutupKonfirmasi(); });
\$('formCheckout').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); bukaKonfirmasi(); } });
</script>
JS;
layout_end(); ?>
