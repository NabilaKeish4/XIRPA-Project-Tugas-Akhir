<?php
session_start();
require_once '../Config/database.php';

// PROTEKSI LOGIN PELANGGAN
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$nama_user  = $_SESSION['nama_user'] ?? 'Pelanggan';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// =========================================================
// STATISTIK PESANAN
// =========================================================
$total_orders   = 0;
$pending_orders = 0;
$total_belanja  = 0;

$qStats = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status IN ('Diproses','Dikirim') THEN 1 ELSE 0 END), 0) AS pending,
        COALESCE(SUM(CASE WHEN status = 'Selesai' THEN total_harga ELSE 0 END), 0) AS total_belanja
    FROM transaksi
    WHERE user_id = $user_id AND jenis_transaksi = 'penjualan'
");
if ($qStats) {
    $s = mysqli_fetch_assoc($qStats);
    $total_orders   = (int)$s['total'];
    $pending_orders = (int)$s['pending'];
    $total_belanja  = (float)$s['total_belanja'];
}

// =========================================================
// PESANAN TERAKHIR
// =========================================================
$lastOrder = null;
$qLast = mysqli_query($conn, "
    SELECT t.*, 
           (SELECT COUNT(*) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS jml_item,
           (SELECT COALESCE(SUM(jumlah), 0) FROM transaksi_detail td WHERE td.transaksi_id = t.id) AS total_qty
    FROM transaksi t
    WHERE t.user_id = $user_id AND t.jenis_transaksi = 'penjualan'
    ORDER BY t.created_at DESC
    LIMIT 1
");
if ($qLast && mysqli_num_rows($qLast) > 0) $lastOrder = mysqli_fetch_assoc($qLast);

// =========================================================
// PRODUK TERBARU
// =========================================================
$produk_terbaru = [];
$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.stok > 0 
    ORDER BY p.id DESC LIMIT 4
");
if ($qProduk) while ($r = mysqli_fetch_assoc($qProduk)) $produk_terbaru[] = $r;

// =========================================================
// CEK CHAT BELUM DIBACA (dari admin)
// =========================================================
$unreadChat = 0;
$qChat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM chats WHERE user_id = $user_id AND sender_type = 'admin' AND is_read = 0 AND is_deleted = 0");
if ($qChat) $unreadChat = (int)mysqli_fetch_assoc($qChat)['total'];

// =========================================================
// BADGE STATUS
// =========================================================
if (!function_exists('badgeStatusDash')) {
    function badgeStatusDash($status) {
        switch ($status) {
            case 'Diproses': return 'bg-amber-50 text-amber-700 border-amber-200';
            case 'Dikirim':  return 'bg-blue-50 text-blue-700 border-blue-200';
            case 'Selesai':  return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            case 'Batal':    return 'bg-rose-50 text-rose-700 border-rose-200';
            default:         return 'bg-stone-100 text-stone-600 border-stone-200';
        }
    }
}

if (!function_exists('iconStatusDash')) {
    function iconStatusDash($status) {
        switch ($status) {
            case 'Diproses': return 'clock';
            case 'Dikirim':  return 'truck';
            case 'Selesai':  return 'check-circle-2';
            case 'Batal':    return 'x-circle';
            default:         return 'circle';
        }
    }
}

// =========================================================
// WAKTU SALAM
// =========================================================
$jam = (int)date('H');
if ($jam < 11)      $salam = 'Selamat Pagi';
elseif ($jam < 15)  $salam = 'Selamat Siang';
elseif ($jam < 19)  $salam = 'Selamat Sore';
else                $salam = 'Selamat Malam';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

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

            <form action="katalog.php" method="GET" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <div class="flex items-center gap-3">
                <a href="cart.php" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>
                <div class="relative">
                    <button onclick="toggleProfileMenu()" class="flex items-center gap-3 pl-1 focus:outline-none">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($nama_user) ?></p>
                            <p class="text-xs text-stone-500">Pelanggan</p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                    </button>
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-stone-100 p-2 space-y-1 z-50">
                        <a href="profil.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                            <i data-lucide="user" class="w-4 h-4 text-stone-500"></i> Profil Saya
                        </a>
                        <a href="riwayat.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                            <i data-lucide="history" class="w-4 h-4 text-stone-500"></i> Riwayat Saya
                        </a>
                        <a href="chat.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl">
                            <i data-lucide="message-square" class="w-4 h-4 text-stone-500"></i> Konsultasi
                        </a>
                        <hr class="border-stone-100 my-1">
                        <a href="../Auth/logout.php" onclick="return confirm('Keluar dari akun?');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl">
                            <i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => true],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => false],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => false],
                        ['url' => 'chat.php',      'icon' => 'message-square', 'label' => 'Konsultasi',     'active' => false],
                        ['url' => 'profil.php',    'icon' => 'user',           'label' => 'Profil Saya',    'active' => false],
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
                            <?php if ($m['url'] === 'chat.php' && $unreadChat > 0): ?>
                                <span class="bg-[#D97706] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?= $unreadChat ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-auto">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight">Troli Belanja</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5"><?= $cart_count ?> item siap checkout</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <!-- WELCOME -->
            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight"><?= $salam ?>, <?= htmlspecialchars($nama_user) ?> 👋</h1>
                <p class="text-sm text-stone-500 mt-0.5">Ringkasan aktivitas belanja dan pesanan Anda di PlantHub.</p>
            </div>

            <!-- BANNER: LANJUTKAN BELANJA (kalau cart tidak kosong) -->
            <?php if ($cart_count > 0): ?>
                <div class="bg-gradient-to-r from-[#2E7D32] to-emerald-600 rounded-2xl p-5 sm:p-6 text-white shadow-md flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm flex items-center justify-center shrink-0">
                            <i data-lucide="shopping-bag" class="w-7 h-7"></i>
                        </div>
                        <div>
                            <p class="text-base font-bold">Keranjang belanja Anda menunggu!</p>
                            <p class="text-xs text-emerald-100 mt-0.5">
                                Ada <b class="text-white"><?= $cart_count ?> item</b> yang belum di-checkout. Yuk selesaikan pesanan Anda.
                            </p>
                        </div>
                    </div>
                    <a href="cart.php" class="inline-flex items-center gap-2 bg-white text-[#2E7D32] hover:bg-emerald-50 font-bold px-5 py-3 rounded-xl text-sm transition shadow-sm shrink-0">
                        <span>Lihat Keranjang</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            <?php endif; ?>

            <!-- BANNER: CHAT BELUM DIBACA -->
            <?php if ($unreadChat > 0): ?>
                <div class="bg-blue-50 border-2 border-blue-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <i data-lucide="message-square" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-blue-900">Admin membalas chat Anda!</p>
                            <p class="text-xs text-blue-700 mt-0.5">Anda punya <b><?= $unreadChat ?></b> pesan belum dibaca dari admin.</p>
                        </div>
                    </div>
                    <a href="chat.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition shrink-0">
                        <span>Buka Chat</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            <?php endif; ?>

            <!-- 3 STAT CARD -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="cart.php" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Troli Belanja</p>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                                <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2"><?= $cart_count ?> Item</p>
                    </div>
                    <div class="pt-2 text-xs">
                        <span class="inline-flex items-center gap-1 font-semibold text-[#2E7D32] hover:underline">
                            Lihat Keranjang <i data-lucide="chevron-right" class="w-3 h-3"></i>
                        </span>
                    </div>
                </a>

                <a href="riwayat.php?status_filter=Diproses" class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#D97706] flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Pesanan Aktif</p>
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-[#D97706] flex items-center justify-center">
                                <i data-lucide="truck" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2"><?= $pending_orders ?> Pesanan</p>
                    </div>
                    <div class="pt-2 text-xs">
                        <span class="inline-flex items-center gap-1 font-semibold text-[#D97706] hover:underline">
                            Lacak Status <i data-lucide="chevron-right" class="w-3 h-3"></i>
                        </span>
                    </div>
                </a>

                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32] flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Belanja</p>
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#2E7D32] flex items-center justify-center">
                                <i data-lucide="wallet" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-stone-800 tracking-tight mb-2">Rp <?= number_format($total_belanja, 0, ',', '.') ?></p>
                    </div>
                    <div class="pt-2 text-xs">
                        <span class="font-medium text-stone-400"><?= $total_orders ?> transaksi total</span>
                    </div>
                </div>
            </div>

            <!-- PESANAN TERAKHIR -->
            <?php if ($lastOrder): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-600 flex items-center justify-center">
                                <i data-lucide="package" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-stone-800">Pesanan Terakhir</h2>
                                <p class="text-[11px] text-stone-500 mt-0.5">Status terkini pesanan terbaru Anda</p>
                            </div>
                        </div>
                        <a href="riwayat.php" class="text-xs font-semibold text-[#2E7D32] hover:underline inline-flex items-center gap-1">
                            Lihat Semua <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex-1 space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-mono text-sm font-bold text-stone-800"><?= htmlspecialchars($lastOrder['kode_transaksi']) ?></p>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border <?= badgeStatusDash($lastOrder['status']) ?>">
                                    <i data-lucide="<?= iconStatusDash($lastOrder['status']) ?>" class="w-3 h-3"></i>
                                    <?= htmlspecialchars($lastOrder['status']) ?>
                                </span>
                            </div>
                            <p class="text-xs text-stone-500">
                                <i data-lucide="calendar" class="w-3 h-3 inline-block mr-1"></i>
                                <?= date('d M Y, H:i', strtotime($lastOrder['created_at'])) ?> WIB
                            </p>
                            <p class="text-xs text-stone-500">
                                <i data-lucide="package" class="w-3 h-3 inline-block mr-1"></i>
                                <?= (int)$lastOrder['jml_item'] ?> produk &middot; <?= (int)$lastOrder['total_qty'] ?> item
                            </p>
                            <p class="text-xs text-stone-500">
                                <i data-lucide="map-pin" class="w-3 h-3 inline-block mr-1"></i>
                                <?= htmlspecialchars(mb_strimwidth($lastOrder['alamat'] ?? '-', 0, 60, '...')) ?>
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-5 sm:border-l sm:border-stone-100 sm:pl-5 shrink-0">
                            <div class="text-left sm:text-right">
                                <p class="text-[10px] font-bold uppercase text-stone-400 tracking-wider">Total Bayar</p>
                                <p class="text-lg font-bold text-[#2E7D32]">Rp <?= number_format($lastOrder['total_harga'], 0, ',', '.') ?></p>
                            </div>
                            <a href="riwayat.php" class="inline-flex items-center justify-center gap-1.5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition">
                                <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                Lacak Pesanan
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- PRODUK TERBARU -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3 mb-4">
                    <div>
                        <h2 class="text-base font-bold text-stone-800">Produk Terbaru</h2>
                        <p class="text-xs text-stone-500 mt-0.5">Koleksi tanaman terbaru yang tersedia di katalog.</p>
                    </div>
                    <a href="katalog.php" class="text-xs font-semibold text-[#2E7D32] hover:underline flex items-center gap-1">
                        Lihat Semua <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>

                <?php if (empty($produk_terbaru)): ?>
                    <div class="py-10 text-center text-sm text-stone-400">Belum ada produk tersedia.</div>
                <?php else: ?>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php foreach ($produk_terbaru as $p):
                            $imgSrc = (!empty($p['gambar']) && $p['gambar'] !== 'default.jpg' && file_exists("../assets/img/" . $p['gambar']))
                                ? "../assets/img/" . $p['gambar']
                                : "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400";
                        ?>
                            <a href="detail.php?id=<?= (int)$p['id'] ?>" class="border border-stone-200/80 rounded-xl overflow-hidden hover:shadow-md transition-shadow group">
                                <div class="bg-stone-100 h-32 overflow-hidden">
                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($p['nama_tanaman']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <div class="p-3">
                                    <p class="text-[10px] font-semibold uppercase text-stone-400"><?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?></p>
                                    <p class="text-xs font-semibold text-stone-800 mt-0.5 truncate"><?= htmlspecialchars($p['nama_tanaman']) ?></p>
                                    <p class="text-sm font-bold text-[#2E7D32] mt-1">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
        function toggleProfileMenu() {
            document.getElementById('profileDropdown')?.classList.toggle('hidden');
        }
        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }
        document.addEventListener('click', (e) => {
            const dd = document.getElementById('profileDropdown');
            if (!e.target.closest('#profileDropdown') && !e.target.closest('button[onclick="toggleProfileMenu()"]')) {
                dd?.classList.add('hidden');
            }
        });
    </script>
</body>
</html>