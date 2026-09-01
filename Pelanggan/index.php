<?php
session_start();
// Database connection setup (sesuaikan koneksi jika menggunakan file terpisah misal config.php)
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

// Fitur Tambah ke Keranjang via POST
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_keranjang'])) {
    $id_produk = intval($_POST['id_produk']);
    $jumlah = isset($_POST['jumlah']) ? intval($_POST['jumlah']) : 1;

    // Ambil detail produk dari DB
    $query = "SELECT * FROM produk WHERE id = $id_produk AND stok > 0";
    $res = mysqli_query($conn, $query);

    if ($res && mysqli_num_rows($res) > 0) {
        $produk = mysqli_fetch_assoc($res);
        
        // Cek jika produk sudah ada di keranjang
        if (isset($_SESSION['keranjang'][$id_produk])) {
            $new_qty = $_SESSION['keranjang'][$id_produk]['jumlah'] + $jumlah;
            if ($new_qty <= $produk['stok']) {
                $_SESSION['keranjang'][$id_produk]['jumlah'] = $new_qty;
                $success_msg = "Berhasil memperbarui jumlah <b>" . htmlspecialchars($produk['nama_produk']) . "</b> di keranjang!";
            } else {
                $error_msg = "Jumlah melebihi stok yang tersedia (" . $produk['stok'] . " unit)!";
            }
        } else {
            if ($jumlah <= $produk['stok']) {
                $_SESSION['keranjang'][$id_produk] = [
                    'id' => $produk['id'],
                    'nama_produk' => $produk['nama_produk'],
                    'harga' => $produk['harga'],
                    'gambar' => $produk['gambar'],
                    'jumlah' => $jumlah,
                    'stok' => $produk['stok']
                ];
                $success_msg = "<b>" . htmlspecialchars($produk['nama_produk']) . "</b> ditambahkan ke keranjang!";
            } else {
                $error_msg = "Jumlah melebihi stok yang tersedia!";
            }
        }
    } else {
        $error_msg = "Produk tidak ditemukan atau stok habis!";
    }
}

// Fitur Filtering & Searching
$kategori_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$search_query    = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_query      = isset($_GET['sort']) ? trim($_GET['sort']) : 'terbaru';

$sql = "SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE 1=1";

if (!empty($kategori_filter)) {
    $safe_kat = mysqli_real_escape_string($conn, $kategori_filter);
    $sql .= " AND k.nama_kategori = '$safe_kat'";
}

if (!empty($search_query)) {
    $safe_search = mysqli_real_escape_string($conn, $search_query);
    $sql .= " AND (p.nama_produk LIKE '%$safe_search%' OR p.deskripsi LIKE '%$safe_search%')";
}

switch ($sort_query) {
    case 'harga_low':
        $sql .= " ORDER BY p.harga ASC";
        break;
    case 'harga_high':
        $sql .= " ORDER BY p.harga DESC";
        break;
    case 'nama':
        $sql .= " ORDER BY p.nama_produk ASC";
        break;
    default:
        $sql .= " ORDER BY p.id DESC";
        break;
}

$result_produk = mysqli_query($conn, $sql);

