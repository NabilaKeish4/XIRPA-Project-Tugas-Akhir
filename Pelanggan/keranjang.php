<?php
session_start();

// Database connection setup
$host = "localhost";
$user = "root";
$pass = "";
$db   = "plant_hub";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
// Inisialisasi session keranjang jika belum ada
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

$alert_msg = "";
$alert_type = "";

// 1. HANDLER UPDATE QUANTITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    $id_produk = intval($_POST['id_produk']);
    $action = $_POST['action_type']; // 'increase' atau 'decrease'

    if (isset($_SESSION['keranjang'][$id_produk])) {
        // Ambil stok terbaru dari DB untuk validasi
        $res_stok = mysqli_query($conn, "SELECT stok FROM produk WHERE id = $id_produk");
        $produk_db = mysqli_fetch_assoc($res_stok);
        $stok_tersedia = $produk_db ? $produk_db['stok'] : 0;

        if ($action === 'increase') {
            if ($_SESSION['keranjang'][$id_produk]['jumlah'] < $stok_tersedia) {
                $_SESSION['keranjang'][$id_produk]['jumlah'] += 1;
            } else {
                $alert_msg = "Jumlah pesanan sudah mencapai batas stok maksimum!";
                $alert_type = "warning";
            }
        } elseif ($action === 'decrease') {
            if ($_SESSION['keranjang'][$id_produk]['jumlah'] > 1) {
                $_SESSION['keranjang'][$id_produk]['jumlah'] -= 1;
            } else {
                // Jika kurang dari 1, hapus item
                unset($_SESSION['keranjang'][$id_produk]);
                $alert_msg = "Item berhasil dihapus dari keranjang.";
                $alert_type = "info";
            }
        }
    }
}

// 2. HANDLER HAPUS SINGLE ITEM
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
    $id_remove = intval($_GET['id']);
    if (isset($_SESSION['keranjang'][$id_remove])) {
        $nama_item = $_SESSION['keranjang'][$id_remove]['nama_produk'];
        unset($_SESSION['keranjang'][$id_remove]);
        $alert_msg = "<b>" . htmlspecialchars($nama_item) . "</b> telah dihapus dari keranjang.";
        $alert_type = "info";
    }
}

// 3. HANDLER KOSONGKAN KERANJANG
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $_SESSION['keranjang'] = [];
    $alert_msg = "Keranjang belanja berhasil dikosongkan.";
    $alert_type = "info";
}

// Hitung Ringkasan Belanja
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $subtotal += ($item['harga'] * $item['jumlah']);
    $total_items += $item['jumlah'];
}

// Perhitungan Biaya Tambahan
$pajak = $subtotal * 0.11; // PPN 11%
$biaya_penanganan = $subtotal > 0 ? 2000 : 0;
$grand_total = $subtotal + $pajak + $biaya_penanganan;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - PlantHub</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAFAFA;
        }
    </style>
