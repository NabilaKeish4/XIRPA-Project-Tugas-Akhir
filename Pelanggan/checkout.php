<?php
session_start();
require_once '../Config/database.php';

// Pastikan keranjang tidak kosong
if (empty($_SESSION['cart'])) {
    header('Location: katalog.php');
    exit;
}

// Ambil ID User dari session (fallback ke 1 jika belum set)
$user_id = $_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 1;

// Persiapan data produk yang ada di keranjang
$cart_items = $_SESSION['cart'] ?? [];
$products_in_cart = [];
$total_bayar = 0;

if (!empty($cart_items)) {
    $ids = implode(',', array_map('intval', array_keys($cart_items)));
    $query = "SELECT * FROM produk WHERE id IN ($ids)";
    $result = mysqli_query($conn, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $qty = $cart_items[$row['id']];
            $harga = $row['harga_jual'] ?? $row['harga'] ?? 0;
            $subtotal = $harga * $qty;
            $total_bayar += $subtotal;

            $products_in_cart[] = [
                'id' => $row['id'],
                'name' => $row['nama_tanaman'],
                'price' => $harga,
                'qty' => $qty,
                'subtotal' => $subtotal
            ];
        }
    }
}

// Proses saat tombol "Selesaikan Pesanan" diklik
if (isset($_POST['proses_checkout'])) {
    $nama_penerima = mysqli_real_escape_string($conn, $_POST['nama']);
    $alamat        = mysqli_real_escape_string($conn, $_POST['alamat']);
    $telepon       = mysqli_real_escape_string($conn, $_POST['telepon']);
    $metode_bayar  = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);

    // Kode transaksi unik otomatis (Contoh: TRX-20260915-8493)
    $kode_transaksi = 'TRX-' . date('Ymd') . '-' . rand(1000, 9999);

    // 1. Simpan data utama transaksi ke tabel `transaksi`
    $query_tx = "INSERT INTO transaksi 
                 (kode_transaksi, tanggal_transaksi, user_id, nama_penerima, alamat, telepon, total_harga, total, metode_pembayaran, jenis_transaksi, status, created_at) 
                 VALUES 
                 ('$kode_transaksi', NOW(), '$user_id', '$nama_penerima', '$alamat', '$telepon', '$total_bayar', '$total_bayar', '$metode_bayar', 'Penjualan', 'Diproses', NOW())";
    
    if (mysqli_query($conn, $query_tx)) {
        $transaksi_id = mysqli_insert_id($conn);

        // 2. Simpan setiap detail produk ke tabel `transaksi_detail`
        foreach ($products_in_cart as $item) {
            $id_produk = (int)$item['id'];
            $qty       = (int)$item['qty'];
            $harga     = (float)$item['price'];

            mysqli_query($conn, "INSERT INTO transaksi_detail (transaksi_id, id_produk, jumlah, harga) 
                                 VALUES ('$transaksi_id', '$id_produk', '$qty', '$harga')");
        }

        // 3. Kosongkan keranjang belanja
        unset($_SESSION['cart']);

        // 4. Arahkan pengguna ke halaman riwayat order
        header("Location: riwayat.php?success=1");
        exit;
    } else {
        $error = "Gagal memproses transaksi: " . mysqli_error($conn);
    }

$cart_count = array_sum($cart_items);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Checkout</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        planthub: {
                            bg: '#F4F6F3',
                            card: '#FFFFFF',
                            green: '#3B5E2B',
                            'green-hover': '#2e4a22',
                            'green-light': '#EBF2E8',
                            dark: '#1E291E',
                            muted: '#6B7280',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F4F6F3; color: #1E291E; font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="antialiased min-h-screen flex p-4 lg:p-6 gap-6 text-stone-800">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white rounded-2xl p-5 flex flex-col justify-between h-[calc(100vh-3rem)] sticky top-6 shrink-0 shadow-sm border border-stone-100/80 z-50">
        <div>
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-8 px-1">
                <div class="w-9 h-9 rounded-xl bg-planthub-green flex items-center justify-center text-white shadow-sm shadow-emerald-950/20">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight text-planthub-dark leading-none">Plant<span class="text-planthub-green">Hub</span></h1>
                    <p class="text-[9px] font-extrabold tracking-widest text-stone-400 uppercase mt-0.5">STORE PORTAL</p>
                </div>
            </a>

            <nav class="flex flex-col space-y-1 text-xs font-medium text-stone-600">
                <a href="dashboard.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i> Beranda
                </a>
                <a href="katalog.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="store" class="w-4 h-4"></i> Katalog Shop
                </a>
                <a href="cart.php" class="bg-planthub-green text-white font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag" class="w-4 h-4"></i> Keranjang</span>
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </a>
                <a href="riwayat.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="history" class="w-4 h-4"></i> Riwayat Order
                </a>
                <a href="chat.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="message-square" class="w-4 h-4"></i> Konsultasi
                </a>
            </nav>
        </div>

        <div class="space-y-3 pt-3 border-t border-stone-100">
            <a href="../Auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center justify-center gap-1.5 text-xs font-bold text-red-500 hover:text-red-600 py-1 transition">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar Sesi
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-100/80 flex flex-col h-[calc(100vh-3rem)] overflow-y-auto">
        <header class="flex items-center justify-between pb-5 border-b border-stone-100 mb-6">
            <div>
                <h2 class="text-xl font-bold text-stone-900">Formulir Checkout 📑</h2>
                <p class="text-xs text-stone-400 mt-0.5">Lengkapi data diri dan alamat pengiriman Anda.</p>
            </div>
            <a href="cart.php" class="text-xs font-bold text-planthub-green hover:underline flex items-center gap-1">
                &larr; Kembali ke Keranjang
            </a>
        </header>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-600 rounded-xl text-xs border border-red-100">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- FORM PENGIRIMAN -->
            <form method="POST" class="lg:col-span-2 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-stone-700 mb-1">Nama Lengkap Penerima</label>
                    <input type="text" name="nama" required class="w-full border border-stone-200 p-3 rounded-xl text-xs focus:outline-none focus:border-planthub-green">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 mb-1">Nomor Telepon / WhatsApp</label>
                    <input type="text" name="telepon" required class="w-full border border-stone-200 p-3 rounded-xl text-xs focus:outline-none focus:border-planthub-green">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 mb-1">Alamat Pengiriman Lengkap</label>
                    <textarea name="alamat" required rows="3" class="w-full border border-stone-200 p-3 rounded-xl text-xs focus:outline-none focus:border-planthub-green"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-700 mb-1">Metode Pembayaran</label>
                    <select name="metode_pembayaran" class="w-full border border-stone-200 p-3 rounded-xl text-xs focus:outline-none focus:border-planthub-green">
                        <option value="Transfer Bank">Transfer Bank (BCA / Mandiri)</option>
                        <option value="E-Wallet">E-Wallet (GoPay / ShopeePay / Dana)</option>
                        <option value="COD">Bayar di Tempat (COD)</option>
                    </select>
                </div>

                <button type="submit" name="proses_checkout" class="w-full bg-planthub-green hover:bg-planthub-green-hover text-white font-bold py-3.5 rounded-xl text-xs uppercase tracking-wider transition shadow-sm mt-4">
                    Selesaikan Pesanan
                </button>
            </form>

            <!-- RINGKASAN PESANAN -->
            <div class="bg-stone-50/60 p-5 rounded-2xl border border-stone-100 space-y-4 h-fit">
                <h3 class="text-xs font-bold uppercase text-stone-400 pb-2 border-b border-stone-200">Ringkasan Pesanan</h3>
                <div class="divide-y divide-stone-200/60 max-h-60 overflow-y-auto">
                    <?php foreach ($products_in_cart as $item): ?>
                        <div class="py-2 flex justify-between items-center text-xs">
                            <div>
                                <p class="font-bold text-stone-800"><?= htmlspecialchars($item['name']) ?></p>
                                <p class="text-stone-400 text-[10px]"><?= $item['qty'] ?> x Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                            </div>
                            <span class="font-bold text-stone-700">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pt-3 border-t border-stone-200/60 flex justify-between items-center text-xs">
                    <span class="font-bold text-stone-800">Total Pembayaran</span>
                    <span class="text-base font-extrabold text-planthub-green">Rp <?= number_format($total_bayar, 0, ',', '.') ?></span>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>