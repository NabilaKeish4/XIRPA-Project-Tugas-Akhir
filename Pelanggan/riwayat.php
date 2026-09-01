<?php
session_start();
require_once '../Config/database.php';

$id_user = $_SESSION['id_user'] ?? 1;
$query = "SELECT * FROM transaksi WHERE id_user = '$id_user' ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-emerald-50 text-gray-800">
    <div class="container mx-auto p-6 max-w-4xl">
        <a href="dashboard.php" class="text-emerald-600 font-semibold mb-4 inline-block">&larr; Kembali ke Dashboard</a>
        <h1 class="text-2xl font-bold text-emerald-900 mb-6">Riwayat Pesanan Anda</h1>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-emerald-100 text-emerald-900">
                        <th class="p-4">ID Transaksi</th>
                        <th class="p-4">Tanggal</th>
                        <th class="p-4">Total</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="p-4 font-bold">#<?= $row['id_transaksi'] ?></td>
                            <td class="p-4"><?= $row['tanggal'] ?></td>
                            <td class="p-4 font-semibold">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded text-xs font-bold <?= $row['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <a href="nota.php?id=<?= $row['id_transaksi'] ?>" class="text-emerald-600 hover:underline">Lihat Nota</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500">Belum ada riwayat transaksi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>