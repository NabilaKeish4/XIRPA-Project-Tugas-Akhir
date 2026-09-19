<?php
session_start();
require_once '../Config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../Auth/login.php");
    exit;
}

$admin_nama = $_SESSION['nama_user'] ?? 'Admin';

// HANDLE AKSI
$sukses = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH
    if ($action === 'tambah') {
        $nama    = mysqli_real_escape_string($conn, trim($_POST['nama_supplier'] ?? ''));
        $kontak  = mysqli_real_escape_string($conn, trim($_POST['kontak'] ?? ''));
        $alamat  = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));

        if ($nama === '') {
            $error = "Nama supplier wajib diisi.";
        } else {
            $sql = "INSERT INTO supplier (nama_supplier, kontak, alamat) VALUES ('$nama', '$kontak', '$alamat')";
            if (mysqli_query($conn, $sql)) {
                header("Location: supplier.php?status=added");
                exit;
            } else {
                $error = "Gagal menambah: " . mysqli_error($conn);
            }
        }
    }

    // EDIT
    if ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $nama    = mysqli_real_escape_string($conn, trim($_POST['nama_supplier'] ?? ''));
        $kontak  = mysqli_real_escape_string($conn, trim($_POST['kontak'] ?? ''));
        $alamat  = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));

        if ($id <= 0 || $nama === '') {
            $error = "Data tidak lengkap.";
        } else {
            $sql = "UPDATE supplier SET nama_supplier = '$nama', kontak = '$kontak', alamat = '$alamat' WHERE id = $id";
            if (mysqli_query($conn, $sql)) {
                header("Location: supplier.php?status=edited");
                exit;
            } else {
                $error = "Gagal mengedit: " . mysqli_error($conn);
            }
        }
    }

    // HAPUS
    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cek apakah supplier masih dipakai transaksi pembelian
            $qCek = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE supplier_id = $id");
            $dipakai = $qCek ? (int)mysqli_fetch_assoc($qCek)['total'] : 0;

            if ($dipakai > 0) {
                $error = "Supplier tidak bisa dihapus karena sudah dipakai di $dipakai transaksi pembelian.";
            } else {
                if (mysqli_query($conn, "DELETE FROM supplier WHERE id = $id")) {
                    header("Location: supplier.php?status=deleted");
                    exit;
                } else {
                    $error = "Gagal menghapus: " . mysqli_error($conn);
                }
            }
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added')   $sukses = "Supplier baru berhasil ditambahkan.";
    if ($_GET['status'] === 'edited')  $sukses = "Supplier berhasil diperbarui.";
    if ($_GET['status'] === 'deleted') $sukses = "Supplier berhasil dihapus.";
}

