<?php
session_start();
require_once '../Config/database.php';

$user_id = $_SESSION['user_id'] ?? null;
$nama_user = $_SESSION['nama_user'] ?? 'Pelanggan';

// Query data transaksi
$query = "SELECT t.*, COUNT(dt.id) as total_item 
          FROM transaksi t 
          LEFT JOIN transaksi_detail dt ON t.id = dt.transaksi_id 
          WHERE t.user_id = '$user_id' 
          GROUP BY t.id 
          ORDER BY t.created_at DESC";

$result = false;
if ($user_id && isset($conn)) {
    $result = mysqli_query($conn, $query);
}

$ordersData = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $ordersData[] = $row;
    }
}

// Fallback Dummy Data jika database kosong/belum terkoneksi
if (empty($ordersData)) {
    $ordersData = [
        [
            'id' => 'TRX-98214',
            'created_at' => '2026-03-01 10:30:00',
            'total' => 170000,
            'status' => 'Selesai',
            'total_item' => 2
        ],
        [
            'id' => 'TRX-98105',
            'created_at' => '2026-02-24 14:15:00',
            'total' => 85000,
            'status' => 'Diproses',
            'total_item' => 1
        ]
    ];
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Riwayat Order</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts: Plus Jakarta Sans -->
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
        body {
            background-color: #F4F6F3;
            color: #1E291E;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="antialiased min-h-screen flex p-4 lg:p-6 gap-6 text-stone-800">

    <!-- FLOATING SIDEBAR -->
    <aside class="w-64 bg-white rounded-2xl p-5 flex flex-col justify-between h-[calc(100vh-3rem)] sticky top-6 shrink-0 shadow-sm border border-stone-100/80 z-50">
        <div>
            <!-- LOGO PLANTHUB -->
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-8 px-1">
                <div class="w-9 h-9 rounded-xl bg-planthub-green flex items-center justify-center text-white shadow-sm shadow-emerald-950/20">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight text-planthub-dark leading-none">Plant<span class="text-planthub-green">Hub</span></h1>
                    <p class="text-[9px] font-extrabold tracking-widest text-stone-400 uppercase mt-0.5">STORE PORTAL</p>
                </div>
            </a>

            <!-- NAVIGASI SIDEBAR -->
            <nav class="flex flex-col space-y-1 text-xs font-medium text-stone-600">
                <a href="dashboard.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i> Beranda
                </a>
                <a href="katalog.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="store" class="w-4 h-4"></i> Katalog Shop
                </a>
                <a href="cart.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center justify-between transition">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag" class="w-4 h-4"></i> Keranjang</span>
                    <span class="text-stone-400 text-[11px] font-semibold">(<?= $cart_count ?>)</span>
                </a>
                <a href="riwayat.php" class="bg-planthub-green text-white font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5"><i data-lucide="history" class="w-4 h-4"></i> Riwayat Order</span>
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </a>
                <a href="chat.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="message-square" class="w-4 h-4"></i> Konsultasi
                </a>
            </nav>
        </div>

        <!-- BOTTOM WIDGET & LOGOUT -->
        <div class="space-y-3 pt-3 border-t border-stone-100">
            <!-- TROLI BELANJA WIDGET -->
            <div class="bg-stone-50 p-3 rounded-xl flex items-center justify-between border border-stone-100">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-stone-500"></i>
                    <span class="text-xs font-semibold text-stone-700">Troli Belanja</span>
                </div>
                <span class="w-5 h-5 rounded-full bg-planthub-green text-white text-[10px] font-bold flex items-center justify-center">
                    <?= $cart_count ?>
                </span>
            </div>

            <!-- LOGOUT BUTTON -->
            <a href="../Auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center justify-center gap-1.5 text-xs font-bold text-red-500 hover:text-red-600 py-1 transition">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar Sesi
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA RIWAYAT -->
    <main class="flex-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-100/80 flex flex-col h-[calc(100vh-3rem)] overflow-y-auto custom-scrollbar">

        <!-- HEADER TOP BAR -->
        <header class="flex items-center justify-between pb-5 border-b border-stone-100 mb-6">
            <div>
                <h2 class="text-xl font-bold text-stone-900 flex items-center gap-2">
                    <i data-lucide="history" class="w-5 h-5 text-planthub-green"></i>
                    <span>Riwayat Transaksi</span>
                </h2>
                <p class="text-xs text-stone-400 mt-0.5">Pantau status pemesanan dan riwayat pembelian tanaman hiasmu.</p>
            </div>

            <a href="cart.php" class="w-9 h-9 rounded-xl border border-stone-200/80 flex items-center justify-center text-stone-600 hover:bg-stone-50 transition shadow-sm relative">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-planthub-green text-white text-[8px] font-bold rounded-full flex items-center justify-center">
                        <?= $cart_count ?>
                    </span>
                <?php endif; ?>
            </a>
        </header>

        <!-- TABLE RIWAYAT ORDER -->
        <div class="flex-1 space-y-4">
            <div class="bg-white rounded-2xl border border-stone-100 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-stone-50/80 border-b border-stone-100 text-[11px] font-bold text-stone-400 uppercase tracking-wider">
                                <th class="p-4">Kode Transaksi</th>
                                <th class="p-4">Tanggal Order</th>
                                <th class="p-4">Jumlah Item</th>
                                <th class="p-4">Total Bayar</th>
                                <th class="p-4">Status Pesanan</th>
                                <th class="p-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs font-semibold text-stone-700">
                            <?php foreach ($ordersData as $order): 
                                $status = strtolower($order['status'] ?? 'selesai');
                                $badgeClass = 'bg-emerald-100 text-emerald-800';
                                if ($status === 'diproses' || $status === 'pending') {
                                    $badgeClass = 'bg-amber-100 text-amber-800';
                                } elseif ($status === 'batal' || $status === 'dibatalkan') {
                                    $badgeClass = 'bg-rose-100 text-rose-800';
                                }
                            ?>
                                <tr class="hover:bg-stone-50/50 transition">
                                    <td class="p-4 font-bold text-stone-900">#<?= htmlspecialchars($order['id']) ?></td>
                                    <td class="p-4 text-stone-500 font-normal"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                                    <td class="p-4 text-stone-600"><?= $order['total_item'] ?> Item</td>
                                    <td class="p-4 font-extrabold text-planthub-green">
                                        Rp <?= number_format($order['total'] ?? $order['total_harga'] ?? 0, 0, ',', '.') ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide <?= $badgeClass ?>">
                                            <?= htmlspecialchars($order['status'] ?? 'Selesai') ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="detail_order.php?id=<?= $order['id'] ?>" class="inline-flex items-center gap-1 text-[11px] font-bold text-planthub-green hover:underline">
                                            <span>Detail</span>
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="mt-8 pt-4 border-t border-stone-100 text-center text-[11px] text-stone-400 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>© 2026 PlantHub Store Portal. All rights reserved.</p>
            <div class="flex items-center gap-3">
                <a href="#" class="hover:underline">Privasi</a>
                <span>•</span>
                <a href="#" class="hover:underline">Bantuan</a>
            </div>
        </footer>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>