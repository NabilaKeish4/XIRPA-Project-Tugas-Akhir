<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// =========================================================
// HANDLE AKSI: TAMBAH / EDIT / HAPUS
// =========================================================
$sukses = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH
    if ($action === 'tambah') {
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama_kategori'] ?? ''));
        if ($nama === '') {
            $error = "Nama kategori wajib diisi.";
        } else {
            $cek = mysqli_query($conn, "SELECT id FROM kategori WHERE nama_kategori = '$nama'");
            if ($cek && mysqli_num_rows($cek) > 0) {
                $error = "Kategori '$nama' sudah ada.";
            } else {
                if (mysqli_query($conn, "INSERT INTO kategori (nama_kategori) VALUES ('$nama')")) {
                    header("Location: kategori.php?status=added");
                    exit;
                } else {
                    $error = "Gagal menambah: " . mysqli_error($conn);
                }
            }
        }
    }

    // EDIT
    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $nama = mysqli_real_escape_string($conn, trim($_POST['nama_kategori'] ?? ''));
        if ($id <= 0 || $nama === '') {
            $error = "Data tidak lengkap.";
        } else {
            $cek = mysqli_query($conn, "SELECT id FROM kategori WHERE nama_kategori = '$nama' AND id != $id");
            if ($cek && mysqli_num_rows($cek) > 0) {
                $error = "Kategori '$nama' sudah dipakai oleh kategori lain.";
            } else {
                if (mysqli_query($conn, "UPDATE kategori SET nama_kategori = '$nama' WHERE id = $id")) {
                    header("Location: kategori.php?status=edited");
                    exit;
                } else {
                    $error = "Gagal mengedit: " . mysqli_error($conn);
                }
            }
        }
    }

    // HAPUS
    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cek apakah kategori masih dipakai produk
            $qCek = mysqli_query($conn, "SELECT COUNT(*) AS total FROM produk WHERE kategori_id = $id");
            $dipakai = $qCek ? (int)mysqli_fetch_assoc($qCek)['total'] : 0;

            if ($dipakai > 0) {
                $error = "Kategori tidak bisa dihapus karena masih dipakai oleh $dipakai produk.";
            } else {
                if (mysqli_query($conn, "DELETE FROM kategori WHERE id = $id")) {
                    header("Location: kategori.php?status=deleted");
                    exit;
                } else {
                    $error = "Gagal menghapus: " . mysqli_error($conn);
                }
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added')   $sukses = "Kategori baru berhasil ditambahkan.";
    if ($_GET['status'] === 'edited')  $sukses = "Kategori berhasil diperbarui.";
    if ($_GET['status'] === 'deleted') $sukses = "Kategori berhasil dihapus.";
}

