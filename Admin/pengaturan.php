<?php
session_start();
require_once '../Config/database.php'; // Koneksi ke DB plant_hub

// --- AUTOCREATE TABEL PENGATURAN (Diperbarui dengan kolom logo & foto profil) ---
$create_table = "CREATE TABLE IF NOT EXISTS pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko VARCHAR(100) NOT NULL,
    nama_cabang VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(20) NOT NULL,
    email_toko VARCHAR(100) NOT NULL,
    alamat_toko TEXT NOT NULL,
    catatan_nota TEXT NULL,
    logo_toko VARCHAR(255) DEFAULT 'default_logo.png',
    foto_profil VARCHAR(255) DEFAULT 'default_avatar.jpg',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_table);

// Pastikan ada setidaknya 1 baris data default
$check_data = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
if (mysqli_num_rows($check_data) == 0) {
    $init_data = "INSERT INTO pengaturan (id, nama_toko, nama_cabang, no_telepon, email_toko, alamat_toko, catatan_nota) 
                  VALUES (1, 'PlantShop', 'Cabang Bandung Central', '081234567890', 'admin@plantshop.com', 'Jl. Kebon Tanaman No. 123, Bandung', 'Terima kasih telah berbelanja tanaman di PlantShop!')";
    mysqli_query($conn, $init_data);
}

// --- LOGIKA SIMPAN / UPDATE PENGATURAN & UPLOAD FOTO ---
$pesan_sukses = "";
$pesan_error  = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $nama_toko    = mysqli_real_escape_string($conn, $_POST['nama_toko']);
    $nama_cabang  = mysqli_real_escape_string($conn, $_POST['nama_cabang']);
    $no_telepon   = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $email_toko   = mysqli_real_escape_string($conn, $_POST['email_toko']);
    $alamat_toko  = mysqli_real_escape_string($conn, $_POST['alamat_toko']);
    $catatan_nota = mysqli_real_escape_string($conn, $_POST['catatan_nota']);

    // Handle Upload Foto Profil Admin
    $foto_profil_sql = "";
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_profil']['tmp_name'];
        $fileName    = $_FILES['foto_profil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'profil_' . time() . '.' . $fileExtension;
            $uploadFileDir = '../uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $foto_profil_sql = ", foto_profil = '$newFileName'";
            }
        }
    }

    $update_query = "UPDATE pengaturan SET 
                     nama_toko = '$nama_toko',
                     nama_cabang = '$nama_cabang',
                     no_telepon = '$no_telepon',
                     email_toko = '$email_toko',
                     alamat_toko = '$alamat_toko',
                     catatan_nota = '$catatan_nota'
                     $foto_profil_sql
                     WHERE id = 1";

    if (mysqli_query($conn, $update_query)) {
        $pesan_sukses = "Pengaturan toko & foto profil berhasil diperbarui!";
    } else {
        $pesan_error = "Gagal memperbarui pengaturan: " . mysqli_error($conn);
    }
}

// Ambil data pengaturan terbaru
$res_pengaturan = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
$data_toko     = mysqli_fetch_assoc($res_pengaturan);

// Path Foto Profil
$avatar_src = (!empty($data_toko['foto_profil']) && file_exists('../uploads/' . $data_toko['foto_profil'])) 
    ? '../uploads/' . $data_toko['foto_profil'] 
    : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($data_toko['nama_toko']) ?> - Pengaturan Toko</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F9F8F6; color: #2D3748; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

   <!-- TOP NAVBAR -->
<header class="sticky top-0 z-50 bg-white border-b border-stone-200/80 shadow-sm">
    <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        
        <!-- Logo & Mobile Menu Toggle -->
        <div class="flex items-center gap-3">
            <button id="mobile-menu-btn" type="button" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100 transition-colors cursor-pointer">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <a href="dashboard.php" class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                    <i data-lucide="sprout" class="w-5 h-5"></i>
                </div>
                <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Shop</span></span>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="hidden md:flex flex-1 max-w-md relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
            <input type="text" placeholder="Cari..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all placeholder:text-stone-400">
        </div>

        <!-- Right Menu (Notification & Profile Dropdown) -->
        <div class="flex items-center gap-3">
            
            <!-- Tombol Notifikasi -->
            <div class="relative">
                <button type="button" id="notifBtn" onclick="toggleDropdown('notifMenu')" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full transition-colors cursor-pointer">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                </button>

                <!-- Dropdown Notifikasi -->
                <div id="notifMenu" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-2xl border border-stone-200 shadow-lg py-2 z-50">
                    <div class="px-4 py-2 border-b border-stone-100 flex justify-between items-center">
                        <span class="text-xs font-bold text-stone-800 uppercase">Notifikasi</span>
                        <span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-bold"> Baru</span>
                    </div>
                    <div class="p-3 text-xs text-stone-500 hover:bg-stone-50 transition-colors cursor-pointer">
                        <p class="font-semibold text-stone-800">Stok Monstera Menipis</p>
                        <p class="text-[11px] text-stone-400">Tersisa 2 item di gudang.</p>
                    </div>
                </div>
            </div>

            <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>

            <!-- Tombol Dropdown Profil Admin -->
            <div class="relative">
                <button type="button" onclick="toggleDropdown('profileMenu')" class="flex items-center gap-3 pl-1 hover:opacity-80 transition-opacity cursor-pointer focus:outline-none">
                    <div class="relative">
                        <img id="navAvatar" src="<?= $avatar_src ?>" alt="Nabila" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold text-stone-800 leading-tight">Nabila</p>
                        <p class="text-xs text-stone-500">Administrator</p>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                </button>

                <!-- Menu Dropdown Profil -->
                <div id="profileMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl border border-stone-200 shadow-lg py-1.5 z-50">
                    <a href="pengaturan.php" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100 transition-colors">
                        <i data-lucide="settings" class="w-4 h-4 text-stone-500"></i>
                        <span>Pengaturan Akun</span>
                    </a>
                    <hr class="my-1 border-stone-100">
                    <a href="logout.php" onclick="return confirm('Yakin ingin keluar?')" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors">
                        <i data-lucide="log-out" class="w-4 h-4 text-red-500"></i>
                        <span>Keluar (Logout)</span>
                    </a>
                </div>
            </div>

        </div>
    </div>
