<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_id   = (int)$_SESSION['user_id'];
$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// PASTIKAN TABEL pengaturan ADA
// =========================================================
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko VARCHAR(100) NOT NULL DEFAULT 'PlantHub',
    nama_cabang VARCHAR(100) NOT NULL DEFAULT 'Cabang Utama',
    no_telepon VARCHAR(20) NULL,
    email_toko VARCHAR(100) NULL,
    alamat_toko TEXT NULL,
    catatan_nota TEXT NULL,
    logo_toko VARCHAR(255) DEFAULT 'default_logo.png',
    foto_profil VARCHAR(255) DEFAULT 'default_avatar.jpg',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Pastikan ada 1 baris id=1
$qCek = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
if (!$qCek || mysqli_num_rows($qCek) === 0) {
    mysqli_query($conn, "INSERT INTO pengaturan (id, nama_toko, nama_cabang, no_telepon, email_toko, alamat_toko, catatan_nota) 
                         VALUES (1, 'PlantHub', 'Cabang Utama', '081234567890', 'admin@planthub.com', 'Jl. Contoh No. 123', 'Terima kasih telah berbelanja di PlantHub!')");
}

// =========================================================
// FUNGSI UPLOAD (dengan validasi & pesan error)
// =========================================================
function uploadFile($file, $prefix, $oldFile = '', &$errors = []) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return $oldFile;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Gagal upload $prefix (kode error: {$file['error']}).";
        return $oldFile;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        $errors[] = "Format file $prefix tidak didukung. Gunakan JPG, PNG, atau WEBP.";
        return $oldFile;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = "Ukuran file $prefix melebihi 2MB.";
        return $oldFile;
    }

    $dir = '../uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $newName = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $newName)) {
        if ($oldFile && !in_array($oldFile, ['default.jpg', 'default_logo.png', 'default_avatar.jpg']) && file_exists($dir . $oldFile)) {
            @unlink($dir . $oldFile);
        }
        return $newName;
    }
    $errors[] = "Gagal memindahkan file $prefix ke folder uploads.";
    return $oldFile;
}

// =========================================================
// HANDLE HAPUS LOGO / FOTO PROFIL
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_gambar'])) {
    $target = $_POST['hapus_gambar']; // 'logo' atau 'profil'
    $qOld = mysqli_query($conn, "SELECT logo_toko, foto_profil FROM pengaturan WHERE id = 1");
    $old = mysqli_fetch_assoc($qOld);

    if ($target === 'logo') {
        if ($old['logo_toko'] && !in_array($old['logo_toko'], ['default_logo.png']) && file_exists('../uploads/' . $old['logo_toko'])) {
            @unlink('../uploads/' . $old['logo_toko']);
        }
        mysqli_query($conn, "UPDATE pengaturan SET logo_toko = 'default_logo.png' WHERE id = 1");
        header("Location: pengaturan.php?status=logo_reset");
        exit;
    }
    if ($target === 'profil') {
        if ($old['foto_profil'] && !in_array($old['foto_profil'], ['default_avatar.jpg']) && file_exists('../uploads/' . $old['foto_profil'])) {
            @unlink('../uploads/' . $old['foto_profil']);
        }
        mysqli_query($conn, "UPDATE pengaturan SET foto_profil = 'default_avatar.jpg' WHERE id = 1");
        header("Location: pengaturan.php?status=profil_reset");
        exit;
    }
}

