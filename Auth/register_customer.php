<?php
require_once '../Config/database.php';

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname'] ?? ''));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $no_hp    = mysqli_real_escape_string($conn, trim($_POST['no_hp'] ?? ''));
    $alamat   = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 6) {
        $pesan = "Password minimal 6 karakter.";
    } else {
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE username = '$email' OR email = '$email'");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $pesan = "Email sudah terdaftar!";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO users (nama_lengkap, username, email, password, role, no_hp, alamat) 
                    VALUES ('$fullname', '$email', '$email', '$hash', 'customer', '$no_hp', '$alamat')";
            if (mysqli_query($conn, $sql)) {
                header("Location: login.php?status=registered");
                exit;
            } else {
                $pesan = "Gagal mendaftar: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Pelanggan - PlantHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#F4F3EF] min-h-screen flex text-stone-800 overflow-x-hidden">

    <div class="hidden lg:flex lg:w-3/5 relative bg-cover bg-center flex-col justify-between p-12 overflow-hidden"
         style="background-image: linear-gradient(to right, rgba(15, 23, 42, 0.6), rgba(46, 125, 50, 0.75)), url('https://images.unsplash.com/photo-1518531933037-91b2f5f229cc?q=80&w=1600');">
        <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-emerald-400/30 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex items-center gap-3 z-10">
            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white ring-1 ring-white/30 shadow-xl">
                <i data-lucide="sprout" class="w-7 h-7 text-emerald-300"></i>
            </div>
            <div>
                <span class="text-2xl font-extrabold tracking-tight text-white block leading-none">Plant<span class="text-emerald-400">Hub</span></span>
                <span class="text-[10px] text-stone-300 uppercase tracking-widest font-semibold">Store Portal</span>
            </div>
        </div>
        <div class="max-w-lg z-10 space-y-6">
            <h1 class="text-5xl font-extrabold text-white leading-[1.15] tracking-tight">Mulai Belanja Tanaman Hias</h1>
            <p class="text-stone-200 text-sm leading-relaxed">
                Daftar akun pelanggan gratis, dapatkan akses ke katalog lengkap, konsultasi botanis, dan promo mingguan.
            </p>
        </div>
        <div class="z-10 text-xs text-stone-300 flex items-center justify-between border-t border-white/15 pt-5">
            <span>&copy; 2026 PlantHub</span>
            <span class="flex items-center gap-1.5 text-emerald-300 font-medium">
                <i data-lucide="user-check" class="w-4 h-4"></i> Customer Registration
            </span>
        </div>
    </div>

    <div class="w-full lg:w-2/5 flex flex-col justify-between p-8 sm:p-14 lg:p-16 bg-[#F4F3EF] relative">
        <div class="flex items-center justify-between mb-8">
            <a href="login.php" class="inline-flex items-center gap-2 text-xs font-bold text-stone-500 hover:text-[#2E7D32]">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
            </a>
            <div class="flex items-center gap-2 bg-stone-200/70 p-1 rounded-2xl">
                <a href="login.php" class="px-4 py-1.5 rounded-xl text-xs font-bold text-stone-500 hover:text-stone-800">Masuk</a>
                <a href="register_customer.php" class="px-4 py-1.5 rounded-xl text-xs font-bold bg-white text-stone-800 shadow-sm">Daftar</a>
            </div>
        </div>

        <div class="my-auto max-w-sm w-full mx-auto space-y-5">
            <div>
                <h2 class="text-3xl font-extrabold text-stone-900 tracking-tight">Daftar Pelanggan</h2>
                <p class="text-stone-500 text-xs mt-1.5">Isi data diri Anda untuk mulai berbelanja.</p>
            </div>

            <?php if ($pesan): ?>
                <div class="p-3.5 rounded-2xl text-xs bg-rose-50 text-rose-800 border border-rose-200 flex items-center gap-2.5">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?= htmlspecialchars($pesan) ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-3">
                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                    <div class="relative">
                        <input type="text" name="fullname" required placeholder="Nama Anda"
                            class="w-full pl-4 pr-11 py-2.5 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all">
                        <i data-lucide="user" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Email</label>
                    <div class="relative">
                        <input type="email" name="email" required placeholder="nama@mail.com"
                            class="w-full pl-4 pr-11 py-2.5 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] focus:ring-4 focus:ring-emerald-600/10 transition-all">
                        <i data-lucide="mail" class="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2 text-stone-400"></i>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">No. HP</label>
                        <input type="text" name="no_hp" required placeholder="0812..."
                            class="w-full px-4 py-2.5 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Password</label>
                        <input type="password" name="password" required placeholder="min 6 karakter"
                            class="w-full px-4 py-2.5 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32]">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-stone-600 uppercase tracking-wider mb-1.5">Alamat Pengiriman</label>
                    <textarea name="alamat" rows="2" required placeholder="Jl. ... No. ..., Kota"
                        class="w-full px-4 py-2.5 text-xs bg-white border border-stone-200 rounded-2xl focus:outline-none focus:border-[#2E7D32] resize-none"></textarea>
                </div>

                <button type="submit" class="w-full py-3 bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold rounded-2xl shadow-lg shadow-emerald-900/20 text-xs transition-all flex items-center justify-center gap-2 group mt-2">
                    <span>DAFTAR SEKARANG</span>
                    <i data-lucide="user-plus" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="text-center pt-4 border-t border-stone-200/80">
                <p class="text-xs text-stone-500">
                    Sudah punya akun?
                    <a href="login.php" class="font-bold text-[#2E7D32] hover:underline">Masuk di sini</a>
                </p>
            </div>
        </div>

        <div class="text-center text-[11px] text-stone-400 mt-6">PlantHub &bull; Version 2026</div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>