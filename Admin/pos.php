<?php
require_once __DIR__ . '/includes/init.php';

// =========================================================
// PROSES CHECKOUT POS (AJAX POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout_pos') {
    header('Content-Type: application/json');

    $cartJson  = $_POST['cart'] ?? '[]';
    $metode    = $_POST['metode'] ?? 'tunai';
    $nama      = trim($_POST['nama_pelanggan'] ?? 'Pelanggan Walk-in');
    $bayar     = (float)($_POST['jumlah_bayar'] ?? 0);
    $catatan   = trim($_POST['catatan'] ?? '');

    $cartIn = json_decode($cartJson, true);

    if (!is_array($cartIn) || empty($cartIn)) {
        echo json_encode(['success' => false, 'message' => 'Keranjang kosong.']);
        exit;
    }

    if ($nama === '') $nama = 'Pelanggan Walk-in';
    $nama = mb_substr($nama, 0, 100);

    $metodeValid = ['tunai', 'qris', 'debit', 'transfer', 'ewallet', 'cod'];
    if (!in_array($metode, $metodeValid, true)) $metode = 'tunai';

    // Gabungkan item dengan produk_id yang sama & ambil qty saja dari browser.
    // Harga, nama, dan stok SELALU diambil dari database (bukan dipercaya dari browser).
    $qtyById = [];
    foreach ($cartIn as $item) {
        $pid = (int)($item['id'] ?? 0);
        $qty = (int)($item['qty'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $qtyById[$pid] = ($qtyById[$pid] ?? 0) + $qty;
    }
    if (empty($qtyById)) {
        echo json_encode(['success' => false, 'message' => 'Keranjang kosong.']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $cart     = [];
        $subtotal = 0;

        // Kunci baris produk agar stok tidak bisa dijual ganda oleh kasir lain secara bersamaan
        $ids = implode(',', array_keys($qtyById));
        $qP  = mysqli_query($conn, "SELECT p.id, p.nama_tanaman, p.harga_jual, p.stok, k.nama_kategori
                                    FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id
                                    WHERE p.id IN ($ids) FOR UPDATE");
        $produkDb = [];
        while ($r = mysqli_fetch_assoc($qP)) $produkDb[(int)$r['id']] = $r;

        foreach ($qtyById as $pid => $qty) {
            if (!isset($produkDb[$pid])) throw new Exception("Produk ID $pid tidak ditemukan.");
            $p = $produkDb[$pid];
            if ((int)$p['stok'] < $qty) {
                throw new Exception("Stok \"{$p['nama_tanaman']}\" tidak cukup (tersisa {$p['stok']}).");
            }
            $harga = (float)$p['harga_jual'];
            $subtotal += $qty * $harga;
            $cart[] = [
                'id' => $pid, 'nama' => $p['nama_tanaman'], 'kategori' => $p['nama_kategori'] ?? 'Tanaman',
                'harga' => $harga, 'qty' => $qty, 'stok_baru' => (int)$p['stok'] - $qty,
            ];
        }

        $ppn   = round($subtotal * 0.11);
        $total = $subtotal + $ppn;

        if ($metode === 'tunai') {
            if ($bayar < $total) throw new Exception('Jumlah bayar kurang dari total.');
        } else {
            $bayar = $total; // non-tunai: dianggap dibayar pas
        }

        // Kode unik (ulang bila bentrok)
        $kode = '';
        for ($i = 0; $i < 5; $i++) {
            $kode = 'POS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $cek = mysqli_query($conn, "SELECT id FROM transaksi WHERE kode_transaksi = '$kode' LIMIT 1");
            if (!$cek || mysqli_num_rows($cek) === 0) break;
        }

        $namaEsc    = mysqli_real_escape_string($conn, $nama);
        $catatanEsc = mysqli_real_escape_string($conn, $catatan);

        $sqlTx = "INSERT INTO transaksi
                  (kode_transaksi, user_id, jenis_transaksi, nama_penerima, total_harga, metode_pembayaran, status, catatan, created_at)
                  VALUES
                  ('$kode', NULL, 'penjualan', '$namaEsc', $total, '$metode', 'Selesai', '$catatanEsc', NOW())";
        if (!mysqli_query($conn, $sqlTx)) throw new Exception(mysqli_error($conn));

        $transaksi_id = mysqli_insert_id($conn);

        foreach ($cart as $item) {
            $pid   = (int)$item['id'];
            $qty   = (int)$item['qty'];
            $harga = (float)$item['harga'];
            $sub   = $qty * $harga;

            $sqlDet = "INSERT INTO transaksi_detail (transaksi_id, produk_id, jumlah, harga_satuan, subtotal)
                       VALUES ($transaksi_id, $pid, $qty, $harga, $sub)";
            if (!mysqli_query($conn, $sqlDet)) throw new Exception(mysqli_error($conn));

            $sqlStok = "UPDATE produk SET stok = stok - $qty WHERE id = $pid AND stok >= $qty";
            if (!mysqli_query($conn, $sqlStok)) throw new Exception(mysqli_error($conn));
            if (mysqli_affected_rows($conn) === 0) throw new Exception("Stok produk ID $pid tidak cukup.");
        }

        mysqli_commit($conn);

        echo json_encode([
            'success'    => true,
            'message'    => 'Transaksi berhasil',
            'kode'       => $kode,
            'total'      => $total,
            'subtotal'   => $subtotal,
            'ppn'        => $ppn,
            'bayar'      => $bayar,
            'kembali'    => $metode === 'tunai' ? max(0, $bayar - $total) : 0,
            'metode'     => $metode,
            'nama'       => $nama,
            'catatan'    => $catatan,
            'id'         => $transaksi_id,
            'waktu'      => date('d/m/Y H:i'),
            'kasir'      => $admin_nama,
            'items'      => $cart,
            'toko'       => [
                'nama'    => $nama_toko,
                'cabang'  => $nama_cabang,
                'alamat'  => $alamat_toko,
                'telepon' => $no_telepon,
                'catatan' => $catatan_nota ?: 'Terima kasih telah berbelanja!',
            ],
        ]);
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================
// AMBIL DATA PRODUK & KATEGORI
// =========================================================
$produkList = [];
$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.stok > 0 
    ORDER BY p.nama_tanaman ASC
");
if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produkList[] = $r;

$kategoriList = [];
$qKat = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id ASC");
if ($qKat) while ($r = mysqli_fetch_assoc($qKat)) $kategoriList[] = $r;

function gambarPOS($nama) {
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
    <title><?= e($nama_toko) ?> - Kasir POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 9999px; }

        /* PRINT THERMAL 58mm */
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
<body class="antialiased h-screen flex flex-col overflow-hidden">

    <!-- HEADER -->
    <header class="bg-white border-b border-stone-200/80 shadow-sm shrink-0 z-30">
        <div class="px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="p-2 rounded-lg text-stone-600 hover:bg-stone-100" title="Kembali">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div class="flex items-center gap-2.5">
                    <?php if ($logo_url !== ''): ?>
                        <div class="w-9 h-9 rounded-xl bg-white flex items-center justify-center shadow-sm overflow-hidden border border-stone-100">
                            <img src="<?= e($logo_url) ?>" alt="Logo" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                            <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-base font-bold text-stone-800 leading-tight">Kasir (POS)</h1>
                        <p class="text-[11px] text-stone-400">Kasir: <?= htmlspecialchars($admin_nama) ?></p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-[#2E7D32] border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-[#2E7D32] animate-pulse"></span> Sistem Online
                </span>
                <button onclick="resetSesi()" class="flex items-center gap-2 bg-stone-100 hover:bg-stone-200 px-3 py-2 rounded-xl text-xs font-bold text-stone-700 transition">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline">Reset Sesi</span>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN POS LAYOUT -->
    <div class="flex flex-1 overflow-hidden">

        <!-- KIRI: KATALOG PRODUK -->
        <section class="flex-1 lg:w-[60%] flex flex-col border-r border-stone-200 bg-[#F9F8F6] overflow-hidden">
            <div class="p-4 bg-white border-b border-stone-200/80 space-y-3 shrink-0">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    <input type="text" id="searchInput" oninput="renderProduk()" placeholder="Cari produk..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                </div>

                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <button onclick="setKategori(0, this)" class="cat-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition bg-[#2E7D32] text-white">Semua</button>
                    <?php foreach ($kategoriList as $k): ?>
                        <button onclick="setKategori(<?= (int)$k['id'] ?>, this)" class="cat-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition bg-stone-100 text-stone-600 hover:bg-stone-200">
                            <?= htmlspecialchars($k['nama_kategori']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="gridProduk" class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                    <?php foreach ($produkList as $p): 
                        $imgSrc = gambarPOS($p['gambar']);
                    ?>
                        <div class="produk-card bg-white rounded-xl border border-stone-200/80 shadow-sm hover:shadow-md transition overflow-hidden cursor-pointer group"
                             data-id="<?= (int)$p['id'] ?>"
                             data-nama="<?= htmlspecialchars($p['nama_tanaman']) ?>"
                             data-harga="<?= (float)$p['harga_jual'] ?>"
                             data-stok="<?= (int)$p['stok'] ?>"
                             data-kategori="<?= (int)$p['kategori_id'] ?>"
                             data-nama-kategori="<?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?>"
                             data-gambar="<?= htmlspecialchars($imgSrc) ?>"
                             onclick="tambahKeKeranjang(this)">
                            <div class="relative bg-stone-100 aspect-square overflow-hidden">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover group-hover:scale-105 transition" alt="<?= htmlspecialchars($p['nama_tanaman']) ?>">
                                <span class="absolute top-2 left-2 bg-white/90 backdrop-blur-sm text-stone-700 text-[10px] font-bold px-2 py-0.5 rounded-md border border-stone-200">
                                    Stok: <?= (int)$p['stok'] ?>
                                </span>
                            </div>
                            <div class="p-2.5">
                                <p class="text-[9px] font-bold uppercase text-stone-400 truncate"><?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?></p>
                                <p class="text-xs font-bold text-stone-800 leading-snug truncate"><?= htmlspecialchars($p['nama_tanaman']) ?></p>
                                <p class="text-sm font-bold text-[#2E7D32] mt-1">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($produkList)): ?>
                    <div class="text-center py-20">
                        <div class="w-16 h-16 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="package-x" class="w-7 h-7 text-stone-400"></i>
                        </div>
                        <p class="text-sm font-semibold text-stone-700">Belum ada produk tersedia</p>
                        <p class="text-xs text-stone-500 mt-1">Tambah produk di <a href="stok.php" class="text-[#2E7D32] font-bold hover:underline">Stok & Produk</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- KANAN: KERANJANG & PEMBAYARAN (Bisa di-scroll utuh) -->
        <section id="panelKeranjang" class="hidden lg:flex w-[40%] flex-col bg-white border-l border-stone-200 shadow-xl z-10">
            
            <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                
                <!-- Info Pelanggan -->
                <div class="p-4 border-b border-stone-200/80 bg-stone-50/60 shrink-0">
                    <div class="flex items-center justify-between mb-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="p-2 bg-stone-200/70 text-stone-600 rounded-xl">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <p class="text-[10px] text-stone-400 font-bold uppercase tracking-wider">Pelanggan</p>
                        </div>
                        <span class="text-[10px] font-bold text-[#2E7D32] bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-md">POS</span>
                    </div>
                    <input type="text" id="inNamaPelanggan" value="Pelanggan Walk-in" placeholder="Nama pelanggan..." class="w-full px-3.5 py-2 text-sm bg-white border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] font-semibold text-stone-800">
                    <p class="text-[10px] text-stone-400 mt-1.5">Kosongkan untuk pelanggan walk-in.</p>
                </div>

                <!-- Cart Header -->
                <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between shrink-0 bg-white">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-bold text-stone-800 uppercase tracking-wider">Keranjang</h2>
                        <span id="cartCountBadge" class="bg-[#E8F5E9] text-[#2E7D32] text-[10px] font-bold px-2 py-0.5 rounded-full">0 Item</span>
                    </div>
                    <button onclick="kosongkanKeranjang()" class="text-[10px] text-stone-400 hover:text-rose-600 flex items-center gap-1 font-semibold">
                        <i data-lucide="trash-2" class="w-3 h-3"></i> Kosongkan
                    </button>
                </div>

                <!-- Cart Items -->
                <div id="cartList" class="px-4 py-2 divide-y divide-stone-100 bg-white">
                    <!-- Diisi JS -->
                </div>

                <!-- Summary & Checkout -->
                <div class="p-5 bg-stone-50/80 border-t border-stone-200 space-y-3 mt-auto shrink-0">

                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between text-stone-500">
                            <span>Subtotal</span>
                            <span id="subtotalText" class="font-semibold text-stone-700">Rp 0</span>
                        </div>
                        <div class="flex justify-between text-stone-500">
                            <span>PPN 11%</span>
                            <span id="ppnText" class="font-semibold text-stone-700">Rp 0</span>
                        </div>
                        <div class="pt-2 border-t border-stone-200 flex justify-between items-baseline">
                            <span class="text-sm font-bold text-stone-800">Total</span>
                            <span id="totalText" class="text-xl font-bold text-[#2E7D32]">Rp 0</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-stone-400 uppercase tracking-wider mb-2">Metode Pembayaran</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="setMetode('tunai', this)" class="metode-btn aktif flex flex-col items-center justify-center p-2 rounded-xl border-2 border-[#2E7D32] bg-[#E8F5E9] text-[#2E7D32] font-bold text-[11px] transition">
                                <i data-lucide="banknote" class="w-4 h-4 mb-1"></i> Tunai
                            </button>
                            <button type="button" onclick="setMetode('qris', this)" class="metode-btn flex flex-col items-center justify-center p-2 rounded-xl border border-stone-200 bg-white hover:bg-stone-100 text-stone-600 font-semibold text-[11px] transition">
                                <i data-lucide="qr-code" class="w-4 h-4 mb-1"></i> QRIS
                            </button>
                            <button type="button" onclick="setMetode('debit', this)" class="metode-btn flex flex-col items-center justify-center p-2 rounded-xl border border-stone-200 bg-white hover:bg-stone-100 text-stone-600 font-semibold text-[11px] transition">
                                <i data-lucide="credit-card" class="w-4 h-4 mb-1"></i> Debit
                            </button>
                        </div>
                    </div>

                    <div id="wrapBayar" class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-stone-400 uppercase tracking-wider">Jumlah Bayar</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-stone-400">Rp</span>
                            <input type="number" id="inputBayar" min="0" step="1000" placeholder="0" oninput="hitungKembalian()" class="w-full pl-9 pr-3 py-2 text-base font-bold bg-white border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                        </div>
                        <div class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-stone-200">
                            <span class="text-xs font-semibold text-stone-500">Kembalian</span>
                            <span id="kembalianText" class="text-sm font-bold text-emerald-700">Rp 0</span>
                        </div>
                        <div class="flex gap-1 flex-wrap">
                            <button type="button" onclick="setBayar(50000)" class="px-2.5 py-1 text-[10px] font-semibold bg-white border border-stone-200 rounded-md hover:bg-stone-100">50rb</button>
                            <button type="button" onclick="setBayar(100000)" class="px-2.5 py-1 text-[10px] font-semibold bg-white border border-stone-200 rounded-md hover:bg-stone-100">100rb</button>
                            <button type="button" onclick="setBayar('pas')" class="px-2.5 py-1 text-[10px] font-semibold bg-white border border-stone-200 rounded-md hover:bg-stone-100">Uang Pas</button>
                        </div>
                    </div>

                    <button onclick="prosesCheckout()" id="btnProses" class="w-full py-3.5 bg-[#2E7D32] hover:bg-emerald-800 active:scale-[0.99] text-white rounded-xl font-bold text-sm shadow-md shadow-emerald-900/20 flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                        <span id="btnProsesText">Proses Bayar</span>
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- TOMBOL KERANJANG (hanya layar kecil) -->
    <button id="btnKeranjangMobile" onclick="toggleKeranjangMobile()" class="lg:hidden fixed bottom-4 right-4 z-30 bg-[#2E7D32] text-white px-4 py-3 rounded-full shadow-lg flex items-center gap-2 text-sm font-bold">
        <i data-lucide="shopping-cart" class="w-5 h-5"></i> Keranjang <span id="badgeMobile" class="bg-white text-[#2E7D32] text-[10px] font-bold px-2 py-0.5 rounded-full">0</span>
    </button>
    <button id="btnTutupKeranjang" onclick="toggleKeranjangMobile()" class="hidden lg:hidden fixed top-3 right-3 z-50 bg-stone-100 text-stone-700 px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow">
        <i data-lucide="x" class="w-4 h-4"></i> Tutup
    </button>

    <!-- MODAL STRUK -->
    <div id="modalStruk" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full max-h-[90vh] flex flex-col">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-stone-800">Transaksi Berhasil</h3>
                <button onclick="closeStruk()" class="text-stone-400 hover:text-stone-700 p-1 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto custom-scrollbar">
                <div id="areaCetak">
                    <!-- Struk akan diisi oleh JS -->
                </div>
            </div>

            <div class="p-4 border-t border-stone-100 flex gap-2">
                <button onclick="cetakStruk()" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold py-2.5 rounded-xl text-xs inline-flex items-center justify-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Cetak
                </button>
                <button onclick="closeStruk()" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-2.5 rounded-xl text-xs">
                    Transaksi Baru
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const TOKO = <?= json_encode(['nama' => $nama_toko, 'cabang' => $nama_cabang, 'alamat' => $alamat_toko, 'telepon' => $no_telepon, 'catatan' => $catatan_nota ?: 'Terima kasih telah berbelanja!'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

        let cart = [];
        let currentKategori = 0;
        let currentMetode = 'tunai';

        function formatRp(v) {
            return 'Rp ' + Number(v).toLocaleString('id-ID');
        }

        function renderProduk() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.produk-card').forEach(card => {
                const nama = card.dataset.nama.toLowerCase();
                const kategori = parseInt(card.dataset.kategori);
                const matchSearch = nama.includes(search);
                const matchKategori = currentKategori === 0 || kategori === currentKategori;
                card.style.display = (matchSearch && matchKategori) ? '' : 'none';
            });
        }

        function setKategori(id, btn) {
            currentKategori = id;
            document.querySelectorAll('.cat-btn').forEach(b => {
                b.className = 'cat-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition bg-stone-100 text-stone-600 hover:bg-stone-200';
            });
            btn.className = 'cat-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition bg-[#2E7D32] text-white';
            renderProduk();
        }

        function tambahKeKeranjang(el) {
            const id    = parseInt(el.dataset.id);
            const nama  = el.dataset.nama;
            const harga = parseFloat(el.dataset.harga);
            const stok  = parseInt(el.dataset.stok);
            const kategori = el.dataset.namaKategori; 
            const gambar = el.dataset.gambar;

            const existing = cart.find(i => i.id === id);
            if (existing) {
                if (existing.qty >= stok) {
                    alert('Stok tidak mencukupi. Maksimal: ' + stok);
                    return;
                }
                existing.qty++;
            } else {
                cart.push({ 
                    id: id, 
                    nama: nama, 
                    harga: harga, 
                    qty: 1, 
                    stok: stok, 
                    kategori: kategori, 
                    gambar: gambar 
                });
            }
            renderCart();
        }

        function ubahQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (!item) return;
            const newQty = item.qty + delta;
            if (newQty <= 0) {
                hapusItem(id);
                return;
            }
            if (newQty > item.stok) {
                alert('Stok maksimal: ' + item.stok);
                return;
            }
            item.qty = newQty;
            renderCart();
        }

        function hapusItem(id) {
            const item = cart.find(i => i.id === id);
            if (!item) return;
            if (!confirm(`Hapus "${item.nama}" dari keranjang?`)) return;
            cart = cart.filter(i => i.id !== id);
            renderCart();
        }

        function kosongkanKeranjang() {
            if (cart.length === 0) return;
            if (!confirm('Kosongkan seluruh keranjang?')) return;
            cart = [];
            renderCart();
        }

        function resetSesi() {
            if (confirm('Reset sesi kasir dan kosongkan keranjang?')) {
                cart = [];
                renderCart();
                document.getElementById('inputBayar').value = '';
                document.getElementById('kembalianText').innerText = 'Rp 0';
                document.getElementById('inNamaPelanggan').value = 'Pelanggan Walk-in';
            }
        }

        function renderCart() {
            const container = document.getElementById('cartList');
            
            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="shopping-cart" class="w-6 h-6 text-stone-400"></i>
                        </div>
                        <p class="text-xs text-stone-500 font-medium">Keranjang kosong</p>
                        <p class="text-[10px] text-stone-400 mt-0.5">Klik produk untuk menambah</p>
                    </div>`;
                lucide.createIcons();
            } else {
                let htmlContent = '';
                cart.forEach(item => {
                    htmlContent += `
                        <div class="py-3 flex items-center gap-3">
                            <img src="${esc(item.gambar)}" class="w-12 h-12 rounded-lg object-cover bg-stone-100 shrink-0 border border-stone-200">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-stone-800 leading-tight">${esc(item.nama)}</p>
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-stone-100 text-stone-500">${esc(item.kategori || 'Tanaman')}</span>
                                <p class="text-[12px] text-[#2E7D32] font-bold mt-1">${formatRp(item.harga)}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0 ml-2">
                                <div class="flex items-center border border-stone-200 rounded-lg overflow-hidden bg-stone-50">
                                    <button onclick="ubahQty(${item.id}, -1)" class="px-2.5 py-1 text-stone-600 hover:bg-stone-200 font-bold text-sm">−</button>
                                    <span class="w-8 text-center text-sm font-bold text-stone-800">${item.qty}</span>
                                    <button onclick="ubahQty(${item.id}, 1)" class="px-2.5 py-1 text-stone-600 hover:bg-stone-200 font-bold text-sm">+</button>
                                </div>
                                <p class="text-xs font-bold text-stone-800 mt-1">${formatRp(item.harga * item.qty)}</p>
                                <button onclick="hapusItem(${item.id})" class="text-[10px] text-rose-500 hover:text-rose-700 font-bold mt-1 bg-rose-50 px-2 py-1 rounded">Hapus</button>
                            </div>
                        </div>`;
                });
                container.innerHTML = htmlContent;
                lucide.createIcons();
            }

            let subtotal = 0, totalQty = 0;
            cart.forEach(i => { subtotal += i.harga * i.qty; totalQty += i.qty; });
            const ppn = Math.round(subtotal * 0.11);
            const total = subtotal + ppn;

            document.getElementById('cartCountBadge').innerText = totalQty + ' Item';
            const bm = document.getElementById('badgeMobile'); if (bm) bm.innerText = totalQty;
            document.getElementById('subtotalText').innerText = formatRp(subtotal);
            document.getElementById('ppnText').innerText = formatRp(ppn);
            document.getElementById('totalText').innerText = formatRp(total);
            document.getElementById('btnProsesText').innerText = 'Proses Bayar ' + formatRp(total);

            hitungKembalian();
        }

        function setMetode(m, btn) {
            currentMetode = m;
            document.querySelectorAll('.metode-btn').forEach(b => {
                b.className = 'metode-btn flex flex-col items-center justify-center p-2 rounded-xl border border-stone-200 bg-white hover:bg-stone-100 text-stone-600 font-semibold text-[11px] transition';
            });
            btn.className = 'metode-btn aktif flex flex-col items-center justify-center p-2 rounded-xl border-2 border-[#2E7D32] bg-[#E8F5E9] text-[#2E7D32] font-bold text-[11px] transition';

            document.getElementById('wrapBayar').style.display = (m === 'tunai') ? '' : 'none';
        }

        function hitungKembalian() {
            const subtotal = cart.reduce((s, i) => s + i.harga * i.qty, 0);
            const total = subtotal + Math.round(subtotal * 0.11);
            const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
            const kembali = Math.max(0, bayar - total);
            document.getElementById('kembalianText').innerText = formatRp(kembali);
        }

        function setBayar(val) {
            const subtotal = cart.reduce((s, i) => s + i.harga * i.qty, 0);
            const total = subtotal + Math.round(subtotal * 0.11);
            if (val === 'pas') {
                document.getElementById('inputBayar').value = total;
            } else {
                document.getElementById('inputBayar').value = val;
            }
            hitungKembalian();
        }

        function prosesCheckout() {
            if (cart.length === 0) { alert('Keranjang masih kosong.'); return; }

            const subtotal = cart.reduce((s, i) => s + i.harga * i.qty, 0);
            const total = subtotal + Math.round(subtotal * 0.11);
            const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
            const namaPelanggan = document.getElementById('inNamaPelanggan').value.trim() || 'Pelanggan Walk-in';

            if (currentMetode === 'tunai' && bayar < total) {
                alert('Jumlah bayar kurang dari total.');
                return;
            }

            const btn = document.getElementById('btnProses');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Memproses...';
            lucide.createIcons();

            const formData = new FormData();
            formData.append('action', 'checkout_pos');
            formData.append('cart', JSON.stringify(cart.map(i => ({ id: i.id, qty: i.qty }))));
            formData.append('metode', currentMetode);
            formData.append('nama_pelanggan', namaPelanggan);
            formData.append('jumlah_bayar', bayar);

            fetch('pos.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        tampilkanStruk(data);
                        perbaruiStokKartu(data.items);
                        cart = [];
                        document.getElementById('inputBayar').value = '';
                        renderCart();
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="check-circle-2" class="w-5 h-5"></i> <span id="btnProsesText">Proses Bayar</span>';
                    lucide.createIcons();
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="check-circle-2" class="w-5 h-5"></i> <span>Proses Bayar</span>';
                    lucide.createIcons();
                });
        }

        function tampilkanStruk(data) {
            const t = data.toko || TOKO;
            let itemsHtml = '';
            data.items.forEach(i => {
                itemsHtml += `
                    <div style="margin-bottom:6px;">
                        <div style="font-weight:bold;">${esc(i.nama)}</div>
                        <div style="font-size:9px; color:#444; margin-bottom:2px;">${esc(i.kategori || '')}</div>
                        <div style="display:flex;justify-content:space-between;font-size:10px;">
                            <span>${i.qty} x ${formatRp(i.harga)}</span>
                            <span>${formatRp(i.harga * i.qty)}</span>
                        </div>
                    </div>`;
            });

            const metodeLabel = {
                'tunai': 'Tunai', 'qris': 'QRIS', 'debit': 'Debit',
                'transfer': 'Transfer', 'ewallet': 'E-Wallet', 'cod': 'COD'
            }[data.metode] || data.metode;

            document.getElementById('areaCetak').innerHTML = `
                <div style="font-family:'Courier New',monospace;font-size:11px;color:#000;line-height:1.4;">
                    <div style="text-align:center;border-bottom:1px dashed #000;padding-bottom:8px;margin-bottom:8px;">
                        <div style="font-weight:bold;font-size:14px;">${esc((t.nama || '').toUpperCase())}</div>
                        <div style="font-size:10px;">${esc(t.cabang || '')}</div>
                        ${t.alamat ? `<div style="font-size:9px;">${esc(t.alamat)}</div>` : ''}
                        ${t.telepon ? `<div style="font-size:9px;">Telp: ${esc(t.telepon)}</div>` : ''}
                    </div>
                    <div style="font-size:10px;margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;"><span>Kode</span><span>${data.kode}</span></div>
                        <div style="display:flex;justify-content:space-between;"><span>Tanggal</span><span>${data.waktu}</span></div>
                        <div style="display:flex;justify-content:space-between;"><span>Kasir</span><span>${esc(data.kasir)}</span></div>
                        <div style="display:flex;justify-content:space-between;"><span>Pelanggan</span><span>${esc(data.nama)}</span></div>
                    </div>
                    <div style="border-top:1px dashed #000;border-bottom:1px dashed #000;padding:6px 0;margin-bottom:6px;">
                        ${itemsHtml}
                    </div>
                    <div style="font-size:10px;">
                        <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>${formatRp(data.subtotal)}</span></div>
                        <div style="display:flex;justify-content:space-between;"><span>PPN 11%</span><span>${formatRp(data.ppn)}</span></div>
                        <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:12px;border-top:1px solid #000;padding-top:4px;margin-top:4px;"><span>TOTAL</span><span>${formatRp(data.total)}</span></div>
                    </div>
                    <div style="font-size:10px;border-top:1px dashed #000;margin-top:6px;padding-top:6px;">
                        <div style="display:flex;justify-content:space-between;"><span>Metode</span><span>${metodeLabel}</span></div>
                        <div style="display:flex;justify-content:space-between;"><span>Bayar</span><span>${formatRp(data.bayar)}</span></div>
                        <div style="display:flex;justify-content:space-between;font-weight:bold;"><span>Kembali</span><span>${formatRp(data.kembali)}</span></div>
                    </div>
                    <div style="text-align:center;font-size:10px;margin-top:12px;padding-top:8px;border-top:1px dashed #000;">
                        <div style="white-space:pre-line;">${esc(t.catatan || 'Terima kasih telah berbelanja!')}</div>
                        <div style="margin-top:2px;">~ ${esc(t.nama)} ~</div>
                    </div>
                </div>
            `;

            const modal = document.getElementById('modalStruk');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // Sinkronkan stok di katalog setelah transaksi (tanpa reload halaman)
        function perbaruiStokKartu(items) {
            (items || []).forEach(i => {
                const card = document.querySelector('.produk-card[data-id="' + i.id + '"]');
                if (!card) return;
                if (i.stok_baru <= 0) { card.remove(); return; }
                card.dataset.stok = i.stok_baru;
                const badge = card.querySelector('span.absolute');
                if (badge) badge.textContent = 'Stok: ' + i.stok_baru;
            });
        }

        // Keranjang di layar kecil (HP/tablet): tampil sebagai panel penuh
        function toggleKeranjangMobile() {
            const sec = document.getElementById('panelKeranjang');
            const open = sec.classList.contains('fixed');
            ['hidden', 'fixed', 'inset-0', 'z-40', '!w-full'].forEach(c => sec.classList.toggle(c));
            document.getElementById('btnKeranjangMobile').classList.toggle('hidden', !open ? true : false);
            document.getElementById('btnTutupKeranjang').classList.toggle('hidden', open);
        }

        function cetakStruk() {
            window.print();
        }

        function closeStruk() {
            const modal = document.getElementById('modalStruk');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        renderCart();
    </script>
</body>
</html>