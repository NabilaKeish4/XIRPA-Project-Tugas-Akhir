<?php
require_once __DIR__ . '/customer_init.php';

$uid   = user_id();
$error = '';
$errTab = '';
$user  = current_user(true);
$form  = ['nama_lengkap' => $user['nama_lengkap'] ?? '', 'no_hp' => $user['no_hp'] ?? '', 'alamat' => $user['alamat'] ?? ''];

if (is_post()) {
    csrf_require('profil.php');
    $action = $_POST['action'] ?? '';

    // ---- Hapus foto ----
    if ($action === 'hapus_foto') {
        $old = $user['foto_profil'] ?? '';
        if ($old && $old !== 'default_avatar.jpg') @unlink(PATH_AVATAR . basename($old));
        db_exec("UPDATE users SET foto_profil = 'default_avatar.jpg' WHERE id = ?", 'i', [$uid]);
        flash('info', 'Foto profil dikembalikan ke default.');
        redirect('profil.php');
    }

    // ---- Update profil ----
    if ($action === 'update_profil') {
        $form = [
            'nama_lengkap' => trim($_POST['nama_lengkap'] ?? ''),
            'no_hp'        => trim($_POST['no_hp'] ?? ''),
            'alamat'       => trim($_POST['alamat'] ?? ''),
        ];
        $errTab = 'profil';
        if ($form['nama_lengkap'] === '')                    $error = 'Nama tidak boleh kosong.';
        elseif (mb_strlen($form['nama_lengkap']) > 100)      $error = 'Nama maksimal 100 karakter.';
        elseif ($form['no_hp'] !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $form['no_hp'])) $error = 'Nomor HP tidak valid (8–20 digit).';
        elseif (mb_strlen($form['alamat']) > 500)            $error = 'Alamat maksimal 500 karakter.';
        else {
            $err = null;
            $foto = save_image_upload($_FILES['foto_profil'] ?? null, PATH_AVATAR, 'avatar', 2 * 1024 * 1024, ['jpg', 'png', 'webp'], $err);
            if ($err) $error = $err;
            else {
                try {
                    if ($foto) {
                        $old = $user['foto_profil'] ?? '';
                        db_exec('UPDATE users SET nama_lengkap=?, no_hp=?, alamat=?, foto_profil=? WHERE id=?', 'ssssi', [$form['nama_lengkap'], $form['no_hp'], $form['alamat'], $foto, $uid]);
                        if ($old && $old !== 'default_avatar.jpg') @unlink(PATH_AVATAR . basename($old));
                    } else {
                        db_exec('UPDATE users SET nama_lengkap=?, no_hp=?, alamat=? WHERE id=?', 'sssi', [$form['nama_lengkap'], $form['no_hp'], $form['alamat'], $uid]);
                    }
                    $_SESSION['nama_user'] = $form['nama_lengkap'];
                    flash('success', 'Profil berhasil diperbarui.');
                    redirect('profil.php');
                } catch (mysqli_sql_exception | RuntimeException $e) {
                    if ($foto) @unlink(PATH_AVATAR . $foto);
                    error_log('PROFIL: ' . $e->getMessage());
                    $error = 'Gagal menyimpan profil, coba lagi.';
                }
            }
        }
    }

    // ---- Ganti password ----
    if ($action === 'ganti_password') {
        $errTab = 'password';
        $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $konf = $_POST['konfirmasi'] ?? '';
        if (strlen($new) < 6)                                  $error = 'Password baru minimal 6 karakter.';
        elseif ($new !== $konf)                                $error = 'Konfirmasi password tidak cocok.';
        elseif ($new === $old)                                 $error = 'Password baru harus berbeda dari password lama.';
        elseif (!password_verify($old, $user['password'] ?? '')) $error = 'Password lama salah.';
        else {
            try {
                db_exec('UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), $uid]);
                session_regenerate_id(true);
                flash('success', 'Password berhasil diubah.');
                redirect('profil.php');
            } catch (mysqli_sql_exception | RuntimeException $e) {
                error_log('PASSWORD: ' . $e->getMessage());
                $error = 'Gagal mengganti password, coba lagi.';
            }
        }
    }
}

$user   = current_user(true);
$custom = avatar_is_custom($user);
$avatar = avatar_url($user, 160);
$stat   = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status<>'Batal' THEN total_harga ELSE 0 END),0) AS belanja
                  FROM transaksi WHERE user_id = ? AND jenis_transaksi='penjualan'", 'i', [$uid]) ?: ['n' => 0, 'belanja' => 0];
