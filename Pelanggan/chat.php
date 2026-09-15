<?php
session_start();
require_once '../Config/database.php';

// Check user_id dari session, fallback ke ID 1 jika session belum ada
$user_id = $_SESSION['user_id'] ?? 1;

$error_msg = '';

// Handle Kirim Pesan dari Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    
    if (!empty($msg) && isset($conn) && $conn) {
        $clean_msg = mysqli_real_escape_string($conn, $msg);
        
        // Simpan pesan dengan sender_type = 'customer'
        $insertQuery = "INSERT INTO chats (user_id, sender_type, message, created_at) 
                        VALUES ('$user_id', 'customer', '$clean_msg', NOW())";
        
        if (mysqli_query($conn, $insertQuery)) {
            header('Location: chat.php');
            exit;
        } else {
            $error_msg = "Gagal menyimpan pesan: " . mysqli_error($conn);
        }
    }
}

// Fetch Pesan Antara Customer & Admin (Hanya query tabel chats sederhana)
$chatMessages = [];
if (isset($conn) && $conn) {
    $chatQuery = "SELECT * FROM chats WHERE user_id = '$user_id' ORDER BY created_at ASC";
    $chatResult = mysqli_query($conn, $chatQuery);

    if ($chatResult && mysqli_num_rows($chatResult) > 0) {
        while ($row = mysqli_fetch_assoc($chatResult)) {
            $chatMessages[] = $row;
        }
    }
}

// Fallback jika database masih kosong
if (empty($chatMessages) && empty($error_msg)) {
    $chatMessages = [
        ['sender_type' => 'admin', 'message' => 'Halo! Selamat datang di PlantHub. Ada yang bisa kami bantu mengenai perawatan tanaman Anda?', 'created_at' => date('Y-m-d H:i:s')],
    ];
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Konsultasi Admin</title>
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
                <a href="riwayat.php" class="px-3.5 py-2.5 rounded-xl hover:bg-stone-50 flex items-center gap-2.5 transition">
                    <i data-lucide="history" class="w-4 h-4"></i> Riwayat Order
                </a>
                <a href="chat.php" class="bg-planthub-green text-white font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between shadow-sm">
                    <span class="flex items-center gap-2.5"><i data-lucide="message-square" class="w-4 h-4"></i> Konsultasi</span>
                    <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                </a>
            </nav>
        </div>

        <!-- BOTTOM WIDGET & LOGOUT -->
        <div class="space-y-3 pt-3 border-t border-stone-100">
            <div class="bg-stone-50 p-3 rounded-xl flex items-center justify-between border border-stone-100">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-stone-500"></i>
                    <span class="text-xs font-semibold text-stone-700">Troli Belanja</span>
                </div>
                <span class="w-5 h-5 rounded-full bg-planthub-green text-white text-[10px] font-bold flex items-center justify-center">
                    <?= $cart_count ?>
                </span>
            </div>

            <a href="../Auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="flex items-center justify-center gap-1.5 text-xs font-bold text-red-500 hover:text-red-600 py-1 transition">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Keluar Sesi
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA KONSULTASI / CHAT -->
    <main class="flex-1 bg-white rounded-2xl p-6 shadow-sm border border-stone-100/80 flex flex-col h-[calc(100vh-3rem)] overflow-hidden">

        <!-- HEADER TOP BAR -->
        <header class="flex items-center justify-between pb-4 border-b border-stone-100 mb-4 shrink-0">
            <div>
                <h2 class="text-xl font-bold text-stone-900 flex items-center gap-2">
                    <i data-lucide="message-square" class="w-5 h-5 text-planthub-green"></i>
                    <span>Konsultasi Botanis</span>
                </h2>
                <p class="text-xs text-stone-400 mt-0.5">Tanyakan masalah perawatan, media tanam, atau rekomendasi tanaman.</p>
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

        <?php if (!empty($error_msg)): ?>
            <div class="mb-3 p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-xs font-medium">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- BOX CONTAINER CHAT -->
        <div class="flex-1 border border-stone-100 rounded-2xl flex flex-col overflow-hidden bg-stone-50/50">
            
            <!-- CHAT HEADER INFO -->
            <div class="p-3.5 bg-white border-b border-stone-100 flex items-center gap-3 shrink-0">
                <div class="w-9 h-9 rounded-xl bg-planthub-green-light text-planthub-green flex items-center justify-center font-bold">
                    <i data-lucide="bot" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-stone-800">Customer Care & Botanis PlantHub</h3>
                    <p class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1.5 mt-0.5">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full inline-block animate-pulse"></span> Tim Admin Online
                    </p>
                </div>
            </div>

            <!-- MESSAGES CONTAINER -->
            <div id="chat-box" class="flex-1 p-4 overflow-y-auto space-y-3.5 custom-scrollbar bg-stone-50/30">
                <?php foreach ($chatMessages as $msg): ?>
                    <?php $isAdmin = ($msg['sender_type'] === 'admin'); ?>
                    <div class="flex <?= $isAdmin ? 'justify-start' : 'justify-end' ?>">
                        <div class="max-w-xs sm:max-w-md md:max-w-lg p-3 rounded-2xl text-xs <?= $isAdmin ? 'bg-white text-stone-800 rounded-tl-none border border-stone-100 shadow-sm' : 'bg-planthub-green text-white rounded-tr-none shadow-sm' ?>">
                            <p class="leading-relaxed"><?= htmlspecialchars($msg['message']) ?></p>
                            <span class="block text-[9px] mt-1.5 text-right <?= $isAdmin ? 'text-stone-400' : 'text-emerald-200' ?>">
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- FORM INPUT CHAT -->
            <form method="POST" action="chat.php" class="p-3 bg-white border-t border-stone-100 flex gap-2 shrink-0">
                <input type="text" name="message" placeholder="Ketik pesan atau keluhan tanamanmu..." required autocomplete="off" class="flex-1 px-4 py-2.5 text-xs bg-stone-50 border border-stone-200/80 rounded-xl focus:outline-none focus:border-planthub-green focus:bg-white transition">
                <button type="submit" class="bg-planthub-green hover:bg-planthub-green-hover text-white px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <span>Kirim</span>
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                </button>
            </form>

        </div>

    </main>

    <script>
        lucide.createIcons();
        const chatBox = document.getElementById('chat-box');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    </script>
</body>
</html>