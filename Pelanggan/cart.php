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

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// FUNGSI BANTU
function tambahKeKeranjang($conn, $id, $qty = 1) {
    $id  = (int)$id;
    $qty = max(1, (int)$qty);
    if ($id <= 0) return 'invalid';

    $qCheck = mysqli_query($conn, "SELECT stok FROM produk WHERE id = $id LIMIT 1");
    if (!$qCheck || mysqli_num_rows($qCheck) === 0) return 'notfound';

    $row = mysqli_fetch_assoc($qCheck);
    $stokTersedia = (int)$row['stok'];
    $qtySekarang  = isset($_SESSION['cart'][$id]) ? (int)$_SESSION['cart'][$id] : 0;
    $qtyTotal     = $qtySekarang + $qty;

    if ($stokTersedia <= 0) return 'overstock';
    if ($qtyTotal > $stokTersedia) {
        $_SESSION['cart'][$id] = $stokTersedia;
        return 'overstock';
    }
    $_SESSION['cart'][$id] = $qtyTotal;
    return 'added';
}

// HANDLE AKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $id  = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    $res = tambahKeKeranjang($conn, $id, $qty);
    if ($res === 'added')     { header("Location: cart.php?msg=added"); exit; }
    if ($res === 'overstock') { header("Location: cart.php?msg=overstock"); exit; }
    if ($res === 'notfound')  { header("Location: cart.php?msg=notfound"); exit; }
    header("Location: cart.php");
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id     = (int)($_GET['id'] ?? 0);

    if ($action === 'add' && $id > 0) {
        $res = tambahKeKeranjang($conn, $id, 1);
        if ($res === 'overstock') { header("Location: cart.php?msg=overstock"); exit; }
        if ($res === 'notfound')  { header("Location: cart.php?msg=notfound"); exit; }
        header("Location: cart.php?msg=added");
        exit;
    }

    if ($action === 'increase' && $id > 0) {
        $res = tambahKeKeranjang($conn, $id, 1);
        if ($res === 'overstock') { header("Location: cart.php?msg=overstock"); exit; }
        header("Location: cart.php");
        exit;
    }

    if ($action === 'decrease' && $id > 0) {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] = (int)$_SESSION['cart'][$id] - 1;
            if ($_SESSION['cart'][$id] <= 0) unset($_SESSION['cart'][$id]);
        }
        header("Location: cart.php");
        exit;
    }

    if ($action === 'remove' && $id > 0) {
        unset($_SESSION['cart'][$id]);
        header("Location: cart.php?msg=removed");
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header("Location: cart.php?msg=cleared");
        exit;
    }
}

// AMBIL DETAIL PRODUK
$cart_items = $_SESSION['cart'];
$items      = [];
$subtotal   = 0;
$cart_count = 0;

if (!empty($cart_items)) {
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

                $qty    = (int)$cart_items[$pid];
                $harga  = (float)$row['harga_jual'];
                $sub    = $harga * $qty;

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
}

