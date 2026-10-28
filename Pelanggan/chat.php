<?php
require_once __DIR__ . '/customer_init.php';

$uid  = user_id();
$MAXLEN = 2000;

/** Render isi kotak chat (dipakai halaman penuh & polling AJAX). */
function render_chat_messages(array $msgs, int $editId): string {
    ob_start();
    if (!$msgs): ?>
        <div class="text-center py-12">
            <div class="w-14 h-14 bg-white border border-stone-200 rounded-full flex items-center justify-center mx-auto mb-3"><i data-lucide="message-square" class="w-6 h-6 text-stone-400"></i></div>
            <p class="text-sm font-semibold text-stone-700">Belum ada percakapan</p>
            <p class="text-xs text-stone-500 mt-1">Kirim pesan pertamamu ke admin, atau pilih pertanyaan cepat di bawah.</p>
        </div>
    <?php else:
        $lastDay = '';
        foreach ($msgs as $m):
            $isAdmin = $m['sender_type'] === 'admin';
            $mid     = (int)$m['id'];
            $ts      = strtotime($m['created_at']);
            $day     = date('Y-m-d', $ts);
            $isEdit  = ($editId === $mid && !$isAdmin);
            $hasImg  = !empty($m['attachment']) && is_file(PATH_CHAT . basename($m['attachment']));
            if ($day !== $lastDay):
                $lastDay = $day;
                $label = $day === date('Y-m-d') ? 'Hari ini' : ($day === date('Y-m-d', strtotime('-1 day')) ? 'Kemarin' : tgl_id($ts)); ?>
                <div class="flex justify-center"><span class="text-[10px] font-semibold text-stone-500 bg-white border border-stone-200 rounded-full px-3 py-1 shadow-sm"><?= e($label) ?></span></div>
            <?php endif; ?>
            <div class="flex <?= $isAdmin ? 'justify-start' : 'justify-end' ?> gap-2" id="msg-<?= $mid ?>">
                <?php if ($isAdmin): ?><div class="w-7 h-7 rounded-full bg-emerald-100 text-[#2E7D32] flex items-center justify-center shrink-0 mt-auto"><i data-lucide="headphones" class="w-3.5 h-3.5"></i></div><?php endif; ?>
                <div class="max-w-[80%] sm:max-w-md md:max-w-lg">
                <?php if ($isEdit): ?>
                    <form method="post" class="bg-white border-2 border-[#2E7D32] rounded-2xl p-3 shadow-sm space-y-2 w-full sm:w-96">
                        <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= $mid ?>">
                        <textarea name="new_message" rows="3" required maxlength="2000" class="w-full text-xs bg-stone-50 border border-stone-200 rounded-lg p-2.5 focus:outline-none focus:border-[#2E7D32] resize-none"><?= e($m['message']) ?></textarea>
                        <div class="flex items-center justify-end gap-2">
                            <a href="chat.php" class="px-3 py-1.5 text-[11px] font-semibold text-stone-600 hover:bg-stone-100 rounded-lg">Batal</a>
                            <button class="px-3 py-1.5 text-[11px] font-bold bg-[#2E7D32] text-white rounded-lg hover:bg-emerald-800">Simpan</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="p-3 rounded-2xl text-xs <?= $isAdmin ? 'bg-white text-stone-800 rounded-bl-none border border-stone-200/80 shadow-sm' : 'bg-[#2E7D32] text-white rounded-br-none shadow-sm' ?>">
                        <?php if ($hasImg): $src = URL_CHAT . rawurlencode(basename($m['attachment'])); ?>
                            <img src="<?= e($src) ?>" alt="Lampiran" loading="lazy" class="mb-2 max-w-full max-h-64 rounded-lg cursor-zoom-in hover:opacity-90 transition" onclick="openLightbox(this.src)">
                        <?php endif; ?>
                        <?php if ($m['message'] !== '' && $m['message'] !== null): ?><p class="leading-relaxed whitespace-pre-wrap break-words"><?= e($m['message']) ?></p><?php endif; ?>
                        <div class="flex items-center justify-end gap-1.5 mt-1.5 <?= $isAdmin ? 'text-stone-400' : 'text-emerald-100' ?>">
                            <?php if (!empty($m['edited_at'])): ?><span class="text-[9px] italic">(diedit)</span><?php endif; ?>
                            <span class="text-[9px]"><?= date('H:i', $ts) ?></span>
                            <?php if (!$isAdmin): ?><i data-lucide="<?= !empty($m['is_read']) ? 'check-check' : 'check' ?>" class="w-3 h-3"></i><?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$isAdmin): ?>
                    <div class="flex items-center justify-end gap-1 mt-1">
                        <?php if (!$hasImg && $m['message']): ?><a href="chat.php?edit=<?= $mid ?>" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-stone-600 hover:bg-stone-100 rounded-md border border-stone-200"><i data-lucide="edit-3" class="w-3 h-3"></i> Edit</a><?php endif; ?>
                        <form method="post" data-confirm="Hapus pesan ini?" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="hapus"><input type="hidden" name="id" value="<?= $mid ?>">
                            <button class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold text-rose-600 hover:text-white hover:bg-rose-500 rounded-md border border-rose-200 transition"><i data-lucide="trash-2" class="w-3 h-3"></i> Hapus</button></form>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            </div>
        <?php endforeach;
    endif;
    return ob_get_clean();
}

