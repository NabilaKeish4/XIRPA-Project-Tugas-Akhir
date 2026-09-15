<?php
session_start();
require_once '../Config/database.php';

// Ambil list pelanggan yang pernah berkirim pesan
// Menggunakan query fleksibel agar aman jika kolom nama/username berbeda
$customersQuery = "SELECT DISTINCT u.id, 
                  COALESCE(u.username, u.email, CONCAT('Pelanggan #', u.id)) AS nama_tampil, 
                  u.email 
                  FROM users u 
                  JOIN chats c ON u.id = c.user_id 
                  ORDER BY c.created_at DESC";

$customers = [];
if (isset($conn) && $conn) {
    $cResult = mysqli_query($conn, $customersQuery);
    if ($cResult && mysqli_num_rows($cResult) > 0) {
        while ($row = mysqli_fetch_assoc($cResult)) {
            $customers[] = $row;
        }
    }
}

// Tentukan pelanggan mana yang sedang dipilih oleh Admin
$selected_user_id = $_GET['user_id'] ?? ($customers[0]['id'] ?? 1);

// Handle Kirim Pesan Balasan dari Admin
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    
    if (!empty($msg) && isset($conn) && $conn) {
        $clean_msg = mysqli_real_escape_string($conn, $msg);
        
        // Simpan pesan dengan sender_type = 'admin'
        $insertQuery = "INSERT INTO chats (user_id, sender_type, message, created_at) 
                        VALUES ('$selected_user_id', 'admin', '$clean_msg', NOW())";
        
        if (mysqli_query($conn, $insertQuery)) {
            header("Location: chat.php?user_id=$selected_user_id");
            exit;
        } else {
            $error_msg = "Gagal mengirim pesan: " . mysqli_error($conn);
        }
    }
}

// Fetch Riwayat Pesan dengan Pelanggan Terpilih
$chatMessages = [];
if (isset($conn) && $conn) {
    $chatQuery = "SELECT * FROM chats WHERE user_id = '$selected_user_id' ORDER BY created_at ASC";
    $chatResult = mysqli_query($conn, $chatQuery);

    if ($chatResult && mysqli_num_rows($chatResult) > 0) {
        while ($row = mysqli_fetch_assoc($chatResult)) {
            $chatMessages[] = $row;
        }
    }
}

