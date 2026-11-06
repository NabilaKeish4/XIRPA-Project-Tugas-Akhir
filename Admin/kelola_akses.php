<?php
require_once __DIR__ . '/includes/init.php';
wajibAkses('pengaturan'); // Hanya admin yang boleh kelola akses

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $act = $_POST['act'] ?? '';

    // ==========================================
    // PROSES TAMBAH STAF BARU
    // ==========================================
    if ($act === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = trim($_POST['role'] ?? ''); // Ambil dari form
        $pass     = $_POST['password'] ?? '';

        // Validasi: Jika role kosong, jangan simpan!
        if ($role === '') {
            $err = 'GAGAL: Kamu belum memilih Role / Jabatan di form!';
        } elseif ($username === '' || $pass === '' || $nama === '') {
            $err = 'Nama, Username, dan Password wajib diisi.';
        } elseif (!in_array($role, daftarRole(), true)) {
            $err = 'Role tidak valid (Sistem menolak).';
        } else {
            $u = esc_sql($username);
            $e = esc_sql($email);
            $r = esc_sql($role); // Simpan role yang sudah aman
            
            // Cek duplikat
            $cek = mysqli_query($conn, "SELECT id FROM users WHERE username = '$u' OR (email != '' AND email = '$e') LIMIT 1");
            if ($cek && mysqli_num_rows($cek) > 0) {
                $err = 'Username atau Email sudah dipakai.';
            } else {
                $n = esc_sql($nama);
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $h = esc_sql($hash);
                
                // Eksekusi simpan
                $sql = "INSERT INTO users (nama_lengkap, username, email, password, role, aktif) VALUES ('$n', '$u', '$e', '$h', '$r', 1)";
                
                if (mysqli_query($conn, $sql)) {
                    $msg = 'Akun berhasil ditambahkan dengan role: ' . labelRole($role);
                } else {
                    $err = 'Gagal Database: ' . mysqli_error($conn);
                }
            }
        }
    }

    // ==========================================
    // PROSES RESET PASSWORD
    // ==========================================
    if ($act === 'reset_pass') {
        $id = (int)($_POST['id'] ?? 0);
        $pass = $_POST['password'] ?? '';
        if ($id > 0 && $pass !== '') {
            $hash = esc_sql(password_hash($pass, PASSWORD_DEFAULT));
            mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = $id");
            $msg = 'Password berhasil diubah.';
        } else {
            $err = 'Password tidak boleh kosong.';
        }
    }

    // ==========================================
    // PROSES BANNED / AKTIFKAN
    // ==========================================
    if ($act === 'toggle_aktif') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== $admin_id) {
            mysqli_query($conn, "UPDATE users SET aktif = 1 - aktif WHERE id = $id");
            $msg = 'Status akun diperbarui.';
        } else { $err = 'Tidak bisa menonaktifkan diri sendiri.'; }
    }

    // ==========================================
    // PROSES HAPUS
    // ==========================================
    if ($act === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== $admin_id) {
            mysqli_query($conn, "DELETE FROM users WHERE id = $id AND role != 'admin'"); 
            $msg = 'Akun dihapus.';
        } else { $err = 'Tidak bisa menghapus akun Anda sendiri.'; }
    }
}

// Filter Role untuk Tampilan
$filterRole = $_GET['role'] ?? '';
$where = '';
if ($filterRole !== '' && in_array($filterRole, daftarRole(), true)) {
    $fr = esc_sql($filterRole);
    $where = "WHERE role = '$fr'";
}

// Ambil Data User
$qUsers = mysqli_query($conn, "SELECT id, username, nama_lengkap, email, role, aktif FROM users $where ORDER BY role ASC, nama_lengkap ASC");
$users = [];
if ($qUsers) while ($r = mysqli_fetch_assoc($qUsers)) $users[] = $r;

