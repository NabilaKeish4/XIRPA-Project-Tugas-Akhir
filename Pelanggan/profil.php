<?php
session_start();

// Database connection setup
$host = "localhost";
$user = "root";
$pass = "";
$db   = "plant_hub";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

$success_msg = "";
$error_msg = "";

// Ambil ID user dari session (Default ID 1 untuk simulasi jika belum ada sistem auth ketat)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Fetch Data User / Pelanggan
$query_user = mysqli_query($conn, "SELECT * FROM pelanggan WHERE id = $user_id LIMIT 1");
$user = mysqli_fetch_assoc($query_user);

// If database table pelanggan is empty, setup mock array
if (!$user) {
    $user = [
        'id' => 1,
        'nama_pelanggan' => isset($_SESSION['user_nama']) ? $_SESSION['user_nama'] : 'Nabila Keisha',
        'email' => 'nabila@example.com',
        'no_hp' => '081234567890',
        'alamat' => 'Jl. Anggrek Mawar No. 45, Jakarta Selatan'
    ];
}

// HANDLER UPDATE PROFIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_pelanggan']));
    $email  = mysqli_real_escape_string($conn, trim($_POST['email']));
    $no_hp  = mysqli_real_escape_string($conn, trim($_POST['no_hp']));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat']));

    if (empty($nama) || empty($no_hp)) {
        $error_msg = "Nama lengkap dan Nomor WhatsApp wajib diisi!";
    } else {
        $update_q = "UPDATE pelanggan SET 
                        nama_pelanggan = '$nama', 
                        email = '$email', 
                        no_hp = '$no_hp', 
                        alamat = '$alamat' 
                    WHERE id = $user_id";

        if (mysqli_query($conn, $update_q)) {
            $_SESSION['user_nama'] = $nama;
            $user['nama_pelanggan'] = $nama;
            $user['email'] = $email;
            $user['no_hp'] = $no_hp;
            $user['alamat'] = $alamat;
            $success_msg = "Profil berhasil diperbarui!";
        } else {
            $error_msg = "Gagal memperbarui profil: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - PlantHub</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #FAFAFA;
        }
    </style>
</head>
<body class="text-stone-800 flex flex-col min-h-screen">

    <!-- NAVBAR PELANGGAN -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-stone-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <a href="index.php" class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-md shadow-emerald-900/20">
                        <i data-lucide="sprout" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-stone-900 leading-none block">Plant<span class="text-[#2E7D32]">Hub</span></span>
                        <span class="text-[10px] text-stone-400 font-medium tracking-widest uppercase">Green Store</span>
                    </div>
                </a>

                <div class="flex items-center gap-4 text-xs font-semibold">
                    <a href="riwayat.php" class="text-stone-600 hover:text-[#2E7D32] transition-colors flex items-center gap-1.5">
                        <i data-lucide="history" class="w-4 h-4"></i> Riwayat Pesanan
                    </a>
                    <a href="index.php" class="text-stone-600 hover:text-[#2E7D32] transition-colors flex items-center gap-1.5">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Katalog
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">

        <div class="border-b border-stone-200/80 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 tracking-tight">Akun Saya</h1>
                <p class="text-xs text-stone-500 mt-1">Kelola data informasi diri, alamat pengiriman default, serta keamanan akun Anda.</p>
            </div>
        </div>

        <!-- Alert Notifications -->
        <?php if (!empty($success_msg)): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-[#2E7D32]"></i>
                    <span><?= $success_msg ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-stone-400 hover:text-stone-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                    <span><?= $error_msg ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-stone-400 hover:text-stone-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
            
            <!-- USER SIDE CARD (LEFT 1 COL) -->
            <div class="bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs text-center space-y-4">
                <div class="w-24 h-24 bg-emerald-100 text-[#2E7D32] rounded-full flex items-center justify-center mx-auto text-3xl font-extrabold shadow-inner border-2 border-emerald-200">
                    <?= strtoupper(substr($user['nama_pelanggan'], 0, 2)) ?>
                </div>
                <div>
                    <h2 class="font-bold text-stone-900 text-base"><?= htmlspecialchars($user['nama_pelanggan']) ?></h2>
                    <p class="text-xs text-stone-400"><?= htmlspecialchars($user['email'] ?: 'Belum ada email') ?></p>
                </div>
                <div class="pt-2 border-t border-stone-100">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-[#2E7D32] border border-emerald-200">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Pelanggan Terverifikasi
                    </span>
                </div>
            </div>

            <!-- FORM EDIT PROFIL (RIGHT 2 COLS) -->
            <div class="md:col-span-2 bg-white p-6 rounded-2xl border border-stone-200/80 shadow-xs space-y-6">
                <h3 class="font-bold text-stone-800 text-base border-b border-stone-100 pb-3 flex items-center gap-2">
                    <i data-lucide="user" class="w-5 h-5 text-[#2E7D32]"></i> Data Personal & Informasi Pengiriman
                </h3>

                <form method="POST" action="profil.php" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-stone-600 mb-1.5">Nama Lengkap *</label>
                        <input type="text" name="nama_pelanggan" required value="<?= htmlspecialchars($user['nama_pelanggan']) ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-stone-600 mb-1.5">No. WhatsApp / Telepon *</label>
                            <input type="text" name="no_hp" required value="<?= htmlspecialchars($user['no_hp']) ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                        </div>
                        <div>
                            <label class="block font-semibold text-stone-600 mb-1.5">Alamat Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-stone-600 mb-1.5">Alamat Lengkap Utama</label>
                        <textarea name="alamat" rows="4" placeholder="Alamat rumah atau kantor tempat pengiriman tanaman..." class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl focus:bg-white focus:outline-none focus:border-[#2E7D32]"><?= htmlspecialchars($user['alamat']) ?></textarea>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" name="update_profile" class="bg-[#2E7D32] hover:bg-emerald-800 text-white font-bold py-2.5 px-5 rounded-xl text-xs flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </main>

    <!-- FOOTER PELANGGAN -->
    <footer class="bg-white border-t border-stone-200/80 mt-12 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center sm:text-left flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-[#2E7D32] flex items-center justify-center text-white">
                    <i data-lucide="sprout" class="w-4 h-4"></i>
                </div>
                <span class="text-xs font-bold text-stone-700">PlantHub Store &copy; <?= date('Y') ?></span>
            </div>
            <p class="text-xs text-stone-400">Sistem Manajemen Toko Tanaman Online & Management POS</p>
        </div>
    </footer>

    <!-- SCRIPT INITIALIZATION -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>