// =========================================================
// AMBIL DATA KATEGORI + JUMLAH PRODUK
// =========================================================
$kategoriList = [];
$qKategori = mysqli_query($conn, "
    SELECT k.*, 
           (SELECT COUNT(*) FROM produk p WHERE p.kategori_id = k.id) AS total_produk
    FROM kategori k
    ORDER BY k.id ASC
");
if ($qKategori) while ($r = mysqli_fetch_assoc($qKategori)) $kategoriList[] = $r;

// Statistik
$statTotal    = count($kategoriList);
$statDipakai  = 0;
$statKosong   = 0;
foreach ($kategoriList as $k) {
    if ((int)$k['total_produk'] > 0) $statDipakai++;
    else $statKosong++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Kelola Kategori</title>
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
                    <div class="w-9 h-9 rounded-xl bg-[#2E7D32] flex items-center justify-center text-white shadow-sm">
                        <i data-lucide="sprout" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-stone-800">Plant<span class="text-[#2E7D32]">Hub</span></span>
                </a>
            </div>

            <div class="hidden md:flex flex-1 max-w-md">
                <span class="text-xs text-stone-500 self-center">Kelola Kategori Produk</span>
            </div>

            <a href="dashboard.php" class="flex items-center gap-3 pl-1">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_nama) ?>&background=2E7D32&color=fff" class="w-9 h-9 rounded-full ring-2 ring-[#2E7D32]/20">
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-semibold text-stone-800 leading-tight"><?= htmlspecialchars($admin_nama) ?></p>
                    <p class="text-xs text-stone-500">Administrator</p>
                </div>
            </a>
        </div>
    </header>

    <div class="flex flex-1">
        <aside id="sidebar" class="w-64 bg-white border-r border-stone-200/80 hidden lg:flex flex-col justify-between shrink-0 p-4">
            <div class="space-y-6">
                <nav class="space-y-1">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">MAIN MENU</p>
                    <?php
                    $menu = [
                        ['url' => 'dashboard.php',  'icon' => 'layout-grid',    'label' => 'Dashboard',        'active' => false],
                        ['url' => 'pos.php',        'icon' => 'shopping-bag',   'label' => 'Kasir (POS)',      'active' => false],
                        ['url' => 'restock.php',    'icon' => 'truck',          'label' => 'Pembelian',        'active' => false],
                        ['url' => 'stok.php',       'icon' => 'box',            'label' => 'Stok & Produk',    'active' => false],
                        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => true],
                        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => false],
                        ['url' => 'pelanggan.php',  'icon' => 'users',          'label' => 'Pelanggan',        'active' => false],
                        ['url' => 'chat.php',       'icon' => 'message-square', 'label' => 'Konsultasi Chat',  'active' => false],
                        ['url' => 'transaksi.php',  'icon' => 'receipt',        'label' => 'Riwayat Transaksi','active' => false],
                        ['url' => 'laporan.php',    'icon' => 'bar-chart-2',    'label' => 'Laporan',          'active' => false],
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
                    <hr class="border-stone-100 my-3">
                    <p class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider mb-3">PENGATURAN</p>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="settings" class="w-5 h-5 text-stone-500"></i> Pengaturan Toko
                    </a>
                    <a href="bantuan.php" class="flex items-center gap-3 px-3 py-2.5 text-sm font-bold rounded-xl text-stone-700 hover:bg-stone-100">
                        <i data-lucide="help-circle" class="w-5 h-5 text-stone-500"></i> Bantuan
                    </a>
                </nav>
            </div>
            <div class="p-3 bg-stone-50/80 border border-stone-200/60 rounded-2xl flex items-center gap-3 mt-auto">
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 flex items-center justify-center text-[#2E7D32] shrink-0">
                    <i data-lucide="store" class="w-5 h-5"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-stone-800 truncate leading-tight">PlantHub Admin</p>
                    <p class="text-[11px] font-medium text-stone-400 truncate mt-0.5">Sistem Online</p>
                </div>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Kelola Kategori</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Atur kategori produk (Indoor, Outdoor, Pot, Media Tanam, dsb).</p>
                </div>
                <button onclick="openModalTambah()" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm self-start sm:self-auto">
                    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kategori
                </button>
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

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-[#2E7D32]">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Kategori</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $statTotal ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Terdaftar di sistem</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-emerald-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Dipakai Produk</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $statDipakai ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Ada produk terkait</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-stone-400">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Belum Dipakai</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $statKosong ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Bisa dihapus</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6 w-16 text-center">ID</th>
                                <th class="py-3.5 px-4">Nama Kategori</th>
                                <th class="py-3.5 px-4 text-center">Jumlah Produk</th>
                                <th class="py-3.5 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($kategoriList)): ?>
                                <tr>
                                    <td colspan="4" class="py-12 text-center">
                                        <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i data-lucide="tag" class="w-6 h-6 text-stone-400"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-stone-700">Belum ada kategori</p>
                                        <p class="text-xs text-stone-500 mt-1">Klik "Tambah Kategori" untuk mulai.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kategoriList as $k): ?>
                                    <tr class="hover:bg-stone-50/60 transition-colors">
                                        <td class="py-3.5 px-6 text-center font-mono text-stone-500">#<?= (int)$k['id'] ?></td>
                                        <td class="py-3.5 px-4 font-bold text-stone-800"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if ((int)$k['total_produk'] > 0): ?>
                                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-[#2E7D32] font-bold border border-emerald-100"><?= (int)$k['total_produk'] ?> produk</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-lg bg-stone-100 text-stone-500 font-semibold border border-stone-200">Kosong</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-6">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='openModalEdit(<?= json_encode(["id" => $k["id"], "nama_kategori" => $k["nama_kategori"]]) ?>)' class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-stone-700 bg-stone-100 hover:bg-stone-200 rounded-lg transition">
                                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit
                                                </button>
                                                <?php if ((int)$k['total_produk'] === 0): ?>
                                                    <button onclick='konfirmasiHapus(<?= (int)$k["id"] ?>, <?= json_encode($k["nama_kategori"]) ?>)' class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition">
                                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-stone-400 italic px-2">Tidak bisa dihapus</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL TAMBAH / EDIT -->
    <div id="modalKategori" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <h3 id="modalTitle" class="text-lg font-bold text-stone-800">Tambah Kategori</h3>
                <button onclick="closeModal()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="formKategori" method="POST" action="kategori.php" class="p-5 space-y-4">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId" value="">
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Kategori <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_kategori" id="inNama" required placeholder="Contoh: Indoor, Outdoor, Pot" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-stone-100">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                    <button type="submit" class="px-5 py-2 text-sm font-semibold bg-[#2E7D32] text-white rounded-xl hover:bg-emerald-800 inline-flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL HAPUS -->
    <form id="formHapus" method="POST" action="kategori.php" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <input type="hidden" name="action" value="hapus">
        <input type="hidden" name="id" id="hapusId">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 space-y-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6 text-rose-600"></i>
            </div>
            <div class="text-center">
                <h3 class="text-lg font-bold text-stone-800">Hapus Kategori?</h3>
                <p class="text-sm text-stone-500 mt-1">Kategori <b id="hapusNama" class="text-stone-800"></b> akan dihapus permanen.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="closeHapus()" class="flex-1 px-4 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 px-4 py-2 text-sm font-semibold bg-rose-600 text-white rounded-xl hover:bg-rose-700">Ya, Hapus</button>
            </div>
        </div>
    </form>

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

        const modal = document.getElementById('modalKategori');
        const form = document.getElementById('formKategori');

        function openModalTambah() {
            document.getElementById('modalTitle').innerText = 'Tambah Kategori';
            document.getElementById('formAction').value = 'tambah';
            document.getElementById('formId').value = '';
            form.reset();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openModalEdit(data) {
            document.getElementById('modalTitle').innerText = 'Edit Kategori';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = data.id;
            document.getElementById('inNama').value = data.nama_kategori;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        const modalHapus = document.getElementById('formHapus');
        function konfirmasiHapus(id, nama) {
            document.getElementById('hapusId').value = id;
            document.getElementById('hapusNama').innerText = nama;
            modalHapus.classList.remove('hidden');
            modalHapus.classList.add('flex');
        }
        function closeHapus() {
            modalHapus.classList.add('hidden');
            modalHapus.classList.remove('flex');
        }

        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        modalHapus.addEventListener('click', (e) => { if (e.target === modalHapus) closeHapus(); });
    </script>
</body>
</html>