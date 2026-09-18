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

$search   = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;

$where = "WHERE 1=1";
if ($search !== '') $where .= " AND p.nama_tanaman LIKE '%$search%'";
if ($kategori > 0)  $where .= " AND p.kategori_id = $kategori";

$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    $where
    ORDER BY p.stok DESC, p.id DESC
");

$kategoriList = [];
$qKat = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id ASC");
if ($qKat) while ($r = mysqli_fetch_assoc($qKat)) $kategoriList[] = $r;

function gambarKatalog($nama) {
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
    <title>PlantHub - Katalog Shop</title>
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

            <form method="GET" action="katalog.php" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] placeholder:text-stone-400">
            </form>

            <div class="flex items-center gap-3">
                <a href="cart.php" class="relative p-2 text-stone-600 hover:bg-stone-100 rounded-full">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="absolute top-1 right-1 w-4 h-4 bg-[#2E7D32] text-white text-[9px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
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
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => false],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => true],
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

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Katalog Produk</h1>
                <p class="text-sm text-stone-500 mt-0.5">Telusuri dan pilih tanaman yang Anda inginkan.</p>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-stone-200/80 shadow-sm space-y-3">
                <form method="GET" action="katalog.php" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama tanaman..." class="w-full pl-10 pr-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>
                    <?php if ($kategori > 0): ?>
                        <input type="hidden" name="kategori" value="<?= $kategori ?>">
                    <?php endif; ?>
                    <button type="submit" class="bg-[#2E7D32] hover:bg-emerald-800 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                        Cari
                    </button>
                </form>

                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <a href="katalog.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori === 0 ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                        Semua
                    </a>
                    <?php foreach ($kategoriList as $k): ?>
                        <a href="katalog.php?kategori=<?= (int)$k['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                           class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $kategori === (int)$k['id'] ? 'bg-[#2E7D32] text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' ?>">
                            <?= htmlspecialchars($k['nama_kategori']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!$qProduk || mysqli_num_rows($qProduk) === 0): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 p-12 text-center">
                    <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="package-search" class="w-6 h-6 text-stone-400"></i>
                    </div>
                    <p class="text-sm font-semibold text-stone-700">Produk tidak ditemukan</p>
                    <p class="text-xs text-stone-500 mt-1">Coba kata kunci lain atau reset filter.</p>
                    <a href="katalog.php" class="inline-block mt-4 text-xs font-semibold text-[#2E7D32] hover:underline">Reset pencarian</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php while ($p = mysqli_fetch_assoc($qProduk)):
                        $imgSrc = gambarKatalog($p['gambar']);
                        $stok = (int)$p['stok'];
                        $isHabis = $stok <= 0;
                        $isKritis = $stok > 0 && $stok <= (int)$p['stok_minimal'];
                    ?>
                        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden flex flex-col">
                            <div class="relative bg-stone-100 h-44 overflow-hidden">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($p['nama_tanaman']) ?>" class="w-full h-full object-cover">
                                <?php if ($isHabis): ?>
                                    <span class="absolute top-2.5 left-2.5 bg-rose-600 text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase">Stok Habis</span>
                                <?php elseif ($isKritis): ?>
                                    <span class="absolute top-2.5 left-2.5 bg-[#D97706] text-white text-[10px] font-bold px-2 py-1 rounded-md uppercase">Stok Terbatas</span>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 flex-1 flex flex-col">
                                <p class="text-[10px] font-semibold uppercase text-stone-400 tracking-wider"><?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?></p>
                                <h3 class="text-sm font-bold text-stone-800 mt-0.5 leading-snug line-clamp-2"><?= htmlspecialchars($p['nama_tanaman']) ?></h3>
                                <p class="text-base font-bold text-[#2E7D32] mt-2">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>
                                <p class="text-[11px] text-stone-500 mt-1">Stok: <?= $stok ?> unit</p>

                                <div class="mt-3 pt-3 border-t border-stone-100 grid grid-cols-2 gap-2">
                                    <a href="detail.php?id=<?= (int)$p['id'] ?>" class="text-center text-xs font-semibold text-stone-700 bg-stone-100 hover:bg-stone-200 py-2 rounded-lg transition">
                                        Detail
                                    </a>
                                    <?php if ($isHabis): ?>
                                        <button disabled class="text-center text-xs font-semibold text-stone-400 bg-stone-100 py-2 rounded-lg cursor-not-allowed">
                                            Habis
                                        </button>
                                    <?php else: ?>
                                        <a href="cart.php?action=add&id=<?= (int)$p['id'] ?>" class="text-center text-xs font-semibold text-white bg-[#2E7D32] hover:bg-emerald-800 py-2 rounded-lg transition">
                                            + Keranjang
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

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