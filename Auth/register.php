<?php
require_once '../Config/database.php';

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username = '$email' OR email = '$email'");
    
    if ($cek && mysqli_num_rows($cek) > 0) {
        $pesan = "Email / Username sudah terdaftar!";
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $query = "INSERT INTO users (nama, username, password, role) VALUES ('$fullname', '$email', '$password_hash', 'admin')";
        
        if (mysqli_query($conn, $query)) {
            header("Location: login.php?status=registered");
            exit;
        } else {
            $pesan = "Gagal mendaftar: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PlantShop Admin</title>
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
         style="background-image: linear-gradient(to right, rgba(15, 23, 42, 0.6), rgba(46, 125, 50, 0.75)), url('https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?q=80&w=1600');">
        
        <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-emerald-400/30 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-3 z-10">
            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white ring-1 ring-white/30 shadow-xl">
                <i data-lucide="sprout" class="w-7 h-7 text-emerald-300"></i>
            </div>
            <div>
                <span class="text-2xl font-extrabold tracking-tight text-white block leading-none">Plant<span class="text-emerald-400">Shop</span></span>
                <span class="text-[10px] text-stone-300 uppercase tracking-widest font-semibold">Management System</span>
            </div>
        </div>

        <div class="max-w-lg z-10 space-y-6">
            <h1 class="text-5xl font-extrabold text-white leading-[1.15] tracking-tight">
                Bergabung dengan Sistem
            </h1>
            <p class="text-stone-200 text-sm leading-relaxed">
                Buat akun pengguna baru untuk mulai mengelola stok barang, transaksi penjualan, dan laporan berkala toko Anda.
            </p>
        </div>

        <div class="z-10 text-xs text-stone-300 flex items-center justify-between border-t border-white/15 pt-5">
            <span>&copy; 2026 PlantShop Admin Portal</span>
            <span class="flex items-center gap-1.5 text-emerald-300 font-medium">
                <i data-lucide="user-check" class="w-4 h-4"></i> Admin Privileges
            </span>
        </div>
    </div>

    <!-- SISI KANAN: Form Register Interaktif -->
    <div class="w-full lg:w-2/5 flex flex-col justify-between p-8 sm:p-14 lg:p-16 bg-[#F4F3EF] relative">
        
        <div class="flex items-center justify-between mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-stone-500 hover:text-[#2E7D32] transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Home
            </a>
            
            <div class="flex items-center gap-2 bg-stone-200/70 p-1 rounded-2xl">
                <a href="login.php" class="px-4 py-1.5 rounded-xl text-xs font-bold text-stone-500 hover:text-stone-800 transition-all">Masuk</a>
                <a href="register.php" class="px-4 py-1.5 rounded-xl text-xs font-bold bg-white text-stone-800 shadow-sm transition-all">Daftar</a>
            </div>
        </div>

        <div class="my-auto max-w-sm w-full mx-auto space-y-5">
            <div>
                <h2 class="text-3xl font-extrabold text-stone-900 tracking-tight">Daftar Akun Baru</h2>
                <p class="text-stone-500 text-xs mt-1.5">Isi formulir berikut untuk registrasi akun admin</p>
            </div>

            <?php if ($pesan): ?>
                <div class="p-3.5 rounded-2xl text-xs bg-rose-50 text-rose-800 border border-rose-200 flex items-center gap-2.5">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?= $pesan ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-3.5">
                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                    <div class="relative">
                        <input type="text" name="fullname" required placeholder="Nabila Putri" 
                            class="w-full pl-4 pr-11 py-3 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all shadow-sm">
                        <i data-lucide="user" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Email / Username</label>
                    <div class="relative">
                        <input type="email" name="email" required placeholder="nabila@plantshop.com" 
                            class="w-full pl-4 pr-11 py-3 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all shadow-sm">
                        <i data-lucide="mail" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" name="password" required placeholder="••••••••" 
                            class="w-full pl-4 pr-11 py-3 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all shadow-sm">
                        <i data-lucide="lock" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <button type="submit" class="w-full py-3.5 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold rounded-2xl shadow-lg shadow-emerald-900/20 text-xs transition-all flex items-center justify-center gap-2 group mt-2">
                    <span>BUAT AKUN SEKARANG</span>
                    <i data-lucide="user-plus" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="text-center pt-4 border-t border-stone-200/80">
                <p class="text-xs text-stone-500">
                    Sudah memiliki akun? 
                    <a href="login.php" class="font-bold text-[#2E7D32] hover:underline">Masuk / Login</a>
                </p>
            </div>
        </div>

        <div class="text-center text-[11px] text-stone-400 mt-6">
            PlantShop POS System &bull; Version 2026
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>