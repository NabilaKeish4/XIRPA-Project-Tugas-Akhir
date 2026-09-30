<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: transaksi.php");
    exit;
}

// =========================================================
// HANDLE UPDATE STATUS
// =========================================================
$suksesMsg = '';
$errorMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['new_status'] ?? '';
    $allowed = ['Diproses','Dikirim','Selesai','Batal'];

    if (in_array($newStatus, $allowed)) {
        // Ambil status lama
        $qOld = mysqli_query($conn, "SELECT status, jenis_transaksi FROM transaksi WHERE id = $id LIMIT 1");
        $oldData = $qOld ? mysqli_fetch_assoc($qOld) : null;
        $oldStatus = $oldData['status'] ?? '';

        // Kalau status berubah ke Batal, kembalikan stok
        $perluRestoreStok = ($newStatus === 'Batal' && $oldStatus !== 'Batal' && $oldData['jenis_transaksi'] === 'penjualan');

        if ($perluRestoreStok) {
            mysqli_begin_transaction($conn);
            try {
                // Ambil detail
                $details = [];
                $qDet = mysqli_query($conn, "SELECT produk_id, jumlah FROM transaksi_detail WHERE transaksi_id = $id");
                while ($r = mysqli_fetch_assoc($qDet)) $details[] = $r;

                // Restore stok
                foreach ($details as $d) {
                    $pid = (int)$d['produk_id'];
                    $qty = (int)$d['jumlah'];
                    if ($pid > 0 && $qty > 0) {
                        mysqli_query($conn, "UPDATE produk SET stok = stok + $qty WHERE id = $pid");
                    }
                }

                // Update status
                $esc = mysqli_real_escape_string($conn, $newStatus);
                mysqli_query($conn, "UPDATE transaksi SET status = '$esc' WHERE id = $id");

                mysqli_commit($conn);
                $suksesMsg = "Status diubah ke Batal. Stok produk telah dikembalikan.";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errorMsg = "Gagal membatalkan: " . $e->getMessage();
            }
        } else {
            $esc = mysqli_real_escape_string($conn, $newStatus);
            if (mysqli_query($conn, "UPDATE transaksi SET status = '$esc' WHERE id = $id")) {
                $suksesMsg = "Status transaksi berhasil diperbarui.";
            } else {
                $errorMsg = "Gagal update status: " . mysqli_error($conn);
            }
        }
    } else {
        $errorMsg = "Status tidak valid.";
    }
}