// Hitung Total per Role
$countRole = [];
$qCnt = mysqli_query($conn, "SELECT role, COUNT(*) AS jml FROM users GROUP BY role");
if ($qCnt) while ($r = mysqli_fetch_assoc($qCnt)) $countRole[$r['role']] = (int)$r['jml'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Akses Staf - <?= e($nama_toko) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#F9F8F6;}</style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- TOPBAR SEDERHANA (Khusus Kelola Akses) -->
    <header class="sticky top-0 z-30 bg-white border-b border-stone-200/80 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="pengaturan.php" class="p-2 rounded-lg text-stone-600 hover:bg-stone-100" title="Kembali ke Pengaturan">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h1 class="text-base font-bold text-stone-800 leading-tight">Kelola Akses Staf</h1>
                    <p class="text-[11px] text-stone-500">Atur akun & hak akses</p>
                </div>
            </div>
            
            <button onclick="document.getElementById('modalTambah').classList.replace('hidden','flex')" class="bg-[#2E7D32] hover:bg-emerald-800 text-white px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-2 shadow-sm transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Staf
            </button>
        </div>
    </header>

    <main class="max-w-5xl mx-auto p-6 space-y-4 w-full">
        <?php if ($msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-3 rounded-xl"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3 rounded-xl"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <!-- TABS FILTER ROLE -->
        <div class="flex gap-2 flex-wrap mb-4">
            <a href="kelola_akses.php" class="px-4 py-2 rounded-xl text-xs font-bold <?= $filterRole==='' ? 'bg-[#2E7D32] text-white shadow-md' : 'bg-white border border-stone-200 text-stone-600 hover:bg-stone-100' ?> transition">Semua (<?= array_sum($countRole) ?>)</a>
            <?php foreach (daftarRole() as $r): ?>
                <a href="kelola_akses.php?role=<?= $r ?>" class="px-4 py-2 rounded-xl text-xs font-bold <?= $filterRole===$r ? 'bg-[#2E7D32] text-white shadow-md' : 'bg-white border border-stone-200 text-stone-600 hover:bg-stone-100' ?> transition">
                    <?= labelRole($r) ?> (<?= $countRole[$r] ?? 0 ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <!-- TABEL DAFTAR STAF -->
        <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-stone-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="text-left px-4 py-3">Nama Staf</th>
                            <th class="text-left px-4 py-3">Username / Email</th>
                            <th class="text-left px-4 py-3">Role</th>
                            <th class="text-left px-4 py-3 text-center">Status</th>
                            <th class="text-right px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" class="text-center py-10 text-stone-400 text-xs">Belum ada akun.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-stone-50/50">
                            <td class="px-4 py-3 font-semibold text-stone-800"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                            <td class="px-4 py-3 text-stone-600">
                                <div class="font-mono text-xs font-bold text-stone-800"><?= htmlspecialchars($u['username']) ?></div>
                                <div class="text-[10px]"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                    <?= $u['role'] ? labelRole($u['role']) : '<span class="text-rose-500">Kosong</span>' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($u['aktif']): ?>
                                    <span class="text-emerald-600 text-xs font-bold">● Aktif</span>
                                <?php else: ?>
                                    <span class="text-stone-400 text-xs font-bold">○ Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right space-x-1">
                                <button onclick="openReset(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['nama_lengkap'], ENT_QUOTES) ?>')" class="text-[10px] px-2 py-1.5 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 font-bold transition">Ganti Pass</button>
                                
                                <?php if ((int)$u['id'] !== $admin_id): ?>
                                <form method="post" action="kelola_akses.php" class="inline" onsubmit="return confirm('Ubah status aktif akun?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="toggle_aktif">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="text-[10px] px-2 py-1.5 rounded bg-stone-100 text-stone-700 hover:bg-stone-200 font-bold transition"><?= $u['aktif'] ? 'Banned' : 'Aktifkan' ?></button>
                                </form>
                                <form method="post" action="kelola_akses.php" class="inline" onsubmit="return confirm('Hapus akun ini permanen?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="act" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="text-[10px] px-2 py-1.5 rounded bg-rose-50 text-rose-700 hover:bg-rose-100 font-bold transition">Hapus</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL TAMBAH STAF -->
    <div id="modalTambah" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-base font-bold text-stone-800 mb-4">Tambah Staf Baru</h3>
            <form method="POST" action="kelola_akses.php" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="tambah">
                <div>
                    <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Nama Lengkap</label>
                    <input name="nama" required class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] bg-stone-50">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Username</label>
                    <input name="username" required class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] bg-stone-50">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Email <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" required class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] bg-stone-50">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Role / Jabatan <span class="text-rose-500">*</span></label>
                    <select name="role" required class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] bg-stone-50">
    <option value="">-- Pilih Role --</option>
    <?php foreach (daftarRole() as $r): ?>
        <option value="<?= $r ?>"><?= labelRole($r) ?></option>
    <?php endforeach; ?>
</select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Password</label>
                    <input name="password" type="password" required class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] bg-stone-50">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modalTambah').classList.replace('flex','hidden')" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 py-2.5 rounded-xl text-xs font-bold transition">Batal</button>
                    <button type="submit" class="flex-1 bg-[#2E7D32] hover:bg-emerald-800 text-white py-2.5 rounded-xl text-xs font-bold transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL RESET PASSWORD -->
    <div id="modalReset" class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-base font-bold text-stone-800 mb-1">Ganti Password</h3>
            <p id="resetTarget" class="text-xs text-stone-500 mb-4"></p>
            <form method="POST" action="kelola_akses.php" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="act" value="reset_pass">
                <input type="hidden" name="id" id="resetId">
                <div>
                    <label class="block text-[10px] font-bold text-stone-500 uppercase mb-1">Password Baru</label>
                    <input name="password" type="password" required class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:outline-none focus:border-[#2E7D32] bg-stone-50">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modalReset').classList.replace('flex','hidden')" class="flex-1 bg-stone-100 hover:bg-stone-200 text-stone-700 py-2.5 rounded-xl text-xs font-bold transition">Batal</button>
                    <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-xl text-xs font-bold transition">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function openReset(id, nama) {
            document.getElementById('resetId').value = id;
            document.getElementById('resetTarget').innerText = 'Akun: ' + nama;
            document.getElementById('modalReset').classList.replace('hidden','flex');
        }
    </script>
</body>
</html>