$wishN  = count(wishlist_ids($uid));
$lastLogin = !empty($user['last_login']) ? waktu_lalu($user['last_login']) : 'Belum tercatat';

$faq = [
    ['Bagaimana cara memesan tanaman?', 'Buka <b>Katalog</b>, klik <b>Keranjang</b> pada produk, lalu buka <b>Keranjang</b> → <b>Lanjut ke Checkout</b>. Isi alamat, pilih metode bayar, dan klik <b>Selesaikan Pesanan</b>. Ingin cepat? Pakai <b>Beli Sekarang</b> di halaman produk.'],
    ['Bagaimana cara melacak pesanan?', 'Buka menu <b>Pesanan</b>. Setiap pesanan punya pelacak status: Diproses → Dikirim → Selesai.'],
    ['Bagaimana cara membatalkan pesanan?', 'Pesanan berstatus <b>Diproses</b> bisa dibatalkan lewat tombol <b>Batalkan</b> di menu Pesanan. Stok produk otomatis dikembalikan.'],
    ['Bagaimana mencetak nota?', 'Di menu Pesanan klik <b>Lihat Nota</b>, lalu <b>Cetak / PDF</b>. Nota juga bisa dibagikan lewat WhatsApp atau link.'],
    ['Bagaimana cara konsultasi dengan admin?', 'Buka menu <b>Konsultasi</b>, tulis pertanyaan atau kirim foto tanamanmu, lalu klik Kirim. Admin membalas pada jam layanan.'],
    ['Bagaimana cara menyimpan tanaman favorit?', 'Tekan ikon <b>hati</b> pada produk. Semua favoritmu tersimpan di menu <b>Wishlist</b>.'],
];

$input = 'w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] focus:ring-2 focus:ring-emerald-100';

layout_start(['title' => 'Profil Saya', 'active' => 'profil', 'width' => 'max-w-4xl']);
?>

<div class="fade-up">
    <h1 class="text-2xl font-bold tracking-tight">Profil Saya</h1>
    <p class="text-sm text-stone-500 mt-0.5">Kelola data diri, alamat pengiriman, dan keamanan akunmu.</p>
</div>

<?php if ($error): ?>
<div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2"><i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i><span><?= e($error) ?></span></div>
<?php endif; ?>

<!-- KARTU PROFIL -->
<section class="relative overflow-hidden bg-white rounded-2xl border border-stone-200/80 shadow-sm fade-up">
    <div class="h-20 bg-gradient-to-r from-[#1E7D32] via-[#2E7D32] to-emerald-400"></div>
    <div class="px-6 pb-6 -mt-10 flex flex-col sm:flex-row items-center sm:items-end gap-4">
        <div class="relative shrink-0">
            <img id="previewAvatar" src="<?= e($avatar) ?>" alt="" class="w-24 h-24 rounded-2xl object-cover ring-4 ring-white bg-stone-50 shadow-md">
            <?php if ($custom): ?>
            <form method="post" data-confirm="Hapus foto profil dan kembalikan ke default?" class="absolute -top-1 -right-1">
                <?= csrf_field() ?><input type="hidden" name="action" value="hapus_foto">
                <button class="w-6 h-6 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center shadow-md" title="Hapus foto"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
            </form>
            <?php endif; ?>
        </div>
        <div class="flex-1 text-center sm:text-left sm:pb-1 min-w-0">
            <h2 class="text-xl font-bold truncate"><?= e($user['nama_lengkap'] ?? nama_user()) ?></h2>
            <p class="text-xs text-stone-500 truncate"><?= e($user['email'] ?? '') ?></p>
            <p class="text-xs text-stone-400 mt-0.5">Bergabung <?= !empty($user['created_at']) ? tgl_id($user['created_at']) : '-' ?> · Login terakhir <?= e($lastLogin) ?></p>
        </div>
    </div>
    <div class="grid grid-cols-3 divide-x divide-stone-100 border-t border-stone-100 text-center">
        <a href="riwayat.php" class="py-4 hover:bg-stone-50 transition"><p class="text-lg font-bold"><?= (int)$stat['n'] ?></p><p class="text-[10px] uppercase tracking-wider text-stone-500 font-semibold">Pesanan</p></a>
        <a href="wishlist.php" class="py-4 hover:bg-stone-50 transition"><p class="text-lg font-bold"><?= $wishN ?></p><p class="text-[10px] uppercase tracking-wider text-stone-500 font-semibold">Wishlist</p></a>
        <div class="py-4"><p class="text-lg font-bold text-[#2E7D32]"><?= rupiah($stat['belanja']) ?></p><p class="text-[10px] uppercase tracking-wider text-stone-500 font-semibold">Total Belanja</p></div>
    </div>
