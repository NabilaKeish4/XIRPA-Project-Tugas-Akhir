<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// PROSES AKSI (POST: tambah / edit / hapus)
// =========================================================
$pesan_sukses = '';
$pesan_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- FUNGSI UPLOAD GAMBAR -----
    function uploadGambar($file, $oldFile = '') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return $oldFile;

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) return $oldFile;
        if ($file['size'] > 2 * 1024 * 1024) return $oldFile; // max 2MB

        $dir = '../assets/img/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $newName = 'produk_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $newName)) {
            // Hapus file lama kalau bukan default
            if ($oldFile && $oldFile !== 'default.jpg' && file_exists($dir . $oldFile)) {
                @unlink($dir . $oldFile);
            }
            return $newName;
        }
        return $oldFile;
    }

    // ----- TAMBAH PRODUK -----
    if ($action === 'tambah') {
        $nama         = mysqli_real_escape_string($conn, trim($_POST['nama_tanaman'] ?? ''));
        $kategori_id  = (int)($_POST['kategori_id'] ?? 0);
        $harga_jual   = (float)($_POST['harga_jual'] ?? 0);
        $harga_beli   = (float)($_POST['harga_beli'] ?? 0);
        $stok         = (int)($_POST['stok'] ?? 0);
        $stok_minimal = (int)($_POST['stok_minimal'] ?? 5);
        $deskripsi    = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
        $perawatan    = mysqli_real_escape_string($conn, trim($_POST['cara_perawatan'] ?? ''));

        // Upload gambar
        $gambar = 'default.jpg';
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $gambar = uploadGambar($_FILES['gambar'], '');
        }

        if ($nama === '') {
            $pesan_error = "Nama produk wajib diisi.";
        } elseif ($harga_jual <= 0) {
            $pesan_error = "Harga jual harus lebih dari 0.";
        } else {
            $sql = "INSERT INTO produk 
                    (kategori_id, nama_tanaman, deskripsi, cara_perawatan, harga_jual, harga_beli, stok, stok_minimal, gambar, created_at) 
                    VALUES 
                    (" . ($kategori_id > 0 ? $kategori_id : 'NULL') . ", '$nama', '$deskripsi', '$perawatan', $harga_jual, $harga_beli, $stok, $stok_minimal, '$gambar', NOW())";
            if (mysqli_query($conn, $sql)) {
                header("Location: stok.php?status=added");
                exit;
            } else {
                $pesan_error = "Gagal menambah produk: " . mysqli_error($conn);
            }
        }
    }

    // ----- EDIT PRODUK -----
    if ($action === 'edit') {
        $id           = (int)($_POST['id'] ?? 0);
        $nama         = mysqli_real_escape_string($conn, trim($_POST['nama_tanaman'] ?? ''));
        $kategori_id  = (int)($_POST['kategori_id'] ?? 0);
        $harga_jual   = (float)($_POST['harga_jual'] ?? 0);
        $harga_beli   = (float)($_POST['harga_beli'] ?? 0);
        $stok         = (int)($_POST['stok'] ?? 0);
        $stok_minimal = (int)($_POST['stok_minimal'] ?? 5);
        $deskripsi    = mysqli_real_escape_string($conn, trim($_POST['deskripsi'] ?? ''));
        $perawatan    = mysqli_real_escape_string($conn, trim($_POST['cara_perawatan'] ?? ''));

        // Ambil gambar lama
        $oldGambar = 'default.jpg';
        $qOld = mysqli_query($conn, "SELECT gambar FROM produk WHERE id = $id LIMIT 1");
        if ($qOld && $r = mysqli_fetch_assoc($qOld)) $oldGambar = $r['gambar'];

        $gambar = uploadGambar($_FILES['gambar'] ?? null, $oldGambar);

        if ($id <= 0 || $nama === '') {
            $pesan_error = "Data tidak lengkap.";
        } else {
            $sql = "UPDATE produk SET 
                        kategori_id = " . ($kategori_id > 0 ? $kategori_id : 'NULL') . ",
                        nama_tanaman = '$nama',
                        deskripsi = '$deskripsi',
                        cara_perawatan = '$perawatan',
                        harga_jual = $harga_jual,
                        harga_beli = $harga_beli,
                        stok = $stok,
                        stok_minimal = $stok_minimal,
                        gambar = '$gambar'
                    WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                header("Location: stok.php?status=edited");
                exit;
            } else {
                $pesan_error = "Gagal mengedit: " . mysqli_error($conn);
            }
        }
    }

    // ----- HAPUS PRODUK -----
    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cek apakah produk pernah dipakai di transaksi
            $qCek = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi_detail WHERE produk_id = $id");
            $dipakai = $qCek ? (int)mysqli_fetch_assoc($qCek)['total'] : 0;

            if ($dipakai > 0) {
                $pesan_error = "Produk tidak bisa dihapus karena sudah dipakai di $dipakai transaksi.";
            } else {
                // Hapus gambar
                $qImg = mysqli_query($conn, "SELECT gambar FROM produk WHERE id = $id LIMIT 1");
                if ($qImg && $row = mysqli_fetch_assoc($qImg)) {
                    if ($row['gambar'] && $row['gambar'] !== 'default.jpg' && file_exists("../assets/img/" . $row['gambar'])) {
                        @unlink("../assets/img/" . $row['gambar']);
                    }
                }
                if (mysqli_query($conn, "DELETE FROM produk WHERE id = $id")) {
                    header("Location: stok.php?status=deleted");
                    exit;
                } else {
                    $pesan_error = "Gagal menghapus: " . mysqli_error($conn);
                }
            }
        }
    }
}

