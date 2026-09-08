<?php
session_start();
require_once '../Config/database.php';

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = mysqli_real_escape_string($conn, trim($_POST['message']));
    if (!empty($msg)) {
        $insertQuery = "INSERT INTO chats (user_id, sender_type, message, created_at) VALUES ('$user_id', 'customer', '$msg', NOW())";
        mysqli_query($conn, $insertQuery);
    }
    header('Location: chat.php');
    exit;
}

$chatQuery = "SELECT * FROM chats WHERE user_id = '$user_id' ORDER BY created_at ASC";
$chatResult = mysqli_query($conn, $chatQuery);

$chatMessages = [];
if ($chatResult && mysqli_num_rows($chatResult) > 0) {
    while ($row = mysqli_fetch_assoc($chatResult)) {
        $chatMessages[] = $row;
    }
}

if (empty($chatMessages)) {
    $chatMessages = [
        ['sender_type' => 'admin', 'message' => 'Halo! Selamat datang di PlantShop. Ada yang bisa kami bantu mengenai perawatan tanaman Anda?', 'created_at' => '10:00'],
        ['sender_type' => 'customer', 'message' => 'Halo min, Monstera saya daunnya agak menguning, kira-kira kenapa ya?', 'created_at' => '10:02'],
        ['sender_type' => 'admin', 'message' => 'Daun menguning biasanya karena overwatering (kelebihan air) atau kurang pencahayaan, Kak. Pastikan tanahnya kering sebelum disiram kembali ya!', 'created_at' => '10:05']
    ];
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PlantShop - Konsultasi Admin</title>
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
                <a href="riwayat.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50">Riwayat</a>
                <a href="cart.php" class="hover:text-brand-500 transition px-4 py-3 rounded-xl hover:bg-gray-50 flex items-center justify-between">
                    <span>Keranjang</span>
                    <span class="text-gray-400">(<?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?>)</span>
                </a>
            </nav>
        </div>
        <div class="border-t border-gray-100 pt-6">
            <a href="dashboard.php#katalog" class="text-xs font-bold text-gray-500 hover:text-brand-500 uppercase flex items-center gap-1">&larr; Kembali ke Shop</a>
        </div>
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
                </a>
                <a href="riwayat.php" class="px-4 py-3 rounded-xl hover:bg-stone-50 flex items-center gap-2.5"><i data-lucide="history"></i> Riwayat Order</a>
                <a href="chat.php" class="text-brand-primary font-extrabold bg-brand-primary-light px-4 py-3 rounded-xl flex items-center justify-between">
                    <span class="flex items-center gap-2.5"><i data-lucide="message-square"></i> Konsultasi</span>
                    <span class="w-2 h-2 bg-brand-primary rounded-full"></span>
                </a>
            </nav>
        </div>
>>>>>>> d3ff7d9 (perubahan pada pelanggan oleh nabila)
    </aside>

    <main class="flex-1 p-8 flex flex-col h-screen">
        <div class="max-w-4xl w-full mx-auto flex flex-col flex-1 bg-white border border-stone-200 rounded-2xl shadow-sm overflow-hidden">
            
            <div class="p-4 border-b border-stone-100 flex items-center gap-3 bg-stone-50">
                <div class="w-10 h-10 rounded-full bg-brand-primary text-white flex items-center justify-center font-bold">
                    <i data-lucide="bot" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-stone-800 uppercase">Customer Care & Botanis</h2>
                    <p class="text-[11px] text-emerald-600 font-semibold flex items-center gap-1">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full inline-block"></span> Online (Siap Membantu)
                    </p>
                </div>
            </div>

            <div class="flex-1 p-6 overflow-y-auto space-y-4 bg-[#FDFCFB]">
                <?php foreach ($chatMessages as $msg): ?>
                    <?php $isAdmin = ($msg['sender_type'] === 'admin'); ?>
                    <div class="flex <?= $isAdmin ? 'justify-start' : 'justify-end' ?>">
                        <div class="max-w-xs md:max-w-md p-3.5 rounded-2xl text-xs <?= $isAdmin ? 'bg-stone-100 text-stone-800 rounded-tl-none' : 'bg-brand-primary text-white rounded-tr-none' ?>">
                            <p class="leading-relaxed"><?= htmlspecialchars($msg['message']) ?></p>
                            <span class="block text-[9px] mt-1 text-right <?= $isAdmin ? 'text-stone-400' : 'text-emerald-200' ?>">
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="chat.php" class="p-3 border-t border-stone-100 bg-white flex gap-2">
                <input type="text" name="message" placeholder="Tanyakan seputar perawatan tanaman..." required class="flex-1 px-4 py-2 text-xs bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white transition">
                <button type="submit" class="bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2 rounded-xl text-xs font-bold uppercase transition flex items-center gap-1.5">
                    <span>Kirim</span>
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                </button>
            </form>

        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>