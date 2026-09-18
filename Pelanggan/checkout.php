<?php
session_start();
require_once '../Config/database.php';

// =========================================================
// PROTEKSI LOGIN
// =========================================================
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$nama_user = $_SESSION['nama_user'] ?? 'Pelanggan';

// =========================================================
// CEK KERANJANG
// =========================================================
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// =========================================================
// AMBIL DATA PRODUK DARI KERANJANG
// =========================================================
$cart_items = $_SESSION['cart'];
$items      = [];
$subtotal   = 0;
$cart_count = 0;

$ids = implode(',', array_map('intval', array_keys($cart_items)));
if ($ids !== '') {
    $qItems = mysqli_query($conn, "
        SELECT p.*, k.nama_kategori 
        FROM produk p 
        LEFT JOIN kategori k ON p.kategori_id = k.id 
        WHERE p.id IN ($ids)
    ");
    if ($qItems) {
        while ($row = mysqli_fetch_assoc($qItems)) {
            $pid = (int)$row['id'];
            if (!isset($cart_items[$pid])) continue;

            $qty   = (int)$cart_items[$pid];
            $harga = (float)$row['harga_jual'];
            $sub   = $harga * $qty;

            $subtotal   += $sub;
            $cart_count += $qty;

            $items[] = [
                'id'       => $pid,
                'nama'     => $row['nama_tanaman'],
                'kategori' => $row['nama_kategori'] ?? 'Tanaman',
                'harga'    => $harga,
                'qty'      => $qty,
                'subtotal' => $sub,
                'stok'     => (int)$row['stok'],
                'gambar'   => (!empty($row['gambar']) && $row['gambar'] !== 'default.jpg' && file_exists("../assets/img/" . $row['gambar']))
                                ? "../assets/img/" . $row['gambar']
                                : "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400",
            ];
        }
    }
}

if (empty($items)) {
    header("Location: cart.php");
    exit;
}

$ppn   = $subtotal * 0.11;
$total = $subtotal + $ppn;

// =========================================================
// AMBIL PROFIL USER (untuk autofill form)
// =========================================================
$profil = null;
$qUser = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1");
if ($qUser) $profil = mysqli_fetch_assoc($qUser);

// =========================================================
// PROSES CHECKOUT
// =========================================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_checkout'])) {
    $nama_penerima = mysqli_real_escape_string($conn, trim($_POST['nama'] ?? ''));
    $telepon       = mysqli_real_escape_string($conn, trim($_POST['telepon'] ?? ''));
    $alamat        = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));
    $metode        = mysqli_real_escape_string($conn, $_POST['metode_pembayaran'] ?? 'transfer');
    $catatan       = mysqli_real_escape_string($conn, trim($_POST['catatan'] ?? ''));

    // Validasi
    if ($nama_penerima === '' || $telepon === '' || $alamat === '') {
        $error = "Semua field wajib diisi.";
    } else {

        // Validasi metode pembayaran
        $metodeValid = ['tunai', 'qris', 'debit', 'transfer', 'ewallet', 'cod'];
        if (!in_array($metode, $metodeValid)) $metode = 'transfer';

        // ----- VALIDASI STOK ULANG (jaga-jaga) -----
        $stokError = false;
        foreach ($items as $it) {
            if ($it['qty'] > $it['stok']) {
                $error = "Stok produk \"{$it['nama']}\" tidak mencukupi (sisa {$it['stok']}).";
                $stokError = true;
                break;
            }
        }

        if (!$stokError) {
            // ----- GENERATE KODE TRANSAKSI -----
            $kode = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            // Mulai transaksi DB (biar atomic)
            mysqli_begin_transaction($conn);

            try {
                // 1. INSERT transaksi
                $sqlTx = "
                    INSERT INTO transaksi 
                    (kode_transaksi, user_id, supplier_id, jenis_transaksi, nama_penerima, alamat, telepon, total_harga, metode_pembayaran, status, catatan, created_at)
                    VALUES 
                    ('$kode', $user_id, NULL, 'penjualan', '$nama_penerima', '$alamat', '$telepon', $total, '$metode', 'Diproses', '$catatan', NOW())
                ";
                if (!mysqli_query($conn, $sqlTx)) {
                    throw new Exception("Gagal simpan transaksi: " . mysqli_error($conn));
                }

                $transaksi_id = mysqli_insert_id($conn);

                // 2. INSERT transaksi_detail + UPDATE stok
                foreach ($items as $it) {
                    $pid       = (int)$it['id'];
                    $qty       = (int)$it['qty'];
                    $harga     = (float)$it['harga'];
                    $sub       = (float)$it['subtotal'];

                    $sqlDet = "
                        INSERT INTO transaksi_detail 
                        (transaksi_id, produk_id, jumlah, harga_satuan, subtotal)
                        VALUES 
                        ($transaksi_id, $pid, $qty, $harga, $sub)
                    ";
                    if (!mysqli_query($conn, $sqlDet)) {
                        throw new Exception("Gagal simpan detail: " . mysqli_error($conn));
                    }

                    $sqlStok = "UPDATE produk SET stok = stok - $qty WHERE id = $pid AND stok >= $qty";
                    if (!mysqli_query($conn, $sqlStok)) {
                        throw new Exception("Gagal update stok: " . mysqli_error($conn));
                    }
                    if (mysqli_affected_rows($conn) === 0) {
                        throw new Exception("Stok produk ID $pid tidak cukup.");
                    }
                }

                // Commit
                mysqli_commit($conn);

                // Kosongkan keranjang
                $_SESSION['cart'] = [];

                // Redirect ke riwayat
                header("Location: riwayat.php?success=1&kode=" . urlencode($kode));
                exit;

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <!-- HEADER -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
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
                <span class="text-xs text-stone-500 self-center">Checkout — Selesaikan pesanan Anda</span>
            </div>

            <div class="flex items-center gap-3">
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
        <!-- SIDEBAR -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => false],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => true],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => false],
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
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Checkout Pesanan</h1>
                <p class="text-sm text-stone-500 mt-0.5">Lengkapi data pengiriman dan pilih metode pembayaran.</p>
            </div>

            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-500"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="checkout.php" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- FORM PENGIRIMAN -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-5">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Data Pengiriman</h2>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Penerima</label>
                        <input type="text" name="nama" required
                               value="<?= htmlspecialchars($profil['nama_lengkap'] ?? $nama_user) ?>"
                               class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">No. Telepon / WhatsApp</label>
                        <input type="text" name="telepon" required
                               value="<?= htmlspecialchars($profil['no_hp'] ?? '') ?>"
                               placeholder="081234567890"
                               class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat Pengiriman Lengkap</label>
                        <textarea name="alamat" required rows="3"
                                  placeholder="Jl. ... No. ..., Kelurahan, Kecamatan, Kota, Kode Pos"
                                  class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"><?= htmlspecialchars($profil['alamat'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Metode Pembayaran</label>
                        <select name="metode_pembayaran" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                            <option value="transfer">Transfer Bank</option>
                            <option value="ewallet">E-Wallet (GoPay / OVO / Dana)</option>
                            <option value="qris">QRIS</option>
                            <option value="cod">Bayar di Tempat (COD)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Catatan (Opsional)</label>
                        <textarea name="catatan" rows="2"
                                  placeholder="Contoh: Titip ke resepsionis, jangan dibanting, dll."
                                  class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-between border-t border-stone-100">
                        <a href="cart.php" class="text-xs font-semibold text-stone-500 hover:text-stone-800 inline-flex items-center gap-1">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Keranjang
                        </a>
                    </div>
                </div>

                <!-- RINGKASAN PESANAN -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 sticky top-24">
                        <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider border-b border-stone-100 pb-3">Ringkasan Pesanan</h2>

                        <div class="space-y-3 max-h-72 overflow-y-auto">
                            <?php foreach ($items as $it): ?>
                                <div class="flex gap-3 items-start">
                                    <img src="<?= htmlspecialchars($it['gambar']) ?>" class="w-12 h-12 rounded-lg object-cover bg-stone-100 shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-stone-800 truncate"><?= htmlspecialchars($it['nama']) ?></p>
                                        <p class="text-[11px] text-stone-500"><?= $it['qty'] ?> x Rp <?= number_format($it['harga'], 0, ',', '.') ?></p>
                                    </div>
                                    <p class="text-xs font-bold text-stone-800 shrink-0">Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="border-t border-stone-100 pt-3 space-y-2 text-sm">
                            <div class="flex justify-between text-stone-600">
                                <span>Subtotal (<?= $cart_count ?> item)</span>
                                <span class="font-semibold">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-stone-600">
                                <span>PPN 11%</span>
                                <span class="font-semibold">Rp <?= number_format($ppn, 0, ',', '.') ?></span>
                            </div>
                            <div class="pt-2 border-t border-stone-200 flex justify-between items-baseline">
                                <span class="text-sm font-bold text-stone-800">Total Bayar</span>
                                <span class="text-xl font-bold text-[#2E7D32]">Rp <?= number_format($total, 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <button type="submit" name="proses_checkout"
                                class="w-full bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 rounded-xl text-sm transition inline-flex items-center justify-center gap-2 shadow-sm">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            <span>Selesaikan Pesanan</span>
                        </button>

                        <p class="text-[10px] text-stone-400 text-center leading-relaxed">
                            Dengan menyelesaikan pesanan, Anda menyetujui syarat & ketentuan PlantHub.
                        </p>
                    </div>
                </div>

            </form>

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