// =========================================================
// STATUS MESSAGE
// =========================================================
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added')   $pesan_sukses = "Produk baru berhasil ditambahkan.";
    if ($_GET['status'] === 'edited')  $pesan_sukses = "Produk berhasil diperbarui.";
    if ($_GET['status'] === 'deleted') $pesan_sukses = "Produk berhasil dihapus.";
}

// =========================================================
// FILTER & SEARCH
// =========================================================
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$kategori_id   = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$statusFilter  = $_GET['status_filter'] ?? 'semua';

$where = "WHERE 1=1";
if ($search !== '')      $where .= " AND p.nama_tanaman LIKE '%$search%'";
if ($kategori_id > 0)    $where .= " AND p.kategori_id = $kategori_id";
if ($statusFilter === 'safe')     $where .= " AND p.stok > p.stok_minimal";
if ($statusFilter === 'critical') $where .= " AND p.stok > 0 AND p.stok <= p.stok_minimal";
if ($statusFilter === 'empty')    $where .= " AND p.stok = 0";

// =========================================================
// AMBIL DAFTAR PRODUK
// =========================================================
$produk = [];
$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    $where
    ORDER BY p.id DESC
");
if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produk[] = $r;

// Ambil kategori untuk filter & dropdown form
$kategoriList = [];
$qKat = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id ASC");
if ($qKat) while ($r = mysqli_fetch_assoc($qKat)) $kategoriList[] = $r;

// Statistik
$statTotal    = 0;
$statSafe     = 0;
$statCritical = 0;
$statEmpty    = 0;
$qStat = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN stok > stok_minimal THEN 1 ELSE 0 END), 0) AS safe,
        COALESCE(SUM(CASE WHEN stok > 0 AND stok <= stok_minimal THEN 1 ELSE 0 END), 0) AS critical,
        COALESCE(SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END), 0) AS empty
    FROM produk
");
if ($qStat) {
    $s = mysqli_fetch_assoc($qStat);
    $statTotal    = (int)$s['total'];
    $statSafe     = (int)$s['safe'];
    $statCritical = (int)$s['critical'];
    $statEmpty    = (int)$s['empty'];
}

