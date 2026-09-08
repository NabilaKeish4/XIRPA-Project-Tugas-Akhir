<?php
session_start();
require_once '../Config/database.php';

$id_user = (int)($_SESSION['id_user'] ?? 1);
$query = "SELECT * FROM transaksi WHERE id_user = '$id_user' ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - PlantShop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#63B745', 600: '#529E38', light: '#E2F2DC' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex min-h-screen">

    <!-- Sidebar Navbar -->
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col justify-between h-screen sticky top-0 shrink-0 z-50 p-6">
        <div>
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900 block mb-10">PlantShop</a>
           <nav class="flex flex-col space-y-4 text-xs font-semibold uppercase tracking-wider text-gray-600">
    <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">HOME</a>
    <a href="dashboard.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Shop</a>
    <a href="riwayat.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
        <span>Riwayat</span>
        <span class="w-1.5 h-1.5 bg-brand-500 rounded-full"></span>
    </a>
    <a href="cart.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Keranjang</a>
</nav>
        </div>
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Kembali ke Dashboard</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto my-4">
            <h1 class="text-2xl font-black uppercase text-gray-900 mb-6">Riwayat Pesanan Anda</h1>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-400 uppercase tracking-wider border-b border-gray-100">
                            <th class="p-4">ID Transaksi</th>
                            <th class="p-4">Tanggal</th>
                            <th class="p-4">Total</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="p-4 font-bold text-gray-900">#<?= (int)$row['id_transaksi'] ?></td>
                                <td class="p-4"><?= htmlspecialchars($row['tanggal']) ?></td>
                                <td class="p-4 font-bold text-gray-900">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $row['status'] == 'Pending' ? 'bg-yellow-50 text-yellow-600' : 'bg-green-50 text-green-600' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <a href="nota.php?id=<?= (int)$row['id_transaksi'] ?>" class="text-brand-500 font-bold hover:underline">Lihat Nota</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-400">Belum ada riwayat transaksi.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>