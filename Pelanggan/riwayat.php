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


$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$search_invoice = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Query data penjualan/pesanan
$query_str = "SELECT p.*, pl.nama_pelanggan, pl.no_hp 
              FROM penjualan p 
              LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id 
              WHERE 1=1";

if (!empty($search_invoice)) {
    $query_str .= " AND p.no_faktur LIKE '%$search_invoice%'";
}

$query_str .= " ORDER BY p.tanggal DESC";
$result_penjualan = mysqli_query($conn, $query_str);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - PlantHub</title>
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

                <div class="flex items-center gap-4 text-xs font-semibold">
                    <a href="profil.php" class="text-stone-600 hover:text-[#2E7D32] transition-colors flex items-center gap-1.5">
                        <i data-lucide="user" class="w-4 h-4"></i> Profil Saya
                    </a>
                    <a href="index.php" class="text-stone-600 hover:text-[#2E7D32] transition-colors flex items-center gap-1.5">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Katalog
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">

        <!-- Header & Search -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-stone-200/80 pb-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 tracking-tight">Riwayat Pesanan</h1>
                <p class="text-xs text-stone-500 mt-1">Lacak status pengiriman dan riwayat transaksi belanja tanaman Anda.</p>
            </div>

            <!-- Form Cari Faktur -->
            <form method="GET" action="riwayat.php" class="flex items-center gap-2">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-stone-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_invoice) ?>" placeholder="Cari No. Faktur (INV-...)" class="pl-9 pr-3.5 py-2 bg-white border border-stone-200 rounded-xl text-xs focus:outline-none focus:border-[#2E7D32] w-60">
                </div>
                <button type="submit" class="px-3.5 py-2 bg-[#2E7D32] text-white rounded-xl text-xs font-semibold hover:bg-emerald-800 transition-colors">Cari</button>
            </form>
        </div>

        <!-- Notification Success Post Checkout -->
        <?php if (isset($_GET['success']) && isset($_GET['invoice'])): ?>
            <div class="p-5 bg-emerald-50 border border-emerald-200 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#2E7D32] text-white flex items-center justify-center shrink-0">
                        <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-emerald-900 text-sm">Pesanan Berhasil Dibuat!</h3>
                        <p class="text-xs text-emerald-700">Nomor Faktur Anda: <span class="font-mono font-bold"><?= htmlspecialchars($_GET['invoice']) ?></span></p>
                    </div>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 text-xs font-semibold">Tutup</button>
            </div>
        <?php endif; ?>

        <!-- TABLE HASIL TRANSAKSI -->
        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-stone-50 text-stone-500 uppercase text-[10px] tracking-wider font-bold border-b border-stone-200/80">
                            <th class="py-4 px-6">No. Faktur / Waktu</th>
                            <th class="py-4 px-6">Penerima</th>
                            <th class="py-4 px-6">Metode & Kurir</th>
                            <th class="py-4 px-6">Total Tagihan</th>
                            <th class="py-4 px-6">Status</th>
                            <th class="py-4 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 text-xs">
                        <?php if (mysqli_num_rows($result_penjualan) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result_penjualan)): ?>
                                <tr class="hover:bg-stone-50/60 transition-colors">
                                    <td class="py-4 px-6 space-y-1">
                                        <p class="font-mono font-bold text-stone-900"><?= htmlspecialchars($row['no_faktur']) ?></p>
                                        <p class="text-[11px] text-stone-400"><?= date('d M Y, H:i', strtotime($row['tanggal'])) ?> WIB</p>
                                    </td>
                                    <td class="py-4 px-6 space-y-0.5">
                                        <p class="font-semibold text-stone-800"><?= htmlspecialchars($row['nama_pelanggan'] ?: 'Umum') ?></p>
                                        <p class="text-[11px] text-stone-400"><?= htmlspecialchars($row['no_hp'] ?: '-') ?></p>
                                    </td>
                                    <td class="py-4 px-6 space-y-0.5">
                                        <p class="font-semibold text-stone-700 uppercase"><?= str_replace('_', ' ', $row['metode_pembayaran']) ?></p>
                                        <p class="text-[11px] text-stone-400"><?= htmlspecialchars($row['ekspedisi'] ?: 'Regular') ?></p>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="font-extrabold text-[#2E7D32]">Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php 
                                            $st = strtolower($row['status_pembayaran']);
                                            $badge_cls = "bg-stone-100 text-stone-700 border-stone-200";
                                            if ($st === 'lunas' || $st === 'selesai') {
                                                $badge_cls = "bg-emerald-50 text-[#2E7D32] border-emerald-200";
                                            } elseif ($st === 'diproses') {
                                                $badge_cls = "bg-blue-50 text-blue-700 border-blue-200";
                                            } elseif ($st === 'menunggu pembayaran') {
                                                $badge_cls = "bg-amber-50 text-amber-700 border-amber-200";
                                            }
                                        ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $badge_cls ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                            <?= ucfirst($row['status_pembayaran']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a href="riwayat.php?detail_id=<?= $row['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-stone-100 hover:bg-[#2E7D32] hover:text-white rounded-lg font-semibold text-stone-600 transition-colors">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-stone-400">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 text-stone-300"></i>
                                    <p class="font-semibold text-stone-600">Belum ada riwayat pesanan.</p>
                                    <p class="text-[11px]">Silakan lakukan pembelian melalui katalog kami.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- MODAL DETAIL TRANSAKSI -->
    <?php if (isset($_GET['detail_id'])): ?>
        <?php
            $det_id = intval($_GET['detail_id']);
            $q_head = mysqli_query($conn, "SELECT p.*, pl.nama_pelanggan, pl.alamat, pl.no_hp FROM penjualan p LEFT JOIN pelanggan pl ON p.pelanggan_id = pl.id WHERE p.id = $det_id LIMIT 1");
            $head = mysqli_fetch_assoc($q_head);

            $q_items = mysqli_query($conn, "SELECT dp.*, pr.nama_produk FROM detail_penjualan dp JOIN produk pr ON dp.produk_id = pr.id WHERE dp.penjualan_id = $det_id");
        ?>
        <?php if ($head): ?>
            <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl border border-stone-200 max-w-xl w-full p-6 space-y-5 shadow-2xl relative">
                    
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <div>
                            <h3 class="font-bold text-stone-900 text-base">Rincian Faktur <?= htmlspecialchars($head['no_faktur']) ?></h3>
                            <p class="text-[11px] text-stone-400"><?= date('d F Y, H:i', strtotime($head['tanggal'])) ?></p>
                        </div>
                        <a href="riwayat.php" class="p-1.5 text-stone-400 hover:text-stone-700 bg-stone-100 rounded-lg"><i data-lucide="x" class="w-4 h-4"></i></a>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="bg-stone-50 p-3.5 rounded-xl space-y-1 border border-stone-200/60">
                            <p class="font-bold text-stone-800">Tujuan Pengiriman:</p>
                            <p class="text-stone-600"><?= htmlspecialchars($head['nama_pelanggan']) ?> (<?= htmlspecialchars($head['no_hp']) ?>)</p>
                            <p class="text-stone-500 text-[11px]"><?= htmlspecialchars($head['alamat'] ?: 'Alamat tidak dicatat') ?></p>
                        </div>

                        <!-- Items Table -->
                        <div class="divide-y divide-stone-100 border-t border-b border-stone-100 py-2">
                            <?php while ($it = mysqli_fetch_assoc($q_items)): ?>
                                <div class="py-2 flex items-center justify-between text-xs">
                                    <div>
                                        <p class="font-bold text-stone-800"><?= htmlspecialchars($it['nama_produk']) ?></p>
                                        <p class="text-[11px] text-stone-400"><?= $it['jumlah'] ?> x Rp <?= number_format($it['harga_satuan'], 0, ',', '.') ?></p>
                                    </div>
                                    <span class="font-semibold text-stone-700">Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <div class="space-y-1.5 text-xs text-stone-600 pt-1">
                            <div class="flex justify-between">
                                <span>PPN</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($head['pajak'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Ongkos Kirim</span>
                                <span class="font-semibold text-stone-800">Rp <?= number_format($head['biaya_pengiriman'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between font-bold text-stone-900 text-sm pt-2 border-t border-stone-200">
                                <span>Total Faktur</span>
                                <span class="text-[#2E7D32]">Rp <?= number_format($head['total_harga'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <a href="riwayat.php" class="w-full bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold py-2.5 rounded-xl text-xs flex items-center justify-center">Tutup Rincian</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

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