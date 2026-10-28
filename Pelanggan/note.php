<?php
require_once __DIR__ . '/customer_init.php';

$uid = user_id();
$id  = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('riwayat.php');

// Hanya pemilik pesanan yang boleh melihat nota
$tx = db_one("SELECT * FROM transaksi WHERE id = ? AND user_id = ? AND jenis_transaksi = 'penjualan' LIMIT 1", 'ii', [$id, $uid]);
if (!$tx) { flash('error', 'Nota tidak ditemukan.'); redirect('riwayat.php'); }

$details = db_all("
    SELECT td.*, p.nama_tanaman, p.gambar
    FROM transaksi_detail td LEFT JOIN produk p ON td.produk_id = p.id
    WHERE td.transaksi_id = ? ORDER BY td.id ASC", 'i', [$id]);

$subtotal = 0.0;
foreach ($details as $d) $subtotal += (float)$d['subtotal'];
$total  = (float)$tx['total_harga'];          // total_harga sudah termasuk PPN saat checkout
$ppn    = max(0, $total - $subtotal);
$qtyAll = array_sum(array_map(fn($d) => (int)$d['jumlah'], $details));

$toko = db_one('SELECT * FROM pengaturan WHERE id = 1 LIMIT 1') ?: [];
$namaToko    = $toko['nama_toko']    ?? 'PlantHub';
$namaCabang  = $toko['nama_cabang']  ?? 'Cabang Utama';
$telpToko    = $toko['no_telepon']   ?? '-';
$emailToko   = $toko['email_toko']   ?? '-';
$alamatToko  = $toko['alamat_toko']  ?? '-';
$catatanNota = $toko['catatan_nota'] ?? 'Terima kasih telah berbelanja di PlantHub!';

$statusCls = [
    'Diproses' => 'bg-amber-100 text-amber-700',
    'Dikirim'  => 'bg-blue-100 text-blue-700',
    'Selesai'  => 'bg-emerald-100 text-emerald-700',
    'Batal'    => 'bg-rose-100 text-rose-700',
][$tx['status']] ?? 'bg-stone-100 text-stone-600';

// Nama file PDF, contoh: Nota-INV-20261002-65FE8.pdf
$namaFile = 'Nota-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string)$tx['kode_transaksi']) . '.pdf';

layout_start(['title' => 'Nota ' . $tx['kode_transaksi'], 'active' => 'riwayat', 'width' => 'max-w-4xl']);
?>

<nav class="no-print flex items-center gap-1.5 text-xs text-stone-500">
    <a href="dashboard.php" class="hover:text-[#2E7D32]">Beranda</a><i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="riwayat.php" class="hover:text-[#2E7D32]">Riwayat Order</a><i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-stone-800 font-semibold">Nota</span>
</nav>

<div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Nota Pesanan</h1>
        <p class="text-sm text-stone-500 mt-0.5">Bukti transaksi <?= e($tx['kode_transaksi']) ?>. Unduh sebagai struk PDF untuk disimpan di perangkatmu.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="riwayat.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 hover:bg-stone-50 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
        </a>
        <button type="button" id="btnPdf" onclick="downloadPdf()" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm disabled:opacity-60">
            <i data-lucide="download" class="w-4 h-4"></i> <span id="btnPdfText">Download PDF</span>
        </button>
    </div>
</div>

<!-- ====== TAMPILAN NOTA DI LAYAR (PDF yang diunduh berbentuk struk, lihat #strukArea di bawah) ====== -->
<article class="relative bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden fade-up">

    <?php if ($tx['status'] === 'Batal'): ?>
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
        <span class="text-6xl sm:text-8xl font-extrabold text-rose-500/15 -rotate-12 border-8 border-rose-500/15 rounded-3xl px-6">DIBATALKAN</span>
    </div>
    <?php elseif ($tx['status'] === 'Selesai'): ?>
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
        <span class="text-6xl sm:text-8xl font-extrabold text-emerald-500/10 -rotate-12 border-8 border-emerald-500/10 rounded-3xl px-6">LUNAS</span>
    </div>
    <?php endif; ?>

    <div class="h-2 bg-gradient-to-r from-[#1E7D32] via-[#2E7D32] to-emerald-400"></div>

    <!-- Kepala nota -->
    <div class="p-6 sm:p-8 border-b border-stone-100">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-2">
                    <div class="w-11 h-11 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                        <i data-lucide="sprout" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold leading-none"><?= e($namaToko) ?></h2>
                        <p class="text-[11px] text-stone-500 mt-1"><?= e($namaCabang) ?></p>
                    </div>
                </div>
                <p class="text-[11px] text-stone-500 leading-relaxed mt-2">
                    <?= nl2br(e($alamatToko)) ?><br>
                    Telp: <?= e($telpToko) ?> &middot; Email: <?= e($emailToko) ?>
                </p>
            </div>
            <div class="sm:text-right">
                <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Nota Transaksi</p>
                <p class="text-lg font-bold font-mono mt-0.5"><?= e($tx['kode_transaksi']) ?></p>
                <span class="inline-flex px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide mt-1 <?= $statusCls ?>"><?= e($tx['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- Penerima & detail -->
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
            <?php if (!empty($tx['catatan'])): ?>
                <p><b class="text-stone-800">Catatan:</b> <?= e($tx['catatan']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel produk -->
    <div class="p-6 sm:p-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-stone-200 text-[10px] font-bold uppercase tracking-wider text-stone-500">
                        <th class="pb-3 pr-2 w-8">No</th>
                        <th class="pb-3 px-2">Produk</th>
                        <th class="pb-3 px-2 text-center w-16">Qty</th>
                        <th class="pb-3 px-2 text-right w-28">Harga</th>
                        <th class="pb-3 pl-2 text-right w-32">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                <?php if (!$details): ?>
                    <tr><td colspan="5" class="py-6 text-center text-xs text-stone-400 italic">Tidak ada detail produk.</td></tr>
                <?php else: $no = 1; foreach ($details as $d): ?>
                    <tr>
                        <td class="py-3 pr-2 text-stone-500 text-xs"><?= $no++ ?></td>
                        <td class="py-3 px-2">
                            <div class="flex items-center gap-2.5">
                                <img src="<?= e(produk_img($d['gambar'] ?? '', 80)) ?>" alt="" class="w-9 h-9 rounded-lg object-cover bg-stone-100">
                                <p class="font-bold text-xs"><?= e($d['nama_tanaman'] ?? 'Produk dihapus') ?></p>
                            </div>
                        </td>
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
                <div class="pt-3 border-t border-stone-200 flex justify-between items-baseline">
                    <span class="font-bold">Total Bayar</span>
                    <span class="text-xl font-bold text-[#2E7D32]"><?= rupiah($total) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Catatan kaki -->
    <div class="p-6 sm:p-8 border-t border-stone-100 bg-stone-50/60">
        <p class="text-[11px] text-stone-500 leading-relaxed text-center italic"><?= nl2br(e($catatanNota)) ?></p>
        <p class="text-[9px] text-stone-400 text-center mt-3">Dokumen ini dibuat dari sistem PlantHub pada <?= date('d M Y, H:i:s') ?> WIB</p>
    </div>
</article>

<div class="no-print flex items-center justify-between gap-3 pt-2">
    <a href="riwayat.php" class="inline-flex items-center gap-2 text-xs font-semibold text-stone-500 hover:text-stone-800"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Riwayat</a>
    <a href="chat.php" class="inline-flex items-center gap-2 text-xs font-semibold text-[#2E7D32] hover:underline"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Ada pertanyaan soal pesanan ini?</a>
</div>

<?php
// ===== DATA STRUK (dipakai untuk file PDF yang diunduh) =====
$strukTanggal = date('d/m/Y H:i', strtotime($tx['created_at']));
$mono = "font-family:'Courier New',Courier,monospace;";
$dash = 'border-top:1px dashed #000;margin:7px 0;';
?>
<!-- STRUK TERSEMBUNYI: hanya dipakai untuk membuat PDF (lebar 80mm seperti struk kasir) -->
<div aria-hidden="true" style="position:absolute;left:-10000px;top:0;">
    <div id="strukArea" style="width:302px;box-sizing:border-box;padding:14px 12px 16px;background:#fff;color:#000;font-size:11px;line-height:1.45;<?= $mono ?>">
        <div style="text-align:center;">
            <div style="font-size:16px;font-weight:bold;letter-spacing:1px;"><?= e(strtoupper($namaToko)) ?></div>
            <div style="font-size:10px;">Sistem Manajemen Toko Tanaman</div>
            <div style="font-size:9px;margin-top:2px;"><?= e($namaCabang) ?></div>
            <div style="font-size:9px;"><?= e($alamatToko) ?></div>
            <div style="font-size:9px;">Telp: <?= e($telpToko) ?></div>
        </div>
        <div style="<?= $dash ?>"></div>

        <div style="display:flex;justify-content:space-between;"><span>Kode</span><span><?= e($tx['kode_transaksi']) ?></span></div>
        <div style="display:flex;justify-content:space-between;"><span>Tanggal</span><span><?= e($strukTanggal) ?></span></div>
        <div style="display:flex;justify-content:space-between;"><span>Kasir</span><span>Online</span></div>
        <div style="display:flex;justify-content:space-between;gap:8px;"><span>Pelanggan</span><span style="text-align:right;"><?= e($tx['nama_penerima'] ?? '-') ?></span></div>
        <div style="<?= $dash ?>"></div>

        <?php if (!$details): ?>
            <div style="text-align:center;">Tidak ada detail produk.</div>
        <?php else: foreach ($details as $d): ?>
            <div style="font-weight:bold;"><?= e($d['nama_tanaman'] ?? 'Produk dihapus') ?></div>
            <div style="display:flex;justify-content:space-between;padding-left:6px;margin-bottom:3px;">
                <span><?= (int)$d['jumlah'] ?> x <?= rupiah($d['harga_satuan']) ?></span>
                <span><?= rupiah($d['subtotal']) ?></span>
            </div>
        <?php endforeach; endif; ?>
        <div style="<?= $dash ?>"></div>

        <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span><?= rupiah($subtotal) ?></span></div>
        <div style="display:flex;justify-content:space-between;"><span>PPN <?= (int)(PPN_RATE * 100) ?>%</span><span><?= rupiah($ppn) ?></span></div>
        <div style="border-top:1px solid #000;margin:6px 0 4px;"></div>
        <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:bold;"><span>TOTAL</span><span><?= rupiah($total) ?></span></div>
        <div style="<?= $dash ?>"></div>

        <div style="display:flex;justify-content:space-between;"><span>Metode</span><span><?= e(strtoupper(metode_label((string)($tx['metode_pembayaran'] ?? '')))) ?></span></div>
        <div style="display:flex;justify-content:space-between;"><span>Status</span><span><?= e($tx['status']) ?></span></div>
        <?php if (!empty($tx['alamat'])): ?>
            <div style="<?= $dash ?>"></div>
            <div style="font-size:10px;">Dikirim ke:</div>
            <div style="font-size:10px;"><?= e($tx['nama_penerima'] ?? '') ?> (<?= e($tx['telepon'] ?? '-') ?>)</div>
            <div style="font-size:10px;word-break:break-word;"><?= e($tx['alamat']) ?></div>
        <?php endif; ?>
        <?php if (!empty($tx['catatan'])): ?>
            <div style="font-size:10px;margin-top:3px;">Catatan: <?= e($tx['catatan']) ?></div>
        <?php endif; ?>
        <div style="<?= $dash ?>"></div>

        <div style="text-align:center;font-size:10px;">
            <div><?= e($catatanNota) ?></div>
            <div style="margin-top:4px;">~ <?= e($namaToko) ?> ~</div>
        </div>
        <div id="strukBarcode" style="text-align:center;margin-top:10px;min-height:4px;"></div>
    </div>
</div>

<?php
$fileJs = json_encode($namaFile, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$kodeJs = json_encode((string)$tx['kode_transaksi'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$GLOBALS['__extra_js'] = <<<JS
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<script>
const KODE_NOTA = $kodeJs;

// Barcode CODE128 dari kode transaksi (bukan link, jadi tidak terikat localhost)
function buatBarcode() {
    const wrap = document.getElementById('strukBarcode');
    if (!wrap || wrap.dataset.done) return;
    try {
        if (typeof JsBarcode === 'undefined') return;
        const c = document.createElement('canvas');
        JsBarcode(c, KODE_NOTA, { format: 'CODE128', width: 1.6, height: 48, displayValue: true,
                                  fontSize: 12, font: 'Courier New', margin: 4, background: '#ffffff', lineColor: '#000000' });
        const img = document.createElement('img');
        img.src = c.toDataURL('image/png');            // data URL: aman untuk html2canvas
        img.style.cssText = 'max-width:100%;height:auto;display:inline-block;';
        wrap.appendChild(img);
        wrap.dataset.done = '1';
    } catch (e) { /* tanpa barcode pun struk tetap dibuat */ }
}

function downloadPdf() {
    const btn = document.getElementById('btnPdf'), txt = document.getElementById('btnPdfText'), struk = document.getElementById('strukArea');
    if (typeof html2pdf === 'undefined') { toast('Pustaka PDF belum termuat. Periksa koneksi internet lalu coba lagi.', 'error'); return; }

    btn.disabled = true; txt.textContent = 'Membuat PDF…';
    buatBarcode();

    // Tinggi kertas mengikuti panjang struk (lebar tetap 80 mm seperti kertas thermal)
    const tinggiMm = Math.ceil(struk.offsetHeight * 25.4 / 96) + 4;

    html2pdf().set({
        margin:       0,
        filename:     $fileJs,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 3, useCORS: true, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: [80, tinggiMm], orientation: 'portrait' },
        pagebreak:    { mode: [] }
    }).from(struk).save().then(() => {
        toast('Struk berhasil diunduh.', 'success');
    }).catch(() => {
        toast('Gagal membuat PDF. Silakan coba lagi.', 'error');
    }).finally(() => {
        btn.disabled = false; txt.textContent = 'Download PDF';
    });
}
window.addEventListener('load', buatBarcode);
</script>
JS;
layout_end(); ?>