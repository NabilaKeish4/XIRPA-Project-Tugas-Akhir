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
// PROSES SIMPAN RESTOCK (AJAX POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan_restock') {
    header('Content-Type: application/json');

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $catatan     = trim($_POST['catatan'] ?? '');
    $items       = json_decode($_POST['items'] ?? '[]', true);

    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'Belum ada item untuk disimpan.']);
        exit;
    }

    if ($supplier_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih supplier terlebih dahulu.']);
        exit;
    }

    $total = 0;
    foreach ($items as $it) {
        $total += (int)$it['qty'] * (float)$it['harga'];
    }

    mysqli_begin_transaction($conn);
    try {
        $kode = 'RST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $catatanEsc = mysqli_real_escape_string($conn, $catatan);

        $sqlTx = "INSERT INTO transaksi 
                  (kode_transaksi, user_id, supplier_id, jenis_transaksi, total_harga, metode_pembayaran, status, catatan, created_at) 
                  VALUES 
                  ('$kode', NULL, $supplier_id, 'pembelian', $total, 'transfer', 'Selesai', '$catatanEsc', NOW())";
        if (!mysqli_query($conn, $sqlTx)) throw new Exception(mysqli_error($conn));

        $transaksi_id = mysqli_insert_id($conn);

        foreach ($items as $it) {
            $pid   = (int)$it['id'];
            $qty   = (int)$it['qty'];
            $harga = (float)$it['harga'];
            $sub   = $qty * $harga;

            $sqlDet = "INSERT INTO transaksi_detail (transaksi_id, produk_id, jumlah, harga_satuan, subtotal) 
                       VALUES ($transaksi_id, $pid, $qty, $harga, $sub)";
            if (!mysqli_query($conn, $sqlDet)) throw new Exception(mysqli_error($conn));

            $sqlStok = "UPDATE produk SET stok = stok + $qty, harga_beli = $harga WHERE id = $pid";
            if (!mysqli_query($conn, $sqlStok)) throw new Exception(mysqli_error($conn));
        }

        mysqli_commit($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Restock berhasil disimpan',
            'kode'    => $kode,
            'total'   => $total,
            'id'      => $transaksi_id
        ]);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================