</head>
<body class="text-stone-800 flex flex-col min-h-screen">

    <!-- NAVBAR PELANGGAN -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-stone-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo Brand -->
                <a href="index.php" class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-md shadow-emerald-900/20">
                        <i data-lucide="sprout" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-stone-900 leading-none block">Plant<span class="text-[#2E7D32]">Hub</span></span>
                        <span class="text-[10px] text-stone-400 font-medium tracking-widest uppercase">Green Store</span>
                    </div>
                </a>

                <!-- Step Indicator -->
                <div class="hidden md:flex items-center gap-3 text-xs font-semibold">
                    <span class="text-[#2E7D32] flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
                        <span class="w-5 h-5 rounded-full bg-[#2E7D32] text-white flex items-center justify-center text-[10px]">1</span> Keranjang
                    </span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-stone-300"></i>
                    <span class="text-stone-400 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-stone-200 text-stone-600 flex items-center justify-center text-[10px]">2</span> Checkout
                    </span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-stone-300"></i>
                    <span class="text-stone-400 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-stone-200 text-stone-600 flex items-center justify-center text-[10px]">3</span> Selesai
                    </span>
                </div>

                <!-- Kembali ke katalog -->
                <a href="index.php" class="inline-flex items-center gap-2 text-xs font-semibold text-stone-600 hover:text-[#2E7D32] transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Lanjut Belanja
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">

        <!-- Title -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200/80 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 tracking-tight">Keranjang Belanja</h1>
                <p class="text-xs text-stone-500 mt-1">Kelola dan periksa kembali tanaman pilihanmu sebelum melanjutkan pembayaran.</p>
            </div>
            <?php if (!empty($_SESSION['keranjang'])): ?>
                <a href="keranjang.php?action=clear" onclick="return confirm('Apakah Anda yakin ingin mekosongkan seluruh isi keranjang?')" class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-2 rounded-xl transition-colors">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Kosongkan Keranjang
                </a>
            <?php endif; ?>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($alert_msg)): ?>
            <div class="p-4 rounded-xl text-xs flex items-center justify-between shadow-xs <?= $alert_type === 'warning' ? 'bg-amber-50 border border-amber-200 text-amber-800' : 'bg-blue-50 border border-blue-200 text-blue-800' ?>">
                <div class="flex items-center gap-2">
                    <i data-lucide="<?= $alert_type === 'warning' ? 'alert-triangle' : 'info' ?>" class="w-4 h-4"></i>
                    <span><?= $alert_msg ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-stone-400 hover:text-stone-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['keranjang'])): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                
                <!-- LIST ITEM KERANJANG (LEFT - 2 COLS) -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="bg-white rounded-2xl border border-stone-200/80 shadow-xs overflow-hidden">
                        <div class="divide-y divide-stone-100">
                            <?php foreach ($_SESSION['keranjang'] as $id => $item): ?>
                                <?php $item_subtotal = $item['harga'] * $item['jumlah']; ?>
                                <div class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-stone-50/50 transition-colors">
                                    
                                    <!-- Image & Info -->
                                    <div class="flex items-center gap-4 flex-1">
                                        <div class="w-20 h-20 rounded-xl bg-stone-100 border border-stone-200/60 overflow-hidden shrink-0">
                                            <?php if (!empty($item['gambar'])): ?>
                                                <img src="../assets/uploads/<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-stone-400">
                                                    <i data-lucide="image" class="w-6 h-6"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="space-y-1">
                                            <h3 class="font-bold text-stone-800 text-sm sm:text-base"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                                            <p class="text-xs text-stone-500">Harga Satuan: <span class="font-semibold text-stone-700">Rp <?= number_format($item['harga'], 0, ',', '.') ?></span></p>
                                            <p class="text-[11px] text-emerald-700 font-medium">Stok Tersedia: <?= $item['stok'] ?> unit</p>
                                        </div>
                                    </div>

                                    <!-- Quantity Controller & Action -->
                                    <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-6 pt-2 sm:pt-0 border-t sm:border-t-0 border-stone-100">
                                        
                                        <!-- Inc/Dec Form -->
                                        <form method="POST" action="keranjang.php" class="flex items-center bg-stone-100 p-1 rounded-xl border border-stone-200">
                                            <input type="hidden" name="id_produk" value="<?= $id ?>">
                                            <input type="hidden" name="update_qty" value="1">

                                            <button type="submit" name="action_type" value="decrease" class="w-7 h-7 bg-white rounded-lg shadow-xs flex items-center justify-center text-stone-600 hover:text-rose-600 transition-colors">
                                                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                            </button>

                                            <span class="w-10 text-center font-bold text-xs text-stone-800"><?= $item['jumlah'] ?></span>

                                            <button type="submit" name="action_type" value="increase" class="w-7 h-7 bg-white rounded-lg shadow-xs flex items-center justify-center text-stone-600 hover:text-[#2E7D32] transition-colors">
                                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>

                                        <!-- Subtotal Item -->
                                        <div class="text-right min-w-[100px]">
                                            <p class="text-[10px] text-stone-400 uppercase font-bold">Subtotal</p>
                                            <p class="text-sm font-extrabold text-[#2E7D32]">Rp <?= number_format($item_subtotal, 0, ',', '.') ?></p>
                                        </div>

                                        <!-- Hapus Button -->
                                        <a href="keranjang.php?action=remove&id=<?= $id ?>" onclick="return confirm('Hapus item ini dari keranjang?')" class="p-2 text-stone-400 hover:text-rose-600 transition-colors" title="Hapus Produk">
                                            <i data-lucide="trash" class="w-4 h-4"></i>
                                        </a>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Note Pengiriman -->
                    <div class="p-4 bg-emerald-50/50 border border-emerald-200/60 rounded-2xl flex items-center gap-3">
                        <i data-lucide="truck" class="w-5 h-5 text-[#2E7D32]"></i>
                        <p class="text-xs text-stone-600">
                            Tanaman dikemas dengan media tanam lembap khusus & box pelindung tebal untuk mencegah kerusakan selama transit.
                        </p>
                    </div>
                </div>

                <!-- RINGKASAN BELANJA (RIGHT - 1 COL) -->
                <div class="space-y-4">
                    <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs space-y-5">
                        <h2 class="font-bold text-stone-800 text-base border-b border-stone-100 pb-3 flex items-center gap-2">
                            <i data-lucide="receipt" class="w-5 h-5 text-[#2E7D32]"></i> Ringkasan Belanja
                        </h2>

                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between text-stone-600">
                                <span>Total Item (<?= $total_items ?> barang)</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-stone-600">
                                <span>Estimasi PPN (11%)</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($pajak, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-stone-600">
                                <span>Biaya Layanan & Penanganan</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($biaya_penanganan, 0, ',', '.') ?></span>
                            </div>

                            <div class="border-t border-dashed border-stone-200 pt-3 flex justify-between items-baseline">
                                <span class="font-bold text-stone-800 text-sm">Total Tagihan</span>
                                <span class="text-lg font-extrabold text-[#2E7D32]">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <a href="checkout.php" class="w-full bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 px-4 rounded-xl text-xs flex items-center justify-center gap-2 shadow-md shadow-emerald-900/20 transition-all cursor-pointer">
                            <span>Lanjut ke Checkout</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>

                    <!-- Metode Pembayaran Safe Badge -->
                    <div class="p-4 bg-stone-100/70 border border-stone-200 rounded-2xl text-center space-y-2">
                        <p class="text-[11px] font-bold text-stone-600 uppercase tracking-wider">Metode Pembayaran Didukung</p>
                        <div class="flex items-center justify-center gap-3 text-xs text-stone-500 font-semibold">
                            <span class="px-2 py-1 bg-white rounded border border-stone-200">Transfer Bank</span>
                            <span class="px-2 py-1 bg-white rounded border border-stone-200">QRIS / E-Wallet</span>
                            <span class="px-2 py-1 bg-white rounded border border-stone-200">COD</span>
                        </div>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <!-- EMPTY CART STATE -->
            <div class="bg-white rounded-2xl border border-stone-200/80 p-12 text-center space-y-4 max-w-lg mx-auto my-8">
                <div class="w-20 h-20 bg-emerald-50 text-[#2E7D32] rounded-full flex items-center justify-center mx-auto">
                    <i data-lucide="shopping-bag" class="w-10 h-10"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-stone-800">Keranjang Belanja Masih Kosong</h3>
                    <p class="text-xs text-stone-500">Sepertinya Anda belum menambahkan tanaman hias atau produk perlengkapan ke keranjang.</p>
                </div>
                <a href="index.php" class="inline-flex items-center gap-2 px-5 py-3 bg-[#2E7D32] text-white text-xs font-semibold rounded-xl hover:bg-emerald-800 transition-all shadow-sm">
                    <i data-lucide="sprout" class="w-4 h-4"></i> Eksplor Katalog Tanaman
                </a>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER PELANGGAN -->
    <footer class="bg-white border-t border-stone-200/80 mt-12 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-[#2E7D32] flex items-center justify-center text-white">
                    <i data-lucide="sprout" class="w-4 h-4"></i>
                </div>
                <span class="text-xs font-bold text-stone-700">PlantHub Store &copy; <?= date('Y') ?></span>
            </div>
            <p class="text-xs text-stone-400">Sistem Manajemen Toko Tanaman Online & Management POS</p>
        </div>
    </footer>

    <!-- SCRIPT INITIALIZATION -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>