// =========================================================
// SIMPAN PENGATURAN (dengan validasi lengkap)
// =========================================================
$pesan_sukses = '';
$pesan_error  = '';
$upload_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $nama_toko    = trim($_POST['nama_toko'] ?? '');
    $nama_cabang  = trim($_POST['nama_cabang'] ?? '');
    $no_telepon   = trim($_POST['no_telepon'] ?? '');
    $email_toko   = trim($_POST['email_toko'] ?? '');
    $alamat_toko  = trim($_POST['alamat_toko'] ?? '');
    $catatan_nota = trim($_POST['catatan_nota'] ?? '');

    // Validasi
    if ($nama_toko === '') {
        $pesan_error = "Nama toko wajib diisi.";
    } elseif ($nama_cabang === '') {
        $pesan_error = "Nama cabang wajib diisi.";
    } elseif ($email_toko !== '' && !filter_var($email_toko, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = "Format email tidak valid.";
    } elseif ($no_telepon !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $no_telepon)) {
        $pesan_error = "Nomor telepon hanya boleh angka, min. 8 digit.";
    }

    if ($pesan_error === '') {
        $nama_toko_esc    = mysqli_real_escape_string($conn, $nama_toko);
        $nama_cabang_esc  = mysqli_real_escape_string($conn, $nama_cabang);
        $no_telepon_esc   = mysqli_real_escape_string($conn, $no_telepon);
        $email_toko_esc   = mysqli_real_escape_string($conn, $email_toko);
        $alamat_toko_esc  = mysqli_real_escape_string($conn, $alamat_toko);
        $catatan_nota_esc = mysqli_real_escape_string($conn, $catatan_nota);

        $qOld = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
        $dataLama = mysqli_fetch_assoc($qOld);

        $logo_toko   = uploadFile($_FILES['logo_toko']   ?? null, 'logo',   $dataLama['logo_toko'],   $upload_errors);
        $foto_profil = uploadFile($_FILES['foto_profil'] ?? null, 'profil', $dataLama['foto_profil'], $upload_errors);

        if (!empty($upload_errors)) {
            $pesan_error = implode(' ', $upload_errors);
        } else {
            $sql = "UPDATE pengaturan SET 
                        nama_toko = '$nama_toko_esc',
                        nama_cabang = '$nama_cabang_esc',
                        no_telepon = '$no_telepon_esc',
                        email_toko = '$email_toko_esc',
                        alamat_toko = '$alamat_toko_esc',
                        catatan_nota = '$catatan_nota_esc',
                        logo_toko = '$logo_toko',
                        foto_profil = '$foto_profil'
                    WHERE id = 1";
            if (mysqli_query($conn, $sql)) {
                header("Location: pengaturan.php?status=saved");
                exit;
            } else {
                $pesan_error = "Gagal menyimpan: " . mysqli_error($conn);
            }
        }
    }
}

// Status message dari redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'saved')        $pesan_sukses = "Pengaturan berhasil diperbarui.";
    if ($_GET['status'] === 'logo_reset')   $pesan_sukses = "Logo toko dikembalikan ke default.";
    if ($_GET['status'] === 'profil_reset') $pesan_sukses = "Foto profil dikembalikan ke default.";
}

// =========================================================
// AMBIL DATA PENGATURAN
// =========================================================
$data_toko = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1"));

$logo_src = (!empty($data_toko['logo_toko']) && $data_toko['logo_toko'] !== 'default_logo.png' && file_exists('../uploads/' . $data_toko['logo_toko']))
    ? '../uploads/' . $data_toko['logo_toko']
    : 'https://ui-avatars.com/api/?name=PH&background=2E7D32&color=fff&size=128';

$avatar_src = (!empty($data_toko['foto_profil']) && $data_toko['foto_profil'] !== 'default_avatar.jpg' && file_exists('../uploads/' . $data_toko['foto_profil']))
    ? '../uploads/' . $data_toko['foto_profil']
    : 'https://ui-avatars.com/api/?name=' . urlencode($admin_nama) . '&background=2E7D32&color=fff';

