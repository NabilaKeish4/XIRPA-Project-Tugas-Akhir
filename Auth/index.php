<?php
session_start();
require_once '../Config/database.php';

$pesan = '';

// Mengirim form login langsung dari halaman index
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE username = '$email' OR email = '$email'");
    
    if ($query && mysqli_num_rows($query) === 1) {
        $user = mysqli_fetch_assoc($query);
        
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];

            header("Location: ../Admin/dashboard.php");
            exit;
        } else {
            $pesan = "Password salah!";
        }
    } else {
        $pesan = "Akun tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Welcome Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#F4F3EF] min-h-screen flex text-stone-800 overflow-x-hidden">

    <!-- SISI KIRI: Visual Aesthetic Banner -->
    <div class="hidden lg:flex lg:w-3/5 relative bg-cover bg-center flex-col justify-between p-12 overflow-hidden"
         style="background-image: linear-gradient(to right, rgba(15, 23, 42, 0.4), rgba(15, 23, 42, 0.75)), url('https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?q=80&w=1600');">
        
        <!-- Glow Circle Effect -->
        <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-[#2E7D32]/40 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Header Brand -->
        <div class="flex items-center gap-3 z-10">
            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white ring-1 ring-white/30 shadow-xl">
                <i data-lucide="sprout" class="w-7 h-7 text-emerald-300"></i>
            </div>
            <div>
                <span class="text-2xl font-extrabold tracking-tight text-white block leading-none">Plant<span class="text-emerald-400">Shop</span></span>
                <span class="text-[10px] text-stone-300 uppercase tracking-widest font-semibold">Management System</span>
            </div>
        </div>

        <!-- Banner Content -->
        <div class="max-w-lg z-10 space-y-6">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-xs font-semibold backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Sistem Kasir & Control Stok 2026
            </div>
            <h1 class="text-5xl font-extrabold text-white leading-[1.15] tracking-tight">
                Kelola Tanaman & Transaksi Toko Lebih Mudah.
            </h1>
            <p class="text-stone-300 text-sm leading-relaxed">
                Akses cepat laporan penjualan, kontrol persediaan tanaman hias, dan manajemen inventaris dalam satu platform terpadu.
            </p>
        </div>

        <!-- Footer Visual -->
        <div class="z-10 text-xs text-stone-400 flex items-center justify-between border-t border-white/15 pt-5">
            <span>&copy; 2026 PlantShop Admin Portal</span>
            <span class="flex items-center gap-1.5 text-emerald-300 font-medium">
                <i data-lucide="shield-check" class="w-4 h-4"></i> Secure System
            </span>
        </div>
    </div>

    <!-- SISI KANAN: Form Interaktif Direct Login / Register Quick Switch -->
    <div class="w-full lg:w-2/5 flex flex-col justify-between p-8 sm:p-14 lg:p-16 bg-[#F4F3EF] relative">
        
        <!-- Header Nav Switcher -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex lg:hidden items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <span class="text-lg font-bold">Plant<span class="text-[#2E7D32]">Shop</span></span>
            </div>
            
            <div class="ml-auto flex items-center gap-2 bg-stone-200/70 p-1 rounded-2xl">
                <a href="index.php" class="px-4 py-1.5 rounded-xl text-xs font-bold bg-white text-stone-800 shadow-sm transition-all">Masuk</a>
                <a href="register.php" class="px-4 py-1.5 rounded-xl text-xs font-bold text-stone-500 hover:text-stone-800 transition-all">Daftar</a>
            </div>
        </div>

        <!-- Main Form Content -->
        <div class="my-auto max-w-sm w-full mx-auto space-y-6">
            <div>
                <h2 class="text-3xl font-extrabold text-stone-900 tracking-tight">Selamat Datang!</h2>
                <p class="text-stone-500 text-xs mt-1.5">Masukan data akun Anda untuk mengakses sistem</p>
            </div>

            <?php if ($pesan): ?>
                <div class="p-3.5 rounded-2xl text-xs bg-rose-50 text-rose-700 border border-rose-200 flex items-center gap-2.5">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?= $pesan ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">
                
                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Email / Username</label>
                    <div class="relative">
                        <input type="text" name="email" required placeholder="admin@plantshop.com" 
                            class="w-full pl-4 pr-11 py-3 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all shadow-sm">
                        <i data-lucide="mail" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider">Password</label>
                        <a href="#" class="text-[11px] font-semibold text-[#2E7D32] hover:underline">Lupa password?</a>
                    </div>
                    <div class="relative">
                        <input type="password" name="password" required placeholder="••••••••" 
                            class="w-full pl-4 pr-11 py-3 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all shadow-sm">
                        <i data-lucide="lock" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold rounded-2xl shadow-lg shadow-emerald-900/20 text-xs transition-all flex items-center justify-center gap-2 group mt-2">
                    <span>MASUK KE DASHBOARD</span>
                    <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="relative my-4 text-center">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-stone-200"></div></div>
                <span class="relative px-3 bg-[#F4F3EF] text-[11px] text-stone-400 font-medium uppercase tracking-wider">atau</span>
            </div>

            <a href="register.php" class="w-full py-3 bg-white hover:bg-stone-50 text-stone-700 font-bold border border-stone-200 rounded-2xl text-xs transition-all flex items-center justify-center gap-2 shadow-sm">
                <i data-lucide="user-plus" class="w-4 h-4 text-[#2E7D32]"></i>
                <span>Buat Akun Admin Baru</span>
            </a>
        </div>

        <div class="text-center text-[11px] text-stone-400 mt-6">
            PlantShop POS System &bull; Version 2026
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>