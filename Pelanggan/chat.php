<?php
session_start();
require_once '../Config/database.php';

$id_user = (int)($_SESSION['id_user'] ?? 1);
$produk_tanya = htmlspecialchars($_GET['produk'] ?? '');

// Menangani Pengiriman Pesan
if (isset($_POST['kirim_pesan'])) {
    $pesan = mysqli_real_escape_string($conn, $_POST['pesan']);
    if (!empty(trim($pesan))) {
        $tanggal = date('Y-m-d H:i:s');
        mysqli_query($conn, "INSERT INTO pesan (id_user, pengirim, pesan, tanggal) VALUES ('$id_user', 'user', '$pesan', '$tanggal')");
        header("Location: chat.php");
        exit;
    }
}

// Ambil Riwayat Chat
$query_chat = "SELECT * FROM pesan WHERE id_user = '$id_user' ORDER BY tanggal ASC";
$res_chat = mysqli_query($conn, $query_chat);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konsultasi Tanaman - PlantShop</title>
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
                <a href="chat.php" class="text-brand-500 font-bold bg-brand-light/40 px-4 py-3 rounded-xl flex items-center justify-between">
                    <span>Konsultasi</span>
                    <span class="w-1.5 h-1.5 bg-brand-500 rounded-full"></span>
                </a>
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50 flex items-center justify-between">
                    <span>Keranjang</span>
                    <span class="text-gray-400">(<?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?>)</span>
                </a>
            </nav>
        </div>
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Kembali ke Shop</a>
        </div>
    </aside>

    <!-- Main Content Chat -->
    <div class="flex-1 p-8 overflow-y-auto flex flex-col justify-between">
        <div class="max-w-3xl mx-auto w-full flex-1 flex flex-col">
            <div class="mb-6">
                <h1 class="text-2xl font-black uppercase text-gray-900">Konsultasi Tanaman 💬</h1>
                <p class="text-xs text-gray-400 mt-1">Tanyakan seputar kondisi tanaman, tips perawatan, atau ketersediaan stok ke admin.</p>
            </div>

            <!-- Box Chat -->
            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm flex-1 mb-4 flex flex-col justify-between min-h-[400px]">
                <div class="space-y-4 overflow-y-auto max-h-[450px] pr-2">
                    <!-- Default Welcome Chat -->
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-brand-500 text-white font-bold flex items-center justify-center text-xs shrink-0">A</div>
                        <div class="bg-gray-100 text-gray-700 p-3.5 rounded-2xl rounded-tl-none text-xs max-w-md">
                            Halo! Ada yang bisa kami bantu mengenai perawatan atau kondisi tanaman kami? 🌱
                        </div>
                    </div>

                    <?php if ($res_chat && mysqli_num_rows($res_chat) > 0): ?>
                        <?php while ($chat = mysqli_fetch_assoc($res_chat)): ?>
                            <?php if ($chat['pengirim'] === 'user'): ?>
                                <div class="flex items-end justify-end gap-2">
                                    <div class="bg-brand-500 text-white p-3.5 rounded-2xl rounded-tr-none text-xs max-w-md">
                                        <?= nl2br(htmlspecialchars($chat['pesan'])) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-brand-500 text-white font-bold flex items-center justify-center text-xs shrink-0">A</div>
                                    <div class="bg-gray-100 text-gray-700 p-3.5 rounded-2xl rounded-tl-none text-xs max-w-md">
                                        <?= nl2br(htmlspecialchars($chat['pesan'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <!-- Form Input Chat -->
                <form method="POST" class="mt-6 border-t border-gray-100 pt-4 flex gap-2">
                    <input type="text" name="pesan" value="<?= $produk_tanya ? "Halo admin, saya mau tanya tentang tanaman " . $produk_tanya : '' ?>" placeholder="Tulis pertanyaan Anda..." required class="flex-1 border p-3 rounded-xl text-xs focus:outline-none focus:border-brand-500">
                    <button type="submit" name="kirim_pesan" class="bg-brand-500 hover:bg-brand-600 text-white px-6 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition">Kirim</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>