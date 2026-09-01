<?php
session_start();
require_once '../Config/database.php';

// --- AUTOCREATE TABEL PELANGGAN ---
$create_table = "CREATE TABLE IF NOT EXISTS pelanggan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL,
    alamat TEXT NULL,
    total_transaksi INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_table);

// --- FITUR 1: TAMBAH PELANGGAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pelanggan'])) {
    $nama   = mysqli_real_escape_string($conn, $_POST['nama_pelanggan']);
    $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $email  = mysqli_real_escape_string($conn, $_POST['email']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    $query = "INSERT INTO pelanggan (nama_pelanggan, no_hp, email, alamat) VALUES ('$nama', '$no_hp', '$email', '$alamat')";
    if (mysqli_query($conn, $query)) {
        header("Location: pelanggan.php?status=success_add");
        exit;
    }
}

// --- FITUR 2: EDIT PELANGGAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pelanggan'])) {
    $id     = (int)$_POST['id_pelanggan'];
    $nama   = mysqli_real_escape_string($conn, $_POST['nama_pelanggan']);
    $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $email  = mysqli_real_escape_string($conn, $_POST['email']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    $query = "UPDATE pelanggan SET nama_pelanggan='$nama', no_hp='$no_hp', email='$email', alamat='$alamat' WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        header("Location: pelanggan.php?status=success_edit");
        exit;
    }
}

// --- FITUR 3: HAPUS PELANGGAN ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    mysqli_query($conn, "DELETE FROM pelanggan WHERE id = $id");
    header("Location: pelanggan.php?status=success_delete");
    exit;
}

// --- FITUR 4: CARI PELANGGAN ---
$search = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$where_clause = $search ? "WHERE nama_pelanggan LIKE '%$search%' OR no_hp LIKE '%$search%' OR email LIKE '%$search%'" : "";

