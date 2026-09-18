<?php
session_start();
require_once '../Config/database.php';

// =========================================================
// PROTEKSI LOGIN
// =========================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$nama_user  = $_SESSION['nama_user'] ?? 'Pelanggan';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: riwayat.php");
    exit;
}

// =========================================================
// AMBIL TRANSAKSI (pastikan milik user ini)
// =========================================================
$qTx = mysqli_query($conn, "
    SELECT * FROM transaksi 
    WHERE id = $id AND user_id = $user_id AND jenis_transaksi = 'penjualan'
    LIMIT 1
");

if (!$qTx || mysqli_num_rows($qTx) === 0) {
    header("Location: riwayat.php");
    exit;
}

$tx = mysqli_fetch_assoc($qTx);

// =========================================================
// AMBIL DETAIL TRANSAKSI
// =========================================================
$details = [];
$qDet = mysqli_query($conn, "
    SELECT td.*, p.nama_tanaman, p.gambar
    FROM transaksi_detail td
    LEFT JOIN produk p ON td.produk_id = p.id
    WHERE td.transaksi_id = $id
    ORDER BY td.id ASC
");
if ($qDet) {
    while ($row = mysqli_fetch_assoc($qDet)) $details[] = $row;
}

// =========================================================
// HITUNG SUBTOTAL, PPN, TOTAL
// =========================================================
$subtotal = 0;
foreach ($details as $d) {
    $subtotal += (float)$d['subtotal'];
}
$ppn    = $subtotal * 0.11;
$total  = $subtotal + $ppn;

// Info toko dari tabel pengaturan (kalau ada)
$toko = null;
$qToko = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1 LIMIT 1");
if ($qToko && mysqli_num_rows($qToko) > 0) $toko = mysqli_fetch_assoc($qToko);

$nama_toko    = $toko['nama_toko']    ?? 'PlantHub';
$nama_cabang  = $toko['nama_cabang']  ?? 'Cabang Utama';
$no_telepon   = $toko['no_telepon']   ?? '-';
$email_toko   = $toko['email_toko']   ?? '-';
$alamat_toko  = $toko['alamat_toko']  ?? '-';
$catatan_nota = $toko['catatan_nota'] ?? 'Terima kasih telah berbelanja di PlantHub!';

// Helper badge status
function badgeStatusNote($status) {
    switch ($status) {
        case 'Diproses': return 'bg-amber-100 text-amber-700';
        case 'Dikirim':  return 'bg-blue-100 text-blue-700';
        case 'Selesai':  return 'bg-emerald-100 text-emerald-700';
        case 'Batal':    return 'bg-rose-100 text-rose-700';
        default:         return 'bg-stone-100 text-stone-600';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota <?= htmlspecialchars($tx['kode_transaksi']) ?> - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }

        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; }
            main { padding: 0 !important; max-width: 100% !important; }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- HEADER (hilang saat print) -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm no-print">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Nota Pesanan <?= htmlspecialchars($tx['kode_transaksi']) ?></span>
            </div>

            <div class="flex items-center gap-3">
                <a href="cart.php" class="relative p-2 text-stone-600 hover:bg-stone-100 rounded-full">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
                <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($nama_user) ?></p>
                        <p class="text-xs text-stone-500">Pelanggan</p>
                    </div>
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- SIDEBAR (hilang saat print) -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4 no-print">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => false],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => false],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => true],
                        ['url' => 'chat.php',      'icon' => 'message-square', 'label' => 'Konsultasi',     'active' => false],
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
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 leading-tight">Troli Belanja</p>
                    <p class="text-[11px] text-stone-400 mt-0.5"><?= $cart_count ?> item</p>
                </div>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-6">

            <!-- Breadcrumb (hilang saat print) -->
            <nav class="no-print flex items-center gap-1.5 text-xs text-stone-500">
                <a href="dashboard.php" class="hover:text-[#2E7D32]">Beranda</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="riwayat.php" class="hover:text-[#2E7D32]">Riwayat Order</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-stone-800 font-semibold truncate">Nota</span>
            </nav>

            <!-- Aksi (hilang saat print) -->
            <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Nota Pesanan</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Detail transaksi <?= htmlspecialchars($tx['kode_transaksi']) ?>.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="riwayat.php" class="inline-flex items-center gap-2 bg-white border border-stone-300 hover:bg-stone-50 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-stone-700 transition">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Kembali
                    </a>
                    <button onclick="window.print()" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Cetak Nota
                    </button>
                </div>
            </div>

            <!-- NOTA -->
            <div class="print-container bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">

                <!-- Header Nota -->
                <div class="p-6 sm:p-8 border-b border-stone-100">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2.5 mb-2">
                                <div class="w-10 h-10 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                                    <i data-lucide="sprout" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-stone-800 leading-none"><?= htmlspecialchars($nama_toko) ?></h2>
                                    <p class="text-[11px] text-stone-500 mt-0.5"><?= htmlspecialchars($nama_cabang) ?></p>
                                </div>
                            </div>
                            <p class="text-[11px] text-stone-500 leading-relaxed mt-2">
                                <?= nl2br(htmlspecialchars($alamat_toko)) ?><br>
                                Telp: <?= htmlspecialchars($no_telepon) ?> &middot; Email: <?= htmlspecialchars($email_toko) ?>
                            </p>
                        </div>

                        <div class="text-left sm:text-right">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Nota Transaksi</p>
                            <p class="text-lg font-bold font-mono text-stone-800 mt-0.5"><?= htmlspecialchars($tx['kode_transaksi']) ?></p>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide mt-1 <?= badgeStatusNote($tx['status']) ?>">
                                <?= htmlspecialchars($tx['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Info Pelanggan & Transaksi -->
                <div class="p-6 sm:p-8 grid grid-cols-1 sm:grid-cols-2 gap-6 border-b border-stone-100">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400 mb-2">Dikirim Ke</p>
                        <p class="text-sm font-bold text-stone-800"><?= htmlspecialchars($tx['nama_penerima'] ?? '-') ?></p>
                        <p class="text-xs text-stone-600 mt-1"><?= htmlspecialchars($tx['telepon'] ?? '-') ?></p>
                        <p class="text-xs text-stone-600 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($tx['alamat'] ?? '-')) ?></p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-400 mb-2">Detail Transaksi</p>
                        <p class="text-xs text-stone-600">
                            <b class="text-stone-800">Tanggal:</b> <?= date('d M Y, H:i', strtotime($tx['created_at'])) ?> WIB
                        </p>
                        <p class="text-xs text-stone-600 mt-1">
                            <b class="text-stone-800">Metode:</b> <?= htmlspecialchars(ucfirst($tx['metode_pembayaran'] ?? '-')) ?>
                        </p>
                        <?php if (!empty($tx['catatan'])): ?>
                            <p class="text-xs text-stone-600 mt-1">
                                <b class="text-stone-800">Catatan:</b> <?= htmlspecialchars($tx['catatan']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabel Produk -->
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
                                <?php if (empty($details)): ?>
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-xs text-stone-400 italic">Tidak ada detail produk.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($details as $d): ?>
                                        <tr class="text-sm">
                                            <td class="py-3 pr-2 text-stone-500 text-xs"><?= $no++ ?></td>
                                            <td class="py-3 px-2">
                                                <p class="font-bold text-stone-800 text-xs"><?= htmlspecialchars($d['nama_tanaman'] ?? 'Produk') ?></p>
                                            </td>
                                            <td class="py-3 px-2 text-center text-xs font-semibold text-stone-700"><?= (int)$d['jumlah'] ?></td>
                                            <td class="py-3 px-2 text-right text-xs text-stone-700">Rp <?= number_format($d['harga_satuan'], 0, ',', '.') ?></td>
                                            <td class="py-3 pl-2 text-right text-xs font-bold text-stone-800">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ringkasan -->
                    <div class="mt-6 flex justify-end">
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
                                <span class="text-xl font-bold text-[#2E7D32]">Rp <?= number_format($total, 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Catatan Kaki -->
                <div class="p-6 sm:p-8 border-t border-stone-100 bg-stone-50/60">
                    <p class="text-[11px] text-stone-500 leading-relaxed text-center italic">
                        <?= nl2br(htmlspecialchars($catatan_nota)) ?>
                    </p>
                </div>

            </div>

            <!-- Tombol Bawah (hilang saat print) -->
            <div class="no-print flex items-center justify-between gap-3 pt-2">
                <a href="riwayat.php" class="inline-flex items-center gap-2 text-xs font-semibold text-stone-500 hover:text-stone-800">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Kembali ke Riwayat
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-2 bg-white border border-stone-300 hover:bg-stone-50 px-4 py-2 rounded-xl text-xs font-semibold text-stone-700 transition">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    Cetak Lagi
                </button>
            </div>

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