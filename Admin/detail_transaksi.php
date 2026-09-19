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

// Handle update status
$suksesMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['new_status'] ?? '';
    $allowed = ['Diproses','Dikirim','Selesai','Batal'];
    if (in_array($newStatus, $allowed)) {
        $esc = mysqli_real_escape_string($conn, $newStatus);
        if (mysqli_query($conn, "UPDATE transaksi SET status = '$esc' WHERE id = $id")) {
            $suksesMsg = "Status transaksi berhasil diperbarui.";
        }
    }
}

// Ambil transaksi
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
    SELECT td.*, p.nama_tanaman, p.gambar 
    FROM transaksi_detail td
    LEFT JOIN produk p ON td.produk_id = p.id
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
    ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],  // ← BARU
    ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],  // ← BARU
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
                    <p class="text-sm text-stone-500 mt-0.5"><?= htmlspecialchars($tx['kode_transaksi']) ?></p>
                </div>
                <a href="transaksi.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 hover:bg-stone-50">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
                </a>
            </div>

            <?php if ($suksesMsg): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32]"></i>
                    <span><?= htmlspecialchars($suksesMsg) ?></span>
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
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase border mt-1 <?= badgeStatus($tx['status']) ?>">
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
                                        <td class="py-3 px-4 font-semibold text-stone-800"><?= htmlspecialchars($d['nama_tanaman'] ?? 'Produk') ?></td>
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
                        <p class="text-xs text-stone-500 mt-0.5">Update status pesanan pelanggan (Diproses → Dikirim → Selesai).</p>
                    </div>

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
                </div>
            <?php endif; ?>

        </main>
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
    </script>
</body>
</html>