$ppn   = $subtotal * 0.11;
$total = $subtotal + $ppn;

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Keranjang Belanja</title>
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
                <a href="profil.php" class="flex items-center gap-3 pl-1">
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
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => true],
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

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Keranjang Belanja</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Tinjau item yang akan Anda beli sebelum checkout.</p>
                </div>
                <?php if (!empty($items)): ?>
                    <a href="cart.php?action=clear" onclick="return confirm('Kosongkan seluruh keranjang?')" class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 px-3 py-2 rounded-xl transition">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Kosongkan Keranjang
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($msg === 'added'): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32]"></i>
                    <span>Produk berhasil ditambahkan ke keranjang.</span>
                </div>
            <?php elseif ($msg === 'overstock'): ?>
                <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-[#D97706]"></i>
                    <span>Jumlah melebihi stok yang tersedia. Silakan kurangi kuantitas.</span>
                </div>
            <?php elseif ($msg === 'removed'): ?>
                <div class="p-4 bg-stone-50 border border-stone-200 text-stone-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="info" class="w-5 h-5 text-stone-500"></i>
                    <span>Item telah dihapus dari keranjang.</span>
                </div>
            <?php elseif ($msg === 'cleared'): ?>
                <div class="p-4 bg-stone-50 border border-stone-200 text-stone-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="info" class="w-5 h-5 text-stone-500"></i>
                    <span>Keranjang telah dikosongkan.</span>
                </div>
            <?php elseif ($msg === 'notfound'): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="x-circle" class="w-5 h-5 text-rose-500"></i>
                    <span>Produk tidak ditemukan.</span>
                </div>
            <?php endif; ?>

            <?php if (empty($items)): ?>
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-12 text-center">
                    <div class="w-16 h-16 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-4 text-stone-400">
                        <i data-lucide="shopping-cart" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-base font-bold text-stone-800">Keranjang masih kosong</h3>
                    <p class="text-sm text-stone-500 mt-1">Tambahkan tanaman dari katalog untuk memulai belanja.</p>
                    <a href="katalog.php" class="inline-flex items-center gap-2 mt-5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                        <i data-lucide="store" class="w-4 h-4"></i>
                        <span>Buka Katalog</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-stone-100">
                            <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Item di Keranjang</h2>
                        </div>

                        <div class="divide-y divide-stone-100">
                            <?php foreach ($items as $item): ?>
                                <div class="p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                                    <img src="<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>" class="w-20 h-20 rounded-xl object-cover bg-stone-100 shrink-0">

                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-bold uppercase text-stone-400 tracking-wider"><?= htmlspecialchars($item['kategori']) ?></p>
                                        <h3 class="text-sm font-bold text-stone-800 mt-0.5 truncate"><?= htmlspecialchars($item['nama']) ?></h3>
                                        <p class="text-xs text-stone-500 mt-1">Rp <?= number_format($item['harga'], 0, ',', '.') ?> &middot; Stok tersedia: <?= $item['stok'] ?></p>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-4 shrink-0">
                                        <div class="flex items-center border border-stone-200 rounded-xl overflow-hidden bg-stone-50">
                                            <a href="cart.php?action=decrease&id=<?= $item['id'] ?>" class="px-3 py-2 text-stone-600 hover:bg-stone-100 font-bold text-sm">-</a>
                                            <span class="px-3 py-2 text-sm font-bold text-stone-800 bg-white min-w-[40px] text-center"><?= $item['qty'] ?></span>
                                            <a href="cart.php?action=increase&id=<?= $item['id'] ?>" class="px-3 py-2 text-stone-600 hover:bg-stone-100 font-bold text-sm">+</a>
                                        </div>

                                        <div class="text-right min-w-[100px]">
                                            <p class="text-sm font-bold text-[#2E7D32]">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></p>
                                            <a href="cart.php?action=remove&id=<?= $item['id'] ?>" onclick="return confirm('Hapus item ini?')" class="inline-flex items-center gap-1 text-[11px] text-stone-400 hover:text-rose-600 mt-0.5">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i> Hapus
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="px-6 py-4 bg-stone-50/60 border-t border-stone-100">
                            <a href="katalog.php" class="inline-flex items-center gap-2 text-xs font-semibold text-[#2E7D32] hover:underline">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Lanjut Belanja
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4 sticky top-24">
                            <h2 class="text-sm font-bold text-stone-800 uppercase tracking-wider border-b border-stone-100 pb-3">Ringkasan Belanja</h2>

                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between text-stone-600">
                                    <span>Subtotal (<?= $cart_count ?> item)</span>
                                    <span class="font-semibold">Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                                </div>
                                <div class="flex justify-between text-stone-600">
                                    <span>PPN 11%</span>
                                    <span class="font-semibold">Rp <?= number_format($ppn, 0, ',', '.') ?></span>
                                </div>
                                <div class="pt-3 border-t border-stone-200 flex justify-between items-baseline">
                                    <span class="text-sm font-bold text-stone-800">Total Bayar</span>
                                    <span class="text-xl font-bold text-[#2E7D32]">Rp <?= number_format($total, 0, ',', '.') ?></span>
                                </div>
                            </div>

                            <a href="checkout.php" class="w-full inline-flex items-center justify-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-3 rounded-xl text-sm transition shadow-sm">
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                <span>Lanjut ke Checkout</span>
                            </a>
                        </div>
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
    </script>
</body>
</html>