$is_logo_custom   = !empty($data_toko['logo_toko']) && $data_toko['logo_toko'] !== 'default_logo.png';
$is_avatar_custom = !empty($data_toko['foto_profil']) && $data_toko['foto_profil'] !== 'default_avatar.jpg';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Pengaturan Toko</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Pengaturan Toko</span>
            </div>

            <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="<?= $avatar_src ?>" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                    <p class="text-xs text-stone-500">Administrator</p>
                </div>
            </a>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MAIN MENU</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php',  'icon' => 'layout-grid',    'label' => 'Dashboard',        'active' => false],
                        ['url' => 'pos.php',        'icon' => 'shopping-bag',   'label' => 'Kasir (POS)',      'active' => false],
                        ['url' => 'restock.php',    'icon' => 'truck',          'label' => 'Pembelian',        'active' => false],
                        ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'active' => false],
                        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],
                        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],
                        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => false],
                        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => false],
                        ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','active' => false],
                        ['url' => 'laporan.php',    'icon' => 'bar-chart-2',    'label' => 'Laporan',          'active' => false],
                    ];
                    foreach ($menu as $m):
                        $cls = $m['active'] ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                            <div class="flex items-center gap-3">
                                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $m['active'] ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                                <span><?= $m['label'] ?></span>
                            </div>
                            <?php if ($m['active']): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <hr class="border-stone-100 my-3">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">PENGATURAN</p>
                    <?php
                    $menu2 = [
                        ['url' => 'pengaturan.php', 'icon' => 'settings',    'label' => 'Pengaturan Toko', 'active' => true],
                        ['url' => 'bantuan.php',    'icon' => 'help-circle', 'label' => 'Bantuan',         'active' => false],
                    ];
                    foreach ($menu2 as $m):
                        $cls = $m['active'] ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                            <div class="flex items-center gap-3">
                                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $m['active'] ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                                <span><?= $m['label'] ?></span>
                            </div>
                            <?php if ($m['active']): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-auto">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight">PlantHub Admin</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5">Sistem Online</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-6">

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Pengaturan Toko</h1>
                <p class="text-sm text-stone-500 mt-0.5">Atur identitas toko, kontak, dan preferensi struk.</p>
            </div>

            <!-- ALERT -->
            <?php if (!empty($pesan_sukses)): ?>
                <div id="alertSukses" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2 transition-opacity duration-500">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                    <span><?= htmlspecialchars($pesan_sukses) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($pesan_error)): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                    <span><?= htmlspecialchars($pesan_error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="pengaturan.php" enctype="multipart/form-data" class="space-y-6" onsubmit="return konfirmasiSimpan(event)">

                <!-- KARTU 1: LOGO + FOTO PROFIL -->
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-5">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-base font-bold text-stone-800">Logo & Profil Admin</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Gambar akan tampil di header dan halaman pelanggan.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Logo Toko -->
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Logo Toko</label>
                            <div class="flex items-start gap-4">
                                <img id="previewLogo" src="<?= htmlspecialchars($logo_src) ?>" class="w-20 h-20 rounded-2xl object-cover ring-4 ring-stone-100 bg-stone-50">
                                <div class="flex-1">
                                    <div class="flex flex-wrap gap-2">
                                        <label class="inline-flex items-center gap-2 bg-stone-100 hover:bg-stone-200 text-stone-700 px-3.5 py-2 rounded-xl text-xs font-semibold cursor-pointer transition">
                                            <i data-lucide="upload" class="w-4 h-4"></i>
                                            <span>Pilih Logo</span>
                                            <input type="file" name="logo_toko" id="inLogo" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewImage(event, 'previewLogo', 'infoLogo', '<?= htmlspecialchars($logo_src) ?>')">
                                        </label>
                                        <?php if ($is_logo_custom): ?>
                                            <button type="button" onclick="konfirmasiHapusGambar('logo')" class="inline-flex items-center gap-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 rounded-xl text-xs font-semibold transition">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                <span>Hapus</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div id="infoLogo" class="hidden mt-2 text-[10px] text-stone-500 flex items-center gap-2">
                                        <i data-lucide="file-image" class="w-3 h-3"></i>
                                        <span class="nama-file italic">Belum ada file dipilih</span>
                                        <button type="button" onclick="batalPilih('inLogo', 'previewLogo', 'infoLogo', '<?= htmlspecialchars($logo_src) ?>')" class="text-rose-500 hover:underline font-semibold">Batal</button>
                                    </div>
                                    <p class="text-[10px] text-stone-400 mt-1.5">JPG/PNG/WEBP, maks 2MB.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Foto Profil -->
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Foto Profil Admin</label>
                            <div class="flex items-start gap-4">
                                <img id="previewProfil" src="<?= htmlspecialchars($avatar_src) ?>" class="w-20 h-20 rounded-2xl object-cover ring-4 ring-stone-100 bg-stone-50">
                                <div class="flex-1">
                                    <div class="flex flex-wrap gap-2">
                                        <label class="inline-flex items-center gap-2 bg-stone-100 hover:bg-stone-200 text-stone-700 px-3.5 py-2 rounded-xl text-xs font-semibold cursor-pointer transition">
                                            <i data-lucide="upload" class="w-4 h-4"></i>
                                            <span>Pilih Foto</span>
                                            <input type="file" name="foto_profil" id="inProfil" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewImage(event, 'previewProfil', 'infoProfil', '<?= htmlspecialchars($avatar_src) ?>')">
                                        </label>
                                        <?php if ($is_avatar_custom): ?>
                                            <button type="button" onclick="konfirmasiHapusGambar('profil')" class="inline-flex items-center gap-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 px-3 py-2 rounded-xl text-xs font-semibold transition">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                <span>Hapus</span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div id="infoProfil" class="hidden mt-2 text-[10px] text-stone-500 flex items-center gap-2">
                                        <i data-lucide="file-image" class="w-3 h-3"></i>
                                        <span class="nama-file italic">Belum ada file dipilih</span>
                                        <button type="button" onclick="batalPilih('inProfil', 'previewProfil', 'infoProfil', '<?= htmlspecialchars($avatar_src) ?>')" class="text-rose-500 hover:underline font-semibold">Batal</button>
                                    </div>
                                    <p class="text-[10px] text-stone-400 mt-1.5">Foto yang tampil di header.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KARTU 2: INFO TOKO -->
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-base font-bold text-stone-800">Informasi Toko</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Identitas dan detail kontak toko Anda.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Toko <span class="text-rose-500">*</span></label>
                            <input type="text" name="nama_toko" value="<?= htmlspecialchars($data_toko['nama_toko']) ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Cabang <span class="text-rose-500">*</span></label>
                            <input type="text" name="nama_cabang" value="<?= htmlspecialchars($data_toko['nama_cabang']) ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">No. Telepon / WA</label>
                            <input type="text" name="no_telepon" value="<?= htmlspecialchars($data_toko['no_telepon']) ?>" placeholder="08123456789" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Email Toko</label>
                            <input type="email" name="email_toko" value="<?= htmlspecialchars($data_toko['email_toko']) ?>" placeholder="admin@planthub.com" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat Lengkap</label>
                        <textarea name="alamat_toko" rows="3" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"><?= htmlspecialchars($data_toko['alamat_toko']) ?></textarea>
                    </div>
                </div>

                <!-- KARTU 3: CATATAN STRUK + PREVIEW -->
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-base font-bold text-stone-800">Preferensi Struk</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Catatan kaki yang muncul di bagian bawah struk pembelian.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Catatan Kaki Struk</label>
                            <textarea name="catatan_nota" id="inCatatan" rows="5" oninput="updatePreviewStruk()" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"><?= htmlspecialchars($data_toko['catatan_nota']) ?></textarea>

                            <div class="mt-3 text-[11px] text-stone-400">
                                <p class="font-semibold text-stone-500 mb-1">Terakhir diperbarui:</p>
                                <p><?= !empty($data_toko['updated_at']) ? date('d M Y, H:i', strtotime($data_toko['updated_at'])) . ' WIB' : '-' ?></p>
                            </div>
                        </div>

                        <!-- Preview Struk -->
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Preview Struk</label>
                            <div class="bg-stone-50 border border-stone-200 rounded-xl p-4">
                                <div class="bg-white rounded-lg p-4 font-mono text-[10px] text-stone-700 shadow-sm border border-stone-100 max-w-xs mx-auto">
                                    <div class="text-center border-b border-dashed border-stone-300 pb-2 mb-2">
                                        <p class="font-bold text-xs text-stone-800" id="pvNamaToko"><?= htmlspecialchars($data_toko['nama_toko']) ?></p>
                                        <p class="text-[9px] text-stone-500" id="pvNamaCabang"><?= htmlspecialchars($data_toko['nama_cabang']) ?></p>
                                    </div>
                                    <div class="space-y-0.5 mb-2">
                                        <div class="flex justify-between"><span>Contoh Produk</span><span>x1</span></div>
                                        <div class="flex justify-between"><span></span><span>Rp 50.000</span></div>
                                    </div>
                                    <div class="border-t border-dashed border-stone-300 pt-2 flex justify-between font-bold">
                                        <span>TOTAL</span><span>Rp 55.500</span>
                                    </div>
                                    <div class="border-t border-dashed border-stone-300 mt-2 pt-2 text-center text-[9px] text-stone-500 italic min-h-[1.5rem]" id="pvCatatan">
                                        <?= htmlspecialchars($data_toko['catatan_nota'] ?: 'Terima kasih!') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TOMBOL AKSI -->
                <div class="flex items-center justify-end gap-3">
                    <a href="pengaturan.php" class="px-5 py-2.5 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</a>
                    <button type="submit" name="simpan_pengaturan" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-emerald-800 shadow-sm transition">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan Pengaturan
                    </button>
                </div>

            </form>

            <!-- ZONA INFO -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-5">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i data-lucide="info" class="w-5 h-5"></i>
                    </div>
                    <div class="text-xs text-stone-500 leading-relaxed">
                        <p class="font-bold text-stone-700 mb-1">Info</p>
                        <p>Semua perubahan akan langsung diterapkan ke seluruh halaman admin, katalog pelanggan, dan struk pembelian. Upload gambar maksimal 2MB.</p>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- FORM HAPUS GAMBAR (hidden, submit via JS) -->
    <form id="formHapusGambar" method="POST" action="pengaturan.php" class="hidden">
        <input type="hidden" name="hapus_gambar" id="hapusTarget" value="">
    </form>

    <script>
        lucide.createIcons();

        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }

        function previewImage(event, targetId, infoId, originalSrc) {
            const file = event.target.files[0];
            if (!file) return;

            // Validasi ukuran
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file melebihi 2MB.');
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById(targetId).src = e.target.result;
                const info = document.getElementById(infoId);
                if (info) {
                    info.classList.remove('hidden');
                    info.querySelector('.nama-file').innerText = file.name;
                }
            };
            reader.readAsDataURL(file);
        }

        function batalPilih(inputId, targetId, infoId, originalSrc) {
            document.getElementById(inputId).value = '';
            document.getElementById(targetId).src = originalSrc;
            document.getElementById(infoId)?.classList.add('hidden');
        }

        function konfirmasiHapusGambar(target) {
            const label = target === 'logo' ? 'logo toko' : 'foto profil';
            if (confirm(`Hapus ${label} dan kembalikan ke default?`)) {
                document.getElementById('hapusTarget').value = target;
                document.getElementById('formHapusGambar').submit();
            }
        }

        function konfirmasiSimpan(e) {
            // Cek apakah ada file yang dipilih atau field berubah
            const nama = document.querySelector('input[name="nama_toko"]').value.trim();
            if (nama === '') {
                alert('Nama toko wajib diisi.');
                e.preventDefault();
                return false;
            }
            return true;
        }

        function updatePreviewStruk() {
            const catatan = document.getElementById('inCatatan').value.trim();
            document.getElementById('pvCatatan').innerText = catatan || 'Terima kasih!';
        }

        // Auto-hide alert sukses
        const alertSukses = document.getElementById('alertSukses');
        if (alertSukses) {
            setTimeout(() => {
                alertSukses.style.opacity = '0';
                setTimeout(() => alertSukses.remove(), 500);
            }, 4000);
        }
    </script>
</body>
</html>