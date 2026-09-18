<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../Auth/login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$nama_user  = $_SESSION['nama_user'] ?? 'Pelanggan';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$sukses = '';
$error  = '';

$userData = null;
$qUser = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1");
if ($qUser) $userData = mysqli_fetch_assoc($qUser);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profil') {
        $nama   = mysqli_real_escape_string($conn, trim($_POST['nama_lengkap'] ?? ''));
        $no_hp  = mysqli_real_escape_string($conn, trim($_POST['no_hp'] ?? ''));
        $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));

        if ($nama === '') {
            $error = "Nama tidak boleh kosong.";
        } else {
            $sql = "UPDATE users SET nama_lengkap = '$nama', no_hp = '$no_hp', alamat = '$alamat' WHERE id = $user_id";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['nama_user'] = $nama;
                $nama_user = $nama;
                $sukses = "Profil berhasil diperbarui.";
                $qUser = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1");
                $userData = mysqli_fetch_assoc($qUser);
            } else {
                $error = "Gagal update profil: " . mysqli_error($conn);
            }
        }
    }

    if ($action === 'ganti_password') {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $konf = $_POST['konfirmasi'] ?? '';

        if (strlen($new) < 6) {
            $error = "Password baru minimal 6 karakter.";
        } elseif ($new !== $konf) {
            $error = "Konfirmasi password tidak cocok.";
        } elseif (!password_verify($old, $userData['password'])) {
            $error = "Password lama salah.";
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            if (mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = $user_id")) {
                $sukses = "Password berhasil diubah.";
            } else {
                $error = "Gagal ganti password: " . mysqli_error($conn);
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
    <title>PlantHub - Profil Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; } </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>
            <div class="flex-1 max-w-md hidden md:block">
                <span class="text-xs text-stone-500">Profil Saya</span>
            </div>
            <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($nama_user) ?></p>
                    <p class="text-xs text-stone-500">Pelanggan</p>
                </div>
            </a>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MENU PELANGGAN</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php', 'icon' => 'layout-grid',    'label' => 'Beranda',        'active' => false],
                        ['url' => 'katalog.php',   'icon' => 'store',          'label' => 'Katalog Shop',   'active' => false],
                        ['url' => 'cart.php',      'icon' => 'shopping-bag',   'label' => 'Keranjang',      'active' => false],
                        ['url' => 'riwayat.php',   'icon' => 'history',        'label' => 'Riwayat Order',  'active' => false],
                        ['url' => 'chat.php',      'icon' => 'message-square', 'label' => 'Konsultasi',     'active' => false],
                        ['url' => 'profil.php',    'icon' => 'user',           'label' => 'Profil Saya',    'active' => true],
                    ];
                    foreach ($menu as $m):
                        $cls = $m['active'] ? 'text-[#1E7D32] bg-[#E8F5E9]' : 'text-stone-700 hover:bg-stone-100';
                    ?>
                        <a href="<?= $m['url'] ?>" class="flex items-center justify-between px-3 py-2.5 text-sm font-bold rounded-xl transition-colors <?= $cls ?>">
                            <div class="flex items-center gap-3">
                                <i data-lucide="<?= $m['icon'] ?>" class="w-5 h-5 <?= $m['active'] ? 'text-[#1E7D32]' : 'text-stone-500' ?>"></i>
                                <span><?= $m['label'] ?></span>
                            </div>
                            <?php if ($m['active']): ?><span class="w-2.5 h-2.5 rounded-full bg-[#1E7D32]"></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 leading-tight">Troli Belanja</p>
                    <p class="text-[11px] text-stone-400 mt-0.5"><?= $cart_count ?> item</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-6">

            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Profil Saya</h1>
                <p class="text-sm text-stone-500 mt-0.5">Kelola data diri dan keamanan akun Anda.</p>
            </div>

            <?php if ($sukses): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32]"></i>
                    <span><?= htmlspecialchars($sukses) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 flex items-center gap-5">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=2E7D32&color=fff&size=128" class="w-20 h-20 rounded-2xl ring-4 ring-[#2E7D32]/10">
                <div>
                    <h2 class="text-xl font-bold text-stone-800"><?= htmlspecialchars($nama_user) ?></h2>
                    <p class="text-xs text-stone-500 mt-0.5"><?= htmlspecialchars($userData['email'] ?? '') ?></p>
                    <p class="text-xs text-stone-400 mt-0.5">Bergabung: <?= !empty($userData['created_at']) ? date('d M Y', strtotime($userData['created_at'])) : '-' ?></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                <div class="border-b border-stone-100 pb-3">
                    <h2 class="text-base font-bold text-stone-800">Data Diri</h2>
                    <p class="text-xs text-stone-500 mt-0.5">Perbarui informasi kontak dan alamat pengiriman Anda.</p>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_profil">

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($userData['nama_lengkap'] ?? '') ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Email (tidak bisa diubah)</label>
                            <input type="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" disabled class="w-full px-4 py-2.5 text-sm bg-stone-100 border border-stone-200 rounded-xl text-stone-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">No. HP / WhatsApp</label>
                            <input type="text" name="no_hp" value="<?= htmlspecialchars($userData['no_hp'] ?? '') ?>" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat Pengiriman</label>
                        <textarea name="alamat" rows="3" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"><?= htmlspecialchars($userData['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-emerald-800 transition">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                <div class="border-b border-stone-100 pb-3">
                    <h2 class="text-base font-bold text-stone-800">Ganti Password</h2>
                    <p class="text-xs text-stone-500 mt-0.5">Gunakan password yang kuat dan mudah diingat.</p>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="ganti_password">

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Password Lama</label>
                        <input type="password" name="old_password" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Password Baru</label>
                            <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Konfirmasi Password</label>
                            <input type="password" name="konfirmasi" required minlength="6" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="inline-flex items-center gap-2 bg-stone-800 text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-stone-900 transition">
                            <i data-lucide="lock" class="w-4 h-4"></i> Ubah Password
                        </button>
                    </div>
                </form>
            </div>
                        <!-- BANTUAN & PANDUAN -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                <div class="border-b border-stone-100 pb-3">
                    <h2 class="text-base font-bold text-stone-800">Bantuan & Panduan</h2>
                    <p class="text-xs text-stone-500 mt-0.5">Panduan singkat menggunakan fitur PlantHub.</p>
                </div>

                <div class="space-y-2">
                    <details class="group border border-stone-200/80 rounded-xl overflow-hidden">
                        <summary class="cursor-pointer p-3.5 text-sm font-semibold text-stone-800 hover:bg-stone-50 flex items-center justify-between list-none">
                            <span>Bagaimana cara memesan tanaman?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 group-open:rotate-180 transition"></i>
                        </summary>
                        <div class="p-3.5 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                            Buka <b>Katalog Shop</b>, klik produk → <b>+ Keranjang</b>. Lalu buka <b>Keranjang</b>, klik <b>Checkout</b>, isi alamat pengiriman, pilih metode bayar, dan klik <b>Selesaikan Pesanan</b>.
                        </div>
                    </details>

                    <details class="group border border-stone-200/80 rounded-xl overflow-hidden">
                        <summary class="cursor-pointer p-3.5 text-sm font-semibold text-stone-800 hover:bg-stone-50 flex items-center justify-between list-none">
                            <span>Bagaimana cara melacak pesanan saya?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 group-open:rotate-180 transition"></i>
                        </summary>
                        <div class="p-3.5 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                            Buka menu <b>Riwayat Order</b>. Semua pesanan Anda akan tampil beserta status terbaru (Diproses / Dikirim / Selesai / Batal).
                        </div>
                    </details>

                    <details class="group border border-stone-200/80 rounded-xl overflow-hidden">
                        <summary class="cursor-pointer p-3.5 text-sm font-semibold text-stone-800 hover:bg-stone-50 flex items-center justify-between list-none">
                            <span>Bagaimana cara membatalkan pesanan?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 group-open:rotate-180 transition"></i>
                        </summary>
                        <div class="p-3.5 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                            Pesanan yang masih berstatus <b>Diproses</b> bisa dibatalkan. Buka <b>Riwayat Order</b>, klik tombol <b>Batalkan</b> pada pesanan yang diinginkan. Stok produk akan otomatis dikembalikan.
                        </div>
                    </details>

                    <details class="group border border-stone-200/80 rounded-xl overflow-hidden">
                        <summary class="cursor-pointer p-3.5 text-sm font-semibold text-stone-800 hover:bg-stone-50 flex items-center justify-between list-none">
                            <span>Bagaimana cara konsultasi dengan admin?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 group-open:rotate-180 transition"></i>
                        </summary>
                        <div class="p-3.5 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                            Buka menu <b>Konsultasi</b>, ketik pertanyaan Anda tentang perawatan tanaman atau produk, lalu klik <b>Kirim</b>. Admin akan membalas secepatnya.
                        </div>
                    </details>

                    <details class="group border border-stone-200/80 rounded-xl overflow-hidden">
                        <summary class="cursor-pointer p-3.5 text-sm font-semibold text-stone-800 hover:bg-stone-50 flex items-center justify-between list-none">
                            <span>Bagaimana cara mengganti password?</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 group-open:rotate-180 transition"></i>
                        </summary>
                        <div class="p-3.5 pt-0 text-xs text-stone-600 leading-relaxed bg-stone-50/50 border-t border-stone-100">
                            Anda sedang berada di halaman yang tepat! Scroll ke atas, isi form <b>Ganti Password</b> dengan password lama, password baru, dan konfirmasi.
                        </div>
                    </details>
                </div>

                <div class="pt-3 border-t border-stone-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-emerald-50/50 -mx-6 -mb-6 px-6 py-4 rounded-b-2xl">
                    <div>
                        <p class="text-xs font-bold text-stone-800">Butuh bantuan lebih lanjut?</p>
                        <p class="text-[11px] text-stone-500 mt-0.5">Tim admin PlantHub siap membantu Anda.</p>
                    </div>
                    <a href="chat.php" class="inline-flex items-center gap-2 bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                        <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                        <span>Hubungi Admin</span>
                    </a>
                </div>
            </div>

        </main>
    </div>

    <script>
        lucide.createIcons();
        function toggleMobileSidebar() {
            const s = document.getElementById('sidebar');
            s?.classList.toggle('hidden');
            s?.classList.toggle('fixed');
            s?.classList.toggle('inset-y-0');
            s?.classList.toggle('left-0');
            s?.classList.toggle('z-40');
        }
    </script>
</body>
</html>