function load_chat_messages(int $uid): array {
    return db_all("SELECT * FROM chats WHERE user_id = ? AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY created_at ASC, id ASC", 'i', [$uid]);
}

// =====================================================
// POLLING AJAX (GET ?ajax=1) -> hanya isi kotak chat
// =====================================================
if (!is_post() && ($_GET['ajax'] ?? '') === '1') {
    db_exec("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0", 'i', [$uid]);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo render_chat_messages(load_chat_messages($uid), 0);
    exit;
}

// =====================================================
// AKSI POST
// =====================================================
if (is_post()) {
    csrf_require('chat.php');
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'kirim') {
            $msg = trim($_POST['message'] ?? '');
            if (mb_strlen($msg) > $MAXLEN) throw new UserError("Pesan maksimal $MAXLEN karakter.");
            $err = null;
            $att = save_image_upload($_FILES['attachment'] ?? null, PATH_CHAT, 'chat', 3 * 1024 * 1024, ['jpg', 'png', 'webp', 'gif'], $err);
            if ($err) throw new UserError($err);
            if ($msg === '' && !$att) throw new UserError('Ketik pesan atau lampirkan gambar terlebih dahulu.');
            db_insert("INSERT INTO chats (user_id, sender_type, message, attachment, is_read, created_at) VALUES (?, 'customer', ?, ?, 0, NOW())", 'iss', [$uid, $msg, $att]);
        }
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0); $new = trim($_POST['new_message'] ?? '');
            if ($new === '') throw new UserError('Pesan tidak boleh kosong.');
            if (mb_strlen($new) > $MAXLEN) throw new UserError("Pesan maksimal $MAXLEN karakter.");
            $n = db_exec("UPDATE chats SET message = ?, edited_at = NOW() WHERE id = ? AND user_id = ? AND sender_type = 'customer' AND (is_deleted = 0 OR is_deleted IS NULL)", 'sii', [$new, $id, $uid]);
            if ($n < 1) throw new UserError('Pesan tidak dapat diedit.');
            flash('success', 'Pesan diperbarui.');
        }
        elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            $row = db_one("SELECT attachment FROM chats WHERE id = ? AND user_id = ? AND sender_type = 'customer' LIMIT 1", 'ii', [$id, $uid]);
            if (!$row) throw new UserError('Pesan tidak ditemukan.');
            db_exec("UPDATE chats SET is_deleted = 1 WHERE id = ? AND user_id = ? AND sender_type = 'customer'", 'ii', [$id, $uid]);
            if (!empty($row['attachment'])) @unlink(PATH_CHAT . basename($row['attachment']));
            flash('info', 'Pesan dihapus.');
        }
    } catch (UserError $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('CHAT: ' . $e->getMessage());
        flash('error', 'Terjadi kendala, silakan coba lagi.');
    }
    redirect('chat.php');
}

// =====================================================
// DATA HALAMAN
// =====================================================
$messages = load_chat_messages($uid);
db_exec("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0", 'i', [$uid]);
$editId = (int)($_GET['edit'] ?? 0);

// Konteks produk (?produk=ID)
$prod = null; $prefill = '';
$pid = (int)($_GET['produk'] ?? 0);
if ($pid > 0) {
    $prod = db_one('SELECT id, nama_tanaman, harga_jual, gambar FROM produk WHERE id = ? LIMIT 1', 'i', [$pid]);
    if ($prod) $prefill = 'Halo admin, saya ingin bertanya tentang "' . $prod['nama_tanaman'] . '". ';
}

$lastAdmin = db_val("SELECT MAX(created_at) FROM chats WHERE user_id = ? AND sender_type = 'admin' AND (is_deleted = 0 OR is_deleted IS NULL)", 'i', [$uid], null);