</header>
    <div class="flex flex-1">
        <!-- SIDEBAR NAVIGASI (Preserved from Dashboard) -->
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0">
            <div class="p-4 space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Main Menu</p>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </a>
                    <a href="pos.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Penjualan (POS)
                    </a>
                    <a href="restock.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="truck" class="w-4 h-4"></i> Pembelian (Restock)
                    </a>
                    <a href="stok.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="package" class="w-4 h-4"></i> Stok & Produk
                    </a>
                    <a href="pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="users" class="w-4 h-4"></i> Pelanggan
                    </a>
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Laporan
                    </a>
                </nav>

                <hr class="border-stone-100">

                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Pengaturan</p>
                    <a href="pengaturan.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-[#2E7D32] bg-[#2E7D32]/10 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <i data-lucide="settings" class="w-4 h-4"></i> Pengaturan Toko
                        </div>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span>
                    </a>
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="help-circle" class="w-4 h-4"></i> Bantuan
                    </a>
                </nav>
            </div>

            <div class="p-4 m-4 rounded-xl bg-stone-50 border border-stone-200/60">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-100 text-[#2E7D32] rounded-lg">
                        <i data-lucide="store" class="w-4 h-4"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-semibold text-stone-800 truncate"><?= htmlspecialchars($data_toko['nama_cabang']) ?></p>
                        <p class="text-[11px] text-stone-500 truncate">Sistem Online Active</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-6">
            
            <div>
                <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Pengaturan Sistem</h1>
                <p class="text-sm text-stone-500 mt-0.5">Atur profil administrator, identitas cabang toko, dan preferensi struk.</p>
            </div>

            <!-- Notifikasi Alert -->
            <?php if (!empty($pesan_sukses)): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32]"></i>
                    <span><?= $pesan_sukses ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($pesan_error)): ?>
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                    <span><?= $pesan_error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="pengaturan.php" enctype="multipart/form-data" class="space-y-6">
                
                <!-- BAGIAN 1: FOTO PROFIL ADMIN -->
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-base font-bold text-stone-800">Foto Profil Administrator</h2>
                        <p class="text-xs text-stone-500">Ubah foto profil yang tampil pada header aplikasi.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        <div class="relative group">
                            <img id="previewAvatar" src="<?= $avatar_src ?>" alt="Preview Profil" class="w-24 h-24 rounded-2xl object-cover ring-4 ring-stone-100 shadow-sm">
                        </div>
                        <div class="space-y-2 text-center sm:text-left">
                            <label class="inline-flex items-center gap-2 bg-stone-100 hover:bg-stone-200 text-stone-700 px-4 py-2 rounded-xl text-xs font-semibold cursor-pointer transition-colors">
                                <i data-lucide="upload" class="w-4 h-4"></i>
                                <span>Pilih Foto Baru</span>
                                <input type="file" name="foto_profil" accept="image/*" class="hidden" onchange="previewImage(event)">
                            </label>
                            <p class="text-[11px] text-stone-400">Format yang didukung: JPG, PNG, atau WEBP. Maksimal 2MB.</p>
                        </div>
                    </div>
                </div>

                <!-- BAGIAN 2: INFORMASI UMUM TOKO -->
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-base font-bold text-stone-800">Informasi Umum Toko</h2>
                        <p class="text-xs text-stone-500">Detail identitas dan alamat lokasi cabang utama.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Toko</label>
                            <input type="text" name="nama_toko" value="<?= htmlspecialchars($data_toko['nama_toko']) ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Cabang / Lokasi</label>
                            <input type="text" name="nama_cabang" value="<?= htmlspecialchars($data_toko['nama_cabang']) ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">No. Telepon / WhatsApp</label>
                            <input type="text" name="no_telepon" value="<?= htmlspecialchars($data_toko['no_telepon']) ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Email Toko</label>
                            <input type="email" name="email_toko" value="<?= htmlspecialchars($data_toko['email_toko']) ?>" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat Lengkap Toko</label>
                        <textarea name="alamat_toko" rows="3" required class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"><?= htmlspecialchars($data_toko['alamat_toko']) ?></textarea>
                    </div>
                </div>

                <!-- BAGIAN 3: PENGATURAN STRUK -->
                <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm p-6 space-y-4">
                    <div class="border-b border-stone-100 pb-3">
                        <h2 class="text-base font-bold text-stone-800">Preferensi Struk POS</h2>
                        <p class="text-xs text-stone-500">Catatan kaki yang akan secara otomatis dicetak di bagian bawah nota.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Catatan Kaki Struk (Footer Struk)</label>
                        <textarea name="catatan_nota" rows="2" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]"><?= htmlspecialchars($data_toko['catatan_nota']) ?></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="dashboard.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-stone-600 hover:bg-stone-100">Batal</a>
                    <button type="submit" name="simpan_pengaturan" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm transition-all cursor-pointer">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>

        </main>
    </div>

    <script>
        lucide.createIcons();

        // Preview Foto Profil Langsung Saat Dipilih
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('previewAvatar');
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        // Mobile Sidebar Toggle
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const sidebar = document.getElementById('sidebar');
        mobileBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('fixed');
            sidebar.classList.toggle('inset-y-0');
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('z-40');
        });
    </script>
</body>
</html>
