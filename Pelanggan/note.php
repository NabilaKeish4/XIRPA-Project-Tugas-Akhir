<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();
$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('riwayat.php');

$tx = db_one("SELECT * FROM transaksi WHERE id = ? AND user_id = ? AND jenis_transaksi = 'penjualan' LIMIT 1", 'ii', [$id, $uid]);
if (!$tx) { flash('error', 'Nota tidak ditemukan.'); redirect('riwayat.php'); }

$details = db_all("
    SELECT td.*, p.nama_tanaman, p.gambar
    FROM transaksi_detail td LEFT JOIN produk p ON td.produk_id = p.id
    WHERE td.transaksi_id = ? ORDER BY td.id ASC", 'i', [$id]);

$subtotal = 0.0;
foreach ($details as $d) $subtotal += (float)$d['subtotal'];
$total = (float)$tx['total_harga'];
$ppn   = max(0, $total - $subtotal);          // total_harga sudah termasuk PPN saat checkout
if ($subtotal <= 0) { $t = hitung_total(0); $total = (float)$tx['total_harga']; }
$qtyAll = array_sum(array_map(fn($d) => (int)$d['jumlah'], $details));

$toko = db_one('SELECT * FROM pengaturan WHERE id = 1 LIMIT 1') ?: [];
$namaToko   = $toko['nama_toko']    ?? 'PlantHub';
$namaCabang = $toko['nama_cabang']  ?? 'Cabang Utama';
$telpToko   = $toko['no_telepon']   ?? '-';
$emailToko  = $toko['email_toko']   ?? '-';
$alamatToko = $toko['alamat_toko']  ?? '-';
$catatanNota = $toko['catatan_nota'] ?? 'Terima kasih telah berbelanja di PlantHub!';

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$noteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['REQUEST_URI']), '/\\') . '/note.php?id=' . $id;
$qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($noteUrl);
$waUrl   = 'https://wa.me/?text=' . urlencode("Nota PlantHub {$tx['kode_transaksi']}:\n$noteUrl");

$statusCls = [
    'Diproses' => 'bg-amber-100 text-amber-700', 'Dikirim' => 'bg-blue-100 text-blue-700',
    'Selesai'  => 'bg-emerald-100 text-emerald-700', 'Batal' => 'bg-rose-100 text-rose-700',
][$tx['status']] ?? 'bg-stone-100 text-stone-600';

