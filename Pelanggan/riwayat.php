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
    <title>Riwayat Transaksi - TPLANT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 500: '#63B745', 600: '#529E38' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">
    <header class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="dashboard.php" class="text-2xl font-black tracking-wider text-gray-900">TPLANT</a>
            <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase">&larr; Kembali ke Dashboard</a>
        </div>
    </header>

    <div class="max-w-4xl mx-auto p-6 my-8">
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
</body>
</html>