// =========================================================
// AMBIL TRANSAKSI
// =========================================================
$qTx = mysqli_query($conn, "
    SELECT t.*, 
           COALESCE(u.nama_lengkap, 'Walk-in / Supplier') AS pelanggan,
           u.email AS pelanggan_email,
           s.nama_supplier
    FROM transaksi t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN supplier s ON t.supplier_id = s.id
    WHERE t.id = $id LIMIT 1
");
if (!$qTx || mysqli_num_rows($qTx) === 0) {
    header("Location: transaksi.php");
    exit;
}
$tx = mysqli_fetch_assoc($qTx);

// Detail item
$details = [];
$qDet = mysqli_query($conn, "
    SELECT td.*, p.nama_tanaman, p.gambar, k.nama_kategori
    FROM transaksi_detail td
    LEFT JOIN produk p ON td.produk_id = p.id
    LEFT JOIN kategori k ON p.kategori_id = k.id
    WHERE td.transaksi_id = $id
    ORDER BY td.id ASC
");
if ($qDet) while ($r = mysqli_fetch_assoc($qDet)) $details[] = $r;

// Hitung
$subtotal = 0;
foreach ($details as $d) $subtotal += (float)$d['subtotal'];
$ppn  = $subtotal * 0.11;
$total = $subtotal + $ppn;

function badgeStatus($status) {
    switch ($status) {
        case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
        default:         return 'bg-stone-100 text-stone-600 border-stone-200';
    }
}

function badgeIconStatus($status) {
    switch ($status) {
        case 'Diproses': return 'clock';
        case 'Dikirim':  return 'truck';
        case 'Selesai':  return 'check-circle-2';
        case 'Batal':    return 'x-circle';
        default:         return 'circle';
    }
}

// Timeline step info
$timelineSteps = [
    ['key' => 'Diproses', 'label' => 'Diproses', 'desc' => 'Pesanan diterima'],
    ['key' => 'Dikirim',  'label' => 'Dikirim',  'desc' => 'Dalam pengiriman'],
    ['key' => 'Selesai',  'label' => 'Selesai',  'desc' => 'Pesanan diterima pelanggan'],
];

$currentStep = array_search($tx['status'], array_column($timelineSteps, 'key'));
$isBatal = ($tx['status'] === 'Batal');

// Helper waktu relatif
function waktuRelatif($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff/86400) . ' hari lalu';
    return date('d M Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi <?= htmlspecialchars($tx['kode_transaksi']) ?> - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }

        /* Print thermal 58mm */
        @media print {
            body * { visibility: hidden !important; }
            #areaCetak, #areaCetak * { visibility: visible !important; }
            #areaCetak {
                position: absolute !important;
                left: 0; top: 0;
                width: 58mm !important;
                padding: 2mm !important;
                margin: 0 !important;
                background: #fff !important;
                font-family: 'Courier New', monospace !important;
                font-size: 10px !important;
                color: #000 !important;
            }
            @page { size: 58mm auto; margin: 0; }
        }
    </style>
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
                <span class="text-xs text-stone-500 self-center">Detail Transaksi</span>
            </div>
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
                        ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'active' => false],
                        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],
                        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],
                        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => false],
                        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => false],
                        ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','active' => true],
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

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto w-full space-y-6">

            <nav class="flex items-center gap-1.5 text-xs text-stone-500">
                <a href="dashboard.php" class="hover:text-[#2E7D32]">Dashboard</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="transaksi.php" class="hover:text-[#2E7D32]">Riwayat Transaksi</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-stone-800 font-semibold"><?= htmlspecialchars($tx['kode_transaksi']) ?></span>
            </nav>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Detail Transaksi</h1>
                    <p class="text-sm text-stone-500 mt-0.5">
                        <?= htmlspecialchars($tx['kode_transaksi']) ?> &middot; <?= waktuRelatif($tx['created_at']) ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openPrintStruk()" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 hover:bg-stone-50 transition">
                        <i data-lucide="printer" class="w-4 h-4 text-stone-500"></i> Cetak Struk
                    </button>
                    <a href="transaksi.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 hover:bg-stone-50">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
                    </a>
                </div>
            </div>

            <?php if ($suksesMsg): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32]"></i>
                    <span><?= htmlspecialchars($suksesMsg) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                    <span><?= htmlspecialchars($errorMsg) ?></span>
                </div>
            <?php endif; ?>

            <!-- TIMELINE STATUS -->
            <?php if ($tx['jenis_transaksi'] === 'penjualan'): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5 flex-wrap gap-2">
                        <div>
                            <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Timeline Pesanan</h2>
                            <p class="text-xs text-stone-500 mt-0.5">Status pesanan pelanggan</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase border <?= badgeStatus($tx['status']) ?>">
                            <i data-lucide="<?= badgeIconStatus($tx['status']) ?>" class="w-3.5 h-3.5"></i>
                            <?= htmlspecialchars($tx['status']) ?>
                        </span>
                    </div>

                    <?php if ($isBatal): ?>
                        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                <i data-lucide="x-circle" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-rose-800">Pesanan Dibatalkan</p>
                                <p class="text-xs text-rose-600 mt-0.5">Stok produk telah dikembalikan ke sistem.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Timeline horizontal desktop / vertical mobile -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-start gap-0 sm:gap-0">
                            <?php foreach ($timelineSteps as $idx => $step):
                                $isDone = ($currentStep !== false && $idx <= $currentStep);
                                $isCurrent = ($idx === $currentStep);
                                $isLast = ($idx === count($timelineSteps) - 1);
                            ?>
                                <div class="flex sm:flex-col items-center sm:items-stretch flex-1 relative">
                                    <!-- Garis penghubung (mobile: vertikal, desktop: horizontal) -->
                                    <?php if (!$isLast): ?>
                                        <div class="absolute sm:left-1/2 left-5 top-5 sm:top-5 w-0.5 sm:w-full h-full sm:h-0.5 
                                            <?= $isDone ? 'bg-[#2E7D32]' : 'bg-stone-200' ?>
                                            sm:translate-x-5">
                                        </div>
                                    <?php endif; ?>

                                    <!-- Icon -->
                                    <div class="relative z-10 flex sm:flex-col items-center sm:items-center flex-1 sm:flex-initial">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0
                                            <?php if ($isCurrent): ?>
                                                bg-[#2E7D32] text-white ring-4 ring-emerald-100
                                            <?php elseif ($isDone): ?>
                                                bg-[#2E7D32] text-white
                                            <?php else: ?>
                                                bg-stone-100 text-stone-400 border-2 border-stone-200
                                            <?php endif; ?>">
                                            <i data-lucide="<?= $isDone ? 'check' : 'circle' ?>" class="w-5 h-5"></i>
                                        </div>

                                        <!-- Label (mobile: di samping, desktop: di bawah) -->
                                        <div class="ml-4 sm:ml-0 sm:mt-3 sm:text-center">
                                            <p class="text-xs font-bold <?= $isDone ? 'text-stone-800' : 'text-stone-400' ?>"><?= $step['label'] ?></p>
                                            <p class="text-[10px] <?= $isDone ? 'text-stone-500' : 'text-stone-400' ?>"><?= $step['desc'] ?></p>
                                        </div>
                                    </div>

                                    <!-- Spacer desktop (biar label tidak mepet) -->
                                    <?php if (!$isLast): ?>
                                        <div class="hidden sm:block h-8"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- HEADER KARTU -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-stone-100 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Kode Transaksi</p>
                        <p class="text-base font-bold font-mono text-stone-800 mt-1"><?= htmlspecialchars($tx['kode_transaksi']) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Tanggal</p>
                        <p class="text-sm text-stone-700 mt-1"><?= date('d M Y, H:i', strtotime($tx['created_at'])) ?> WIB</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Status</p>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase border mt-1 <?= badgeStatus($tx['status']) ?>">
                            <i data-lucide="<?= badgeIconStatus($tx['status']) ?>" class="w-3 h-3"></i>
                            <?= htmlspecialchars($tx['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6 bg-stone-50/40">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400 mb-2">Pemesan</p>
                        <p class="text-sm font-bold text-stone-800"><?= htmlspecialchars($tx['pelanggan']) ?></p>
                        <?php if (!empty($tx['pelanggan_email'])): ?>
                            <p class="text-xs text-stone-500 mt-0.5"><?= htmlspecialchars($tx['pelanggan_email']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($tx['nama_penerima'])): ?>
                            <p class="text-xs text-stone-600 mt-2"><b>Penerima:</b> <?= htmlspecialchars($tx['nama_penerima']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($tx['telepon'])): ?>
                            <p class="text-xs text-stone-600 mt-1"><b>Telp:</b> <?= htmlspecialchars($tx['telepon']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400 mb-2">Info Lain</p>
                        <p class="text-xs text-stone-600"><b>Jenis:</b> <?= htmlspecialchars(ucfirst($tx['jenis_transaksi'])) ?></p>
                        <p class="text-xs text-stone-600 mt-1"><b>Metode:</b> <?= htmlspecialchars(ucfirst($tx['metode_pembayaran'] ?? '-')) ?></p>
                        <?php if (!empty($tx['alamat'])): ?>
                            <p class="text-xs text-stone-600 mt-1"><b>Alamat:</b> <?= htmlspecialchars($tx['alamat']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($tx['catatan'])): ?>
                            <p class="text-xs text-stone-600 mt-1"><b>Catatan:</b> <?= htmlspecialchars($tx['catatan']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ITEM -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-stone-100">
                    <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Daftar Item (<?= count($details) ?>)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-stone-50/60 text-[11px] font-bold text-stone-500 uppercase tracking-wider border-b border-stone-100">
                                <th class="py-3 px-6 w-16">Foto</th>
                                <th class="py-3 px-4">Produk</th>
                                <th class="py-3 px-4 text-center">Qty</th>
                                <th class="py-3 px-4 text-right">Harga</th>
                                <th class="py-3 px-6 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($details)): ?>
                                <tr><td colspan="5" class="py-8 text-center text-stone-400">Tidak ada item.</td></tr>
                            <?php else: ?>
                                <?php foreach ($details as $d): 
                                    $imgSrc = !empty($d['gambar']) && $d['gambar'] !== 'default.jpg' && file_exists("../assets/img/" . $d['gambar'])
                                        ? "../assets/img/" . $d['gambar']
                                        : "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=200";
                                ?>
                                    <tr class="hover:bg-stone-50/60">
                                        <td class="py-3 px-6">
                                            <img src="<?= $imgSrc ?>" class="w-12 h-12 rounded-lg object-cover bg-stone-100">
                                        </td>
                                        <td class="py-3 px-4">
                                            <p class="font-semibold text-stone-800"><?= htmlspecialchars($d['nama_tanaman'] ?? 'Produk') ?></p>
                                            <p class="text-[10px] text-stone-400"><?= htmlspecialchars($d['nama_kategori'] ?? '-') ?></p>
                                        </td>
                                        <td class="py-3 px-4 text-center font-semibold"><?= (int)$d['jumlah'] ?></td>
                                        <td class="py-3 px-4 text-right">Rp <?= number_format($d['harga_satuan'], 0, ',', '.') ?></td>
                                        <td class="py-3 px-6 text-right font-bold text-stone-800">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-6 bg-stone-50/60 border-t border-stone-100 flex justify-end">
                    <div class="w-full sm:w-72 space-y-2 text-sm">
                        <div class="flex justify-between text-stone-600">
                            <span>Subtotal</span>
                            <span class="font-semibold">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between text-stone-600">
                            <span>PPN 11%</span>
                            <span class="font-semibold">Rp <?= number_format($ppn, 0, ',', '.') ?></span>
                        </div>
                        <div class="pt-3 border-t border-stone-200 flex justify-between items-baseline">
                            <span class="text-sm font-bold text-stone-800">Total Bayar</span>
                            <span class="text-xl font-bold text-[#2E7D32]">Rp <?= number_format($tx['total_harga'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AKSI: UPDATE STATUS -->
            <?php if ($tx['jenis_transaksi'] === 'penjualan'): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Ubah Status Transaksi</h2>
                        <p class="text-xs text-stone-500 mt-0.5">
                            Update status pesanan pelanggan.
                            <?php if ($tx['status'] === 'Batal'): ?>
                                <span class="text-rose-600 font-semibold">Pesanan sudah dibatalkan.</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($tx['status'] === 'Batal'): ?>
                        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs flex items-center gap-2">
                            <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                            <span>Pesanan ini sudah dibatalkan. Status tidak bisa diubah lagi.</span>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="flex flex-col sm:flex-row gap-3">
                            <select name="new_status" class="flex-1 px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                                <?php foreach (['Diproses','Dikirim','Selesai','Batal'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $tx['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_status" class="bg-[#2E7D32] hover:bg-emerald-800 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                                Simpan Status
                            </button>
                        </form>
                        <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl text-[11px] text-amber-700 flex items-start gap-2">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
                            <span>Mengubah status ke <b>Batal</b> akan otomatis mengembalikan stok produk ke sistem.</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL CETAK STRUK -->
    <div id="modalStruk" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full max-h-[90vh] flex flex-col">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-stone-800">Cetak Struk</h3>
                <button onclick="closePrintStruk()" class="text-stone-400 hover:text-stone-700 p-1 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto custom-scrollbar">
                <div id="areaCetak">
                    <div style="font-family:'Courier New',monospace;font-size:11px;color:#000;line-height:1.4;">
                        <div style="text-align:center;border-bottom:1px dashed #000;padding-bottom:8px;margin-bottom:8px;">
                            <div style="font-weight:bold;font-size:14px;">PLANTHUB</div>
                            <div style="font-size:10px;">Sistem Manajemen Toko Tanaman</div>
                        </div>
                        <div style="font-size:10px;margin-bottom:8px;">
                            <div style="display:flex;justify-content:space-between;"><span>Kode</span><span><?= htmlspecialchars($tx['kode_transaksi']) ?></span></div>
                            <div style="display:flex;justify-content:space-between;"><span>Tanggal</span><span><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></span></div>
                            <div style="display:flex;justify-content:space-between;"><span>Kasir</span><span><?= htmlspecialchars($admin_nama) ?></span></div>
                            <div style="display:flex;justify-content:space-between;"><span>Pelanggan</span><span><?= htmlspecialchars($tx['nama_penerima'] ?? $tx['pelanggan']) ?></span></div>
                        </div>
                        <div style="border-top:1px dashed #000;border-bottom:1px dashed #000;padding:6px 0;margin-bottom:6px;">
                            <?php foreach ($details as $d): ?>
                                <div style="margin-bottom:4px;">
                                    <div><?= htmlspecialchars($d['nama_tanaman'] ?? 'Produk') ?></div>
                                    <div style="display:flex;justify-content:space-between;font-size:10px;">
                                        <span>&nbsp;&nbsp;<?= (int)$d['jumlah'] ?> x Rp <?= number_format($d['harga_satuan'], 0, ',', '.') ?></span>
                                        <span>Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size:10px;">
                            <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span></div>
                            <div style="display:flex;justify-content:space-between;"><span>PPN 11%</span><span>Rp <?= number_format($ppn, 0, ',', '.') ?></span></div>
                            <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:12px;border-top:1px solid #000;padding-top:4px;margin-top:4px;"><span>TOTAL</span><span>Rp <?= number_format($tx['total_harga'], 0, ',', '.') ?></span></div>
                        </div>
                        <div style="font-size:10px;border-top:1px dashed #000;margin-top:6px;padding-top:6px;">
                            <div style="display:flex;justify-content:space-between;"><span>Metode</span><span><?= strtoupper(htmlspecialchars($tx['metode_pembayaran'] ?? '-')) ?></span></div>
                            <div style="display:flex;justify-content:space-between;"><span>Status</span><span><?= htmlspecialchars($tx['status']) ?></span></div>
                        </div>
                        <div style="text-align:center;font-size:10px;margin-top:12px;padding-top:8px;border-top:1px dashed #000;">
                            <div>Terima kasih telah berbelanja!</div>
                            <div style="margin-top:2px;">~ PlantHub ~</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-stone-100 flex gap-2">
                <button onclick="cetakSekarang()" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-2.5 rounded-xl text-xs inline-flex items-center justify-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Cetak Sekarang
                </button>
                <button onclick="closePrintStruk()" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold py-2.5 rounded-xl text-xs">
                    Tutup
                </button>
            </div>
        </div>
    </div>

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

        function openPrintStruk() {
            const modal = document.getElementById('modalStruk');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closePrintStruk() {
            const modal = document.getElementById('modalStruk');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function cetakSekarang() {
            window.print();
        }

        document.getElementById('modalStruk').addEventListener('click', (e) => {
            if (e.target.id === 'modalStruk') closePrintStruk();
        });
    </script>
</body>
</html>