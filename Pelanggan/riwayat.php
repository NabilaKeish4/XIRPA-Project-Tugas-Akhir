<?php
session_start();
require_once '../Config/database.php';

$user_id = $_SESSION['user_id'] ?? 1;

// Perbaikan: Ubah t.pelanggan_id menjadi t.user_id
$query = "SELECT t.*, COUNT(dt.id) as total_item 
          FROM transaksi t 
          LEFT JOIN transaksi_detail dt ON t.id = dt.transaksi_id 
          WHERE t.user_id = '$user_id' 
          GROUP BY t.id 
          ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $query);

$ordersData = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $ordersData[] = $row;
    }
}
$ordersData = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $ordersData[] = $row;
    }
}

// Fallback Dummy Data
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
    <title>PlantShop - Riwayat Order</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { primary: '#2E7D32', 'primary-light': '#E8F5E9' } }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F9F8F6] flex min-h-screen font-['Plus_Jakarta_Sans'] text-stone-800">

    <aside class="w-64 bg-white border-r border-stone-200 flex flex-col justify-between h-screen sticky top-0 shrink-0 p-6 z-50">
        <div>
<<<<<<< HEAD
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
            <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
                <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">HOME</a>
                <a href="dashboard.php#katalog" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Shop</a>
                <a href="riwayat.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>Riwayat</span>
                    <span class="w-1.5 h-1.5 bg-brand-500 rounded-full"></span>
=======
            <a href="dashboard.php" class="flex items-center gap-2.5 mb-10">
                <div class="w-9 h-9 rounded-xl bg-brand-primary flex items-center justify-center text-white"><i data-lucide="sprout"></i></div>
                <span class="text-xl font-extrabold text-stone-800">Plant<span class="text-brand-primary">Shop</span></span>
            </a>
            <nav class="flex flex-col space-y-2 text-xs font-bold uppercase text-stone-500">
                <a href="dashboard.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="layout-dashboard"></i> Beranda</a>
                <a href="katalog.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="store"></i> Katalog Shop</a>
                <a href="cart.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center justify-between">
                    <span class="flex items-center gap-2.5"><i data-lucide="shopping-bag"></i> Keranjang</span>
                    <span class="bg-brand-primary text-white text-[10px] px-2 py-0.5 rounded-full font-bold"><?= $cart_count ?></span>
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
                </a>
                <a href="riwayat.php" class="text-brand-primary font-extrabold bg-brand-primary-light px-4 py-3 rounded-xl flex items-center justify-between">
                    <span class="flex items-center gap-2.5"><i data-lucide="history"></i> Riwayat Order</span>
                    <span class="w-2 h-2 bg-brand-primary rounded-full"></span>
                </a>
                <a href="chat.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="message-square"></i> Konsultasi</a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 p-8 max-w-5xl overflow-y-auto">
        <h1 class="text-2xl font-black text-stone-900 mb-6 uppercase flex items-center gap-2">
            <i data-lucide="history" class="text-brand-primary"></i> Riwayat Transaksi Anda
        </h1>

        <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200 text-[11px] font-bold text-stone-400 uppercase">
                        <th class="p-4">Kode Transaksi</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Jumlah Item</th>
                        <th class="p-4">Total Bayar</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 text-xs font-semibold">
                    <?php foreach ($ordersData as $order): ?>
                        <tr class="hover:bg-stone-50/50 transition">
                            <td class="p-4 font-extrabold text-stone-800">#<?= htmlspecialchars($order['id']) ?></td>
                            <td class="p-4 text-stone-500"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></td>
                            <td class="p-4 text-stone-600"><?= $order['total_item'] ?> produk</td>
                            <td class="p-4 font-bold text-brand-primary">Rp <?= number_format($order['total'] ?? $order['total_harga'] ?? 0, 0, ',', '.') ?></td>
                            <td class="p-4">
                                <span class="bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase"><?= htmlspecialchars($order['status'] ?? 'Selesai') ?></span>
                            </td>
                            <td class="p-4 text-center">
                                <a href="detail_order.php?id=<?= $order['id'] ?>" class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-primary hover:underline">
                                    <span>Detail</span> <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>