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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: katalog.php");
    exit;
}

$qProduk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.id = $id LIMIT 1
");

if (!$qProduk || mysqli_num_rows($qProduk) === 0) {
    header("Location: katalog.php");
    exit;
}

$p = mysqli_fetch_assoc($qProduk);
$stok = (int)$p['stok'];
$isHabis = $stok <= 0;

$imgSrc = (!empty($p['gambar']) && $p['gambar'] !== 'default.jpg' && file_exists("../assets/img/" . $p['gambar']))
    ? "../assets/img/" . $p['gambar']
    : "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=600";

// Produk terkait
$produkTerkait = [];
$qRelated = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.kategori_id = " . (int)$p['kategori_id'] . " 
      AND p.id != $id 
      AND p.stok > 0
    ORDER BY RAND() LIMIT 4
");
if ($qRelated) while ($r = mysqli_fetch_assoc($qRelated)) $produkTerkait[] = $r;

$alert = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['nama_tanaman']) ?> - PlantHub</title>
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

            <nav class="flex items-center gap-1.5 text-xs text-stone-500">
                <a href="dashboard.php" class="hover:text-[#2E7D32]">Beranda</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="katalog.php" class="hover:text-[#2E7D32]">Katalog</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-stone-800 font-semibold truncate"><?= htmlspecialchars($p['nama_tanaman']) ?></span>
            </nav>

            <?php if ($alert === 'added'): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32]"></i>
                    <span>Produk berhasil ditambahkan ke keranjang.</span>
                </div>
            <?php elseif ($alert === 'overstock'): ?>
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-[#D97706]"></i>
                    <span>Jumlah melebihi stok yang tersedia.</span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                    <div class="bg-stone-100 aspect-square md:aspect-auto md:min-h-[420px] overflow-hidden">
                        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($p['nama_tanaman']) ?>" class="w-full h-full object-cover">
                    </div>

                    <div class="p-6 lg:p-8 flex flex-col">
                        <p class="text-[11px] font-bold uppercase text-stone-400 tracking-wider"><?= htmlspecialchars($p['nama_kategori'] ?? 'Tanaman') ?></p>
                        <h1 class="text-2xl font-bold text-stone-800 tracking-tight mt-1"><?= htmlspecialchars($p['nama_tanaman']) ?></h1>

                        <div class="mt-3 flex items-center gap-3 text-xs">
                            <?php if ($isHabis): ?>
                                <span class="inline-flex items-center gap-1 font-semibold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-md border border-rose-100">
                                    <i data-lucide="x-circle" class="w-3 h-3"></i> Stok Habis
                                </span>
                            <?php elseif ($stok <= (int)$p['stok_minimal']): ?>
                                <span class="inline-flex items-center gap-1 font-semibold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-100">
                                    <i data-lucide="alert-triangle" class="w-3 h-3"></i> Stok Terbatas
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-100">
                                    <i data-lucide="check-circle" class="w-3 h-3"></i> Tersedia
                                </span>
                            <?php endif; ?>
                            <span class="text-stone-500">Sisa stok: <b class="text-stone-800"><?= $stok ?> unit</b></span>
                        </div>

                        <div class="mt-5 pb-5 border-b border-stone-100">
                            <p class="text-3xl font-bold text-[#2E7D32] tracking-tight">Rp <?= number_format($p['harga_jual'], 0, ',', '.') ?></p>
                        </div>

                        <?php if (!empty($p['deskripsi'])): ?>
                            <div class="mt-5">
                                <h3 class="text-xs font-bold uppercase text-stone-500 tracking-wider mb-2">Deskripsi</h3>
                                <p class="text-sm text-stone-700 leading-relaxed"><?= nl2br(htmlspecialchars($p['deskripsi'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($p['cara_perawatan'])): ?>
                            <div class="mt-5">
                                <h3 class="text-xs font-bold uppercase text-stone-500 tracking-wider mb-2">Cara Perawatan</h3>
                                <p class="text-sm text-stone-700 leading-relaxed"><?= nl2br(htmlspecialchars($p['cara_perawatan'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="mt-6 pt-5 border-t border-stone-100">
                            <?php if ($isHabis): ?>
                                <button disabled class="w-full bg-stone-200 text-stone-400 font-semibold py-3 rounded-xl text-sm cursor-not-allowed">
                                    Stok Habis — Tidak Dapat Dibeli
                                </button>
                            <?php else: ?>
                                <form action="cart.php" method="POST" class="flex flex-col sm:flex-row gap-3">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                                    <div class="flex items-center border border-stone-200 rounded-xl overflow-hidden bg-stone-50">
                                        <button type="button" onclick="ubahQty(-1)" class="px-4 py-3 text-stone-600 hover:bg-stone-100 font-bold">-</button>
                                        <input type="number" name="qty" id="qtyInput" value="1" min="1" max="<?= $stok ?>" class="w-16 py-3 text-center text-sm font-bold bg-white border-0 focus:outline-none">
                                        <button type="button" onclick="ubahQty(1)" class="px-4 py-3 text-stone-600 hover:bg-stone-100 font-bold">+</button>
                                    </div>

                                    <button type="submit" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 rounded-xl text-sm transition flex items-center justify-center gap-2">
                                        <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                                        <span>Tambah ke Keranjang</span>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="chat.php" class="mt-3 w-full border border-stone-200 text-stone-700 hover:bg-stone-50 font-semibold py-3 rounded-xl text-sm transition flex items-center justify-center gap-2">
                                <i data-lucide="message-square" class="w-4 h-4 text-stone-500"></i>
                                <span>Tanya Admin tentang Tanaman Ini</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($produkTerkait)): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6">
                    <div class="flex items-center justify-between border-b border-stone-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-base font-bold text-stone-800">Produk Serupa</h2>
                            <p class="text-xs text-stone-500 mt-0.5">Produk lain dengan kategori sama.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php foreach ($produkTerkait as $r):
                            $rImg = (!empty($r['gambar']) && $r['gambar'] !== 'default.jpg' && file_exists("../assets/img/" . $r['gambar']))
                                ? "../assets/img/" . $r['gambar']
                                : "https://images.unsplash.com/photo-1614594975525-e45190c55d0b?auto=format&fit=crop&q=80&w=400";
                        ?>
                            <a href="detail.php?id=<?= (int)$r['id'] ?>" class="border border-stone-200/80 rounded-xl overflow-hidden hover:shadow-md transition-shadow group">
                                <div class="bg-stone-100 h-32 overflow-hidden">
                                    <img src="<?= $rImg ?>" alt="<?= htmlspecialchars($r['nama_tanaman']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <div class="p-3">
                                    <p class="text-[10px] font-semibold uppercase text-stone-400"><?= htmlspecialchars($r['nama_kategori'] ?? 'Tanaman') ?></p>
                                    <p class="text-xs font-semibold text-stone-800 mt-0.5 truncate"><?= htmlspecialchars($r['nama_tanaman']) ?></p>
                                    <p class="text-sm font-bold text-[#2E7D32] mt-1">Rp <?= number_format($r['harga_jual'], 0, ',', '.') ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
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
        function ubahQty(delta) {
            const input = document.getElementById('qtyInput');
            const max = parseInt(input.getAttribute('max'));
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (val > max) val = max;
            input.value = val;
        }
    </script>
</body>
</html>