layout_start(['title' => 'Nota ' . $tx['kode_transaksi'], 'active' => 'riwayat', 'width' => 'max-w-4xl']);
?>
<style>
@media print {
    main { padding: 0 !important; }
    .print-container { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
    .watermark { display: flex !important; }
    @page { margin: 1cm; }
}
</style>

<nav class="no-print flex items-center gap-1.5 text-xs text-stone-500">
    <a href="dashboard.php" class="hover:text-[#2E7D32]">Beranda</a><i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="riwayat.php" class="hover:text-[#2E7D32]">Pesanan</a><i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-stone-800 font-semibold">Nota</span>
</nav>

<div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Nota Pesanan</h1>
        <p class="text-sm text-stone-500 mt-0.5">Bukti transaksi digital <?= e($tx['kode_transaksi']) ?>.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="riwayat.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 hover:bg-stone-50 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 transition"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
        <button type="button" onclick="copyLink()" class="inline-flex items-center gap-2 bg-white border border-stone-300 hover:bg-stone-50 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 transition"><i data-lucide="link" class="w-4 h-4"></i><span id="copyLinkText">Salin Link</span></button>
        <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-emerald-700 transition"><i data-lucide="message-circle" class="w-4 h-4"></i> Bagikan WA</a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm"><i data-lucide="printer" class="w-4 h-4"></i> Cetak / PDF</button>
    </div>
</div>

<article class="print-container relative bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden fade-up">
    <?php if ($tx['status'] === 'Batal'): ?>
    <div class="watermark absolute inset-0 items-center justify-center pointer-events-none z-10 flex"><span class="text-6xl sm:text-8xl font-extrabold text-rose-500/15 -rotate-12 border-8 border-rose-500/15 rounded-3xl px-6">DIBATALKAN</span></div>
    <?php elseif ($tx['status'] === 'Selesai'): ?>
    <div class="watermark absolute inset-0 items-center justify-center pointer-events-none z-10 flex"><span class="text-6xl sm:text-8xl font-extrabold text-emerald-500/10 -rotate-12 border-8 border-emerald-500/10 rounded-3xl px-6">LUNAS</span></div>
    <?php endif; ?>

    <div class="h-2 bg-gradient-to-r from-[#1E7D32] via-[#2E7D32] to-emerald-400"></div>

    <div class="p-6 sm:p-8 border-b border-stone-100">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-11 h-11 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white"><i data-lucide="sprout" class="w-6 h-6"></i></div>
                    <div><h2 class="text-xl font-bold leading-none"><?= e($namaToko) ?></h2><p class="text-[11px] text-stone-500 mt-1"><?= e($namaCabang) ?></p></div>
                </div>
                <p class="text-[11px] text-stone-500 leading-relaxed mt-2"><?= nl2br(e($alamatToko)) ?><br>Telp: <?= e($telpToko) ?> · Email: <?= e($emailToko) ?></p>
            </div>
            <div class="flex sm:flex-col items-start sm:items-end justify-between gap-3">
                <div class="sm:text-right">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Nota Transaksi</p>
                    <p class="text-lg font-bold font-mono mt-0.5"><?= e($tx['kode_transaksi']) ?></p>
                    <span class="inline-flex px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide mt-1 <?= $statusCls ?>"><?= e($tx['status']) ?></span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <img src="<?= e($qrUrl) ?>" alt="QR Nota" class="w-20 h-20 rounded-lg border border-stone-200 p-1 bg-white">
                    <p class="text-[8px] text-stone-400 text-center leading-tight">Scan untuk<br>membuka nota</p>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6 sm:p-8 grid sm:grid-cols-2 gap-6 border-b border-stone-100">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400 mb-2">Dikirim Ke</p>
            <p class="text-sm font-bold"><?= e($tx['nama_penerima'] ?? '-') ?></p>
            <p class="text-xs text-stone-600 mt-1"><?= e($tx['telepon'] ?? '-') ?></p>
            <p class="text-xs text-stone-600 mt-1 leading-relaxed"><?= nl2br(e($tx['alamat'] ?? '-')) ?></p>
        </div>
        <div class="sm:text-right space-y-1 text-xs text-stone-600">
            <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400 mb-2">Detail Transaksi</p>
            <p><b class="text-stone-800">Tanggal:</b> <?= tgl_id($tx['created_at'], true) ?> WIB</p>
            <p><b class="text-stone-800">Metode:</b> <?= e(metode_label((string)($tx['metode_pembayaran'] ?? ''))) ?></p>
            <?php if (!empty($tx['catatan'])): ?><p><b class="text-stone-800">Catatan:</b> <?= e($tx['catatan']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="p-6 sm:p-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead><tr class="border-b border-stone-200 text-[10px] font-bold uppercase tracking-wider text-stone-500">
                    <th class="pb-3 pr-2 w-8">No</th><th class="pb-3 px-2">Produk</th><th class="pb-3 px-2 text-center w-16">Qty</th>
                    <th class="pb-3 px-2 text-right w-28">Harga</th><th class="pb-3 pl-2 text-right w-32">Subtotal</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                <?php if (!$details): ?>
                    <tr><td colspan="5" class="py-6 text-center text-xs text-stone-400 italic">Tidak ada detail produk.</td></tr>
                <?php else: $no = 1; foreach ($details as $d): ?>
                    <tr>
                        <td class="py-3 pr-2 text-stone-500 text-xs"><?= $no++ ?></td>
                        <td class="py-3 px-2"><div class="flex items-center gap-2.5">
                            <img src="<?= e(produk_img($d['gambar'] ?? '', 80)) ?>" alt="" class="w-9 h-9 rounded-lg object-cover bg-stone-100 no-print">
                            <p class="font-bold text-xs"><?= e($d['nama_tanaman'] ?? 'Produk dihapus') ?></p></div></td>
                        <td class="py-3 px-2 text-center text-xs font-semibold"><?= (int)$d['jumlah'] ?></td>
                        <td class="py-3 px-2 text-right text-xs"><?= rupiah($d['harga_satuan']) ?></td>
                        <td class="py-3 pl-2 text-right text-xs font-bold"><?= rupiah($d['subtotal']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <div class="w-full sm:w-72 space-y-2 text-sm">
                <div class="flex justify-between text-stone-600"><span>Subtotal (<?= (int)$qtyAll ?> item)</span><span class="font-semibold"><?= rupiah($subtotal) ?></span></div>
                <div class="flex justify-between text-stone-600"><span>PPN <?= (int)(PPN_RATE * 100) ?>%</span><span class="font-semibold"><?= rupiah($ppn) ?></span></div>
                <div class="pt-3 border-t border-stone-200 flex justify-between items-baseline"><span class="font-bold">Total Bayar</span><span class="text-xl font-bold text-[#2E7D32]"><?= rupiah($total) ?></span></div>
            </div>
        </div>
    </div>

    <div class="p-6 sm:p-8 border-t border-stone-100 bg-stone-50/60">
        <p class="text-[11px] text-stone-500 leading-relaxed text-center italic"><?= nl2br(e($catatanNota)) ?></p>
        <p class="text-[9px] text-stone-400 text-center mt-3">Dokumen ini dicetak dari sistem PlantHub pada <?= date('d M Y, H:i:s') ?> WIB</p>
    </div>
</article>

<div class="no-print flex items-center justify-between gap-3 pt-2">
    <a href="riwayat.php" class="inline-flex items-center gap-2 text-xs font-semibold text-stone-500 hover:text-stone-800"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Pesanan</a>
    <a href="chat.php" class="inline-flex items-center gap-2 text-xs font-semibold text-[#2E7D32] hover:underline"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Ada pertanyaan soal pesanan ini?</a>
</div>

<?php
$linkJs = json_encode($noteUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$GLOBALS['__extra_js'] = <<<JS
<script>
function copyLink() {
    const link = $linkJs, t = document.getElementById('copyLinkText');
    const done = () => { t.textContent = 'Tersalin!'; setTimeout(() => t.textContent = 'Salin Link', 2000); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(link).then(done).catch(() => fallback(link, done)); }
    else fallback(link, done);
}
function fallback(text, done) {
    const ta = document.createElement('textarea'); ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { prompt('Salin link berikut:', text); }
    ta.remove();
}
</script>
JS;
layout_end(); ?>