$quick = [
    ['leaf',        'Daun tanaman saya menguning, kenapa ya?'],
    ['droplet',     'Berapa kali sehari sebaiknya disiram?'],
    ['sun',         'Apakah butuh sinar matahari langsung?'],
    ['package',     'Apakah bisa dikirim ke luar kota?'],
    ['help-circle', 'Bagaimana cara perawatan yang benar?'],
    ['receipt',     'Saya ingin menanyakan status pesanan saya'],
];

layout_start(['title' => 'Konsultasi', 'active' => 'chat', 'width' => 'max-w-5xl']);
?>
<style>
.lightbox{position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:100;display:none;align-items:center;justify-content:center;padding:1rem}
.lightbox.active{display:flex}.lightbox img{max-width:100%;max-height:90vh;border-radius:.5rem}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}.pulse-dot{animation:pulse-dot 2s ease-in-out infinite}
</style>

<div class="flex items-end justify-between gap-3 fade-up">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Konsultasi</h1>
        <p class="text-sm text-stone-500 mt-0.5">Tanyakan perawatan tanaman atau kirim foto kondisi tanamanmu.</p>
    </div>
    <button type="button" id="btnAuto" onclick="toggleAuto()" class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1.5 rounded-full border border-emerald-200 bg-emerald-50 text-[#2E7D32] shrink-0">
        <span id="autoDot" class="pulse-dot w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span><span id="autoLabel">Auto 10s</span>
    </button>
</div>

<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm flex flex-col overflow-hidden h-[calc(100vh-15rem)] min-h-[440px] fade-up">
    <div class="px-5 py-3.5 border-b border-stone-100 flex items-center gap-3 shrink-0">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-[#2E7D32]"><i data-lucide="headphones" class="w-5 h-5"></i></div>
        <div class="min-w-0">
            <p class="text-sm font-bold">Customer Care PlantHub</p>
            <p class="text-[11px] text-stone-500 truncate">
                <?= $lastAdmin ? 'Balasan terakhir ' . e(waktu_lalu($lastAdmin)) : 'Admin membalas pada jam layanan' ?> · jam layanan 08.00–21.00 WIB
            </p>
        </div>
    </div>

    <div id="chat-box" class="flex-1 p-4 sm:p-5 overflow-y-auto space-y-4 bg-stone-50/40"><?= render_chat_messages($messages, $editId) ?></div>

    <div class="px-3 pt-2 pb-1 bg-white border-t border-stone-100 flex items-center gap-2 overflow-x-auto no-scrollbar shrink-0">
        <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider whitespace-nowrap shrink-0">Cepat</span>
        <?php foreach ($quick as [$ic, $tx]): ?>
            <button type="button" data-quick="<?= e($tx) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium text-stone-600 bg-stone-100 hover:bg-emerald-50 hover:text-[#2E7D32] rounded-lg whitespace-nowrap transition shrink-0"><i data-lucide="<?= $ic ?>" class="w-3 h-3"></i><?= e($tx) ?></button>
        <?php endforeach; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="p-3 bg-white border-t border-stone-100 flex flex-col gap-2 shrink-0" autocomplete="off" id="formChat">
        <?= csrf_field() ?><input type="hidden" name="action" value="kirim">

        <?php if ($prod): ?>
        <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-xl p-2 pr-3">
            <img src="<?= e(produk_img($prod['gambar'], 100)) ?>" alt="" class="w-10 h-10 rounded-lg object-cover">
            <div class="min-w-0 flex-1"><p class="text-[10px] font-bold uppercase text-[#2E7D32] tracking-wider">Menanyakan produk</p><p class="text-xs font-semibold truncate"><?= e($prod['nama_tanaman']) ?> · <?= rupiah($prod['harga_jual']) ?></p></div>
            <a href="chat.php" class="text-stone-400 hover:text-stone-700" title="Hapus konteks"><i data-lucide="x" class="w-4 h-4"></i></a>
        </div>
        <?php endif; ?>

        <div id="previewWrap" class="hidden">
            <div class="relative inline-block">
                <img id="previewImg" src="" alt="" class="h-20 rounded-lg border-2 border-[#2E7D32] object-cover">
                <button type="button" onclick="batalPilihGambar()" class="absolute -top-2 -right-2 w-6 h-6 bg-rose-600 hover:bg-rose-700 text-white rounded-full flex items-center justify-center shadow-md"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
            </div>
            <p id="previewInfo" class="text-[10px] text-stone-500 mt-1"></p>
        </div>

        <div class="flex gap-2">
            <label class="inline-flex items-center justify-center bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-xl w-11 h-11 cursor-pointer transition shrink-0" title="Lampirkan gambar">
                <i data-lucide="image-plus" class="w-5 h-5"></i>
                <input type="file" name="attachment" id="inAttachment" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="previewGambar(event)">
            </label>
            <input type="text" name="message" id="inPesan" maxlength="<?= $MAXLEN ?>" value="<?= e($prefill) ?>" placeholder="Ketik pesan atau lampirkan gambar…" class="flex-1 min-w-0 px-4 py-2.5 text-xs bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100 transition">
            <button type="submit" id="btnKirim" class="bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 sm:px-5 py-2.5 rounded-xl text-xs font-bold transition inline-flex items-center gap-2 shrink-0 active:scale-95"><span class="hidden sm:inline">Kirim</span><i data-lucide="send" class="w-3.5 h-3.5"></i></button>
        </div>
        <p class="text-[10px] text-stone-400">Maks. 3MB per gambar · JPG, PNG, WEBP, GIF.</p>
    </form>
</section>

<div id="lightbox" class="lightbox no-print" onclick="this.classList.remove('active')"><img id="lightboxImg" src="" alt="Pratinjau"></div>

<?php
$edit = (int)$editId;
$GLOBALS['__extra_js'] = <<<JS
<script>
const box = document.getElementById('chat-box');
const editAktif = $edit;
let timer = null, autoOn = true, lastHtml = box.innerHTML;
box.scrollTop = box.scrollHeight;
if (editAktif) document.getElementById('msg-' + editAktif)?.scrollIntoView({block: 'center'});

function nearBottom() { return box.scrollHeight - box.scrollTop - box.clientHeight < 60; }
async function refreshChat() {
    if (editAktif || document.hidden) return;
    if (document.activeElement && document.activeElement.id === 'inPesan' && document.getElementById('inPesan').value) return;
    try {
        const r = await fetch('chat.php?ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
        if (!r.ok) return;
        const html = await r.text();
        if (html === lastHtml) return;
        const stick = nearBottom(), top = box.scrollTop;
        box.innerHTML = html; lastHtml = html;
        lucide.createIcons();
        box.scrollTop = stick ? box.scrollHeight : top;
    } catch (e) {}
}
function startAuto() { stopAuto(); timer = setInterval(refreshChat, 10000); }
function stopAuto() { if (timer) { clearInterval(timer); timer = null; } }
function toggleAuto() {
    autoOn = !autoOn;
    const b = document.getElementById('btnAuto');
    b.className = 'inline-flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1.5 rounded-full border shrink-0 ' + (autoOn ? 'border-emerald-200 bg-emerald-50 text-[#2E7D32]' : 'border-stone-200 bg-stone-100 text-stone-500');
    document.getElementById('autoDot').classList.toggle('pulse-dot', autoOn);
    document.getElementById('autoDot').className = (autoOn ? 'pulse-dot ' : '') + 'w-1.5 h-1.5 rounded-full ' + (autoOn ? 'bg-[#2E7D32]' : 'bg-stone-400');
    document.getElementById('autoLabel').textContent = autoOn ? 'Auto 10s' : 'Auto mati';
    autoOn ? startAuto() : stopAuto();
}
startAuto();
document.addEventListener('visibilitychange', () => { if (!document.hidden && autoOn) refreshChat(); });

function previewGambar(ev) {
    const f = ev.target.files[0]; if (!f) return;
    if (f.size > 3 * 1024 * 1024) { toast('Ukuran gambar melebihi 3MB.', 'error'); ev.target.value = ''; return; }
    const rd = new FileReader();
    rd.onload = (e) => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('previewInfo').textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
        document.getElementById('previewWrap').classList.remove('hidden');
    };
    rd.readAsDataURL(f);
}
function batalPilihGambar() { document.getElementById('inAttachment').value = ''; document.getElementById('previewWrap').classList.add('hidden'); }
function openLightbox(src) { document.getElementById('lightboxImg').src = src; document.getElementById('lightbox').classList.add('active'); }
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.getElementById('lightbox').classList.remove('active'); });
document.querySelectorAll('[data-quick]').forEach(b => b.addEventListener('click', () => { const i = document.getElementById('inPesan'); i.value = b.dataset.quick; i.focus(); }));

document.getElementById('formChat').addEventListener('submit', (e) => {
    const msg = document.getElementById('inPesan').value.trim(), file = document.getElementById('inAttachment').files.length;
    if (!msg && !file) { e.preventDefault(); toast('Ketik pesan atau lampirkan gambar terlebih dahulu.', 'warning'); return; }
    const b = document.getElementById('btnKirim'); b.style.pointerEvents = 'none'; b.style.opacity = '.7';
});
</script>
JS;
layout_end(); ?>