$query_pelanggan = "SELECT * FROM pelanggan $where_clause ORDER BY id DESC";
$res_pelanggan   = mysqli_query($conn, $query_pelanggan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantShop - Data Pelanggan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button id="mobile-menu-btn" onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-lg text-stone-600 hover:bg-stone-100">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <a href="dashboard.php" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm shadow-emerald-900/20">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Shop</span></span>
                </a>
            </div>

            <!-- Global Search Bar -->
            <form method="GET" action="pelanggan.php" class="hidden md:flex flex-1 max-w-md relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-400"></i>
                <input type="text" name="q" id="globalSearch" value="<?= htmlspecialchars($search) ?>" placeholder="Cari tanaman, pot, media tanam..." class="w-full pl-10 pr-4 py-2 text-sm bg-stone-100/70 border border-transparent rounded-full focus:outline-none focus:bg-white focus:border-[#2E7D32] transition-all placeholder:text-stone-400">
            </form>

            <!-- Right Utilities & Profile -->
            <div class="flex items-center gap-3 relative">
                <!-- Notifications Button -->
                <div class="relative">
                    <button onclick="toggleNotifications()" class="relative p-2 text-stone-600 hover:text-stone-900 hover:bg-stone-100 rounded-full transition-colors">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-[#D97706] rounded-full ring-2 ring-white"></span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-stone-100 p-4 space-y-3 z-50">
                        <div class="flex items-center justify-between border-b border-stone-100 pb-2">
                            <h4 class="font-bold text-sm text-stone-800">Notifikasi</h4>
                            <span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-semibold">2 Baru</span>
                        </div>
                        <div class="space-y-2 max-h-60 overflow-y-auto text-xs">
                            <div class="p-2 rounded-xl bg-amber-50/70 border border-amber-100">
                                <p class="font-semibold text-amber-900">Stok Kritis: Fiddle Leaf Fig</p>
                                <p class="text-amber-700 text-[11px] mt-0.5">Sisa stok 3 unit. Segera pesan ke supplier.</p>
                            </div>
                            <div class="p-2 rounded-xl bg-rose-50/70 border border-rose-100">
                                <p class="font-semibold text-rose-900">Stok Habis: Calathea Orbifolia</p>
                                <p class="text-rose-700 text-[11px] mt-0.5">Produk bernilai stok 0 dan dinonaktifkan di POS.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="h-6 w-px bg-stone-200 hidden sm:block"></div>

                <!-- Profile Dropdown Button -->
                <div class="relative">
                    <button onclick="toggleProfileMenu()" class="flex items-center gap-3 pl-1 focus:outline-none">
                        <div class="relative">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120" alt="Nabila" class="w-9 h-9 rounded-full object-cover ring-2 ring-[#2E7D32]/20">
                            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-stone-800 leading-tight">Nabila</p>
                            <p class="text-xs text-stone-500">Administrator</p>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-stone-400 hidden sm:block"></i>
                    </button>

                    <!-- Profile Dropdown Menu -->
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-stone-100 p-2 space-y-1 z-50">
                        <a href="profil.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl transition-colors">
                            <i data-lucide="user" class="w-4 h-4 text-stone-500"></i> Profil Saya
                        </a>
                        <a href="pengaturan.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-100 rounded-xl transition-colors">
                            <i data-lucide="settings" class="w-4 h-4 text-stone-500"></i> Pengaturan Toko
                        </a>
                        <hr class="border-stone-100 my-1">
                        <a href="logout.php" onclick="event.preventDefault(); alert('Logout Berhasil');" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4 text-rose-500"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- SIDEBAR NAVIGASI -->
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
                    <!-- Active Page: Pelanggan -->
                    <a href="pelanggan.php" class="flex items-center justify-between px-3 py-2.5 text-sm font-semibold text-[#2E7D32] bg-[#2E7D32]/10 rounded-xl transition-colors">
                        <div class="flex items-center gap-3">
                            <i data-lucide="users" class="w-4 h-4"></i> Pelanggan
                        </div>
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2E7D32]"></span>
                    </a>
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Laporan
                    </a>
                </nav>

                <hr class="border-stone-100">

                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-2">Pengaturan</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-stone-600 rounded-xl hover:bg-stone-100 transition-colors">
                        <i data-lucide="settings" class="w-4 h-4"></i> Pengaturan Toko
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
                        <p class="text-xs font-semibold text-stone-800 truncate">Cabang Bandung Central</p>
                        <p class="text-[11px] text-stone-500 truncate">Sistem Online Active</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Data Pelanggan</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Kelola daftar kontak pelanggan loyal PlantShop Anda.</p>
                </div>
                <button type="button" onclick="openModalTambah()" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm shadow-emerald-900/20 transition-all cursor-pointer">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Tambah Pelanggan</span>
                </button>
            </div>

            <!-- TABEL DATA PELANGGAN -->
            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6">Pelanggan</th>
                                <th class="py-3.5 px-6">No. Telepon</th>
                                <th class="py-3.5 px-6">Alamat</th>
                                <th class="py-3.5 px-6 text-center">Transaksi</th>
                                <th class="py-3.5 px-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs font-medium text-stone-700">
                            <?php if (mysqli_num_rows($res_pelanggan) > 0): ?>
                                <?php while ($p = mysqli_fetch_assoc($res_pelanggan)): ?>
                                    <tr class="hover:bg-stone-50/80 transition-colors">
                                        <td class="py-3.5 px-6 font-semibold text-stone-800">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-[#2E7D32] flex items-center justify-center font-bold">
                                                    <?= strtoupper(substr($p['nama_pelanggan'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-stone-800"><?= htmlspecialchars($p['nama_pelanggan']) ?></p>
                                                    <p class="text-stone-400 text-[11px]"><?= htmlspecialchars($p['email'] ?? '-') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-6 font-semibold text-stone-700"><?= htmlspecialchars($p['no_hp']) ?></td>
                                        <td class="py-3.5 px-6 text-stone-500 max-w-xs truncate"><?= htmlspecialchars($p['alamat'] ?? '-') ?></td>
                                        <td class="py-3.5 px-6 text-center">
                                            <span class="px-2.5 py-1 bg-emerald-50 text-[#2E7D32] rounded-lg font-bold border border-emerald-100">
                                                <?= $p['total_transaksi'] ?> Order
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-6 text-right space-x-1">
                                            <button type="button" onclick='openModalEdit(<?= json_encode($p) ?>)' class="p-2 text-stone-500 hover:text-stone-900 hover:bg-stone-100 rounded-lg transition-all">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <a href="pelanggan.php?action=delete&id=<?= $p['id'] ?>" onclick="return confirm('Yakin ingin menghapus pelanggan ini?')" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all inline-block">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-stone-400">Tidak ada data pelanggan ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL FORM PELANGGAN (TAMBAH / EDIT) -->
    <div id="modalPelanggan" class="fixed inset-0 bg-stone-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-stone-200 shadow-xl max-w-md w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                <h3 id="modalTitle" class="text-lg font-bold text-stone-800">Tambah Pelanggan Baru</h3>
                <button type="button" onclick="closeModal()" class="text-stone-400 hover:text-stone-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form method="POST" action="pelanggan.php" class="space-y-4">
                <input type="hidden" name="id_pelanggan" id="id_pelanggan">
                
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Nama Lengkap</label>
                    <input type="text" name="nama_pelanggan" id="nama_pelanggan" required class="w-full px-3.5 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">No. HP / WhatsApp</label>
                    <input type="text" name="no_hp" id="no_hp" required class="w-full px-3.5 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Email</label>
                    <input type="email" name="email" id="email" class="w-full px-3.5 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Alamat</label>
                    <textarea name="alamat" id="alamat" rows="2" class="w-full px-3.5 py-2 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32]"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-xs font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                    <button type="submit" name="tambah_pelanggan" id="btnSubmit" class="px-4 py-2 text-xs font-semibold bg-[#2E7D32] text-white rounded-xl hover:bg-emerald-800">Simpan Pelanggan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPT INTERAKSI -->
    <script>
        lucide.createIcons();

        // Toggle Sidebar Mobile
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar?.classList.toggle('hidden');
            sidebar?.classList.toggle('fixed');
            sidebar?.classList.toggle('inset-y-0');
            sidebar?.classList.toggle('left-0');
            sidebar?.classList.toggle('z-40');
        }

        // Toggle Dropdowns
        function toggleNotifications() {
            const notif = document.getElementById('notificationDropdown');
            const profile = document.getElementById('profileDropdown');
            profile?.classList.add('hidden');
            notif?.classList.toggle('hidden');
        }

        function toggleProfileMenu() {
            const profile = document.getElementById('profileDropdown');
            const notif = document.getElementById('notificationDropdown');
            notif?.classList.add('hidden');
            profile?.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            const notifDropdown = document.getElementById('notificationDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            
            if (!e.target.closest('#notificationDropdown') && !e.target.closest('button[onclick="toggleNotifications()"]')) {
                notifDropdown?.classList.add('hidden');
            }
            if (!e.target.closest('#profileDropdown') && !e.target.closest('button[onclick="toggleProfileMenu()"]')) {
                profileDropdown?.classList.add('hidden');
            }
        });

        // Modal Pelanggan Functions
        const modal = document.getElementById('modalPelanggan');

        function openModalTambah() {
            document.getElementById('modalTitle').innerText = 'Tambah Pelanggan Baru';
            document.getElementById('id_pelanggan').value = '';
            document.getElementById('nama_pelanggan').value = '';
            document.getElementById('no_hp').value = '';
            document.getElementById('email').value = '';
            document.getElementById('alamat').value = '';
            document.getElementById('btnSubmit').name = 'tambah_pelanggan';
            modal.classList.remove('hidden');
        }

        function openModalEdit(data) {
            document.getElementById('modalTitle').innerText = 'Edit Data Pelanggan';
            document.getElementById('id_pelanggan').value = data.id;
            document.getElementById('nama_pelanggan').value = data.nama_pelanggan;
            document.getElementById('no_hp').value = data.no_hp;
            document.getElementById('email').value = data.email || '';
            document.getElementById('alamat').value = data.alamat || '';
            document.getElementById('btnSubmit').name = 'edit_pelanggan';
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>