// Ambil list kategori untuk sidebar filter
$result_kategori = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Hitung total item keranjang
$total_cart_items = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $total_cart_items += $item['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Belanja Tanaman Hias & Perlangkapan Rumah</title>
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
            <div class="flex items-center justify-between h-20 gap-4">
                
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

                <!-- Search Form Desk (Middle) -->
                <form action="index.php" method="GET" class="hidden md:flex items-center flex-1 max-w-md mx-8">
                    <div class="relative w-full">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari Monstera, Sansevieria, Pupuk Organik..." class="w-full pl-10 pr-4 py-2.5 bg-stone-100 border border-transparent rounded-full text-xs font-medium focus:bg-white focus:border-[#2E7D32] focus:outline-none transition-all">
                        <i data-lucide="search" class="w-4 h-4 text-stone-400 absolute left-3.5 top-3"></i>
                        <?php if (!empty($kategori_filter)): ?>
                            <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori_filter) ?>">
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Navigation Actions -->
                <div class="flex items-center gap-3">
                    <!-- Link Keranjang -->
                    <a href="keranjang.php" class="relative p-2.5 text-stone-600 hover:text-[#2E7D32] hover:bg-stone-100 rounded-xl transition-colors">
                        <i data-lucide="shopping-bag" class="w-6 h-6"></i>
                        <?php if ($total_cart_items > 0): ?>
                            <span class="absolute top-1 right-1 bg-[#2E7D32] text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center border-2 border-white animate-pulse">
                                <?= $total_cart_items ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Riwayat Pesanan -->
                    <a href="riwayat.php" class="p-2.5 text-stone-600 hover:text-[#2E7D32] hover:bg-stone-100 rounded-xl transition-colors flex items-center gap-1.5 text-xs font-semibold">
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                        <span class="hidden sm:inline">Pesanan Saya</span>
                    </a>

                    <!-- Profil User -->
                    <a href="profil.php" class="flex items-center gap-2 pl-2 border-l border-stone-200 hover:opacity-80 transition-opacity">
                        <div class="w-9 h-9 rounded-full bg-amber-100 border border-amber-300 text-amber-800 font-bold flex items-center justify-center text-xs">
                            <?= isset($_SESSION['user_nama']) ? strtoupper(substr($_SESSION['user_nama'], 0, 2)) : 'PL' ?>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Mobile Search Bar -->
            <form action="index.php" method="GET" class="md:hidden pb-4">
                <div class="relative w-full">
                    <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari tanaman favoritmu..." class="w-full pl-10 pr-4 py-2 bg-stone-100 border border-transparent rounded-full text-xs focus:bg-white focus:border-[#2E7D32] focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-stone-400 absolute left-3.5 top-2.5"></i>
                </div>
            </form>
        </div>
    </header>

    <!-- BANNER HERO -->
    <section class="bg-gradient-to-r from-stone-900 via-stone-800 to-emerald-950 text-white py-12 px-4 relative overflow-hidden">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-8 relative z-10">
            <div class="space-y-4 max-w-xl text-center md:text-left">
                <span class="inline-block px-3 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 rounded-full text-xs font-semibold tracking-wide">
                    🌿 Garansi Segar & Sehat Sampai Tujuan
                </span>
                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-tight">
                    Bawakan Nuansa Alam Asri ke Dalam Hunian Anda
                </h1>
                <p class="text-stone-300 text-xs sm:text-sm font-light leading-relaxed">
                    Koleksi tanaman hias premium, sekulen langka, dan perlengkapan perkebunan organik dengan perawatan mudah untuk estetika ruangan maksimal.
                </p>
            </div>
            <div class="hidden lg:flex gap-4">
                <div class="p-4 bg-white/10 backdrop-blur-md rounded-2xl border border-white/10 flex items-center gap-3">
                    <i data-lucide="truck" class="w-8 h-8 text-emerald-400"></i>
                    <div>
                        <p class="text-xs font-bold">Pengiriman Cepat</p>
                        <p class="text-[10px] text-stone-300">Packing aman berlapis</p>
                    </div>
                </div>
                <div class="p-4 bg-white/10 backdrop-blur-md rounded-2xl border border-white/10 flex items-center gap-3">
                    <i data-lucide="shield-check" class="w-8 h-8 text-emerald-400"></i>
                    <div>
                        <p class="text-xs font-bold">Kualitas Terjamin</p>
                        <p class="text-[10px] text-stone-300"> Bebas hama & penyakit</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- NOTIFIKASI SYSTEM -->
    <?php if (!empty($success_msg)): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-[#2E7D32]"></i>
                    <span><?= $success_msg ?></span>
                </div>
                <a href="keranjang.php" class="font-bold underline text-[#2E7D32] hover:text-emerald-900">Lihat Keranjang</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs flex items-center gap-2 shadow-xs">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span><?= $error_msg ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- MAIN CATALOG CONTAINER -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
        <div class="flex flex-col lg:flex-row gap-8">

            <!-- SIDEBAR FILTER (LEFT) -->
            <aside class="w-full lg:w-64 shrink-0 space-y-6">
                <!-- Filter Box -->
                <div class="bg-white p-5 rounded-2xl border border-stone-200/80 shadow-xs space-y-5">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                        <h3 class="font-bold text-stone-800 text-sm flex items-center gap-2">
                            <i data-lucide="sliders" class="w-4 h-4 text-[#2E7D32]"></i> Filter Katalog
                        </h3>
                        <?php if (!empty($kategori_filter) || !empty($search_query)): ?>
                            <a href="index.php" class="text-[11px] text-rose-600 hover:underline font-semibold">Reset</a>
                        <?php endif; ?>
                    </div>

                    <!-- Kategori List -->
                    <div>
                        <p class="text-xs font-bold text-stone-500 uppercase tracking-wider mb-2.5">Kategori Produk</p>
                        <div class="space-y-1">
                            <a href="index.php<?= !empty($search_query) ? '?search='.urlencode($search_query) : '' ?>" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-colors <?= empty($kategori_filter) ? 'bg-emerald-50 text-[#2E7D32]' : 'text-stone-600 hover:bg-stone-50' ?>">
                                <span>Semua Kategori</span>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </a>
                            <?php if ($result_kategori && mysqli_num_rows($result_kategori) > 0): ?>
                                <?php while ($kat = mysqli_fetch_assoc($result_kategori)): ?>
                                    <a href="index.php?kategori=<?= urlencode($kat['nama_kategori']) ?><?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition-colors <?= ($kategori_filter == $kat['nama_kategori']) ? 'bg-emerald-50 text-[#2E7D32] font-semibold' : 'text-stone-600 hover:bg-stone-50' ?>">
                                        <span><?= htmlspecialchars($kat['nama_kategori']) ?></span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 opacity-40"></i>
                                    </a>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sort Option -->
                    <div class="border-t border-stone-100 pt-4">
                        <p class="text-xs font-bold text-stone-500 uppercase tracking-wider mb-2.5">Urutkan Berdasarkan</p>
                        <form action="index.php" method="GET">
                            <?php if (!empty($kategori_filter)): ?>
                                <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori_filter) ?>">
                            <?php endif; ?>
                            <?php if (!empty($search_query)): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                            <?php endif; ?>
                            <select name="sort" onchange="this.form.submit()" class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-700 font-medium focus:outline-none focus:border-[#2E7D32]">
                                <option value="terbaru" <?= $sort_query == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                                <option value="harga_low" <?= $sort_query == 'harga_low' ? 'selected' : '' ?>>Harga: Rendah ke Tinggi</option>
                                <option value="harga_high" <?= $sort_query == 'harga_high' ? 'selected' : '' ?>>Harga: Tinggi ke Rendah</option>
                                <option value="nama" <?= $sort_query == 'nama' ? 'selected' : '' ?>>Nama: A - Z</option>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Info Box Tips Perawatan -->
                <div class="p-5 bg-amber-50/60 border border-amber-200/60 rounded-2xl space-y-2">
                    <div class="flex items-center gap-2 text-amber-800 font-bold text-xs">
                        <i data-lucide="sun" class="w-4 h-4 text-amber-600"></i>
                        <span>Tips Perawatan Tanaman</span>
                    </div>
                    <p class="text-[11px] text-amber-900/80 leading-relaxed">
                        Penyiraman ideal dilakukan pagi hari sebelum jam 9. Hindari genangan air pada media tanam agar akar tidak membusuk.
                    </p>
                </div>
            </aside>

            <!-- PRODUCT GRID (RIGHT) -->
            <section class="flex-1 space-y-6">
                <!-- Status pencarian -->
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-stone-500">
                        Menampilkan hasil untuk: <span class="text-stone-800 font-bold"><?= !empty($kategori_filter) ? htmlspecialchars($kategori_filter) : 'Semua Produk' ?></span>
                        <?= !empty($search_query) ? ' ("'.htmlspecialchars($search_query).'")' : '' ?>
                    </p>
                </div>

                <!-- Cards Grid -->
                <?php if ($result_produk && mysqli_num_rows($result_produk) > 0): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while ($produk = mysqli_fetch_assoc($result_produk)): ?>
                            <div class="bg-white border border-stone-200/80 rounded-2xl overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col group">
                                
                                <!-- Product Image Container -->
                                <div class="relative h-48 bg-stone-100 overflow-hidden">
                                    <?php if (!empty($produk['gambar'])): ?>
                                        <img src="../assets/uploads/<?= htmlspecialchars($produk['gambar']) ?>" alt="<?= htmlspecialchars($produk['nama_produk']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-stone-400 bg-stone-100">
                                            <i data-lucide="image" class="w-10 h-10 mb-1"></i>
                                            <span class="text-[10px]">Foto Belum Tersedia</span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Kategori Badge -->
                                    <span class="absolute top-3 left-3 bg-white/90 backdrop-blur-md text-stone-800 text-[10px] font-bold px-2.5 py-1 rounded-full border border-stone-200/60 shadow-xs">
                                        <?= htmlspecialchars($produk['nama_kategori'] ?: 'Umum') ?>
                                    </span>

                                    <!-- Stok Badge -->
                                    <?php if ($produk['stok'] <= 0): ?>
                                        <span class="absolute top-3 right-3 bg-rose-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-xs">
                                            Habis
                                        </span>
                                    <?php else: ?>
                                        <span class="absolute top-3 right-3 bg-emerald-600/90 backdrop-blur-md text-white text-[10px] font-bold px-2.5 py-1 rounded-full shadow-xs">
                                            Stok: <?= $produk['stok'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Detail Content -->
                                <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                    <div>
                                        <h3 class="font-bold text-stone-800 text-sm hover:text-[#2E7D32] transition-colors line-clamp-1">
                                            <?= htmlspecialchars($produk['nama_produk']) ?>
                                        </h3>
                                        <p class="text-stone-500 text-xs mt-1.5 line-clamp-2 leading-relaxed">
                                            <?= htmlspecialchars($produk['deskripsi'] ?: 'Tanaman hias berkualitas tinggi untuk dekorasi interior dan eksterior.') ?>
                                        </p>
                                    </div>

                                    <div class="pt-3 border-t border-stone-100 flex items-center justify-between">
                                        <div>
                                            <p class="text-[10px] text-stone-400 uppercase font-semibold">Harga</p>
                                            <p class="text-base font-extrabold text-[#2E7D32]">
                                                Rp <?= number_format($produk['harga'], 0, ',', '.') ?>
                                            </p>
                                        </div>

                                        <!-- Action Form -->
                                        <?php if ($produk['stok'] > 0): ?>
                                            <form method="POST" action="index.php">
                                                <input type="hidden" name="id_produk" value="<?= $produk['id'] ?>">
                                                <input type="hidden" name="jumlah" value="1">
                                                <button type="submit" name="tambah_keranjang" class="p-2.5 bg-emerald-50 text-[#2E7D32] hover:bg-[#2E7D32] hover:text-white rounded-xl transition-colors cursor-pointer" title="Tambah ke Keranjang">
                                                    <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button disabled class="p-2.5 bg-stone-100 text-stone-400 rounded-xl cursor-not-allowed">
                                                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="bg-white rounded-2xl border border-stone-200/80 p-12 text-center space-y-3">
                        <div class="w-16 h-16 bg-stone-100 text-stone-400 rounded-full flex items-center justify-center mx-auto">
                            <i data-lucide="search-x" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-base font-bold text-stone-800">Tanaman Tidak Ditemukan</h3>
                        <p class="text-xs text-stone-500 max-w-sm mx-auto">Maaf, kami tidak dapat menemukan produk yang sesuai dengan pencarian atau filter Anda.</p>
                        <a href="index.php" class="inline-block mt-2 px-4 py-2 bg-[#2E7D32] text-white text-xs font-semibold rounded-xl hover:bg-emerald-800 transition-colors">
                            Lihat Semua Produk
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
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