</section>

<!-- DATA DIRI -->
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 fade-up">
    <div class="border-b border-stone-100 pb-3">
        <h2 class="text-base font-bold flex items-center gap-2"><i data-lucide="user-round" class="w-4 h-4 text-[#2E7D32]"></i> Data Diri</h2>
        <p class="text-xs text-stone-500 mt-0.5">Alamat & nomor HP akan terisi otomatis saat checkout.</p>
    </div>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_profil">
        <div>
            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Foto Profil</label>
            <div class="flex items-center gap-3 flex-wrap">
                <label class="inline-flex items-center gap-2 bg-stone-100 hover:bg-stone-200 text-stone-700 px-4 py-2.5 rounded-xl text-xs font-semibold cursor-pointer transition"><i data-lucide="upload" class="w-4 h-4"></i> Pilih Foto
                    <input type="file" name="foto_profil" id="inFoto" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewImage(event)"></label>
                <span id="infoFoto" class="hidden text-[11px] text-stone-500 items-center gap-2"><span class="nama-file italic"></span> <button type="button" onclick="batalPilihFoto()" class="text-rose-500 hover:underline font-semibold">Batal</button></span>
            </div>
            <p class="text-[10px] text-stone-400 mt-1.5">JPG, PNG, WEBP · maks 2MB · rasio 1:1 disarankan.</p>
        </div>
        <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" value="<?= e($form['nama_lengkap']) ?>" required maxlength="100" class="<?= $input ?>"></div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">Email (tidak dapat diubah)</label>
                <input type="email" value="<?= e($user['email'] ?? '') ?>" disabled class="w-full px-4 py-2.5 text-sm bg-stone-100 border border-stone-200 rounded-xl text-stone-500 cursor-not-allowed"></div>
            <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">No. HP / WhatsApp</label>
                <input type="tel" name="no_hp" value="<?= e($form['no_hp']) ?>" placeholder="081234567890" class="<?= $input ?>"></div>
        </div>
        <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat Pengiriman</label>
            <textarea name="alamat" rows="3" maxlength="500" class="<?= $input ?> resize-none"><?= e($form['alamat']) ?></textarea></div>
        <div class="flex justify-end pt-1"><button class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-emerald-800 transition active:scale-95"><i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan</button></div>
    </form>
</section>

<!-- PASSWORD -->
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 fade-up">
    <div class="border-b border-stone-100 pb-3">
        <h2 class="text-base font-bold flex items-center gap-2"><i data-lucide="lock" class="w-4 h-4 text-[#2E7D32]"></i> Ganti Password</h2>
        <p class="text-xs text-stone-500 mt-0.5">Gunakan kombinasi huruf, angka, dan simbol agar lebih aman.</p>
    </div>
    <form method="post" class="space-y-4" id="formPass">
        <?= csrf_field() ?><input type="hidden" name="action" value="ganti_password">
        <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">Password Lama</label>
            <input type="password" name="old_password" required autocomplete="current-password" class="<?= $input ?>"></div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">Password Baru</label>
                <input type="password" name="new_password" id="pwNew" required minlength="6" autocomplete="new-password" class="<?= $input ?>">
                <div class="mt-2 flex gap-1" aria-hidden="true"><?php for ($i = 0; $i < 4; $i++): ?><span class="pw-bar h-1.5 flex-1 rounded-full bg-stone-200 transition"></span><?php endfor; ?></div>
                <p id="pwLabel" class="text-[10px] text-stone-400 mt-1">Minimal 6 karakter</p></div>
            <div><label class="block text-xs font-bold text-stone-600 uppercase mb-2">Konfirmasi Password</label>
                <input type="password" name="konfirmasi" id="pwKonf" required minlength="6" autocomplete="new-password" class="<?= $input ?>">
                <p id="pwMatch" class="text-[10px] mt-2 h-3"></p></div>
        </div>
        <div class="flex justify-end pt-1"><button class="inline-flex items-center gap-2 bg-stone-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-stone-900 transition active:scale-95"><i data-lucide="key-round" class="w-4 h-4"></i> Ubah Password</button></div>
    </form>
