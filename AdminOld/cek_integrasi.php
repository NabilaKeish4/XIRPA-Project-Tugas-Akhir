<?php
/**
 * Admin/cek_integrasi.php
 * Halaman diagnostik: memeriksa apakah Admin sudah terhubung dengan Config, Auth, database,
 * folder upload, dan FPDF. Hanya bisa dibuka setelah login sebagai admin.
 * (Boleh dihapus setelah semua centang hijau.)
 */
require_once __DIR__ . '/includes/init.php';

$root = realpath(__DIR__ . '/..');
$cek  = [];
function add(array &$a, string $grup, string $nama, bool $ok, string $info = '', bool $wajib = true) {
    $a[] = ['grup' => $grup, 'nama' => $nama, 'ok' => $ok, 'info' => $info, 'wajib' => $wajib];
}

// --- PHP ---
add($cek, 'Server', 'PHP 8.0 atau lebih baru', version_compare(PHP_VERSION, '8.0.0', '>='), 'Versi sekarang: ' . PHP_VERSION);
add($cek, 'Server', 'Ekstensi mysqli', extension_loaded('mysqli'));
add($cek, 'Server', 'Ekstensi mbstring', extension_loaded('mbstring'), 'Dipakai untuk memotong teks di PDF');
add($cek, 'Server', 'Ekstensi iconv', function_exists('iconv'), 'Dipakai agar karakter PDF tidak rusak');
add($cek, 'Server', 'Ekstensi gd (opsional)', extension_loaded('gd'), 'Dibutuhkan FPDF bila logo PNG transparan', false);

// --- File & folder ---
add($cek, 'File & Folder', '../Config/database.php', is_file($root . '/Config/database.php'));
add($cek, 'File & Folder', '../Auth/login.php', is_file($root . '/Auth/login.php'));
add($cek, 'File & Folder', '../Auth/logout.php', is_file($root . '/Auth/logout.php'));
add($cek, 'File & Folder', '../fpdf/fpdf.php (untuk PDF)', is_file($root . '/fpdf/fpdf.php'), 'Unduh dari fpdf.org lalu taruh di folder fpdf/');
foreach (['assets/img' => 'Gambar produk', 'uploads' => 'Logo & foto profil', 'uploads/chats' => 'Lampiran chat'] as $d => $ket) {
    $p = $root . '/' . $d;
    if (!is_dir($p)) @mkdir($p, 0775, true);
    add($cek, 'File & Folder', "../$d/ ($ket) bisa ditulis", is_dir($p) && is_writable($p), is_dir($p) ? '' : 'Folder belum ada & gagal dibuat');
}

// --- Database ---
$skema = [
    'users'            => ['id', 'nama_lengkap', 'username', 'email', 'password', 'role', 'created_at'],
    'kategori'         => ['id', 'nama_kategori'],
    'produk'           => ['id', 'kategori_id', 'nama_tanaman', 'deskripsi', 'cara_perawatan', 'harga_jual', 'harga_beli', 'stok', 'stok_minimal', 'gambar'],
    'supplier'         => ['id', 'nama_supplier', 'kontak', 'alamat'],
    'transaksi'        => ['id', 'kode_transaksi', 'user_id', 'supplier_id', 'jenis_transaksi', 'nama_penerima', 'total_harga', 'metode_pembayaran', 'status', 'catatan', 'created_at'],
    'transaksi_detail' => ['id', 'transaksi_id', 'produk_id', 'jumlah', 'harga_satuan', 'subtotal'],
    'chats'            => ['id', 'user_id', 'sender_type', 'message', 'attachment', 'is_read', 'is_deleted', 'edited_at', 'created_at'],
    'pengaturan'       => ['id', 'nama_toko', 'nama_cabang', 'no_telepon', 'email_toko', 'alamat_toko', 'catatan_nota', 'logo_toko', 'foto_profil'],
];
foreach ($skema as $tabel => $kolom) {
    $ada = table_exists($tabel);
    add($cek, 'Database', "Tabel `$tabel`", $ada);
    if ($ada) {
        $hilang = [];
        foreach ($kolom as $k) if (!column_exists($tabel, $k)) $hilang[] = $k;
        add($cek, 'Database', "Kolom tabel `$tabel` lengkap", empty($hilang), $hilang ? 'Kolom belum ada: ' . implode(', ', $hilang) : '');
    }
}
add($cek, 'Database', 'Tabel bantuan_feedback (dibuat otomatis oleh bantuan.php)', table_exists('bantuan_feedback'), 'Buka halaman Bantuan sekali agar tabel terbentuk', false);
$qA = mysqli_query($conn, "SELECT COUNT(*) AS n FROM users WHERE role = 'admin'");
add($cek, 'Database', 'Ada minimal 1 akun admin', $qA && (int)mysqli_fetch_assoc($qA)['n'] > 0);

// --- Sesi ---
add($cek, 'Sesi Login', "\$_SESSION['user_id'] terisi", !empty($_SESSION['user_id']), 'ID: ' . ($_SESSION['user_id'] ?? '-'));
add($cek, 'Sesi Login', "\$_SESSION['role'] = admin", ($_SESSION['role'] ?? '') === 'admin');
add($cek, 'Sesi Login', "\$_SESSION['nama_user'] terisi", !empty($_SESSION['nama_user']), 'Nilai: ' . ($_SESSION['nama_user'] ?? '-'), false);

$gagal = count(array_filter($cek, fn($c) => !$c['ok'] && $c['wajib']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($nama_toko) ?> - Cek Integrasi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen p-4 sm:p-8">
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Cek Integrasi Admin</h1>
            <p class="text-sm text-stone-500 mt-0.5">Memeriksa koneksi Admin ke Config, Auth, database, folder, dan FPDF.</p>
        </div>
        <a href="dashboard.php" class="text-xs font-bold text-[#2E7D32] hover:underline">&larr; Dashboard</a>
    </div>

    <div class="p-4 rounded-2xl border text-sm font-semibold <?= $gagal === 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
        <?= $gagal === 0 ? 'Semua pemeriksaan wajib lulus. Admin siap dipakai.' : $gagal . ' pemeriksaan wajib belum lulus — perbaiki yang bertanda merah.' ?>
    </div>

    <?php
    $grup = [];
    foreach ($cek as $c) $grup[$c['grup']][] = $c;
    foreach ($grup as $nama => $items): ?>
        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-stone-100 text-xs font-bold uppercase tracking-wider text-stone-500"><?= e($nama) ?></div>
            <ul class="divide-y divide-stone-100">
                <?php foreach ($items as $c): ?>
                    <li class="px-5 py-3 flex items-start gap-3 text-sm">
                        <?php if ($c['ok']): ?>
                            <i data-lucide="check-circle-2" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                        <?php elseif ($c['wajib']): ?>
                            <i data-lucide="x-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                        <?php else: ?>
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-500 shrink-0"></i>
                        <?php endif; ?>
                        <div>
                            <p class="font-semibold text-stone-800"><?= e($c['nama']) ?></p>
                            <?php if ($c['info'] !== ''): ?><p class="text-xs text-stone-500 mt-0.5"><?= e($c['info']) ?></p><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