// AMBIL DETAIL RESTOCK (untuk modal)
// =========================================================
if (isset($_GET['detail_id']) && (int)$_GET['detail_id'] > 0) {
    header('Content-Type: application/json');
    $id = (int)$_GET['detail_id'];

    $qTx = mysqli_query($conn, "
        SELECT t.*, s.nama_supplier, s.kontak AS supplier_kontak, s.alamat AS supplier_alamat
        FROM transaksi t
        LEFT JOIN supplier s ON t.supplier_id = s.id
        WHERE t.id = $id AND t.jenis_transaksi = 'pembelian'
        LIMIT 1
    ");
    if (!$qTx || mysqli_num_rows($qTx) === 0) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan.']);
        exit;
    }
    $tx = mysqli_fetch_assoc($qTx);

    $items = [];
    $qDet = mysqli_query($conn, "
        SELECT td.*, p.nama_tanaman, k.nama_kategori
        FROM transaksi_detail td
        LEFT JOIN produk p ON td.produk_id = p.id
        LEFT JOIN kategori k ON p.kategori_id = k.id
        WHERE td.transaksi_id = $id
        ORDER BY td.id ASC
    ");
    if ($qDet) while ($r = mysqli_fetch_assoc($qDet)) $items[] = $r;

    echo json_encode([
        'success' => true,
        'tx' => $tx,
        'items' => $items,
    ]);
    exit;
}

// =========================================================
// AMBIL SUPPLIER
// =========================================================
$supplierList = [];
$qSup = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nama_supplier ASC");
if ($qSup) while ($r = mysqli_fetch_assoc($qSup)) $supplierList[] = $r;
$supplierKosong = empty($supplierList);

// =========================================================
// AMBIL PRODUK
// =========================================================
$produkList = [];
$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    ORDER BY p.nama_tanaman ASC
");
if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produkList[] = $r;

// =========================================================
// RIWAYAT RESTOCK TERAKHIR
// =========================================================
$riwayat = [];
$qRiwayat = mysqli_query($conn, "
    SELECT t.*, s.nama_supplier,
           (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item
    FROM transaksi t
    LEFT JOIN supplier s ON t.supplier_id = s.id
    WHERE t.jenis_transaksi = 'pembelian'
    ORDER BY t.created_at DESC
    LIMIT 10
");
if ($qRiwayat) while ($r = mysqli_fetch_assoc($qRiwayat)) $riwayat[] = $r;

// Statistik
$statRestock = 0;
$statTotal   = 0;
$qStat = mysqli_query($conn, "
    SELECT COUNT(*) AS total, COALESCE(SUM(total_harga), 0) AS nilai
    FROM transaksi WHERE jenis_transaksi = 'pembelian'
");
if ($qStat) {
    $r = mysqli_fetch_assoc($qStat);
    $statRestock = (int)$r['total'];
    $statTotal   = (float)$r['nilai'];
}

$invoicePreview = 'RST-' . date('Ymd') . '-XXXXX';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Pembelian Supplier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 9999px; }

        /* Print invoice */
        @media print {
            body * { visibility: hidden !important; }
            #areaCetakInvoice, #areaCetakInvoice * { visibility: visible !important; }
            #areaCetakInvoice {
                position: absolute !important;
                left: 0; top: 0;
                width: 100% !important;
                padding: 10mm !important;
                margin: 0 !important;
                background: #fff !important;
                color: #000 !important;
                font-family: 'Plus Jakarta Sans', sans-serif !important;
            }
            @page { size: A4; margin: 10mm; }
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
                <span class="text-xs text-stone-500 self-center">Pembelian Supplier (Restock)</span>
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
                        ['url' => 'restock.php',    'icon' => 'truck',          'label' => 'Pembelian',        'active' => true],
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
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Pembelian Supplier (Restock)</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Catat pembelian dari supplier, stok produk akan otomatis bertambah.</p>
                </div>
                <div class="self-start sm:self-auto bg-white border border-stone-200/80 px-3.5 py-2 rounded-xl flex items-center gap-2 shadow-sm">
                    <i data-lucide="receipt" class="w-4 h-4 text-stone-500"></i>
                    <span class="text-xs text-stone-500 font-medium">Preview:</span>
                    <span class="text-xs font-bold font-mono text-[#2E7D32]"><?= $invoicePreview ?></span>
                </div>
            </div>

            <!-- WARNING SUPPLIER KOSONG -->
            <?php if ($supplierKosong): ?>
                <div class="p-5 bg-amber-50 border-2 border-amber-200 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-amber-900">Belum ada supplier terdaftar</p>
                        <p class="text-xs text-amber-700 mt-0.5">Kamu harus menambahkan supplier dulu sebelum bisa melakukan restock.</p>
                    </div>
                    <a href="supplier.php" class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition shrink-0">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah Supplier
                    </a>
                </div>
            <?php endif; ?>

            <!-- STATISTIK -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Restock</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $statRestock ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Transaksi pembelian</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Nilai Pembelian</p>
                    <p class="text-lg font-bold text-stone-800 mt-1">Rp <?= number_format($statTotal, 0, ',', '.') ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Total pengeluaran</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-blue-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Supplier Terdaftar</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= count($supplierList) ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Vendor aktif</p>
                </div>
            </div>

            <!-- FORM RESTOCK -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-5 <?= $supplierKosong ? 'opacity-60 pointer-events-none' : '' ?>">
                <div class="border-b border-stone-100 pb-3">
                    <h2 class="text-base font-bold text-stone-800">Form Pembelian Baru</h2>
                    <p class="text-xs text-stone-500 mt-0.5">Pilih supplier, tambah item, lalu simpan.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Supplier <span class="text-rose-500">*</span></label>
                        <select id="inSupplier" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($supplierList as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['nama_supplier']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Tanggal</label>
                        <input type="date" id="inTanggal" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>
                </div>

                <!-- Quick Add Item -->
                <div class="bg-emerald-50/50 border border-emerald-100 rounded-xl p-4 space-y-3">
                    <div class="flex items-center gap-2 text-[#2E7D32] font-bold text-xs uppercase tracking-wider">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        <span>Tambah Item</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-5">
                            <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Produk</label>
                            <select id="inProduk" onchange="autoIsiHarga()" class="w-full px-3 py-2 text-sm bg-white border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32]">
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($produkList as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" data-nama="<?= htmlspecialchars($p['nama_tanaman']) ?>" data-harga="<?= (float)$p['harga_beli'] ?>" data-stok="<?= (int)$p['stok'] ?>">
                                        <?= htmlspecialchars($p['nama_tanaman']) ?> (Stok: <?= (int)$p['stok'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Qty</label>
                            <input type="number" id="inQty" min="1" value="1" class="w-full px-3 py-2 text-sm bg-white border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32] text-center font-semibold">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Harga Beli / Unit</label>
                            <input type="number" id="inHargaBeli" min="0" step="500" placeholder="0" class="w-full px-3 py-2 text-sm bg-white border border-stone-200 rounded-lg focus:outline-none focus:border-[#2E7D32] font-bold">
                        </div>
                        <div class="md:col-span-2">
                            <button type="button" onclick="tambahItem()" class="w-full py-2 bg-[#2E7D32] hover:bg-emerald-800 text-white rounded-lg text-xs font-bold inline-flex items-center justify-center gap-1.5">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Daftar Item -->
                <div class="border border-stone-200/80 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-stone-50/70 border-b border-stone-200/80 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-stone-800">Item Pembelian</h3>
                        <span id="itemCountBadge" class="bg-stone-200 text-stone-700 text-xs font-bold px-2.5 py-1 rounded-full">0 Item</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-stone-50/40 border-b border-stone-200/60 text-[10px] font-bold text-stone-500 uppercase tracking-wider">
                                    <th class="py-2.5 px-3 w-10">No</th>
                                    <th class="py-2.5 px-3">Produk</th>
                                    <th class="py-2.5 px-3 text-center w-24">Qty</th>
                                    <th class="py-2.5 px-3 text-right w-32">Harga Beli</th>
                                    <th class="py-2.5 px-3 text-right w-32">Subtotal</th>
                                    <th class="py-2.5 px-3 text-center w-14">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody" class="divide-y divide-stone-100 text-xs">
                                <tr><td colspan="6" class="py-8 text-center text-stone-400">Belum ada item. Tambahkan dari form di atas.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Total + Simpan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Catatan (Opsional)</label>
                        <textarea id="inCatatan" rows="3" placeholder="Contoh: Pengiriman via kurir toko, pembayaran lunas transfer..." class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"></textarea>
                    </div>
                    <div class="space-y-3">
                        <div class="bg-[#E8F5E9] border-2 border-[#2E7D32]/30 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold text-stone-500 uppercase tracking-wider">Total Pembelian</p>
                                <p class="text-xs text-stone-500 mt-0.5">Stok otomatis bertambah setelah disimpan</p>
                            </div>
                            <p id="totalText" class="text-2xl font-bold text-[#2E7D32]">Rp 0</p>
                        </div>

                        <button onclick="simpanRestock()" id="btnSimpan" class="w-full py-3.5 bg-[#2E7D32] hover:bg-emerald-800 text-white rounded-xl text-sm font-bold shadow-sm inline-flex items-center justify-center gap-2 transition">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Simpan Pembelian</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- RIWAYAT RESTOCK -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-stone-800">Riwayat Restock Terakhir</h2>
                        <p class="text-xs text-stone-500 mt-0.5">10 transaksi pembelian terbaru. Klik row untuk lihat detail.</p>
                    </div>
                    <span class="p-2 bg-stone-100 rounded-lg text-stone-500">
                        <i data-lucide="history" class="w-4 h-4"></i>
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3 px-6">Kode</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4 text-center">Item</th>
                                <th class="py-3 px-4 text-right">Total</th>
                                <th class="py-3 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($riwayat)): ?>
                                <tr><td colspan="6" class="py-8 text-center text-stone-400">Belum ada riwayat restock.</td></tr>
                            <?php else: ?>
                                <?php foreach ($riwayat as $r): ?>
                                    <tr class="hover:bg-stone-50/60 cursor-pointer" onclick="openDetail(<?= (int)$r['id'] ?>)">
                                        <td class="py-3 px-6 font-mono text-[11px] font-semibold text-stone-700"><?= htmlspecialchars($r['kode_transaksi']) ?></td>
                                        <td class="py-3 px-4 font-semibold text-stone-800"><?= htmlspecialchars($r['nama_supplier'] ?? 'Tanpa Supplier') ?></td>
                                        <td class="py-3 px-4 text-stone-500"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
                                        <td class="py-3 px-4 text-center font-semibold text-stone-700"><?= (int)$r['jml_item'] ?></td>
                                        <td class="py-3 px-4 text-right font-bold text-[#D97706]">Rp <?= number_format($r['total_harga'], 0, ',', '.') ?></td>
                                        <td class="py-3 px-6 text-center">
                                            <button onclick="event.stopPropagation(); openDetail(<?= (int)$r['id'] ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-[#2E7D32] hover:bg-emerald-50 rounded-lg transition">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL SUKSES -->
    <div id="modalSukses" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 space-y-4 text-center">
            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto">
                <i data-lucide="check-circle" class="w-9 h-9 text-[#2E7D32]"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-stone-800">Restock Berhasil!</h3>
                <p class="text-sm text-stone-500 mt-1">Pembelian supplier telah disimpan.</p>
            </div>
            <div class="bg-stone-50 border border-stone-200 rounded-xl p-3 text-left text-xs space-y-1">
                <div class="flex justify-between"><span class="text-stone-500">Kode</span><span id="mKode" class="font-bold font-mono">-</span></div>
                <div class="flex justify-between"><span class="text-stone-500">Total</span><span id="mTotal" class="font-bold text-[#2E7D32]">-</span></div>
            </div>
            <button onclick="closeModalSukses()" class="w-full bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-2.5 rounded-xl text-sm transition">
                Selesai
            </button>
        </div>
    </div>

    <!-- MODAL DETAIL RIWAYAT -->
    <div id="modalDetail" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full my-8">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-stone-800">Detail Pembelian</h3>
                    <p class="text-[11px] text-stone-500 mt-0.5" id="dKode">-</p>
                </div>
                <button onclick="closeDetail()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div id="dLoading" class="p-12 text-center">
                <div class="w-8 h-8 border-3 border-stone-200 border-t-[#2E7D32] rounded-full animate-spin mx-auto"></div>
                <p class="text-xs text-stone-500 mt-3">Memuat detail...</p>
            </div>

            <div id="dContent" class="hidden">
                <div id="areaCetakInvoice" class="p-6 space-y-5">

                    <!-- Header invoice -->
                    <div class="flex items-start justify-between border-b-2 border-[#2E7D32] pb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <div class="w-8 h-8 rounded-lg bg-[#2E7D32] flex items-center justify-center text-white">
                                    <i data-lucide="sprout" class="w-4 h-4"></i>
                                </div>
                                <span class="text-lg font-bold text-stone-800">PlantHub</span>
                            </div>
                            <p class="text-[10px] text-stone-500">Sistem Manajemen Toko Tanaman</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider">Invoice Pembelian</p>
                            <p class="text-sm font-bold font-mono text-stone-800" id="invKode">-</p>
                            <p class="text-[10px] text-stone-500" id="invTanggal">-</p>
                        </div>
                    </div>

                    <!-- Info supplier -->
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Supplier</p>
                            <p class="font-bold text-stone-800" id="dSupplier">-</p>
                            <p class="text-stone-500 text-[11px] mt-0.5" id="dSupplierKontak">-</p>
                            <p class="text-stone-500 text-[11px]" id="dSupplierAlamat">-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Info Transaksi</p>
                            <p class="text-stone-600"><b>Status:</b> <span id="dStatus">-</span></p>
                            <p class="text-stone-600 mt-0.5"><b>Metode:</b> <span id="dMetode">-</span></p>
                            <p class="text-stone-600 mt-0.5"><b>Kasir:</b> <?= htmlspecialchars($admin_nama) ?></p>
                        </div>
                    </div>

                    <!-- Tabel item -->
                    <div class="border border-stone-200 rounded-lg overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-stone-50 text-[10px] font-bold text-stone-500 uppercase tracking-wider">
                                    <th class="py-2.5 px-3">Produk</th>
                                    <th class="py-2.5 px-3 text-center">Qty</th>
                                    <th class="py-2.5 px-3 text-right">Harga Beli</th>
                                    <th class="py-2.5 px-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="dItems" class="divide-y divide-stone-100 text-xs">
                                <!-- Diisi JS -->
                            </tbody>
                            <tfoot>
                                <tr class="bg-stone-50 border-t-2 border-stone-200">
                                    <td colspan="3" class="py-2.5 px-3 text-right font-bold text-stone-700">Total</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-[#2E7D32] text-sm" id="dTotal">Rp 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Catatan -->
                    <div id="dCatatanWrap" class="hidden">
                        <p class="text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-1">Catatan</p>
                        <p class="text-xs text-stone-600 italic" id="dCatatan">-</p>
                    </div>

                    <div class="text-center text-[10px] text-stone-400 pt-3 border-t border-dashed border-stone-200">
                        Dokumen ini dicetak dari sistem PlantHub
                    </div>
                </div>

                <div class="p-4 border-t border-stone-100 flex gap-2">
                    <button onclick="window.print()" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold py-2.5 rounded-xl text-xs inline-flex items-center justify-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i> Cetak Invoice
                    </button>
                    <button onclick="closeDetail()" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-2.5 rounded-xl text-xs">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        let items = [];

        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }

        function formatRp(v) { return 'Rp ' + Number(v).toLocaleString('id-ID'); }

        function autoIsiHarga() {
            const sel = document.getElementById('inProduk');
            const opt = sel.options[sel.selectedIndex];
            const harga = opt.dataset.harga || 0;
            document.getElementById('inHargaBeli').value = harga > 0 ? harga : '';
        }

        function tambahItem() {
            const sel = document.getElementById('inProduk');
            const pid = parseInt(sel.value);
            if (!pid) { alert('Pilih produk dulu.'); return; }

            const opt = sel.options[sel.selectedIndex];
            const nama = opt.dataset.nama;
            const qty = parseInt(document.getElementById('inQty').value);
            const harga = parseFloat(document.getElementById('inHargaBeli').value);

            if (!qty || qty < 1) { alert('Qty minimal 1.'); return; }
            if (!harga || harga < 0) { alert('Harga beli tidak valid.'); return; }

            const existing = items.find(i => i.id === pid);
            if (existing) {
                existing.qty += qty;
                existing.harga = harga;
            } else {
                items.push({ id: pid, nama, qty, harga });
            }

            sel.value = '';
            document.getElementById('inQty').value = 1;
            document.getElementById('inHargaBeli').value = '';

            renderItems();
        }

        function hapusItem(idx) {
            items.splice(idx, 1);
            renderItems();
        }

        function ubahQty(idx, delta) {
            const newQty = items[idx].qty + delta;
            if (newQty < 1) return;
            items[idx].qty = newQty;
            renderItems();
        }

        function renderItems() {
            const tbody = document.getElementById('itemsBody');
            tbody.innerHTML = '';

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-stone-400">Belum ada item. Tambahkan dari form di atas.</td></tr>';
                document.getElementById('itemCountBadge').innerText = '0 Item';
                document.getElementById('totalText').innerText = 'Rp 0';
                return;
            }

            let total = 0;
            items.forEach((it, idx) => {
                const sub = it.qty * it.harga;
                total += sub;
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-stone-50/60';
                tr.innerHTML = `
                    <td class="py-3 px-3 font-mono text-stone-400">${idx + 1}</td>
                    <td class="py-3 px-3 font-semibold text-stone-800">${it.nama}</td>
                    <td class="py-3 px-3">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="ubahQty(${idx}, -1)" class="w-6 h-6 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs">−</button>
                            <span class="w-10 text-center font-bold text-stone-800">${it.qty}</span>
                            <button onclick="ubahQty(${idx}, 1)" class="w-6 h-6 rounded bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs">+</button>
                        </div>
                    </td>
                    <td class="py-3 px-3 text-right">${formatRp(it.harga)}</td>
                    <td class="py-3 px-3 text-right font-bold text-stone-800">${formatRp(sub)}</td>
                    <td class="py-3 px-3 text-center">
                        <button onclick="hapusItem(${idx})" class="p-1.5 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('itemCountBadge').innerText = items.length + ' Item';
            document.getElementById('totalText').innerText = formatRp(total);
            lucide.createIcons();
        }

        function simpanRestock() {
            const supplier_id = document.getElementById('inSupplier').value;
            const catatan = document.getElementById('inCatatan').value;

            if (!supplier_id) { alert('Pilih supplier terlebih dahulu.'); return; }
            if (items.length === 0) { alert('Belum ada item.'); return; }

            const btn = document.getElementById('btnSimpan');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Menyimpan...';
            lucide.createIcons();

            const fd = new FormData();
            fd.append('action', 'simpan_restock');
            fd.append('supplier_id', supplier_id);
            fd.append('catatan', catatan);
            fd.append('items', JSON.stringify(items));

            fetch('restock.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('mKode').innerText = data.kode;
                        document.getElementById('mTotal').innerText = formatRp(data.total);
                        const modal = document.getElementById('modalSukses');
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> <span>Simpan Pembelian</span>';
                    lucide.createIcons();
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> <span>Simpan Pembelian</span>';
                    lucide.createIcons();
                });
        }

        function closeModalSukses() {
            window.location.href = 'restock.php';
        }

        // ========================================================
        // DETAIL VIEW
        // ========================================================
        function openDetail(id) {
            const modal = document.getElementById('modalDetail');
            const loading = document.getElementById('dLoading');
            const content = document.getElementById('dContent');

            loading.classList.remove('hidden');
            content.classList.add('hidden');

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            fetch('restock.php?detail_id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Gagal memuat detail: ' + data.message);
                        closeDetail();
                        return;
                    }

                    const tx = data.tx;

                    document.getElementById('dKode').innerText = tx.kode_transaksi;
                    document.getElementById('invKode').innerText = tx.kode_transaksi;
                    document.getElementById('invTanggal').innerText = new Date(tx.created_at).toLocaleString('id-ID', {
                        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                    });

                    document.getElementById('dSupplier').innerText = tx.nama_supplier || 'Tanpa Supplier';
                    document.getElementById('dSupplierKontak').innerText = tx.supplier_kontak || '-';
                    document.getElementById('dSupplierAlamat').innerText = tx.supplier_alamat || '-';
                    document.getElementById('dStatus').innerText = tx.status;
                    document.getElementById('dMetode').innerText = (tx.metode_pembayaran || '-').toUpperCase();

                    const itemsBody = document.getElementById('dItems');
                    itemsBody.innerHTML = '';
                    data.items.forEach(it => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-stone-50/60';
                        tr.innerHTML = `
                            <td class="py-2.5 px-3">
                                <p class="font-semibold text-stone-800">${it.nama_tanaman || 'Produk'}</p>
                                <p class="text-[10px] text-stone-400">${it.nama_kategori || '-'}</p>
                            </td>
                            <td class="py-2.5 px-3 text-center font-semibold">${it.jumlah}</td>
                            <td class="py-2.5 px-3 text-right">${formatRp(it.harga_satuan)}</td>
                            <td class="py-2.5 px-3 text-right font-bold text-stone-800">${formatRp(it.subtotal)}</td>
                        `;
                        itemsBody.appendChild(tr);
                    });

                    document.getElementById('dTotal').innerText = formatRp(tx.total_harga);

                    // Catatan
                    const catatanWrap = document.getElementById('dCatatanWrap');
                    if (tx.catatan && tx.catatan.trim() !== '') {
                        document.getElementById('dCatatan').innerText = tx.catatan;
                        catatanWrap.classList.remove('hidden');
                    } else {
                        catatanWrap.classList.add('hidden');
                    }

                    loading.classList.add('hidden');
                    content.classList.remove('hidden');
                    lucide.createIcons();
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    closeDetail();
                });
        }

        function closeDetail() {
            const modal = document.getElementById('modalDetail');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('modalDetail').addEventListener('click', (e) => {
            if (e.target.id === 'modalDetail') closeDetail();
        });

        renderItems();
    </script>
</body>
</html>