</section>

<!-- BANTUAN -->
<section class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 fade-up">
    <div class="border-b border-stone-100 pb-3">
        <h2 class="text-base font-bold flex items-center gap-2"><i data-lucide="life-buoy" class="w-4 h-4 text-[#2E7D32]"></i> Bantuan & Panduan</h2>
        <p class="text-xs text-stone-500 mt-0.5">Panduan singkat menggunakan fitur PlantHub.</p>
    </div>
    <div class="space-y-2">
        <?php foreach ($faq as [$q, $a]): ?>
        <details class="group border border-stone-200/80 rounded-xl overflow-hidden">
            <summary class="cursor-pointer p-3.5 text-sm font-semibold hover:bg-stone-50 flex items-center justify-between list-none"><span><?= e($q) ?></span><i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 group-open:rotate-180 transition"></i></summary>
            <div class="p-3.5 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100"><?= $a /* konten statis tepercaya */ ?></div>
        </details>
        <?php endforeach; ?>
    </div>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-emerald-50/60 rounded-xl px-5 py-4">
        <div><p class="text-xs font-bold">Butuh bantuan lebih lanjut?</p><p class="text-[11px] text-stone-500 mt-0.5">Tim admin PlantHub siap membantu.</p></div>
        <a href="chat.php" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2 rounded-xl text-xs font-bold transition"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Hubungi Admin</a>
    </div>
    <div class="text-center"><a href="../Auth/logout.php" data-logout class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 px-4 py-2 rounded-xl transition"><i data-lucide="log-out" class="w-4 h-4"></i> Keluar dari akun</a></div>
</section>

<?php
$avJs = json_encode($avatar, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$GLOBALS['__extra_js'] = <<<JS
<script>
const AVATAR0 = $avJs;
function previewImage(ev) {
    const f = ev.target.files[0]; if (!f) return;
    if (f.size > 2 * 1024 * 1024) { toast('Ukuran file melebihi 2MB.', 'error'); ev.target.value = ''; return; }
    const rd = new FileReader();
    rd.onload = (e) => { document.getElementById('previewAvatar').src = e.target.result;
        const i = document.getElementById('infoFoto'); i.classList.remove('hidden'); i.classList.add('inline-flex'); i.querySelector('.nama-file').textContent = f.name; };
    rd.readAsDataURL(f);
}
function batalPilihFoto() {
    document.getElementById('inFoto').value = ''; document.getElementById('previewAvatar').src = AVATAR0;
    const i = document.getElementById('infoFoto'); i.classList.add('hidden'); i.classList.remove('inline-flex');
}
// kekuatan password
const pw = document.getElementById('pwNew'), kf = document.getElementById('pwKonf');
const bars = document.querySelectorAll('.pw-bar'), lab = document.getElementById('pwLabel'), mt = document.getElementById('pwMatch');
const cols = ['bg-rose-500', 'bg-orange-400', 'bg-amber-400', 'bg-emerald-500'], names = ['Lemah', 'Cukup', 'Baik', 'Kuat'];
function skor(v) { let s = 0; if (v.length >= 6) s++; if (v.length >= 10) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++; return Math.min(s, 4); }
function cek() {
    const v = pw.value, s = v ? Math.max(1, skor(v)) : 0;
    bars.forEach((b, i) => { b.className = 'pw-bar h-1.5 flex-1 rounded-full transition ' + (i < s ? cols[s - 1] : 'bg-stone-200'); });
    lab.textContent = v ? 'Kekuatan: ' + names[s - 1] : 'Minimal 6 karakter';
    if (kf.value) { const ok = kf.value === v; mt.textContent = ok ? '✓ Password cocok' : '✗ Belum cocok'; mt.className = 'text-[10px] mt-2 h-3 ' + (ok ? 'text-emerald-600' : 'text-rose-500'); } else mt.textContent = '';
}
pw.addEventListener('input', cek); kf.addEventListener('input', cek);
document.querySelector('[data-logout]')?.addEventListener('click', (e) => { if (!confirm('Keluar dari akun?')) e.preventDefault(); });
</script>
JS;
layout_end(); ?>
