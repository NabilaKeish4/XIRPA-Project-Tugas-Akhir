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
// PROSES CHECKOUT POS (AJAX POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout_pos') {
    header('Content-Type: application/json');
    
    $cartJson  = $_POST['cart'] ?? '[]';
    $metode    = $_POST['metode'] ?? 'tunai';
    $nama      = trim($_POST['nama_pelanggan'] ?? 'Pelanggan Walk-in');
    $bayar     = (float)($_POST['jumlah_bayar'] ?? 0);
    $catatan   = trim($_POST['catatan'] ?? '');

    $cart = json_decode($cartJson, true);

    if (!is_array($cart) || empty($cart)) {
        echo json_encode(['success' => false, 'message' => 'Keranjang kosong.']);
        exit;
    }

    $metodeValid = ['tunai', 'qris', 'debit', 'transfer', 'ewallet', 'cod'];
    if (!in_array($metode, $metodeValid)) $metode = 'tunai';

    // Hitung total & validasi stok
    $total    = 0;
    $subtotal = 0;
    foreach ($cart as $item) {
        $qty   = (int)$item['qty'];
        $harga = (float)$item['harga'];
        $subtotal += $qty * $harga;
    }
    $ppn   = $subtotal * 0.11;
    $total = $subtotal + $ppn;

    // Validasi bayar
    if ($metode === 'tunai' && $bayar < $total) {
        echo json_encode(['success' => false, 'message' => 'Jumlah bayar kurang dari total.']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $kode = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $namaEsc    = mysqli_real_escape_string($conn, $nama);
        $catatanEsc = mysqli_real_escape_string($conn, $catatan);

        // 1. Simpan transaksi
        $sqlTx = "INSERT INTO transaksi 
                  (kode_transaksi, user_id, jenis_transaksi, nama_penerima, total_harga, metode_pembayaran, status, catatan, created_at) 
                  VALUES 
                  ('$kode', NULL, 'penjualan', '$namaEsc', $total, '$metode', 'Selesai', '$catatanEsc', NOW())";
        if (!mysqli_query($conn, $sqlTx)) throw new Exception(mysqli_error($conn));

        $transaksi_id = mysqli_insert_id($conn);

        // 2. Simpan detail + update stok
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
            'success' => true,
            'message' => 'Transaksi berhasil',
            'kode'    => $kode,
            'total'   => $total,
            'bayar'   => $bayar,
            'kembali' => $metode === 'tunai' ? max(0, $bayar - $total) : 0,
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
// AMBIL DAFTAR PRODUK UNTUK KATALOG POS
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
    <title>PlantHub - Kasir POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 9999px; }
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
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    </div>
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
                             data-nama-kategori="<?= htmlspecialchars($p['nama_kategori'] ?? '') ?>"
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

        <!-- KANAN: KERANJANG -->
        <section class="hidden lg:flex w-[40%] flex-col bg-white border-l border-stone-200 shadow-xl z-10 overflow-hidden">

            <!-- Info Pelanggan -->
            <div class="p-4 border-b border-stone-200/80 bg-stone-50/60 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-stone-200/70 text-stone-600 rounded-xl">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-stone-400 font-bold uppercase">Pelanggan</p>
                        <p class="text-sm font-semibold text-stone-800">Walk-in Customer</p>
                    </div>
                </div>
                <span class="text-[10px] font-bold text-[#2E7D32] bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-md">POS</span>
            </div>

            <!-- Cart Header -->
            <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-xs font-bold text-stone-800 uppercase tracking-wider">Keranjang</h2>
                    <span id="cartCountBadge" class="bg-[#E8F5E9] text-[#2E7D32] text-[10px] font-bold px-2 py-0.5 rounded-full">0 Item</span>
                </div>
                <button onclick="kosongkanKeranjang()" class="text-[10px] text-stone-400 hover:text-rose-600 flex items-center gap-1 font-semibold">
                    <i data-lucide="trash-2" class="w-3 h-3"></i> Kosongkan
                </button>
            </div>

            <!-- Cart Items -->
            <div id="cartList" class="flex-1 overflow-y-auto px-4 py-2 divide-y divide-stone-100 custom-scrollbar">
                <div class="text-center py-12">
                    <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="shopping-cart" class="w-6 h-6 text-stone-400"></i>
                    </div>
                    <p class="text-xs text-stone-500 font-medium">Keranjang kosong</p>
                    <p class="text-[10px] text-stone-400 mt-0.5">Klik produk untuk menambah</p>
                </div>
            </div>

            <!-- Summary & Checkout -->
            <div class="p-5 bg-stone-50/80 border-t border-stone-200 space-y-3 shrink-0">

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
        </section>
    </div>

    <!-- MODAL STRUK (setelah checkout) -->
    <div id="modalStruk" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full max-h-[90vh] flex flex-col">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <h3 class="text-base font-bold text-stone-800">Transaksi Berhasil</h3>
                <button onclick="closeStruk()" class="text-stone-400 hover:text-stone-700 p-1 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div id="strukContent" class="p-6 overflow-y-auto custom-scrollbar text-sm font-mono"></div>

            <div class="p-4 border-t border-stone-100 flex gap-2">
                <button onclick="window.print()" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold py-2.5 rounded-xl text-xs inline-flex items-center justify-center gap-2">
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
            const gambar = el.dataset.gambar;

            const existing = cart.find(i => i.id === id);
            if (existing) {
                if (existing.qty >= stok) {
                    alert('Stok tidak mencukupi. Maksimal: ' + stok);
                    return;
                }
                existing.qty++;
            } else {
                cart.push({ id, nama, harga, qty: 1, stok, gambar });
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
            cart = cart.filter(i => i.id !== id);
            renderCart();
        }

        function kosongkanKeranjang() {
            if (cart.length === 0) return;
            if (!confirm('Kosongkan keranjang?')) return;
            cart = [];
            renderCart();
        }

        function resetSesi() {
            if (confirm('Reset sesi kasir dan kosongkan keranjang?')) {
                cart = [];
                renderCart();
                document.getElementById('inputBayar').value = '';
                document.getElementById('kembalianText').innerText = 'Rp 0';
            }
        }

        function renderCart() {
            const container = document.getElementById('cartList');
            container.innerHTML = '';

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
                cart.forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'py-3 flex items-center gap-3';
                    row.innerHTML = `
                        <img src="${item.gambar}" class="w-12 h-12 rounded-lg object-cover bg-stone-100 shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-stone-800 truncate">${item.nama}</p>
                            <p class="text-[11px] text-stone-500 mt-0.5">${formatRp(item.harga)}</p>
                        </div>
                        <div class="flex items-center border border-stone-200 rounded-lg overflow-hidden bg-stone-50">
                            <button onclick="ubahQty(${item.id}, -1)" class="px-2 py-1 text-stone-600 hover:bg-stone-200 font-bold text-xs">−</button>
                            <span class="w-8 text-center text-xs font-bold text-stone-800">${item.qty}</span>
                            <button onclick="ubahQty(${item.id}, 1)" class="px-2 py-1 text-stone-600 hover:bg-stone-200 font-bold text-xs">+</button>
                        </div>
                        <div class="text-right shrink-0 min-w-[70px]">
                            <p class="text-xs font-bold text-stone-800">${formatRp(item.harga * item.qty)}</p>
                            <button onclick="hapusItem(${item.id})" class="text-[10px] text-stone-400 hover:text-rose-600 mt-0.5">Hapus</button>
                        </div>`;
                    container.appendChild(row);
                });
                lucide.createIcons();
            }

            // Hitung
            let subtotal = 0, totalQty = 0;
            cart.forEach(i => { subtotal += i.harga * i.qty; totalQty += i.qty; });
            const ppn = subtotal * 0.11;
            const total = subtotal + ppn;

            document.getElementById('cartCountBadge').innerText = totalQty + ' Item';
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
            const total = subtotal * 1.11;
            const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
            const kembali = Math.max(0, bayar - total);
            document.getElementById('kembalianText').innerText = formatRp(kembali);
        }

        function setBayar(val) {
            const subtotal = cart.reduce((s, i) => s + i.harga * i.qty, 0);
            const total = Math.ceil(subtotal * 1.11);
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
            const total = subtotal * 1.11;
            const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;

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
            formData.append('cart', JSON.stringify(cart));
            formData.append('metode', currentMetode);
            formData.append('nama_pelanggan', 'Pelanggan Walk-in');
            formData.append('jumlah_bayar', bayar);

            fetch('pos.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        tampilkanStruk(data);
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
            const subtotal = cart.reduce((s, i) => s + i.harga * i.qty, 0);
            let itemsHtml = '';
            cart.forEach(i => {
                itemsHtml += `<div class="flex justify-between text-[11px] py-0.5">
                    <span>${i.nama} x${i.qty}</span>
                    <span>${formatRp(i.harga * i.qty)}</span>
                </div>`;
            });

            const now = new Date();
            const tgl = now.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

            document.getElementById('strukContent').innerHTML = `
                <div class="text-center border-b border-dashed border-stone-300 pb-3 mb-3">
                    <p class="font-bold text-base text-stone-800">PlantHub</p>
                    <p class="text-[10px] text-stone-500">Sistem Manajemen Toko Tanaman</p>
                </div>
                <div class="text-[10px] text-stone-600 mb-3 space-y-0.5">
                    <div class="flex justify-between"><span>Kode</span><span class="font-bold">${data.kode}</span></div>
                    <div class="flex justify-between"><span>Tanggal</span><span>${tgl}</span></div>
                    <div class="flex justify-between"><span>Kasir</span><span><?= htmlspecialchars($admin_nama) ?></span></div>
                </div>
                <div class="border-t border-b border-dashed border-stone-300 py-2 mb-3">
                    ${itemsHtml}
                </div>
                <div class="text-[11px] space-y-1 mb-3">
                    <div class="flex justify-between"><span>Subtotal</span><span>${formatRp(subtotal)}</span></div>
                    <div class="flex justify-between"><span>PPN 11%</span><span>${formatRp(subtotal * 0.11)}</span></div>
                    <div class="flex justify-between font-bold text-base border-t border-stone-300 pt-2 mt-2"><span>TOTAL</span><span>${formatRp(data.total)}</span></div>
                </div>
                <div class="text-[11px] space-y-1 border-t border-dashed border-stone-300 pt-2">
                    <div class="flex justify-between"><span>Bayar</span><span>${formatRp(data.bayar)}</span></div>
                    <div class="flex justify-between font-bold"><span>Kembali</span><span>${formatRp(data.kembali)}</span></div>
                </div>
                <div class="text-center text-[10px] text-stone-500 mt-4 pt-3 border-t border-dashed border-stone-300">
                    <p>Terima kasih!</p>
                </div>
            `;

            const modal = document.getElementById('modalStruk');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
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