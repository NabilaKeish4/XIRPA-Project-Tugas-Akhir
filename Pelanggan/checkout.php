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

// Redirect jika keranjang kosong
if (empty($_SESSION['keranjang'])) {
    header("Location: index.php");
    exit();
}

// Hitung Ringkasan Belanja
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $subtotal += ($item['harga'] * $item['jumlah']);
    $total_items += $item['jumlah'];
}

$pajak = $subtotal * 0.11; // PPN 11%
$biaya_penanganan = 2000;
$ongkir_default = 15000;
$grand_total = $subtotal + $pajak + $biaya_penanganan + $ongkir_default;

$error_msg = "";

// PROCESS CHECKOUT FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_checkout'])) {
    $nama_penerima  = mysqli_real_escape_string($conn, trim($_POST['nama_penerima']));
    $no_hp          = mysqli_real_escape_string($conn, trim($_POST['no_hp']));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']));
    $alamat_lengkap = mysqli_real_escape_string($conn, trim($_POST['alamat_lengkap']));
    $metode_bayar   = mysqli_real_escape_string($conn, $_POST['metode_pembayaran']);
    $kurir          = mysqli_real_escape_string($conn, $_POST['ekspedisi']);
    $catatan        = mysqli_real_escape_string($conn, trim($_POST['catatan']));

    if (empty($nama_penerima) || empty($no_hp) || empty($alamat_lengkap)) {
        $error_msg = "Harap isi semua kolom wajib pada informasi pengiriman!";
    } else {
        // Start Transaction
        mysqli_begin_transaction($conn);

        try {
            // 1. Cek / Insert Data Pelanggan
            $id_pelanggan = null;
            $cek_pelanggan = mysqli_query($conn, "SELECT id FROM pelanggan WHERE no_hp = '$no_hp' LIMIT 1");
            if ($cek_pelanggan && mysqli_num_rows($cek_pelanggan) > 0) {
                $row_p = mysqli_fetch_assoc($cek_pelanggan);
                $id_pelanggan = (int)$row_p['id'];
                // Update alamat terbaru
                mysqli_query($conn, "UPDATE pelanggan SET nama_pelanggan = '$nama_penerima', alamat = '$alamat_lengkap', email = '$email' WHERE id = $id_pelanggan");
            } else {
                $ins_p = mysqli_query($conn, "INSERT INTO pelanggan (nama_pelanggan, no_hp, email, alamat) VALUES ('$nama_penerima', '$no_hp', '$email', '$alamat_lengkap')");
                $id_pelanggan = (int)mysqli_insert_id($conn);
            }

            // Simpan ID pelanggan ke Session jika belum ada
            $_SESSION['user_id'] = $id_pelanggan;
            $_SESSION['user_nama'] = $nama_penerima;

            // 2. Buat Nomor Faktur Unik (INV-YYYYMMDD-XXXX)
            $kode_faktur = "INV-" . date('Ymd') . "-" . strtoupper(substr(uniqid(), -4));
            $tgl_transaksi = date('Y-m-d H:i:s');
            $status_pesanan = ($metode_bayar === 'cod') ? 'Diproses' : 'Menunggu Pembayaran';

            // 3. Insert Master Transaksi Penjualan
            $query_penjualan = "INSERT INTO penjualan 
                (no_faktur, pelanggan_id, tanggal, total_harga, pajak, biaya_pengiriman, metode_pembayaran, ekspedisi, status_pembayaran, catatan) 
                VALUES 
                ('$kode_faktur', $id_pelanggan, '$tgl_transaksi', '$grand_total', '$pajak', '$ongkir_default', '$metode_bayar', '$kurir', '$status_pesanan', '$catatan')";
            
            if (!mysqli_query($conn, $query_penjualan)) {
                throw new Exception("Gagal menyimpan data transaksi penjualan.");
            }

            $penjualan_id = (int)mysqli_insert_id($conn);

            // 4. Insert Detail Penjualan & Potong Stok Produk
            foreach ($_SESSION['keranjang'] as $prod_id => $item) {
                $prod_id = (int)$prod_id;
                $qty = (int)$item['jumlah'];
                $harga_satuan = (float)$item['harga'];
                $subtotal_item = $qty * $harga_satuan;

                // Cek stok terkini
                $res_stok = mysqli_query($conn, "SELECT stok FROM produk WHERE id = $prod_id FOR UPDATE");
                $prod_data = mysqli_fetch_assoc($res_stok);

                if (!$prod_data || $prod_data['stok'] < $qty) {
                    throw new Exception("Stok untuk produk " . htmlspecialchars($item['nama_produk']) . " tidak mencukupi!");
                }

                // Insert Detail
                $query_detail = "INSERT INTO detail_penjualan (penjualan_id, produk_id, jumlah, harga_satuan, subtotal) 
                                VALUES ($penjualan_id, $prod_id, $qty, '$harga_satuan', '$subtotal_item')";
                if (!mysqli_query($conn, $query_detail)) {
                    throw new Exception("Gagal menyimpan rincian item transaksi.");
                }

                // Update Stok
                $query_stok = "UPDATE produk SET stok = stok - $qty WHERE id = $prod_id";
                if (!mysqli_query($conn, $query_stok)) {
                    throw new Exception("Gagal memperbarui stok produk.");
                }
            }

            // Commit Transaksi Jika Semua Berhasil
            mysqli_commit($conn);

            // Simpan Faktur di Session untuk Halaman Success & Clear Cart
            $_SESSION['last_invoice'] = $kode_faktur;
            $_SESSION['keranjang'] = [];

            header("Location: riwayat.php?success=1&invoice=" . urlencode($kode_faktur));
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Transaksi Gagal: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Pesanan - PlantHub</title>
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
                    <span class="text-stone-400 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-stone-200 text-stone-600 flex items-center justify-center text-[10px]">1</span> Keranjang
                    </span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-stone-300"></i>
                    <span class="text-[#2E7D32] flex items-center gap-1.5 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
                        <span class="w-5 h-5 rounded-full bg-[#2E7D32] text-white flex items-center justify-center text-[10px]">2</span> Checkout
                    </span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-stone-300"></i>
                    <span class="text-stone-400 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-stone-200 text-stone-600 flex items-center justify-center text-[10px]">3</span> Selesai
                    </span>
                </div>

                <a href="keranjang.php" class="inline-flex items-center gap-2 text-xs font-semibold text-stone-600 hover:text-[#2E7D32] transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Keranjang
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">

        <div class="border-b border-stone-200/80 pb-4">
            <h1 class="text-2xl font-bold text-stone-900 tracking-tight">Formulir Checkout</h1>
            <p class="text-xs text-stone-500 mt-1">Lengkapi alamat pengiriman dan pilih metode pembayaran untuk menyelesaikan order.</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs flex items-center gap-2 shadow-xs">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                
                <!-- INFORMASI PENGIRIMAN & PEMBAYARAN (LEFT - 2 COLS) -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- 1. Alamat Pengiriman -->
                    <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs space-y-4">
                        <h2 class="font-bold text-stone-800 text-base border-b border-stone-100 pb-3 flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5 text-[#2E7D32]"></i> Alamat Penerima
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block font-semibold text-stone-600 mb-1.5">Nama Lengkap Penerima *</label>
                                <input type="text" name="nama_penerima" required value="<?= isset($_SESSION['user_nama']) ? htmlspecialchars($_SESSION['user_nama']) : '' ?>" placeholder="Contoh: Nabila Keisha" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                            </div>
                            <div>
                                <label class="block font-semibold text-stone-600 mb-1.5">No. WhatsApp / HP *</label>
                                <input type="text" name="no_hp" required placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-stone-600 mb-1.5">Email (Opsional)</label>
                                <input type="email" name="email" placeholder="nama@email.com" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-stone-600 mb-1.5">Alamat Lengkap Pengiriman *</label>
                                <textarea name="alamat_lengkap" rows="3" required placeholder="Jl. Raya Utama No. 123, Kecamatan, Kota / Kabupaten, Kode Pos..." class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Ekspedisi & Kurir -->
                    <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs space-y-4">
                        <h2 class="font-bold text-stone-800 text-base border-b border-stone-100 pb-3 flex items-center gap-2">
                            <i data-lucide="truck" class="w-5 h-5 text-[#2E7D32]"></i> Opsi Layanan Kurir
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <label class="border border-stone-200 p-4 rounded-xl flex flex-col justify-between cursor-pointer hover:border-[#2E7D32] has-[:checked]:border-[#2E7D32] has-[:checked]:bg-emerald-50/50 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-stone-800">JNE Express</span>
                                    <input type="radio" name="ekspedisi" value="JNE Express" checked class="accent-[#2E7D32]">
                                </div>
                                <p class="text-[11px] text-stone-500">Estimasi 1-2 hari kerja</p>
                                <p class="text-xs font-bold text-[#2E7D32] mt-2">Rp 15.000</p>
                            </label>

                            <label class="border border-stone-200 p-4 rounded-xl flex flex-col justify-between cursor-pointer hover:border-[#2E7D32] has-[:checked]:border-[#2E7D32] has-[:checked]:bg-emerald-50/50 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-stone-800">J&T Cargo</span>
                                    <input type="radio" name="ekspedisi" value="J&T Cargo" class="accent-[#2E7D32]">
                                </div>
                                <p class="text-[11px] text-stone-500">Aman untuk tanaman pot besar</p>
                                <p class="text-xs font-bold text-[#2E7D32] mt-2">Rp 15.000</p>
                            </label>

                            <label class="border border-stone-200 p-4 rounded-xl flex flex-col justify-between cursor-pointer hover:border-[#2E7D32] has-[:checked]:border-[#2E7D32] has-[:checked]:bg-emerald-50/50 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-stone-800">Kurir Toko (Instant)</span>
                                    <input type="radio" name="ekspedisi" value="Kurir Toko Direct" class="accent-[#2E7D32]">
                                </div>
                                <p class="text-[11px] text-stone-500">Khusus area sekitar cabang</p>
                                <p class="text-xs font-bold text-[#2E7D32] mt-2">Rp 15.000</p>
                            </label>
                        </div>
                    </div>

                    <!-- 3. Metode Pembayaran -->
                    <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs space-y-4">
                        <h2 class="font-bold text-stone-800 text-base border-b border-stone-100 pb-3 flex items-center gap-2">
                            <i data-lucide="wallet" class="w-5 h-5 text-[#2E7D32]"></i> Metode Pembayaran
                        </h2>

                        <div class="space-y-3 text-xs">
                            <label class="border border-stone-200 p-4 rounded-xl flex items-center justify-between cursor-pointer hover:border-[#2E7D32] has-[:checked]:border-[#2E7D32] has-[:checked]:bg-emerald-50/50 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-stone-100 rounded-lg text-stone-600"><i data-lucide="building-2" class="w-5 h-5"></i></div>
                                    <div>
                                        <p class="font-bold text-stone-800">Transfer Bank Direct (BCA / Mandiri / BRI)</p>
                                        <p class="text-[11px] text-stone-500">Konfirmasi otomatis via sistem</p>
                                    </div>
                                </div>
                                <input type="radio" name="metode_pembayaran" value="transfer_bank" checked class="accent-[#2E7D32]">
                            </label>

                            <label class="border border-stone-200 p-4 rounded-xl flex items-center justify-between cursor-pointer hover:border-[#2E7D32] has-[:checked]:border-[#2E7D32] has-[:checked]:bg-emerald-50/50 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-stone-100 rounded-lg text-stone-600"><i data-lucide="qr-code" class="w-5 h-5"></i></div>
                                    <div>
                                        <p class="font-bold text-stone-800">QRIS All Payment / E-Wallet</p>
                                        <p class="text-[11px] text-stone-500">GoPay, OVO, Dana, ShopeePay, LinkAja</p>
                                    </div>
                                </div>
                                <input type="radio" name="metode_pembayaran" value="qris" class="accent-[#2E7D32]">
                            </label>

                            <label class="border border-stone-200 p-4 rounded-xl flex items-center justify-between cursor-pointer hover:border-[#2E7D32] has-[:checked]:border-[#2E7D32] has-[:checked]:bg-emerald-50/50 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-stone-100 rounded-lg text-stone-600"><i data-lucide="banknote" class="w-5 h-5"></i></div>
                                    <div>
                                        <p class="font-bold text-stone-800">Bayar di Tempat (COD)</p>
                                        <p class="text-[11px] text-stone-500">Bayar tunai langsung saat barang tiba di lokasi</p>
                                    </div>
                                </div>
                                <input type="radio" name="metode_pembayaran" value="cod" class="accent-[#2E7D32]">
                            </label>
                        </div>

                        <div>
                            <label class="block font-semibold text-stone-600 mb-1.5 text-xs">Catatan Pesanan / Instruksi Khusus (Opsional)</label>
                            <input type="text" name="catatan" placeholder="Contoh: Titipkan ke satpam kompleks jika rumah kosong" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                        </div>
                    </div>

                </div>

                <!-- RINGKASAN ITEMS & Rincian Tagihan (RIGHT - 1 COL) -->
                <div class="space-y-4">
                    <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs space-y-5 sticky top-28">
                        <h2 class="font-bold text-stone-800 text-base border-b border-stone-100 pb-3 flex items-center justify-between">
                            <span>Item Pesanan</span>
                            <span class="text-xs font-semibold text-stone-400"><?= $total_items ?> barang</span>
                        </h2>

                        <!-- List Mini Items -->
                        <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                            <?php foreach ($_SESSION['keranjang'] as $item): ?>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <div class="flex items-center gap-2.5 overflow-hidden">
                                        <div class="w-10 h-10 rounded-lg bg-stone-100 overflow-hidden shrink-0 border border-stone-200/60">
                                            <?php if (!empty($item['gambar'])): ?>
                                                <img src="../assets/uploads/<?= htmlspecialchars($item['gambar']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-stone-400"><i data-lucide="image" class="w-4 h-4"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="truncate">
                                            <p class="font-bold text-stone-800 truncate"><?= htmlspecialchars($item['nama_produk']) ?></p>
                                            <p class="text-[10px] text-stone-400"><?= $item['jumlah'] ?> x Rp <?= number_format($item['harga'], 0, ',', '.') ?></p>
                                        </div>
                                    </div>
                                    <span class="font-semibold text-stone-700 shrink-0">Rp <?= number_format($item['harga'] * $item['jumlah'], 0, ',', '.') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="border-t border-stone-100 pt-4 space-y-2.5 text-xs">
                            <div class="flex justify-between text-stone-600">
                                <span>Subtotal Produk</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-stone-600">
                                <span>PPN (11%)</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($pajak, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-stone-600">
                                <span>Ongkos Kirim Standard</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($ongkir_default, 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-stone-600">
                                <span>Biaya Layanan</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($biaya_penanganan, 0, ',', '.') ?></span>
                            </div>

                            <div class="border-t border-dashed border-stone-200 pt-3 flex justify-between items-baseline">
                                <span class="font-bold text-stone-800 text-sm">Total Tagihan</span>
                                <span class="text-lg font-extrabold text-[#2E7D32]">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <button type="submit" name="proses_checkout" class="w-full bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3.5 px-4 rounded-xl text-xs flex items-center justify-center gap-2 shadow-md shadow-emerald-900/20 transition-all cursor-pointer">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span>Buat Pesanan Sekarang</span>
                        </button>
                    </div>
                </div>

            </div>
        </form>

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