// Ambil detail nama pelanggan terpilih
$selectedCustomerName = 'Pelanggan #' . $selected_user_id;
foreach ($customers as $c) {
    if ($c['id'] == $selected_user_id) {
        $selectedCustomerName = $c['nama_tampil'] ?? $c['email'];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop Admin - Chat Pelanggan</title>
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
                        admin: {
                            green: '#2E7D32',
                            'green-light': '#E8F5E9',
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
            background-color: #F8F9FA;
            color: #1E291E;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 9999px;
        }
    </style>
</head>
<body class="antialiased h-screen flex overflow-hidden bg-gray-50">

    <!-- SIDEBAR ADMIN -->
    <aside class="w-64 bg-white border-r border-gray-200/80 p-5 flex flex-col justify-between shrink-0 h-screen">
        <div>
            <!-- LOGO PLANTSHOP -->
            <a href="dashboard.php" class="flex items-center gap-3 mb-8 px-1">
                <div class="w-10 h-10 rounded-2xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                    <i data-lucide="sprout" class="w-6 h-6"></i>
                </div>
                <span class="text-xl font-bold tracking-tight text-gray-900">Plant<span class="text-[#2E7D32]">Shop</span></span>
            </a>

            <!-- NAVIGASI MAIN MENU -->
            <div class="space-y-6">
                <div>
                    <p class="text-[10px] font-extrabold tracking-wider text-gray-400 uppercase mb-3 px-3">MAIN MENU</p>
                    <nav class="flex flex-col space-y-1 text-sm font-semibold text-gray-600">
                        <a href="dashboard.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="layout-grid" class="w-5 h-5 text-gray-500"></i> Dashboard
                        </a>
                        <a href="pos.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="shopping-bag" class="w-5 h-5 text-gray-500"></i> Kasir (POS)
                        </a>
                        <a href="restock.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="truck" class="w-5 h-5 text-gray-500"></i> Pembelian (Restock)
                        </a>
                        <a href="stok.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="box" class="w-5 h-5 text-gray-500"></i> Stok & Produk
                        </a>
                        <a href="chat.php" class="bg-[#E8F5E9] text-[#2E7D32] font-bold px-3.5 py-2.5 rounded-xl flex items-center justify-between transition">
                            <span class="flex items-center gap-3"><i data-lucide="message-square" class="w-5 h-5 text-[#2E7D32]"></i> Konsultasi Chat</span>
                            <span class="w-2 h-2 bg-[#2E7D32] rounded-full"></span>
                        </a>
                        <a href="pelanggan.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="users" class="w-5 h-5 text-gray-500"></i> Pelanggan
                        </a>
                        <a href="laporan.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="bar-chart-3" class="w-5 h-5 text-gray-500"></i> Laporan
                        </a>
                    </nav>
                </div>

                <!-- NAVIGASI PENGATURAN -->
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-[10px] font-extrabold tracking-wider text-gray-400 uppercase mb-3 px-3">PENGATURAN</p>
                    <nav class="flex flex-col space-y-1 text-sm font-semibold text-gray-600">
                        <a href="pengaturan.php" class="px-3.5 py-2.5 rounded-xl hover:bg-gray-50 flex items-center gap-3 transition">
                            <i data-lucide="settings" class="w-5 h-5 text-gray-500"></i> Pengaturan Toko
                        </a>
                    </nav>
                </div>
            </div>
        </div>

        <!-- WIDGET LOKASI CABANG -->
        <div class="bg-[#F8F9FA] border border-gray-200/60 p-3.5 rounded-2xl flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#E8F5E9] text-[#2E7D32] flex items-center justify-center shrink-0">
                <i data-lucide="store" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0">
                <h4 class="text-xs font-bold text-gray-900 truncate">Cabang Batu Central</h4>
                <p class="text-[10px] text-gray-400 font-medium">Sistem Online Active</p>
            </div>
        </div>
    </aside>

    <!-- KONTEN UTAMA CHAT ADMIN -->
    <main class="flex-1 flex h-screen overflow-hidden">
        
        <!-- SIDEBAR DAFTAR PELANGGAN -->
        <div class="w-72 bg-white border-r border-gray-200/80 flex flex-col h-full">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Daftar Percakapan</h3>
                <p class="text-[11px] text-gray-400">Pilih pelanggan untuk membalas pesan</p>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 space-y-1">
                <?php if (empty($customers)): ?>
                    <div class="p-4 text-center text-xs text-gray-400">Belum ada obrolan pelanggan</div>
                <?php else: ?>
                    <?php foreach ($customers as $cust): ?>
                        <a href="chat.php?user_id=<?= $cust['id'] ?>" class="flex items-center gap-3 p-3 rounded-xl transition <?= $selected_user_id == $cust['id'] ? 'bg-[#E8F5E9] text-[#2E7D32]' : 'hover:bg-gray-50 text-gray-700' ?>">
                            <div class="w-8 h-8 rounded-full bg-stone-200 flex items-center justify-center font-bold text-xs uppercase text-stone-600 shrink-0">
                                <?= substr($cust['nama_tampil'] ?? 'P', 0, 1) ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold truncate"><?= htmlspecialchars($cust['nama_tampil']) ?></h4>
                                <p class="text-[10px] opacity-70 truncate"><?= htmlspecialchars($cust['email'] ?? 'Pelanggan Online') ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- AREA CHAT BOX -->
        <div class="flex-1 bg-white flex flex-col h-full">
            
            <!-- CHAT HEADER -->
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-white shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-[#E8F5E9] text-[#2E7D32] flex items-center justify-center font-bold text-xs">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900"><?= htmlspecialchars($selectedCustomerName) ?></h3>
                        <p class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full"></span> Pelanggan Aktif
                        </p>
                    </div>
                </div>
            </div>

            <!-- MESSAGES CONTAINER -->
            <div id="admin-chat-box" class="flex-1 p-6 overflow-y-auto space-y-4 bg-gray-50/50 custom-scrollbar">
                <?php if (!empty($error_msg)): ?>
                    <div class="p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-xs font-medium">
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($chatMessages as $msg): ?>
                    <?php $isAdmin = ($msg['sender_type'] === 'admin'); ?>
                    <div class="flex <?= $isAdmin ? 'justify-end' : 'justify-start' ?>">
                        <div class="max-w-xs md:max-w-md p-3.5 rounded-2xl text-xs <?= $isAdmin ? 'bg-[#2E7D32] text-white rounded-tr-none shadow-sm' : 'bg-white text-gray-800 rounded-tl-none border border-gray-200/70 shadow-sm' ?>">
                            <p class="leading-relaxed"><?= htmlspecialchars($msg['message']) ?></p>
                            <span class="block text-[9px] mt-1.5 text-right <?= $isAdmin ? 'text-emerald-100' : 'text-gray-400' ?>">
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- FORM INPUT CHAT ADMIN -->
            <form method="POST" action="chat.php?user_id=<?= $selected_user_id ?>" class="p-4 bg-white border-t border-gray-100 flex gap-2 shrink-0">
                <input type="text" name="message" placeholder="Tulis balasan untuk pelanggan..." required autocomplete="off" class="flex-1 px-4 py-2.5 text-xs bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-[#2E7D32] focus:bg-white transition">
                <button type="submit" class="bg-[#2E7D32] hover:bg-[#256628] text-white px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                    <span>Kirim Balasan</span>
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                </button>
            </form>

        </div>

    </main>

    <script>
        lucide.createIcons();
        const chatBox = document.getElementById('admin-chat-box');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    </script>
</body>
</html>