function gambarProduk($nama) {
    if (!$nama || $nama === 'default.jpg') {
        return "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400";
    }
    if (file_exists("../assets/img/" . $nama)) return "../assets/img/" . $nama;
    return "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Stok & Produk</title>
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

            <form method="GET" action="stok.php" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari produk..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_nama) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
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
                        ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'active' => true],
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
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="settings" class="w-5 h-5 text-stone-500"></i> Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="help-circle" class="w-5 h-5 text-stone-500"></i> Bantuan
                    </a>
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

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Kelola Stok & Produk</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Atur inventaris, harga, dan gambar produk yang muncul di katalog & POS.</p>
                </div>
                <button onclick="openModalTambah()" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm self-start sm:self-auto">
                    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Produk
                </button>
            </div>

            <!-- ALERT -->
            <?php if ($pesan_sukses): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                    <span><?= htmlspecialchars($pesan_sukses) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($pesan_error): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                    <span><?= htmlspecialchars($pesan_error) ?></span>
                </div>
            <?php endif; ?>

            <!-- 4 STATISTIK -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="stok.php" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Produk</p>
                        <i data-lucide="package" class="w-4 h-4 text-[#2E7D32]"></i>
                    </div>
                    <p class="text-2xl font-bold text-stone-800"><?= $statTotal ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Produk di katalog</p>
                </a>
                <a href="stok.php?status_filter=safe" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-emerald-500 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Stok Aman</p>
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                    </div>
                    <p class="text-2xl font-bold text-stone-800"><?= $statSafe ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Di atas minimal</p>
                </a>
                <a href="stok.php?status_filter=critical" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706] hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Stok Kritis</p>
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-[#D97706]"></i>
                    </div>
                    <p class="text-2xl font-bold text-stone-800"><?= $statCritical ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Perlu restock</p>
                </a>
                <a href="stok.php?status_filter=empty" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-rose-600 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Stok Habis</p>
                        <i data-lucide="x-circle" class="w-4 h-4 text-rose-600"></i>
                    </div>
                    <p class="text-2xl font-bold text-stone-800"><?= $statEmpty ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Segera isi ulang</p>
                </a>
            </div>

            <!-- FILTER -->
            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm space-y-3">
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <a href="stok.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori_id === 0 ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">Semua Kategori</a>
                    <?php foreach ($kategoriList as $k): ?>
                        <a href="stok.php?kategori=<?= (int)$k['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori_id === (int)$k['id'] ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                            <?= htmlspecialchars($k['nama_kategori']) ?>
                        </a>
                    <?php endforeach; ?>

                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-xs text-stone-500 whitespace-nowrap">Status:</span>
                        <select onchange="window.location.href='stok.php?status_filter='+this.value+'<?= $kategori_id > 0 ? '&kategori=' . $kategori_id : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>'" class="px-3 py-1.5 text-xs bg-stone-50 border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32]">
                            <option value="semua" <?= $statusFilter === 'semua' ? 'selected' : '' ?>>Semua</option>
                            <option value="safe" <?= $statusFilter === 'safe' ? 'selected' : '' ?>>Aman</option>
                            <option value="critical" <?= $statusFilter === 'critical' ? 'selected' : '' ?>>Kritis</option>
                            <option value="empty" <?= $statusFilter === 'empty' ? 'selected' : '' ?>>Habis</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- GRID PRODUK -->
            <?php if (empty($produk)): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 p-12 text-center">
                    <div class="w-16 h-16 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="package-search" class="w-7 h-7 text-stone-400"></i>
                    </div>
                    <p class="text-sm font-semibold text-stone-700">Belum ada produk</p>
                    <p class="text-xs text-stone-500 mt-1">Klik "Tambah Produk" untuk mulai mengisi katalog toko.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($produk as $p):
                        $stok = (int)$p['stok'];
                        $min  = (int)$p['stok_minimal'];
                        $status = $stok === 0 ? 'empty' : ($stok <= $min ? 'critical' : 'safe');
                        $badgeClass = $status === 'empty' ? 'bg-rose-600' : ($status === 'critical' ? 'bg-[#D97706]' : 'bg-emerald-600');
                        $badgeText  = $status === 'empty' ? 'HABIS' : ($status === 'critical' ? 'KRITIS' : 'AMAN');
                        $imgSrc = gambarProduk($p['gambar']);
                    ?>
                        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden flex flex-col group">
                            <div class="relative bg-stone-100 aspect-square overflow-hidden">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($p['nama_tanaman']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <span class="absolute top-2.5 left-2.5 <?= $badgeClass ?> text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm"><?= $badgeText ?></span>
                            </div>

                            <div class="p-4 flex-1 flex flex-col">
                                <p class="text-[10px] font-semibold uppercase text-stone-400 tracking-wider"><?= htmlspecialchars($p['nama_kategori'] ?? 'Tanpa Kategori') ?></p>
                                <h3 class="text-sm font-bold text-stone-800 mt-0.5 leading-snug line-clamp-2 min-h-[2.5rem]"><?= htmlspecialchars($p['nama_tanaman']) ?></h3>

                                <div class="mt-2">
                                    <p class="text-lg font-bold text-[#2E7D32]">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>
                                    <p class="text-[10px] text-stone-400">Beli: Rp <?= number_format($p['harga_beli'], 0, ',', '.') ?></p>
                                </div>

                                <div class="mt-3 pt-3 border-t border-stone-100 flex items-center justify-between text-xs">
                                    <span class="text-stone-600">Stok: <b class="<?= $status === 'empty' ? 'text-rose-600' : ($status === 'critical' ? 'text-[#D97706]' : 'text-emerald-700') ?>"><?= $stok ?></b> / min <?= $min ?></span>
                                </div>

                                <div class="mt-3 pt-3 border-t border-stone-100 grid grid-cols-2 gap-2">
                                    <button onclick='openModalEdit(<?= json_encode([
                                        "id" => $p['id'], "nama" => $p['nama_tanaman'], "kategori_id" => $p['kategori_id'],
                                        "harga_jual" => $p['harga_jual'], "harga_beli" => $p['harga_beli'],
                                        "stok" => $p['stok'], "stok_minimal" => $p['stok_minimal'],
                                        "deskripsi" => $p['deskripsi'], "perawatan" => $p['cara_perawatan'],
                                        "gambar" => $imgSrc
                                    ]) ?>)' class="text-xs font-semibold text-stone-700 bg-stone-100 hover:bg-stone-200 py-2 rounded-lg transition inline-flex items-center justify-center gap-1">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit
                                    </button>
                                    <button onclick='konfirmasiHapus(<?= (int)$p['id'] ?>, <?= json_encode($p['nama_tanaman']) ?>)' class="text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 py-2 rounded-lg transition inline-flex items-center justify-center gap-1">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL TAMBAH / EDIT -->
    <div id="modalProduk" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full my-8">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <div>
                    <h3 id="modalTitle" class="text-lg font-bold text-stone-800">Tambah Produk Baru</h3>
                    <p class="text-xs text-stone-500 mt-0.5">Lengkapi detail produk di bawah ini.</p>
                </div>
                <button onclick="closeModal()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="formProduk" method="POST" action="stok.php" enctype="multipart/form-data" class="p-5 space-y-4">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- KIRI: FORM -->
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Nama Produk <span class="text-rose-500">*</span></label>
                            <input type="text" name="nama_tanaman" id="inNama" required class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Kategori</label>
                            <select name="kategori_id" id="inKategori" class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                                <option value="0">-- Tanpa Kategori --</option>
                                <?php foreach ($kategoriList as $k): ?>
                                    <option value="<?= (int)$k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Harga Jual <span class="text-rose-500">*</span></label>
                                <input type="number" name="harga_jual" id="inHargaJual" min="0" step="500" required class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Harga Beli</label>
                                <input type="number" name="harga_beli" id="inHargaBeli" min="0" step="500" value="0" class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Stok Awal</label>
                                <input type="number" name="stok" id="inStok" min="0" value="0" class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Stok Minimal</label>
                                <input type="number" name="stok_minimal" id="inStokMin" min="0" value="5" class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            </div>
                        </div>
                    </div>

                    <!-- KANAN: GAMBAR + DESKRIPSI -->
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Gambar Produk</label>
                            <div class="border-2 border-dashed border-stone-200 hover:border-[#2E7D32] rounded-xl overflow-hidden bg-stone-50 aspect-square flex flex-col items-center justify-center relative transition-colors cursor-pointer" onclick="document.getElementById('inGambar').click()">
                                <img id="previewGambar" src="https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400" class="w-full h-full object-cover absolute inset-0" />
                                <div class="relative z-10 bg-white/90 backdrop-blur-sm rounded-lg px-3 py-2 flex items-center gap-2 shadow-sm">
                                    <i data-lucide="camera" class="w-4 h-4 text-[#2E7D32]"></i>
                                    <span class="text-xs font-bold text-stone-700">Pilih Gambar</span>
                                </div>
                                <input type="file" name="gambar" id="inGambar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewImg(event)">
                            </div>
                            <p class="text-[10px] text-stone-400 mt-1">JPG, PNG, WEBP. Maks 2MB.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Deskripsi</label>
                    <textarea name="deskripsi" id="inDeskripsi" rows="2" class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Cara Perawatan</label>
                    <textarea name="cara_perawatan" id="inPerawatan" rows="2" class="w-full px-3.5 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-stone-100">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold bg-[#2E7D32] text-white rounded-xl hover:bg-emerald-800 shadow-sm inline-flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL HAPUS -->
    <form id="formHapus" method="POST" action="stok.php" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <input type="hidden" name="action" value="hapus">
        <input type="hidden" name="id" id="hapusId" value="">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 space-y-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6 text-rose-600"></i>
            </div>
            <div class="text-center">
                <h3 class="text-lg font-bold text-stone-800">Hapus Produk?</h3>
                <p class="text-sm text-stone-500 mt-1">Produk <b id="hapusNama" class="text-stone-800"></b> akan dihapus permanen.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="closeHapus()" class="flex-1 px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 px-4 py-2 text-sm font-semibold bg-rose-600 text-white rounded-xl hover:bg-rose-700">Ya, Hapus</button>
            </div>
        </div>
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

        const modal = document.getElementById('modalProduk');
        const formProduk = document.getElementById('formProduk');

        function openModalTambah() {
            document.getElementById('modalTitle').innerText = 'Tambah Produk Baru';
            document.getElementById('formAction').value = 'tambah';
            document.getElementById('formId').value = '';
            formProduk.reset();
            document.getElementById('previewGambar').src = 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openModalEdit(data) {
            document.getElementById('modalTitle').innerText = 'Edit Produk';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = data.id;
            document.getElementById('inNama').value = data.nama || '';
            document.getElementById('inKategori').value = data.kategori_id || 0;
            document.getElementById('inHargaJual').value = data.harga_jual || 0;
            document.getElementById('inHargaBeli').value = data.harga_beli || 0;
            document.getElementById('inStok').value = data.stok || 0;
            document.getElementById('inStokMin').value = data.stok_minimal || 5;
            document.getElementById('inDeskripsi').value = data.deskripsi || '';
            document.getElementById('inPerawatan').value = data.perawatan || '';
            document.getElementById('previewGambar').src = data.gambar || 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function previewImg(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    document.getElementById('previewGambar').src = ev.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        const modalHapus = document.getElementById('formHapus');
        function konfirmasiHapus(id, nama) {
            document.getElementById('hapusId').value = id;
            document.getElementById('hapusNama').innerText = nama;
            modalHapus.classList.remove('hidden');
            modalHapus.classList.add('flex');
        }
        function closeHapus() {
            modalHapus.classList.add('hidden');
            modalHapus.classList.remove('flex');
        }

        // Klik overlay untuk close
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        modalHapus.addEventListener('click', (e) => { if (e.target === modalHapus) closeHapus(); });
    </script>
</body>
</html>