// AMBIL DATA
$supplierList = [];
$qSupplier = mysqli_query($conn, "
    SELECT s.*,
           (SELECT COUNT(*) FROM transaksi t WHERE t.supplier_id = s.id) AS total_transaksi
    FROM supplier s
    ORDER BY s.id ASC
");
if ($qSupplier) while ($r = mysqli_fetch_assoc($qSupplier)) $supplierList[] = $r;

$statTotal = count($supplierList);
$statAktif = 0;
foreach ($supplierList as $s) {
    if ((int)$s['total_transaksi'] > 0) $statAktif++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlantHub - Kelola Supplier</title>
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
                <span class="text-xs text-stone-500 self-center">Kelola Data Supplier</span>
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
                        ['url' => 'kategori.php',   'icon' => 'tag',            'label' => 'Kategori',         'active' => false],
                        ['url' => 'supplier.php',   'icon' => 'building-2',     'label' => 'Supplier',         'active' => true],
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
                    <h1 class="text-2xl font-bold text-stone-800 tracking-tight">Kelola Supplier</h1>
                    <p class="text-sm text-stone-500 mt-0.5">Daftar supplier/vendor yang menyuplai tanaman dan barang toko.</p>
                </div>
                <button onclick="openModalTambah()" class="inline-flex items-center gap-2 bg-[#2E7D32] text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm self-start sm:self-auto">
                    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Supplier
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
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Supplier</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $statTotal ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Terdaftar</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-emerald-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Supplier Aktif</p>
                    <p class="text-2xl font-bold text-stone-800 mt-1"><?= $statAktif ?></p>
                    <p class="text-[11px] text-stone-400 mt-1">Pernah transaksi</p>
                </div>
                <div class="bg-white rounded-2xl p-5 border border-stone-200/80 shadow-sm border-t-4 border-t-blue-500">
                    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Info</p>
                    <p class="text-xs text-stone-600 mt-2 leading-relaxed">Supplier dipakai di menu <b>Pembelian (Restock)</b>.</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/50 text-[11px] font-bold text-stone-500 uppercase tracking-wider">
                                <th class="py-3.5 px-6 w-16 text-center">ID</th>
                                <th class="py-3.5 px-4">Nama Supplier</th>
                                <th class="py-3.5 px-4">Kontak</th>
                                <th class="py-3.5 px-4">Alamat</th>
                                <th class="py-3.5 px-4 text-center">Transaksi</th>
                                <th class="py-3.5 px-6 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 text-xs">
                            <?php if (empty($supplierList)): ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center">
                                        <div class="w-14 h-14 bg-stone-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i data-lucide="building-2" class="w-6 h-6 text-stone-400"></i>
                                        </div>
                                        <p class="text-sm font-semibold text-stone-700">Belum ada supplier</p>
                                        <p class="text-xs text-stone-500 mt-1">Klik "Tambah Supplier" untuk mulai.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($supplierList as $s): ?>
                                    <tr class="hover:bg-stone-50/60 transition-colors">
                                        <td class="py-3.5 px-6 text-center font-mono text-stone-500">#<?= (int)$s['id'] ?></td>
                                        <td class="py-3.5 px-4">
                                            <p class="font-bold text-stone-800"><?= htmlspecialchars($s['nama_supplier']) ?></p>
                                        </td>
                                        <td class="py-3.5 px-4 text-stone-600"><?= htmlspecialchars($s['kontak'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-stone-500 max-w-xs truncate"><?= htmlspecialchars($s['alamat'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if ((int)$s['total_transaksi'] > 0): ?>
                                                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-[#2E7D32] font-bold border border-emerald-100"><?= (int)$s['total_transaksi'] ?>x</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-lg bg-stone-100 text-stone-500 font-semibold border border-stone-200">Belum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-6">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick='openModalEdit(<?= json_encode($s) ?>)' class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-stone-700 bg-stone-100 hover:bg-stone-200 rounded-lg transition">
                                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit
                                                </button>
                                                <?php if ((int)$s['total_transaksi'] === 0): ?>
                                                    <button onclick='konfirmasiHapus(<?= (int)$s["id"] ?>, <?= json_encode($s["nama_supplier"]) ?>)' class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition">
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
    <div id="modalSupplier" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-5 border-b border-stone-100 flex items-center justify-between">
                <h3 id="modalTitle" class="text-lg font-bold text-stone-800">Tambah Supplier</h3>
                <button onclick="closeModal()" class="text-stone-400 hover:text-stone-700 p-1.5 rounded-lg hover:bg-stone-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="formSupplier" method="POST" action="supplier.php" class="p-5 space-y-4">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId" value="">

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Nama Supplier <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_supplier" id="inNama" required placeholder="Contoh: CV. Flora Utama" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Kontak (No. HP/Telp)</label>
                    <input type="text" name="kontak" id="inKontak" placeholder="08123456789" class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-2">Alamat</label>
                    <textarea name="alamat" id="inAlamat" rows="3" placeholder="Alamat lengkap supplier..." class="w-full px-4 py-2.5 text-sm bg-stone-50 border border-stone-200 rounded-xl focus:outline-none focus:bg-white focus:border-[#2E7D32] resize-none"></textarea>
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
    <form id="formHapus" method="POST" action="supplier.php" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <input type="hidden" name="action" value="hapus">
        <input type="hidden" name="id" id="hapusId">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 space-y-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6 text-rose-600"></i>
            </div>
            <div class="text-center">
                <h3 class="text-lg font-bold text-stone-800">Hapus Supplier?</h3>
                <p class="text-sm text-stone-500 mt-1">Supplier <b id="hapusNama" class="text-stone-800"></b> akan dihapus permanen.</p>
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

        const modal = document.getElementById('modalSupplier');
        const form = document.getElementById('formSupplier');

        function openModalTambah() {
            document.getElementById('modalTitle').innerText = 'Tambah Supplier';
            document.getElementById('formAction').value = 'tambah';
            document.getElementById('formId').value = '';
            form.reset();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openModalEdit(data) {
            document.getElementById('modalTitle').innerText = 'Edit Supplier';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = data.id;
            document.getElementById('inNama').value = data.nama_supplier || '';
            document.getElementById('inKontak').value = data.kontak || '';
            document.getElementById('inAlamat').value = data.alamat || '';
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